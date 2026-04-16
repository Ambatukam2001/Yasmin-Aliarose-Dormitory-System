<?php
require_once 'public/api/db.php';
echo "DSN: " . $dsn . "\n";
echo "User: " . $username . "\n";
echo "Password length: " . strlen($password) . "\n";

try {
    $conn->query("SELECT 1");
    echo "Connection successful!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
