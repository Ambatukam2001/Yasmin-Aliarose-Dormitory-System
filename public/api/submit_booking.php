<?php
/**
 * api/submit_booking.php — PDO version
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/core.php';
header('Content-Type: application/json');

set_exception_handler(function($e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$bed_id           = intval($_POST['bed_id']           ?? 0);
$full_name        = trim($_POST['full_name']          ?? '');
$category         = trim($_POST['category']           ?? '');
$school_name      = trim($_POST['school_name']        ?? '');
$contact_number   = trim($_POST['contact_number']     ?? '');
$guardian_name    = trim($_POST['guardian_name']      ?? '');
$guardian_contact = trim($_POST['guardian_contact']   ?? '');
$payment_method   = trim($_POST['payment_method']     ?? '');

if (!$bed_id || !$full_name || !$category || !$contact_number || !$guardian_name || !$guardian_contact || !$payment_method) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled in.']);
    exit;
}

if (!in_array($category, ['Reviewer', 'College', 'High School'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid category.']);
    exit;
}
if (!in_array($payment_method, ['GCash Online', 'Cash In'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method.']);
    exit;
}

// Confirm bed is still Available
$check = $conn->prepare("SELECT id, status FROM beds WHERE id = ?");
$check->execute([$bed_id]);
$bed = $check->fetch();

if (!$bed) {
    echo json_encode(['success' => false, 'message' => 'Bed not found.']);
    exit;
}
if ($bed['status'] !== 'Available') {
    echo json_encode(['success' => false, 'message' => 'Sorry, that bed was just taken. Please choose another.']);
    exit;
}

// Handle receipt upload
$receipt_path = null;

if ($payment_method === 'GCash Online') {
    if (empty($_FILES['receipt']['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => 'GCash receipt is required.']);
        exit;
    }

    $file = $_FILES['receipt'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File upload error code: ' . $file['error']]);
        exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Receipt file exceeds 5 MB limit.']);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/jpg', 'application/pdf'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. PNG, JPG, or PDF only.']);
        exit;
    }

    $upload_dir = __DIR__ . '/../uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext         = ($mime === 'application/pdf') ? 'pdf' : 'jpg';
    $filename    = 'receipt_' . time() . '_' . $bed_id . '.' . $ext;
    $destination = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save receipt file.']);
        exit;
    }

    $receipt_path = 'uploads/documents/' . $filename;
}

// Generate unique booking reference
do {
    $booking_ref = 'BK-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
    $dup = $conn->prepare("SELECT id FROM bookings WHERE booking_ref = ?");
    $dup->execute([$booking_ref]);
} while ($dup->fetch());

// Transaction: insert booking
$conn->beginTransaction();

try {
    $user_id = $_SESSION['user_id'] ?? null;

    if (!empty($user_id)) {
        $up = $conn->prepare("UPDATE users SET full_name = COALESCE(NULLIF(full_name,''), ?), phone = COALESCE(NULLIF(phone,''), ?) WHERE id = ?");
        $up->execute([$full_name, $contact_number, $user_id]);

        $u = $conn->prepare("SELECT full_name, phone FROM users WHERE id = ? LIMIT 1");
        $u->execute([$user_id]);
        $urow = $u->fetch();

        if (!empty($urow)) {
            if (!empty($urow['full_name'])) $full_name = $urow['full_name'];
            if (!empty($urow['phone'])) $contact_number = $urow['phone'];
        }
    }

    $ins = $conn->prepare("
        INSERT INTO bookings
            (booking_ref, bed_id, user_id,
             full_name, category, school_name,
             contact_number, guardian_name, guardian_contact,
             payment_method, receipt_path,
             monthly_rent, current_balance,
             booking_status, payment_status, reserve_at)
        VALUES
            (?, ?, ?,
             ?, ?, ?,
             ?, ?, ?,
             ?, ?,
             ?, ?,
             'Pending', 'Pending', NOW())
    ");

    if (!$ins->execute([
        $booking_ref, $bed_id, $user_id,
        $full_name, $category, $school_name,
        $contact_number, $guardian_name, $guardian_contact,
        $payment_method, $receipt_path,
        FIXED_RENT, FIXED_RENT
    ])) {
        throw new Exception('Insert failed');
    }

    $conn->commit();
    echo json_encode(['success' => true, 'booking_ref' => $booking_ref]);

} catch (Exception $e) {
    $conn->rollBack();
    if ($receipt_path && file_exists(__DIR__ . '/../' . $receipt_path)) {
        unlink(__DIR__ . '/../' . $receipt_path);
    }
    echo json_encode(['success' => false, 'message' => 'Booking failed: ' . $e->getMessage()]);
}
