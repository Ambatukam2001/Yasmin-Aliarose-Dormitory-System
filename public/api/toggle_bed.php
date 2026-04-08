<?php
require_once __DIR__ . '/core.php';
require_admin_auth();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$bed_id    = isset($_POST['bed_id']) ? (int)$_POST['bed_id'] : 0;
$newStatus = isset($_POST['status']) ? trim($_POST['status']) : '';

// beds.status enum: 'Available' | 'Reserved' | 'Occupied'
if (!$bed_id || !in_array($newStatus, ['Available', 'Reserved', 'Occupied'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

// Get bed → room_id
$stmt = $conn->prepare("SELECT room_id FROM beds WHERE id = ?");
$stmt->bind_param("i", $bed_id);
$stmt->execute();
$bed = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bed) {
    echo json_encode(['success' => false, 'message' => 'Bed not found.']);
    exit;
}

$room_id = (int)$bed['room_id'];

// Update bed
if ($newStatus === 'Occupied') {
    $stmt = $conn->prepare("UPDATE beds SET status = 'Occupied', reserved_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $bed_id);
} else {
    $stmt = $conn->prepare("UPDATE beds SET status = ?, reserved_at = NULL WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $bed_id);
}
$stmt->execute();
$stmt->close();

// Get updated reserved_at
$stmt = $conn->prepare("SELECT reserved_at FROM beds WHERE id = ?");
$stmt->bind_param("i", $bed_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$reserved_at = (!empty($row['reserved_at']) && $row['reserved_at'] !== '0000-00-00 00:00:00')
    ? date('M d, Y', strtotime($row['reserved_at']))
    : null;

// Recalculate occupancy (only Occupied counts as "taken")
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

// Sync rooms.status  (rooms only has 'Available' | 'Full')
$room_status = $is_full ? 'Full' : 'Available';
$stmt = $conn->prepare("UPDATE rooms SET status = ? WHERE id = ?");
$stmt->bind_param("si", $room_status, $room_id);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success'      => true,
    'reserved_at'  => $reserved_at,
    'room_summary' => [
        'room_id'   => $room_id,
        'total'     => $total,
        'occupied'  => $occupied,
        'vacancies' => $vacancies,
        'pct'       => $pct,
        'is_full'   => $is_full,
    ]
]);
