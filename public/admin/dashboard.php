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
                                ORDER BY b.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// ── Chart 1: Floor capacity (occupied vs available per floor)
$floor_stats = $conn->query("
    SELECT r.floor_no,
           COUNT(b.id) as total_beds,
           SUM(CASE WHEN b.status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
           SUM(CASE WHEN b.status = 'Available' THEN 1 ELSE 0 END) as available
    FROM beds b
    JOIN rooms r ON b.room_id = r.id
    GROUP BY r.floor_no
    ORDER BY r.floor_no ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Chart 2: Residents by booking status
$booking_status_stats = $conn->query("
    SELECT booking_status, COUNT(*) as cnt 
    FROM bookings 
    GROUP BY booking_status
")->fetchAll(PDO::FETCH_ASSOC);

// ── Chart 3: Payment status (paid vs unpaid active residents)
$payment_stats = $conn->query("
    SELECT payment_status, COUNT(*) as cnt 
    FROM bookings 
    WHERE booking_status = 'Active'
    GROUP BY payment_status
")->fetchAll(PDO::FETCH_ASSOC);
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
            <div class="stat-card stat-card--currency">
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

        <!-- ═══════════════════════════════════════════
             STATISTICS CHARTS SECTION (MINIMALIST)
        ════════════════════════════════════════════ -->
        <div class="admin-charts-grid admin-charts-grid--balanced" role="region" aria-label="Dashboard charts">

            <!-- Chart 1: Floor Capacity -->
            <div class="admin-chart-card">
                <div class="admin-chart-card__head">
                    <div class="admin-chart-card__icon admin-chart-card__icon--emerald" aria-hidden="true">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="admin-chart-card__titles">
                        <div class="admin-chart-card__title">Floor Capacity</div>
                        <div class="admin-chart-card__subtitle">Occupied vs available beds by floor</div>
                    </div>
                </div>
                <div class="admin-chart-card__canvas">
                    <canvas id="chartFloorCapacity"></canvas>
                </div>
            </div>

            <!-- Chart 2: Resident Overview -->
            <div class="admin-chart-card">
                <div class="admin-chart-card__head">
                    <div class="admin-chart-card__icon admin-chart-card__icon--blue" aria-hidden="true">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="admin-chart-card__titles">
                        <div class="admin-chart-card__title">Residents</div>
                        <div class="admin-chart-card__subtitle">Distribution by booking status</div>
                    </div>
                </div>
                <div class="admin-chart-card__canvas">
                    <canvas id="chartResidentStatus"></canvas>
                </div>
            </div>

            <!-- Chart 3: Rent Collection -->
            <div class="admin-chart-card">
                <div class="admin-chart-card__head">
                    <div class="admin-chart-card__icon admin-chart-card__icon--amber" aria-hidden="true">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="admin-chart-card__titles">
                        <div class="admin-chart-card__title">Rent collection</div>
                        <div class="admin-chart-card__subtitle">Payment status for active bookings</div>
                    </div>
                </div>
                <div class="admin-chart-card__canvas">
                    <canvas id="chartPaymentStatus"></canvas>
                </div>
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

    <script src="../assets/js/chat.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>

<script>
(function() {
    var isDark = document.body.classList.contains('dark-theme');
    var gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(15, 23, 42, 0.07)';
    var tickColor = isDark ? '#94a3b8' : '#64748b';
    var edgeHighlight = isDark ? 'rgba(255,255,255,0.14)' : 'rgba(255,255,255,0.9)';

    function barVerticalGradient(chart, bottom, top) {
        var c = chart.ctx;
        var a = chart.chartArea;
        if (!a) return top;
        var g = c.createLinearGradient(0, a.bottom, 0, a.top);
        g.addColorStop(0, bottom);
        g.addColorStop(1, top);
        return g;
    }

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = tickColor;

    // ── Chart 1: Floor Capacity Doughnut
    const floorData = <?php
        $labels = [];
        $occupied = [];
        $available = [];
        foreach ($floor_stats as $f) {
            $labels[]   = 'Floor ' . $f['floor_no'];
            $occupied[] = (int)$f['occupied'];
            $available[]= (int)$f['available'];
        }
        echo json_encode(['labels' => $labels, 'occupied' => $occupied, 'available' => $available]);
    ?>;

    var ringColors = ['#0d9488', '#2563eb', '#d97706', '#7c3aed', '#e11d48'];
    new Chart(document.getElementById('chartFloorCapacity'), {
        type: 'doughnut',
        data: {
            labels: floorData.labels,
            datasets: [
                {
                    label: 'Occupied',
                    data: floorData.occupied,
                    backgroundColor: ringColors,
                    borderWidth: 3,
                    borderColor: edgeHighlight,
                    hoverOffset: 8,
                    offset: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '58%',
            animation: { animateRotate: true, duration: 480, easing: 'easeOutQuart' },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 11, weight: '600' },
                        padding: 14,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var i = ctx.dataIndex;
                            return ' ' + ctx.label + ': ' + floorData.occupied[i] + ' occupied / ' + floorData.available[i] + ' available';
                        }
                    }
                }
            }
        }
    });

    // ── Chart 2: Residents by Booking Status
    const statusData = <?php
        $statusMap = ['Active'=>0,'Pending'=>0,'Completed'=>0,'Cancelled'=>0];
        foreach ($booking_status_stats as $s) {
            $statusMap[$s['booking_status']] = (int)$s['cnt'];
        }
        echo json_encode($statusMap);
    ?>;

    var statusGradients = [
        ['#047857', '#34d399'],
        ['#b45309', '#fbbf24'],
        ['#1d4ed8', '#93c5fd'],
        ['#b91c1c', '#f87171']
    ];

    new Chart(document.getElementById('chartResidentStatus'), {
        type: 'bar',
        data: {
            labels: Object.keys(statusData),
            datasets: [{
                label: 'Residents',
                data: Object.values(statusData),
                backgroundColor: function(ctx) {
                    var i = ctx.dataIndex;
                    var p = statusGradients[i] || statusGradients[0];
                    return barVerticalGradient(ctx.chart, p[0], p[1]);
                },
                borderRadius: 12,
                borderSkipped: false,
                borderWidth: 2,
                borderColor: edgeHighlight
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 480, easing: 'easeOutQuart' },
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, color: tickColor, font: { size: 11, weight: '600' } },
                    grid: { color: gridColor, drawBorder: false }
                },
                x: {
                    ticks: { color: tickColor, font: { size: 11, weight: '600' } },
                    grid: { display: false, drawBorder: false }
                }
            }
        }
    });

    // ── Chart 3: Payment Status (Active residents only)
    const payData = <?php
        $payMap = ['Confirmed' => 0, 'Pending' => 0];
        foreach ($payment_stats as $p) {
            if (isset($payMap[$p['payment_status']])) {
                $payMap[$p['payment_status']] = (int)$p['cnt'];
            }
        }
        echo json_encode($payMap);
    ?>;

    var payPairs = [['#047857', '#6ee7b7'], ['#b91c1c', '#fca5a5']];

    new Chart(document.getElementById('chartPaymentStatus'), {
        type: 'bar',
        data: {
            labels: ['Paid (Confirmed)', 'Unpaid (Pending)'],
            datasets: [{
                label: 'Residents',
                data: Object.values(payData),
                backgroundColor: function(ctx) {
                    var p = payPairs[ctx.dataIndex] || payPairs[0];
                    return barVerticalGradient(ctx.chart, p[0], p[1]);
                },
                borderRadius: 14,
                borderSkipped: false,
                borderWidth: 2,
                borderColor: edgeHighlight,
                maxBarThickness: 56
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 480, easing: 'easeOutQuart' },
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, color: tickColor, font: { size: 11, weight: '600' } },
                    grid: { color: gridColor, drawBorder: false }
                },
                x: {
                    ticks: { color: tickColor, font: { size: 11, weight: '600' } },
                    grid: { display: false, drawBorder: false }
                }
            }
        }
    });
})();
</script>
</body>
</html>
