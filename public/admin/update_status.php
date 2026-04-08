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
$booking_status = $_POST['booking_status'] ?? '';
$payment_status = $_POST['payment_status'] ?? '';

// Whitelist — reject anything unexpected
if (!in_array($booking_status, ['Active', 'Completed', 'Cancelled'], true) ||
    !in_array($payment_status, ['Pending', 'Confirmed'], true)) {
    redirect_bookings($_current_filter, 'error');
}

$conn->beginTransaction();
try {
    $stmt = $conn->prepare("
        UPDATE bookings
        SET booking_status = ?,
            payment_status = ?
        WHERE id = ?
    ");
    $stmt->execute([$booking_status, $payment_status, $bid]);

    // Completed or Cancelled → free the bed
    if (in_array($booking_status, ['Completed', 'Cancelled'], true)) {
        $stmt = $conn->prepare("SELECT bed_id FROM bookings WHERE id = ?");
        $stmt->execute([$bid]);
        $row = $stmt->fetch();
        if (!empty($row['bed_id'])) {
            $stmt = $conn->prepare("
                UPDATE beds SET status = 'Available', reserved_at = NULL
                WHERE id = ?
            ");
            $stmt->execute([$row['bed_id']]);
        }
    }

    // Confirmed with no due date → auto-set one
    if ($payment_status === 'Confirmed') {
        $conn->query("
            UPDATE bookings
            SET due_date = NOW() + INTERVAL '1 month'
            WHERE id = $bid
              AND (due_date IS NULL OR due_date = '0000-00-00')
        ");
    }

    $conn->commit();
    redirect_bookings($_current_filter, 'status_updated');
} catch (Exception $e) {
    $conn->rollBack();
    redirect_bookings($_current_filter, 'error');
}
