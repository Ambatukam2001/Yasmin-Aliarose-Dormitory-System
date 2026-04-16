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
    while ($row = $stats_res->fetch_assoc()) {
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
            while ($row = $floors_query->fetch_assoc()) $floors[] = (int)$row['floor_no'];
        }
        if (empty($floors)) $floors = [2, 3, 4];

        foreach ($floors as $index => $f):
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

/* PAGE HEADER */
.bk-header { text-align: center; margin-bottom: 2rem; }
.bk-title  { font-family: 'Outfit', sans-serif; font-weight: 900;
             font-size: clamp(1.6rem, 4vw, 2.2rem); color: #1e293b; margin: 0 0 .4rem; }
.bk-sub    { font-family: 'Inter', sans-serif; font-size: .95rem;
             color: #64748b; margin: 0; }

/* ── FLOOR TABS ─────────────────────────────────────── */
.floor-tab-bar { display: flex; justify-content: center; gap: .75rem;
                 flex-wrap: wrap; margin-bottom: 1.75rem; }

.floor-tab {
    display: flex; flex-direction: column; align-items: center;
    padding: .85rem 2rem; border-radius: 1.25rem;
    background: #fff; border: 2px solid #e2e8f0;
    cursor: pointer; transition: .25s;
    font-family: 'Outfit', sans-serif; min-width: 130px;
}
.floor-tab:hover { border-color: #10b981; background: #f0fdf4; }
.floor-tab.active {
    border-color: #10b981; background: #f0fdf4;
    box-shadow: 0 4px 16px rgba(16,185,129,.15);
}
.ft-label { font-weight: 800; font-size: 1rem; color: #1e293b; }
.ft-badge {
    font-size: .65rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .08em; color: #10b981; background: #d1fae5;
    padding: .2rem .6rem; border-radius: 99px; margin-top: .3rem;
}
.ft-badge--full { background: #fee2e2; color: #b91c1c; }

/* ── SEARCH BAR ──────────────────────────────────────── */
.bk-search {
    position: relative; max-width: 480px;
    margin: 0 auto 2rem;
}
.bk-search-ico {
    position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%);
    color: #cbd5e1; font-size: .95rem; pointer-events: none;
}
.bk-search input {
    width: 100%; padding: .85rem 1rem .85rem 2.8rem;
    border: 2px solid #e2e8f0; border-radius: 1rem;
    background: #fff; font-family: 'Inter', sans-serif;
    font-size: .92rem; font-weight: 600; color: #1e293b;
    outline: none; transition: .2s;
}
.bk-search input:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.1); }
.bk-search input::placeholder { color: #cbd5e1; font-weight: 500; }

/* ── ROOMS GRID ──────────────────────────────────────── */
.rooms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.25rem;
}
.rooms-loading, .rooms-empty {
    grid-column: 1 / -1; text-align: center;
    padding: 3rem 1rem; color: #94a3b8;
    font-family: 'Inter', sans-serif; font-size: .95rem; font-weight: 600;
}
.rooms-empty i, .rooms-loading i { font-size: 2rem; display: block; margin-bottom: .75rem; }

/* ── ROOM CARD ───────────────────────────────────────── */
.room-card {
    background: #fff; border-radius: 1.25rem;
    padding: 1.35rem 1.35rem 1.1rem;
    border: 2px solid #f1f5f9;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
    cursor: pointer; position: relative;
    transition: transform .22s, box-shadow .22s, border-color .22s;
    display: flex; flex-direction: column; gap: .6rem;
    font-family: 'Inter', sans-serif;
}
.room-card:hover:not(.room-card--full) {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(16,185,129,.13);
    border-color: #10b981;
}
.room-card--full { cursor: default; opacity: .72; }
.room-card:focus-visible { outline: 3px solid #10b981; outline-offset: 3px; }

/* status pill */
.room-status-pill {
    display: inline-flex; align-items: center; gap: .3rem;
    align-self: flex-start;
    font-family: 'Outfit', sans-serif; font-size: .62rem;
    font-weight: 900; text-transform: uppercase; letter-spacing: .1em;
    padding: .28rem .7rem; border-radius: 99px;
}
.room-status-pill i { font-size: .5rem; }
.pill--avail { background: #d1fae5; color: #065f46; }
.pill--full  { background: #fee2e2; color: #991b1b; }

/* title */
.room-number {
    font-family: 'Outfit', sans-serif; font-weight: 900;
    font-size: 1.3rem; color: #1e293b; margin: 0; line-height: 1.1;
}
.room-floor  {
    font-size: .65rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: #94a3b8; margin: 0;
}

/* vacancy */
.room-vacancy {
    display: flex; align-items: center; gap: .4rem;
    font-size: .83rem; font-weight: 600; color: #10b981;
}
.room-vacancy i { font-size: .85rem; }
.room-vacancy strong { font-weight: 900; }
.room-vacancy--full { color: #94a3b8; }

/* bar */
.room-bar-track {
    height: 6px; background: #f1f5f9; border-radius: 99px;
    overflow: hidden; display: flex;
}
.room-bar-fill { height: 100%; border-radius: 99px; transition: width .4s; }
.room-bar-res  { height: 100%; background: #fbbf24; }
.room-bar-meta {
    display: flex; justify-content: space-between;
    font-size: .62rem; font-weight: 700; color: #94a3b8;
    margin-top: -.1rem;
}

/* footer */
.room-footer {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: .2rem;
}
.room-legend {
    display: flex; align-items: center;
    font-size: .65rem; font-weight: 600; color: #94a3b8;
}
.leg-dot {
    display: inline-block; width: 6px; height: 6px;
    border-radius: 50%; margin-right: 3px; vertical-align: middle;
}
.leg-dot--occ { background: #10b981; }
.leg-dot--res { background: #fbbf24; }

.room-cta {
    display: inline-flex; align-items: center; gap: .35rem;
    background: #10b981; color: #fff;
    font-family: 'Outfit', sans-serif; font-weight: 800;
    font-size: .7rem; text-transform: uppercase; letter-spacing: .05em;
    padding: .4rem .9rem; border-radius: .55rem;
    transition: background .18s; border: none;
}
.room-card:hover .room-cta:not(.room-cta--full) { background: #059669; }
.room-cta--full { background: #f1f5f9; color: #94a3b8; }
.room-cta i { font-size: .6rem; }

/* ── DARK THEME OVERRIDES ────────────────────────────── */
.dark-theme .booking-page-wrap { background: var(--background); }
.dark-theme .bk-title { color: var(--text-primary); }
.dark-theme .bk-sub { color: var(--text-secondary); }
.dark-theme .floor-tab { background: var(--off-white); border-color: rgba(255,255,255,0.05); box-shadow: none; }
.dark-theme .floor-tab:hover, .dark-theme .floor-tab.active { background: rgba(16, 185, 129, 0.15); border-color: var(--primary); }
.dark-theme .ft-label { color: var(--text-primary); }
.dark-theme .ft-badge { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.dark-theme .ft-badge--full { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.dark-theme .bk-search input { background: var(--off-white); border-color: rgba(255,255,255,0.05); color: var(--text-primary); }
.dark-theme .bk-search input:focus { background: var(--white); }
.dark-theme .bk-search input::placeholder { color: var(--text-muted); }
.dark-theme .rooms-empty, .dark-theme .rooms-loading { color: var(--text-muted); }
.dark-theme .room-card { background: var(--white); border-color: rgba(255,255,255,0.05); }
.dark-theme .room-number { color: var(--text-primary); }
.dark-theme .room-floor { color: var(--text-muted); }
.dark-theme .room-vacancy { color: #34d399; }
.dark-theme .room-vacancy--full { color: var(--text-muted); }
.dark-theme .room-bar-track { background: rgba(255,255,255,0.05); }
.dark-theme .room-bar-meta { color: var(--text-muted); }
.dark-theme .room-legend { color: var(--text-muted); }
.dark-theme .room-cta--full { background: rgba(255,255,255,0.05); color: var(--text-muted); }
.dark-theme .pill--avail { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.dark-theme .pill--full { background: rgba(239, 68, 68, 0.15); color: #f87171; }
</style>

<?php include 'api/footer.php'; ?>
