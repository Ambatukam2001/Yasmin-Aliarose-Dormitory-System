<?php
// Function to load .env format files
if (!function_exists('loadEnv')) {
    function loadEnv($path)
    {
        if (!file_exists($path))
            return false;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#') === 0 || empty($line))
                continue;
            if (strpos($line, '=') === false) continue;
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
            putenv(trim($name) . "=" . trim($value));
        }
        return true;
    }
}

// Ensure $_ENV is populated from file
$env_path = __DIR__ . '/../config/config.env';
if (!loadEnv($env_path)) {
    loadEnv(__DIR__ . '/../../config/config.env');
}

$db_type = $_ENV['DB_TYPE'] ?? 'mysql'; // 'mysql' or 'pgsql'
$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? ($db_type === 'pgsql' ? '5432' : '3306');
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';
$dbname = $_ENV['DB_NAME'] ?? 'dormitory_db';

try {
    if ($db_type === 'pgsql' || strpos($host, 'supabase') !== false) {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
        $db_type = 'pgsql';
    } else {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $db_type = 'mysql';
    }

    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if ($db_type === 'mysql') {
        $conn->exec("SET time_zone = '+08:00'");
    } else {
        $conn->exec("SET TIME ZONE 'Asia/Manila'");
    }
    date_default_timezone_set('Asia/Manila');

} catch (PDOException $e) {
    // For debugging on Vercel, but should be handled better in production
    die("Connection failed: " . $e->getMessage());
}

$site_name = $_ENV['SITE_NAME'] ?? "Yasmin & Aliarose Dormitory";
$bed_price = $_ENV['BED_PRICE'] ?? 1600;
$gcash_number = $_ENV['GCASH_NUMBER'] ?? "09915740177";
?>

