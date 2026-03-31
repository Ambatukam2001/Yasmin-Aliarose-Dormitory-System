<?php
/**
 * api/submit_booking.php
 * Matched to actual schema:
 *   bookings: id, booking_ref, bed_id, full_name, school_name, category,
 *             contact_number, guardian_name, guardian_contact, payment_method,
 *             payment_status, booking_status, created_at, due_date,
 *             monthly_rent, current_balance, receipt_path
 *   beds:     id, room_id, floor_id, bed_no, status, reserved_at
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

/* ── Only accept POST ───────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

/* ── Collect & sanitise inputs ──────────────────────────── */
$bed_id           = intval($_POST['bed_id']           ?? 0);
$full_name        = trim($_POST['full_name']          ?? '');
$category         = trim($_POST['category']           ?? '');
$school_name      = trim($_POST['school_name']        ?? '');
$contact_number   = trim($_POST['contact_number']     ?? '');
$guardian_name    = trim($_POST['guardian_name']      ?? '');
$guardian_contact = trim($_POST['guardian_contact']   ?? '');
$payment_method   = trim($_POST['payment_method']     ?? '');

/* ── Basic validation ───────────────────────────────────── */
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

/* ── Confirm bed is still Available ────────────────────── */
$check = $conn->prepare("SELECT id, status FROM beds WHERE id = ?");
$check->bind_param("i", $bed_id);
$check->execute();
$bed = $check->get_result()->fetch_assoc();

if (!$bed) {
    echo json_encode(['success' => false, 'message' => 'Bed not found.']);
    exit;
}
if ($bed['status'] !== 'Available') {
    echo json_encode(['success' => false, 'message' => 'Sorry, that bed was just taken. Please choose another.']);
    exit;
}

/* ── Handle receipt upload ──────────────────────────────── */
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

/* ── Generate unique booking reference ──────────────────── */
do {
    $booking_ref = 'BK-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
    $dup = $conn->prepare("SELECT id FROM bookings WHERE booking_ref = ?");
    $dup->bind_param("s", $booking_ref);
    $dup->execute();
    $dup->store_result();
} while ($dup->num_rows > 0);

/* ── Transaction: insert booking + mark bed Reserved ─────── */
$conn->begin_transaction();

try {
    // bookings has no room_id column — only bed_id
    // booking_status default is 'Active', payment_status default is 'Pending'
    $ins = $conn->prepare("
        INSERT INTO bookings
            (booking_ref, bed_id,
             full_name, category, school_name,
             contact_number, guardian_name, guardian_contact,
             payment_method, receipt_path,
             booking_status, payment_status, reserve_at)
        VALUES
            (?, ?,
             ?, ?, ?,
             ?, ?, ?,
             ?, ?,
             'Active', 'Pending', NOW())
    ");

    $ins->bind_param(
        "sissssssss",
        $booking_ref, $bed_id,
        $full_name, $category, $school_name,
        $contact_number, $guardian_name, $guardian_contact,
        $payment_method, $receipt_path
    );

    if (!$ins->execute()) {
        throw new Exception('Insert failed: ' . $ins->error);
    }

    // Mark bed Reserved + set reserved_at timestamp
    $upd_bed = $conn->prepare("UPDATE beds SET status = 'Reserved', reserved_at = NOW() WHERE id = ?");
    $upd_bed->bind_param("i", $bed_id);
    $upd_bed->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'booking_ref' => $booking_ref]);

} catch (Exception $e) {
    $conn->rollback();
    if ($receipt_path && file_exists(__DIR__ . '/../' . $receipt_path)) {
        unlink(__DIR__ . '/../' . $receipt_path);
    }
    echo json_encode(['success' => false, 'message' => 'Booking failed: ' . $e->getMessage()]);
}
