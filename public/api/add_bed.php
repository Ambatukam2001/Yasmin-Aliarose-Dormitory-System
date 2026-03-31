<?php
require_once __DIR__ . '/core.php';
require_admin_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
if (!$room_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid room.']);
    exit;
}

// Get room → floor_no (stored as floor_id in beds)
$stmt = $conn->prepare("SELECT floor_no FROM rooms WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Room not found.']);
    exit;
}

$floor_no = (int)$room['floor_no'];

// Next bed_no = current max + 1
$stmt = $conn->prepare("SELECT COALESCE(MAX(bed_no), 0) AS max_bed FROM beds WHERE room_id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$next_bed = (int)$row['max_bed'] + 1;

// Insert new bed — status defaults to 'Available' per schema
$stmt = $conn->prepare("INSERT INTO beds (room_id, floor_id, bed_no, status) VALUES (?, ?, ?, 'Available')");
$stmt->bind_param("iii", $room_id, $floor_no, $next_bed);
$stmt->execute();
$new_bed_id = $stmt->insert_id;
$stmt->close();

if (!$new_bed_id) {
    echo json_encode(['success' => false, 'message' => 'Failed to insert bed.']);
    exit;
}

// Update room capacity
$stmt = $conn->prepare("UPDATE rooms SET capacity = capacity + 1 WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$stmt->close();

// Return updated summary
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

echo json_encode([
    'success' => true,
    'bed'     => [
        'id'     => $new_bed_id,
        'bed_no' => $next_bed,
        'status' => 'Available',
    ],
    'room_summary' => [
        'room_id'   => $room_id,
        'total'     => $total,
        'occupied'  => $occupied,
        'vacancies' => $vacancies,
        'pct'       => $pct,
        'is_full'   => $is_full,
    ]
]);
