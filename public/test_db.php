<?php
/**
 * Database Connection Tester
 * Upload this to your InfinityFree public_html/ and run it:
 * e.g. http://your-site.epizy.com/test_db.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Diagnostic</h1>";

$env_file = __DIR__ . '/config/config.env';
echo "<p>Checking for config file at: <strong>$env_file</strong></p>";

if (!file_exists($env_file)) {
    echo "<p style='color:red;'>❌ Error: config/config.env NOT FOUND!</p>";
} else {
    echo "<p style='color:green;'>✅ Found config file.</p>";
}

require_once 'api/db.php';

if ($conn->connect_error) {
    echo "<p style='color:red;'>❌ Database Connection FAILED!</p>";
    echo "<p>Error: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color:green; font-size:1.5rem;'>✅ Database Connection SUCCESS!</p>";
    echo "<ul>";
    echo "<li><strong>Host:</strong> " . (isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'Not set (using localhost)') . "</li>";
    echo "<li><strong>User:</strong> " . (isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : 'Not set') . "</li>";
    echo "<li><strong>Database:</strong> " . (isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : 'Not set') . "</li>";
    echo "</ul>";
    
    // Test a simple query
    $res = $conn->query("SELECT COUNT(*) FROM admins");
    if ($res) {
        $count = $res->fetch_row()[0];
        echo "<p style='color:green;'>✅ Query Test: FOUND $count admin(s) in the database.</p>";
    } else {
        echo "<p style='color:red;'>❌ Query Test FAILED: " . $conn->error . "</p>";
    }
}

echo "<hr><p><em>Delete this file after testing for security!</em></p>";
?>
