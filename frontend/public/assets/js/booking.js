/**
 * booking.js
 * Flow: Bed picker → Details form → Receipt upload (GCash only) → Confirm → Success
 */

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
                    <span class="bk-step-label">Bed</span>
                </div>
                <div class="bk-step-line"></div>
                <div class="bk-step" id="bkStep2Ind">
                    <span class="bk-step-num">2</span>
                    <span class="bk-step-label">Info</span>
                </div>
                <div class="bk-step-line"></div>
                <div class="bk-step" id="bkStep3Ind">
                    <span class="bk-step-num">3</span>
                    <span class="bk-step-label">Pay</span>
                </div>
                <div class="bk-step-line"></div>
                <div class="bk-step" id="bkStep4Ind">
                    <span class="bk-step-num">4</span>
                    <span class="bk-step-label">Done</span>
                </div>
            </div>

            <!-- ── STEP 1: Bed picker ── -->
            <div id="bkStepBeds" class="bk-step-panel" style="display:none">
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
                            <input type="text" id="bkFullName" placeholder="Juan dela Cruz" required>
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
                            <input type="text" id="bkSchool" placeholder="University">
                        </div>
                        <div class="bk-form-group">
                            <label>Contact <span class="req">*</span></label>
                            <input type="tel" id="bkContact" placeholder="09XXXXXXXXX" required>
                        </div>
                    </div>
                    <div class="bk-form-row">
                        <div class="bk-form-group">
                            <label>Guardian <span class="req">*</span></label>
                            <input type="text" id="bkGuardian" placeholder="Name" required>
                        </div>
                        <div class="bk-form-group">
                            <label>Guardian Contact <span class="req">*</span></label>
                            <input type="tel" id="bkGuardianContact" placeholder="09XXX" required>
                        </div>
                    </div>
                    <div class="bk-form-group">
                        <label>Payment Method <span class="req">*</span></label>
                        <div class="bk-pay-opts">
                            <label class="bk-pay-opt">
                                <input type="radio" name="bkPayMethod" value="GCash Online" onchange="onPayMethodChange('GCash Online')">
                                <span>GCash</span>
                            </label>
                            <label class="bk-pay-opt">
                                <input type="radio" name="bkPayMethod" value="Cash In" onchange="onPayMethodChange('Cash In')">
                                <span>Cash In</span>
                            </label>
                        </div>
                    </div>
                </form>
                <div class="bk-modal-footer">
                    <button class="bk-btn bk-btn--ghost" onclick="goToBeds()">Back</button>
                    <button class="bk-btn bk-btn--primary" onclick="goToReceipt()">Next</button>
                </div>
            </div>

            <!-- ── STEP 3: Receipt upload ── -->
            <div id="bkStepReceipt" class="bk-step-panel" style="display:none">
                <div id="bkReceiptGcash">
                    <p class="bk-step-hint">Transfer ₱1,600.00 to 09915740177 (YA Dormitory)</p>
                    <div class="bk-dropzone" id="bkDropzone" onclick="document.getElementById('bkReceiptFile').click()">
                        <div class="bk-dropzone-inner" id="bkDropzoneInner">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click or drag receipt proof</p>
                        </div>
                        <img id="bkReceiptPreview" class="bk-receipt-preview" style="display:none">
                    </div>
                    <input type="file" id="bkReceiptFile" accept="image/*,application/pdf" style="display:none" onchange="onReceiptSelected(this)">
                </div>

                <div id="bkReceiptCash" style="display:none">
                    <p class="bk-step-hint">Please pay ₱1,600.00 upon arrival at the dormitory.</p>
                </div>

                <div class="bk-modal-footer">
                    <button class="bk-btn bk-btn--ghost" onclick="goToForm()">Back</button>
                    <button class="bk-btn bk-btn--primary" onclick="goToConfirm()">Review</button>
                </div>
            </div>

            <!-- ── STEP 4: Confirm ── -->
            <div id="bkStepConfirm" class="bk-step-panel" style="display:none">
                <div class="bk-confirm-card" id="bkConfirmCard"></div>
                <div class="bk-modal-footer">
                    <button class="bk-btn bk-btn--ghost" onclick="goToReceipt()">Edit</button>
                    <button class="bk-btn bk-btn--primary" id="bkSubmitBtn" onclick="submitBooking()">Confirm Booking</button>
                </div>
            </div>

            <!-- ── SUCCESS ── -->
            <div id="bkStepSuccess" class="bk-step-panel" style="display:none; text-align:center;">
                <i class="fas fa-check-circle" style="font-size:3.5rem; color:#10b981; margin-bottom:1rem;"></i>
                <h3>Booking Successful!</h3>
                <p class="bk-success-sub">Your reservation has been received. Please save your reference code below.</p>
                
                <div class="bk-ref-box">
                    <span class="bk-ref-label">Reference Number</span>
                    <div class="bk-ref-value-wrap">
                        <strong id="bkRefCode" class="bk-ref-code"></strong>
                        <button class="bk-copy-btn" onclick="copyRefCode()" title="Copy Code">
                            <i class="far fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="bk-success-notice">
                    <i class="fas fa-info-circle"></i>
                    <span>You can track your booking status in your profile.</span>
                </div>

                <button class="bk-btn bk-btn--primary" onclick="window.location.href='profile.php'" style="margin-top:1.5rem; width: 100%;">
                    Go to Profile <i class="fas fa-user-circle"></i>
                </button>
            </div>

        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', html);

    // Initial setup
    const overlay = document.getElementById('bookingOverlay');
    overlay.addEventListener('click', e => { if (e.target === overlay) _closeModal(); });
    document.getElementById('bkCloseBtn').onclick  = _closeModal;
    document.getElementById('bkCancelBtn').onclick = _closeModal;

    const dz = document.getElementById('bkDropzone');
    if (dz) {
        dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('over'); });
        dz.addEventListener('dragleave', ()  => dz.classList.remove('over'));
        dz.addEventListener('drop', e => {
            e.preventDefault();
            const file = e.dataTransfer.files[0];
            if (file) applyReceiptFile(file);
        });
    }

    injectStyles();
})();

/* ═══════════════════════════════════════════════════════
   STATE & NAVIGATION
   ═══════════════════════════════════════════════════════ */
let _roomId = null, _roomNo = null, _floorNo = null;
let _selectedBedId = null, _selectedBedNo = null;
let _payMethod = null, _receiptFile = null;

function openBookingModal(roomId, roomNo, floorNo) {
    _roomId = roomId; _roomNo = roomNo; _floorNo = floorNo;
    const overlay = document.getElementById('bookingOverlay');
    if (!overlay) return;

    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';

    document.getElementById('bkModalTitle').textContent = `Room ${roomNo}`;
    document.getElementById('bkModalFloor').textContent = `${floorNo}${floorNo==1?'st':floorNo==2?'nd':'th'} Floor`;

    if (typeof IS_LOGGED_IN !== 'undefined' && !IS_LOGGED_IN) {
        _closeModal();
        if (window.YA_DORM && YA_DORM.showAuthModal) YA_DORM.showAuthModal();
        return;
    } else {
        document.getElementById('bkStepsBar').style.display = 'flex';
        showPanel('bkStepBeds');
        setStepActive(1);
        loadBeds(roomId);
    }
}

function _closeModal() {
    const overlay = document.getElementById('bookingOverlay');
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
}

const PANELS = ['bkStepBeds','bkStepForm','bkStepReceipt','bkStepConfirm','bkStepSuccess'];

function showPanel(id) {
    PANELS.forEach(p => {
        const el = document.getElementById(p);
        if (el) el.style.display = (p === id) ? 'block' : 'none';
    });
}

function setStepActive(step) {
    [1,2,3,4].forEach(n => {
        const el = document.getElementById(`bkStep${n}Ind`);
        if (el) {
            el.classList.toggle('bk-step--active', n === step);
            el.classList.toggle('bk-step--completed', n < step);
        }
    });
}

/* ═══════════════════════════════════════════════════════
   LOGIC
   ═══════════════════════════════════════════════════════ */
async function loadBeds(roomId) {
    const grid = document.getElementById('bkBedGrid');
    grid.innerHTML = '<div class="bk-bed-loading"><i class="fas fa-spinner fa-spin"></i> Loading beds...</div>';
    try {
        const res = await fetch(`api/room_api.php?action=beds&room_id=${roomId}`);
        const beds = await res.json();
        
        // Group beds by pairs (Bunk A, Bunk B, etc.)
        const bunks = [];
        for (let i = 0; i < beds.length; i += 2) {
            bunks.push(beds.slice(i, i + 2));
        }

        grid.innerHTML = bunks.map((bunkBeds, idx) => {
            const bunkLetter = String.fromCharCode(65 + idx); // A, B, C...
            return `
            <div class="bk-bunk-card">
                <div class="bk-bunk-label">BUNK ${bunkLetter}</div>
                <div class="bk-bunk-beds">
                    ${bunkBeds.map(b => {
                        const num = parseInt(b.bed_no);
                        const isUpper = (num % 2 === 0);
                        const deckLabel = isUpper ? 'Upper Deck' : 'Lower Deck';
                        return `
                        <div class="bk-bed-horizontal-chip ${b.status !== 'Available' ? 'disabled' : ''}" 
                             onclick="${b.status === 'Available' ? `selectBed(${b.id}, '${b.bed_no}')` : ''}">
                            <div class="bk-bed-icon-box">
                                <i class="fas fa-bed"></i>
                            </div>
                            <div class="bk-bed-info">
                                <span class="bk-bed-deck-title">${deckLabel}</span>
                                <span class="bk-bed-status-small">${b.status}</span>
                            </div>
                        </div>
                        `;
                    }).join('')}
                </div>
            </div>
            `;
        }).join('');
    } catch(e) { grid.innerHTML = 'Error loading beds.'; }
}

function selectBed(id, no) {
    const isAlreadySelected = (_selectedBedId === id);
    
    // Clear all selections
    document.querySelectorAll('.bk-bed-horizontal-chip').forEach(c => c.classList.remove('selected'));
    
    if (isAlreadySelected) {
        // Deselect
        _selectedBedId = null; _selectedBedNo = null;
        document.getElementById('bkNextToForm').disabled = true;
    } else {
        // Select new
        _selectedBedId = id; _selectedBedNo = no;
        event.currentTarget.classList.add('selected');
        document.getElementById('bkNextToForm').disabled = false;
    }
}

function goToForm() { showPanel('bkStepForm'); setStepActive(2); }
function goToBeds() { showPanel('bkStepBeds'); setStepActive(1); }

function onPayMethodChange(method) {
    _payMethod = method;
    document.getElementById('bkReceiptGcash').style.display = (method === 'GCash Online') ? 'block' : 'none';
    document.getElementById('bkReceiptCash').style.display = (method === 'Cash In') ? 'block' : 'none';
}

function goToReceipt() {
    if (!document.getElementById('bkFullName').value || !_payMethod) {
        document.getElementById('bkFormError').innerText = 'Please fill all required fields.';
        document.getElementById('bkFormError').style.display = 'block';
        return;
    }
    showPanel('bkStepReceipt'); setStepActive(3);
}

function onReceiptSelected(input) { if (input.files && input.files[0]) applyReceiptFile(input.files[0]); }

function applyReceiptFile(file) {
    _receiptFile = file;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('bkReceiptPreview').src = e.target.result;
        document.getElementById('bkReceiptPreview').style.display = 'block';
        document.getElementById('bkDropzoneInner').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function goToConfirm() {
    const card = document.getElementById('bkConfirmCard');
    const data = [
        ['Room', _roomNo],
        ['Floor', _floorNo],
        ['Bed', _selectedBedNo],
        ['Name', document.getElementById('bkFullName').value],
        ['Payment', _payMethod]
    ];
    card.innerHTML = data.map(r => `
        <div class="bk-confirm-row">
            <span class="bk-confirm-label">${r[0]}</span>
            <span class="bk-confirm-val">${r[1]}</span>
        </div>
    `).join('');
    showPanel('bkStepConfirm'); setStepActive(4);
}

async function submitBooking() {
    const btn = document.getElementById('bkSubmitBtn');
    btn.disabled = true; btn.innerText = 'Submitting...';
    
    const fd = new FormData();
    fd.append('bed_id', _selectedBedId);
    fd.append('full_name', document.getElementById('bkFullName').value);
    fd.append('category', document.getElementById('bkCategory').value);
    fd.append('contact_number', document.getElementById('bkContact').value);
    fd.append('guardian_name', document.getElementById('bkGuardian').value);
    fd.append('guardian_contact', document.getElementById('bkGuardianContact').value);
    fd.append('payment_method', _payMethod);
    if (_receiptFile) fd.append('receipt', _receiptFile);

    try {
        const res = await fetch('api/submit_booking.php', { method:'POST', body:fd });
        const resJson = await res.json();
        if (resJson.success) {
            document.getElementById('bkRefCode').innerText = resJson.booking_ref;
            showPanel('bkStepSuccess');
        } else alert(resJson.message);
    } catch(e) { alert('Error submitting. Check connection.'); btn.disabled = false; btn.innerText = 'Confirm Booking'; }
}

function copyRefCode() {
    const code = document.getElementById('bkRefCode').innerText;
    navigator.clipboard.writeText(code).then(() => {
        const btn = document.querySelector('.bk-copy-btn');
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => btn.innerHTML = oldHtml, 2000);
    });
}

function injectStyles() {
    const css = `
    .bk-success-sub { font-size: 0.9rem; color: #64748b; margin-bottom: 2rem; }
    .bk-ref-box {
        background: #f1f5f9;
        border: 2px solid #e2e8f0;
        border-radius: 1.25rem;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .dark-theme .bk-ref-box { background: #0f172a; border-color: #334155; }
    .bk-ref-label { font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
    .bk-ref-value-wrap { display: flex; align-items: center; justify-content: center; gap: 1rem; }
    .bk-ref-code { font-family: 'Outfit'; font-size: 1.5rem; font-weight: 900; color: #1e293b; letter-spacing: 1px; }
    .dark-theme .bk-ref-code { color: #f8fafc; }
    .bk-copy-btn { background: none; border: none; color: #10b981; cursor: pointer; font-size: 1.25rem; padding: 0.5rem; transition: transform 0.2s; }
    .bk-copy-btn:hover { transform: scale(1.2); }
    
    .bk-success-notice {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #ecfdf5;
        color: #065f46;
        padding: 1rem;
        border-radius: 1rem;
        font-size: 0.85rem;
        font-weight: 600;
        text-align: left;
    }
    .dark-theme .bk-success-notice { background: rgba(16, 185, 129, 0.1); color: #34d399; }

    .bk-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        transition: all 0.3s ease;
    }
    .bk-overlay.open { display: flex; animation: bkFadeIn 0.3s ease; }
    
    .bk-modal {
        background: #fff;
        border-radius: 1.75rem;
        width: 100%;
        max-width: 580px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 2rem;
        box-shadow: 0 25px 60px rgba(0,0,0,0.2);
        position: relative;
        font-family: 'Inter', sans-serif;
        color: #1e293b;
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE/Edge */
    }
    .bk-modal::-webkit-scrollbar {
        display: none; /* Chrome, Safari, Opera */
    }
    .dark-theme .bk-modal { background: #1e293b; color: #f8fafc; border: 1px solid #334155; }

    .bk-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
    }
    .bk-modal-floor { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; font-weight: 700; margin-bottom: 0.25rem; }
    .bk-modal-title { font-family: 'Outfit'; font-weight: 800; font-size: 1.75rem; margin: 0; line-height: 1.1; }
    .bk-close-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #f1f5f9;
        border: none;
        color: #64748b;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        font-size: 1.1rem;
    }
    .bk-close-btn:hover { background: #fee2e2; color: #ef4444; transform: rotate(90deg); }
    .dark-theme .bk-close-btn { background: #334155; color: #94a3b8; }

    /* Steps */
    .bk-steps { display: flex; align-items: center; margin-bottom: 2.5rem; justify-content: space-between; position: relative; }
    .bk-step { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; z-index: 1; flex: 1; }
    .bk-step-num {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #f1f5f9;
        color: #94a3b8;
        font-size: 0.75rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
        border: 2px solid transparent;
    }
    .dark-theme .bk-step-num { background: #334155; color: #64748b; }
    .bk-step-label { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; transition: all 0.3s; }
    
    .bk-step--active .bk-step-num { background: #10b981; color: #fff; transform: scale(1.15); box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); }
    .bk-step--active .bk-step-label { color: #10b981; }
    .bk-step--completed .bk-step-num { background: #ecfdf5; color: #10b981; border-color: #10b981; }
    .dark-theme .bk-step--completed .bk-step-num { background: rgba(16, 185, 129, 0.1); }
    
    .bk-step-line { position: absolute; top: 14px; left: 10%; right: 10%; height: 2px; background: #f1f5f9; z-index: 0; }
    .dark-theme .bk-step-line { background: #334155; }

    /* Panels */
    .bk-step-panel { animation: bkSlideIn 0.4s ease forwards; }
    .bk-step-hint { color: #64748b; font-size: 0.85rem; margin-bottom: 1.5rem; }
    .dark-theme .bk-step-hint { color: #94a3b8; }

    /* Bunk Cards */
    .bk-bed-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem; }
    @media (min-width: 640px) { .bk-bed-grid { grid-template-columns: 1fr 1fr; } }

    .bk-bunk-card {
        background: #f8fafc;
        border-radius: 1.5rem;
        padding: 1.25rem;
        border: 1px solid #e2e8f0;
    }
    .dark-theme .bk-bunk-card { background: #0f172a; border-color: #334155; }
    
    .bk-bunk-label {
        font-size: 0.7rem;
        font-weight: 800;
        color: #94a3b8;
        text-transform: uppercase;
        margin-bottom: 1rem;
        margin-left: 0.5rem;
        letter-spacing: 0.05em;
    }

    .bk-bunk-beds { display: flex; flex-direction: column; gap: 0.75rem; }

    .bk-bed-horizontal-chip {
        display: flex;
        align-items: center;
        background: #fff;
        border: 1.5px solid #d1fae5;
        border-radius: 1rem;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        gap: 1rem;
    }
    .dark-theme .bk-bed-horizontal-chip { background: #1e293b; border-color: #065f46; }

    .bk-bed-horizontal-chip:hover:not(.disabled) {
        transform: translateX(5px);
        background: #f0fdf4;
        border-color: #10b981;
    }
    .bk-bed-horizontal-chip:hover:not(.disabled) .bk-bed-deck-title { color: #10b981; }
    .bk-bed-horizontal-chip:hover:not(.disabled) .bk-bed-status-small { color: #10b981; }
    .bk-bed-horizontal-chip:hover:not(.disabled) .bk-bed-icon-box { background: #d1fae5; color: #059669; }

    .bk-bed-horizontal-chip.selected {
        background: #10b981;
        border-color: #10b981;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
    }

    .bk-bed-icon-box {
        width: 42px;
        height: 42px;
        background: #ecfdf5;
        color: #10b981;
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        transition: all 0.2s;
    }
    .bk-bed-horizontal-chip.selected .bk-bed-icon-box { background: rgba(255,255,255,0.2); color: #fff; }

    .bk-bed-info { display: flex; flex-direction: column; }
    
    .bk-bed-deck-title {
        font-family: 'Outfit';
        font-weight: 800;
        font-size: 1rem;
        color: #1e293b;
        transition: all 0.2s;
    }
    .bk-bed-horizontal-chip.selected .bk-bed-deck-title { color: #fff; }
    .dark-theme .bk-bed-deck-title { color: #f1f5f9; }

    .bk-bed-status-small {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.05em;
    }
    .bk-bed-horizontal-chip.selected .bk-bed-status-small { color: rgba(255,255,255,0.8); }

    .bk-bed-horizontal-chip.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f1f5f9;
        border-color: #e2e8f0;
        filter: grayscale(1);
    }
    .dark-theme .bk-bed-horizontal-chip.disabled { background: #0f172a; border-color: #1e293b; }

    /* Form */
    .bk-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
    @media (max-width: 480px) { .bk-form-row { grid-template-columns: 1fr; } }
    .bk-form-group label { display: block; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
    .bk-form-group input, .bk-form-group select {
        width: 100%;
        padding: 0.85rem 1rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.75rem;
        font-family: inherit;
        font-size: 0.95rem;
        transition: all 0.2s;
        background: transparent;
        color: inherit;
        box-sizing: border-box;
    }
    .bk-form-group input:focus, .bk-form-group select:focus { border-color: #10b981; outline: none; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
    .dark-theme .bk-form-group input, .dark-theme .bk-form-group select { border-color: #334155; }

    /* Pay Opts */
    .bk-pay-opts { display: flex; gap: 0.75rem; }
    .bk-pay-opt { flex: 1; position: relative; cursor: pointer; }
    .bk-pay-opt input { position: absolute; opacity: 0; }
    .bk-pay-opt span {
        display: block;
        padding: 1rem;
        text-align: center;
        border: 2px solid #e2e8f0;
        border-radius: 1rem;
        font-weight: 800;
        font-size: 0.95rem;
        transition: all 0.2s;
        color: #64748b;
    }
    .bk-pay-opt input:checked + span { background: #10b981; color: #fff; border-color: #10b981; box-shadow: 0 8px 20px rgba(16, 185, 129, 0.25); }
    .dark-theme .bk-pay-opt span { border-color: #334155; }

    /* Dropzone */
    .bk-dropzone { border: 2.5px dashed #cbd5e1; border-radius: 1.5rem; padding: 3rem 1.5rem; text-align: center; transition: all 0.3s; cursor: pointer; background: #f8fafc; }
    .bk-dropzone:hover { border-color: #10b981; background: #f0fdf4; transform: scale(1.01); }
    .dark-theme .bk-dropzone { background: #0f172a; border-color: #334155; }
    .bk-dropzone.over { border-color: #10b981; background: #ecfdf5; }
    .bk-dropzone i { font-size: 3rem; color: #10b981; margin-bottom: 1.25rem; opacity: 0.7; }
    .bk-dropzone p { font-size: 0.9rem; font-weight: 700; color: #1e293b; margin: 0; }
    .dark-theme .bk-dropzone p { color: #f8fafc; }

    /* Confirm card */
    .bk-confirm-card { background: #f8fafc; border-radius: 1.5rem; padding: 1.75rem; display: flex; flex-direction: column; gap: 0.85rem; border: 1px solid rgba(0,0,0,0.03); }
    .dark-theme .bk-confirm-card { background: #0f172a; border-color: rgba(255,255,255,0.03); }
    .bk-confirm-row { display: flex; justify-content: space-between; font-size: 0.95rem; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(0,0,0,0.03); }
    .dark-theme .bk-confirm-row { border-bottom-color: rgba(255,255,255,0.03); }
    .bk-confirm-row:last-child { border-bottom: none; padding-bottom: 0; }
    .bk-confirm-label { color: #64748b; font-weight: 600; }
    .bk-confirm-val { font-weight: 800; color: #1e293b; }
    .dark-theme .bk-confirm-val { color: #f8fafc; }

    /* Footer */
    .bk-modal-footer { display: flex; justify-content: space-between; gap: 1.25rem; margin-top: 2.5rem; }
    .bk-modal-footer .bk-btn { flex: 1; }
    .bk-btn {
        padding: 1rem 1.5rem;
        border-radius: 1rem;
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        font-size: 1rem;
        cursor: pointer;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .bk-btn--primary { background: #10b981; color: #fff; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.25); }
    .bk-btn--primary:hover { background: #059669; transform: translateY(-3px); box-shadow: 0 12px 30px rgba(16, 185, 129, 0.35); }
    .bk-btn--primary:disabled { background: #cbd5e1; cursor: not-allowed; box-shadow: none; transform: none; }
    .bk-btn--ghost { background: #f1f5f9; color: #64748b; }
    .bk-btn--ghost:hover { background: #e2e8f0; color: #1e293b; }
    .dark-theme .bk-btn--ghost { background: #334155; color: #94a3b8; }

    @keyframes bkFadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes bkSlideIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    
    .bk-form-error { background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 0.75rem; font-size: 0.85rem; font-weight: 800; margin-bottom: 1.5rem; border: 1px solid #fecaca; }
    `;
    const s = document.createElement('style'); s.textContent = css; document.head.appendChild(s);
}