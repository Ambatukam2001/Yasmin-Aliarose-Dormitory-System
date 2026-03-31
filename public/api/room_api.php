<?php
/**
 * api/room_api.php
 * Actions: all | floor_rooms | beds
 */
require_once __DIR__ . '/core.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'all') {
    $floor_no = intval($_GET['floor_no'] ?? 2);
    $stmt = $conn->prepare("SELECT * FROM rooms WHERE floor_no = ?");
    $stmt->bind_param("i", $floor_no);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($action === 'floor_rooms') {
    $floor_no = intval($_GET['floor_no'] ?? 2);
    $sql = "
        SELECT r.*,
            (SELECT COUNT(*) FROM beds WHERE room_id = r.id) as total_beds,
            (SELECT COUNT(*) FROM beds WHERE room_id = r.id AND status = 'Occupied') as occupied_count,
            (SELECT COUNT(*) FROM beds WHERE room_id = r.id AND status = 'Reserved') as reserved_count
        FROM rooms r
        WHERE r.floor_no = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $floor_no);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($action === 'beds') {
    $room_id = intval($_GET['room_id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM beds WHERE room_id = ? ORDER BY bed_no ASC");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

echo json_encode(['error' => 'Invalid action']);
