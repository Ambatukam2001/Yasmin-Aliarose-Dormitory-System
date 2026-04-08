<?php
/**
 * Database Initialiser — Yasmin & Aliarose Dormitory
 *
 * Creates all required tables and seeds sample data.
 * Safe to run multiple times: uses CREATE TABLE IF NOT EXISTS.
 *
 * Access via browser: /init_database.php
 * DELETE this file after first successful run.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/api/db.php';

/* ── Helpers ─────────────────────────────────────────────── */
$steps   = [];
$errors  = [];

function ok(string $msg): void  { global $steps;  $steps[]  = $msg; }
function err(string $msg): void { global $errors; $errors[] = $msg; }

function run(mysqli $conn, string $sql, string $label): void {
    if ($conn->query($sql) === true) {
        ok("✅ $label");
    } else {
        err("❌ $label — " . $conn->error);
    }
}

/* ═══════════════════════════════════════════════════════════
   1. TABLE DEFINITIONS
   ═══════════════════════════════════════════════════════════ */

// ── admins ──────────────────────────────────────────────────
run($conn, "
    CREATE TABLE IF NOT EXISTS `admins` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `username`   VARCHAR(80)  NOT NULL UNIQUE,
        `password`   VARCHAR(255) NOT NULL,
        `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Create table: admins");

// ── rooms ───────────────────────────────────────────────────
run($conn, "
    CREATE TABLE IF NOT EXISTS `rooms` (
        `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `room_no`      VARCHAR(20)  NOT NULL,
        `floor_no`     TINYINT      NOT NULL DEFAULT 2,
        `capacity`     TINYINT      NOT NULL DEFAULT 0,
        `status`       ENUM('Available','Full') NOT NULL DEFAULT 'Available',
        `monthly_rent` DECIMAL(10,2) NOT NULL DEFAULT 1600.00,
        `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Create table: rooms");

// ── beds ────────────────────────────────────────────────────
run($conn, "
    CREATE TABLE IF NOT EXISTS `beds` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `room_id`     INT UNSIGNED NOT NULL,
        `floor_id`    TINYINT      NOT NULL DEFAULT 2,
        `bed_no`      VARCHAR(10)  NOT NULL,
        `status`      ENUM('Available','Reserved','Occupied') NOT NULL DEFAULT 'Available',
        `reserved_at` DATETIME     NULL DEFAULT NULL,
        `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_beds_room_id` (`room_id`),
        KEY `idx_beds_status`  (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Create table: beds");

// ── bookings ─────────────────────────────────────────────────
run($conn, "
    CREATE TABLE IF NOT EXISTS `bookings` (
        `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `booking_ref`      VARCHAR(20)   NOT NULL UNIQUE,
        `bed_id`           INT UNSIGNED  NULL DEFAULT NULL,
        `full_name`        VARCHAR(120)  NOT NULL,
        `category`         VARCHAR(40)   NOT NULL DEFAULT '',
        `school_name`      VARCHAR(120)  NOT NULL DEFAULT '',
        `contact_number`   VARCHAR(30)   NOT NULL DEFAULT '',
        `guardian_name`    VARCHAR(120)  NOT NULL DEFAULT '',
        `guardian_contact` VARCHAR(30)   NOT NULL DEFAULT '',
        `payment_method`   VARCHAR(40)   NOT NULL DEFAULT '',
        `receipt_path`     VARCHAR(255)  NULL DEFAULT NULL,
        `booking_status`   ENUM('Active','Completed','Cancelled') NOT NULL DEFAULT 'Active',
        `payment_status`   ENUM('Pending','Confirmed')            NOT NULL DEFAULT 'Pending',
        `monthly_rent`     DECIMAL(10,2) NOT NULL DEFAULT 1600.00,
        `monthly_rate`     DECIMAL(10,2) NOT NULL DEFAULT 1600.00,
        `current_balance`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `due_date`         DATE          NULL DEFAULT NULL,
        `move_in_date`     DATE          NULL DEFAULT NULL,
        `move_out_date`    DATE          NULL DEFAULT NULL,
        `reserve_at`       DATETIME      NULL DEFAULT NULL,
        `remarks`          TEXT          NULL DEFAULT NULL,
        `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_bookings_bed_id`        (`bed_id`),
        KEY `idx_bookings_booking_status`(`booking_status`),
        KEY `idx_bookings_payment_status`(`payment_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Create table: bookings");

// ── payments ─────────────────────────────────────────────────
run($conn, "
    CREATE TABLE IF NOT EXISTS `payments` (
        `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `booking_id`   INT UNSIGNED  NOT NULL,
        `amount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `notes`        TEXT          NULL DEFAULT NULL,
        `receipt_path` VARCHAR(255)  NULL DEFAULT NULL,
        `paid_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_payments_booking_id` (`booking_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Create table: payments");

// ── users ────────────────────────────────────────────────────
run($conn, "
    CREATE TABLE IF NOT EXISTS `users` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name`       VARCHAR(120) NOT NULL,
        `email`      VARCHAR(120) NOT NULL UNIQUE,
        `phone`      VARCHAR(30)  NOT NULL DEFAULT '',
        `address`    TEXT         NULL DEFAULT NULL,
        `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Create table: users");

/* ═══════════════════════════════════════════════════════════
   2. FOREIGN KEY CONSTRAINTS
   (Added separately so they can be skipped gracefully if
    the tables already existed with data.)
   ═══════════════════════════════════════════════════════════ */

// beds → rooms
$fk1 = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME         = 'beds'
      AND CONSTRAINT_NAME    = 'fk_beds_room_id'
      AND CONSTRAINT_TYPE    = 'FOREIGN KEY'
");
if ($fk1 && $fk1->fetch_assoc()['cnt'] == 0) {
    run($conn,
        "ALTER TABLE `beds`
         ADD CONSTRAINT `fk_beds_room_id`
         FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`)
         ON DELETE CASCADE ON UPDATE CASCADE",
        "Add FK: beds.room_id → rooms.id"
    );
} else {
    ok("⏭️  FK beds.room_id already exists — skipped");
}

// bookings → beds (SET NULL so deleting a bed doesn't wipe booking history)
$fk2 = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME         = 'bookings'
      AND CONSTRAINT_NAME    = 'fk_bookings_bed_id'
      AND CONSTRAINT_TYPE    = 'FOREIGN KEY'
");
if ($fk2 && $fk2->fetch_assoc()['cnt'] == 0) {
    run($conn,
        "ALTER TABLE `bookings`
         ADD CONSTRAINT `fk_bookings_bed_id`
         FOREIGN KEY (`bed_id`) REFERENCES `beds`(`id`)
         ON DELETE SET NULL ON UPDATE CASCADE",
        "Add FK: bookings.bed_id → beds.id"
    );
} else {
    ok("⏭️  FK bookings.bed_id already exists — skipped");
}

// payments → bookings
$fk3 = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME         = 'payments'
      AND CONSTRAINT_NAME    = 'fk_payments_booking_id'
      AND CONSTRAINT_TYPE    = 'FOREIGN KEY'
");
if ($fk3 && $fk3->fetch_assoc()['cnt'] == 0) {
    run($conn,
        "ALTER TABLE `payments`
         ADD CONSTRAINT `fk_payments_booking_id`
         FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`)
         ON DELETE CASCADE ON UPDATE CASCADE",
        "Add FK: payments.booking_id → bookings.id"
    );
} else {
    ok("⏭️  FK payments.booking_id already exists — skipped");
}

/* ═══════════════════════════════════════════════════════════
   3. SEED DATA
   ═══════════════════════════════════════════════════════════ */

// ── Admin user ───────────────────────────────────────────────
$admin_check = $conn->query("SELECT COUNT(*) AS cnt FROM `admins`");
if ($admin_check && (int)$admin_check->fetch_assoc()['cnt'] === 0) {
    $hashed = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt   = $conn->prepare("INSERT INTO `admins` (username, password) VALUES (?, ?)");
    $stmt->bind_param('ss', $username_val, $hashed);
    $username_val = 'admin';
    if ($stmt->execute()) {
        ok("✅ Seed: admin user inserted (username: admin / password: admin123)");
    } else {
        err("❌ Seed: admin insert failed — " . $stmt->error);
    }
    $stmt->close();
} else {
    ok("⏭️  Seed: admins table already has data — skipped");
}

// ── Rooms & Beds ─────────────────────────────────────────────
$rooms_check = $conn->query("SELECT COUNT(*) AS cnt FROM `rooms`");
if ($rooms_check && (int)$rooms_check->fetch_assoc()['cnt'] === 0) {

    $sample_rooms = [
        ['room_no' => '201', 'floor_no' => 2, 'capacity' => 4, 'monthly_rent' => 1600.00],
        ['room_no' => '202', 'floor_no' => 2, 'capacity' => 4, 'monthly_rent' => 1600.00],
    ];

    $room_stmt = $conn->prepare(
        "INSERT INTO `rooms` (room_no, floor_no, capacity, monthly_rent, status)
         VALUES (?, ?, ?, ?, 'Available')"
    );

    $bed_stmt = $conn->prepare(
        "INSERT INTO `beds` (room_id, floor_id, bed_no, status)
         VALUES (?, ?, ?, 'Available')"
    );

    foreach ($sample_rooms as $room) {
        $room_stmt->bind_param(
            'siid',
            $room['room_no'],
            $room['floor_no'],
            $room['capacity'],
            $room['monthly_rent']
        );

        if ($room_stmt->execute()) {
            $new_room_id = $room_stmt->insert_id;
            ok("✅ Seed: room {$room['room_no']} (Floor {$room['floor_no']}) inserted");

            // Insert 4 beds per room
            for ($b = 1; $b <= 4; $b++) {
                $bed_no = str_pad($b, 2, '0', STR_PAD_LEFT); // "01", "02", …
                $bed_stmt->bind_param('iis', $new_room_id, $room['floor_no'], $bed_no);
                if ($bed_stmt->execute()) {
                    ok("✅ Seed: bed {$bed_no} in room {$room['room_no']} inserted");
                } else {
                    err("❌ Seed: bed {$bed_no} in room {$room['room_no']} failed — " . $bed_stmt->error);
                }
            }
        } else {
            err("❌ Seed: room {$room['room_no']} insert failed — " . $room_stmt->error);
        }
    }

    $room_stmt->close();
    $bed_stmt->close();

} else {
    ok("⏭️  Seed: rooms table already has data — skipped");
}

// ── Sample user ──────────────────────────────────────────────
$users_check = $conn->query("SELECT COUNT(*) AS cnt FROM `users`");
if ($users_check && (int)$users_check->fetch_assoc()['cnt'] === 0) {
    $stmt = $conn->prepare(
        "INSERT INTO `users` (name, email, phone, address)
         VALUES (?, ?, ?, ?)"
    );
    $u_name    = 'Maria Santos';
    $u_email   = 'maria.santos@example.com';
    $u_phone   = '09171234567';
    $u_address = '123 Rizal Street, Manila, Philippines';
    $stmt->bind_param('ssss', $u_name, $u_email, $u_phone, $u_address);
    if ($stmt->execute()) {
        ok("✅ Seed: sample user '{$u_name}' inserted");
    } else {
        err("❌ Seed: sample user insert failed — " . $stmt->error);
    }
    $stmt->close();
} else {
    ok("⏭️  Seed: users table already has data — skipped");
}

/* ═══════════════════════════════════════════════════════════
   4. VERIFICATION SUMMARY
   ═══════════════════════════════════════════════════════════ */
$table_counts = [];
foreach (['admins', 'rooms', 'beds', 'bookings', 'payments', 'users'] as $tbl) {
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM `{$tbl}`");
    $table_counts[$tbl] = $res ? (int)$res->fetch_assoc()['cnt'] : '(error)';
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Initialiser — Yasmin &amp; Aliarose Dormitory</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f0fdf4;
            color: #1e293b;
            padding: 2rem 1rem;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            max-width: 760px;
            margin: 0 auto;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            padding: 1.75rem 2rem;
        }
        .card-header h1 { font-size: 1.4rem; font-weight: 800; margin-bottom: .25rem; }
        .card-header p  { font-size: .88rem; opacity: .85; }
        .card-body { padding: 1.75rem 2rem; }

        h2 { font-size: 1rem; font-weight: 700; color: #374151; margin: 1.5rem 0 .75rem; }
        h2:first-child { margin-top: 0; }

        .log-list { list-style: none; display: flex; flex-direction: column; gap: .35rem; }
        .log-list li {
            font-size: .85rem;
            padding: .45rem .75rem;
            border-radius: .5rem;
            background: #f8fafc;
            border-left: 3px solid #e2e8f0;
            font-family: 'Courier New', monospace;
        }
        .log-list li.ok  { border-left-color: #10b981; background: #f0fdf4; color: #065f46; }
        .log-list li.err { border-left-color: #ef4444; background: #fef2f2; color: #991b1b; }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: .75rem;
            margin-top: .75rem;
        }
        .summary-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: .75rem;
            padding: .85rem 1rem;
            text-align: center;
        }
        .summary-card .tbl  { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin-bottom: .3rem; }
        .summary-card .cnt  { font-size: 1.75rem; font-weight: 900; color: #10b981; font-family: 'Courier New', monospace; }

        .alert {
            border-radius: .75rem;
            padding: 1rem 1.25rem;
            font-size: .88rem;
            font-weight: 600;
            margin-top: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: .65rem;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

        .btn {
            display: inline-block;
            margin-top: 1.5rem;
            padding: .75rem 1.5rem;
            border-radius: .75rem;
            font-weight: 700;
            font-size: .9rem;
            text-decoration: none;
            color: #fff;
            background: #10b981;
            transition: background .15s;
        }
        .btn:hover { background: #059669; }
        .btn-outline {
            background: transparent;
            border: 2px solid #10b981;
            color: #10b981;
            margin-left: .75rem;
        }
        .btn-outline:hover { background: #f0fdf4; }

        .divider { border: none; border-top: 1px solid #f1f5f9; margin: 1.5rem 0; }
        .text-muted { color: #94a3b8; font-size: .8rem; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h1>🏨 Database Initialiser</h1>
        <p>Yasmin &amp; Aliarose Dormitory Management System</p>
    </div>
    <div class="card-body">

        <!-- ── Execution log ── -->
        <h2>Execution Log</h2>
        <ul class="log-list">
            <?php foreach ($steps as $step): ?>
                <li class="<?php echo strpos($step, '❌') !== false ? 'err' : 'ok'; ?>">
                    <?php echo htmlspecialchars($step); ?>
                </li>
            <?php endforeach; ?>
            <?php foreach ($errors as $e): ?>
                <li class="err"><?php echo htmlspecialchars($e); ?></li>
            <?php endforeach; ?>
        </ul>

        <hr class="divider">

        <!-- ── Row counts ── -->
        <h2>Table Row Counts</h2>
        <div class="summary-grid">
            <?php foreach ($table_counts as $tbl => $cnt): ?>
            <div class="summary-card">
                <div class="tbl"><?php echo htmlspecialchars($tbl); ?></div>
                <div class="cnt"><?php echo $cnt; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <hr class="divider">

        <!-- ── Status banner ── -->
        <?php if (empty($errors)): ?>
            <div class="alert alert-success">
                ✅ &nbsp;All steps completed successfully. The database is ready.
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                ❌ &nbsp;<?php echo count($errors); ?> error(s) occurred. Review the log above and check your database connection settings.
            </div>
        <?php endif; ?>

        <div class="alert alert-warning" style="margin-top:.85rem;">
            ⚠️ &nbsp;<strong>Security notice:</strong> Delete or restrict access to this file after initialisation.
            It exposes your database structure and inserts default credentials.
        </div>

        <!-- ── Quick links ── -->
        <a href="login.php" class="btn">Go to Admin Login →</a>
        <a href="index.html" class="btn btn-outline">View Site</a>

        <p class="text-muted" style="margin-top:1.25rem;">
            Default admin credentials: <strong>username:</strong> admin &nbsp;/&nbsp; <strong>password:</strong> admin123
        </p>

    </div>
</div>
</body>
</html>
