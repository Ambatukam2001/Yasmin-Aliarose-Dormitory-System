<?php
// Function to load .env format files
function loadEnv($path)
{
    if (!file_exists($path))
        return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0 || empty($line))
            continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        putenv(trim($name) . "=" . trim($value));
    }
    return true;
}

// Ensure $_ENV is populated from file
$env_path = __DIR__ . '/../config/config.env';
if (!loadEnv($env_path)) {
    // If we're on a deeper folder structure on hosting, look one more level up
    loadEnv(__DIR__ . '/../../config/config.env');
}

$host = $_ENV['DB_HOST'] ?? 'mysql.railway.internal';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? 'HslLMCqxEazuJbAnlvRrKThVfSuYtTMa';
$dbname = $_ENV['DB_NAME'] ?? 'railway';

$user = $username;
$pass = $password;

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+08:00'");
date_default_timezone_set('Asia/Manila');

$site_name = $_ENV['SITE_NAME'] ?? "Yasmin & Aliarose Dormitory";
$bed_price = $_ENV['BED_PRICE'] ?? 1600;
$gcash_number = $_ENV['GCASH_NUMBER'] ?? "09915740177";

?>
