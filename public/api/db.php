<?php
// ============================================================
// Database Connection — Supabase Transaction Pooler (PostgreSQL)
// ============================================================

// Password reads from Vercel Environment Variable: DB_PASS
// Set it in: Vercel Dashboard → Project → Settings → Environment Variables
$password = getenv('postgresql://postgres.zafdsyvthslobaalkwam:[YOUR-PASSWORD]@aws-1-ap-northeast-1.pooler.supabase.com:6543/postgres') ?: ($_SERVER['postgresql://postgres.zafdsyvthslobaalkwam:[YOUR-PASSWORD]@aws-1-ap-northeast-1.pooler.supabase.com:6543/postgres'] ?? $_ENV['postgresql://postgres.zafdsyvthslobaalkwam:[YOUR-PASSWORD]@aws-1-ap-northeast-1.pooler.supabase.com:6543/postgres'] ?? '');

// Supabase Transaction Pooler connection details
$host     = 'aws-1-ap-northeast-1.pooler.supabase.com';
$port     = '6543';
$dbname   = 'postgres';
$username = 'postgres.zafdsyvthslobaalkwam';

try {
    $dsn  = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $conn->exec("SET TIME ZONE 'Asia/Manila'");
    date_default_timezone_set('Asia/Manila');

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$site_name    = 'Yasmin & Aliarose Dormitory';
$bed_price    = 1600;
$gcash_number = '09915740177';
?>
