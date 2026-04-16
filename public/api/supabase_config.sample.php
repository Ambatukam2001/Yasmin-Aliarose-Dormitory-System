<?php
/**
 * Copy this file to supabase_config.php (same directory) and fill in your project values.
 * supabase_config.php is gitignored — never commit secrets.
 *
 * Supabase dashboard: Settings → API (Project URL, anon public key, service_role secret).
 * Database: Settings → Database (connection string / pooler for server-side tools only).
 */

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', 'https://YOUR_PROJECT_REF.supabase.co');
}

/** Public anon key — safe for browser + RLS-protected REST calls only */
if (!defined('SUPABASE_ANON_KEY')) {
    define('SUPABASE_ANON_KEY', 'YOUR_SUPABASE_ANON_KEY');
}

/** Service role — server-side PHP only; never expose to the browser */
if (!defined('SUPABASE_SERVICE_ROLE_KEY')) {
    define('SUPABASE_SERVICE_ROLE_KEY', '');
}

/**
 * Optional: Postgres pooler URI for CLI/migration scripts (PHP PDO pgsql), not used by default app.
 * postgresql://postgres.PROJECT:[PASSWORD]@aws-0-REGION.pooler.supabase.com:6543/postgres
 */
if (!defined('SUPABASE_DB_URL')) {
    define('SUPABASE_DB_URL', '');
}
