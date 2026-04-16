<?php 
require_once 'api/core.php';

// Enforce login for profile/dashboard
if (!isset($_SESSION['user_id'])) {
    if (isset($_SESSION['admin_id'])) {
        redirect('admin/dashboard.php');
    }
    redirect('login.php');
}

$user_id = (int)$_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

$is_pgsql = (strpos($conn->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql') !== false);

function table_exists(PDO $conn, string $table, bool $is_pgsql): bool {
    if ($is_pgsql) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?");
    } else {
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    }
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function column_exists(PDO $conn, string $table, string $column, bool $is_pgsql): bool {
    if ($is_pgsql) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = ? AND column_name = ?");
        $stmt->execute([$table, $column]);
    } else {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
    }
    return (bool)$stmt->fetchColumn();
}

$has_request_out_table = table_exists($conn, 'request_out_requests', $is_pgsql);

// Handle Request-Out Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request_out'])) {
    if (!$has_request_out_table) {
        $error_msg = "Request-out module is not initialized yet. Please run setup first.";
    } else {
        $request_booking_id = intval($_POST['request_out_booking_id'] ?? 0);
        $request_out_date = trim($_POST['request_out_date'] ?? '');
        $request_out_reason = trim($_POST['request_out_reason'] ?? '');

        if (!$request_booking_id || $request_out_date === '' || $request_out_reason === '') {
            $error_msg = "Please complete all Request-Out fields.";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $request_out_date)) {
            $error_msg = "Please provide a valid request-out date.";
        } else {
            // Ensure booking belongs to current user and is active-like
            $check_booking = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ? AND booking_status IN ('Active', 'Confirmed', 'accepted') LIMIT 1");
            $check_booking->execute([$request_booking_id, $user_id]);
            $booking_ok = $check_booking->fetch(PDO::FETCH_ASSOC);

            if (!$booking_ok) {
                $error_msg = "Only active bookings can submit a request-out.";
            } else {
                // Prevent duplicate pending request-outs for the same booking
                $dup_stmt = $conn->prepare("SELECT id FROM request_out_requests WHERE booking_id = ? AND status = 'Pending' LIMIT 1");
                $dup_stmt->execute([$request_booking_id]);
                $dup = $dup_stmt->fetch(PDO::FETCH_ASSOC);

                if ($dup) {
                    $error_msg = "You already have a pending request-out for this booking.";
                } else {
                    $insert_req = $conn->prepare("INSERT INTO request_out_requests (user_id, booking_id, request_out_date, reason, status) VALUES (?, ?, ?, ?, 'Pending')");
                    if ($insert_req->execute([$user_id, $request_booking_id, $request_out_date, $request_out_reason])) {
                        $success_msg = "Request-out submitted. Admin will review it soon.";
                    } else {
                        $error_msg = "Failed to submit request-out. Please try again.";
                    }
                }
            }
        }
    }
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    
    if ($full_name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please provide a valid full name and email address.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, address=?, emergency_contact=? WHERE id=?");
        
        if ($stmt->execute([$full_name, $email, $phone, $address, $emergency_contact, $user_id])) {
            // Keep booking records consistent with user profile
            $has_full_name = column_exists($conn, 'bookings', 'full_name', $is_pgsql);
            $has_phone     = column_exists($conn, 'bookings', 'contact_number', $is_pgsql);

            if ($has_full_name || $has_phone) {
                $sets = [];
                $vals = [];
                if ($has_full_name) {
                    $sets[] = "full_name = ?";
                    $vals[] = $full_name;
                }
                if ($has_phone) {
                    $sets[] = "contact_number = ?";
                    $vals[] = $phone;
                }
                if (!empty($sets)) {
                    $vals[] = $user_id;
                    $sql = "UPDATE bookings SET " . implode(', ', $sets) . " WHERE user_id = ?";
                    $sync = $conn->prepare($sql);
                    $sync->execute($vals);
                }
            }

            $success_msg = "Profile updated successfully!";
        } else {
            $error_msg = "Failed to update profile. Please try a different email or try again.";
        }
    }
}

// Fetch User Info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Rent Statistics & Overdue Status
$total_paid = 0;
$overdue_count = 0;
$total_overdue_amount = 0;

// Fetch Bookings with payment stats
$bookings = [];
$stmt = $conn->prepare("SELECT b.*, r.room_no, bd.bed_no 
    FROM bookings b 
    LEFT JOIN beds bd ON b.bed_id = bd.id 
    LEFT JOIN rooms r ON bd.room_id = r.id 
    WHERE b.user_id = ? 
    AND b.booking_status != 'Cancelled'
    ORDER BY b.created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$res_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($res_bookings as $row) {
    $status = strtolower((string) ($row['booking_status'] ?? ''));
    // Check if overdue: Must be Active, have a due date, and that date must be in the past
    $row['is_overdue'] = false;
    if (in_array($status, ['active', 'confirmed', 'accepted'], true) && !empty($row['due_date']) && $row['due_date'] !== '0000-00-00') {
        if (strtotime($row['due_date']) < time()) {
            $row['is_overdue'] = true;
            $overdue_count++;
            $total_overdue_amount += floatval($row['current_balance'] ?: $row['monthly_rent']);
        }
    }
    
    // Fetch total paid for this booking
    $stmt_pay = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE booking_id = ?");
    $stmt_pay->execute([$row['id']]);
    $pay_res = $stmt_pay->fetch(PDO::FETCH_ASSOC);
    $row['total_paid'] = $pay_res['total'] ?? 0;
    
    $total_paid += floatval($row['total_paid']);
    $bookings[] = $row;
}

// Fetch Rent/Payment History
$stmt = $conn->prepare("SELECT p.*, bd.bed_no FROM payments p JOIN bookings b ON p.booking_id = b.id LEFT JOIN beds bd ON b.bed_id = bd.id WHERE b.user_id = ? ORDER BY p.paid_at DESC");
$stmt->execute([$user_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user request-out history
$request_outs = [];
if ($has_request_out_table) {
    $stmt = $conn->prepare("SELECT * FROM request_out_requests WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $request_outs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = "My Dashboard";
include 'api/head.php';
include 'api/header.php';
?>


<style>
    .dashboard-container { max-width: 1200px; margin: 2rem auto; padding: 0 1.25rem; }
    
    /* Layout collapse */
    .dashboard-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; align-items: start; }
    
    /* Panel Overrides */
    .panel { 
        border: 1px solid var(--glass-border) !important; 
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05) !important; 
        background: var(--white) !important; 
        border-radius: 1.5rem !important; 
        margin-bottom: 2rem; 
        overflow: hidden;
    }
    .panel-header { 
        background: rgba(16, 185, 129, 0.03) !important; 
        border-bottom: 1px solid rgba(16, 185, 129, 0.05) !important; 
        padding: 1.5rem 2rem !important; 
    }
    .panel-body { padding: 2rem !important; }

    .color-primary { color: var(--primary); }

    /* Welcome Banner Customization */
    .welcome-banner { 
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); 
        border-radius: 2rem; 
        padding: 4rem 3rem; 
        margin-bottom: 3.5rem; 
        position: relative; 
        overflow: hidden; 
        display: flex; 
        align-items: center; 
        gap: 3rem; 
        color: white;
        box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.3);
        margin-top: 1rem;
    }

    .welcome-glow {
        position: absolute;
        top: 0;
        right: 0;
        width: 380px;
        height: 380px;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.1) 0%, transparent 70%);
        border-radius: 50%;
        transform: translate(30%, -30%);
        pointer-events: none;
    }

    .welcome-content { flex: 1; z-index: 1; min-width: 0; }

    .welcome-title {
        font-family: 'Outfit';
        font-size: clamp(1.65rem, 4.8vw, 2.6rem);
        margin: 0;
        font-weight: 800;
        line-height: 1.15;
        letter-spacing: -0.02em;
        word-break: break-word;
    }

    .welcome-subtitle {
        margin: 0.75rem 0 0;
        opacity: 0.8;
        font-size: clamp(0.92rem, 2.4vw, 1.05rem);
        font-weight: 500;
        max-width: 42ch;
    }
    
    .welcome-avatar { 
        width: 110px; 
        height: 110px; 
        border-radius: 50%; 
        background: var(--primary-gradient); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 3.5rem; 
        font-weight: 800; 
        font-family: 'Outfit'; 
        box-shadow: 0 0 0 10px rgba(16, 185, 129, 0.1); 
        flex-shrink: 0;
        animation: scaleIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    /* Action Buttons in Header */
    .welcome-actions .btn {
        border-radius: 1.5rem !important;
        padding: 0.82rem 1.2rem !important;
        font-weight: 800 !important;
        font-size: 0.92rem !important;
        letter-spacing: -0.01em;
    }
    
    .btn-glass {
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.15);
        backdrop-filter: blur(10px);
        color: white;
        border-radius: 1.25rem;
    }

    .btn-white {
        background: white;
        color: #0f172a;
        border: none;
        border-radius: 1.25rem;
    }

    .stat-cards {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.15rem;
        padding: 1.15rem 0.35rem 1.2rem;
        margin-bottom: 2.15rem;
    }

    .stat-cards .stat-card {
        display: flex;
        align-items: center;
        gap: 0.95rem;
        padding: 1.1rem 1.25rem;
        border-radius: 1.15rem;
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: 0 18px 40px -28px rgba(15, 23, 42, 0.45);
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .stat-cards .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 24px 55px -30px rgba(15, 23, 42, 0.55);
        border-color: rgba(16, 185, 129, 0.22);
    }

    @media (min-width: 901px) {
        .stat-cards .stat-card {
            padding: 1.05rem 1.2rem;
        }
    }

    .stat-cards .stat-icon {
        width: 46px;
        height: 46px;
        min-width: 46px;
        border-radius: 1rem;
    }

    .stat-cards .stat-info h4 {
        margin: 0;
        font-size: 0.8rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #64748b;
    }

    .stat-cards .stat-info p {
        margin: 0.22rem 0 0;
        font-size: clamp(1.05rem, 1.9vw, 1.35rem);
        font-weight: 900;
        line-height: 1.18;
        letter-spacing: -0.01em;
        color: #0f172a;
    }

    /* Pay Now Button (Rent Functionality) */
    .btn-pay-now {
        background: var(--primary-gradient) !important;
        border: none !important;
        color: white !important;
        box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2) !important;
        border-radius: 1rem !important;
        padding: 0.72rem 1rem !important;
        font-size: 0.85rem !important;
        width: 100%;
        max-width: 100%;
        margin-top: 0.5rem;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        gap: 0.42rem;
        white-space: nowrap;
        text-align: center;
        min-height: 44px;
        box-sizing: border-box;
        transform-origin: center center;
    }

    .btn-pay-now:hover {
        transform: translateY(-2px) scale(1);
        box-shadow: 0 15px 30px rgba(16, 185, 129, 0.3) !important;
    }

    .drawer-shell { margin-top: 1.5rem; }
    .drawer-title {
        font-family: 'Outfit';
        font-weight: 800;
        font-size: clamp(1.45rem, 4vw, 2rem);
        margin-bottom: 0.7rem;
        color: var(--text-primary);
        line-height: 1.15;
    }
    .drawer-subtitle {
        color: var(--text-secondary);
        font-size: 0.95rem;
        margin-bottom: 1.45rem;
    }
    .pay-method-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.8rem;
        margin-bottom: 1.5rem;
    }
    .pay-opt-card {
        display: flex;
        text-align: left;
        align-items: center;
        gap: 1rem;
        border: 1.5px solid rgba(148, 163, 184, 0.35);
        border-radius: 1rem;
        padding: 0.85rem 1rem;
        transition: 0.25s ease;
        cursor: pointer;
        width: 100%;
        min-height: 78px;
        box-sizing: border-box;
    }
    .pay-opt-card:hover {
        transform: translateY(-1px);
        border-color: rgba(16, 185, 129, 0.28);
        box-shadow: 0 10px 20px -16px rgba(15, 23, 42, 0.45);
    }
    .pay-opt-card.active {
        border-color: var(--primary);
        background: rgba(16, 185, 129, 0.06);
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.12);
    }
    .pay-method-meta h4 {
        font-size: 0.95rem;
        margin: 0;
    }
    .pay-method-meta p {
        font-size: 0.78rem;
        margin: 0;
        opacity: 0.72;
    }
    .pay-opt-card .val {
        white-space: nowrap;
        text-align: right;
        line-height: 1.2;
    }

    .profile-form {
        display: grid;
        gap: 0.85rem;
    }
    .profile-form .form-group {
        margin-bottom: 0;
    }
    .profile-form label {
        display: block;
        font-size: clamp(0.82rem, 1.5vw, 0.92rem);
        font-weight: 700;
        color: var(--text-secondary);
        margin-bottom: 0.35rem;
        letter-spacing: 0.01em;
    }
    .profile-form .form-control {
        border-radius: 0.85rem;
        font-size: clamp(0.89rem, 1.9vw, 0.98rem);
        padding: 0.72rem 0.85rem;
        line-height: 1.35;
    }
    .profile-form textarea.form-control {
        min-height: 84px;
        resize: vertical;
    }
    .profile-form .btn {
        margin-top: 0.3rem !important;
        font-size: clamp(0.9rem, 1.8vw, 1rem) !important;
    }

    .panel-header h3 {
        margin: 0;
        font-size: clamp(1rem, 2.3vw, 1.22rem);
        line-height: 1.25;
        letter-spacing: -0.01em;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Ensure profile textareas are full-width */
    .profile-form .form-control {
        width: 100%;
        box-sizing: border-box;
    }

    .payment-history-table {
        width: 100%;
        border-collapse: collapse;
    }
    .payment-history-table thead th {
        font-size: 0.78rem;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: var(--text-muted);
        font-weight: 800;
        padding: 0.85rem 0.9rem;
    }
    .payment-history-table tbody td {
        padding: 0.85rem 0.9rem;
        font-size: 0.9rem;
        vertical-align: middle;
        border-top: 1px solid rgba(148, 163, 184, 0.14);
    }
    .payment-method-chip {
        font-size: 0.76rem;
        background: #f1f5f9;
        padding: 0.28rem 0.62rem;
        border-radius: 0.5rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .receipt-upload-box {
        border: 2px dashed var(--glass-border);
        border-radius: 1rem;
        padding: 1.25rem 1rem;
        text-align: center;
        cursor: pointer;
        background: rgba(15,23,42,0.02);
        transition: 0.3s;
    }
    .receipt-upload-box:hover {
        border-color: rgba(16, 185, 129, 0.45);
        background: rgba(16, 185, 129, 0.04);
    }
    .helper-note {
        background: var(--primary-subtle);
        border-radius: 0.9rem;
        padding: 0.95rem 1rem;
        font-size: 0.82rem;
        color: var(--primary-dark);
        display: flex;
        gap: 0.7rem;
        align-items: start;
    }
    .transfer-form .form-group {
        margin-bottom: 0.95rem;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .transfer-form {
        padding-left: 0.55rem;
        padding-right: 0.35rem;
        box-sizing: border-box;
        width: 100%;
    }
    .transfer-form label {
        font-weight: 700;
        font-size: 0.84rem;
        color: var(--text-secondary);
        margin-bottom: 0.4rem;
        display: block;
    }
    .transfer-form .form-control {
        border-radius: 0.85rem;
        font-size: 0.9rem;
        min-height: 44px;
        width: 100%;
        padding: 0.72rem 0.92rem;
        box-sizing: border-box;
        border: 1.2px solid rgba(148, 163, 184, 0.35);
        background-color: #fff;
        color: #1e293b;
        font-family: 'Inter', system-ui, sans-serif;
        line-height: 1.35;
        transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
    }
    .transfer-form .form-control:hover {
        border-color: rgba(16, 185, 129, 0.45);
    }
    .transfer-form .form-control:focus {
        border-color: rgba(16, 185, 129, 0.75);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        outline: none;
    }
    .transfer-form select.form-control {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        padding-right: 2.5rem;
        background-image: linear-gradient(45deg, transparent 50%, #64748b 50%), linear-gradient(135deg, #64748b 50%, transparent 50%);
        background-position: calc(100% - 18px) calc(50% - 3px), calc(100% - 12px) calc(50% - 3px);
        background-size: 6px 6px, 6px 6px;
        background-repeat: no-repeat;
    }
    .transfer-form textarea.form-control {
        min-height: 92px;
        resize: vertical;
    }
    .transfer-form .drawer-cta {
        margin-top: 1.1rem;
    }
    .transfer-form select.form-control:disabled {
        background-color: #f8fafc;
        color: #94a3b8;
        cursor: not-allowed;
    }
    .drawer-cta {
        width: 100%;
        margin-top: 1.35rem;
        padding: 0.95rem !important;
        font-size: 0.95rem !important;
        border-radius: 1rem;
        font-weight: 800;
    }

    /* Make transfer drawer larger on desktop */
    #transferModalOverlay .drawer-content {
        width: min(560px, 100vw);
        max-width: 560px;
    }

    .profile-empty-state {
        min-height: 160px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 1.4rem 1rem;
    }
    .profile-empty-state i {
        font-size: 1.4rem;
        color: var(--primary);
        opacity: 0.9;
        margin-bottom: 0.5rem;
    }
    .profile-empty-state p {
        margin: 0;
        color: var(--text-secondary);
        font-weight: 600;
    }

    .request-out-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    .request-out-form {
        border: 1px solid rgba(148, 163, 184, 0.22);
        border-radius: 0.95rem;
        padding: 1rem;
        background: rgba(248, 250, 252, 0.68);
    }
    .request-out-form .form-group {
        margin-bottom: 0.75rem;
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }
    .request-out-form label {
        display: block;
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--text-secondary);
        margin-bottom: 0.32rem;
    }
    .request-out-form .form-control {
        border-radius: 0.75rem;
        min-height: 42px;
        font-size: 0.9rem;
        width: 100%;
        box-sizing: border-box;
        padding: 0.7rem 0.85rem;
        border: 1.2px solid rgba(148, 163, 184, 0.35);
        background: #fff;
        color: #1e293b;
        font-family: 'Inter', system-ui, sans-serif;
        line-height: 1.35;
    }
    .request-out-form .form-control:focus {
        border-color: rgba(16, 185, 129, 0.7);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
        outline: none;
    }
    .request-out-form textarea.form-control {
        min-height: 94px;
        resize: vertical;
    }
    .request-out-list .row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 0.5rem 1rem;
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 0.8rem;
        padding: 0.75rem 0.85rem;
        margin-bottom: 0.65rem;
    }
    .request-out-meta {
        font-size: 0.82rem;
        color: var(--text-secondary);
        margin-top: 0.18rem;
    }
    .request-out-reason {
        grid-column: 1 / -1;
        font-size: 0.84rem;
        color: var(--text-primary);
        background: rgba(15, 23, 42, 0.03);
        border-radius: 0.6rem;
        padding: 0.5rem 0.65rem;
    }

    /* Welcome Banner Flex Logic */
    @media (max-width: 900px) { 
        .dashboard-grid { grid-template-columns: 1fr; }
        .welcome-banner { flex-direction: column; text-align: center; padding: 2.2rem 1.2rem !important; gap: 1rem; margin-top: 0; }
        .welcome-avatar { width: 90px !important; height: 90px !important; font-size: 2.75rem !important; margin: 0 auto; }
        .welcome-actions { width: 100%; flex-direction: column; gap: 0.75rem; }
        .welcome-actions .btn { width: 100%; padding: 0.85rem 1.25rem !important; }
        .stat-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); max-width: 100%; gap: 0.9rem; padding: 1rem 0 1rem; margin-bottom: 1.6rem; }
        .drawer-content { width: min(100vw, 460px); max-width: 100vw; }
        .drawer-shell { margin-top: 1.25rem; }
        .pay-opt-card .val { font-size: 0.8rem !important; }
        .btn-pay-now {
            font-size: 0.82rem !important;
            padding: 0.7rem 0.9rem !important;
            transform: scale(0.94);
        }
        .btn-pay-now:hover {
            transform: translateY(-2px) scale(0.95);
        }
        .transfer-form {
            padding-left: 0.3rem;
            padding-right: 0.2rem;
        }
        .transfer-form .form-control {
            font-size: 0.88rem;
            min-height: 42px;
        }
    }

    @media (max-width: 576px) {
        .dashboard-container { padding: 0 0.8rem; margin: 1.2rem auto; }
        .panel-header { padding: 1rem 1rem !important; }
        .panel-body { padding: 1rem !important; }
        .welcome-avatar { width: 78px !important; height: 78px !important; font-size: 2.1rem !important; }
        .welcome-title { font-size: clamp(1.35rem, 6vw, 1.75rem); }
        .welcome-subtitle { font-size: 0.88rem; }
        .drawer-close-btn {
            width: 36px !important;
            height: 36px !important;
            top: 1rem !important;
            left: 1rem !important;
        }
        .pay-opt-card { padding: 0.75rem 0.78rem; }
        .pay-opt-card .stat-icon { width: 40px !important; height: 40px !important; font-size: 1rem !important; }
        .drawer-subtitle { margin-bottom: 1rem; }
        .stat-cards { grid-template-columns: 1fr; padding: 0.85rem 0 0.9rem; }
        .payment-history-table tbody td,
        .payment-history-table thead th { font-size: 0.82rem; }
        .btn-pay-now {
            font-size: 0.78rem !important;
            padding: 0.62rem 0.65rem !important;
            border-radius: 0.85rem !important;
            transform: scale(0.88);
            min-height: 44px;
        }
        .btn-pay-now:hover {
            transform: translateY(-2px) scale(0.9);
        }
        .transfer-form {
            padding-left: 0;
            padding-right: 0;
        }
        .transfer-form .form-group {
            margin-bottom: 0.8rem;
        }
        .transfer-form .form-control {
            font-size: 0.87rem;
            padding: 0.65rem 0.78rem;
        }
        .transfer-form textarea.form-control {
            min-height: 88px;
        }
        .request-out-form {
            padding: 0.8rem;
        }
        .request-out-form .form-control {
            font-size: 0.87rem;
            padding: 0.62rem 0.75rem;
        }
        .request-out-list .row {
            grid-template-columns: 1fr;
        }
    }

    /* Profile dashboard — deep dark theme alignment */
    body.dark-theme .panel {
        background: #0a0a0a !important;
        border-color: rgba(255, 255, 255, 0.08) !important;
    }
    body.dark-theme .panel-header {
        background: rgba(16, 185, 129, 0.06) !important;
        border-bottom-color: rgba(255, 255, 255, 0.06) !important;
    }
    body.dark-theme .panel-header h3 {
        color: #f8fafc;
    }
    body.dark-theme .stat-cards .stat-card {
        background: #0f172a !important;
        border-color: rgba(255, 255, 255, 0.08) !important;
        box-shadow: 0 18px 40px -28px rgba(0, 0, 0, 0.65) !important;
    }
    body.dark-theme .stat-cards .stat-info h4 {
        color: #94a3b8;
    }
    body.dark-theme .stat-cards .stat-info p {
        color: #f8fafc;
    }
    body.dark-theme .welcome-banner {
        background: linear-gradient(135deg, #020617 0%, #0f172a 100%);
        box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.5);
    }
    body.dark-theme .payment-history-table thead th {
        color: #94a3b8;
    }
    body.dark-theme .payment-history-table tbody td {
        border-color: rgba(255, 255, 255, 0.06);
        color: #e2e8f0;
    }
    body.dark-theme .payment-method-chip {
        background: #1e293b;
        color: #cbd5e1;
    }
    body.dark-theme .receipt-upload-box {
        background: #0f172a;
        border-color: rgba(255, 255, 255, 0.1);
    }
    body.dark-theme .request-out-form {
        background: rgba(15, 23, 42, 0.85);
        border-color: rgba(255, 255, 255, 0.08);
    }
    body.dark-theme .transfer-form .form-control {
        background: #0f172a;
        color: #f8fafc;
        border-color: rgba(255, 255, 255, 0.12);
    }
    body.dark-theme .transfer-form select.form-control:disabled {
        background-color: #020617;
        color: #64748b;
    }
    body.dark-theme .profile-form .form-control {
        background: #0f172a;
        color: #f8fafc;
        border-color: rgba(255, 255, 255, 0.1);
    }
</style>

<div class="dashboard-container">
    <!-- Premium Profile Header -->
    <div class="welcome-banner">
        <div class="welcome-glow"></div>
        
        <div class="welcome-avatar">
            <?php echo strtoupper(substr($user_info['full_name'] ?: $user_info['username'], 0, 1)); ?>
        </div>
        
        <div class="welcome-content">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user_info['full_name'] ?: $user_info['username']); ?>!</h1>
            <p class="welcome-subtitle">Managing your dormitory stay with Yasmin & Aliarose.</p>
        </div>
        
        <div class="welcome-actions" style="z-index: 1; display: flex; gap: 1rem;">
            <button class="btn btn-glass" onclick="openTransferModal(<?php echo !empty($bookings) ? htmlspecialchars($bookings[0]['id']) : 0; ?>)">
                <i class="fas fa-exchange-alt"></i> Transfer Request
            </button>
            <a href="booking.php" class="btn btn-primary btn-white" onclick="return YA_DORM.handleBookingClick(event)">Book a New Bed</a>
        </div>
    </div>

    <?php if($success_msg): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?></div>
    <?php endif; ?>

    <div class="stat-cards">
        <div class="stat-card stat-card-info">
            <div class="stat-icon"><i class="fas fa-bed"></i></div>
            <div class="stat-info">
                <h4>Active Bookings</h4>
                <p><?php echo count(array_filter($bookings, function($b){ return !in_array(strtolower((string)($b['booking_status'] ?? '')), ['cancelled', 'declined', 'rejected'], true); })); ?></p>
            </div>
        </div>
        <div class="stat-card stat-card-success">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-info">
                <h4>Total Rent Paid</h4>
                <p>₱<?php echo number_format($total_paid, 2); ?></p>
            </div>
        </div>
        <div class="stat-card <?php echo $overdue_count > 0 ? 'stat-card-warning' : 'stat-card-success'; ?>">
            <div class="stat-icon"><i class="fas <?php echo $overdue_count > 0 ? 'fa-exclamation-circle' : 'fa-check'; ?>"></i></div>
            <div class="stat-info">
                <h4>Overdue Rent</h4>
                <p><?php echo $overdue_count > 0 ? '₱'.number_format($total_overdue_amount, 2) : 'None'; ?></p>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Account Settings Panel -->
        <div class="panel">
            <div class="panel-header">
                <h3><i class="fas fa-user-cog color-primary"></i> Personal Info</h3>
            </div>
            <div class="panel-body">
                <form method="POST" class="profile-form">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user_info['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Home Address</label>
                        <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user_info['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact</label>
                        <input type="text" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($user_info['emergency_contact'] ?? ''); ?>">
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary" style="width: 100%; border-radius: 1rem; padding: 0.95rem !important; font-weight: 800;">Update Profile</button>
                </form>
            </div>
        </div>

        <!-- Main Area: Bookings & Payments -->
        <div>
            <!-- Bookings List -->
            <div class="panel">
                <div class="panel-header">
                    <h3><i class="fas fa-bed color-primary"></i> Current Booking</h3>
                </div>
                <div class="table-responsive-stack">
                    <?php if(empty($bookings)): ?>
                        <div class="empty-state profile-empty-state">
                            <i class="fas fa-bed"></i>
                            <p>You haven't booked a bed yet.</p>
                            <a href="booking.php" class="btn btn-outline" style="margin-top: 1rem;" onclick="return YA_DORM.handleBookingClick(event)">Find a Bed</a>
                        </div>
                    <?php else: ?>
                        <table class="payment-history-table">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Room / Bed</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Rent Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($bookings as $b): ?>
                                <tr>
                                    <td data-label="Booking ID"><strong>#<?php echo $b['id']; ?></strong></td>
                                    <td data-label="Room / Bed">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <span style="font-weight:600;"><?php echo htmlspecialchars('Bed ' . ($b['bed_no'] ?? $b['bed_id'])); ?></span>
                                            <?php if($b['room_no']): ?>
                                                <small style="color:#64748b;">(Room <?php echo $b['room_no']; ?>)</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Status">
                                        <?php 
                                            $s = strtolower($b['booking_status']);
                                            $badgeClass = 'badge-warning';
                                            $displayStatus = 'Pending';
                                            
                                            if (in_array($s, ['active', 'confirmed', 'accepted'])) {
                                                $badgeClass = 'badge-success';
                                                $displayStatus = 'Accepted';
                                            } elseif (in_array($s, ['declined', 'cancelled', 'rejected'])) {
                                                $badgeClass = 'badge-danger';
                                                $displayStatus = 'Declined';
                                            }
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo $displayStatus; ?>
                                        </span>
                                    </td>
                                    <td data-label="Due Date"><?php echo $b['due_date'] ? date('M d, Y', strtotime($b['due_date'])) : 'N/A'; ?></td>
                                    <td data-label="Rent Status">
                                        <?php if($b['is_overdue']): ?>
                                            <span class="badge badge-danger">Overdue</span>
                                        <?php elseif($b['booking_status'] === 'Active' || $b['booking_status'] === 'Confirmed'): ?>
                                            <span class="badge badge-success">Good</span>
                                        <?php else: ?>
                                            <span class="badge" style="background:#e2e8f0;color:#475569;">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Action">
                                        <?php if(strtolower($b['booking_status']) === 'active' || strtolower($b['booking_status']) === 'confirmed' || strtolower($b['booking_status']) === 'accepted'): ?>
                                            <button class="btn btn-pay-now <?php echo $b['is_overdue'] ? 'btn-pulse' : ''; ?>" onclick="openPaymentModal(<?php echo $b['id']; ?>)">
                                                <i class="fas fa-wallet"></i> Pay Rent
                                            </button>
                                        <?php else: ?>
                                            <div style="color: var(--text-muted); font-size: 0.8rem;">-</div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rent History -->
            <div class="panel">
                <div class="panel-header">
                    <h3><i class="fas fa-history color-primary"></i> Payment History</h3>
                </div>
                <div class="table-responsive-stack">
                    <?php if(empty($payments)): ?>
                        <div class="empty-state profile-empty-state">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <p>No payment history available.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Bed Service</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($payments as $p): ?>
                                <tr>
                                    <td data-label="Date" style="white-space: nowrap;"><?php echo date('M d, Y h:i A', strtotime($p['paid_at'])); ?></td>
                                    <td data-label="Bed Service" style="font-weight: 600;"><?php echo htmlspecialchars($p['bed_no'] ? 'Bed '.$p['bed_no'] : 'N/A'); ?></td>
                                    <td data-label="Amount" style="font-weight:800;color:var(--primary);">₱<?php echo number_format($p['amount'], 2); ?></td>
                                    <td data-label="Method">
                                        <span class="payment-method-chip">
                                            <?php echo htmlspecialchars($p['payment_method'] ?: 'Cash'); ?>
                                        </span>
                                    </td>
                                    <td data-label="Reference" style="font-size: 0.85rem; color: var(--text-secondary);"><?php echo htmlspecialchars($p['notes'] ?: '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Request-Out -->
            <div class="panel">
                <div class="panel-header">
                    <h3><i class="fas fa-sign-out-alt color-primary"></i> Request-Out</h3>
                </div>
                <div class="panel-body">
                    <div class="request-out-grid">
                        <?php if ($has_request_out_table): ?>
                            <form method="POST" class="request-out-form">
                                <input type="hidden" name="submit_request_out" value="1">
                                <input type="hidden" name="request_out_booking_id" value="<?php echo !empty($bookings) ? (int)$bookings[0]['id'] : 0; ?>">
                                <div class="form-group">
                                    <label>Preferred Request-Out Date</label>
                                    <input type="date" name="request_out_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" <?php echo empty($bookings) ? 'disabled' : 'required'; ?>>
                                </div>
                                <div class="form-group">
                                    <label>Reason</label>
                                    <textarea name="request_out_reason" class="form-control" rows="3" placeholder="Explain your request-out reason..." <?php echo empty($bookings) ? 'disabled' : 'required'; ?>></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary" style="width:100%;" <?php echo empty($bookings) ? 'disabled' : ''; ?>>
                                    Submit Request-Out <i class="fas fa-paper-plane"></i>
                                </button>
                                <?php if (empty($bookings)): ?>
                                    <p class="request-out-meta">You need an active booking to submit a request-out.</p>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <div class="request-out-form">
                                <p class="request-out-meta" style="margin:0;">
                                    Request-out module is not initialized. Run `setup_db.php` once to create required tables.
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="request-out-list">
                            <?php if (empty($request_outs)): ?>
                                <div class="empty-state profile-empty-state" style="min-height:130px;">
                                    <i class="fas fa-clipboard-list"></i>
                                    <p>No request-out history yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($request_outs as $ro): ?>
                                    <?php
                                        $ro_status = strtolower((string)($ro['status'] ?? 'pending'));
                                        $badge = 'badge-warning';
                                        if ($ro_status === 'approved') { $badge = 'badge-success'; }
                                        if ($ro_status === 'declined') { $badge = 'badge-danger'; }
                                    ?>
                                    <div class="row">
                                        <div>
                                            <strong>Request-Out: <?php echo date('M d, Y', strtotime($ro['request_out_date'])); ?></strong>
                                            <div class="request-out-meta">Submitted: <?php echo date('M d, Y h:i A', strtotime($ro['created_at'])); ?></div>
                                        </div>
                                        <div><span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($ro['status']); ?></span></div>
                                        <div class="request-out-reason"><?php echo htmlspecialchars($ro['reason']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     PAYMENT MODAL (SIDE DRAWER)
     ══════════════════════════════════-->
<div id="paymentModalOverlay" class="drawer-overlay" onclick="if(event.target===this) closePaymentModal()">
    <div class="drawer-content">
        <button onclick="closePaymentModal()" class="drawer-close-btn" style="position:absolute; top:2rem; left:2rem; background:rgba(15, 23, 42, 0.05); border:none; width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--text-secondary); cursor:pointer;"><i class="fas fa-chevron-right"></i></button>
        
        <div class="drawer-shell">
            <h3 class="drawer-title">Pay Your Rent</h3>
            <p class="drawer-subtitle">Settle <strong id="payBedLabel" class="color-primary"></strong> securely.</p>

            <div class="pay-method-grid">
                <!-- GCASH -->
                <div class="pay-opt-card" id="optGcash" onclick="selectPayMethod('GCash')">
                    <div class="stat-icon" style="width:48px; height:48px; background:rgba(16,185,129,0.1); color:var(--primary); font-size:1.25rem;"><i class="fas fa-mobile-alt"></i></div>
                    <div class="pay-method-meta" style="flex:1;">
                        <h4>GCash</h4>
                        <p>Digital Transfer</p>
                    </div>
                    <span class="val" style="font-weight:800; color:var(--primary); font-size:0.85rem;">0912 345 6789</span>
                </div>
                <!-- CASH IN -->
                <div class="pay-opt-card" id="optCash" onclick="selectPayMethod('Cash In')">
                    <div class="stat-icon" style="width:48px; height:48px; background:rgba(148,163,184,0.1); color:var(--text-muted); font-size:1.25rem;"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="pay-method-meta" style="flex:1;">
                        <h4>Cash In</h4>
                        <p>Visit Office</p>
                    </div>
                    <span class="val" style="font-weight:800; color:var(--text-muted); font-size:0.85rem;">Admin Desk</span>
                </div>
            </div>

            <div id="paymentFormArea" style="max-height:0; overflow:hidden; transition:0.4s ease; opacity:0;">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="font-size: 0.75rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.75rem; display:block;">Transaction Receipt</label>
                    <div onclick="document.getElementById('receiptFile').click()" class="receipt-upload-box">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary); margin-bottom: 0.75rem; opacity: 0.6;"></i>
                        <p id="fileNameDisplay" style="margin:0; font-size:0.9rem; color:var(--text-secondary); font-weight:700;">Click to upload snapshot</p>
                        <input type="file" id="receiptFile" hidden accept="image/*" onchange="updateFileName(this)">
                    </div>
                </div>
                <div class="helper-note">
                    <i class="fas fa-info-circle" style="margin-top:2px;"></i>
                    <span style="line-height:1.4;">Notifications are verified within 24 hours.</span>
                </div>
            </div>

            <button id="btnConfirmPay" onclick="submitPayment()" class="btn btn-primary drawer-cta" style="display:none;">
                Confirm Payment <i class="fas fa-check-circle"></i>
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     TRANSFER MODAL (SIDE DRAWER)
     ══════════════════════════════════-->
<div id="transferModalOverlay" class="drawer-overlay" onclick="if(event.target===this) closeTransferModal()">
    <div class="drawer-content">
        <button onclick="closeTransferModal()" class="drawer-close-btn" style="position:absolute; top:2rem; left:2rem; background:rgba(15, 23, 42, 0.05); border:none; width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--text-secondary); cursor:pointer;"><i class="fas fa-chevron-right"></i></button>
        
        <div class="drawer-shell">
            <h3 class="drawer-title">Transfer Request</h3>
            <p class="drawer-subtitle">Request a bed or room change.</p>

            <form id="transferForm" class="transfer-form" onsubmit="submitTransfer(event)">
                <input type="hidden" id="tfBookingId" name="booking_id" value="">
                <div class="form-group">
                    <label>Floor Selection</label>
                    <select id="tfFloor" name="requested_floor" class="form-control" onchange="loadTransferRooms()" required>
                        <option value="">-- Choose Floor --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Room Preference</label>
                    <select id="tfRoom" name="requested_room" class="form-control" onchange="loadTransferBeds()" required disabled>
                        <option value="">-- Select Room --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bed (upper or lower)</label>
                    <select id="tfBedType" name="requested_bed" class="form-control" required disabled onfocus="loadTransferBedsRefreshIfOpen()">
                        <option value="">-- Select Room First --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <textarea id="tfReason" name="reason" rows="3" class="form-control" placeholder="Briefly explain your request..." required></textarea>
                </div>
                
                <button type="submit" id="btnConfirmTransfer" class="btn btn-primary drawer-cta">
                    Send Request <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    let _activePayBookingId = null;
    let _activePayMethod = null;

    function openPaymentModal(bookingId) {
        _activePayBookingId = bookingId;
        _activePayMethod = null;
        
        // Reset Modal
        document.querySelectorAll('.pay-opt-card').forEach(c => c.classList.remove('active'));
        const formArea = document.getElementById('paymentFormArea');
        if(formArea) formArea.classList.remove('show');
        
        const btn = document.getElementById('btnConfirmPay');
        if(btn) btn.style.display = 'none';
        
        const rFile = document.getElementById('receiptFile');
        if(rFile) rFile.value = '';
        document.getElementById('fileNameDisplay').innerText = 'Click to upload snapshot';
        
        const overlay = document.getElementById('paymentModalOverlay');
        overlay.style.display = 'flex';
        setTimeout(() => overlay.classList.add('active'), 10);
        
        document.getElementById('payBedLabel').innerText = 'Booking #' + bookingId;
    }

    function closePaymentModal() {
        const overlay = document.getElementById('paymentModalOverlay');
        overlay.classList.remove('active');
        setTimeout(() => {
            if (!overlay.classList.contains('active')) {
                overlay.style.display = 'none';
            }
        }, 300);
    }

    function updateFileName(input) {
        const display = document.getElementById('fileNameDisplay');
        if (input.files.length > 0) {
            display.innerText = 'Selected: ' + input.files[0].name;
            display.style.color = '#10b981';
        } else {
            display.innerText = 'Click to choose GCash receipt';
            display.style.color = '#64748b';
        }
    }


    function selectPayMethod(method) {
        _activePayMethod = method;
        document.querySelectorAll('.pay-opt-card').forEach(c => c.classList.remove('active'));
        
        if (method === 'GCash') {
            document.getElementById('optGcash').classList.add('active');
            document.getElementById('paymentFormArea').classList.add('show');
        } else {
            document.getElementById('optCash').classList.add('active');
            document.getElementById('paymentFormArea').classList.remove('show');
        }
        
        document.getElementById('btnConfirmPay').style.display = 'block';
    }

    async function submitPayment() {
        const btn = document.getElementById('btnConfirmPay');
        const fileInput = document.getElementById('receiptFile');
        
        if (!_activePayMethod) {
            alert('Please choose a payment method first.');
            return;
        }

        if (_activePayMethod === 'GCash' && fileInput.files.length === 0) {
            alert('Please upload your GCash receipt.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        const formData = new FormData();
        formData.append('booking_id', _activePayBookingId);
        formData.append('method', _activePayMethod);
        if (fileInput.files[0]) {
            formData.append('receipt', fileInput.files[0]);
        }

        try {
            const res = await fetch('api/submit_payment_request.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                alert('Payment notification sent to admin! Please wait for verification.');
                location.reload();
            } else {
                alert(data.message || 'Something went wrong.');
                btn.disabled = false;
                btn.innerHTML = 'Confirm Payment <i class="fas fa-check-circle"></i>';
            }
        } catch(e) {
            alert('Error connecting to server.');
            btn.disabled = false;
            btn.innerHTML = 'Confirm Payment <i class="fas fa-check-circle"></i>';
        }
    }

    // TRANSFER MODAL LOGIC — beds follow admin Room Management (api/room_api.php)
    let _transferBedPollTimer = null;

    function openTransferModal(bookingId) {
        if (!bookingId || bookingId == 0) {
            alert('You need an active booking before you can request a transfer. Please make a booking first.');
            return;
        }
        stopTransferBedPoll();
        document.getElementById('transferForm').reset();
        document.getElementById('tfBookingId').value = bookingId;
        document.getElementById('tfRoom').disabled = true;
        document.getElementById('tfRoom').innerHTML = '<option value="">-- Select Room --</option>';
        document.getElementById('tfBedType').disabled = true;
        document.getElementById('tfBedType').innerHTML = '<option value="">-- Select Room First --</option>';

        // Load floor options from Room Management data
        loadTransferFloors();
        
        const overlay = document.getElementById('transferModalOverlay');
        overlay.style.display = 'flex';
        setTimeout(() => overlay.classList.add('active'), 10);

        _transferBedPollTimer = setInterval(function () {
            var roomEl = document.getElementById('tfRoom');
            if (!roomEl || !roomEl.value) return;
            if (document.hidden) return;
            loadTransferBeds(true);
        }, 12000);
    }

    function stopTransferBedPoll() {
        if (_transferBedPollTimer) {
            clearInterval(_transferBedPollTimer);
            _transferBedPollTimer = null;
        }
    }

    function loadTransferBedsRefreshIfOpen() {
        var ov = document.getElementById('transferModalOverlay');
        if (ov && ov.classList.contains('active') && document.getElementById('tfRoom').value) {
            loadTransferBeds(true);
        }
    }
    function transferFloorLabel(n) {
        const num = parseInt(n, 10);
        if (Number.isNaN(num)) return '';
        const k = num % 100;
        const j = num % 10;
        let suf = 'th';
        if (k < 11 || k > 13) {
            if (j === 1) suf = 'st';
            else if (j === 2) suf = 'nd';
            else if (j === 3) suf = 'rd';
        }
        return num + suf + ' Floor';
    }

    async function loadTransferFloors() {
        const floorSelect = document.getElementById('tfFloor');
        if (!floorSelect) return;

        floorSelect.innerHTML = '<option value="">Loading floors...</option>';
        floorSelect.disabled = true;

        try {
            const res = await fetch('api/room_api.php?action=floors');
            const floors = await res.json();
            if (!Array.isArray(floors)) throw new Error('Invalid floors response');

            let opts = '<option value="">-- Choose Floor --</option>';
            floors.forEach(f => {
                const n = parseInt(f);
                if (!Number.isNaN(n)) {
                    opts += `<option value="${n}">${transferFloorLabel(n)}</option>`;
                }
            });

            if (opts === '<option value="">-- Choose Floor --</option>') {
                opts = '<option value="">No floors available</option>';
                floorSelect.disabled = true;
            } else {
                floorSelect.disabled = false;
            }
            floorSelect.innerHTML = opts;
        } catch (e) {
            floorSelect.innerHTML = '<option value="">Error loading floors</option>';
            floorSelect.disabled = true;
        }
    }

    function closeTransferModal() {
        stopTransferBedPoll();
        const overlay = document.getElementById('transferModalOverlay');
        overlay.classList.remove('active');
        setTimeout(() => {
            if (!overlay.classList.contains('active')) {
                overlay.style.display = 'none';
            }
        }, 300);
    }

    async function loadTransferRooms() {
        const floor = document.getElementById('tfFloor').value;
        const roomSelect = document.getElementById('tfRoom');
        const bedSelect = document.getElementById('tfBedType');
        if (!floor) {
            roomSelect.innerHTML = '<option value="">-- Select Room --</option>';
            roomSelect.disabled = true;
            bedSelect.innerHTML = '<option value="">-- Select Room First --</option>';
            bedSelect.disabled = true;
            return;
        }
        
        roomSelect.innerHTML = '<option value="">Loading rooms...</option>';
        roomSelect.disabled = true;
        bedSelect.innerHTML = '<option value="">-- Select Room First --</option>';
        bedSelect.disabled = true;
        
        try {
            const res = await fetch('api/room_api.php?action=floor_rooms&floor_no=' + floor);
            const rooms = await res.json();
            if (!Array.isArray(rooms)) {
                throw new Error('Invalid rooms response');
            }
            
            let opts = '<option value="">-- Select Room --</option>';
            rooms.forEach(r => {
                const avail = parseInt(r.total_beds, 10) - parseInt(r.occupied_count, 10) - parseInt(r.reserved_count, 10);
                if (avail > 0) {
                    opts += `<option value="${r.id}">Room ${r.room_no} (${avail} bed${avail === 1 ? '' : 's'} available)</option>`;
                }
            });
            
            if(opts === '<option value="">-- Select Room --</option>') {
                opts = '<option value="">No available rooms on this floor</option>';
            } else {
                roomSelect.disabled = false;
            }
            roomSelect.innerHTML = opts;
        } catch(e) {
            roomSelect.innerHTML = '<option value="">Error loading rooms</option>';
        }
    }

    function sortBedsNatural(beds) {
        return beds.slice().sort(function (a, b) {
            return String(a.bed_no).localeCompare(String(b.bed_no), undefined, { numeric: true, sensitivity: 'base' });
        });
    }

    function escAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    /** Maps admin bed_no to Upper/Lower labels (bunk order matches Room Management sort order). */
    function transferBedChoiceLabel(bed, sortedAllBeds) {
        var raw = String(bed.bed_no == null ? '' : bed.bed_no).trim();
        var low = raw.toLowerCase();
        if (/upper/.test(low)) return 'Upper bed';
        if (/lower/.test(low)) return 'Lower bed';
        var idx = sortedAllBeds.findIndex(function (x) { return String(x.bed_no) === String(bed.bed_no); });
        if (idx === 0) return 'Upper bed';
        if (idx === 1) return 'Lower bed';
        return 'Bed ' + raw;
    }

    async function loadTransferBeds(preserveSelection) {
        const roomId = document.getElementById('tfRoom').value;
        const bedSelect = document.getElementById('tfBedType');
        if (!roomId) {
            bedSelect.innerHTML = '<option value="">-- Select Room First --</option>';
            bedSelect.disabled = true;
            return;
        }

        var prev = preserveSelection ? String(bedSelect.value || '') : '';

        if (!preserveSelection) {
            bedSelect.innerHTML = '<option value="">Loading beds...</option>';
            bedSelect.disabled = true;
        }

        try {
            const res = await fetch('api/room_api.php?action=beds&room_id=' + encodeURIComponent(roomId) + '&_=' + Date.now());
            const beds = await res.json();
            if (!Array.isArray(beds)) {
                throw new Error('Invalid beds response');
            }

            const sortedAll = sortBedsNatural(beds);
            const availableBeds = sortedAll.filter(function (b) { return String(b.status).toLowerCase() === 'available'; });

            let opts = '<option value="">-- Choose Upper or Lower --</option>';
            availableBeds.forEach(function (b) {
                var lab = transferBedChoiceLabel(b, sortedAll);
                if (lab !== 'Upper bed' && lab !== 'Lower bed') return;
                opts += '<option value="' + escAttr(String(b.bed_no)) + '">' + escAttr(lab) + '</option>';
            });

            if (availableBeds.length === 0) {
                opts = '<option value="">No available beds in this room</option>';
                bedSelect.disabled = true;
            } else {
                bedSelect.disabled = false;
            }

            bedSelect.innerHTML = opts;

            if (prev) {
                for (var i = 0; i < bedSelect.options.length; i++) {
                    if (bedSelect.options[i].value === prev) {
                        bedSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        } catch (err) {
            if (!preserveSelection) {
                bedSelect.innerHTML = '<option value="">Error loading beds</option>';
            }
            bedSelect.disabled = true;
        }
    }

    async function submitTransfer(e) {
        e.preventDefault();
        const btn = document.getElementById('btnConfirmTransfer');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        const fd = new FormData(document.getElementById('transferForm'));
        
        try {
            const res = await fetch('api/submit_transfer.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert('Transfer request submitted successfully. Admin will review your request.');
                closeTransferModal();
            } else {
                alert(data.message || 'Error submitting request');
            }
        } catch(err) {
            alert('Network error while submitting request.');
        }
        btn.disabled = false;
        btn.innerHTML = 'Send Request <i class="fas fa-paper-plane"></i>';
    }
</script>

<?php include 'api/footer.php'; ?>
