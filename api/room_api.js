const { getPool } = require('./_db');

function json(res, statusCode, payload) {
  res.status(statusCode).json(payload);
}

module.exports = async (req, res) => {
  // Only support GET for this endpoint (matches existing frontend usage).
  if (req.method !== 'GET') return json(res, 405, { error: 'Method not allowed' });

  const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  const action = url.searchParams.get('action') || '';

  try {
    const pool = getPool();
    if (action === 'all') {
      const floorNo = parseInt(url.searchParams.get('floor_no') || '2', 10);
      const q = `
        SELECT id, room_no, floor_no, capacity, status, created_at
        FROM rooms
        WHERE floor_no = $1
        ORDER BY room_no ASC
      `;
      const result = await pool.query(q, [floorNo]);
      return json(res, 200, result.rows);
    }

    if (action === 'floor_rooms') {
      const floorNo = parseInt(url.searchParams.get('floor_no') || '2', 10);
      const q = `
        SELECT
          r.*,
          (SELECT COUNT(*) FROM beds b WHERE b.room_id = r.id) AS total_beds,
          (SELECT COUNT(*) FROM beds b WHERE b.room_id = r.id AND b.status = 'Occupied') AS occupied_count,
          (SELECT COUNT(*) FROM beds b WHERE b.room_id = r.id AND b.status = 'Reserved') AS reserved_count
        FROM rooms r
        WHERE r.floor_no = $1
        ORDER BY r.room_no ASC
      `;
      const result = await pool.query(q, [floorNo]);
      return json(res, 200, result.rows);
    }

    if (action === 'room_beds') {
      const roomId = parseInt(url.searchParams.get('room_id') || '0', 10);
      const q = `
        SELECT id, room_id, floor_id, bed_no, status, reserved_at, created_at
        FROM beds
        WHERE room_id = $1
        ORDER BY bed_no ASC
      `;
      const result = await pool.query(q, [roomId]);
      return json(res, 200, result.rows);
    }

    return json(res, 400, { error: 'Invalid action' });
  } catch (e) {
    return json(res, 500, { error: 'Database error', details: e.message });
  }
};

