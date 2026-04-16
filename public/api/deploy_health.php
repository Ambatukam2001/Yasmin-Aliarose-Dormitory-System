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

function table_exists(mysqli $conn, string $table_name): bool
{
    $safe = $conn->real_escape_string($table_name);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
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
    $ok = is_dir($dir) && is_writable($dir);
    $dir_checks[$dir] = $ok;
    if (!$ok) {
        $all_dirs_ok = false;
    }
}

$db_ok = !$conn->connect_errno;
$ok = $db_ok && $all_tables_ok && $all_dirs_ok;

echo json_encode([
    'ok' => $ok,
    'timestamp' => date('c'),
    'checks' => [
        'database_connection' => $db_ok,
        'required_tables' => $table_checks,
        'upload_directories_writable' => $dir_checks,
    ]
], JSON_PRETTY_PRINT);
