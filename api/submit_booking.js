const crypto = require('crypto');
const Busboy = require('busboy');
const { getPool } = require('./_db');

function json(res, statusCode, payload) {
  res.status(statusCode).json(payload);
}

function randomBookingRef() {
  // 8 hex chars -> 32-bit collision resistance is plenty for this use.
  const code = crypto.randomBytes(4).toString('hex').toUpperCase();
  return `BK-${code}`;
}

async function generateUniqueBookingRef() {
  // Try a few times to avoid rare collisions with the UNIQUE constraint.
  for (let i = 0; i < 5; i++) {
    const booking_ref = randomBookingRef();
    const check = await getPool().query('SELECT id FROM bookings WHERE booking_ref = $1 LIMIT 1', [booking_ref]);
    if (check.rows.length === 0) return booking_ref;
  }
  throw new Error('Could not generate unique booking reference');
}

module.exports = async (req, res) => {
  if (req.method !== 'POST') return json(res, 405, { success: false, message: 'Method not allowed.' });

  const contentType = req.headers['content-type'] || '';
  if (!contentType.includes('multipart/form-data')) {
    // The current frontend sends FormData (multipart).
    return json(res, 400, { success: false, message: 'Invalid content type. Expected multipart/form-data.' });
  }

  const maxReceiptBytes = 5 * 1024 * 1024;
  const settingsBedPrice = Number(process.env.BED_PRICE || 1600);

  const fields = {};
  let receipt = null; // { filename, mimeType, size, buffer? }

  const bb = Busboy({
    headers: req.headers,
    limits: {
      files: 1,
      fileSize: maxReceiptBytes,
    },
  });

  bb.on('field', (name, val) => {
    fields[name] = val;
  });

  bb.on('file', (name, file, info) => {
    // We need to consume the stream. We won't persist the binary to disk on Vercel.
    // For now, we only store a lightweight receipt_path label (filename + mime).
    if (name === 'receipt') {
      const chunks = [];
      let size = 0;

      file.on('data', (d) => {
        size += d.length;
        chunks.push(d);
      });

      file.on('limit', () => {
        // busboy will throw later; keep for clarity
      });

      file.on('end', () => {
        const buffer = Buffer.concat(chunks);
        receipt = {
          filename: info.filename || 'receipt',
          mimeType: info.mimeType || info.mime_type || 'application/octet-stream',
          size,
          buffer,
        };
      });
    } else {
      // Unknown file input; discard
      file.resume();
    }
  });

  bb.on('error', (e) => {
    return json(res, 500, { success: false, message: 'Upload parsing error: ' + e.message });
  });

  const parsed = new Promise((resolve, reject) => {
    bb.on('finish', () => resolve(true));
    bb.on('fileSizeLimit', () => reject(new Error('Receipt file exceeds 5 MB limit.')));
  });

  try {
    await parsed;
  } catch (e) {
    return json(res, 400, { success: false, message: e.message });
  }

  const bed_id = parseInt(fields.bed_id || '0', 10);
  const full_name = String(fields.full_name || '').trim();
  const category = String(fields.category || '').trim();
  const school_name = String(fields.school_name || '').trim();
  const contact_number = String(fields.contact_number || '').trim();
  const guardian_name = String(fields.guardian_name || '').trim();
  const guardian_contact = String(fields.guardian_contact || '').trim();
  const payment_method = String(fields.payment_method || '').trim();

  if (!bed_id || !full_name || !category || !contact_number || !guardian_name || !guardian_contact || !payment_method) {
    return json(res, 400, { success: false, message: 'All required fields must be filled in.' });
  }
  if (!['Reviewer', 'College', 'High School'].includes(category)) {
    return json(res, 400, { success: false, message: 'Invalid category.' });
  }
  if (!['GCash Online', 'Cash In'].includes(payment_method)) {
    return json(res, 400, { success: false, message: 'Invalid payment method.' });
  }

  if (payment_method === 'GCash Online') {
    if (!receipt || !receipt.buffer) {
      return json(res, 400, { success: false, message: 'GCash receipt is required.' });
    }
  }

  // Transaction-like workflow: reserve bed first, then insert booking.
  const client = await getPool().connect();
  try {
    await client.query('BEGIN');

    // Confirm bed is still available (prevents double-booking race).
    const bedRes = await client.query(
      'SELECT id, status FROM beds WHERE id = $1 FOR UPDATE',
      [bed_id]
    );

    const bed = bedRes.rows[0];
    if (!bed) {
      await client.query('ROLLBACK');
      return json(res, 400, { success: false, message: 'Bed not found.' });
    }
    if (bed.status !== 'Available') {
      await client.query('ROLLBACK');
      return json(res, 409, { success: false, message: 'Sorry, that bed was just taken. Please choose another.' });
    }

    // Update bed -> Reserved
    await client.query(
      'UPDATE beds SET status = $1, reserved_at = NOW() WHERE id = $2',
      ['Reserved', bed_id]
    );

    const booking_ref = await generateUniqueBookingRef();

    // Light-weight receipt_path: since schema has a varchar(255), we store just metadata.
    // (Storing the full binary would require Supabase Storage and is outside this fix.)
    let receipt_path = null;
    if (receipt) {
      receipt_path = `receipt:${receipt.mimeType}:${receipt.filename}`;
    }

    await client.query(
      `
      INSERT INTO bookings
        (booking_ref, bed_id,
         full_name, category, school_name,
         contact_number, guardian_name, guardian_contact,
         payment_method, receipt_path,
         booking_status, payment_status,
         reserve_at, due_date, monthly_rent, current_balance)
      VALUES
        ($1, $2,
         $3, $4, $5,
         $6, $7, $8,
         $9, $10,
         'Active', 'Pending',
         NOW(),
         NOW() + INTERVAL '1 month',
         $11, $11)
      `,
      [
        booking_ref,
        bed_id,
        full_name,
        category,
        school_name,
        contact_number,
        guardian_name,
        guardian_contact,
        payment_method,
        receipt_path,
        settingsBedPrice,
      ]
    );

    await client.query('COMMIT');
    return json(res, 200, { success: true, booking_ref });
  } catch (e) {
    await client.query('ROLLBACK');
    return json(res, 500, { success: false, message: 'Booking failed: ' + e.message });
  } finally {
    client.release();
  }
};

