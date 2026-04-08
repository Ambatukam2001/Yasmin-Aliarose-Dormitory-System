const { pool } = require('./_db');

function json(res, status, payload) {
  res.status(status).json(payload);
}

async function fetchBookings(filter) {
  if (filter === 'archive') {
    // No soft-delete column in schema; just return none for now.
    return [];
  }

  let where = '';
  const params = [];

  if (filter && filter !== 'all' && filter !== 'Overdue') {
    params.push(filter, filter);
    where = 'WHERE payment_status = $1 OR booking_status = $2';
  }

  const q = `
    SELECT
      b.*,
      r.room_no,
      r.floor_no
    FROM bookings b
    LEFT JOIN beds d ON b.bed_id = d.id
    LEFT JOIN rooms r ON d.room_id = r.id
    ${where}
    ORDER BY b.created_at DESC
  `;
  const result = await pool.query(q, params);
  return result.rows;
}

module.exports = async (req, res) => {
  const method = req.method;

  try {
    if (method === 'GET') {
      const filter = (req.query.filter || 'all').toString();
      const rows = await fetchBookings(filter);
      return json(res, 200, { success: true, bookings: rows });
    }

    if (method !== 'POST') {
      return json(res, 405, { success: false, message: 'Method not allowed' });
    }

    const action = (req.query.action || '').toString();
    if (!action) return json(res, 400, { success: false, message: 'Missing action' });

    const { id } = req.body || {};
    const bookingId = parseInt(id || '0', 10);
    if (!bookingId) return json(res, 400, { success: false, message: 'Invalid booking id' });

    if (action === 'accept') {
      await pool.query(
        "UPDATE bookings SET booking_status = 'Active', payment_status = 'Confirmed' WHERE id = $1",
        [bookingId]
      );
      return json(res, 200, { success: true });
    }

    if (action === 'decline') {
      await pool.query(
        "UPDATE bookings SET booking_status = 'Cancelled', payment_status = 'Declined' WHERE id = $1",
        [bookingId]
      );
      return json(res, 200, { success: true });
    }

    if (action === 'checkout') {
      // Mark completed and free bed.
      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        const bRes = await client.query('SELECT bed_id FROM bookings WHERE id = $1', [bookingId]);
        const booking = bRes.rows[0];

        await client.query(
          "UPDATE bookings SET booking_status = 'Completed', payment_status = 'Cleared' WHERE id = $1",
          [bookingId]
        );

        if (booking && booking.bed_id) {
          await client.query(
            "UPDATE beds SET status = 'Available', reserved_at = NULL WHERE id = $1",
            [booking.bed_id]
          );
        }
        await client.query('COMMIT');
      } catch (e) {
        await pool.query('ROLLBACK');
        throw e;
      } finally {
        // eslint-disable-next-line no-unsafe-finally
        client && client.release();
      }
      return json(res, 200, { success: true });
    }

    if (action === 'payment') {
      const { amount, next_due, method: payMethod } = req.body || {};
      const amt = parseFloat(amount || '0');
      if (!amt) return json(res, 400, { success: false, message: 'Invalid amount' });

      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        await client.query(
          `
          INSERT INTO payments (booking_id, amount, payment_mode)
          VALUES ($1,$2,$3)
          `,
          [bookingId, amt, payMethod || 'Manual (Admin)']
        );

        let dueDateSql = 'NOW() + INTERVAL \'1 month\'';
        const params = [bookingId, 'Confirmed'];
        if (next_due) {
          dueDateSql = '$3::timestamp';
          params.push(next_due);
        }

        await client.query(
          `
          UPDATE bookings
          SET payment_status = $2,
              due_date       = ${dueDateSql}
          WHERE id = $1
          `,
          params
        );

        await client.query('COMMIT');
      } catch (e) {
        await pool.query('ROLLBACK');
        throw e;
      } finally {
        // eslint-disable-next-line no-unsafe-finally
        client && client.release();
      }
      return json(res, 200, { success: true });
    }

    return json(res, 400, { success: false, message: 'Invalid action' });
  } catch (e) {
    return json(res, 500, { success: false, message: e.message });
  }
};

