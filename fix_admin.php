<?php
require_once 'public/api/db.php';
$username = 'admin';
$password = 'admin123';
$hashed = password_hash($password, PASSWORD_DEFAULT);

$check = $conn->prepare("SELECT id FROM admins WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE username = ?");
    $stmt->bind_param("ss", $hashed, $username);
    $stmt->execute();
    echo "Admin password updated to 'admin123'.\n";
} else {
    $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed);
    $stmt->execute();
    echo "Admin account created with password 'admin123'.\n";
}
?>
