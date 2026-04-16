<?php
require_once 'api/db.php';

echo "<div style='font-family:sans-serif; max-width:600px; margin:2rem auto; border:1px solid #ccc; padding:2rem; border-radius:12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);'>";
echo "<h2 style='color:#10b981; margin-top:0;'>Supabase Connection Repair</h2>";

try {
    // 1. Connection Info
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        $stmt = $conn->query("SELECT current_database() as db, current_user as usr");
    } else {
        $stmt = $conn->query("SELECT DATABASE() as db, USER() as usr");
    }
    $info = $stmt->fetch();
    $is_vercel = !empty(getenv('DATABASE_URL')) || !empty(getenv('POSTGRES_URL'));
    
    echo "<div style='background:#f1f5f9; padding:1rem; border-radius:8px; margin-bottom:1.5rem;'>";
    echo "<strong>Target:</strong> " . ($is_vercel ? "<span style='color:blue'>Vercel/Supabase (Production)</span>" : "<span style='color:orange'>Localhost (XAMPP)</span>") . "<br>";
    echo "<strong>Driver:</strong> $driver<br>";
    echo "<strong>DB:</strong> " . ($info['db'] ?? 'unknown') . "<br>";
    echo "<strong>User:</strong> " . ($info['usr'] ?? 'unknown');
    echo "</div>";

    // 2. Clear and Create Admin
    echo "<h3>System Maintenance:</h3>";
    
    // Ensure table exists (for PostgreSQL)
    if ($driver === 'pgsql') {
        $conn->exec("CREATE TABLE IF NOT EXISTS admins (
            id SERIAL PRIMARY KEY,
            username VARCHAR(255) UNIQUE NOT NULL,
            password TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // 0. Ensure Sessions Table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(255) PRIMARY KEY,
        data TEXT NOT NULL,
        last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>✅ Session storage initialized.</p>";

    // Deep clean: Delete existing admin 'admin' to avoid duplicates or salt issues
    $conn->prepare("DELETE FROM admins WHERE username = 'admin'")->execute();
    
    $new_password = password_hash('admin123', PASSWORD_DEFAULT);
    $ins = $conn->prepare("INSERT INTO admins (username, password) VALUES ('admin', ?)");
    $ins->execute([$new_password]);
    echo "<p style='color:green; font-weight:bold;'>✅ Success! Admin account 'admin' recreated.</p>";

    // 3. Create Resident User for testing
    $user_pass = password_hash('resident123', PASSWORD_DEFAULT);
    $conn->prepare("DELETE FROM users WHERE username = 'resident'")->execute();
    $ins_user = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone) VALUES ('resident', ?, 'Test Resident', 'resident@example.com', '09123456789')");
    $ins_user->execute([$user_pass]);
    echo "<p style='color:blue; font-weight:bold;'>✅ Success! Resident account 'resident' created.</p>";
    
    echo "<p>Admin: admin / admin123<br>Resident: resident / resident123</p>";

    
    echo "<div style='margin-top:2rem;'>";
    echo "<a href='login.php' style='background:#10b981; color:white; padding:0.75rem 1.5rem; text-decoration:none; border-radius:8px; font-weight:bold;'>Go to Login Page</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<p style='color:red; background:#fee2e2; padding:1rem; border-radius:8px;'>❌ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";
?>
