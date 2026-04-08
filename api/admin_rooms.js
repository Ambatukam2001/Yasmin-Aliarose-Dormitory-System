const { getPool } = require('./_db');

function json(res, status, payload) {
  res.status(status).json(payload);
}

module.exports = async (req, res) => {
  if (req.method !== 'POST') return json(res, 405, { success: false, message: 'Method not allowed' });

  const action = (req.query.action || '').toString();
  if (!action) return json(res, 400, { success: false, message: 'Missing action' });

  try {
    const pool = getPool();
    if (action === 'add') {
      const { room_no, floor_no, beds_count } = req.body || {};
      const roomNo = (room_no || '').toString().trim();
      const floorNo = parseInt(floor_no || '2', 10);
      const bedsCount = parseInt(beds_count || '0', 10);

      if (!roomNo || Number.isNaN(floorNo) || bedsCount < 0) {
        return json(res, 400, { success: false, message: 'Invalid inputs.' });
      }

      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        const roomRes = await client.query(
          'INSERT INTO rooms (room_no, floor_no, capacity, status) VALUES ($1,$2,$3,$4) RETURNING id',
          [roomNo, floorNo, bedsCount, 'Available']
        );
        const roomId = roomRes.rows[0].id;

        for (let i = 1; i <= bedsCount; i += 1) {
          await client.query(
            "INSERT INTO beds (room_id, floor_id, bed_no, status) VALUES ($1,$2,$3,'Available')",
            [roomId, floorNo, i]
          );
        }

        await client.query('COMMIT');

        return json(res, 200, { success: true });
      } catch (e) {
        await client.query('ROLLBACK');
        throw e;
      } finally {
        // eslint-disable-next-line no-unsafe-finally
        client && client.release();
      }
    }

    if (action === 'delete') {
      const { room_id } = req.body || {};
      const roomId = parseInt(room_id || '0', 10);
      if (!roomId) return json(res, 400, { success: false, message: 'Invalid room id.' });

      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        await client.query('DELETE FROM beds WHERE room_id = $1', [roomId]);
        await client.query('DELETE FROM rooms WHERE id = $1', [roomId]);
        await client.query('COMMIT');
        return json(res, 200, { success: true });
      } catch (e) {
        await client.query('ROLLBACK');
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

