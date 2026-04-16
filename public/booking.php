<?php 
require_once 'api/core.php';
$page_title = "Select Your Space";

// Guests can view rooms; login required in the booking modal.

include 'api/head.php';
include 'api/header.php';

// Floor stats for tab badges — uses live bed counts, not static capacity column
$floor_stats = [];
$stats_res = $conn->query("
    SELECT r.floor_no, 
        COUNT(b.id) as total_beds,
        SUM(CASE WHEN b.status = 'Occupied' THEN 1 ELSE 0 END) as occupied_count
    FROM rooms r
    LEFT JOIN beds b ON b.room_id = r.id
    GROUP BY r.floor_no
");
if ($stats_res) {
    $stats_rows = $stats_res->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stats_rows as $row) {
        $floor_stats[$row['floor_no']] = [
            'total'    => (int)$row['total_beds'],
            'occupied' => (int)$row['occupied_count']
        ];
    }
}
?>

<main class="booking-page-wrap">
<div class="booking-container-inner">

    <!-- PAGE HEADER -->
    <div class="bk-header">
        <h1 class="bk-title">Find Your Room</h1>
        <p class="bk-sub">Choose a floor, pick your room, and secure your bed in minutes.</p>
    </div>

    <!-- FLOOR TABS -->
    <div class="floor-tab-bar" id="floorTabBar">
        <?php 
        $floors_query = $conn->query("SELECT DISTINCT floor_no FROM rooms ORDER BY floor_no ASC");
        $floors = [];
        if ($floors_query) {
            $floors = $floors_query->fetchAll(PDO::FETCH_COLUMN);
        }
        if (empty($floors)) $floors = [2, 3, 4];

        foreach ($floors as $index => $f):
            $f = (int)$f;
            $total    = isset($floor_stats[$f]) ? $floor_stats[$f]['total']    : 0;
            $occupied = isset($floor_stats[$f]) ? $floor_stats[$f]['occupied'] : 0;
            $free     = max(0, $total - $occupied);
            $isFull   = ($total > 0 && $free <= 0);
            $label    = $f === 2 ? '2nd' : ($f === 3 ? '3rd' : $f.'th');
            $isActive = ($index === 0) ? 'active' : '';
        ?>
        <button class="floor-tab <?php echo $isActive; ?>"
                data-floor="<?php echo $f; ?>"
                onclick="switchFloor(<?php echo $f; ?>, this)">
            <span class="ft-label"><?php echo $label; ?> Floor</span>
            <span class="ft-badge <?php echo $isFull ? 'ft-badge--full' : ''; ?>">
                <?php echo $isFull ? 'FULL' : ($total === 0 ? 'No rooms' : $free.' free'); ?>
            </span>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- SEARCH BAR -->
    <div class="bk-search">
        <i class="fas fa-search bk-search-ico"></i>
        <input type="text" id="roomSearch" placeholder="Search room number (e.g. 201)…" oninput="filterRoomCards()">
    </div>

    <!-- ROOMS GRID — populated by JS -->
    <div id="roomsGrid" class="rooms-grid">
        <div class="rooms-loading" id="roomsLoading">
            <i class="fas fa-spinner fa-spin"></i> Loading rooms…
        </div>
    </div>

</div>
</main>

<script src="assets/js/booking.js?v=<?php echo time(); ?>"></script>
<script>
/* ═══════════════════════════════════════════════════════════
   FLOOR SWITCHING & ROOM CARD RENDERING
   ═══════════════════════════════════════════════════════════ */
let activeFloor = 2;

function switchFloor(floor, btn) {
    // Update active tab
    document.querySelectorAll('.floor-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeFloor = floor;
    loadRooms(floor);
}

async function loadRooms(floor) {
    const grid    = document.getElementById('roomsGrid');
    const loading = document.getElementById('roomsLoading');
    grid.innerHTML = '<div class="rooms-loading"><i class="fas fa-spinner fa-spin"></i> Loading rooms…</div>';

    try {
        const res   = await fetch(`api/room_api.php?action=floor_rooms&floor_no=${floor}`);
        const rooms = await res.json();
        renderRooms(rooms, floor);
    } catch(e) {
        grid.innerHTML = '<div class="rooms-empty"><i class="fas fa-exclamation-circle"></i> Failed to load rooms. Please refresh.</div>';
    }
}

function renderRooms(rooms, floor) {
    const grid = document.getElementById('roomsGrid');

    if (!rooms.length) {
        grid.innerHTML = `
            <div class="rooms-empty">
                <i class="fas fa-door-closed"></i>
                <p>No rooms found on this floor yet.</p>
            </div>`;
        return;
    }

    grid.innerHTML = rooms.map(r => {
        const total    = parseInt(r.total_beds) || 0;
        const occ      = parseInt(r.occupied_count) || 0;
        const res      = parseInt(r.reserved_count) || 0;
        const free     = Math.max(0, total - occ - res);
        const isFull   = r.status === 'Full' || (total > 0 && free <= 0);
        const occPct   = total > 0 ? Math.round((occ / total) * 100) : 0;
        const resPct   = total > 0 ? Math.round((res / total) * 100) : 0;
        const barClr   = isFull ? '#ef4444' : (occPct >= 70 ? '#f59e0b' : '#10b981');

        return `
        <div class="room-card ${isFull ? 'room-card--full' : ''}"
             ${isFull ? '' : `onclick="openBookingModal(${r.id}, '${r.room_no}', ${r.floor_no})" role="button" tabindex="0"`}>

            <!-- status pill -->
            <span class="room-status-pill ${isFull ? 'pill--full' : 'pill--avail'}">
                <i class="fas ${isFull ? 'fa-lock' : 'fa-check-circle'}"></i>
                ${isFull ? 'Full' : 'Available'}
            </span>

            <!-- title -->
            <h3 class="room-number">Room ${r.room_no}</h3>
            <p class="room-floor">${floor}${floor===2?'nd':floor===3?'rd':'th'} Floor</p>

            <!-- vacancy info -->
            <div class="room-vacancy ${isFull ? 'room-vacancy--full' : ''}">
                <i class="fas fa-bed"></i>
                ${isFull
                    ? 'No beds available'
                    : `<strong>${free}</strong> of ${total} beds free`}
            </div>

            <!-- progress bar -->
            <div class="room-bar-track">
                <div class="room-bar-fill" style="width:${occPct}%;background:${barClr};"></div>
                <div class="room-bar-res"  style="width:${resPct}%;"></div>
            </div>
            <div class="room-bar-meta">
                <span>${occPct}% occupied</span>
                <span>${free} free</span>
            </div>

            <!-- footer -->
            <div class="room-footer">
                <div class="room-legend">
                    <span class="leg-dot leg-dot--occ"></span> Occ
                    &nbsp;
                    <span class="leg-dot leg-dot--res"></span> Res
                </div>
                <div class="room-cta ${isFull ? 'room-cta--full' : ''}">
                    ${isFull ? 'Unavailable' : 'Select <i class="fas fa-arrow-right"></i>'}
                </div>
            </div>
        </div>`;
    }).join('');
}

function filterRoomCards() {
    const q = document.getElementById('roomSearch').value.toLowerCase();
    document.querySelectorAll('.room-card').forEach(card => {
        const num = card.querySelector('.room-number')?.innerText.toLowerCase() || '';
        card.style.display = num.includes(q) ? '' : 'none';
    });
}

// Load first available floor on page ready
const firstFloor = <?php echo isset($floors[0]) ? $floors[0] : 2; ?>;
document.addEventListener('DOMContentLoaded', () => loadRooms(firstFloor));
</script>

<style>
/* ═══════════════════════════════════════════════════════
   BOOKING PAGE — SELF-CONTAINED STYLES
   ═══════════════════════════════════════════════════════ */
.booking-page-wrap      { background: #f8fafc; min-height: 80vh; padding: 2rem 1rem 4rem; }
.booking-container-inner{ max-width: 1100px; margin: 0 auto; }

.bk-header { text-align: center; margin-bottom: 3.5rem; }
.bk-title  { font-family: 'Outfit', sans-serif; font-weight: 900; font-size: 2.8rem; color: #1e293b; letter-spacing: -0.02em; margin-bottom: 0.75rem; }
.bk-sub    { color: #64748b; font-size: 1.1rem; font-weight: 500; }

.floor-tab-bar { display: flex; gap: 1rem; justify-content: center; margin-bottom: 2.5rem; flex-wrap: wrap; }
.floor-tab {
    background: #fff; border: 1.5px solid #e2e8f0; padding: 1rem 1.75rem; border-radius: 1.25rem;
    cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex; flex-direction: column; align-items: center; gap: 0.25rem; min-width: 160px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}
.floor-tab:hover { border-color: #10b981; transform: translateY(-3px); box-shadow: 0 12px 20px -8px rgba(16,185,129,0.15); }
.floor-tab.active { background: #10b981; border-color: #10b981; color: #fff; transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(16,185,129,0.25); }

.ft-label { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.05rem; }
.ft-badge { font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; padding: 0.2rem 0.6rem; border-radius: 99px; background: #f1f5f9; color: #64748b; }
.floor-tab.active .ft-badge { background: rgba(255,255,255,0.2); color: #fff; }
.ft-badge--full { background: #fef2f2; color: #ef4444; }

.bk-search { position: relative; max-width: 500px; margin: 0 auto 3.5rem; }
.bk-search-ico { position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1.1rem; }
.bk-search input {
    width: 100%; padding: 1.1rem 1.25rem 1.1rem 3.25rem; border-radius: 1.25rem; border: 1.5px solid #e2e8f0;
    font-family: inherit; font-size: 1.05rem; outline: none; transition: all 0.2s; background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.bk-search input:focus { border-color: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,0.1); }

.rooms-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.75rem; }
.rooms-loading, .rooms-empty { grid-column: 1 / -1; text-align: center; padding: 5rem 2rem; color: #94a3b8; font-weight: 600; }
.rooms-loading i, .rooms-empty i { font-size: 2.5rem; display: block; margin-bottom: 1.25rem; }

.room-card {
    background: #fff; border-radius: 1.75rem; padding: 2.25rem; border: 1px solid #f1f5f9; position: relative;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
}
.room-card:not(.room-card--full):hover { transform: translateY(-8px) scale(1.01); border-color: #10b981; box-shadow: 0 25px 50px -12px rgba(16, 185, 129, 0.15); }
.room-card--full { cursor: default; opacity: 0.8; filter: grayscale(0.5); }

.room-status-pill {
    position: absolute; top: 1.5rem; right: 1.5rem; font-size: 0.65rem; font-weight: 900; text-transform: uppercase;
    letter-spacing: 0.08em; padding: 0.35rem 0.75rem; border-radius: 99px; display: flex; align-items: center; gap: 0.35rem;
}
.pill--avail { background: #d1fae5; color: #065f46; }
.pill--full  { background: #fee2e2; color: #b91c1c; }

.room-number { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.6rem; color: #1e293b; margin: 0; }
.room-floor  { font-size: 0.9rem; color: #64748b; font-weight: 600; margin: 0.25rem 0 1.75rem; }

.room-vacancy { display: flex; align-items: center; gap: 0.6rem; color: #475569; font-size: 0.95rem; margin-bottom: 0.75rem; }
.room-vacancy i { color: #94a3b8; font-size: 0.85rem; }
.room-vacancy--full { color: #94a3b8; }

.room-bar-track { height: 8px; background: #f1f5f9; border-radius: 99px; overflow: hidden; position: relative; display: flex; margin-bottom: 0.6rem; }
.room-bar-fill  { height: 100%; border-radius: 99px; transition: width 0.6s ease; }
.room-bar-res   { height: 100%; background: #fcd34d; border-radius: 99px; opacity: 0.6; transition: width 0.6s ease; }
.room-bar-meta  { display: flex; justify-content: space-between; font-size: 0.72rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }

.room-footer { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #f8fafc; display: flex; align-items: center; justify-content: space-between; }
.room-legend { font-size: 0.68rem; font-weight: 700; color: #94a3b8; display: flex; align-items: center; }
.leg-dot { width: 6px; height: 6px; border-radius: 50%; margin-right: 4px; }
.leg-dot--occ { background: #10b981; }
.leg-dot--res { background: #f59e0b; }

.room-cta { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 0.92rem; color: #10b981; display: flex; align-items: center; gap: 0.5rem; transition: gap 0.2s; }
.room-card:hover .room-cta { gap: 0.85rem; }
.room-cta--full { color: #94a3b8; }

@media (max-width: 640px) {
    .bk-title { font-size: 2.2rem; }
    .rooms-grid { grid-template-columns: 1fr; }
    .floor-tab { min-width: 130px; padding: 0.85rem 1rem; }
}
</style>

<?php include 'api/footer.php'; ?>
