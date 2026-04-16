<?php
/**
 * Simple idempotent migration runner.
 *
 * Usage:
 *   php scripts/db_migrate.php
 */

require_once __DIR__ . '/../public/api/db.php';

if ($conn->connect_error) {
    fwrite(STDERR, "Database connection failed: " . $conn->connect_error . PHP_EOL);
    exit(1);
}

$conn->query("
CREATE TABLE IF NOT EXISTS schema_migrations (
  id INT NOT NULL AUTO_INCREMENT,
  migration_key VARCHAR(190) NOT NULL UNIQUE,
  executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
");

$migrations = [
    '2026_04_15_create_request_out_requests' => "
        CREATE TABLE IF NOT EXISTS request_out_requests (
          id INT NOT NULL AUTO_INCREMENT,
          user_id INT NOT NULL,
          booking_id INT NOT NULL,
          request_out_date DATE NOT NULL,
          reason TEXT NOT NULL,
          status VARCHAR(20) DEFAULT 'Pending',
          reviewed_by INT NULL,
          reviewed_at DATETIME NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id)
        )
    ",
];

foreach ($migrations as $key => $sql) {
    $stmt = $conn->prepare("SELECT id FROM schema_migrations WHERE migration_key = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        echo "[SKIP] {$key}" . PHP_EOL;
        continue;
    }

    $conn->begin_transaction();
    try {
        $conn->query($sql);
        $stmt = $conn->prepare("INSERT INTO schema_migrations (migration_key) VALUES (?)");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        echo "[OK]   {$key}" . PHP_EOL;
    } catch (Exception $e) {
        $conn->rollback();
        fwrite(STDERR, "[ERR]  {$key}: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo "Migrations complete." . PHP_EOL;
