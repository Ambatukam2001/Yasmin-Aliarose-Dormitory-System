<?php
require_once 'api/db.php';

echo "<h2>Dormitory System Repair Tool</h2>";

try {
    // 1. Test Connection
    $stmt = $conn->query("SELECT current_database(), current_user");
    $info = $stmt->fetch();
    echo "<p style='color:green'>✅ Connected to: " . $info['current_database'] . "</p>";

    // 2. Clear debug info from db.php (Optional, but good for security)
    // 3. Reset Admin Password to 'admin123'
    $new_password = password_hash('admin123', PASSWORD_DEFAULT);
    
    // Check if admin 'admin' exists
    $check = $conn->prepare("SELECT id FROM admins WHERE username = 'admin'");
    $check->execute();
    if ($check->fetch()) {
        $upd = $conn->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
        $upd->execute([$new_password]);
        echo "<p style='color:blue'>✅ Password for user 'admin' reset to: <strong>admin123</strong></p>";
    } else {
        // Create it if missing
        $ins = $conn->prepare("INSERT INTO admins (username, password) VALUES ('admin', ?)");
        $ins->execute([$new_password]);
        echo "<p style='color:blue'>✅ Admin user 'admin' created with password: <strong>admin123</strong></p>";
    }

    echo "<p><a href='login.php'>Go to Login Page</a></p>";

} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
