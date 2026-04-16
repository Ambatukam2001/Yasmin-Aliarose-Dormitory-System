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
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bed_ids = [];
while ($row = $result->fetch_assoc()) {
    $bed_ids[] = $row['bed_id'];
}
$stmt->close();

$conn->begin_transaction();

try {
    // 1. Free the beds
    if (!empty($bed_ids)) {
        $ids_str = implode(',', array_fill(0, count($bed_ids), '?'));
        $stmt = $conn->prepare("UPDATE beds SET status = 'Available' WHERE id IN ($ids_str)");
        $stmt->bind_param(str_repeat('i', count($bed_ids)), ...$bed_ids);
        $stmt->execute();
        $stmt->close();
    }

    // 2. Delete related records
    // Bookings (Payments will cascade if DB is set up, but let's be safe if not)
    // Note: Our DB check showed payments has ON DELETE CASCADE on booking_id.
    
    // Transfer Requests
    $stmt = $conn->prepare("DELETE FROM transfer_requests WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // Request-Out Requests (table may not exist on older installs)
    $has_request_out = $conn->query("SHOW TABLES LIKE 'request_out_requests'");
    if ($has_request_out && $has_request_out->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM request_out_requests WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }

    // Chats / Messages (support multiple schema variants)
    // Newer schema: messages has chat_id only (no sender_id/receiver_id)
    // Older/other schema: messages may have sender_id/receiver_id
    $has_sender_cols = false;
    $col_check = $conn->query("SHOW COLUMNS FROM messages LIKE 'sender_id'");
    if ($col_check && $col_check->num_rows > 0) {
        $has_sender_cols = true;
    }

    if ($has_sender_cols) {
        // Sender/receiver based delete (if those columns exist)
        $stmt = $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Chat-based delete
        $stmt = $conn->prepare("SELECT id FROM chats WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $chat_ids = [];
        while ($r = $res->fetch_assoc()) {
            $chat_ids[] = (int)$r['id'];
        }
        $stmt->close();

        if (!empty($chat_ids)) {
            $placeholders = implode(',', array_fill(0, count($chat_ids), '?'));
            $types = str_repeat('i', count($chat_ids));

            $stmt = $conn->prepare("DELETE FROM messages WHERE chat_id IN ($placeholders)");
            $stmt->bind_param($types, ...$chat_ids);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("DELETE FROM chats WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Deleting bookings will cascade to payments.
    $stmt = $conn->prepare("DELETE FROM bookings WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // 3. Delete user record
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    
    // Clear session
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error deleting account: ' . $e->getMessage()]);
}
