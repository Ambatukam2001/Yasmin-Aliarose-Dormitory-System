<?php
require_once 'core.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$method     = trim($_POST['method'] ?? '');
$user_id    = $_SESSION['user_id'];

if (!$booking_id || !$method) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

$receipt_path = '';

if ($method === 'GCash' && isset($_FILES['receipt'])) {
    $target_dir = "../uploads/receipts/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    
    $file_ext   = strtolower(pathinfo($_FILES["receipt"]["name"], PATHINFO_EXTENSION));
    $file_name  = "pay_" . time() . "_" . $user_id . "." . $file_ext;
    $target_file = $target_dir . $file_name;
    
    if (move_uploaded_file($_FILES["receipt"]["tmp_name"], $target_file)) {
        $receipt_path = "uploads/receipts/" . $file_name;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload receipt']);
        exit;
    }
}

// Log into payments table with 'Pending' note
$stmt      = $conn->prepare("INSERT INTO payments (booking_id, amount, payment_method, notes, paid_at) VALUES (?, ?, ?, ?, NOW())");
$zero_amount = 0.00;
$notes     = ($method === 'GCash') ? "GCash Receipt: " . $receipt_path : "Cash-in Request at Admin";

if ($stmt->execute([$booking_id, $zero_amount, $method, $notes])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
