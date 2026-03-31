<?php
require_once __DIR__ . '/core.php';
require_admin_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$bed_id = isset($_POST['bed_id']) ? (int)$_POST['bed_id'] : 0;
if (!$bed_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid bed ID.']);
    exit;
}

// Fetch bed
$stmt = $conn->prepare("SELECT * FROM beds WHERE id = ?");
$stmt->bind_param("i", $bed_id);
$stmt->execute();
$bed = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bed) {
    echo json_encode(['success' => false, 'message' => 'Bed not found.']);
    exit;
}

// Block if occupied
if ($bed['status'] === 'Occupied') {
    echo json_encode(['success' => false, 'message' => 'Cannot delete an occupied bed. Release it first.']);
    exit;
}

// Block if there's an active booking linked to this bed
$stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt FROM bookings
    WHERE bed_id = ? AND booking_status = 'Active'
");
$stmt->bind_param("i", $bed_id);
$stmt->execute();
$booking_check = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ((int)$booking_check['cnt'] > 0) {
    echo json_encode(['success' => false, 'message' => 'This bed has an active booking. Cancel the booking first.']);
    exit;
}

$room_id = (int)$bed['room_id'];

// Delete bed (bookings.bed_id will SET NULL via FK)
$stmt = $conn->prepare("DELETE FROM beds WHERE id = ?");
$stmt->bind_param("i", $bed_id);
$stmt->execute();
$stmt->close();

// Decrease room capacity (floor at 0)
$stmt = $conn->prepare("UPDATE rooms SET capacity = GREATEST(0, capacity - 1) WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$stmt->close();

// Recalculate and sync room status
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total,
           SUM(status = 'Occupied') AS occupied
    FROM beds WHERE room_id = ?
");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total     = (int)$s['total'];
$occupied  = (int)$s['occupied'];
$vacancies = $total - $occupied;
$pct       = $total > 0 ? round(($occupied / $total) * 100) : 0;
$is_full   = ($total > 0 && $vacancies <= 0);

$room_status = $is_full ? 'Full' : 'Available';
$stmt = $conn->prepare("UPDATE rooms SET status = ? WHERE id = ?");
$stmt->bind_param("si", $room_status, $room_id);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success'      => true,
    'room_summary' => [
        'room_id'   => $room_id,
        'total'     => $total,
        'occupied'  => $occupied,
        'vacancies' => $vacancies,
        'pct'       => $pct,
        'is_full'   => $is_full,
    ]
]);
