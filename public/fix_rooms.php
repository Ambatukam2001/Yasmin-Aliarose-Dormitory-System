<?php
require_once 'api/db.php';

echo "<h2>Fixing Room Constraints...</h2>";

try {
    $is_pgsql = (strpos($conn->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql') !== false);

    if ($is_pgsql) {
        // 1. Find the old constraint name
        // 2. Drop it
        // 3. Add new composite constraint
        $conn->exec("ALTER TABLE rooms DROP CONSTRAINT IF EXISTS rooms_room_no_key");
        $conn->exec("ALTER TABLE rooms DROP CONSTRAINT IF EXISTS uniq_room_no");
        $conn->exec("ALTER TABLE rooms ADD CONSTRAINT uniq_room_floor UNIQUE (room_no, floor_no)");
        echo "<p style='color:green;'>✅ Success (PostgreSQL): Room numbers are now unique per floor.</p>";
    } else {
        // MySQL
        $conn->exec("ALTER TABLE rooms DROP INDEX uniq_room_no");
        $conn->exec("ALTER TABLE rooms ADD UNIQUE INDEX uniq_room_floor (room_no, floor_no)");
        echo "<p style='color:green;'>✅ Success (MySQL): Room numbers are now unique per floor.</p>";
    }

} catch (Exception $e) {
     echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
     echo "<p>It's possible the constraint was already updated or has a different name.</p>";
}
?>
