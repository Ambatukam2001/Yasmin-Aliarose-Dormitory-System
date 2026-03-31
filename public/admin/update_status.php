<?php
require_once '../api/core.php';
require_admin_auth();

function redirect_bookings(string $filter = 'all', string $flash = ''): void {
    $url = 'bookings.php?status=' . urlencode($filter);
    if ($flash !== '') $url .= '&flash=' . urlencode($flash);
    header("Location: $url");
    exit;
}

$_current_filter = (!empty($_POST['current_filter']))
    ? $_POST['current_filter']
    : ($_GET['status'] ?? 'all');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_status'])) {
    redirect_bookings($_current_filter);
}

$bid            = (int) $_POST['id'];
$booking_status = $conn->real_escape_string($_POST['booking_status'] ?? '');
$payment_status = $conn->real_escape_string($_POST['payment_status'] ?? '');

// Whitelist — reject anything unexpected
if (!in_array($booking_status, ['Active', 'Completed', 'Cancelled'], true) ||
    !in_array($payment_status, ['Pending', 'Confirmed'], true)) {
    redirect_bookings($_current_filter, 'error');
}

$conn->begin_transaction();
try {
    $conn->query("
        UPDATE bookings
        SET booking_status = '$booking_status',
            payment_status = '$payment_status'
        WHERE id = $bid
    ");

    // Completed or Cancelled → free the bed
    if (in_array($booking_status, ['Completed', 'Cancelled'], true)) {
        $row = $conn->query("SELECT bed_id FROM bookings WHERE id = $bid")->fetch_assoc();
        if (!empty($row['bed_id'])) {
            $conn->query("
                UPDATE beds SET status = 'Available', reserved_at = NULL
                WHERE id = {$row['bed_id']}
            ");
        }
    }

    // Confirmed with no due date → auto-set one
    if ($payment_status === 'Confirmed') {
        $conn->query("
            UPDATE bookings
            SET due_date = DATE_ADD(NOW(), INTERVAL 1 MONTH)
            WHERE id = $bid
              AND (due_date IS NULL OR due_date = '0000-00-00')
        ");
    }

    $conn->commit();
    redirect_bookings($_current_filter, 'status_updated');
} catch (Exception $e) {
    $conn->rollback();
    redirect_bookings($_current_filter, 'error');
}
