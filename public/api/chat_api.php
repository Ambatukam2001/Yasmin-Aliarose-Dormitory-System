<?php
require_once 'core.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$admin_id = $_SESSION['admin_id'] ?? null;
$role = $_SESSION['role'] ?? ($admin_id ? 'admin' : ($user_id ? 'user' : null));

if (!$user_id && !$admin_id) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'get_messages') {
    $target_chat_id = null;

    if ($role === 'user') {
        // Find or create chat for this user
        $stmt = $conn->prepare("SELECT id FROM chats WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $target_chat_id = $res->fetch_assoc()['id'];
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO chats (user_id) VALUES (?)");
            $stmt_insert->bind_param("i", $user_id);
            $stmt_insert->execute();
            $target_chat_id = $conn->insert_id;
        }
    } else if ($role === 'admin') {
        // Admin must specify a chat_id
        $target_chat_id = $_GET['chat_id'] ?? null;
    }

    if (!$target_chat_id) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("SELECT m.*, u.username as user_name FROM messages m LEFT JOIN chats c ON m.chat_id = c.id LEFT JOIN users u ON c.user_id = u.id WHERE m.chat_id = ? ORDER BY m.created_at ASC");
    $stmt->bind_param("i", $target_chat_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $messages = [];
    while ($row = $res->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'sender_type' => $row['sender_type'],
            'message' => $row['message'],
            'file_url' => $row['file_url'],
            'is_voice' => (bool)$row['is_voice'],
            'created_at' => $row['created_at']
        ];
    }
    echo json_encode($messages);
    exit;
}

if ($action === 'send_message') {
    $chat_id = null;
    if ($role === 'user') {
        $stmt = $conn->prepare("SELECT id FROM chats WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $chat_id = $stmt->get_result()->fetch_assoc()['id'] ?? null;
    } else {
        $chat_id = $_POST['chat_id'] ?? null;
    }

    if (!$chat_id) {
        echo json_encode(['error' => 'Invalid chat']);
        exit;
    }

    $message = $_POST['message'] ?? '';
    $file_url = null;
    $is_voice = 0;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/chat/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $filename = time() . '_' . basename($_FILES['file']['name']);
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
            $file_url = 'uploads/chat/' . $filename;
            if (isset($_POST['is_voice']) && $_POST['is_voice'] == '1') {
                $is_voice = 1;
            }
        }
    }

    if (empty($message) && !$file_url) {
        echo json_encode(['error' => 'Empty message']);
        exit;
    }

    $sender_type = $role; // 'user' or 'admin'
    
    $stmt = $conn->prepare("INSERT INTO messages (chat_id, sender_type, message, file_url, is_voice) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $chat_id, $sender_type, $message, $file_url, $is_voice);
    if ($stmt->execute()) {
        // Update last message in chats table
        $last_msg = $is_voice ? '[Voice Message]' : ($file_url ? '[Attachment]' : substr($message, 0, 50));
        $stmt_update = $conn->prepare("UPDATE chats SET last_message = ?, updated_at = NOW() WHERE id = ?");
        $stmt_update->bind_param("si", $last_msg, $chat_id);
        $stmt_update->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to send']);
    }
    exit;
}

if ($action === 'get_chats') {
    // Only for admin
    if ($role !== 'admin') {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $stmt = $conn->prepare("SELECT c.*, u.username FROM chats c JOIN users u ON c.user_id = u.id ORDER BY c.updated_at DESC");
    $stmt->execute();
    $res = $stmt->get_result();
    
    $chats = [];
    while ($row = $res->fetch_assoc()) {
        $chats[] = $row;
    }
    echo json_encode($chats);
    exit;
}
?>
