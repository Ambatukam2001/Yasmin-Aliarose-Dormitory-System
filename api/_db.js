const { Pool } = require('pg');

function buildPool() {
  const databaseUrl = process.env.DATABASE_URL;
  if (!databaseUrl) {
    throw new Error('Missing DATABASE_URL env var (Supabase Postgres connection string).');
  }

  return new Pool({
    connectionString: databaseUrl,
    ssl: { rejectUnauthorized: false },
  });
}

// Vercel keeps serverless instances warm, so this is fine.
const pool = buildPool();

module.exports = { pool };

