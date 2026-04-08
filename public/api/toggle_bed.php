<?php
require_once __DIR__ . '/core.php';
require_admin_auth();

header('Content-Type: application/json');

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
$stmt->execute([$bed_id]);
$bed = $stmt->fetch();

if (!$bed) {
    echo json_encode(['success' => false, 'message' => 'Bed not found.']);
    exit;
}

$room_id = (int)$bed['room_id'];

// Update bed
if ($newStatus === 'Occupied') {
    $stmt = $conn->prepare("UPDATE beds SET status = 'Occupied', reserved_at = NOW() WHERE id = ?");
    $stmt->execute([$bed_id]);
} else {
    $stmt = $conn->prepare("UPDATE beds SET status = ?, reserved_at = NULL WHERE id = ?");
    $stmt->execute([$newStatus, $bed_id]);
}

// Get updated reserved_at
$stmt = $conn->prepare("SELECT reserved_at FROM beds WHERE id = ?");
$stmt->execute([$bed_id]);
$row = $stmt->fetch();

$reserved_at = (!empty($row['reserved_at']) && $row['reserved_at'] !== '0000-00-00 00:00:00')
    ? date('M d, Y', strtotime($row['reserved_at']))
    : null;

// Recalculate occupancy (only Occupied counts as "taken")
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) AS occupied
    FROM beds WHERE room_id = ?
");
$stmt->execute([$room_id]);
$s = $stmt->fetch();

$total     = (int)$s['total'];
$occupied  = (int)$s['occupied'];
$vacancies = $total - $occupied;
$pct       = $total > 0 ? round(($occupied / $total) * 100) : 0;
$is_full   = ($total > 0 && $vacancies <= 0);

// Sync rooms.status  (rooms only has 'Available' | 'Full')
$room_status = $is_full ? 'Full' : 'Available';
$stmt = $conn->prepare("UPDATE rooms SET status = ? WHERE id = ?");
$stmt->execute([$room_status, $room_id]);

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
