<?php
/**
 * actions.php — PDO version
 */

if (!isset($route) || !in_array($route, ['bookings', 'overview'])) {
    return;
}

function booking_redirect(string $flash, string $filter = 'all'): void
{
    global $route;
    $dest   = ($route === 'overview') ? 'dashboard.php' : 'bookings.php';
    $filter = preg_replace('/[^a-z_]/', '', $filter);
    header("Location: {$dest}?flash={$flash}&status={$filter}");
    exit;
}

function free_bed(PDO $conn, int $booking_id): void
{
    $stmt = $conn->prepare("SELECT bed_id FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $row = $stmt->fetch();

    if (!empty($row['bed_id'])) {
        $s = $conn->prepare("UPDATE beds SET status = 'Available' WHERE id = ?");
        $s->execute([$row['bed_id']]);
    }
}

/* ── GET quick_action=accept|decline ── */
if (isset($_GET['quick_action'])) {

    $action = $_GET['quick_action'];
    $id     = (int)($_GET['id'] ?? 0);
    $filter = $_GET['status'] ?? 'all';

    if (!$id || !in_array($action, ['accept', 'decline'], true)) {
        booking_redirect('error', $filter);
    }

    if ($action === 'accept') {

        $stmt = $conn->prepare(
            "SELECT bed_id, move_in_date FROM bookings WHERE id = ? AND booking_status = 'Pending' LIMIT 1"
        );
        $stmt->execute([$id]);
        $booking = $stmt->fetch();

        if (!$booking) booking_redirect('error', $filter);

        $base = (!empty($booking['move_in_date']) && $booking['move_in_date'] !== '0000-00-00')
            ? $booking['move_in_date']
            : date('Y-m-d');

        $due = date('Y-m-d', strtotime($base . ' +1 month'));

        $stmt = $conn->prepare(
            "UPDATE bookings SET booking_status = 'Active', payment_status = 'Confirmed', due_date = ? WHERE id = ?"
        );
        $ok = $stmt->execute([$due, $id]);

        if (!$ok) booking_redirect('error', $filter);

        if (!empty($booking['bed_id'])) {
            $s = $conn->prepare("UPDATE beds SET status = 'Occupied' WHERE id = ?");
            $s->execute([$booking['bed_id']]);
        }

        booking_redirect('accept', $filter);

    } else {

        $stmt = $conn->prepare(
            "SELECT id FROM bookings WHERE id = ? AND booking_status = 'Pending' LIMIT 1"
        );
        $stmt->execute([$id]);
        $exists = $stmt->fetch();

        if (!$exists) booking_redirect('error', $filter);

        free_bed($conn, $id);

        $stmt = $conn->prepare(
            "UPDATE bookings SET booking_status = 'Cancelled', payment_status = 'Pending' WHERE id = ?"
        );
        $ok = $stmt->execute([$id]);

        booking_redirect($ok ? 'decline' : 'error', $filter);
    }
}

/* ── GET action=delete ── */
if (isset($_GET['action']) && $_GET['action'] === 'delete') {

    $id     = (int)($_GET['id'] ?? 0);
    $filter = $_GET['status'] ?? 'all';

    if (!$id) booking_redirect('error', $filter);

    free_bed($conn, $id);

    $s = $conn->prepare("DELETE FROM payments WHERE booking_id = ?");
    $s->execute([$id]);

    $s  = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $ok = $s->execute([$id]);

    booking_redirect($ok ? 'deleted' : 'error', $filter);
}

/* ── POST log_payment=1 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['log_payment'])) {

    $booking_id = (int)($_POST['booking_id']  ?? 0);
    $amount     = (float)($_POST['amount']     ?? 0);
    $next_due   = trim($_POST['next_due_date'] ?? '');
    $notes      = trim($_POST['notes']         ?? '');
    $filter     = $_POST['current_filter']     ?? 'confirmed';

    if (!$booking_id || $amount <= 0 || !$next_due) booking_redirect('error', $filter);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_due)) booking_redirect('error', $filter);

    $receipt_path = null;
    if (!empty($_FILES['receipt']['tmp_name'])) {
        $upload_dir = '../uploads/receipts/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext  = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        $safe = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        if (in_array($ext, $safe, true)) {
            $fname = 'receipt_' . $booking_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_dir . $fname)) {
                $receipt_path = 'uploads/receipts/' . $fname;
            }
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO payments (booking_id, amount, notes, receipt_path, paid_at) VALUES (?, ?, ?, ?, NOW())"
    );
    $ok = $stmt->execute([$booking_id, $amount, $notes, $receipt_path]);

    if (!$ok) booking_redirect('error', $filter);

    $stmt = $conn->prepare("UPDATE bookings SET due_date = ?, payment_status = 'Confirmed' WHERE id = ?");
    $stmt->execute([$next_due, $booking_id]);

    $stmt = $conn->prepare("SELECT bed_id FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $b_row = $stmt->fetch();
    if (!empty($b_row['bed_id'])) {
        $s = $conn->prepare("UPDATE beds SET status = 'Occupied' WHERE id = ?");
        $s->execute([$b_row['bed_id']]);
    }

    if ($receipt_path) {
        $s = $conn->prepare("UPDATE bookings SET receipt_path = ? WHERE id = ?");
        $s->execute([$receipt_path, $booking_id]);
    }

    booking_redirect('payment_logged', $filter);
}

/* ── POST update_status=1 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['update_status'])) {

    $id             = (int)($_POST['id']             ?? 0);
    $booking_status = $_POST['booking_status']        ?? '';
    $payment_status = $_POST['payment_status']        ?? '';
    $filter         = $_POST['current_filter']        ?? 'all';

    $allowed_b = ['Active', 'Completed', 'Cancelled'];
    $allowed_p = ['Pending', 'Confirmed'];

    if (!$id
        || !in_array($booking_status, $allowed_b, true)
        || !in_array($payment_status, $allowed_p, true)) {
        booking_redirect('error', $filter);
    }

    if (in_array($booking_status, ['Completed', 'Cancelled'], true)) {
        free_bed($conn, $id);
    }

    $due_date = null;
    if ($payment_status === 'Confirmed' && $booking_status === 'Active') {
        $check = $conn->prepare("SELECT due_date FROM bookings WHERE id = ?");
        $check->execute([$id]);
        $row = $check->fetch();
        if (empty($row['due_date']) || $row['due_date'] === '0000-00-00') {
            $due_date = date('Y-m-d', strtotime('+1 month'));
        }
    }

    if ($due_date) {
        $stmt = $conn->prepare("UPDATE bookings SET booking_status = ?, payment_status = ?, due_date = ? WHERE id = ?");
        $ok = $stmt->execute([$booking_status, $payment_status, $due_date, $id]);
    } else {
        $stmt = $conn->prepare("UPDATE bookings SET booking_status = ?, payment_status = ? WHERE id = ?");
        $ok = $stmt->execute([$booking_status, $payment_status, $id]);
    }

    booking_redirect($ok ? 'status_updated' : 'error', $filter);
}


/* ── POST checkout=1 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['checkout'])) {

    $id      = (int)($_POST['id']          ?? 0);
    $moveout = trim($_POST['moveout_date'] ?? date('Y-m-d'));
    $remarks = trim($_POST['remarks']      ?? '');
    $filter  = $_POST['current_filter']    ?? 'all';

    if (!$id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $moveout)) {
        booking_redirect('error', $filter);
    }

    free_bed($conn, $id);

    $stmt = $conn->prepare(
        "UPDATE bookings SET booking_status = 'Completed', move_out_date = ?, remarks = ? WHERE id = ?"
    );
    $ok = $stmt->execute([$moveout, $remarks, $id]);

    booking_redirect($ok ? 'checked_out' : 'error', $filter);
}
