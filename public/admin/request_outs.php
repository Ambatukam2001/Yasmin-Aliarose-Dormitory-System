<?php
require_once '../api/core.php';
require_admin_auth();

$route = 'request_outs';

$has_request_out_table = false;
$is_pgsql = (strpos($conn->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql') !== false);

if ($is_pgsql) {
    $check_table = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'request_out_requests' LIMIT 1");
} else {
    $check_table = $conn->query("SHOW TABLES LIKE 'request_out_requests'");
}

if ($check_table && $check_table->fetch()) {
    $has_request_out_table = true;
}

if ($has_request_out_table && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request_out'])) {
    $req_id = intval($_POST['request_out_id'] ?? 0);
    $action = $_POST['action_request_out'] ?? '';

    if (!$req_id || !in_array($action, ['accept', 'decline'], true)) {
        $_SESSION['flash_msg'] = "Invalid request-out action.";
        header('Location: request_outs.php');
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM request_out_requests WHERE id = ? AND status = 'Pending' LIMIT 1");
        $stmt->execute([$req_id]);
        $request_out = $stmt->fetch();

        if (!$request_out) {
            $_SESSION['flash_msg'] = "Request-out not found or already processed.";
            header('Location: request_outs.php');
            exit;
        }

        $admin_id = intval($_SESSION['admin_id'] ?? 0);

        if ($action === 'decline') {
            $stmt = $conn->prepare("UPDATE request_out_requests SET status = 'Declined', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->execute([$admin_id, $req_id]);

            $_SESSION['flash_msg'] = "Request-out declined.";
            header('Location: request_outs.php');
            exit;
        }

        $booking_id = intval($request_out['booking_id'] ?? 0);
        $request_out_date = $request_out['request_out_date'] ?? date('Y-m-d');

        $conn->beginTransaction();

        if ($booking_id > 0) {
            $stmt = $conn->prepare("SELECT bed_id FROM bookings WHERE id = ? LIMIT 1");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch();

            $old_bed_id = intval($booking['bed_id'] ?? 0);
            if ($old_bed_id > 0) {
                $stmt = $conn->prepare("UPDATE beds SET status = 'Available' WHERE id = ?");
                $stmt->execute([$old_bed_id]);
            }

            $remarks = "Request-out approved by admin";
            $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'Completed', move_out_date = ?, remarks = ? WHERE id = ?");
            $stmt->execute([$request_out_date, $remarks, $booking_id]);
        }

        $stmt = $conn->prepare("UPDATE request_out_requests SET status = 'Approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$admin_id, $req_id]);

        $conn->commit();
        $_SESSION['flash_msg'] = "Request-out approved successfully.";
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['flash_msg'] = "Request-out error: " . $e->getMessage();
    }

    header('Location: request_outs.php');
    exit;
}

$request_out_reqs = [];
if ($has_request_out_table) {
    $request_out_reqs = $conn->query("
        SELECT ro.*,
               u.full_name as user_name,
               u.username as user_username,
               b.booking_ref,
               bd.bed_no,
               rm.room_no
        FROM request_out_requests ro
        LEFT JOIN users u ON ro.user_id = u.id
        LEFT JOIN bookings b ON ro.booking_id = b.id
        LEFT JOIN beds bd ON b.bed_id = bd.id
        LEFT JOIN rooms rm ON bd.room_id = rm.id
        ORDER BY ro.created_at DESC
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request-Outs | <?php echo $site_name; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-page">
    <header class="mobile-admin-header">
        <div class="logo-stack-mini" style="background: rgba(255,255,255,0.2); width: 35px; height: 35px; border-radius: 50%; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-hotel" style="font-size: 0.85rem; color: #fff;"></i>
        </div>
        <span class="font-bold text-sm tracking-wide">ADMIN PORTAL</span>
        <button id="sidebarToggleBtn" class="sidebar-toggle" onclick="toggleSidebar(event)"><i class="fas fa-bars"></i></button>
    </header>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="view-header">
            <div>
                <h1>Request-Outs <i class="fas fa-sign-out-alt color-primary"></i></h1>
                <p>Review and process all resident request-out submissions.</p>
            </div>
        </header>

        <?php if(isset($_SESSION['flash_msg'])): ?>
            <div class="alert alert-success" style="background:#d1fae5; color:#065f46; padding:1rem; border-radius:0.5rem; margin-bottom:1rem;">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['flash_msg']; unset($_SESSION['flash_msg']); ?>
            </div>
        <?php endif; ?>

        <?php if(!$has_request_out_table): ?>
            <div class="main-card" style="margin-bottom:2rem; border:1px dashed #cbd5e1;">
                <strong>Request-Out module is not initialized.</strong>
                <p style="margin-top:0.4rem; color:#64748b;">Run <code>setup_db.php</code> once to create the <code>request_out_requests</code> table.</p>
            </div>
        <?php else: ?>
            <div class="table-container main-card table-responsive-stack">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Resident</th>
                            <th>Booking</th>
                            <th>Request-Out Date</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($request_out_reqs)): ?>
                            <tr><td colspan="6" class="text-center py-8 text-muted">No request-out records found.</td></tr>
                        <?php else: ?>
                            <?php foreach($request_out_reqs as $req): ?>
                            <tr>
                                <td data-label="Resident" class="font-bold">
                                    <?php echo htmlspecialchars($req['user_name'] ?: $req['user_username'] ?: 'Unknown'); ?>
                                </td>
                                <td data-label="Booking">
                                    <?php if(!empty($req['booking_ref'])): ?>
                                        <?php echo htmlspecialchars($req['booking_ref']); ?>
                                        <br><small class="text-muted">Room <?php echo htmlspecialchars($req['room_no'] ?? 'N/A'); ?> / Bed <?php echo htmlspecialchars($req['bed_no'] ?? 'N/A'); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Request-Out Date"><?php echo date('M d, Y', strtotime($req['request_out_date'])); ?></td>
                                <td data-label="Reason" style="max-width: 280px; white-space: normal;"><?php echo htmlspecialchars($req['reason']); ?></td>
                                <td data-label="Status">
                                    <?php
                                        $s = strtolower((string)$req['status']);
                                        $cls = 'badge-warning';
                                        if ($s === 'approved') { $cls = 'badge-confirmed'; }
                                        if ($s === 'declined') { $cls = 'badge-cancelled'; }
                                    ?>
                                    <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                                </td>
                                <td data-label="Action" class="text-right">
                                    <?php if ($s === 'pending'): ?>
                                        <form method="POST" style="display:inline-flex; gap:0.5rem; width: 100%; justify-content: flex-end;">
                                            <input type="hidden" name="request_out_id" value="<?php echo (int)$req['id']; ?>">
                                            <button type="submit" name="action_request_out" value="accept" class="btn-action btn-confirm"><i class="fas fa-check"></i> Accept</button>
                                            <button type="submit" name="action_request_out" value="decline" class="btn-action btn-danger"><i class="fas fa-times"></i> Decline</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 0.75rem; font-weight: 700;">Handled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
</body>
</html>
