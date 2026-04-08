<?php
/**
 * Admin Bookings — complete renter management
 */
require_once '../api/core.php';
require_admin_auth();

$route = 'bookings';
include __DIR__ . '/actions.php';

$status_filter = $_GET['status'] ?? 'all';
$stats         = get_stats($conn);

$where = match($status_filter) {
    'pending'   => " WHERE b.payment_status='Pending' AND b.booking_status='Active'",
    'confirmed' => " WHERE b.payment_status='Confirmed' AND b.booking_status='Active'",
    'overdue'   => " WHERE b.due_date < NOW() AND b.booking_status='Active' AND b.payment_status='Confirmed'",
    'cancelled' => " WHERE b.booking_status='Cancelled'",
    'completed' => " WHERE b.booking_status='Completed'",
    default     => ''
};
$bookings = $conn->query("
    SELECT b.*, bd.bed_no, r.room_no, r.floor_no,
           EXTRACT(DAY FROM NOW() - b.due_date) AS days_overdue,
           (SELECT COUNT(*) FROM payments p WHERE p.booking_id = b.id) AS payment_count,
           (SELECT SUM(p.amount) FROM payments p WHERE p.booking_id = b.id) AS total_paid
    FROM bookings b
    LEFT JOIN beds bd ON b.bed_id = bd.id
    LEFT JOIN rooms r ON bd.room_id = r.id
    $where
    ORDER BY b.created_at DESC
")->fetchAll();

$pending_count   = (int)($conn->query("SELECT COUNT(*) FROM bookings WHERE payment_status='Pending' AND booking_status='Active'")->fetchColumn() ?? 0);
$active_count    = (int)($conn->query("SELECT COUNT(*) FROM bookings WHERE booking_status='Active' AND payment_status='Confirmed'")->fetchColumn() ?? 0);
$overdue_count   = (int)($stats['overdue_count'] ?? 0);
$completed_count = (int)($conn->query("SELECT COUNT(*) FROM bookings WHERE booking_status='Completed'")->fetchColumn() ?? 0);

$flash = $_GET['flash'] ?? '';
$flash_map = [
    'accept'         => ['type'=>'success','icon'=>'fa-check-circle',      'msg'=>'Booking accepted — bed marked Occupied.'],
    'decline'        => ['type'=>'danger', 'icon'=>'fa-times-circle',      'msg'=>'Booking declined — bed freed to Available.'],
    'payment_logged' => ['type'=>'success','icon'=>'fa-hand-holding-usd',  'msg'=>'Rent payment recorded & due date advanced by 1 month.'],
    'status_updated' => ['type'=>'info',   'icon'=>'fa-user-edit',         'msg'=>'Booking status updated.'],
    'checked_out'    => ['type'=>'success','icon'=>'fa-door-open',         'msg'=>'Resident checked out — bed freed.'],
    'deleted'        => ['type'=>'danger', 'icon'=>'fa-trash',             'msg'=>'Booking record permanently deleted.'],
    'error'          => ['type'=>'danger', 'icon'=>'fa-exclamation-circle','msg'=>'An error occurred. Please try again.'],
];
$flash_data = $flash_map[$flash] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations | <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* ── Flash toast ── */
        .flash-toast{position:fixed;top:1.5rem;right:1.5rem;z-index:9999;padding:.85rem 1.5rem;border-radius:1rem;font-weight:700;font-size:.88rem;display:flex;align-items:center;gap:.65rem;box-shadow:0 8px 30px rgba(0,0,0,.14);animation:toastIn .35s ease,toastOut .4s ease 4s forwards;}
        .flash-toast.success{background:#d1fae5;color:#065f46;}
        .flash-toast.danger{background:#fee2e2;color:#991b1b;}
        .flash-toast.info{background:#eff6ff;color:#1e40af;}
        @keyframes toastIn{from{opacity:0;transform:translateY(-14px)}to{opacity:1;transform:translateY(0)}}
        @keyframes toastOut{to{opacity:0;transform:translateY(-14px);pointer-events:none}}

        /* ── Summary bar ── */
        .summary-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:1.75rem;}
        .summary-card{background:#fff;border-radius:1rem;padding:1rem 1.25rem;border:1px solid #f1f5f9;box-shadow:0 2px 8px rgba(0,0,0,.04);}
        .sc-label{font-size:.63rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:.3rem;}
        .sc-value{font-family:'Outfit',sans-serif;font-weight:900;font-size:1.6rem;color:#1e293b;}
        .sc-value.green{color:#10b981;}.sc-value.yellow{color:#f59e0b;}.sc-value.red{color:#ef4444;}.sc-value.blue{color:#3b82f6;}

        /* ── Notification dot ── */
        .notif-dot{display:inline-flex;align-items:center;justify-content:center;background:#ef4444;color:#fff;font-size:.58rem;font-weight:900;min-width:17px;height:17px;border-radius:99px;padding:0 3px;margin-left:.3rem;}

        /* ── Row highlights ── */
        tr.row-pending{border-left:3px solid #f59e0b;}
        tr.row-overdue{border-left:3px solid #ef4444;}
        tr.row-pending td:first-child,tr.row-overdue td:first-child{padding-left:1rem;}

        /* ── Badges ── */
        .badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:.28rem .7rem;border-radius:99px;}
        .badge-pending{background:#fffbeb;color:#92400e;}
        .badge-confirmed{background:#d1fae5;color:#065f46;}
        .badge-cancelled{background:#fef2f2;color:#991b1b;}
        .badge-completed{background:#eff6ff;color:#1e40af;}
        .badge-muted{background:#f1f5f9;color:#94a3b8;}

        /* ── Due chips ── */
        .due-chip{display:inline-flex;align-items:center;gap:.3rem;font-size:.76rem;font-weight:700;padding:.25rem .65rem;border-radius:99px;white-space:nowrap;}
        .due-chip.ok{background:#f0fdf4;color:#065f46;}
        .due-chip.warning{background:#fffbeb;color:#92400e;}
        .due-chip.danger{background:#fef2f2;color:#991b1b;}
        .due-chip.none{background:#f8fafc;color:#94a3b8;font-weight:500;}

        /* ── Misc chips ── */
        .ref-mono{font-family:'Courier New',monospace;font-size:.7rem;font-weight:700;color:#64748b;background:#f1f5f9;padding:.14rem .48rem;border-radius:.35rem;}
        .receipt-chip{display:inline-flex;align-items:center;gap:.32rem;background:#f0fdf4;border:1px solid #bbf7d0;color:#065f46;font-size:.7rem;font-weight:700;padding:.22rem .6rem;border-radius:99px;cursor:pointer;transition:background .15s;}
        .receipt-chip:hover{background:#d1fae5;}
        .receipt-chip.cash{background:#fffbeb;border-color:#fde68a;color:#92400e;cursor:default;}
        .history-chip{display:inline-flex;align-items:center;gap:.32rem;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;font-size:.7rem;font-weight:700;padding:.22rem .6rem;border-radius:99px;cursor:pointer;transition:background .15s;margin-left:.3rem;}
        .history-chip:hover{background:#dbeafe;}

        /* ── Action buttons ── */
        .actions-flex{display:flex;gap:.4rem;justify-content:flex-end;flex-wrap:wrap;}
        .btn-accept{background:#10b981;color:#fff;border:none;padding:.44rem .85rem;border-radius:.6rem;font-weight:800;font-size:.72rem;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;transition:all .15s;}
        .btn-accept:hover{background:#059669;transform:translateY(-1px);}
        .btn-decline{background:#fef2f2;color:#b91c1c;border:1.5px solid #fecaca;padding:.44rem .85rem;border-radius:.6rem;font-weight:800;font-size:.72rem;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;transition:all .15s;}
        .btn-decline:hover{background:#ef4444;color:#fff;border-color:#ef4444;transform:translateY(-1px);}
        .btn-action{padding:.44rem .85rem;border-radius:.6rem;font-weight:700;font-size:.72rem;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;transition:all .15s;border:1.5px solid #e2e8f0;background:#f8fafc;color:#374151;}
        .btn-action:hover{border-color:#10b981;color:#10b981;}
        .btn-rent{color:#10b981;border-color:#bbf7d0;background:#f0fdf4;}
        .btn-rent:hover{background:#10b981;color:#fff;border-color:#10b981;}
        .btn-checkout{color:#3b82f6;border-color:#bfdbfe;background:#eff6ff;}
        .btn-checkout:hover{background:#3b82f6;color:#fff;border-color:#3b82f6;}
        .btn-danger{background:#fef2f2;color:#b91c1c;border:1.5px solid #fecaca;}
        .btn-danger:hover{background:#ef4444;color:#fff;border-color:#ef4444;}

        /* ── Empty state ── */
        .empty-state{text-align:center;padding:4rem 2rem;color:#94a3b8;}
        .empty-state i{font-size:2.5rem;display:block;margin-bottom:1rem;}
        .empty-state p{font-size:.95rem;font-weight:600;}

        /* ── Lightbox ── */
        .lightbox{position:fixed;inset:0;z-index:9900;background:rgba(15,23,42,.75);backdrop-filter:blur(6px);display:none;align-items:center;justify-content:center;padding:1.5rem;}
        .lightbox.open{display:flex;}
        .lightbox-inner{background:#fff;border-radius:1.25rem;padding:1.5rem;max-width:520px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,.25);position:relative;}
        .lightbox-inner img{width:100%;max-height:70vh;object-fit:contain;border-radius:.75rem;}
        .lightbox-close{position:absolute;top:.85rem;right:.85rem;width:32px;height:32px;border-radius:50%;background:#f1f5f9;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.85rem;color:#64748b;}
        .lightbox-close:hover{background:#e2e8f0;}
        .lightbox-title{font-family:'Outfit',sans-serif;font-weight:800;font-size:1rem;color:#1e293b;margin-bottom:.85rem;}

        /* ── Confirm overlay ── */
        .confirm-overlay{position:fixed;inset:0;z-index:9800;background:rgba(15,23,42,.5);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;padding:1rem;}
        .confirm-overlay.open{display:flex;}
        .confirm-box{background:#fff;border-radius:1.25rem;padding:2rem;max-width:420px;width:100%;box-shadow:0 16px 48px rgba(0,0,0,.18);text-align:center;}
        .confirm-box .confirm-icon{font-size:2.5rem;margin-bottom:.75rem;}
        .confirm-box h3{font-family:'Outfit',sans-serif;font-weight:900;font-size:1.15rem;color:#1e293b;margin:0 0 .5rem;}
        .confirm-box p{font-size:.85rem;color:#64748b;margin:0 0 1.5rem;}
        .confirm-btns{display:flex;gap:.65rem;justify-content:center;}
        .cb-cancel{background:#f1f5f9;color:#64748b;border:none;padding:.65rem 1.5rem;border-radius:.75rem;font-weight:700;cursor:pointer;font-family:inherit;font-size:.88rem;}
        .cb-accept{background:#10b981;color:#fff;border:none;padding:.65rem 1.5rem;border-radius:.75rem;font-weight:700;text-decoration:none;display:inline-block;font-family:inherit;font-size:.88rem;}
        .cb-decline{background:#ef4444;color:#fff;border:none;padding:.65rem 1.5rem;border-radius:.75rem;font-weight:700;text-decoration:none;display:inline-block;font-family:inherit;font-size:.88rem;}

        /* ── Payment history rows ── */
        .ph-row{display:flex;justify-content:space-between;align-items:center;padding:.65rem 0;border-bottom:1px solid #f1f5f9;font-size:.82rem;}
        .ph-row:last-child{border-bottom:none;}
        .ph-amount{font-weight:800;color:#10b981;font-family:'Outfit',sans-serif;}
        .ph-date{color:#94a3b8;font-size:.72rem;font-weight:600;}
        .ph-notes{font-size:.72rem;color:#64748b;margin-top:.15rem;}
        .ph-total{display:flex;justify-content:space-between;font-weight:800;font-size:.88rem;padding:.75rem 0 0;margin-top:.5rem;border-top:2px solid #f1f5f9;color:#1e293b;}

        /* ── Modal wrappers ── */
        .modal-wrapper{display:none;position:fixed;inset:0;z-index:9700;background:rgba(15,23,42,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:1rem;}
        .modal-wrapper.open{display:flex;}
        .modal-body{background:#fff;border-radius:1.25rem;padding:1.75rem;width:100%;box-shadow:0 16px 48px rgba(0,0,0,.18);}
        .max-w-500{max-width:500px;}

        /* ── Modal form elements ── */
        .modal-section{margin-bottom:1.1rem;}
        .modal-section label{display:block;font-size:.78rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem;}
        .modal-section input,.modal-section select,.modal-section textarea{width:100%;padding:.72rem 1rem;border:1.5px solid #e2e8f0;border-radius:.75rem;font-family:inherit;font-size:.88rem;outline:none;transition:border-color .15s;box-sizing:border-box;}
        .modal-section input:focus,.modal-section select:focus,.modal-section textarea:focus{border-color:#10b981;}
        .modal-actions{display:flex;gap:.75rem;margin-top:1.25rem;}
        .modal-actions .btn-cancel{flex:1;padding:.75rem;border-radius:.75rem;border:1.5px solid #e2e8f0;background:#f8fafc;font-weight:700;cursor:pointer;font-family:inherit;}
        .modal-actions .btn-submit{flex:2;padding:.75rem;border-radius:.75rem;border:none;background:#10b981;color:#fff;font-weight:800;cursor:pointer;font-family:inherit;}
        .modal-actions .btn-submit.blue{background:#3b82f6;}
        .modal-row{display:grid;grid-template-columns:1fr 1fr;gap:.85rem;}
        @media(max-width:480px){.modal-row{grid-template-columns:1fr;}}

        /* ── Search Bar ── */
        .booking-search-input {
            width:100%; padding:.85rem 1rem .85rem 2.8rem; border-radius:1rem; 
            border:1.5px solid #e2e8f0; font-family:inherit; font-size:.88rem; outline:none; 
            background:#fff; box-sizing:border-box; transition:all .15s;
        }
        .booking-search-input:focus { border-color:#10b981; box-shadow:0 0 0 4px rgba(16,185,129,0.1); }

        /* ── Overdue warning bar ── */
        .due-info-bar{background:#fffbeb;border:1px solid #fde68a;border-radius:.6rem;padding:.6rem .9rem;font-size:.78rem;color:#92400e;font-weight:700;margin-bottom:1rem;display:none;}
        .due-info-bar.visible{display:flex;align-items:center;gap:.5rem;}

        /* ── Heading utility ── */
        .font-bold{font-family:'Outfit',sans-serif;font-weight:800;font-size:1.1rem;color:#1e293b;}
        .mb-4{margin-bottom:1rem;}
        .color-primary{color:#10b981;}

        /* ── DARK THEME OVERRIDES ── */
        .dark-theme .summary-card { background: var(--white); border-color: rgba(255,255,255,0.05); }
        .dark-theme .sc-value { color: var(--text-primary); }
        .dark-theme .sc-label { color: var(--text-muted); }
        .dark-theme .btn-action { background: var(--off-white); border-color: rgba(255,255,255,0.05); color: var(--text-muted); }
        .dark-theme .btn-action:hover { border-color: var(--primary); color: var(--primary); }
        .dark-theme .btn-rent { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.2); }
        .dark-theme .btn-rent:hover { background: var(--primary); color: #fff; }
        .dark-theme .btn-checkout { background: rgba(59,130,246,0.1); border-color: rgba(59,130,246,0.2); }
        .dark-theme .btn-checkout:hover { background: #3b82f6; color: #fff; }
        .dark-theme .btn-accept { background: rgba(16,185,129,0.15); color: #34d399; }
        .dark-theme .btn-accept:hover { background: var(--primary); color: #fff; }
        .dark-theme .btn-decline { background: rgba(239,68,68,0.15); color: #f87171; border-color: transparent; }
        .dark-theme .btn-decline:hover { background: #ef4444; color: #fff; }
        .dark-theme #residentSearch { background: var(--off-white); border-color: rgba(255,255,255,0.05); color: var(--text-primary); }
        .dark-theme #residentSearch:focus { background: var(--white); border-color: var(--primary); }
        .dark-theme .lightbox-inner, .dark-theme .modal-body, .dark-theme .confirm-box { background: var(--white); box-shadow: 0 16px 48px rgba(0,0,0,.5); }
        .dark-theme .lightbox-close, .dark-theme .cb-cancel, .dark-theme .modal-actions .btn-cancel { background: rgba(255,255,255,0.05); color: var(--text-muted); border-color: rgba(255,255,255,0.05); }
        .dark-theme .lightbox-title, .dark-theme .font-bold, .dark-theme .ph-total { color: var(--text-primary); }
        .dark-theme .ph-row { border-color: rgba(255,255,255,0.05); }
        .dark-theme .modal-section input, .dark-theme .modal-section select, .dark-theme .modal-section textarea { background: var(--off-white); border-color: rgba(255,255,255,0.05); color: var(--text-primary); }
        .dark-theme .modal-section input:focus, .dark-theme .modal-section select:focus, .dark-theme .modal-section textarea:focus { background: var(--white); border-color: var(--primary); }
        .dark-theme .due-info-bar { background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.2); color: #fbbf24; }
        .dark-theme .ph-total { border-color: rgba(255,255,255,0.05); }
        .dark-theme .badge-pending { background: rgba(245,158,11,0.15); color: #fbbf24; }
        .dark-theme .badge-confirmed { background: rgba(16,185,129,0.15); color: #34d399; }
        .dark-theme .badge-cancelled { background: rgba(239,68,68,0.15); color: #f87171; }
        .dark-theme .badge-completed { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .dark-theme .badge-muted, .dark-theme .ref-mono { background: rgba(255,255,255,0.05); color: var(--text-muted); }
        .dark-theme .receipt-chip { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.2); color: #34d399; }
        .dark-theme .receipt-chip.cash { background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.2); color: #fbbf24; }
        .dark-theme .history-chip { background: rgba(59,130,246,0.1); border-color: rgba(59,130,246,0.2); color: #60a5fa; }
        .dark-theme .due-chip.none { background: rgba(255,255,255,0.05); color: var(--text-muted); }
        .dark-theme .due-chip.ok { background: rgba(16,185,129,0.15); color: #34d399; }
        .dark-theme .due-chip.warning { background: rgba(245,158,11,0.15); color: #fbbf24; }
        .dark-theme .due-chip.danger { background: rgba(239,68,68,0.15); color: #f87171; }
        .dark-theme tr.row-pending { border-left-color: #fbbf24; }
        .dark-theme tr.row-overdue { border-left-color: #f87171; }
        .dark-theme .revenue-badge { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.2); }
        .dark-theme .badge-label { color: #34d399; }
        .dark-theme .badge-value { color: #f8fafc; }
        .dark-theme .filter-nav-btn { color: var(--text-muted); background: rgba(255,255,255,0.02); }
        .dark-theme .filter-nav-btn:hover { background: rgba(255,255,255,0.05); color: var(--text-primary); }
        .dark-theme .filter-nav-btn.nav-active { background: var(--primary) !important; color: #fff !important; }
    </style>
</head>
<body class="admin-page">

<?php if ($flash_data): ?>
<div class="flash-toast <?php echo $flash_data['type']; ?>">
    <i class="fas <?php echo $flash_data['icon']; ?>"></i>
    <?php echo $flash_data['msg']; ?>
</div>
<?php endif; ?>

<header class="mobile-admin-header">
    <div class="logo-stack-mini" style="background:rgba(255,255,255,.2);width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-hotel" style="font-size:.85rem;color:#fff;"></i>
    </div>
    <span class="font-bold text-sm tracking-wide">ADMIN PORTAL</span>
    <button id="sidebarToggleBtn" class="sidebar-toggle" onclick="toggleSidebar(event)"><i class="fas fa-bars"></i></button>
</header>

<?php include 'sidebar.php'; ?>

<main class="main-content">

    <header class="view-header">
        <div>
            <h1>Reservation Control <i class="fas fa-calendar-check color-primary"></i></h1>
            <p>Accept bookings, log rent payments, manage resident status &amp; checkout.</p>
        </div>
        <div class="revenue-badge">
            <div class="badge-label">Monthly Billing</div>
            <div class="badge-value"><?php echo format_price($stats['potential_revenue']); ?></div>
        </div>
    </header>

    <div class="summary-bar">
        <div class="summary-card"><div class="sc-label">Pending Review</div><div class="sc-value yellow"><?php echo $pending_count; ?></div></div>
        <div class="summary-card"><div class="sc-label">Active Residents</div><div class="sc-value green"><?php echo $active_count; ?></div></div>
        <div class="summary-card"><div class="sc-label">Overdue</div><div class="sc-value red"><?php echo $overdue_count; ?></div></div>
        <div class="summary-card"><div class="sc-label">Due This Week</div><div class="sc-value"><?php echo $stats['due_this_week']; ?></div></div>
        <div class="summary-card"><div class="sc-label">Completed</div><div class="sc-value blue"><?php echo $completed_count; ?></div></div>
    </div>

    <div class="filter-tabs" style="margin-bottom:1.5rem;">
        <?php
        $tabs = [
            'all'       => ['icon'=>'fa-layer-group',    'label'=>'All',       'count'=>0],
            'pending'   => ['icon'=>'fa-clock',          'label'=>'Pending',   'count'=>$pending_count],
            'confirmed' => ['icon'=>'fa-check-double',   'label'=>'Active',    'count'=>0],
            'overdue'   => ['icon'=>'fa-calendar-times', 'label'=>'Overdue',   'count'=>$overdue_count],
            'cancelled' => ['icon'=>'fa-times-circle',   'label'=>'Cancelled', 'count'=>0],
            'completed' => ['icon'=>'fa-door-open',      'label'=>'Completed', 'count'=>0],
        ];
        foreach ($tabs as $key => $t):
        ?>
        <a href="bookings.php?status=<?php echo $key; ?>" class="filter-nav-btn <?php echo $status_filter===$key?'nav-active':''; ?>">
            <i class="fas <?php echo $t['icon']; ?>"></i> <?php echo $t['label']; ?>
            <?php if ($t['count'] > 0): ?><span class="notif-dot"><?php echo $t['count']; ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div style="margin-bottom:1.5rem;max-width:400px;position:relative;">
        <i class="fas fa-search" style="position:absolute;left:1.1rem;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"></i>
        <input type="text" id="residentSearch" placeholder="Search name, ref, room…" class="booking-search-input">
    </div>

    <div class="table-container main-card">
        <table class="data-table" id="bookingsTable">
            <thead>
                <tr>
                    <th>Resident</th>
                    <th>Placement</th>
                    <th>Receipt / Payments</th>
                    <th>Status</th>
                    <th>Next Due</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($bookings)): ?>
                <tr><td colspan="6">
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No bookings found<?php echo $status_filter !== 'all' ? ' in this category' : ''; ?>.</p>
                    </div>
                </td></tr>
            <?php else: foreach ($bookings as $b):
                $isPending   = $b['payment_status'] === 'Pending'  && $b['booking_status'] === 'Active';
                $isActive    = $b['booking_status'] === 'Active'   && $b['payment_status'] === 'Confirmed';
                $isCancelled = $b['booking_status'] === 'Cancelled';
                $isCompleted = $b['booking_status'] === 'Completed';
                $isDanger    = $b['due_date'] && $b['days_overdue'] > 0 && $b['booking_status'] === 'Active';
                $isNearDue   = $b['due_date'] && $b['days_overdue'] <= 0 && strtotime($b['due_date']) < strtotime('+4 days') && $b['booking_status'] === 'Active';
                $hasReceipt  = !empty($b['receipt_path']);
                $rowClass    = $isPending ? 'row-pending' : ($isDanger ? 'row-overdue' : '');

                $id        = (int)$b['id'];
                $fnAttr    = htmlspecialchars($b['full_name'],      ENT_QUOTES);
                $duAttr    = htmlspecialchars($b['due_date'] ?? '', ENT_QUOTES);
                $rate      = (int)($b['monthly_rate'] ?? 1500);
                $bStatAttr = htmlspecialchars($b['booking_status'], ENT_QUOTES);
                $pStatAttr = htmlspecialchars($b['payment_status'], ENT_QUOTES);
                $rcptAttr  = $hasReceipt ? htmlspecialchars('../'.$b['receipt_path'], ENT_QUOTES) : '';
            ?>
            <tr class="<?php echo $rowClass; ?>">
                <td>
                    <div style="font-weight:800;font-size:.92rem;color:#1e293b;"><?php echo htmlspecialchars($b['full_name']); ?></div>
                    <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-top:.15rem;">
                        <?php echo htmlspecialchars($b['category']); ?>
                        <?php if (!empty($b['school_name'])): ?>&middot; <span style="color:#64748b;"><?php echo htmlspecialchars($b['school_name']); ?></span><?php endif; ?>
                    </div>
                    <div style="font-size:.7rem;color:#94a3b8;margin-top:.1rem;">
                        <i class="fas fa-phone" style="font-size:.6rem;"></i> <?php echo htmlspecialchars($b['contact_number']); ?>
                    </div>
                </td>

                <td>
                    <span class="ref-mono"><?php echo htmlspecialchars($b['booking_ref']); ?></span>
                    <div style="margin-top:.3rem;font-size:.76rem;font-weight:700;color:#374151;">
                        <?php if ($b['bed_no']): ?>
                            <i class="fas fa-layer-group" style="color:#cbd5e1;font-size:.62rem;"></i>
                            Floor <?php echo $b['floor_no']; ?> &middot; Rm <?php echo $b['room_no']; ?> &middot; Bed <?php echo $b['bed_no']; ?>
                        <?php else: ?>
                            <span style="color:#f59e0b;font-style:italic;">Pending Assignment</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:.68rem;color:#94a3b8;margin-top:.1rem;">
                        <i class="fas fa-calendar-plus" style="font-size:.58rem;"></i>
                        <?php echo date('M d, Y', strtotime($b['created_at'])); ?>
                    </div>
                </td>

                <td>
                    <?php if ($hasReceipt): ?>
                        <span class="receipt-chip"
                              data-action="receipt"
                              data-path="<?php echo $rcptAttr; ?>"
                              data-name="<?php echo $fnAttr; ?>">
                            <i class="fas fa-image"></i> Receipt
                        </span>
                    <?php elseif ($b['payment_method'] === 'Cash In'): ?>
                        <span class="receipt-chip cash"><i class="fas fa-money-bill-wave"></i> Cash</span>
                    <?php else: ?>
                        <span style="font-size:.72rem;color:#94a3b8;">—</span>
                    <?php endif; ?>

                    <span class="history-chip"
                          data-action="history"
                          data-id="<?php echo $id; ?>"
                          data-name="<?php echo $fnAttr; ?>">
                        <i class="fas fa-id-card"></i> Info
                        <?php if ((int)$b['payment_count'] > 0): ?> &middot; <?php echo $b['payment_count']; ?><?php endif; ?>
                    </span>
                </td>

                <td>
                    <span class="badge <?php echo get_badge_class($b['payment_status']); ?>">
                        <?php echo htmlspecialchars($b['payment_status']); ?>
                    </span>
                    <div style="font-size:.68rem;color:#94a3b8;margin-top:.25rem;font-weight:600;">
                        <?php echo htmlspecialchars($b['booking_status']); ?>
                    </div>
                    <?php if ($isDanger): ?>
                    <div style="font-size:.66rem;color:#ef4444;font-weight:800;margin-top:.15rem;">
                        <?php echo $b['days_overdue']; ?> day<?php echo $b['days_overdue']>1?'s':''; ?> overdue
                    </div>
                    <?php endif; ?>
                </td>

                <td>
                    <?php if ($b['due_date'] && $b['due_date'] !== '0000-00-00'): ?>
                        <span class="due-chip <?php echo $isDanger ? 'danger' : ($isNearDue ? 'warning' : 'ok'); ?>">
                            <i class="fas <?php echo $isDanger ? 'fa-exclamation-circle' : ($isNearDue ? 'fa-clock' : 'fa-calendar'); ?>"></i>
                            <?php echo date('M d, Y', strtotime($b['due_date'])); ?>
                        </span>
                    <?php else: ?>
                        <span class="due-chip none">Not set</span>
                    <?php endif; ?>
                    <?php if (!empty($b['total_paid'])): ?>
                    <div style="font-size:.66rem;color:#10b981;font-weight:700;margin-top:.2rem;">
                        Total paid: ₱<?php echo number_format($b['total_paid'],2); ?>
                    </div>
                    <?php endif; ?>
                </td>

                <td class="text-right">
                    <div class="actions-flex">
                        <?php if ($isPending): ?>
                            <button class="btn-accept"
                                    data-action="accept"
                                    data-id="<?php echo $id; ?>"
                                    data-name="<?php echo $fnAttr; ?>">
                                <i class="fas fa-check"></i> Accept
                            </button>
                            <button class="btn-decline"
                                    data-action="decline"
                                    data-id="<?php echo $id; ?>"
                                    data-name="<?php echo $fnAttr; ?>">
                                <i class="fas fa-times"></i> Decline
                            </button>
                            <button class="btn-action btn-rent"
                                    data-action="payment"
                                    data-id="<?php echo $id; ?>"
                                    data-name="<?php echo $fnAttr; ?>"
                                    data-due="<?php echo $duAttr; ?>"
                                    data-rate="<?php echo $rate; ?>">
                                <i class="fas fa-hand-holding-usd"></i> Rent
                            </button>
                            <button class="btn-action"
                                    data-action="status"
                                    data-id="<?php echo $id; ?>"
                                    data-bstatus="<?php echo $bStatAttr; ?>"
                                    data-pstatus="<?php echo $pStatAttr; ?>">
                                <i class="fas fa-user-edit"></i> Status
                            </button>

                        <?php elseif ($isActive): ?>
                            <button class="btn-action btn-rent"
                                    data-action="payment"
                                    data-id="<?php echo $id; ?>"
                                    data-name="<?php echo $fnAttr; ?>"
                                    data-due="<?php echo $duAttr; ?>"
                                    data-rate="<?php echo $rate; ?>">
                                <i class="fas fa-hand-holding-usd"></i> Rent
                            </button>
                            <button class="btn-action"
                                    data-action="status"
                                    data-id="<?php echo $id; ?>"
                                    data-bstatus="<?php echo $bStatAttr; ?>"
                                    data-pstatus="<?php echo $pStatAttr; ?>">
                                <i class="fas fa-user-edit"></i> Status
                            </button>
                            <button class="btn-action btn-checkout"
                                    data-action="checkout"
                                    data-id="<?php echo $id; ?>"
                                    data-name="<?php echo $fnAttr; ?>">
                                <i class="fas fa-door-open"></i> Out
                            </button>

                        <?php elseif ($isCompleted || $isCancelled): ?>
                            <button class="btn-action"
                                    data-action="status"
                                    data-id="<?php echo $id; ?>"
                                    data-bstatus="<?php echo $bStatAttr; ?>"
                                    data-pstatus="<?php echo $pStatAttr; ?>"
                                    style="font-size:.68rem; color:#10b981; border-color:#10b981;">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                        <?php endif; ?>

                        <button class="btn-action btn-danger"
                                data-action="delete"
                                data-id="<?php echo $id; ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- ══════════════════════════════════════
     RECEIPT LIGHTBOX
══════════════════════════════════════ -->
<div id="receiptLightbox" class="lightbox">
    <div class="lightbox-inner">
        <button class="lightbox-close" id="lightboxCloseBtn"><i class="fas fa-times"></i></button>
        <div class="lightbox-title" id="lightboxTitle">Receipt</div>
        <img id="lightboxImg" src="" alt="Receipt">
    </div>
</div>

<!-- ══════════════════════════════════════
     CONFIRM DIALOG
══════════════════════════════════════ -->
<div id="confirmOverlay" class="confirm-overlay">
    <div class="confirm-box">
        <div class="confirm-icon" id="confirmIcon">✅</div>
        <h3 id="confirmTitle">Confirm Action</h3>
        <p id="confirmMsg">Are you sure?</p>
        <div class="confirm-btns">
            <button class="cb-cancel" id="confirmCancelBtn">Cancel</button>
            <a id="confirmActionBtn" href="#" class="cb-accept">Confirm</a>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     LOG RENT PAYMENT MODAL
══════════════════════════════════════ -->
<div id="paymentModal" class="modal-wrapper">
    <div class="modal-body max-w-500">
        <h2 class="font-bold mb-4"><i class="fas fa-hand-holding-usd color-primary"></i> Log Rent Payment</h2>
        <form method="POST" enctype="multipart/form-data" action="bookings.php">
            <input type="hidden" name="log_payment" value="1">
            <input type="hidden" name="booking_id" id="pay_booking_id">
            <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
            <p id="pay_resident_label" style="font-weight:800;color:#10b981;font-size:.95rem;margin-bottom:.75rem;"></p>
            <div id="overdueWarning" class="due-info-bar">
                <i class="fas fa-exclamation-triangle"></i>
                <span id="overdueWarningText"></span>
            </div>
            <div class="modal-row">
                <div class="modal-section">
                    <label>Amount Received (₱)</label>
                    <input type="number" name="amount" id="pay_amount" value="1500" required min="1" step="0.01">
                </div>
                <div class="modal-section">
                    <label>Next Due Date <small style="color:#94a3b8;text-transform:none;">(auto: +1 month)</small></label>
                    <input type="date" name="next_due_date" id="pay_next_due" required>
                </div>
            </div>
            <div class="modal-section">
                <label>Payment Notes (optional)</label>
                <input type="text" name="notes" placeholder="e.g. March rent, partial payment…">
            </div>
            <div class="modal-section">
                <label>Upload Receipt (optional)</label>
                <input type="file" name="receipt" accept="image/*,.pdf" style="padding:.5rem;">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="paymentModalClose">Cancel</button>
                <button type="submit" class="btn-submit"><i class="fas fa-check"></i> Confirm Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════
     UPDATE STATUS MODAL
══════════════════════════════════════ -->
<div id="statusModal" class="modal-wrapper">
    <div class="modal-body max-w-500">
        <h2 class="font-bold mb-4"><i class="fas fa-user-edit color-primary"></i> Update Booking Status</h2>
        <form method="POST" action="bookings.php">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="id" id="status_booking_id">
            <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
            <div class="modal-row">
                <div class="modal-section">
                    <label>Registration Status</label>
                    <select name="booking_status" id="status_booking_val">
                        <option value="Active">Active Resident</option>
                        <option value="Completed">Completed / Moved Out</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="modal-section">
                    <label>Financial Status</label>
                    <select name="payment_status" id="status_payment_val">
                        <option value="Pending">Pending</option>
                        <option value="Confirmed">Confirmed / Paid</option>
                    </select>
                </div>
            </div>
            <p style="font-size:.76rem;color:#94a3b8;margin:.25rem 0 1rem;">
                <i class="fas fa-info-circle"></i>
                Setting to <strong>Completed</strong> or <strong>Cancelled</strong> will free the assigned bed.
                Setting to <strong>Confirmed</strong> will auto-set the due date if missing.
            </p>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="statusModalClose">Cancel</button>
                <button type="submit" class="btn-submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════
     CHECKOUT MODAL
══════════════════════════════════════ -->
<div id="checkoutModal" class="modal-wrapper">
    <div class="modal-body max-w-500">
        <h2 class="font-bold mb-4"><i class="fas fa-door-open" style="color:#3b82f6;"></i> Checkout Resident</h2>
        <form method="POST" action="bookings.php">
            <input type="hidden" name="checkout" value="1">
            <input type="hidden" name="id" id="checkout_booking_id">
            <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
            <p id="checkout_resident_name" style="font-weight:800;color:#3b82f6;font-size:.95rem;margin-bottom:1rem;"></p>
            <div class="modal-section">
                <label>Move-Out Date</label>
                <input type="date" name="moveout_date" id="checkout_date" required>
            </div>
            <div class="modal-section">
                <label>Remarks (optional)</label>
                <textarea name="remarks" rows="3" placeholder="e.g. Left voluntarily, end of contract…"></textarea>
            </div>
            <p style="font-size:.76rem;color:#94a3b8;margin:.25rem 0 1rem;">
                <i class="fas fa-info-circle"></i>
                Booking will be marked <strong>Completed</strong> and the bed freed to <strong>Available</strong>.
            </p>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="checkoutModalClose">Cancel</button>
                <button type="submit" class="btn-submit blue"><i class="fas fa-door-open"></i> Confirm Checkout</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════
     PAYMENT HISTORY MODAL
══════════════════════════════════════ -->
<div id="historyModal" class="modal-wrapper">
    <div class="modal-body max-w-500">
        <h2 class="font-bold mb-4"><i class="fas fa-history color-primary"></i> Payment History</h2>
        <p id="history_resident_name" style="font-weight:800;color:#10b981;font-size:.95rem;margin-bottom:1rem;"></p>
        <div id="historyContent"></div>
        <div style="margin-top:1rem;">
            <button id="historyModalClose" style="width:100%;padding:.75rem;border-radius:.75rem;border:1.5px solid #e2e8f0;background:#f8fafc;font-weight:700;cursor:pointer;font-family:inherit;">Close</button>
        </div>
    </div>
</div>

<!-- PHP payment data injected as a JS constant -->
<script>
var BOOKING_FILTER = <?php echo json_encode($status_filter); ?>;
var BOOKINGS_DATA  = <?php
    $bData = new stdClass();
    if (!empty($bookings)) {
        foreach ($bookings as $bk) {
            $bData->{$bk['id']} = $bk;
        }
    }
    echo json_encode($bData);
?>;
var PAYMENT_DATA   = <?php
    $all_payments = new stdClass();
    if (!empty($bookings)) {
        $id_list = implode(',', array_map('intval', array_column($bookings, 'id')));
        $rows = $conn->query("SELECT booking_id, amount, paid_at, notes, receipt_path FROM payments WHERE booking_id IN ($id_list) ORDER BY paid_at DESC");
        if ($rows) {
            foreach ($rows as $r) {
                $bid = $r['booking_id'];
                if (!isset($all_payments->$bid)) {
                    $all_payments->$bid = [];
                }
                $all_payments->$bid[] = $r;
            }
        }
    }
    echo json_encode($all_payments);
?>;
</script>


<script src="../assets/js/admin.js"></script>
<script src="../assets/js/bookings.js?v=<?= filemtime('../assets/js/bookings.js') ?>"></script>
</body>
</html>
