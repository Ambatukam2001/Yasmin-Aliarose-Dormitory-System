<?php
/**
 * actions.php — All booking mutations (no output, redirects only)
 *
 * Included by bookings.php AFTER $route and $conn are defined.
 * Handles:
 *   GET  ?quick_action=accept|decline   — one-click accept / decline
 *   GET  ?action=delete                 — permanent delete
 *   POST log_payment=1                  — log a rent payment
 *   POST update_status=1                — change booking / payment status
 *   POST checkout=1                     — resident checkout
 *
 * Every branch ends with header(Location:…) + exit so no HTML is emitted.
 */

/* ── Safety guard: only run on authorized routes ── */
if (!isset($route) || !in_array($route, ['bookings', 'overview'])) {
    return;
}

/* ─────────────────────────────────────────────────────────────
   HELPER: redirect back with a flash key
   ───────────────────────────────────────────────────────────── */
function booking_redirect(string $flash, string $filter = 'all'): void
{
    global $route;
    $dest = ($route === 'overview') ? 'dashboard.php' : 'bookings.php';
    $filter = preg_replace('/[^a-z_]/', '', $filter); 
    header("Location: {$dest}?flash={$flash}&status={$filter}");
    exit;
}

/* ─────────────────────────────────────────────────────────────
   HELPER: free a bed back to Available
   ───────────────────────────────────────────────────────────── */
function free_bed(mysqli $conn, int $booking_id): void
{
    $stmt = $conn->prepare("SELECT bed_id FROM bookings WHERE id = ?");
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!empty($row['bed_id'])) {
        $s = $conn->prepare("UPDATE beds SET status = 'Available' WHERE id = ?");
        $s->bind_param('i', $row['bed_id']);
        $s->execute();
        $s->close();
    }
}

/* ─────────────────────────────────────────────────────────────
   GET  quick_action=accept|decline
   ───────────────────────────────────────────────────────────── */
if (isset($_GET['quick_action'])) {

    $action = $_GET['quick_action'];
    $id     = (int)($_GET['id'] ?? 0);
    $filter = $_GET['status'] ?? 'all';

    if (!$id || !in_array($action, ['accept', 'decline'], true)) {
        booking_redirect('error', $filter);
    }

    /* ── Accept ── */
    if ($action === 'accept') {

        /* 1. Fetch the booking to get the bed and move-in date */
        $stmt = $conn->prepare(
            "SELECT bed_id, move_in_date
               FROM bookings
              WHERE id = ?
                AND booking_status = 'Active'
                AND payment_status = 'Pending'
              LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            booking_redirect('error', $filter);
        }

        /* 2. Compute first due date = move_in_date + 1 month (fallback: today + 1 month) */
        $base = (!empty($booking['move_in_date']) && $booking['move_in_date'] !== '0000-00-00')
            ? $booking['move_in_date']
            : date('Y-m-d');

        $due = date('Y-m-d', strtotime($base . ' +1 month'));

        /* 3. Accept the booking */
        $stmt = $conn->prepare(
            "UPDATE bookings
                SET payment_status = 'Confirmed',
                    due_date       = ?
              WHERE id = ?"
        );
        $stmt->bind_param('si', $due, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            booking_redirect('error', $filter);
        }

        /* 4. Mark the bed Occupied */
        if (!empty($booking['bed_id'])) {
            $s = $conn->prepare("UPDATE beds SET status = 'Occupied' WHERE id = ?");
            $s->bind_param('i', $booking['bed_id']);
            $s->execute();
            $s->close();
        }

        booking_redirect('accept', $filter);

    } else {
        /* ── Decline ── */

        /* Verify booking exists */
        $stmt = $conn->prepare(
            "SELECT id FROM bookings WHERE id = ? AND booking_status = 'Active' LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exists) {
            booking_redirect('error', $filter);
        }

        /* Free the bed */
        free_bed($conn, $id);

        /* Cancel the booking */
        $stmt = $conn->prepare(
            "UPDATE bookings
                SET booking_status = 'Cancelled',
                    payment_status = 'Pending'
              WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        booking_redirect($ok ? 'decline' : 'error', $filter);
    }
}

/* ─────────────────────────────────────────────────────────────
   GET  action=delete
   ───────────────────────────────────────────────────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'delete') {

    $id     = (int)($_GET['id'] ?? 0);
    $filter = $_GET['status'] ?? 'all';

    if (!$id) {
        booking_redirect('error', $filter);
    }

    /* Free bed first */
    free_bed($conn, $id);

    /* Delete payments then booking (FK safe) */
    $s = $conn->prepare("DELETE FROM payments WHERE booking_id = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $s->close();

    $s = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $s->bind_param('i', $id);
    $ok = $s->execute();
    $s->close();

    booking_redirect($ok ? 'deleted' : 'error', $filter);
}

/* ─────────────────────────────────────────────────────────────
   POST  log_payment=1
   ───────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['log_payment'])) {

    $booking_id = (int)($_POST['booking_id']   ?? 0);
    $amount     = (float)($_POST['amount']      ?? 0);
    $next_due   = trim($_POST['next_due_date']  ?? '');
    $notes      = trim($_POST['notes']          ?? '');
    $filter     = $_POST['current_filter']      ?? 'confirmed';

    if (!$booking_id || $amount <= 0 || !$next_due) {
        booking_redirect('error', $filter);
    }

    /* Validate next_due date format */
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_due)) {
        booking_redirect('error', $filter);
    }

    /* Handle optional receipt upload */
    $receipt_path = null;
    if (!empty($_FILES['receipt']['tmp_name'])) {
        $upload_dir = '../uploads/receipts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $ext  = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        $safe = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        if (in_array($ext, $safe, true)) {
            $fname = 'receipt_' . $booking_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_dir . $fname)) {
                $receipt_path = 'uploads/receipts/' . $fname;
            }
        }
    }

    /* Insert payment record */
    $stmt = $conn->prepare(
        "INSERT INTO payments (booking_id, amount, notes, receipt_path, paid_at)
         VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param('idss', $booking_id, $amount, $notes, $receipt_path);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        booking_redirect('error', $filter);
    }

    /* Advance due date on the booking and set Confirmed */
    $stmt = $conn->prepare("UPDATE bookings SET due_date = ?, payment_status = 'Confirmed' WHERE id = ?");
    $stmt->bind_param('si', $next_due, $booking_id);
    $stmt->execute();
    $stmt->close();

    /* Ensure bed is marked Occupied (important if this was their first rent payment while Pending) */
    $stmt = $conn->prepare("SELECT bed_id FROM bookings WHERE id = ?");
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $b_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($b_row['bed_id'])) {
        $s = $conn->prepare("UPDATE beds SET status = 'Occupied' WHERE id = ?");
        $s->bind_param('i', $b_row['bed_id']);
        $s->execute();
        $s->close();
    }

    /* If a new receipt was uploaded, also update bookings.receipt_path */
    if ($receipt_path) {
        $s = $conn->prepare("UPDATE bookings SET receipt_path = ? WHERE id = ?");
        $s->bind_param('si', $receipt_path, $booking_id);
        $s->execute();
        $s->close();
    }

    booking_redirect('payment_logged', $filter);
}

/* ─────────────────────────────────────────────────────────────
   POST  update_status=1
   ───────────────────────────────────────────────────────────── */
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

    /* If completing or cancelling, free the bed */
    if (in_array($booking_status, ['Completed', 'Cancelled'], true)) {
        free_bed($conn, $id);
    }

    /* If newly confirmed with no due date, set it to today + 1 month */
    $due_fragment = '';
    if ($payment_status === 'Confirmed' && $booking_status === 'Active') {
        $check = $conn->prepare("SELECT due_date FROM bookings WHERE id = ?");
        $check->bind_param('i', $id);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();
        if (empty($row['due_date']) || $row['due_date'] === '0000-00-00') {
            $due_fragment = ", due_date = '" . date('Y-m-d', strtotime('+1 month')) . "'";
        }
    }

    $stmt = $conn->prepare(
        "UPDATE bookings
            SET booking_status = ?,
                payment_status = ?
                {$due_fragment}
          WHERE id = ?"
    );
    $stmt->bind_param('ssi', $booking_status, $payment_status, $id);
    $ok = $stmt->execute();
    $stmt->close();

    booking_redirect($ok ? 'status_updated' : 'error', $filter);
}

/* ─────────────────────────────────────────────────────────────
   POST  checkout=1
   ───────────────────────────────────────────────────────────── */
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
        "UPDATE bookings
            SET booking_status = 'Completed',
                move_out_date  = ?,
                remarks        = ?
          WHERE id = ?"
    );
    $stmt->bind_param('ssi', $moveout, $remarks, $id);
    $ok = $stmt->execute();
    $stmt->close();

    booking_redirect($ok ? 'checked_out' : 'error', $filter);
}
