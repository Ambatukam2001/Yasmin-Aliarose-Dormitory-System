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
$booking_status = trim($_POST['booking_status'] ?? '');
$payment_status = trim($_POST['payment_status'] ?? '');

// Whitelist
if (!in_array($booking_status, ['Active', 'Completed', 'Cancelled'], true) ||
    !in_array($payment_status, ['Pending', 'Confirmed'], true)) {
    redirect_bookings($_current_filter, 'error');
}

$conn->beginTransaction();
try {
    $stmt = $conn->prepare(
        "UPDATE bookings SET booking_status = ?, payment_status = ? WHERE id = ?"
    );
    $stmt->execute([$booking_status, $payment_status, $bid]);

    // Completed or Cancelled → free the bed
    if (in_array($booking_status, ['Completed', 'Cancelled'], true)) {
        $row_stmt = $conn->prepare("SELECT bed_id FROM bookings WHERE id = ?");
        $row_stmt->execute([$bid]);
        $row = $row_stmt->fetch();
        if (!empty($row['bed_id'])) {
            $conn->prepare("UPDATE beds SET status = 'Available', reserved_at = NULL WHERE id = ?")
                 ->execute([$row['bed_id']]);
        }
    }

    // Confirmed with no due date → auto-set one
    if ($payment_status === 'Confirmed') {
        $new_due = date('Y-m-d', strtotime('+1 month'));
        $conn->prepare(
            "UPDATE bookings SET due_date = ? WHERE id = ? AND (due_date IS NULL OR due_date = '0000-00-00')"
        )->execute([$new_due, $bid]);
    }

    $conn->commit();
    redirect_bookings($_current_filter, 'status_updated');
} catch (Exception $e) {
    $conn->rollBack();
    redirect_bookings($_current_filter, 'error');
}
