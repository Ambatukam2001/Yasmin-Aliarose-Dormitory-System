<?php
/**
 * Admin Dashboard - Overview Standalone
 */
require_once '../api/core.php';
require_admin_auth();
require_once 'actions.php';

$route = 'overview';
$stats = get_stats($conn);

// Flash messaging
$flash = $_GET['flash'] ?? '';
$flash_map = [
    'payment_logged' => ['type'=>'success','icon'=>'fa-hand-holding-usd', 'msg'=>'Rent payment recorded successfully.'],
    'error'          => ['type'=>'danger', 'icon'=>'fa-exclamation-circle','msg'=>'An error occurred processing the payment.'],
];
$flash_data = $flash_map[$flash] ?? null;

// Fetch recent bookings
$recent_bookings = $conn->query("SELECT b.*, bd.bed_no, r.room_no, r.floor_no 
                                FROM bookings b 
                                LEFT JOIN beds bd ON b.bed_id = bd.id 
                                LEFT JOIN rooms r ON bd.room_id = r.id 
                                ORDER BY b.created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | <?php echo $site_name; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css"> 
    <style>
        .flash-toast { position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999; padding: 1rem 1.75rem; border-radius: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 10px 30px rgba(0,0,0,0.15); animation: toastIn 0.4s ease, toastOut 0.5s ease 4s forwards; }
        .flash-toast.success { background: #d1fae5; color: #065f46; border: 1px solid #34d399; }
        .flash-toast.danger { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        @keyframes toastIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes toastOut { to { opacity: 0; transform: translateY(-20px); visibility: hidden; } }
    </style>
</head>
<body class="admin-page">
    <?php if ($flash_data): ?>
        <div class="flash-toast <?php echo $flash_data['type']; ?>">
            <i class="fas <?php echo $flash_data['icon']; ?>"></i> <?php echo $flash_data['msg']; ?>
        </div>
    <?php endif; ?>
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
                <h1>Command Center <i class="fas fa-microchip color-primary"></i></h1>
                <p>Live monitoring of dormitory occupancy and reservations.</p>
            </div>
        </header>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon bg-info"><i class="fas fa-door-open"></i></div>
                <div class="stat-value"><?php echo $stats['rooms']; ?></div>
                <div class="stat-label">Total Rooms</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-success"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo $stats['beds'] - $stats['occupied']; ?></div>
                <div class="stat-label">Available Beds</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-warning"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="stat-value"><?php echo format_price($stats['potential_revenue']); ?></div>
                <div class="stat-label">Monthly Potential Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-danger"><i class="fas fa-bell"></i></div>
                <div class="stat-value"><?php echo $stats['due_this_week']; ?></div>
                <div class="stat-label">Due this Week</div>
            </div>
        </div>

        <div class="recent-section main-card">
            <div class="section-top">
                <h3>Newest Reservations</h3>
                <a href="bookings.php" class="btn-outline btn-sm">View Full List</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Resident</th>
                        <th>Placement</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_bookings as $booking): ?>
                    <tr>
                        <td class="font-mono font-bold color-primary"><?php echo $booking['booking_ref']; ?></td>
                        <td>
                            <strong><?php echo $booking['full_name']; ?></strong><br>
                            <small class="text-muted"><?php echo $booking['contact_number']; ?></small>
                        </td>
                        <td><strong class="text-slate-700">Floor <?php echo $booking['floor_no']; ?></strong><br><small class="text-muted">Room <?php echo $booking['room_no']; ?> | Bed <?php echo $booking['bed_no']; ?></small></td>
                        <td><span class="badge <?php echo get_badge_class($booking['payment_status']); ?>"><?php echo $booking['payment_status']; ?></span></td>
                        <td class="text-right">
                            <div class="actions-flex">
                                <?php if ($booking['payment_status'] === 'Pending'): ?>
                                    <button onclick="openAddPayment(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['full_name'], ENT_QUOTES); ?>', '<?php echo $booking['due_date']; ?>')" 
                                            class="btn-action btn-confirm">
                                        <i class="fas fa-hand-holding-usd"></i> Rent
                                    </button>
                                <?php else: ?>
                                    <a href="bookings.php" class="text-muted" style="font-size: 0.72rem; text-decoration: none; font-weight: 600;">Details <i class="fas fa-chevron-right" style="font-size: 0.6rem;"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modals -->
    <div id="paymentModal" class="modal-wrapper">
        <div class="modal-body max-w-500">
            <h2 class="font-bold mb-4">Log Rent Payment</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="log_payment" value="1">
                <input type="hidden" name="booking_id" id="pay_booking_id">
                <p id="pay_resident_name" class="font-bold color-primary mb-2"></p>
                <div class="form-group">
                    <label>Amount Recieved (₱)</label>
                    <input type="number" name="amount" value="1500" required step="0.01">
                </div>
                <div class="form-group">
                    <label>Payment Date</label>
                    <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Next Due Date</label>
                    <input type="date" name="next_due_date" id="pay_next_due" required>
                </div>
                <div class="form-group">
                    <label>Upload Receipt (Image)</label>
                    <input type="file" name="receipt" accept="image/*" class="input-file">
                </div>
                <div class="actions-flex gap-4 mt-2">
                    <button type="button" onclick="closeModal('paymentModal')" class="btn btn-outline" style="flex:1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex:2;">Confirm Payment <i class="fas fa-save" style="margin-left: 0.5rem;"></i></button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
</body>
</html>
