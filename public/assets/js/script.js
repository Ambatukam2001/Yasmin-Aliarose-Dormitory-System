/**
 * Booking Wizard — Clean & User-Friendly Version
 * - Consistent Outfit/Inter typography
 * - Icons always left-aligned alongside text (no floating/absolute icons)
 * - No outline/stroke on any button
 * - Proper spacing and visual hierarchy
 */

let holdTimer    = null;
let currentBedId = null;

/* ── Room-card click (from booking.php grid) ─────────────────────── */
async function openBookingModal(roomId, roomNo, floorNo) {
    if (!floorNo) floorNo = Math.floor(parseInt(roomNo) / 100) || 2;
    startBookingWizard(floorNo, { id: roomId, no: roomNo });
}

/* ── Room search filter ───────────────────────────────────────────── */
function filterRooms() {
    const q = document.getElementById('roomSearch').value.toLowerCase();
    document.querySelectorAll('.room-card').forEach(card => {
        const txt = card.querySelector('h3').innerText.toLowerCase();
        card.style.display = txt.includes(q) ? 'block' : 'none';
    });
}

/* ──────────────────────────────────────────────────────────────────
   SHARED STYLES — injected once per wizard open
   ────────────────────────────────────────────────────────────────── */
const SHARED_CSS = `
<style>
  /* ── Fonts & base ─────────────── */
  .swal2-popup * { box-sizing: border-box; }
  .swal2-title   { font-family: 'Outfit', sans-serif !important; font-weight: 900 !important;
                   font-size: 1.6rem !important; color: #1e293b !important;
                   letter-spacing: -0.02em !important; margin-bottom: 0 !important; }

  /* ── Progress bar ─────────────── */
  .wiz-progress { margin-bottom: 2rem; }
  .wiz-steps    { display: flex; justify-content: space-between;
                  align-items: flex-end; margin-bottom: 0.75rem; gap: 4px; }
  .wiz-step     { flex: 1; display: flex; flex-direction: column;
                  align-items: center; gap: 6px; }
  .wiz-dot      { width: 30px; height: 30px; border-radius: 50%;
                  background: #f1f5f9; border: 2px solid #e2e8f0;
                  display: flex; align-items: center; justify-content: center;
                  font-size: 11px; font-weight: 800; color: #94a3b8;
                  font-family: 'Outfit', sans-serif; transition: 0.3s; }
  .wiz-dot.active    { background: #f0fdf4; border-color: #10b981; color: #10b981;
                       transform: scale(1.15);
                       box-shadow: 0 0 0 4px rgba(16,185,129,0.12); }
  .wiz-dot.done      { background: #10b981; border-color: #10b981; color: #fff; }
  .wiz-label    { font-size: 9px; font-weight: 800; text-transform: uppercase;
                  letter-spacing: 0.1em; color: #cbd5e1;
                  font-family: 'Inter', sans-serif; text-align: center; }
  .wiz-label.active { color: #10b981; }
  .wiz-track    { height: 5px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
  .wiz-fill     { height: 100%; background: linear-gradient(90deg,#10b981,#059669);
                  border-radius: 99px; transition: width 0.5s ease; }

  /* ── Section subheading ──────── */
  .form-section-title {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 1rem;
  }
  .form-section-title .bar { width: 4px; height: 22px; border-radius: 99px; }
  .form-section-title h5   { font-family: 'Outfit', sans-serif; font-weight: 800;
                              font-size: 0.75rem; text-transform: uppercase;
                              letter-spacing: 0.12em; color: #475569; margin: 0; }

  /* ── Label ───────────────────── */
  .f-label { display: block; font-family: 'Inter', sans-serif; font-size: 0.7rem;
             font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;
             color: #94a3b8; margin-bottom: 6px; }

  /* ── Input with icon ─────────── */
  .f-input-wrap { display: flex; align-items: center;
                  background: #f8fafc; border: 1.5px solid #e2e8f0;
                  border-radius: 0.85rem; overflow: hidden;
                  transition: border-color 0.2s, box-shadow 0.2s; }
  .f-input-wrap:focus-within { border-color: #10b981;
                               box-shadow: 0 0 0 3px rgba(16,185,129,0.1); background: #fff; }
  .f-input-wrap .f-ico { padding: 0 1rem; font-size: 0.95rem; color: #cbd5e1;
                          flex-shrink: 0; }
  .f-input-wrap .f-ico.green { color: #10b981; }
  .f-input-wrap input { flex: 1; border: none; background: transparent; outline: none;
                         padding: 0.9rem 1rem 0.9rem 0; font-family: 'Inter', sans-serif;
                         font-size: 0.92rem; font-weight: 600; color: #1e293b; min-width: 0; }
  .f-input-wrap input::placeholder { color: #cbd5e1; font-weight: 500; }

  /* ── Grid helpers ────────────── */
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  @media (max-width: 540px) { .two-col { grid-template-columns: 1fr; } }

  /* ── Primary action button ───── */
  .wiz-btn-primary {
    width: 100%; padding: 1rem 1.5rem;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff; border: none; border-radius: 0.85rem;
    font-family: 'Outfit', sans-serif; font-size: 0.9rem; font-weight: 900;
    letter-spacing: 0.05em; text-transform: uppercase;
    cursor: pointer; display: flex; align-items: center;
    justify-content: center; gap: 0.6rem;
    box-shadow: 0 8px 24px rgba(16,185,129,0.22);
    transition: transform 0.2s, box-shadow 0.2s;
  }
  .wiz-btn-primary:hover { transform: translateY(-2px);
                           box-shadow: 0 12px 30px rgba(16,185,129,0.3); }
  .wiz-btn-primary:active { transform: scale(0.98); }

  /* Hide SweetAlert2 default confirm (we use our own buttons mostly) */
  .swal2-confirm, .swal2-cancel {
    font-family: 'Outfit', sans-serif !important;
    font-weight: 800 !important;
    border: none !important;
    box-shadow: none !important;
  }
  .swal2-cancel { background: #f1f5f9 !important; color: #64748b !important; }
  .swal2-cancel:hover { background: #e2e8f0 !important; }
</style>
`;

/* ── Progress HTML helper ─────────────────────────────────────────── */
function getProgressHtml(step) {
    const steps = ['Type', 'Info', 'Room', 'Bed', 'Payment', 'Confirm'];
    const pct   = Math.round(((step - 1) / (steps.length - 1)) * 100);
    return `
        <div class="wiz-progress">
            <div class="wiz-steps">
                ${steps.map((s, i) => {
                    const n     = i + 1;
                    const done  = n < step;
                    const act   = n === step;
                    return `
                    <div class="wiz-step">
                        <div class="wiz-dot ${done ? 'done' : act ? 'active' : ''}">
                            ${done ? '<i class="fas fa-check" style="font-size:10px"></i>' : n}
                        </div>
                        <span class="wiz-label ${act ? 'active' : ''}">${s}</span>
                    </div>`;
                }).join('')}
            </div>
            <div class="wiz-track"><div class="wiz-fill" style="width:${pct}%"></div></div>
        </div>
        ${SHARED_CSS}
    `;
}

/* ══════════════════════════════════════════════════════════════════
   MAIN WIZARD
   ══════════════════════════════════════════════════════════════════ */
async function startBookingWizard(floorNo, preselectedRoom = null) {
    const floorNames = { 2: '2nd', 3: '3rd', 4: '4th' };
    const floorName  = floorNames[floorNo] || floorNo + 'th';

    let category     = null;
    let formValues   = null;
    let selectedRoom = preselectedRoom || null;
    let selectedBedId = null;
    let selectedBedNo = null;
    let finalSelection = null;
    let uploadedReceipt = null;

    /* ── STEP 1: CATEGORY ─────────────────────────────────────────── */
    const step1 = async () => {
        let chosen;
        const { value: cat } = await Swal.fire({
            title: 'Resident Type',
            html: `
                ${getProgressHtml(1)}
                <p style="font-family:'Inter',sans-serif;font-size:0.88rem;color:#64748b;margin:0 0 1.5rem;line-height:1.6;">
                    Select your classification below to begin your <strong style="color:#1e293b">${floorName} Floor</strong> reservation.
                </p>
                <div style="display:grid;gap:0.75rem;">
                    ${[
                        { val:'Reviewer',    icon:'fa-book-open',    label:'Reviewer' },
                        { val:'College',     icon:'fa-user-graduate',label:'College Student' },
                        { val:'High School', icon:'fa-school',       label:'High School' }
                    ].map(c => `
                    <button type="button" class="cat-btn" data-val="${c.val}"
                        style="display:flex;align-items:center;gap:1rem;
                               width:100%;padding:1rem 1.25rem;
                               background:#f8fafc;border:none;border-radius:0.85rem;
                               cursor:pointer;transition:0.2s;text-align:left;">
                        <span style="width:42px;height:42px;background:#f0fdf4;border-radius:0.65rem;
                                     display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas ${c.icon}" style="color:#10b981;font-size:1.1rem;"></i>
                        </span>
                        <span style="font-family:'Outfit',sans-serif;font-weight:800;
                                     font-size:1rem;color:#1e293b;">${c.label}</span>
                        <i class="fas fa-chevron-right" style="margin-left:auto;color:#cbd5e1;font-size:0.75rem;"></i>
                    </button>`).join('')}
                </div>
                <style>
                    .cat-btn:hover { background:#f0fdf4 !important; }
                    .cat-btn:hover i.fa-chevron-right { color:#10b981 !important; }
                </style>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            width: '520px',
            customClass: { popup:'swal-wiz-popup', title:'swal-wiz-title' },
            didOpen: () => {
                Swal.getHtmlContainer().querySelectorAll('.cat-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        chosen = btn.dataset.val;
                        Swal.clickConfirm();
                    });
                });
            },
            preConfirm: () => chosen
        });
        return cat;
    };

    /* ── STEP 2: PERSONAL INFO ────────────────────────────────────── */
    const step2 = async () => {
        const fv = formValues;
        const { value: vals, isDismissed } = await Swal.fire({
            title: 'Your Information',
            html: `
                ${getProgressHtml(2)}

                <div style="text-align:left;font-family:'Inter',sans-serif;">

                    <div class="form-section-title" style="margin-bottom:1rem;">
                        <div class="bar" style="background:#10b981;"></div>
                        <h5>Resident Details</h5>
                    </div>

                    <div style="display:grid;gap:0.9rem;margin-bottom:1.5rem;">
                        <div>
                            <label class="f-label">Full Name</label>
                            <div class="f-input-wrap">
                                <span class="f-ico"><i class="fas fa-user"></i></span>
                                <input id="s-name" type="text" placeholder="Juana Dela Cruz"
                                       value="${fv?.name || ''}">
                            </div>
                        </div>
                        <div>
                            <label class="f-label">Contact Number</label>
                            <div class="f-input-wrap">
                                <span class="f-ico"><i class="fas fa-phone"></i></span>
                                <input id="s-phone" type="tel" placeholder="09XX XXX XXXX"
                                       value="${fv?.phone || ''}">
                            </div>
                        </div>
                    </div>

                    <div class="form-section-title" style="margin-bottom:1rem;">
                        <div class="bar" style="background:#f59e0b;"></div>
                        <h5>Emergency Contact</h5>
                    </div>

                    <div class="two-col" style="margin-bottom:1.75rem;">
                        <div>
                            <label class="f-label">Guardian Name</label>
                            <div class="f-input-wrap">
                                <span class="f-ico"><i class="fas fa-user-shield"></i></span>
                                <input id="s-guardian" type="text" placeholder="Parent / Guardian"
                                       value="${fv?.guardian || ''}">
                            </div>
                        </div>
                        <div>
                            <label class="f-label">Guardian Contact</label>
                            <div class="f-input-wrap">
                                <span class="f-ico"><i class="fas fa-phone-alt"></i></span>
                                <input id="s-gphone" type="tel" placeholder="09XX XXX XXXX"
                                       value="${fv?.guardianPhone || ''}">
                            </div>
                        </div>
                    </div>

                    <button id="s2-next" class="wiz-btn-primary">
                        Next — Choose Room
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: '← Back',
            width: '600px',
            customClass: { popup:'swal-wiz-popup', title:'swal-wiz-title' },
            didOpen: () => {
                document.getElementById('s2-next').addEventListener('click', () => Swal.clickConfirm());
            },
            preConfirm: () => {
                const name         = document.getElementById('s-name').value.trim();
                const phone        = document.getElementById('s-phone').value.trim();
                const guardian     = document.getElementById('s-guardian').value.trim();
                const guardianPhone= document.getElementById('s-gphone').value.trim();
                if (!name || !phone || !guardian || !guardianPhone) {
                    Swal.showValidationMessage('Please fill in all fields before continuing.');
                    return false;
                }
                return { name, phone, guardian, guardianPhone };
            }
        });
        if (isDismissed && Swal.getDismissReason() === 'cancel') return 'BACK';
        return vals;
    };

    /* ── STEP 3: ROOM SELECTION ───────────────────────────────────── */
    const step3 = async () => {
        Swal.fire({ title: 'Loading Rooms…', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        const rooms = await (await fetch(`api/room_api.php?action=floor_rooms&floor_no=${floorNo}`)).json();

        const { value: room, isDismissed } = await Swal.fire({
            title: 'Choose a Room',
            html: `
                ${getProgressHtml(3)}
                <p style="font-family:'Inter',sans-serif;font-size:0.85rem;color:#64748b;margin:0 0 1.25rem;text-align:center;">
                    Available rooms on the <strong style="color:#1e293b">${floorName} Floor</strong>
                </p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));
                            gap:0.85rem;max-height:400px;overflow-y:auto;padding:4px 2px;">
                    ${rooms.map(r => {
                        const avail  = r.total_beds - r.occupied_count - r.reserved_count;
                        const isFull = avail <= 0 || r.status === 'Full';
                        const pct    = r.total_beds > 0 ? Math.round((r.occupied_count / r.total_beds) * 100) : 0;
                        return `
                        <button type="button" class="rm-pick-btn ${isFull?'rm-full':''}"
                                data-id="${r.id}" data-no="${r.room_no}" ${isFull?'disabled':''}>
                            <div style="font-family:'Outfit',sans-serif;font-weight:900;
                                        font-size:1rem;color:${isFull?'#94a3b8':'#1e293b'};margin-bottom:4px;">
                                Room ${r.room_no}
                            </div>
                            <div style="font-size:0.72rem;font-weight:700;margin-bottom:8px;
                                        color:${isFull?'#ef4444':'#10b981'};">
                                ${isFull
                                    ? '<i class="fas fa-lock" style="margin-right:3px"></i> Full'
                                    : `${avail} slot${avail===1?'':'s'} left`}
                            </div>
                            <div style="height:4px;background:#f1f5f9;border-radius:99px;overflow:hidden;margin-bottom:10px;">
                                <div style="height:100%;width:${pct}%;background:${isFull?'#ef4444':'#10b981'};border-radius:99px;"></div>
                            </div>
                            <div style="font-size:0.7rem;font-weight:900;text-transform:uppercase;letter-spacing:.06em;
                                        padding:0.45rem;border-radius:0.55rem;
                                        background:${isFull?'#f8fafc':'#10b981'};
                                        color:${isFull?'#94a3b8':'#fff'};">
                                ${isFull ? 'Unavailable' : 'Select'}
                            </div>
                        </button>`;
                    }).join('')}
                </div>
                <style>
                    .rm-pick-btn { background:#fff;border:none;border-radius:1rem;padding:1rem;
                                   cursor:pointer;transition:0.25s;text-align:center; }
                    .rm-pick-btn:not(.rm-full):hover { background:#f0fdf4;
                        box-shadow:0 8px 24px rgba(16,185,129,.12);transform:translateY(-2px); }
                    .rm-pick-btn:not(.rm-full):has(+) { }
                    .rm-full { opacity:0.55;cursor:not-allowed; }
                </style>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: '← Back',
            width: '680px',
            customClass: { popup:'swal-wiz-popup', title:'swal-wiz-title' },
            didOpen: () => {
                Swal.getHtmlContainer().querySelectorAll('.rm-pick-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        selectedRoom = { id: btn.dataset.id, no: btn.dataset.no };
                        Swal.clickConfirm();
                    });
                });
            }
        });
        if (isDismissed && Swal.getDismissReason() === 'cancel') return 'BACK';
        return room ? selectedRoom : null;
    };

    /* ── STEP 4: BED SELECTION ────────────────────────────────────── */
    const step4 = async () => {
        Swal.fire({ title: 'Loading Beds…', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        const beds = await (await fetch(`api/room_api.php?action=beds&room_id=${selectedRoom.id}`)).json();

        // Pair into double-deck groups
        const decks = [];
        for (let i = 0; i < beds.length; i += 2) decks.push([beds[i], beds[i+1]]);

        const { value: bed, isDismissed } = await Swal.fire({
            title: `Room ${selectedRoom.no} (${floorName} Floor) — Beds`,
            html: `
                ${getProgressHtml(4)}
                <p style="font-family:'Inter',sans-serif;font-size:0.85rem;color:#64748b;margin:0 0 1.25rem;text-align:center;">
                    Select your preferred bedspace below.
                </p>
                <div style="display:grid;gap:1rem;max-height:420px;overflow-y:auto;padding:4px 2px;">
                    ${decks.map((deck, di) => {
                        const label = String.fromCharCode(65 + di);
                        return `
                        <div style="background:#f8fafc;border-radius:1rem;padding:1rem;">
                            <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.85rem;">
                                <i class="fas fa-layer-group" style="color:#10b981;font-size:0.85rem;"></i>
                                <span style="font-family:'Outfit',sans-serif;font-weight:900;
                                             font-size:0.72rem;text-transform:uppercase;
                                             letter-spacing:0.12em;color:#64748b;">Deck ${label}</span>
                            </div>
                            <div style="display:grid;gap:0.65rem;">
                                ${deck.map((b, bi) => {
                                    if (!b) return '';
                                    const isLower    = bi === 0;
                                    const st         = b.status.toLowerCase();
                                    const isDisabled = st === 'occupied' || st === 'reserved';
                                    return `
                                    <button type="button" class="bed-pick-btn ${isDisabled?'bed-dis':''}"
                                            data-id="${b.id}" data-no="${b.bed_no}" ${isDisabled?'disabled':''}>
                                        <div style="display:flex;align-items:center;gap:0.85rem;width:100%;">
                                            <span style="width:36px;height:36px;border-radius:0.6rem;flex-shrink:0;
                                                         display:flex;align-items:center;justify-content:center;
                                                         background:${isDisabled?'#f1f5f9':'#f0fdf4'};">
                                                <i class="fas ${isLower?'fa-arrow-down':'fa-arrow-up'}"
                                                   style="font-size:0.75rem;color:${isDisabled?'#cbd5e1':'#10b981'};"></i>
                                            </span>
                                            <div style="text-align:left;flex:1;">
                                                <div style="font-family:'Outfit',sans-serif;font-weight:900;
                                                            font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;
                                                            color:${isDisabled?'#cbd5e1':'#10b981'};margin-bottom:1px;">
                                                    ${isLower?'Lower Bunk':'Upper Bunk'}
                                                </div>
                                                <div style="font-family:'Outfit',sans-serif;font-weight:900;font-size:0.95rem;
                                                            color:${isDisabled?'#94a3b8':'#1e293b'};">
                                                    Slot ${b.bed_no}
                                                </div>
                                            </div>
                                            <span style="font-size:0.68rem;font-weight:900;text-transform:uppercase;
                                                         letter-spacing:0.06em;padding:0.35rem 0.7rem;border-radius:0.5rem;
                                                         background:${isDisabled?'#f1f5f9':'#10b981'};
                                                         color:${isDisabled?'#94a3b8':'#fff'};">
                                                ${isDisabled ? st : 'Select'}
                                            </span>
                                        </div>
                                    </button>`;
                                }).join('')}
                            </div>
                        </div>`;
                    }).join('')}
                </div>
                <style>
                    .bed-pick-btn { width:100%;background:#fff;border:none;border-radius:0.85rem;
                                    padding:0.85rem 1rem;cursor:pointer;transition:0.2s;}
                    .bed-pick-btn:not(.bed-dis):hover { background:#f0fdf4;
                        box-shadow:0 6px 18px rgba(16,185,129,.1);transform:translateY(-1px); }
                    .bed-dis { opacity:0.6;cursor:not-allowed; }
                </style>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: '← Back',
            width: '560px',
            customClass: { popup:'swal-wiz-popup', title:'swal-wiz-title' },
            didOpen: () => {
                Swal.getHtmlContainer().querySelectorAll('.bed-pick-btn').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const bid = btn.dataset.id;
                        const bno = btn.dataset.no;
                        fd.append('action', 'reserve');
                        const res  = await fetch('api/booking_api.php', { method:'POST', body:fd });
                        const data = await res.json();
                        if (data.success) {
                            selectedBedId = bid;
                            selectedBedNo = bno;
                            currentBedId  = bid;
                            Swal.clickConfirm();
                        } else {
                            Swal.showValidationMessage(data.error || 'Unable to reserve. Try another bed.');
                        }
                    });
                });
            }
        });
        if (isDismissed && Swal.getDismissReason() === 'cancel') return preselectedRoom ? 'CANCEL' : 'BACK';
        return bed ? { id: selectedBedId, no: selectedBedNo } : null;
    };

    /* ── STEP 5: PAYMENT METHOD ───────────────────────────────────── */
    const step5 = async () => {
        const { value: sel, isDismissed } = await Swal.fire({
            title: 'Payment Method',
            html: `
                ${getProgressHtml(5)}
                <p style="font-family:'Inter',sans-serif;font-size:0.85rem;color:#64748b;margin:0 0 1.5rem;text-align:center;">
                    How would you like to settle your reservation fee?
                </p>
                <div style="display:grid;gap:0.85rem;">
                    ${[
                        { val:'CashIn', icon:'fa-hand-holding-usd', iconBg:'#f0fdf4', iconClr:'#10b981',
                          title:'Walk-In Payment', desc:'Visit our office within 24 hours.' },
                        { val:'GCash', icon:'fa-mobile-alt', iconBg:'#eff6ff', iconClr:'#2563eb',
                          title:'GCash Quick Pay', desc:'Scan and pay your reservation fee securely via GCash.' }
                    ].map(p => `
                    <button type="button" class="pay-btn" data-val="${p.val}"
                        style="display:flex;align-items:center;gap:1rem;
                               background:#f8fafc;border:none;border-radius:0.9rem;
                               padding:1.1rem 1.25rem;cursor:pointer;transition:0.2s;text-align:left;width:100%;">
                        <span style="width:46px;height:46px;background:${p.iconBg};border-radius:0.7rem;
                                     display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas ${p.icon}" style="font-size:1.2rem;color:${p.iconClr};"></i>
                        </span>
                        <div style="flex:1;">
                            <div style="font-family:'Outfit',sans-serif;font-weight:900;
                                        font-size:0.95rem;color:#1e293b;margin-bottom:2px;">${p.title}</div>
                            <div style="font-family:'Inter',sans-serif;font-size:0.78rem;
                                        color:#94a3b8;font-weight:600;">${p.desc}</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color:#cbd5e1;font-size:0.75rem;"></i>
                    </button>`).join('')}
                </div>
                <style>.pay-btn:hover { background:#f0fdf4 !important; }</style>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: '← Back',
            width: '520px',
            customClass: { popup:'swal-wiz-popup', title:'swal-wiz-title' },
            didOpen: () => {
                Swal.getHtmlContainer().querySelectorAll('.pay-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        finalSelection = btn.dataset.val;
                        Swal.clickConfirm();
                    });
                });
            }
        });
        if (isDismissed && Swal.getDismissReason() === 'cancel') return 'BACK';
        return sel ? finalSelection : null;
    };

    /* ── STEP 6: CONFIRM SUMMARY ──────────────────────────────────── */
    const step6 = async () => {
        const methodLabel = finalSelection === 'GCash' ? 'GCash Transfer' : 'Walk-In (On-Site)';
        const { isConfirmed, isDismissed } = await Swal.fire({
            title: 'Review & Confirm',
            html: `
                ${getProgressHtml(6)}
                <div style="font-family:'Inter',sans-serif;text-align:left;">
                    <div style="background:#f8fafc;border-radius:1rem;overflow:hidden;
                                border:1px solid #e2e8f0;margin-bottom:1.25rem;">
                        ${[
                            { icon:'fa-user',         label:'Resident',    val: formValues.name },
                            { icon:'fa-phone',        label:'Contact',     val: formValues.phone },
                            { icon:'fa-door-open',    label:'Room & Bed',  val: `Room ${selectedRoom.no} — Bed ${selectedBedNo}` },
                            { icon:'fa-tag',          label:'Category',    val: category },
                            { icon:'fa-credit-card',  label:'Payment',     val: methodLabel }
                        ].map((row, i, arr) => `
                        <div style="display:flex;align-items:center;gap:0.85rem;padding:0.9rem 1rem;
                                    ${i < arr.length-1 ? 'border-bottom:1px solid #f1f5f9' : ''};">
                            <span style="width:30px;text-align:center;flex-shrink:0;">
                                <i class="fas ${row.icon}" style="color:#10b981;font-size:0.9rem;"></i>
                            </span>
                            <span style="flex:1;font-size:0.72rem;font-weight:700;text-transform:uppercase;
                                         letter-spacing:0.08em;color:#94a3b8;">${row.label}</span>
                            <span style="font-weight:800;font-size:0.88rem;color:#1e293b;text-align:right;">${row.val}</span>
                        </div>`).join('')}
                    </div>
                    ${finalSelection === 'GCash' ? `
                    <div style="margin-bottom:1.25rem;display:flex;flex-direction:column;align-items:center;background:#fff;border:1.5px dashed #10b981;border-radius:1rem;padding:1.25rem;">
                        <span style="font-weight:800;font-size:0.75rem;color:#10b981;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:0.75rem;">Scan to Pay</span>
                        <img src="assets/images/gcash.png" alt="GCash QR" style="width:140px;height:140px;border-radius:0.75rem;margin-bottom:0.5rem;box-shadow:0 4px 12px rgba(0,0,0,0.05);">
                        <p style="font-size:0.72rem;color:#64748b;margin:0 0 1rem;text-align:center;">Yasmin <strong style="color:#1e293b;">0912 345 6789</strong></p>
                        <div style="width:100%;text-align:left;border-top:1px solid #f1f5f9;padding-top:1rem;">
                            <label style="display:block;font-family:'Inter',sans-serif;font-size:0.75rem;font-weight:800;text-transform:uppercase;color:#1e293b;margin-bottom:0.5rem;">Upload Screenshot Proof *</label>
                            <input type="file" id="paymentScreenshot" accept="image/*" style="width:100%;padding:0.65rem;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:0.75rem;font-size:0.8rem;outline:none;" required>
                        </div>
                    </div>
                    ` : ''}
                    <p style="font-size:0.78rem;color:#94a3b8;text-align:center;line-height:1.5;">
                        <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                        By confirming, your bed will be secured pending payment verification.
                    </p>
                </div>
            `,
            confirmButtonText: 'Confirm Reservation',
            confirmButtonColor: '#10b981',
            showCancelButton: true,
            cancelButtonText: '← Back',
            width: '520px',
            customClass: { popup:'swal-wiz-popup', title:'swal-wiz-title' },
            preConfirm: () => {
                if (finalSelection === 'GCash') {
                    const fileInput = document.getElementById('paymentScreenshot');
                    if (!fileInput || fileInput.files.length === 0) {
                        Swal.showValidationMessage('Please upload your GCash payment screenshot to secure the booking.');
                        return false;
                    }
                    uploadedReceipt = fileInput.files[0];
                }
                return true;
            }
        });
        if (isDismissed && Swal.getDismissReason() === 'cancel') return 'BACK';
        return isConfirmed;
    };

    /* ══ FLOW CONTROL ══════════════════════════════════════════════ */
    let step = preselectedRoom ? 1 : 1;

    while (step <= 6) {
        let result;
        if (step === 1) {
            result = await step1();
            if (!result) return;
            category = result; step = 2;
        } else if (step === 2) {
            result = await step2();
            if (result === 'BACK') { step = 1; }
            else if (!result) return;
            else { formValues = result; step = preselectedRoom ? 4 : 3; }
        } else if (step === 3) {
            result = await step3();
            if (result === 'BACK') { step = 2; }
            else if (!result) return;
            else { selectedRoom = result; step = 4; }
        } else if (step === 4) {
            result = await step4();
            if (result === 'BACK') { step = preselectedRoom ? 2 : 3; }
            else if (result === 'CANCEL' || !result) return;
            else { step = 5; }
        } else if (step === 5) {
            result = await step5();
            if (result === 'BACK') { step = 4; }
            else if (!result) return;
            else { step = 6; }
        } else if (step === 6) {
            result = await step6();
            if (result === 'BACK') { step = 5; }
            else if (result) break;
            else return;
        }
    }

    /* ══ SUBMIT ════════════════════════════════════════════════════ */
    Swal.fire({ title: 'Saving Reservation…',
                text: 'Please wait while we secure your slot.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading() });

    try {
        const payload = new FormData();
        payload.append('name', formValues.name);
        payload.append('phone', formValues.phone);
        payload.append('guardian', formValues.guardian);
        payload.append('guardianPhone', formValues.guardianPhone);
        payload.append('category', category);
        payload.append('paymentMethod', finalSelection);
        payload.append('bedId', currentBedId);
        
        if (finalSelection === 'GCash' && uploadedReceipt) {
            payload.append('receipt', uploadedReceipt);
        }

        payload.append('action', 'finalize');
        const res = await fetch('api/booking_api.php', {
            method: 'POST',
            body: payload
        });
        const data = await res.json();

        if (data.success) {
            Swal.fire({
                title: 'Reservation Confirmed!',
                icon: 'success',
                html: `
                    <div style="font-family:'Inter',sans-serif;text-align:center;padding:0.5rem 0;">
                        <p style="font-size:0.9rem;color:#64748b;margin-bottom:1.5rem;line-height:1.7;">
                            Your slot has been secured. Please keep your reference number safe.
                        </p>

                        <div style="display:flex;justify-content:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
                            <div style="background:#f0fdf4;border-radius:0.85rem;padding:0.9rem 1.5rem;min-width:140px;">
                                <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;
                                            letter-spacing:0.1em;color:#6ee7b7;margin-bottom:4px;">Reference</div>
                                <div style="font-family:'Outfit',sans-serif;font-weight:900;font-size:1.3rem;
                                            color:#10b981;letter-spacing:0.05em;">${data.booking_ref}</div>
                            </div>
                            <div style="background:#f8fafc;border-radius:0.85rem;padding:0.9rem 1.5rem;min-width:140px;">
                                <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;
                                            letter-spacing:0.1em;color:#94a3b8;margin-bottom:4px;">Placement</div>
                                <div style="font-family:'Outfit',sans-serif;font-weight:900;font-size:1.3rem;
                                            color:#1e293b;">Rm ${selectedRoom.no} · Bed ${selectedBedNo}</div>
                            </div>
                        </div>

                        <div style="background:#f8fafc;border-radius:0.85rem;padding:1rem 1.25rem;text-align:left;">
                            <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.6rem;">
                                <i class="fas fa-wallet" style="color:#10b981;"></i>
                                <span style="font-family:'Outfit',sans-serif;font-weight:900;font-size:0.78rem;
                                             text-transform:uppercase;letter-spacing:0.08em;color:#1e293b;">
                                    Payment Instructions
                                </span>
                            </div>
                            ${finalSelection === 'GCash' ? `
                            <div style="display:flex;gap:1.25rem;align-items:center;background:#fff;border:1.5px solid #e2e8f0;border-radius:0.75rem;padding:0.85rem;">
                                <img src="assets/images/gcash.png" alt="GCash QR Code" style="width:75px;height:75px;border-radius:0.5rem;flex-shrink:0;">
                                <p style="font-size:0.82rem;color:#64748b;margin:0;line-height:1.6;">
                                    Please scan this QR code or send payment to <strong style="color:#10b981">0912 345 6789</strong> (Yasmin).<br>Take a screenshot as proof of transaction.
                                </p>
                            </div>
                            ` : `
                            <p style="font-size:0.82rem;color:#64748b;margin:0;line-height:1.6;">
                                Visit our admin office within <strong>24 hours</strong> to settle your initial deposit and present a valid ID.
                            </p>
                            `}
                        </div>
                    </div>
                `,
                confirmButtonText: 'Back to Home',
                confirmButtonColor: '#10b981',
                width: '560px',
                customClass: { popup:'swal-wiz-popup', title:'swal-wiz-title' }
            }).then(() => window.location.href = `index.html?status=success&ref=${data.booking_ref}`);
        } else {
            Swal.fire({ title: 'Something went wrong',
                        text: data.error || 'Please try again.',
                        icon: 'error', confirmButtonColor: '#10b981' });
        }
    } catch(e) {
        Swal.fire('Server Error', 'Could not connect to the server.', 'error');
    }
}
