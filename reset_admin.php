<?php
require_once 'public/api/db.php';
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$conn->query("UPDATE admins SET password='$hash' WHERE username='admin'");
echo "Admin password reset successfully.\\n";
$r = $conn->query('SELECT username FROM admins');
if ($r) {
    while($row=$r->fetch_assoc()){
        echo 'Found admin: '.$row['username'].PHP_EOL;
    }
}
?>
