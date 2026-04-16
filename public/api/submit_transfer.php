<?php
require_once __DIR__ . '/core.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id    = $_SESSION['user_id'];
$booking_id = intval($_POST['booking_id'] ?? 0);
$floor_no   = intval($_POST['requested_floor'] ?? 0);
$room_id    = intval($_POST['requested_room'] ?? 0);
$bed_type   = trim($_POST['requested_bed'] ?? '');
$reason     = trim($_POST['reason'] ?? '');

if (!$booking_id || !$floor_no || !$room_id || !$bed_type || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

// Prevent same-room transfer
$cur = $conn->prepare("SELECT r.id FROM bookings b JOIN beds bd ON b.bed_id=bd.id JOIN rooms r ON bd.room_id=r.id WHERE b.id=? LIMIT 1");
$cur->execute([$booking_id]);
$cur_room = $cur->fetch();

if ($cur_room && (int)$cur_room['id'] === $room_id) {
    echo json_encode(['success' => false, 'message' => 'You are already assigned to this room. Please choose a different room.']);
    exit;
}

// Ensure selected bed exists in the chosen room and is still available
$bed_lookup = $conn->prepare("SELECT bed_no, status FROM beds WHERE room_id = ? AND bed_no = ? LIMIT 1");
$bed_lookup->execute([$room_id, $bed_type]);
$bed_row = $bed_lookup->fetch();

if (!$bed_row) {
    echo json_encode(['success' => false, 'message' => 'Selected bed was not found in this room.']);
    exit;
}

if (strtolower((string)$bed_row['status']) !== 'available') {
    echo json_encode(['success' => false, 'message' => 'Selected bed is no longer available. Please choose another bed.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO transfer_requests (user_id, booking_id, requested_floor, requested_room_id, requested_bed_type, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");

if ($stmt->execute([$user_id, $booking_id, $floor_no, $room_id, $bed_type, $reason])) {
    echo json_encode(['success' => true, 'message' => 'Transfer request submitted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error submitting request.']);
}
?>
