<?php
// Load .env file (optional for local use)
function loadEnv($path)
{
    if (!file_exists($path)) return false;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0 || empty($line)) continue;

        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        putenv(trim($name) . "=" . trim($value));
    }
    return true;
}

// Try loading local env (optional)
loadEnv(__DIR__ . '/../config/config.env');
loadEnv(__DIR__ . '/../../config/config.env');


// ✅ USE RAILWAY VARIABLES
$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$username = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: 'SsUBVFdBxVezJIAfBRFTtxRipBMKLKWy';
$dbname = getenv('MYSQLDATABASE') ?: 'railway';
$port = getenv('MYSQLPORT') ?: 3306;


// Create connection
$conn = new mysqli($host, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Settings
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+08:00'");
date_default_timezone_set('Asia/Manila');

// Optional system settings
$site_name = $_ENV['SITE_NAME'] ?? "Yasmin & Aliarose Dormitory";
$bed_price = $_ENV['BED_PRICE'] ?? 1600;
$gcash_number = $_ENV['GCASH_NUMBER'] ?? "09915740177";
?>
