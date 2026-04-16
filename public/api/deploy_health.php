<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$health_token = $_ENV['HEALTHCHECK_TOKEN'] ?? '';
if ($health_token !== '') {
    $provided = $_GET['token'] ?? '';
    if (!hash_equals($health_token, $provided)) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'Unauthorized health check request'
        ]);
        exit;
    }
}

function table_exists(PDO $conn, string $table_name): bool
{
    $is_pgsql = (strpos($conn->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql') !== false);
    if ($is_pgsql) {
        $stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = ? LIMIT 1");
    } else {
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    }
    $stmt->execute([$table_name]);
    return (bool)$stmt->fetch();
}

$required_tables = [
    'admins',
    'users',
    'rooms',
    'beds',
    'bookings',
    'payments',
    'transfer_requests',
    'request_out_requests',
    'chats',
    'messages',
];

$table_checks = [];
$all_tables_ok = true;
foreach ($required_tables as $table) {
    $exists = table_exists($conn, $table);
    $table_checks[$table] = $exists;
    if (!$exists) {
        $all_tables_ok = false;
    }
}

$upload_dirs = [
    __DIR__ . '/../uploads/chat',
    __DIR__ . '/../uploads/documents',
    __DIR__ . '/../uploads/receipts',
];

$dir_checks = [];
$all_dirs_ok = true;
foreach ($upload_dirs as $dir) {
    $ok = is_dir($dir); // Removed is_writable for Vercel since it's a read-only filesystem usually
    $dir_checks[$dir] = $ok;
    if (!$ok) {
        // We don't fail health check on directories in Vercel as they are ephemeral or stored in S3/Supabase Storage
        // $all_dirs_ok = false; 
    }
}

$db_ok = true;
try {
    $conn->query("SELECT 1");
} catch (Exception $e) {
    $db_ok = false;
}

$ok = $db_ok && $all_tables_ok;

echo json_encode([
    'ok' => $ok,
    'timestamp' => date('c'),
    'checks' => [
        'database_connection' => $db_ok,
        'required_tables' => $table_checks,
        'upload_directories_exists' => $dir_checks,
    ]
], JSON_PRETTY_PRINT);
