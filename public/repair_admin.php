<?php
require_once 'api/db.php';

echo "<h2>Dormitory System Repair Tool</h2>";

try {
    // 1. Test Connection
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        $stmt = $conn->query("SELECT current_database() as db, current_user as usr");
    } else {
        $stmt = $conn->query("SELECT DATABASE() as db, USER() as usr");
    }
    $info = $stmt->fetch();

    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    $is_vercel = !empty(getenv('DATABASE_URL'));
    
    echo "<p style='padding:1rem; border:1px solid #ccc; border-radius:8px;'>";
    echo "<strong>Environment:</strong> " . ($is_vercel ? "<span style='color:blue'>Vercel (Production)</span>" : "<span style='color:orange'>Localhost (XAMPP)</span>") . "<br>";
    echo "<strong>Database Driver:</strong> <span style='color:purple'>$driver</span><br>";
    echo "<strong>Connected to DB:</strong> " . $info['db'] . "<br>";
    echo "<strong>DB User:</strong> " . $info['usr'];

    echo "</p>";

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
