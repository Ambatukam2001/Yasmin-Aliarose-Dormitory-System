const { getPool } = require('./_db');

module.exports = async (req, res) => {
  try {
    const pool = getPool();
    const r = await pool.query('SELECT 1 AS ok');

    res.status(200).json({
      ok: true,
      db: r.rows?.[0]?.ok === 1,
      hasDatabaseUrl: !!process.env.DATABASE_URL,
      dbHost: (() => {
        try {
          const raw = (process.env.DATABASE_URL || '').trim().split(/\s+/)[0];
          if (!raw) return null;
          const u = new URL(raw.replace(/^['"]|['"]$/g, ''));
          return u.hostname || null;
        } catch {
          return null;
        }
      })(),
    });
  } catch (e) {
    res.status(500).json({
      ok: false,
      error: e.message,
      hasDatabaseUrl: !!process.env.DATABASE_URL,
    });
  }
};

