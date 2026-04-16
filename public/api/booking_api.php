<?php
/**
 * Consolidated Booking API
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/core.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'reserve') {
    $bed_id = $_POST['bed_id'] ?? 0;
    
    $stmt = $conn->prepare("SELECT status FROM beds WHERE id = ?");
    $stmt->execute([$bed_id]);
    $row = $stmt->fetch();
    $status = $row['status'] ?? '';

    if ($status === 'Available') {
        $stmt = $conn->prepare("UPDATE beds SET status = 'Reserved' WHERE id = ?");
        if ($stmt->execute([$bed_id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Bed already taken']);
    }
    exit;
}

if ($action === 'finalize') {
    $name          = $_POST['name'] ?? '';
    $phone         = $_POST['phone'] ?? '';
    $guardian      = $_POST['guardian'] ?? '';
    $guardianPhone = $_POST['guardianPhone'] ?? '';
    $category      = $_POST['category'] ?? '';
    $paymentMethod = $_POST['paymentMethod'] ?? '';
    $bedId         = $_POST['bedId'] ?? 0;
    
    $receipt_url = '';
    if ($paymentMethod === 'GCash' && isset($_FILES['receipt'])) {
        $target_dir = "../uploads/receipts/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_name = uniqid() . '_' . basename($_FILES["receipt"]["name"]);
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES["receipt"]["tmp_name"], $target_file)) {
            $receipt_url = $file_name;
        }
    }

    $booking_ref = "BK-" . strtoupper(substr(uniqid(), -6));
    
    $stmt = $conn->prepare("INSERT INTO bookings (full_name, contact_no, guardian_name, guardian_contact, category, payment_method, bed_id, booking_ref, receipt_photo, booking_status, payment_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending', NOW())");
    if ($stmt->execute([$name, $phone, $guardian, $guardianPhone, $category, $paymentMethod, $bedId, $booking_ref, $receipt_url])) {
        $upd = $conn->prepare("UPDATE beds SET status = 'Reserved' WHERE id = ?");
        $upd->execute([$bedId]);
        echo json_encode(['success' => true, 'booking_ref' => $booking_ref]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

if ($action === 'get_payments') {
    $booking_id = $_GET['booking_id'] ?? 0;
    $stmt = $conn->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY payment_date DESC");
    $stmt->execute([$booking_id]);
    echo json_encode($stmt->fetchAll());
    exit;
}

echo json_encode(['error' => 'Invalid action']);
