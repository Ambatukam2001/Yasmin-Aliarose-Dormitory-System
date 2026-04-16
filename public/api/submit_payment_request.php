<?php
/**
 * api/submit_payment_request.php
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once 'core.php';

header('Content-Type: application/json');

// Global error handler to return JSON
function handlePaymentError($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
set_error_handler(function($errno, $errstr) { return true; }); // Suppress warnings

if (!isset($_SESSION['user_id'])) {
    handlePaymentError('Your session has expired. Please log in again.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handlePaymentError('Method not allowed.');
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$method     = trim($_POST['method'] ?? '');
$user_id    = $_SESSION['user_id'];

if (!$booking_id || !$method) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

$receipt_data = '';

if ($method === 'GCash' && isset($_FILES['receipt'])) {
    $file = $_FILES['receipt'];
    if ($file['size'] > 4 * 1024 * 1024) {
        handlePaymentError('Image is too large. Max 4MB allowed.');
    }
    
    $type = pathinfo($file['name'], PATHINFO_EXTENSION);
    $data = file_get_contents($file['tmp_name']);
    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
    $receipt_data = $base64;
}

// Log into payments table with 'Pending' note
$stmt      = $conn->prepare("INSERT INTO payments (booking_id, amount, payment_method, notes, paid_at) VALUES (?, ?, ?, ?, NOW())");
$zero_amount = 0.00;
// Use a searchable prefix + the base64 data
$notes     = ($method === 'GCash') ? "GCASH_PROOF:" . $receipt_data : "Cash-in Request at Admin - Status: Pending Verification";

if ($stmt->execute([$booking_id, $zero_amount, $method, $notes])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
