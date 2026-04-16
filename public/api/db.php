<?php
// Helper: read from system env (Vercel) OR $_ENV (local file)
function env(string $key, string $default = ''): string {
    // 1. Check system environment (Vercel injects here)
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    // 2. Check $_SERVER (some SAPI configs put it here)
    if (!empty($_SERVER[$key])) return $_SERVER[$key];
    // 3. Check $_ENV (populated by local file loader)
    if (!empty($_ENV[$key])) return $_ENV[$key];
    return $default;
}

// Load local .env file if it exists (XAMPP / local dev only)
if (!function_exists('loadEnv')) {
    function loadEnv(string $path): bool {
        if (!file_exists($path)) return false;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$name, $value] = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value);
            // Only set if NOT already set by the system (don't override Vercel vars)
            if (getenv($name) === false) {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
        return true;
    }
}

// Try local config file (ignored on Vercel since the file doesn't exist there)
if (!loadEnv(__DIR__ . '/../config/config.env')) {
    loadEnv(__DIR__ . '/../../config/config.env');
}

// Read connection values — getenv() works on BOTH Vercel and local
$db_type  = env('DB_TYPE', 'mysql');
$host     = env('DB_HOST', 'localhost');
$port     = env('DB_PORT', $db_type === 'pgsql' ? '5432' : '3306');
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');
$dbname   = env('DB_NAME', 'dormitory_db');

// Auto-detect Supabase from the hostname
if (strpos($host, 'supabase') !== false || strpos($host, 'pooler') !== false) {
    $db_type = 'pgsql';
}

try {
    if ($db_type === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    }

    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    if ($db_type === 'pgsql') {
        $conn->exec("SET TIME ZONE 'Asia/Manila'");
    } else {
        $conn->exec("SET time_zone = '+08:00'");
    }
    date_default_timezone_set('Asia/Manila');

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$site_name    = env('SITE_NAME', 'Yasmin & Aliarose Dormitory');
$bed_price    = env('BED_PRICE', '1600');
$gcash_number = env('GCASH_NUMBER', '09915740177');
?>
