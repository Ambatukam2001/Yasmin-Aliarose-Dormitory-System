const { pool } = require('./_db');

function json(res, status, payload) {
  res.status(status).json(payload);
}

module.exports = async (req, res) => {
  if (req.method !== 'GET') return json(res, 405, { error: 'Method not allowed' });

  const bookingId = parseInt((req.query.booking_id || '0').toString(), 10);
  if (!bookingId) return json(res, 400, { error: 'Invalid booking id' });

  try {
    const q = `
      SELECT
        id,
        booking_id,
        payment_date,
        amount,
        payment_mode AS payment_method,
        NULL::text AS receipt_path
      FROM payments
      WHERE booking_id = $1
      ORDER BY payment_date DESC
    `;
    const result = await pool.query(q, [bookingId]);
    return json(res, 200, result.rows);
  } catch (e) {
    return json(res, 500, { error: e.message });
  }
};

