const { Pool } = require('pg');

function cleanDatabaseUrl(raw) {
  if (!raw) return '';
  let v = String(raw).trim();

  // Common misconfig: user pastes extra text after the URL (e.g. " (password) ...").
  // That turns the hostname into garbage and causes ENOTFOUND.
  v = v.split(/\s+/)[0];

  // Strip wrapping quotes
  v = v.replace(/^['"]|['"]$/g, '');

  return v;
}

function getDbConfig() {
  const databaseUrl = cleanDatabaseUrl(process.env.DATABASE_URL);
  if (databaseUrl) return { connectionString: databaseUrl };

  // Fallback for people who prefer separate env vars.
  const host = (process.env.DB_HOST || '').trim();
  const port = parseInt(process.env.DB_PORT || '5432', 10);
  const user = (process.env.DB_USER || '').trim();
  const password = process.env.DB_PASS || '';
  const database = (process.env.DB_NAME || 'postgres').trim();

  if (!host || !user) {
    throw new Error(
      'Missing DATABASE_URL (or DB_HOST/DB_USER/DB_PASS/DB_NAME) environment variables.'
    );
  }

  return {
    host,
    port,
    user,
    password,
    database,
  };
}

let _pool = null;

function getPool() {
  if (_pool) return _pool;

  const cfg = getDbConfig();

  // Validate DATABASE_URL early to produce a helpful error.
  if (cfg.connectionString) {
    try {
      // eslint-disable-next-line no-new
      new URL(cfg.connectionString);
    } catch {
      throw new Error('Invalid DATABASE_URL format. Expected: postgres://user:pass@host:5432/dbname');
    }
  }

  _pool = new Pool({
    ...cfg,
    ssl: { rejectUnauthorized: false },
  });

  return _pool;
}

module.exports = { getPool };

