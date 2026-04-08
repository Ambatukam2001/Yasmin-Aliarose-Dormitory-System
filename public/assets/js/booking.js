/**
 * booking.js
 * Flow: Bed picker → Details form → Receipt upload (GCash only) → Confirm → Success
 */

/* ═══════════════════════════════════════════════════════
   BASE PATH — resolves API URLs regardless of subfolder depth
   ═══════════════════════════════════════════════════════ */
const BK_BASE = (() => {
    // Get the path of the current page and strip the filename
    const path = window.location.pathname.replace(/\/[^/]*$/, '/');
    return window.location.origin + path;
})();

/* ═══════════════════════════════════════════════════════
   MODAL INJECTION
   ═══════════════════════════════════════════════════════ */
(function injectModal() {
    const html = `
    <div id="bookingOverlay" class="bk-overlay">
        <div class="bk-modal" role="dialog" aria-modal="true" aria-labelledby="bkModalTitle">

            <!-- Header -->
            <div class="bk-modal-header">
                <div>
                    <p class="bk-modal-floor" id="bkModalFloor"></p>
                    <h2 class="bk-modal-title" id="bkModalTitle">Room —</h2>
                </div>
                <button class="bk-close-btn" id="bkCloseBtn" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Step indicator -->
            <div class="bk-steps" id="bkStepsBar">
                <div class="bk-step bk-step--active" id="bkStep1Ind">
                    <span class="bk-step-num">1</span>
                    <span class="bk-step-label">Choose Bed</span>
                </div>
                <div class="bk-step-line"></div>
                <div class="bk-step" id="bkStep2Ind">
                    <span class="bk-step-num">2</span>
                    <span class="bk-step-label">Details</span>
                </div>
                <div class="bk-step-line"></div>
                <div class="bk-step" id="bkStep3Ind">
                    <span class="bk-step-num">3</span>
                    <span class="bk-step-label">Receipt</span>
                </div>
                <div class="bk-step-line"></div>
                <div class="bk-step" id="bkStep4Ind">
                    <span class="bk-step-num">4</span>
                    <span class="bk-step-label">Confirm</span>
                </div>
            </div>

            <!-- ── STEP 1: Bed picker ── -->
            <div id="bkStepBeds" class="bk-step-panel">
                <p class="bk-step-hint">Select an available bed to continue.</p>
                <div class="bk-bed-grid" id="bkBedGrid">
                    <div class="bk-bed-loading"><i class="fas fa-spinner fa-spin"></i> Loading beds…</div>
                </div>
                <div class="bk-modal-footer">
                    <button class="bk-btn bk-btn--ghost" id="bkCancelBtn">Cancel</button>
                    <button class="bk-btn bk-btn--primary" id="bkNextToForm" onclick="goToForm()" disabled>
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ── STEP 2: Details form ── -->
            <div id="bkStepForm" class="bk-step-panel" style="display:none">
                <p id="bkFormError" class="bk-form-error" style="display:none"></p>
                <form id="bkForm" onsubmit="return false;">
                    <div class="bk-form-row">
                        <div class="bk-form-group">
                            <label>Full Name <span class="req">*</span></label>
                            <input type="text" id="bkFullName" placeholder="e.g. Juan dela Cruz" required>
                        </div>
                        <div class="bk-form-group">
                            <label>Category <span class="req">*</span></label>
                            <select id="bkCategory" required>
                                <option value="">— Select —</option>
                                <option value="Reviewer">Reviewer</option>
                                <option value="College">College</option>
                                <option value="High School">High School</option>
                            </select>
                        </div>
                    </div>
                    <div class="bk-form-row">
                        <div class="bk-form-group">
                            <label>School Name</label>
                            <input type="text" id="bkSchool" placeholder="e.g. University of the Philippines">
                        </div>
                        <div class="bk-form-group">
                            <label>Contact Number <span class="req">*</span></label>
                            <input type="tel" id="bkContact" placeholder="09XXXXXXXXX" required>
                        </div>
                    </div>
                    <div class="bk-form-row">
                        <div class="bk-form-group">
                            <label>Guardian Name <span class="req">*</span></label>
                            <input type="text" id="bkGuardian" placeholder="Parent / Guardian" required>
                        </div>
                        <div class="bk-form-group">
                            <label>Guardian Contact <span class="req">*</span></label>
                            <input type="tel" id="bkGuardianContact" placeholder="09XXXXXXXXX" required>
                        </div>
                    </div>
                    <div class="bk-form-group" style="margin-bottom:.85rem">
                        <label>Payment Method <span class="req">*</span></label>
                        <div class="bk-pay-opts">
                            <label class="bk-pay-opt">
                                <input type="radio" name="bkPayMethod" value="GCash Online" onchange="onPayMethodChange('GCash Online')">
                                <span><i class="fas fa-mobile-alt"></i> GCash Online</span>
                            </label>
                            <label class="bk-pay-opt">
                                <input type="radio" name="bkPayMethod" value="Cash In" onchange="onPayMethodChange('Cash In')">
                                <span><i class="fas fa-money-bill-wave"></i> Cash In</span>
                            </label>
                        </div>
                    </div>
                </form>
                <div class="bk-modal-footer">
                    <button class="bk-btn bk-btn--ghost" onclick="goToBeds()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="bk-btn bk-btn--primary" onclick="goToReceipt()">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ── STEP 3: Receipt upload (GCash only) ── -->
            <div id="bkStepReceipt" class="bk-step-panel" style="display:none">
                <div id="bkReceiptGcash">
                    <p class="bk-step-hint">Upload a screenshot of your GCash payment receipt.</p>
                    <div class="bk-gcash-info">
                        <div class="bk-gcash-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div>
                            <p class="bk-gcash-label">Send ₱${parseInt(DormConfig.bed_price).toLocaleString()} to</p>
                            <p class="bk-gcash-number">${DormConfig.gcash_number}</p>
                            <p class="bk-gcash-name">${DormConfig.site_name}</p>
                        </div>
                    </div>
                    <div class="bk-dropzone" id="bkDropzone" onclick="document.getElementById('bkReceiptFile').click()">
                        <div class="bk-dropzone-inner" id="bkDropzoneInner">
                            <i class="fas fa-cloud-upload-alt bk-drop-icon"></i>
                            <p class="bk-drop-label">Click or drag & drop your receipt screenshot</p>
                            <p class="bk-drop-hint">PNG, JPG, or PDF · max 5 MB</p>
                        </div>
                        <img id="bkReceiptPreview" class="bk-receipt-preview" style="display:none" alt="Receipt preview">
                    </div>
                    <input type="file" id="bkReceiptFile" accept="image/png,image/jpeg,image/jpg,application/pdf"
                           style="display:none" onchange="onReceiptSelected(this)">
                    <p id="bkReceiptError" class="bk-form-error" style="display:none"></p>
                </div>

                <!-- Cash in — no upload needed -->
                <div id="bkReceiptCash" style="display:none">
                    <div class="bk-cash-notice">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Cash Payment</strong>
                            <p>No receipt needed. Please pay ₱${DormState.data.settings.bed_price.toLocaleString()}.00 upon check-in. Your booking will be confirmed by the admin.</p>
                        </div>
                    </div>
                    <div class="bk-map-preview" style="border-radius:1rem; overflow:hidden; border:1.5px solid #e2e8f0; margin-top:1rem; height:200px;">
                        <iframe width="100%" height="100%" frameborder="0" style="border:0;" 
                            src="https://maps.google.com/maps?q=8.222817,124.240125&t=&z=16&ie=UTF8&iwloc=&output=embed">
                        </iframe>
                    </div>
                </div>

                <div class="bk-modal-footer">
                    <button class="bk-btn bk-btn--ghost" onclick="goToForm()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="bk-btn bk-btn--primary" onclick="goToConfirm()">
                        Review <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ── STEP 4: Confirm ── -->
            <div id="bkStepConfirm" class="bk-step-panel" style="display:none">
                <div class="bk-confirm-card" id="bkConfirmCard"></div>
                <div class="bk-modal-footer">
                    <button class="bk-btn bk-btn--ghost" onclick="goToReceipt()">
                        <i class="fas fa-arrow-left"></i> Edit
                    </button>
                    <button class="bk-btn bk-btn--success" id="bkSubmitBtn" onclick="submitBooking()">
                        <i class="fas fa-check"></i> Confirm Booking
                    </button>
                </div>
            </div>

            <!-- ── SUCCESS ── -->
            <div id="bkStepSuccess" class="bk-step-panel bk-success-panel" style="display:none">
                <div class="bk-success-icon"><i class="fas fa-check-circle"></i></div>
                <h3>Booking Submitted!</h3>
                <p>Your reservation is <strong>pending confirmation</strong> by the admin.</p>
                <p class="bk-ref-label">Booking Reference</p>
                <p class="bk-ref-code" id="bkRefCode">—</p>
                <button class="bk-btn bk-btn--primary" id="bkDoneBtn" style="margin-top:1.5rem">
                    Done
                </button>
            </div>

        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', html);

    // ── Close handlers (overlay click, X button, Cancel, Done) ──
    const overlay = document.getElementById('bookingOverlay');
    overlay.addEventListener('click', e => {
        if (e.target === overlay) _closeModal();
    });
    document.getElementById('bkCloseBtn').addEventListener('click',  _closeModal);
    document.getElementById('bkCancelBtn').addEventListener('click', _closeModal);
    document.getElementById('bkDoneBtn').addEventListener('click',   _closeModal);

    // ── Drag-and-drop on dropzone ──
    const dz = document.getElementById('bkDropzone');
    dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('bk-dropzone--over'); });
    dz.addEventListener('dragleave', ()  => dz.classList.remove('bk-dropzone--over'));
    dz.addEventListener('drop', e => {
        e.preventDefault();
        dz.classList.remove('bk-dropzone--over');
        const file = e.dataTransfer.files[0];
        if (file) applyReceiptFile(file);
    });

    injectStyles();
})();

/* ═══════════════════════════════════════════════════════
   STATE
   ═══════════════════════════════════════════════════════ */
let _roomId        = null;
let _roomNo        = null;
let _floorNo       = null;
let _selectedBedId = null;
let _selectedBedNo = null;
let _payMethod     = null;
let _receiptFile   = null;
let _receiptData   = null; // Base64 string for storage

/* ═══════════════════════════════════════════════════════
   OPEN / CLOSE
   ═══════════════════════════════════════════════════════ */
function openBookingModal(roomId, roomNo, floorNo) {
    _roomId = roomId; _roomNo = roomNo; _floorNo = floorNo;
    _selectedBedId = null; _selectedBedNo = null;
    _payMethod = null; _receiptFile = null;

    // Reset form fields
    ['bkFullName','bkSchool','bkContact','bkGuardian','bkGuardianContact'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const cat = document.getElementById('bkCategory');
    if (cat) cat.value = '';
    document.querySelectorAll('input[name="bkPayMethod"]').forEach(r => r.checked = false);

    resetReceipt();
    showPanel('bkStepBeds');
    setStepActive(1);
    document.getElementById('bkModalTitle').textContent = `Room ${roomNo}`;
    document.getElementById('bkModalFloor').textContent =
        `${floorNo}${floorNo==2?'nd':floorNo==3?'rd':'th'} Floor`;
    document.getElementById('bkNextToForm').disabled = true;

    document.getElementById('bookingOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    loadBeds(roomId);
}

function _closeModal() {
    document.getElementById('bookingOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

// Keep old name working in case anything calls it
function closeBookingModal(e) {
    if (e && e.target !== document.getElementById('bookingOverlay')) return;
    _closeModal();
}

/* ═══════════════════════════════════════════════════════
   STEP HELPERS
   ═══════════════════════════════════════════════════════ */
const PANELS = ['bkStepBeds','bkStepForm','bkStepReceipt','bkStepConfirm','bkStepSuccess'];

function showPanel(id) {
    PANELS.forEach(p => {
        document.getElementById(p).style.display = p === id ? '' : 'none';
    });
    // Scroll modal to top on panel change
    const modal = document.querySelector('.bk-modal');
    if (modal) modal.scrollTop = 0;
}

function setStepActive(step) {
    [1,2,3,4].forEach(n => {
        const el = document.getElementById(`bkStep${n}Ind`);
        if (!el) return;
        el.classList.toggle('bk-step--active',    n === step);
        el.classList.toggle('bk-step--completed', n < step);
    });
}

/* ═══════════════════════════════════════════════════════
   STEP 1 — Beds
   ═══════════════════════════════════════════════════════ */
async function loadBeds(roomId) {
    const grid = document.getElementById('bkBedGrid');
    grid.innerHTML = '<div class="bk-bed-loading"><i class="fas fa-spinner fa-spin"></i> Loading beds…</div>';
    try {
        const res = await fetch(`api/room_api.php?action=room_beds&room_id=${roomId}`);
        const beds = await res.json();
        renderBeds(beds);
    } catch (e) {
        grid.innerHTML = `<p class="bk-bed-error"><i class="fas fa-exclamation-circle"></i> Failed to load beds (${e.message}).</p>`;
    }
}

function renderBeds(beds) {
    const grid = document.getElementById('bkBedGrid');
    if (!beds.length) {
        grid.innerHTML = '<p class="bk-bed-error">No beds found in this room.</p>';
        return;
    }
    // Group beds by Bunk (Beds 1&2 = Bunk A, 3&4 = Bunk B, etc.)
    const bunks = [];
    for(let i=0; i<beds.length; i+=2) {
        bunks.push(beds.slice(i, i+2));
    }

    grid.innerHTML = bunks.map((pair, idx) => `
        <div class="bk-bunk-unit" style="display:flex; flex-direction:column; gap:0.5rem; background:#f1f5f9; padding:0.75rem; border-radius:1rem; border:1px solid #e2e8f0;">
            <div class="bk-bunk-label" style="font-size:0.6rem; font-weight:800; text-transform:uppercase; color:#94a3b8; margin-bottom:0.25rem;">Bunk ${String.fromCharCode(65 + idx)}</div>
            ${pair.sort((a,b) => b.bed_no - a.bed_no).map(bed => {
                const isAvail = bed.status === 'Available';
                const isRes   = bed.status === 'Reserved';
                const cls     = isAvail ? 'bk-bed--avail' : isRes ? 'bk-bed--res' : 'bk-bed--occ';
                const icon    = isAvail ? 'fa-bed' : isRes ? 'fa-clock' : 'fa-lock';
                const label   = isAvail ? 'Available' : isRes ? 'Reserved' : 'Occupied';
                const deck    = bed.bed_no % 2 === 0 ? 'Upper Deck' : 'Lower Deck';
                return `
                <div class="bk-bed-chip ${cls} ${isAvail ? 'bk-bed--selectable' : ''}"
                     style="margin-bottom:0; width:100%;"
                     id="bed-opt-${bed.id}"
                     ${isAvail ? `onclick="selectBed(${bed.id}, ${bed.bed_no}, this)"` : ''}>
                    <div style="display:flex; align-items:center; gap:0.75rem; text-align:left; width:100%;">
                        <i class="fas ${icon} bk-bed-icon" style="font-size:1rem;"></i>
                        <div style="flex:1">
                            <div class="bk-bed-num" style="font-size:0.8rem;">${deck}</div>
                            <div class="bk-bed-status" style="font-size:0.55rem; color:inherit;">${label}</div>
                        </div>
                    </div>
                </div>`;
            }).join('')}
        </div>
    `).join('');
}

function selectBed(bedId, bedNo, el) {
    document.querySelectorAll('.bk-bed-chip.bk-bed--selected').forEach(c => c.classList.remove('bk-bed--selected'));
    el.classList.add('bk-bed--selected');
    _selectedBedId = bedId;
    _selectedBedNo = bedNo;
    document.getElementById('bkNextToForm').disabled = false;
}

/* ═══════════════════════════════════════════════════════
   STEP 2 — Form
   ═══════════════════════════════════════════════════════ */
function goToForm() {
    if (!_selectedBedId) return;
    showPanel('bkStepForm');
    setStepActive(2);
}

function goToBeds() {
    showPanel('bkStepBeds');
    setStepActive(1);
}

function onPayMethodChange(method) {
    _payMethod = method;
}

function validateForm() {
    const name     = document.getElementById('bkFullName').value.trim();
    const cat      = document.getElementById('bkCategory').value;
    const contact  = document.getElementById('bkContact').value.trim();
    const guardian = document.getElementById('bkGuardian').value.trim();
    const gContact = document.getElementById('bkGuardianContact').value.trim();
    const pay      = document.querySelector('input[name="bkPayMethod"]:checked')?.value;

    if (!name || !cat || !contact || !guardian || !gContact || !pay) {
        showFormError('Please fill in all required fields and select a payment method.');
        return false;
    }
    _payMethod = pay;
    return true;
}

function showFormError(msg) {
    const err = document.getElementById('bkFormError');
    err.textContent = msg;
    err.style.display = '';
    setTimeout(() => err.style.display = 'none', 5000);
}

/* ═══════════════════════════════════════════════════════
   STEP 3 — Receipt
   ═══════════════════════════════════════════════════════ */
function goToReceipt() {
    if (!validateForm()) return;
    const isGcash = _payMethod === 'GCash Online';
    document.getElementById('bkReceiptGcash').style.display = isGcash ? '' : 'none';
    document.getElementById('bkReceiptCash').style.display  = isGcash ? 'none' : '';
    showPanel('bkStepReceipt');
    setStepActive(3);
}

function onReceiptSelected(input) {
    if (input.files && input.files[0]) applyReceiptFile(input.files[0]);
}

function applyReceiptFile(file) {
    if (file.size > 5 * 1024 * 1024) {
        showReceiptError('File too large. Maximum size is 5 MB.');
        return;
    }
    const allowed = ['image/png','image/jpeg','image/jpg','application/pdf'];
    if (!allowed.includes(file.type)) {
        showReceiptError('Invalid file type. Please upload PNG, JPG, or PDF.');
        return;
    }
    _receiptFile = file;
    document.getElementById('bkReceiptError').style.display = 'none';

    const reader = new FileReader();
    reader.onload = e => {
        _receiptData = e.target.result; // Final base64 string
        
        const preview = document.getElementById('bkReceiptPreview');
        const inner   = document.getElementById('bkDropzoneInner');
        
        if (file.type.startsWith('image/')) {
            preview.src = _receiptData;
            preview.style.display = '';
            inner.style.display = 'none';
        } else {
            inner.innerHTML = `<i class="fas fa-file-pdf bk-drop-icon" style="color:#ef4444"></i>
                <p class="bk-drop-label">${file.name}</p>
                <p class="bk-drop-hint">Click to change</p>`;
            preview.style.display = 'none';
        }
    };
    reader.readAsDataURL(file);
}

function resetReceipt() {
    _receiptFile = null;
    const input   = document.getElementById('bkReceiptFile');
    const preview = document.getElementById('bkReceiptPreview');
    const inner   = document.getElementById('bkDropzoneInner');
    if (input)   input.value = '';
    if (preview) { preview.style.display = 'none'; preview.src = ''; }
    if (inner)   {
        inner.style.display = '';
        inner.innerHTML = `<i class="fas fa-cloud-upload-alt bk-drop-icon"></i>
            <p class="bk-drop-label">Click or drag & drop your receipt screenshot</p>
            <p class="bk-drop-hint">PNG, JPG, or PDF · max 5 MB</p>`;
    }
}

function showReceiptError(msg) {
    const err = document.getElementById('bkReceiptError');
    err.textContent = msg;
    err.style.display = '';
}

/* ═══════════════════════════════════════════════════════
   STEP 4 — Confirm
   ═══════════════════════════════════════════════════════ */
function goToConfirm() {
    if (_payMethod === 'GCash Online' && !_receiptFile) {
        showReceiptError('Please upload your GCash payment screenshot before continuing.');
        return;
    }

    const suffix = n => n==2?'nd':n==3?'rd':'th';
    const rows = [
        ['Room',            `Room ${_roomNo} — ${_floorNo}${suffix(_floorNo)} Floor`],
        ['Bed',             _selectedBedNo === 1 ? 'Lower Deck' : _selectedBedNo === 2 ? 'Upper Deck' : `Bed ${_selectedBedNo}`],
        ['Full Name',       document.getElementById('bkFullName').value.trim()],
        ['Category',        document.getElementById('bkCategory').value],
        ['School',          document.getElementById('bkSchool').value.trim() || '—'],
        ['Contact',         document.getElementById('bkContact').value.trim()],
        ['Guardian',        document.getElementById('bkGuardian').value.trim()],
        ['Guardian Contact',document.getElementById('bkGuardianContact').value.trim()],
        ['Payment Method',  _payMethod],
        ['Receipt',         _receiptFile ? `✅ ${_receiptFile.name}` : '— (Cash, no receipt)'],
        ['Monthly Rent',    `₱${parseInt(DormConfig.bed_price).toLocaleString()}.00`],
    ];

    document.getElementById('bkConfirmCard').innerHTML = rows.map(([k,v]) => `
        <div class="bk-confirm-row">
            <span class="bk-confirm-key">${k}</span>
            <span class="bk-confirm-val">${v}</span>
        </div>`).join('');

    showPanel('bkStepConfirm');
    setStepActive(4);
}

/* ═══════════════════════════════════════════════════════
   SUBMIT
   ═══════════════════════════════════════════════════════ */
async function submitBooking() {
    const btn = document.getElementById('bkSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';

    const formData = new FormData();
    formData.append('bed_id', _selectedBedId);
    formData.append('full_name', document.getElementById('bkFullName').value.trim());
    formData.append('category', document.getElementById('bkCategory').value);
    formData.append('school_name', document.getElementById('bkSchool').value.trim());
    formData.append('contact_number', document.getElementById('bkContact').value.trim());
    formData.append('guardian_name', document.getElementById('bkGuardian').value.trim());
    formData.append('guardian_contact', document.getElementById('bkGuardianContact').value.trim());
    formData.append('payment_method', _payMethod);
    
    if (_receiptFile) {
        formData.append('receipt', _receiptFile);
    }

    try {
        const response = await fetch('api/submit_booking.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            document.getElementById('bkRefCode').textContent = result.booking_ref;
            showPanel('bkStepSuccess');
            
            // Refresh main page view if functions exist
            if (typeof updateBadges === 'function') updateBadges();
            if (typeof loadRooms === 'function') loadRooms(_floorNo);
        } else {
            alert(result.message || 'Submission failed. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Confirm Booking';
        }
    } catch (e) {
        alert(`Error: ${e.message}`);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirm Booking';
    }
}

/* ═══════════════════════════════════════════════════════
   STYLES
   ═══════════════════════════════════════════════════════ */
function injectStyles() {
    const css = `
    .bk-overlay {
        position:fixed;inset:0;z-index:9000;
        background:rgba(15,23,42,.55);backdrop-filter:blur(4px);
        display:flex;align-items:center;justify-content:center;padding:1rem;
        opacity:0;pointer-events:none;transition:opacity .25s;
    }
    .bk-overlay.open{opacity:1;pointer-events:all;}

    .bk-modal {
        background:#fff;border-radius:1.5rem;
        width:100%;max-width:600px;max-height:90vh;
        overflow-y:auto;padding:2rem;
        box-shadow:0 24px 64px rgba(0,0,0,.18);
        transform:translateY(20px);transition:transform .3s;
        font-family:'Inter',sans-serif;
    }
    .bk-overlay.open .bk-modal{transform:translateY(0);}

    .bk-modal-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem;}
    .bk-modal-floor{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#10b981;margin:0 0 .2rem;}
    .bk-modal-title{font-family:'Outfit',sans-serif;font-weight:900;font-size:1.5rem;color:#1e293b;margin:0;}
    .bk-close-btn{width:34px;height:34px;border-radius:50%;border:none;background:#f1f5f9;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;transition:background .15s;}
    .bk-close-btn:hover{background:#e2e8f0;color:#1e293b;}

    .bk-steps{display:flex;align-items:center;margin-bottom:1.5rem;}
    .bk-step{display:flex;flex-direction:column;align-items:center;gap:.2rem;flex:0 0 auto;}
    .bk-step-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.78rem;background:#f1f5f9;color:#94a3b8;transition:background .2s,color .2s;}
    .bk-step-label{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;white-space:nowrap;}
    .bk-step--active .bk-step-num{background:#10b981;color:#fff;}
    .bk-step--active .bk-step-label{color:#10b981;}
    .bk-step--completed .bk-step-num{background:#d1fae5;color:#065f46;}
    .bk-step--completed .bk-step-label{color:#10b981;}
    .bk-step-line{flex:1;height:2px;background:#e2e8f0;margin:0 .4rem;margin-bottom:1rem;}

    .bk-step-hint{font-size:.82rem;color:#64748b;margin:0 0 1rem;font-weight:500;}

    .bk-bed-grid{display:flex;gap:.75rem;margin-bottom:1.5rem;overflow-x:auto;padding-bottom:.75rem;-webkit-overflow-scrolling:touch;}
    .bk-bunk-unit{flex:0 0 259px;min-width:259px;}
    .bk-bed-loading,.bk-bed-error{grid-column:1/-1;text-align:center;color:#94a3b8;font-size:.85rem;padding:1.5rem 0;}
    .bk-bed-chip{display:flex;flex-direction:column;align-items:center;gap:.3rem;padding:.85rem .4rem;border-radius:.85rem;border:2px solid;text-align:center;transition:all .18s;}
    .bk-bed-icon{font-size:1.2rem;}
    .bk-bed-num{font-weight:800;font-size:.82rem;color:#1e293b;font-family:'Outfit',sans-serif;}
    .bk-bed-status{font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;}
    .bk-bed--avail{background:#f0fdf4;border-color:#bbf7d0;}
    .bk-bed--avail .bk-bed-icon,.bk-bed--avail .bk-bed-status{color:#10b981;}
    .bk-bed--res{background:#fffbeb;border-color:#fde68a;opacity:.7;cursor:default;}
    .bk-bed--res .bk-bed-icon,.bk-bed--res .bk-bed-status{color:#f59e0b;}
    .bk-bed--occ{background:#fef2f2;border-color:#fecaca;opacity:.6;cursor:default;}
    .bk-bed--occ .bk-bed-icon,.bk-bed--occ .bk-bed-status{color:#ef4444;}
    .bk-bed--selectable{cursor:pointer;}
    .bk-bed--selectable:hover{border-color:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.15);transform:translateY(-2px);}
    .bk-bed--selected{border-color:#10b981;background:#d1fae5;box-shadow:0 0 0 3px rgba(16,185,129,.2);}

    .bk-form-row{display:grid;grid-template-columns:1fr 1fr;gap:.85rem;margin-bottom:.85rem;}
    @media(max-width:480px){.bk-form-row{grid-template-columns:1fr;}}
    .bk-form-group{display:flex;flex-direction:column;gap:.35rem;}
    .bk-form-group label{font-size:.78rem;font-weight:700;color:#374151;}
    .bk-form-group input,.bk-form-group select{padding:.65rem .9rem;border:2px solid #e5e7eb;border-radius:.65rem;font-family:'Inter',sans-serif;font-size:.88rem;font-weight:500;color:#1e293b;background:#fff;outline:none;transition:border-color .15s;}
    .bk-form-group input:focus,.bk-form-group select:focus{border-color:#10b981;}
    .req{color:#ef4444;}
    .bk-form-error{background:#fef2f2;border:1px solid #fecaca;border-radius:.65rem;padding:.65rem 1rem;color:#b91c1c;font-size:.82rem;font-weight:600;margin-bottom:.85rem;}

    .bk-pay-opts{display:flex;gap:.75rem;margin-top:.15rem;}
    .bk-pay-opt{flex:1;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.75rem;border-radius:.75rem;border:2px solid #e5e7eb;cursor:pointer;font-weight:700;font-size:.82rem;color:#374151;transition:all .15s;}
    .bk-pay-opt input[type="radio"]{display:none;}
    .bk-pay-opt:has(input:checked){border-color:#10b981;background:#f0fdf4;color:#065f46;}
    .bk-pay-opt:hover{border-color:#10b981;}

    .bk-gcash-info{display:flex;align-items:center;gap:1rem;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:1rem;padding:1rem 1.25rem;margin-bottom:1.25rem;}
    .bk-gcash-icon{width:44px;height:44px;border-radius:50%;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
    .bk-gcash-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#10b981;margin:0;}
    .bk-gcash-number{font-family:'Outfit',sans-serif;font-weight:900;font-size:1.2rem;color:#1e293b;margin:.1rem 0 0;}
    .bk-gcash-name{font-size:.78rem;font-weight:600;color:#64748b;margin:.1rem 0 0;}

    .bk-dropzone{border:2.5px dashed #cbd5e1;border-radius:1rem;min-height:150px;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;transition:border-color .2s,background .2s;overflow:hidden;position:relative;}
    .bk-dropzone:hover,.bk-dropzone--over{border-color:#10b981;background:#f0fdf4;}
    .bk-dropzone-inner{display:flex;flex-direction:column;align-items:center;padding:1.5rem;}
    .bk-drop-icon{font-size:2.2rem;color:#94a3b8;margin-bottom:.65rem;}
    .bk-drop-label{font-size:.85rem;font-weight:600;color:#374151;margin:0 0 .3rem;text-align:center;}
    .bk-drop-hint{font-size:.72rem;color:#94a3b8;margin:0;}
    .bk-receipt-preview{width:100%;max-height:220px;object-fit:contain;border-radius:.75rem;}

    .bk-cash-notice{display:flex;align-items:flex-start;gap:1rem;background:#fffbeb;border:1.5px solid #fde68a;border-radius:1rem;padding:1.25rem;margin:.5rem 0 1rem;}
    .bk-cash-notice i{font-size:1.3rem;color:#f59e0b;flex-shrink:0;margin-top:.1rem;}
    .bk-cash-notice strong{font-size:.9rem;color:#1e293b;display:block;margin-bottom:.3rem;}
    .bk-cash-notice p{font-size:.82rem;color:#64748b;margin:0;}

    .bk-confirm-card{background:#f8fafc;border-radius:1rem;border:1.5px solid #e2e8f0;overflow:hidden;margin-bottom:1.5rem;}
    .bk-confirm-row{display:flex;justify-content:space-between;align-items:center;padding:.7rem 1.1rem;font-size:.84rem;border-bottom:1px solid #f1f5f9;}
    .bk-confirm-row:last-child{border-bottom:none;}
    .bk-confirm-key{font-weight:600;color:#64748b;}
    .bk-confirm-val{font-weight:700;color:#1e293b;text-align:right;max-width:60%;word-break:break-word;}

    .bk-success-panel{text-align:center;padding:1rem 0;}
    .bk-success-icon{font-size:3.5rem;color:#10b981;margin-bottom:1rem;}
    .bk-success-panel h3{font-family:'Outfit',sans-serif;font-weight:900;font-size:1.4rem;color:#1e293b;margin:0 0 .5rem;}
    .bk-success-panel p{color:#64748b;font-size:.9rem;margin:.25rem 0;}
    .bk-ref-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-top:1.25rem !important;}
    .bk-ref-code{font-family:'Outfit',sans-serif;font-weight:900;font-size:1.4rem;color:#10b981;letter-spacing:.05em;}

    .bk-modal-footer{display:flex;justify-content:flex-end;gap:.65rem;padding-top:1rem;border-top:1px solid #f1f5f9;margin-top:.5rem;}

    .bk-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.65rem 1.35rem;border-radius:.75rem;border:none;font-family:'Outfit',sans-serif;font-weight:800;font-size:.82rem;cursor:pointer;transition:all .15s;}
    .bk-btn--ghost{background:#f1f5f9;color:#64748b;}
    .bk-btn--ghost:hover{background:#e2e8f0;}
    .bk-btn--primary{background:#10b981;color:#fff;}
    .bk-btn--primary:hover{background:#059669;}
    .bk-btn--primary:disabled{background:#d1fae5;color:#6ee7b7;cursor:not-allowed;}
    .bk-btn--success{background:#10b981;color:#fff;padding:.75rem 1.75rem;font-size:.9rem;}
    .bk-btn--success:hover{background:#059669;}
    .bk-btn--success:disabled{opacity:.6;cursor:not-allowed;}

    .dark-theme .bk-modal{background:var(--off-white);}
    .dark-theme .bk-modal-title{color:var(--text-primary);}
    .dark-theme .bk-close-btn{background:rgba(255,255,255,.08);color:var(--text-muted);}
    .dark-theme .bk-step-num{background:rgba(255,255,255,.08);}
    .dark-theme .bk-step-line{background:rgba(255,255,255,.08);}
    .dark-theme .bk-bed-num{color:var(--text-primary);}
    .dark-theme .bk-form-group label{color:var(--text-secondary);}
    .dark-theme .bk-form-group input,.dark-theme .bk-form-group select{background:var(--background);border-color:rgba(255,255,255,.1);color:var(--text-primary);}
    .dark-theme .bk-pay-opt{border-color:rgba(255,255,255,.1);color:var(--text-secondary);}
    .dark-theme .bk-dropzone{border-color:rgba(255,255,255,.15);}
    .dark-theme .bk-dropzone:hover{background:rgba(16,185,129,.08);}
    .dark-theme .bk-drop-label{color:var(--text-primary);}
    .dark-theme .bk-confirm-card{background:var(--background);border-color:rgba(255,255,255,.08);}
    .dark-theme .bk-confirm-row{border-color:rgba(255,255,255,.05);}
    .dark-theme .bk-confirm-key{color:var(--text-muted);}
    .dark-theme .bk-confirm-val{color:var(--text-primary);}
    .dark-theme .bk-success-panel h3{color:var(--text-primary);}
    .dark-theme .bk-modal-footer{border-color:rgba(255,255,255,.06);}
    .dark-theme .bk-btn--ghost{background:rgba(255,255,255,.08);color:var(--text-muted);}
    `;
    const style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);
}