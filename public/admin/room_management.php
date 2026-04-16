<?php
/**
 * Admin Room Management
 * Schema-aligned: beds.status = 'Available'|'Reserved'|'Occupied'
 *                 rooms.status = 'Available'|'Full'
 */
require_once '../api/core.php';
require_admin_auth();
require_once 'actions.php';

$route = 'rooms';

/* ── POST: Add Room ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    $r_no   = trim($_POST['room_no'] ?? '');
    $f_no   = (int)($_POST['floor_no'] ?? 2);
    $bcount = (int)($_POST['beds_count'] ?? 4);

    if ($r_no && $bcount >= 0) {
        $check = $conn->prepare("SELECT id FROM rooms WHERE room_no = ? AND floor_no = ?");
        $check->execute([$r_no, $f_no]);
        if ($check->fetch()) {
            $_SESSION['flash_msg'] = "Error: Room $r_no already exists on Floor $f_no.";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO rooms (room_no, floor_no, capacity) VALUES (?, ?, ?)");
                if ($stmt->execute([$r_no, $f_no, $bcount])) {
                    $new_room_id = $conn->lastInsertId();
                    for ($i = 1; $i <= $bcount; $i++) {
                        $bno = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $stmt_bed = $conn->prepare("INSERT INTO beds (room_id, floor_id, bed_no, status) VALUES (?, ?, ?, 'Available')");
                        $stmt_bed->execute([$new_room_id, $f_no, $bno]);
                    }
                    $_SESSION['flash_msg'] = "Room $r_no added successfully.";
                }
            } catch (Exception $e) {
                $_SESSION['flash_msg'] = "Error adding room: " . $e->getMessage();
            }
        }
    }
    header('Location: room_management.php');
    exit;
}


/* ── POST: Edit Room ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_room'])) {
    $r_id   = (int)($_POST['room_id'] ?? 0);
    $r_no   = trim($_POST['room_no'] ?? '');
    $f_no   = (int)($_POST['floor_no'] ?? 2);
    $status = $_POST['room_status'] ?? 'Available';
    if ($r_id && $r_no) {
        $stmt = $conn->prepare("UPDATE rooms SET room_no = ?, floor_no = ?, status = ? WHERE id = ?");
        $stmt->execute([$r_no, $f_no, $status, $r_id]);
    }
    header('Location: room_management.php');
    exit;
}

/* ── GET: Delete Room ── */
if (isset($_GET['action']) && $_GET['action'] === 'delete_room') {
    $r_id = (int)($_GET['id'] ?? 0);
    if ($r_id) {
        $stmt1 = $conn->prepare("DELETE FROM beds WHERE room_id = ?");
        $stmt1->execute([$r_id]);
        $stmt2 = $conn->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt2->execute([$r_id]);
    }
    header('Location: room_management.php');
    exit;
}

// All rooms with live occupancy counts
$rooms = $conn->query("
    SELECT r.*,
        (SELECT COUNT(*) FROM beds b WHERE b.room_id = r.id) AS total_beds,
        (SELECT COUNT(*) FROM beds b WHERE b.room_id = r.id AND b.status = 'Occupied') AS occupied_count
    FROM rooms r
    ORDER BY r.floor_no ASC, r.room_no ASC
")->fetchAll(PDO::FETCH_ASSOC);

// All beds indexed by room_id
$all_beds = $conn->query("SELECT * FROM beds ORDER BY bed_no ASC")->fetchAll(PDO::FETCH_ASSOC);
$beds_by_room = [];
foreach ($all_beds as $b) {
    $beds_by_room[$b['room_id']][] = $b;
}

// Group by floor
$floors = [];
foreach ($rooms as $r) {
    $floors[$r['floor_no']][] = $r;
}
ksort($floors);

function floor_label($n) {
    $suffixes = ['th','st','nd','rd'];
    $v = $n % 100;
    $suffix = ($v >= 11 && $v <= 13) ? 'th' : ($suffixes[min($v % 10, 3)] ?? 'th');
    return $n . $suffix . ' Floor';
}

/**
 * Build a single bed chip
 */
function buildBedChipHtml($bedId, $bedNo, $status, $reservedAt, $roomId) {
    $isOccupied = ($status === 'Occupied');
    $isReserved = ($status === 'Reserved');

    if ($isOccupied) {
        $chipClass   = 'rm-bed-chip--occupied';
        $dotClass    = 'dot--occupied';
        $nextStatus  = 'Available';
        $toggleClass = 'rm-bed-toggle--release';
        $toggleLabel = '<i class="fas fa-lock-open"></i> Set Available';
        $statusText  = 'Occupied' . ($reservedAt ? "<small>since $reservedAt</small>" : '');
        $showDelete  = false;
    } elseif ($isReserved) {
        $chipClass   = 'rm-bed-chip--reserved';
        $dotClass    = 'dot--reserved';
        $nextStatus  = 'Occupied';
        $toggleClass = 'rm-bed-toggle--occupy';
        $toggleLabel = '<i class="fas fa-lock"></i> Set Occupied';
        $statusText  = 'Reserved' . ($reservedAt ? "<small>since $reservedAt</small>" : '');
        $showDelete  = true;
    } else {
        $chipClass   = 'rm-bed-chip--vacant';
        $dotClass    = 'dot--vacant';
        $nextStatus  = 'Occupied';
        $toggleClass = 'rm-bed-toggle--occupy';
        $toggleLabel = '<i class="fas fa-lock"></i> Set Occupied';
        $statusText  = 'Available';
        $showDelete  = true;
    }

    $deleteBtn = $showDelete
        ? "<button class=\"rm-bed-delete-btn\" onclick=\"deleteBed($bedId, $roomId, this)\" title=\"Remove bed\"><i class=\"fas fa-times\"></i></button>"
        : '';

    return "
    <div class=\"rm-bed-chip $chipClass\" id=\"bed-chip-$bedId\">
        $deleteBtn
        <div class=\"rm-bed-chip-top\">
            <span class=\"rm-bed-label\"><i class=\"fas fa-bed\"></i> Bed $bedNo</span>
            <span class=\"rm-bed-status-dot $dotClass\"></span>
        </div>
        <span class=\"rm-bed-status-text\">$statusText</span>
        <button class=\"rm-bed-toggle-btn $toggleClass\"
                onclick=\"toggleBedStatus($bedId, '$nextStatus', this)\">
            $toggleLabel
        </button>
    </div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management | <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/room_management.css">
    <style>
        /* Bed panel header */
        .rm-bed-panel-header { display:flex; align-items:center; justify-content:space-between; }

        /* Add Bed button */
        .rm-add-bed-btn {
            display: inline-flex; align-items: center; gap: .35rem;
            padding: .28rem .75rem; border-radius: 6px; border: none; cursor: pointer;
            font-size: .78rem; font-weight: 600; font-family: inherit;
            background: var(--color-primary, #4f46e5); color: #fff;
            transition: opacity .15s;
        }
        .rm-add-bed-btn:hover   { opacity: .85; }
        .rm-add-bed-btn:disabled { opacity: .5; cursor: not-allowed; }

        /* Delete button on chip */
        .rm-bed-chip { position: relative; }
        .rm-bed-delete-btn {
            position: absolute; top: .45rem; right: .45rem;
            width: 22px; height: 22px; border-radius: 50%; border: none;
            background: transparent; color: #9ca3af; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; transition: background .15s, color .15s;
        }
        .rm-bed-delete-btn:hover { background: #fee2e2; color: #ef4444; }

        /* Reserved chip colour (yellow-ish) */
        .rm-bed-chip--reserved { background: #fffbeb; border-color: #fcd34d; }
        .dot--reserved { background: #f59e0b; }

        /* ── Floor filter bar ── */
        .rm-floor-filter {
            display: flex; flex-wrap: wrap; gap: .5rem;
            margin-bottom: 1.25rem;
            justify-content: center;
        }
        .rm-floor-pill {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .38rem .9rem; border-radius: 999px;
            border: 1.5px solid #e5e7eb;
            background: #f9fafb; color: #6b7280;
            font-size: .8rem; font-weight: 600; font-family: inherit;
            cursor: pointer; transition: all .15s;
            white-space: nowrap;
        }
        .rm-floor-pill:hover {
            border-color: #10b981;
            color: #10b981;
            background: #f0fdf4;
        }
        .rm-floor-pill--active {
            background: #10b981;
            border-color: #059669;
            color: #fff;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }
        .rm-floor-pill--active:hover {
            background: #059669;
            border-color: #047857;
            color: #fff;
        }

        /* ── DARK THEME OVERRIDES FOR PILLS ── */
        .dark-theme .rm-floor-pill {
            background: var(--off-white);
            border-color: rgba(255,255,255,0.05);
            color: var(--text-muted);
        }
        .dark-theme .rm-floor-pill:hover {
            background: rgba(16, 185, 129, 0.1);
            color: #34d399;
            border-color: rgba(16, 185, 129, 0.2);
        }
        .dark-theme .rm-floor-pill--active {
            background: rgba(16,185,129,0.15);
            border-color: var(--primary);
            color: #34d399;
            box-shadow: none;
        }
        .dark-theme .rm-floor-pill--active:hover {
            background: var(--primary);
            color: #fff;
        }
        .dark-theme .rm-empty { color: var(--text-muted); }
        .dark-theme .rm-floor-icon { background: rgba(16, 185, 129, 0.15); color: var(--primary); }
    </style>
</head>
<body class="admin-page">

    <header class="mobile-admin-header">
        <div class="logo-stack-mini" style="background:rgba(255,255,255,0.2);width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-hotel" style="font-size:.85rem;color:#fff;"></i>
        </div>
        <span class="font-bold text-sm tracking-wide">ADMIN PORTAL</span>
        <button id="sidebarToggleBtn" class="sidebar-toggle" onclick="toggleSidebar(event)"><i class="fas fa-bars"></i></button>
    </header>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">

        <header class="view-header">
            <div>
                <h1>Room &amp; Bed Manager <i class="fas fa-bed color-primary"></i></h1>
                <p>Rooms organised by floor. Click a room to manage its beds.</p>
            </div>
            <div class="header-btns" style="display:flex;gap:.75rem;align-items:center;">
                <div class="rm-search-wrap">
                    <i class="fas fa-search rm-search-icon"></i>
                    <input type="text" id="roomSearchAdmin" onkeyup="filterRoomsAdmin()"
                           placeholder="Search room…" class="rm-search-input">
                </div>
                <button class="rm-btn rm-btn--primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Room
                </button>
            </div>
        </header>

        <div class="rm-search-wrap rm-search--mobile">
            <i class="fas fa-search rm-search-icon"></i>
            <input type="text" id="roomSearchAdminMobile" onkeyup="filterRoomsAdminMobile()"
                   placeholder="Search room…" class="rm-search-input">
        </div>

        <!-- Floor filter pills -->
        <div class="rm-floor-filter" id="floorFilterBar">
            <button class="rm-floor-pill rm-floor-pill--active" data-floor="all" onclick="filterByFloor('all', this)">
                <i class="fas fa-layer-group"></i> All Floors
            </button>
            <?php foreach (array_keys($floors) as $fn): ?>
            <button class="rm-floor-pill" data-floor="<?php echo $fn; ?>" onclick="filterByFloor('<?php echo $fn; ?>', this)">
                <i class="fas fa-stairs"></i> <?php echo floor_label($fn); ?>
            </button>
            <?php endforeach; ?>
        </div>

        <?php if (empty($rooms)): ?>
            <div class="rm-empty rm-empty--page">
                <i class="fas fa-door-closed"></i>
                <p>No rooms yet. Click <strong>Add Room</strong> to get started.</p>
            </div>
        <?php else: ?>

            <?php foreach ($floors as $floor_no => $floor_rooms): ?>
            <section class="rm-floor-section" data-floor="<?php echo $floor_no; ?>">

                <div class="rm-floor-header">
                    <div class="rm-floor-title">
                        <span class="rm-floor-icon"><i class="fas fa-layer-group"></i></span>
                        <span><?php echo floor_label($floor_no); ?></span>
                    </div>
                    <span class="rm-floor-count">
                        <?php echo count($floor_rooms); ?> room<?php echo count($floor_rooms) !== 1 ? 's' : ''; ?>
                    </span>
                </div>

                <div class="rm-room-list">
                    <?php foreach ($floor_rooms as $r):
                        $total_beds     = (int)$r['total_beds'];
                        $occupied_count = (int)$r['occupied_count'];
                        $vacancies      = $total_beds - $occupied_count;
                        $is_full        = ($r['status'] === 'Full' || ($total_beds > 0 && $vacancies <= 0));
                        $occ_pct        = $total_beds > 0 ? round(($occupied_count / $total_beds) * 100) : 0;
                        $room_beds      = $beds_by_room[$r['id']] ?? [];

                        if ($is_full) {
                            $badge_class = 'rm-badge--full';
                            $badge_label = 'Full';
                        } elseif ($vacancies === $total_beds) {
                            $badge_class = 'rm-badge--vacant';
                            $badge_label = 'Vacant';
                        } else {
                            $badge_class = 'rm-badge--partial';
                            $badge_label = $vacancies . ' slot' . ($vacancies !== 1 ? 's' : '') . ' left';
                        }
                    ?>
                    <div class="rm-row-wrap" data-room-id="<?php echo $r['id']; ?>">

                        <div class="rm-row" onclick="toggleBeds(<?php echo $r['id']; ?>)">

                            <button class="rm-expand-btn" id="expand-<?php echo $r['id']; ?>" tabindex="-1" aria-label="Toggle beds">
                                <i class="fas fa-chevron-right"></i>
                            </button>

                            <div class="rm-row-identity">
                                <div class="rm-room-icon"><i class="fas fa-door-open"></i></div>
                                <div class="rm-row-info">
                                    <span class="rm-room-name">Room <?php echo htmlspecialchars($r['room_no']); ?></span>
                                    <span class="rm-room-meta"><?php echo $occupied_count; ?>/<?php echo $total_beds; ?> beds occupied</span>
                                </div>
                            </div>

                            <div class="rm-row-progress">
                                <div class="rm-progress-track">
                                    <div class="rm-progress-fill <?php echo $is_full ? 'rm-progress-fill--full' : ''; ?>"
                                         style="width:<?php echo $occ_pct; ?>%"></div>
                                </div>
                                <span class="rm-progress-label"><?php echo $occ_pct; ?>%</span>
                            </div>

                            <span class="rm-badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>

                            <div class="rm-row-actions" onclick="event.stopPropagation()">
                                <button class="rm-action-btn rm-action-btn--edit"
                                        onclick="openEditModal(<?php echo $r['id']; ?>, '<?php echo addslashes($r['room_no']); ?>', <?php echo $r['floor_no']; ?>, '<?php echo $r['status']; ?>')"
                                        title="Edit room">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                                <a href="room_management.php?action=delete_room&id=<?php echo $r['id']; ?>"
                                   class="rm-action-btn rm-action-btn--delete"
                                   onclick="return confirm('Delete Room <?php echo htmlspecialchars($r['room_no']); ?>? This cannot be undone.')"
                                   title="Delete room">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>

                        </div><!-- /.rm-row -->

                        <!-- Bed panel -->
                        <div class="rm-bed-panel" id="beds-<?php echo $r['id']; ?>">
                            <div class="rm-bed-panel-inner">

                                <div class="rm-bed-panel-header">
                                    <span><i class="fas fa-bed"></i> Beds in Room <?php echo htmlspecialchars($r['room_no']); ?></span>
                                    <div style="display:flex;align-items:center;gap:.75rem;">
                                        <span class="rm-bed-count-label" id="bed-count-label-<?php echo $r['id']; ?>">
                                            <?php echo count($room_beds); ?> bed<?php echo count($room_beds) !== 1 ? 's' : ''; ?>
                                        </span>
                                        <button class="rm-add-bed-btn" onclick="addBed(<?php echo $r['id']; ?>, this)">
                                            <i class="fas fa-plus"></i> Add Bed
                                        </button>
                                    </div>
                                </div>

                                <p class="rm-bed-empty" id="no-beds-msg-<?php echo $r['id']; ?>"
                                   <?php echo !empty($room_beds) ? 'style="display:none"' : ''; ?>>
                                    No beds yet — click <strong>Add Bed</strong> to add one.
                                </p>

                                <div class="rm-bed-grid" id="bed-grid-<?php echo $r['id']; ?>">
                                    <?php foreach ($room_beds as $bed):
                                        $reserved_at = (!empty($bed['reserved_at']) && $bed['reserved_at'] !== '0000-00-00 00:00:00')
                                            ? date('M d, Y', strtotime($bed['reserved_at']))
                                            : null;
                                        echo buildBedChipHtml(
                                            $bed['id'],
                                            $bed['bed_no'],
                                            $bed['status'],
                                            $reserved_at,
                                            $r['id']
                                        );
                                    endforeach; ?>
                                </div>

                            </div>
                        </div>

                    </div><!-- /.rm-row-wrap -->
                    <?php endforeach; ?>
                </div>

            </section>
            <?php endforeach; ?>

        <?php endif; ?>
    </main>

    <!-- Add Room Modal -->
    <div id="addModal" class="modal-wrapper">
        <div class="modal-body max-w-500">
            <button class="rm-modal-close" onclick="closeModal('addModal')"><i class="fas fa-times"></i></button>
            <h2 class="font-bold mb-4">Add New Room</h2>
            <form method="POST">
                <input type="hidden" name="add_room" value="1">
                <div class="form-group mb-4">
                    <label>Floor Number</label>
                    <select name="floor_no" class="input-select w-full" required>
                        <option value="2">2nd Floor</option>
                        <option value="3">3rd Floor</option>
                        <option value="4">4th Floor</option>
                    </select>
                </div>
                <div class="form-group mb-4">
                    <label>Room Name / No.</label>
                    <input type="text" name="room_no" class="input-text w-full" required placeholder="e.g. 201">
                </div>
                <div class="form-group mb-6">
                    <label>Initial Bed Count</label>
                    <input type="number" name="beds_count" class="input-text w-full" value="4" min="0" max="50" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Create Room</button>
            </form>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <div id="editModal" class="modal-wrapper">
        <div class="modal-body max-w-500">
            <button class="rm-modal-close" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
            <h2 class="font-bold mb-4">Edit Room</h2>
            <form method="POST">
                <input type="hidden" name="edit_room" value="1">
                <input type="hidden" name="room_id" id="edit_room_id">
                <div class="form-group mb-4">
                    <label>Room Name / No.</label>
                    <input type="text" name="room_no" id="edit_room_no" class="input-text w-full" required>
                </div>
                <div class="form-group mb-4">
                    <label>Floor</label>
                    <select name="floor_no" id="edit_floor_no" class="input-select w-full">
                        <option value="2">2nd Floor</option>
                        <option value="3">3rd Floor</option>
                        <option value="4">4th Floor</option>
                    </select>
                </div>
                <div class="form-group mb-6">
                    <label>Status</label>
                    <select name="room_status" id="edit_room_status" class="input-select w-full">
                        <option value="Available">Available</option>
                        <option value="Full">Full (Blocked)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
    /* ══════════════════════════════════════════
       Expand / collapse bed panel
    ══════════════════════════════════════════ */
    function toggleBeds(roomId) {
        const panel  = document.getElementById('beds-' + roomId);
        const btn    = document.getElementById('expand-' + roomId);
        const isOpen = panel.classList.contains('rm-bed-panel--open');

        document.querySelectorAll('.rm-bed-panel--open').forEach(p => p.classList.remove('rm-bed-panel--open'));
        document.querySelectorAll('.rm-expand-btn.expanded').forEach(b => b.classList.remove('expanded'));

        if (!isOpen) {
            panel.classList.add('rm-bed-panel--open');
            btn.classList.add('expanded');
        }
    }

    /* ══════════════════════════════════════════
       AJAX: toggle bed status
    ══════════════════════════════════════════ */
    async function toggleBedStatus(bedId, newStatus, btnEl) {
        btnEl.disabled = true;
        const original = btnEl.innerHTML;
        btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const res  = await fetch('../api/toggle_bed.php', {
                method : 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body   : `bed_id=${bedId}&status=${encodeURIComponent(newStatus)}`
            });
            const data = await res.json();

            if (data.success) {
                const chip = document.getElementById('bed-chip-' + bedId);
                refreshChipUI(chip, bedId, newStatus, data.reserved_at);
                if (data.room_summary) updateRoomSummary(data.room_summary);
            } else {
                alert(data.message || 'Failed to update bed status.');
                btnEl.innerHTML = original;
            }
        } catch (e) {
            alert('Network error. Please try again.');
            btnEl.innerHTML = original;
        }

        btnEl.disabled = false;
    }

    /* Refresh chip DOM */
    function refreshChipUI(chip, bedId, status, reservedAt) {
        const isOccupied = status === 'Occupied';
        const isReserved = status === 'Reserved';
        chip.classList.remove('rm-bed-chip--occupied','rm-bed-chip--reserved','rm-bed-chip--vacant');
        chip.classList.add(isOccupied ? 'rm-bed-chip--occupied' : isReserved ? 'rm-bed-chip--reserved' : 'rm-bed-chip--vacant');
        const dot = chip.querySelector('.rm-bed-status-dot');
        dot.classList.remove('dot--occupied','dot--reserved','dot--vacant');
        dot.classList.add(isOccupied ? 'dot--occupied' : isReserved ? 'dot--reserved' : 'dot--vacant');
        let statusHtml = isOccupied ? 'Occupied' + (reservedAt ? `<small>since ${reservedAt}</small>` : '') : isReserved ? 'Reserved' : 'Available';
        chip.querySelector('.rm-bed-status-text').innerHTML = statusHtml;
        const btn = chip.querySelector('.rm-bed-toggle-btn');
        const nextStatus  = isOccupied ? 'Available' : 'Occupied';
        btn.className     = 'rm-bed-toggle-btn ' + (isOccupied ? 'rm-bed-toggle--release' : 'rm-bed-toggle--occupy');
        btn.innerHTML     = isOccupied ? '<i class="fas fa-lock-open"></i> Set Available' : '<i class="fas fa-lock"></i> Set Occupied';
        btn.setAttribute('onclick', `toggleBedStatus(${bedId}, '${nextStatus}', this)`);
        const delBtn = chip.querySelector('.rm-bed-delete-btn');
        if (delBtn) delBtn.style.display = isOccupied ? 'none' : '';
    }

    /* ══════════════════════════════════════════
       AJAX: Add bed
    ══════════════════════════════════════════ */
    async function addBed(roomId, btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const res  = await fetch('../api/add_bed.php', {
                method : 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body   : `room_id=${roomId}`
            });
            const data = await res.json();
            if (data.success) {
                const grid  = document.getElementById('bed-grid-' + roomId);
                const noMsg = document.getElementById('no-beds-msg-' + roomId);
                if (noMsg) noMsg.style.display = 'none';
                grid.insertAdjacentHTML('beforeend', createBedChipHtml(data.bed.id, data.bed.bed_no, 'Available', null, roomId));
                updateBedCountLabel(roomId, data.room_summary.total);
                if (data.room_summary) updateRoomSummary(data.room_summary);
            } else { alert(data.message || 'Failed to add bed.'); }
        } catch (e) { alert('Network error. Please try again.'); }
        btnEl.disabled = false;
        btnEl.innerHTML = '<i class="fas fa-plus"></i> Add Bed';
    }

    /* ══════════════════════════════════════════
       AJAX: Delete bed
    ══════════════════════════════════════════ */
    async function deleteBed(bedId, roomId, btnEl) {
        if (!confirm('Remove this bed? This cannot be undone.')) return;
        btnEl.disabled = true;
        btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const res  = await fetch('../api/delete_bed.php', {
                method : 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body   : `bed_id=${bedId}`
            });
            const data = await res.json();
            if (data.success) {
                const chip = document.getElementById('bed-chip-' + bedId);
                chip.style.cssText += 'opacity:0;transform:scale(.85);transition:opacity .2s,transform .2s;';
                setTimeout(() => {
                    chip.remove();
                    updateBedCountLabel(roomId, data.room_summary.total);
                    const grid = document.getElementById('bed-grid-' + roomId);
                    if (!grid.children.length) { document.getElementById('no-beds-msg-' + roomId).style.display = ''; }
                }, 220);
                if (data.room_summary) updateRoomSummary(data.room_summary);
            } else {
                alert(data.message || 'Failed to delete bed.');
                btnEl.disabled = false;
                btnEl.innerHTML = '<i class="fas fa-times"></i>';
            }
        } catch (e) { alert('Network error. Please try again.'); btnEl.disabled = false; btnEl.innerHTML = '<i class="fas fa-times"></i>'; }
    }

    function createBedChipHtml(bedId, bedNo, status, reservedAt, roomId) {
        const isOccupied = status === 'Occupied';
        const isReserved = status === 'Reserved';
        const chipClass   = isOccupied ? 'rm-bed-chip--occupied' : isReserved ? 'rm-bed-chip--reserved' : 'rm-bed-chip--vacant';
        const dotClass    = isOccupied ? 'dot--occupied' : isReserved ? 'dot--reserved' : 'dot--vacant';
        const nextStatus  = isOccupied ? 'Available' : 'Occupied';
        const toggleClass = isOccupied ? 'rm-bed-toggle--release' : 'rm-bed-toggle--occupy';
        const toggleLabel = isOccupied ? '<i class="fas fa-lock-open"></i> Set Available' : '<i class="fas fa-lock"></i> Set Occupied';
        const statusText  = isOccupied ? 'Occupied' + (reservedAt ? `<small>since ${reservedAt}</small>` : '') : isReserved ? 'Reserved' : 'Available';
        const deleteBtn   = isOccupied ? '' : `<button class="rm-bed-delete-btn" onclick="deleteBed(${bedId}, ${roomId}, this)" title="Remove bed"><i class="fas fa-times"></i></button>`;
        return `<div class="rm-bed-chip ${chipClass}" id="bed-chip-${bedId}">${deleteBtn}<div class="rm-bed-chip-top"><span class="rm-bed-label"><i class="fas fa-bed"></i> Bed ${bedNo}</span><span class="rm-bed-status-dot ${dotClass}"></span></div><span class="rm-bed-status-text">${statusText}</span><button class="rm-bed-toggle-btn ${toggleClass}" onclick="toggleBedStatus(${bedId}, '${nextStatus}', this)">${toggleLabel}</button></div>`;
    }

    function updateRoomSummary(s) {
        const wrap = document.querySelector(`.rm-row-wrap[data-room-id="${s.room_id}"]`);
        if (!wrap) return;
        wrap.querySelector('.rm-room-meta').textContent = `${s.occupied}/${s.total} beds occupied`;
        const fill = wrap.querySelector('.rm-progress-fill');
        fill.style.width = s.pct + '%';
        fill.classList.toggle('rm-progress-fill--full', s.is_full);
        wrap.querySelector('.rm-progress-label').textContent = s.pct + '%';
        const badge = wrap.querySelector('.rm-badge');
        badge.className = 'rm-badge';
        if (s.is_full) { badge.classList.add('rm-badge--full'); badge.textContent = 'Full'; }
        else if (s.vacancies === s.total) { badge.classList.add('rm-badge--vacant'); badge.textContent = 'Vacant'; }
        else { badge.classList.add('rm-badge--partial'); badge.textContent = `${s.vacancies} slot${s.vacancies !== 1 ? 's' : ''} left`; }
    }

    function updateBedCountLabel(roomId, total) {
        const lbl = document.getElementById('bed-count-label-' + roomId);
        if (lbl) lbl.textContent = total + ' bed' + (total !== 1 ? 's' : '');
    }

    function openAddModal()  { document.getElementById('addModal').classList.add('open'); }
    function closeModal(id)  { document.getElementById(id).classList.remove('open'); }

    function openEditModal(id, no, f, s) {
        document.getElementById('edit_room_id').value     = id;
        document.getElementById('edit_room_no').value     = no;
        document.getElementById('edit_floor_no').value    = f;
        document.getElementById('edit_room_status').value = s;
        document.getElementById('editModal').classList.add('open');
    }

    let activeFloor = 'all';

    function filterByFloor(floor, pillEl) {
        activeFloor = floor;
        document.querySelectorAll('.rm-floor-pill').forEach(p => p.classList.remove('rm-floor-pill--active'));
        pillEl.classList.add('rm-floor-pill--active');
        document.querySelectorAll('.rm-floor-section').forEach(section => {
            const match = floor === 'all' || section.dataset.floor == floor;
            section.style.display = match ? '' : 'none';
        });
        const q = (document.getElementById('roomSearchAdmin').value || document.getElementById('roomSearchAdminMobile').value).toLowerCase().trim();
        if (q) applySearch(q);
    }

    function applySearch(q) {
        document.querySelectorAll('.rm-floor-section').forEach(section => {
            if (activeFloor !== 'all' && section.dataset.floor != activeFloor) return;
            let visible = 0;
            section.querySelectorAll('.rm-row-wrap').forEach(wrap => {
                const show = wrap.querySelector('.rm-room-name').innerText.toLowerCase().includes(q);
                wrap.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            section.style.display = visible > 0 ? '' : 'none';
        });
    }

    function filterRoomsAdmin() { applyFilter(document.getElementById('roomSearchAdmin').value.toLowerCase().trim()); }
    function filterRoomsAdminMobile() { applyFilter(document.getElementById('roomSearchAdminMobile').value.toLowerCase().trim()); }

    function applyFilter(q) {
        document.querySelectorAll('.rm-floor-section').forEach(section => {
            if (activeFloor !== 'all' && section.dataset.floor != activeFloor) { section.style.display = 'none'; return; }
            section.style.display = '';
        });
        if (q) applySearch(q);
        else {
            document.querySelectorAll('.rm-floor-section').forEach(section => {
                if (activeFloor !== 'all' && section.dataset.floor != activeFloor) return;
                section.querySelectorAll('.rm-row-wrap').forEach(w => w.style.display = '');
            });
        }
    }
    </script>
</body>
</html>
