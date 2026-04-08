<?php
// Load .env file (optional for local use)
function loadEnv($path)
{
    if (!file_exists($path)) return false;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0 || empty($line)) continue;

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
            putenv(trim($name) . "=" . trim($value));
        }
    }
    return true;
}

// Try loading local env
loadEnv(__DIR__ . '/../config/config.env');
loadEnv(__DIR__ . '/../../config/config.env');

// SUPABASE CONFIGURATION
// Use the provided connection string or individual components from env
$db_url = getenv('DATABASE_URL'); // e.g., postgres://postgres:[pass]@db.[project].supabase.co:5432/postgres

if ($db_url) {
    $dbopts = parse_url($db_url);
    $host = $dbopts['host'];
    $user = $dbopts['user'];
    $pass = $dbopts['pass'];
    $dbname = ltrim($dbopts['path'], '/');
    $port = $dbopts['port'] ?? 5432;
} else {
    $host = getenv('DB_HOST') ?: 'db.dormitory_db.supabase.co'; // Placeholder
    $user = getenv('DB_USER') ?: 'postgres';
    $pass = getenv('DB_PASS') ?: 'gumamaadelyasin2001';
    $dbname = getenv('DB_NAME') ?: 'postgres';
    $port = getenv('DB_PORT') ?: 5432;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    // Timezone - PostgreSQL style
    $conn->exec("SET TIME ZONE 'Asia/Manila'");
    date_default_timezone_set('Asia/Manila');

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Optional system settings
$site_name = $_ENV['SITE_NAME'] ?? "Yasmin & Aliarose Dormitory";
$bed_price = $_ENV['BED_PRICE'] ?? 1600;
$gcash_number = $_ENV['GCASH_NUMBER'] ?? "09915740177";
?>
