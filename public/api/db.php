<?php
// ============================================================
// Database Connection — Supabase via DATABASE_URL
// ============================================================

// Read the full connection URL from Vercel environment variable
$database_url = getenv('DATABASE_URL') ?: ($_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '');

try {
    if (!empty($database_url)) {
        // Parse the DATABASE_URL connection string
        // Format: postgresql://user:password@host:port/dbname
        $parsed = parse_url($database_url);

        $host     = $parsed['host']     ?? '';
        $port     = $parsed['port']     ?? 6543;
        $dbname   = ltrim($parsed['path'] ?? 'postgres', '/');
        $username = $parsed['user']     ?? '';
        $password = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

    } else {
        // Local fallback (XAMPP) — reads from config.env if available
        if (file_exists(__DIR__ . '/../config/config.env')) {
            $lines = file(__DIR__ . '/../config/config.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                [$k, $v] = explode('=', $line, 2);
                putenv(trim($k) . '=' . trim($v));
                $_ENV[trim($k)] = trim($v);
            }
        }
        $host     = getenv('DB_HOST') ?: 'localhost';
        $port     = getenv('DB_PORT') ?: '3306';
        $dbname   = getenv('DB_NAME') ?: 'dormitory_db';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: '';
        $db_type  = getenv('DB_TYPE') ?: 'mysql';

        if ($db_type === 'pgsql' || strpos($host, 'supabase') !== false) {
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
        } else {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        }
    }

    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Set timezone
    if (strpos($dsn, 'pgsql') === 0) {
        $conn->exec("SET TIME ZONE 'Asia/Manila'");
    } else {
        $conn->exec("SET time_zone = '+08:00'");
    }
    date_default_timezone_set('Asia/Manila');

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$site_name    = 'Yasmin & Aliarose Dormitory';
$bed_price    = 1600;
$gcash_number = '09915740177';
?>
