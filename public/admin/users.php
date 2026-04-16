<?php
/**
 * Admin Users (Residents) Standalone
 */
require_once '../api/core.php';
require_admin_auth();
require_once 'actions.php';

$route = 'users'; // sidebar will need to handle this

$is_pgsql = (strpos($conn->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql') !== false);
if ($is_pgsql) {
    $check_ro_table = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'request_out_requests'")->fetch();
} else {
    $check_ro_table = $conn->query("SHOW TABLES LIKE 'request_out_requests'")->fetch();
}
$has_request_out_table = (bool)$check_ro_table;

$users = $conn->query("
    SELECT b.*, 
           u.id as user_actual_id,
           u.full_name as profile_name, 
           u.phone as profile_phone, 
           u.email as profile_email, 
           u.address as profile_address,
           u.emergency_contact as profile_emergency
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    WHERE b.booking_status = 'Active' 
    ORDER BY COALESCE(u.full_name, b.full_name) ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ── POST: Edit Resident Profile ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit_profile'])) {
    $u_id      = (int)($_POST['user_id'] ?? 0);
    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone']);
    $email     = trim($_POST['email']);
    $address   = trim($_POST['address']);
    $emergency = trim($_POST['emergency_contact']);

    if ($u_id) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, address = ?, emergency_contact = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $email, $address, $emergency, $u_id]);
        $_SESSION['flash_msg'] = "✅ Resident profile updated successfully!";
    }
    header('Location: users.php');
    exit;
}

// Handle transfer actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_transfer'])) {
    $t_id   = intval($_POST['transfer_id']);
    $action = $_POST['action_transfer'];

    if ($action === 'decline') {
        $stmt = $conn->prepare("UPDATE transfer_requests SET status='Declined' WHERE id=?");
        $stmt->execute([$t_id]);
        $_SESSION['flash_msg'] = "Transfer request declined.";
        header('Location: users.php');
        exit;
    }

    // ── ACCEPT: perform the actual bed move ──────────────────────────
    try {
        // 1. Fetch the transfer request
        $stmt = $conn->prepare("SELECT * FROM transfer_requests WHERE id = ? AND status = 'Pending' LIMIT 1");
        $stmt->execute([$t_id]);
        $transfer = $stmt->fetch();

        if (!$transfer) {
            $_SESSION['flash_msg'] = "Transfer request not found or already processed.";
            header('Location: users.php');
            exit;
        }

        $booking_id     = (int)$transfer['booking_id'];
        $requested_room = (int)$transfer['requested_room_id'];

        // 2. Get the current bed_id from the booking
        $stmt = $conn->prepare("SELECT bed_id FROM bookings WHERE id = ? LIMIT 1");
        $stmt->execute([$booking_id]);
        $booking    = $stmt->fetch();
        $old_bed_id = (int)($booking['bed_id'] ?? 0);

        // 3. Find an available bed in the requested room (exclude old bed in case it's in same room)
        $stmt = $conn->prepare("SELECT id FROM beds WHERE room_id = ? AND status = 'Available' AND id != ? LIMIT 1");
        $stmt->execute([$requested_room, $old_bed_id]);
        $new_bed = $stmt->fetch();

        if (!$new_bed) {
            $_SESSION['flash_msg'] = "No available beds in the requested room. Transfer could not be completed.";
            header('Location: users.php');
            exit;
        }

        $new_bed_id = (int)$new_bed['id'];

        // 4. Execute the bed swap
        $conn->beginTransaction();

        // Free the old bed
        if ($old_bed_id) {
            $stmt = $conn->prepare("UPDATE beds SET status='Available' WHERE id=?");
            $stmt->execute([$old_bed_id]);
        }
        // Mark the new bed Occupied
        $stmt = $conn->prepare("UPDATE beds SET status='Occupied' WHERE id=?");
        $stmt->execute([$new_bed_id]);
        // Update the booking to point to the new bed
        $stmt = $conn->prepare("UPDATE bookings SET bed_id=? WHERE id=?");
        $stmt->execute([$new_bed_id, $booking_id]);
        // Approve the transfer request
        $stmt = $conn->prepare("UPDATE transfer_requests SET status='Approved' WHERE id=?");
        $stmt->execute([$t_id]);

        $conn->commit();
        $_SESSION['flash_msg'] = "✅ Transfer approved — resident moved to new bed successfully.";

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['flash_msg'] = "❌ Transfer error: " . $e->getMessage();
    }

    header('Location: users.php');
    exit;
}

// Handle request-out actions
if ($has_request_out_table && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request_out'])) {
    $req_id = intval($_POST['request_out_id'] ?? 0);
    $action = $_POST['action_request_out'] ?? '';

    if (!$req_id || !in_array($action, ['accept', 'decline'], true)) {
        $_SESSION['flash_msg'] = "Invalid request-out action.";
        header('Location: users.php');
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM request_out_requests WHERE id = ? AND status = 'Pending' LIMIT 1");
        $stmt->execute([$req_id]);
        $request_out = $stmt->fetch();

        if (!$request_out) {
            $_SESSION['flash_msg'] = "Request-out not found or already processed.";
            header('Location: users.php');
            exit;
        }

        if ($action === 'decline') {
            $stmt = $conn->prepare("UPDATE request_out_requests SET status = 'Declined', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $admin_id = intval($_SESSION['admin_id'] ?? 0);
            $stmt->execute([$admin_id, $req_id]);

            $_SESSION['flash_msg'] = "Request-out declined.";
            header('Location: users.php');
            exit;
        }

        // ACCEPT flow
        $booking_id = intval($request_out['booking_id'] ?? 0);
        $request_out_date = $request_out['request_out_date'] ?? date('Y-m-d');
        $admin_id = intval($_SESSION['admin_id'] ?? 0);

        $conn->beginTransaction();

        // Free bed and complete booking when booking exists
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
        $_SESSION['flash_msg'] = "✅ Request-out approved successfully.";

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['flash_msg'] = "❌ Request-out error: " . $e->getMessage();
    }

    header('Location: users.php');
    exit;
}

// Fetch pending transfers
$transfer_reqs = $conn->query("
    SELECT t.*, u.full_name as user_name, curr_bed.bed_no as current_bed, 
           r.room_no as current_room_no,
           req_r.room_no as req_room_no 
    FROM transfer_requests t
    JOIN users u ON t.user_id = u.id
    JOIN bookings b ON t.booking_id = b.id
    LEFT JOIN beds curr_bed ON b.bed_id = curr_bed.id
    LEFT JOIN rooms r ON curr_bed.room_id = r.id
    LEFT JOIN rooms req_r ON t.requested_room_id = req_r.id
    WHERE t.status = 'Pending'
    ORDER BY t.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all request-out records (pending + processed)
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
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Directory | <?php echo $site_name; ?></title>
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
                <h1>Resident Directory <i class="fas fa-users color-primary"></i></h1>
                <p>Manage all active residents and their information.</p>
            </div>
        </header>
        
        <?php if(isset($_SESSION['flash_msg'])): ?>
            <div class="alert alert-success" style="background:#d1fae5; color:#065f46; padding:1rem; border-radius:0.5rem; margin-bottom:1rem;">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['flash_msg']; unset($_SESSION['flash_msg']); ?>
            </div>
        <?php endif; ?>

        <?php if(count($transfer_reqs) > 0): ?>
        <div class="table-container main-card table-responsive-stack" style="margin-bottom: 2rem; border: 2px solid #fbbf24;">
            <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; background: #fffbeb;">
                <h3 style="margin:0; font-family:'Outfit'; color:#b45309;"><i class="fas fa-exchange-alt"></i> Pending Transfer Requests</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Resident</th>
                        <th>Current Room</th>
                        <th>Requested Move</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($transfer_reqs as $t): ?>
                    <tr>
                        <td data-label="Resident" class="font-bold"><?php echo htmlspecialchars($t['user_name']); ?></td>
                        <td data-label="Current Room">Room <?php echo $t['current_room_no']; ?> (Bed <?php echo $t['current_bed']; ?>)</td>
                        <td data-label="Requested Move">
                            <span class="badge badge-info">Floor <?php echo $t['requested_floor']; ?></span>
                            <span class="badge badge-success">Room <?php echo $t['req_room_no']; ?></span>
                            <br><small class="text-muted"><?php echo $t['requested_bed_type']; ?></small>
                        </td>
                        <td data-label="Reason" style="max-width: 200px; white-space: normal;"><?php echo htmlspecialchars($t['reason']); ?></td>
                        <td data-label="Date" class="text-xs"><?php echo date('M d h:i A', strtotime($t['created_at'])); ?></td>
                        <td data-label="Action" class="text-right">
                            <form method="POST" style="display:inline-flex; gap:0.5rem; width: 100%; justify-content: flex-end;">
                                <input type="hidden" name="transfer_id" value="<?php echo $t['id']; ?>">
                                <button type="submit" name="action_transfer" value="accept" class="btn-action" style="background-color:#10b981; color:white; border:none; padding:0.5rem 0.8rem; border-radius:0.5rem; cursor:pointer; font-weight:600;" title="Accept"><i class="fas fa-check"></i> Accept</button>
                                <button type="submit" name="action_transfer" value="decline" class="btn-action" style="background-color:#ef4444; color:white; border:none; padding:0.5rem 0.8rem; border-radius:0.5rem; cursor:pointer; font-weight:600;" title="Decline"><i class="fas fa-times"></i> Decline</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if($has_request_out_table && count($request_out_reqs) > 0): ?>
        <div class="table-container main-card table-responsive-stack" style="margin-bottom: 2rem; border: 2px solid #cbd5e1;">
            <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; background: #f8fafc;">
                <h3 style="margin:0; font-family:'Outfit'; color:#0f172a;"><i class="fas fa-sign-out-alt"></i> Request-Out Records</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Resident</th>
                        <th>Booking</th>
                        <th>Requested Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
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
                        <td data-label="Requested Date"><?php echo date('M d, Y', strtotime($req['request_out_date'])); ?></td>
                        <td data-label="Reason" style="max-width: 240px; white-space: normal;"><?php echo htmlspecialchars($req['reason']); ?></td>
                        <td data-label="Status">
                            <?php
                                $s = strtolower((string)$req['status']);
                                $cls = 'badge-warning';
                                if ($s === 'approved') { $cls = 'badge-success'; }
                                if ($s === 'declined') { $cls = 'badge-danger'; }
                            ?>
                            <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                        </td>
                        <td data-label="Action" class="text-right">
                            <?php if (strtolower((string)$req['status']) === 'pending'): ?>
                                <form method="POST" style="display:inline-flex; gap:0.5rem; width: 100%; justify-content: flex-end;">
                                    <input type="hidden" name="request_out_id" value="<?php echo (int)$req['id']; ?>">
                                    <button type="submit" name="action_request_out" value="accept" class="btn-action" style="background-color:#10b981; color:white; border:none; padding:0.5rem 0.8rem; border-radius:0.5rem; cursor:pointer; font-weight:600;"><i class="fas fa-check"></i> Accept</button>
                                    <button type="submit" name="action_request_out" value="decline" class="btn-action" style="background-color:#ef4444; color:white; border:none; padding:0.5rem 0.8rem; border-radius:0.5rem; cursor:pointer; font-weight:600;"><i class="fas fa-times"></i> Decline</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 0.75rem; font-weight: 700;">Handled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif(!$has_request_out_table): ?>
        <div class="main-card" style="margin-bottom:2rem; padding:1rem 1.2rem; border:1px dashed #cbd5e1; border-radius:0.8rem;">
            <strong>Request-Out module is not initialized.</strong>
            <p style="margin:0.25rem 0 0; color:#64748b;">Run <code>setup_db.php</code> once to create the required table.</p>
        </div>
        <?php endif; ?>

        <div class="table-container main-card table-responsive-stack">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Resident Name</th>
                        <th>Contact</th>
                        <th>Guardian</th>
                        <th>Resident Type</th>
                        <th>Joined Date</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" class="text-center py-8 text-muted">No active residents found.</td></tr>
                    <?php else: foreach($users as $u): ?>
                    <tr>
                        <td data-label="Resident Name" class="font-bold">
                            <?php echo htmlspecialchars($u['profile_name'] ?: $u['full_name']); ?>
                            <?php if($u['user_id']): ?>
                                <i class="fas fa-check-circle" style="color:#10b981; font-size: 0.7rem;" title="Registered User"></i>
                            <?php endif; ?>
                        </td>
                        <td data-label="Contact" class="font-mono text-xs">
                            <?php echo htmlspecialchars($u['profile_phone'] ?: $u['contact_number']); ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($u['profile_email'] ?: 'No Email'); ?></small>
                        </td>
                        <td data-label="Guardian">
                            <?php echo htmlspecialchars($u['guardian_name']); ?> 
                            <br><small class="text-muted"><?php echo htmlspecialchars($u['profile_emergency'] ?: $u['guardian_contact']); ?></small>
                        </td>
                        <td data-label="Resident Type"><span class="badge badge-info"><?php echo $u['category']; ?></span></td>
                        <td data-label="Joined Date"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        <td data-label="Action" class="text-right">
                            <button class="btn-action btn-outline" 
                                    onclick="openEditUserModal('<?php echo $u['user_actual_id']; ?>', '<?php echo addslashes($u['profile_name'] ?: $u['full_name']); ?>', '<?php echo addslashes($u['profile_phone'] ?: $u['contact_number']); ?>', '<?php echo addslashes($u['profile_email'] ?: ''); ?>', '<?php echo addslashes($u['profile_address'] ?: ''); ?>', '<?php echo addslashes($u['profile_emergency'] ?: ''); ?>')">
                                <i class="fas fa-edit"></i> Edit Info
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal-wrapper" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
        <div class="modal-body max-w-500" style="background:white; padding:2rem; border-radius:1.5rem; width:100%; max-width:500px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h2 style="font-family:'Outfit'; font-weight:800; margin:0; color:var(--text-main);">Edit Resident Profile</h2>
                <button onclick="closeEditUserModal()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.25rem;"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action_edit_profile" value="1">
                <input type="hidden" name="user_id" id="edit_u_id">
                
                <div class="form-group mb-4">
                    <label style="display:block; font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.5rem;">Full Name</label>
                    <input type="text" name="full_name" id="edit_u_name" class="input-text w-full" required style="width:100%; padding:0.75rem; border:1px solid #e2e8f0; border-radius:0.75rem;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label style="display:block; font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.5rem;">Phone Number</label>
                        <input type="text" name="phone" id="edit_u_phone" class="input-text w-full" style="width:100%; padding:0.75rem; border:1px solid #e2e8f0; border-radius:0.75rem;">
                    </div>
                    <div class="form-group">
                        <label style="display:block; font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.5rem;">Email Address</label>
                        <input type="email" name="email" id="edit_u_email" class="input-text w-full" style="width:100%; padding:0.75rem; border:1px solid #e2e8f0; border-radius:0.75rem;">
                    </div>
                </div>
                <div class="form-group mb-4">
                    <label style="display:block; font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.5rem;">Home Address</label>
                    <textarea name="address" id="edit_u_address" rows="2" class="input-text w-full" style="width:100%; padding:0.75rem; border:1px solid #e2e8f0; border-radius:0.75rem; font-family:inherit;"></textarea>
                </div>
                <div class="form-group mb-6">
                    <label style="display:block; font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.5rem;">Emergency Contact Info</label>
                    <input type="text" name="emergency_contact" id="edit_u_emergency" class="input-text w-full" placeholder="Name / Relationship / Phone" style="width:100%; padding:0.75rem; border:1px solid #e2e8f0; border-radius:0.75rem;">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width:100%; padding:1rem; border-radius:1rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">Update Profile <i class="fas fa-save" style="margin-left:0.5rem;"></i></button>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
    <script>
    function openEditUserModal(id, name, phone, email, address, emergency) {
        document.getElementById('edit_u_id').value = id;
        document.getElementById('edit_u_name').value = name;
        document.getElementById('edit_u_phone').value = phone;
        document.getElementById('edit_u_email').value = email;
        document.getElementById('edit_u_address').value = address;
        document.getElementById('edit_u_emergency').value = emergency;
        
        document.getElementById('editUserModal').style.display = 'flex';
    }
    function closeEditUserModal() {
        document.getElementById('editUserModal').style.display = 'none';
    }
    </script>
</body>
</html>
