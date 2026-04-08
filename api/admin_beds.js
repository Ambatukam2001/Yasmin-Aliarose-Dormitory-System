const { pool } = require('./_db');

function json(res, status, payload) {
  res.status(status).json(payload);
}

module.exports = async (req, res) => {
  if (req.method !== 'POST') return json(res, 405, { success: false, message: 'Method not allowed' });

  const action = (req.query.action || '').toString();
  if (!action) return json(res, 400, { success: false, message: 'Missing action' });

  try {
    if (action === 'toggle') {
      const { bed_id, status } = req.body || {};
      const bedId = parseInt(bed_id || '0', 10);
      const newStatus = (status || '').trim();
      if (!bedId || !['Available', 'Reserved', 'Occupied'].includes(newStatus)) {
        return json(res, 400, { success: false, message: 'Invalid parameters.' });
      }

      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        const bedRes = await client.query('SELECT room_id FROM beds WHERE id = $1 FOR UPDATE', [bedId]);
        const bed = bedRes.rows[0];
        if (!bed) {
          await client.query('ROLLBACK');
          return json(res, 404, { success: false, message: 'Bed not found.' });
        }

        if (newStatus === 'Occupied') {
          await client.query(
            "UPDATE beds SET status = 'Occupied', reserved_at = NOW() WHERE id = $1",
            [bedId]
          );
        } else {
          await client.query(
            'UPDATE beds SET status = $1, reserved_at = NULL WHERE id = $2',
            [newStatus, bedId]
          );
        }

        const reservedRes = await client.query(
          `
          SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) AS occupied
          FROM beds
          WHERE room_id = $1
          `,
          [bed.room_id]
        );
        const row = reservedRes.rows[0];
        const total = Number(row.total || 0);
        const occupied = Number(row.occupied || 0);
        const vacancies = total - occupied;
        const pct = total > 0 ? Math.round((occupied / total) * 100) : 0;
        const is_full = total > 0 && vacancies <= 0;

        await client.query(
          'UPDATE rooms SET status = $1 WHERE id = $2',
          [is_full ? 'Full' : 'Available', bed.room_id]
        );

        await client.query('COMMIT');

        return json(res, 200, {
          success: true,
          room_summary: {
            room_id: bed.room_id,
            total,
            occupied,
            vacancies,
            pct,
            is_full,
          },
        });
      } catch (e) {
        await pool.query('ROLLBACK');
        throw e;
      } finally {
        // eslint-disable-next-line no-unsafe-finally
        client && client.release();
      }
    }

    if (action === 'add') {
      const { room_id } = req.body || {};
      const roomId = parseInt(room_id || '0', 10);
      if (!roomId) return json(res, 400, { success: false, message: 'Invalid room.' });

      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        const roomRes = await client.query('SELECT floor_no FROM rooms WHERE id = $1 FOR UPDATE', [roomId]);
        const room = roomRes.rows[0];
        if (!room) {
          await client.query('ROLLBACK');
          return json(res, 404, { success: false, message: 'Room not found.' });
        }

        const maxRes = await client.query(
          'SELECT COALESCE(MAX(bed_no), 0) AS max_bed FROM beds WHERE room_id = $1',
          [roomId]
        );
        const nextBed = Number(maxRes.rows[0].max_bed || 0) + 1;

        const insertRes = await client.query(
          "INSERT INTO beds (room_id, floor_id, bed_no, status) VALUES ($1,$2,$3,'Available') RETURNING id",
          [roomId, room.floor_no, nextBed]
        );
        const newBedId = insertRes.rows[0].id;

        await client.query('UPDATE rooms SET capacity = capacity + 1 WHERE id = $1', [roomId]);

        const statRes = await client.query(
          `
          SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) AS occupied
          FROM beds WHERE room_id = $1
          `,
          [roomId]
        );
        const s = statRes.rows[0];
        const total = Number(s.total || 0);
        const occupied = Number(s.occupied || 0);

        const vacancies = total - occupied;
        const pct = total > 0 ? Math.round((occupied / total) * 100) : 0;
        const is_full = total > 0 && vacancies <= 0;

        await client.query(
          'UPDATE rooms SET status = $1 WHERE id = $2',
          [is_full ? 'Full' : 'Available', roomId]
        );

        await client.query('COMMIT');

        return json(res, 200, {
          success: true,
          bed: { id: newBedId, bed_no: nextBed, status: 'Available' },
          room_summary: {
            room_id: roomId,
            total,
            occupied,
            vacancies,
            pct,
            is_full,
          },
        });
      } catch (e) {
        await pool.query('ROLLBACK');
        throw e;
      } finally {
        // eslint-disable-next-line no-unsafe-finally
        client && client.release();
      }
    }

    if (action === 'delete') {
      const { bed_id } = req.body || {};
      const bedId = parseInt(bed_id || '0', 10);
      if (!bedId) return json(res, 400, { success: false, message: 'Invalid bed id.' });

      const client = await pool.connect();
      try {
        await client.query('BEGIN');

        const bedRes = await client.query('SELECT * FROM beds WHERE id = $1 FOR UPDATE', [bedId]);
        const bed = bedRes.rows[0];
        if (!bed) {
          await client.query('ROLLBACK');
          return json(res, 404, { success: false, message: 'Bed not found.' });
        }
        if (bed.status === 'Occupied') {
          await client.query('ROLLBACK');
          return json(res, 400, { success: false, message: 'Cannot delete an occupied bed. Release it first.' });
        }

        const activeRes = await client.query(
          `
          SELECT COUNT(*) AS cnt
          FROM bookings
          WHERE bed_id = $1 AND booking_status = 'Active'
          `,
          [bedId]
        );
        if (Number(activeRes.rows[0].cnt || 0) > 0) {
          await client.query('ROLLBACK');
          return json(res, 400, { success: false, message: 'This bed has an active booking. Cancel the booking first.' });
        }

        await client.query('DELETE FROM beds WHERE id = $1', [bedId]);

        await client.query(
          'UPDATE rooms SET capacity = GREATEST(0, capacity - 1) WHERE id = $1',
          [bed.room_id]
        );

        const statRes = await client.query(
          `
          SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) AS occupied
          FROM beds WHERE room_id = $1
          `,
          [bed.room_id]
        );
        const s = statRes.rows[0];
        const total = Number(s.total || 0);
        const occupied = Number(s.occupied || 0);
        const vacancies = total - occupied;
        const pct = total > 0 ? Math.round((occupied / total) * 100) : 0;
        const is_full = total > 0 && vacancies <= 0;

        await client.query(
          'UPDATE rooms SET status = $1 WHERE id = $2',
          [is_full ? 'Full' : 'Available', bed.room_id]
        );

        await client.query('COMMIT');

        return json(res, 200, {
          success: true,
          room_summary: {
            room_id: bed.room_id,
            total,
            occupied,
            vacancies,
            pct,
            is_full,
          },
        });
      } catch (e) {
        await pool.query('ROLLBACK');
        throw e;
      } finally {
        // eslint-disable-next-line no-unsafe-finally
        client && client.release();
      }
    }

    return json(res, 400, { success: false, message: 'Invalid action' });
  } catch (e) {
    return json(res, 500, { success: false, message: e.message });
  }
};

