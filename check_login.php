<?php
require_once 'public/api/db.php';
$stmt = $conn->prepare('SELECT * FROM admins WHERE username = ?');
$username = 'admin';
$stmt->bind_param('s', $username);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
echo password_verify('admin123', $admin['password']) ? 'SUCCESS ' . $admin['id'] : 'FAIL';
?>
