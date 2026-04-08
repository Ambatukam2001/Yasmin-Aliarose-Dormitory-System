<?php
/**
 * api/state_api.php — Unified state API for the frontend
 *
 * GET  ?action=getSettings              → site settings (name, price, gcash)
 * GET  ?action=getRooms[&floor=N]       → rooms (optionally filtered by floor)
 * GET  ?action=getBeds&roomId=N         → beds for a room
 * GET  ?action=getStats                 → occupancy / revenue statistics
 * GET  ?action=getBookings[&status=X]   → booking list
 * POST action=processPayment            → log a rent payment
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/core.php';

/* ── CORS & content-type ── */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* ══════════════════════════════════════════════════════════
   GET  getSettings
   ══════════════════════════════════════════════════════════ */
if ($action === 'getSettings') {
    echo json_encode([
        'site_name'    => $site_name,
        'bed_price'    => (int) $bed_price,
        'gcash_number' => $gcash_number,
    ]);
    exit;
}

/* ══════════════════════════════════════════════════════════
   GET  getRooms
   ══════════════════════════════════════════════════════════ */
if ($action === 'getRooms') {
    $floor = isset($_GET['floor']) ? intval($_GET['floor']) : null;

    if ($floor) {
        $sql = "
            SELECT r.*,
                (SELECT COUNT(*) FROM beds WHERE room_id = r.id) AS total_beds,
                (SELECT COUNT(*) FROM beds WHERE room_id = r.id AND status = 'Occupied') AS occupied_count,
                (SELECT COUNT(*) FROM beds WHERE room_id = r.id AND status = 'Reserved') AS reserved_count
            FROM rooms r
            WHERE r.floor_no = ?
            ORDER BY r.room_no ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $floor);
    } else {
        $sql = "
            SELECT r.*,
                (SELECT COUNT(*) FROM beds WHERE room_id = r.id) AS total_beds,
                (SELECT COUNT(*) FROM beds WHERE room_id = r.id AND status = 'Occupied') AS occupied_count,
                (SELECT COUNT(*) FROM beds WHERE room_id = r.id AND status = 'Reserved') AS reserved_count
            FROM rooms r
            ORDER BY r.floor_no ASC, r.room_no ASC
        ";
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Cast numeric strings to integers for JS
    foreach ($rows as &$r) {
        $r['id']             = (int) $r['id'];
        $r['floor_no']       = (int) $r['floor_no'];
        $r['capacity']       = (int) $r['capacity'];
        $r['total_beds']     = (int) ($r['total_beds']     ?? 0);
        $r['occupied_count'] = (int) ($r['occupied_count'] ?? 0);
        $r['reserved_count'] = (int) ($r['reserved_count'] ?? 0);
    }
    unset($r);

    echo json_encode($rows);
    exit;
}

/* ══════════════════════════════════════════════════════════
   GET  getBeds
   ══════════════════════════════════════════════════════════ */
if ($action === 'getBeds') {
    $room_id = intval($_GET['roomId'] ?? 0);
    if (!$room_id) {
        http_response_code(400);
        echo json_encode(['error' => 'roomId is required']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM beds WHERE room_id = ? ORDER BY bed_no ASC");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as &$b) {
        $b['id']      = (int) $b['id'];
        $b['room_id'] = (int) $b['room_id'];
        $b['bed_no']  = (int) $b['bed_no'];
    }
    unset($b);

    echo json_encode($rows);
    exit;
}

/* ══════════════════════════════════════════════════════════
   GET  getStats
   ══════════════════════════════════════════════════════════ */
if ($action === 'getStats') {
    $stats = get_stats($conn);

    // Add available beds count
    $total_beds = (int) ($conn->query("SELECT COUNT(*) FROM beds")->fetch_row()[0] ?? 0);
    $occupied   = (int) ($conn->query("SELECT COUNT(*) FROM beds WHERE status = 'Occupied'")->fetch_row()[0] ?? 0);
    $reserved   = (int) ($conn->query("SELECT COUNT(*) FROM beds WHERE status = 'Reserved'")->fetch_row()[0] ?? 0);
    $available  = $total_beds - $occupied - $reserved;

    echo json_encode([
        'rooms'             => (int) $stats['rooms'],
        'totalBeds'         => $total_beds,
        'occupied'          => $occupied,
        'reserved'          => $reserved,
        'available'         => $available,
        'potential_revenue' => (float) $stats['potential_revenue'],
        'overdue_count'     => (int) $stats['overdue_count'],
        'due_this_week'     => (int) $stats['due_this_week'],
    ]);
    exit;
}

/* ══════════════════════════════════════════════════════════
   GET  getBookings
   ══════════════════════════════════════════════════════════ */
if ($action === 'getBookings') {
    $status = $_GET['status'] ?? 'all';

    $where = match($status) {
        'pending'   => " WHERE b.payment_status='Pending' AND b.booking_status='Active'",
        'confirmed' => " WHERE b.payment_status='Confirmed' AND b.booking_status='Active'",
        'overdue'   => " WHERE b.due_date < NOW() AND b.booking_status='Active' AND b.payment_status='Confirmed'",
        'cancelled' => " WHERE b.booking_status='Cancelled'",
        'completed' => " WHERE b.booking_status='Completed'",
        default     => ''
    };

    $rows = $conn->query("
        SELECT b.*, bd.bed_no, r.room_no, r.floor_no,
               DATEDIFF(NOW(), b.due_date) AS days_overdue,
               (SELECT COUNT(*) FROM payments p WHERE p.booking_id = b.id) AS payment_count,
               (SELECT SUM(p.amount) FROM payments p WHERE p.booking_id = b.id) AS total_paid
        FROM bookings b
        LEFT JOIN beds bd ON b.bed_id = bd.id
        LEFT JOIN rooms r ON bd.room_id = r.id
        {$where}
        ORDER BY b.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode($rows);
    exit;
}

/* ══════════════════════════════════════════════════════════
   POST  processPayment
   ══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'processPayment') {
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $amount     = floatval($_POST['amount']     ?? 0);
    $method     = trim($_POST['method']         ?? '');
    $next_due   = trim($_POST['next_due']       ?? '');

    if (!$booking_id || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'booking_id and amount are required.']);
        exit;
    }

    // Default next_due to +1 month from today if not provided
    if (!$next_due || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_due)) {
        $next_due = date('Y-m-d', strtotime('+1 month'));
    }

    // Insert payment record (schema: booking_id, amount, notes, receipt_path, paid_at)
    $notes = $method ? "Payment via {$method}" : '';
    $stmt = $conn->prepare(
        "INSERT INTO payments (booking_id, amount, notes, paid_at) VALUES (?, ?, ?, NOW())"
    );
    $stmt->bind_param('ids', $booking_id, $amount, $notes);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to record payment: ' . $stmt->error]);
        exit;
    }

    // Advance due date and confirm payment status
    $stmt = $conn->prepare(
        "UPDATE bookings SET due_date = ?, payment_status = 'Confirmed' WHERE id = ?"
    );
    $stmt->bind_param('si', $next_due, $booking_id);
    $stmt->execute();

    // Mark bed Occupied
    $stmt = $conn->prepare("SELECT bed_id FROM bookings WHERE id = ?");
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!empty($row['bed_id'])) {
        $s = $conn->prepare("UPDATE beds SET status = 'Occupied' WHERE id = ?");
        $s->bind_param('i', $row['bed_id']);
        $s->execute();
    }

    echo json_encode(['success' => true]);
    exit;
}

/* ── Fallback ── */
http_response_code(400);
echo json_encode(['error' => 'Unknown or missing action.']);
