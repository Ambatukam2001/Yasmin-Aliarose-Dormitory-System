<?php
require_once __DIR__ . '/core.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get all bed IDs associated with this user's bookings to free them
$stmt = $conn->prepare("SELECT bed_id FROM bookings WHERE user_id = ? AND bed_id IS NOT NULL");
$stmt->execute([$user_id]);
$bed_ids = [];
while ($row = $stmt->fetch()) {
    $bed_ids[] = $row['bed_id'];
}

$conn->beginTransaction();

try {
    // 1. Free the beds
    if (!empty($bed_ids)) {
        $placeholders = implode(',', array_fill(0, count($bed_ids), '?'));
        $stmt = $conn->prepare("UPDATE beds SET status = 'Available' WHERE id IN ($placeholders)");
        $stmt->execute($bed_ids);
    }

    // 2. Transfer Requests
    $stmt = $conn->prepare("DELETE FROM transfer_requests WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // 3. Check for request_out_requests table (PostgreSQL compatible)
    try {
        $stmt = $conn->prepare("DELETE FROM request_out_requests WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        // Table may not exist — ignore
    }

    // 4. Chats / Messages
    $stmt = $conn->prepare("SELECT id FROM chats WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $chat_ids = [];
    while ($r = $stmt->fetch()) {
        $chat_ids[] = (int)$r['id'];
    }

    if (!empty($chat_ids)) {
        $placeholders = implode(',', array_fill(0, count($chat_ids), '?'));
        $stmt = $conn->prepare("DELETE FROM messages WHERE chat_id IN ($placeholders)");
        $stmt->execute($chat_ids);
    }

    $stmt = $conn->prepare("DELETE FROM chats WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // 5. Delete bookings (payments cascade)
    $stmt = $conn->prepare("DELETE FROM bookings WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // 6. Delete user record
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    $conn->commit();

    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error deleting account: ' . $e->getMessage()]);
}
