/**
 * bookings.js — All interactivity for bookings.php
 * Loaded AFTER admin.js. Fully self-contained.
 */

(function () {
    'use strict';

    /* ─────────────────────────────────────────
       Kill admin.js conflicts the moment this
       script executes (after admin.js is done)
    ───────────────────────────────────────── */
    window.onclick = null;

    window.closeModal = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('open');
        el.style.removeProperty('display');
    };

    /* ─────────────────────────────────────────
       Modal helpers
    ───────────────────────────────────────── */
    function openModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.style.removeProperty('display'); /* wipe any inline display:none */
        el.classList.add('open');
    }

    function closeModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('open');
        el.style.removeProperty('display');
    }

    /* ─────────────────────────────────────────
       Date helpers
    ───────────────────────────────────────── */
    function pad(n) { return String(n).padStart(2, '0'); }

    function todayStr() {
        var d = new Date();
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    function addOneMonth(dateStr) {
        var base;
        var now = new Date();
        now.setHours(0,0,0,0);

        if (dateStr && /^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
            var p = dateStr.split('-');
            base = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
            
            // If the date is in the past (Overdue case), 
            // the user likely wants to move to the NEXT valid cycle date in the future.
            while (base <= now) {
                base.setUTCMonth(base.getUTCMonth() + 1);
            }
        } else {
            // New resident (Pending -> Active)
            base = now;
            base.setUTCMonth(base.getUTCMonth() + 1);
        }
        
        return base.getUTCFullYear() + '-' + pad(base.getUTCMonth() + 1) + '-' + pad(base.getUTCDate());
    }

    /* ─────────────────────────────────────────
       Safe HTML escape
    ───────────────────────────────────────── */
    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ─────────────────────────────────────────
       Confirm dialog
    ───────────────────────────────────────── */
    function showConfirm(icon, title, msg, href, btnClass, btnLabel) {
        var el;
        el = document.getElementById('confirmIcon');  if (el) el.innerHTML = icon;
        el = document.getElementById('confirmTitle'); if (el) el.textContent = title;
        el = document.getElementById('confirmMsg');   if (el) el.textContent = msg;

        var btn = document.getElementById('confirmActionBtn');
        if (btn) {
            btn.href        = href;
            btn.className   = btnClass;
            btn.textContent = btnLabel;
        }

        openModal('confirmOverlay');
    }

    /* ─────────────────────────────────────────
       Payment modal
    ───────────────────────────────────────── */
    function openPaymentModal(id, name, due, rate) {
        var el;
        el = document.getElementById('pay_booking_id');     if (el) el.value = id;
        el = document.getElementById('pay_resident_label'); if (el) el.textContent = name;
        el = document.getElementById('pay_amount');         if (el) el.value = rate || 1500;
        el = document.getElementById('pay_next_due');       if (el) el.value = addOneMonth(due);

        var warn = document.getElementById('overdueWarning');
        var wTxt = document.getElementById('overdueWarningText');

        if (warn && wTxt && due && /^\d{4}-\d{2}-\d{2}$/.test(due)) {
            var p   = due.split('-');
            var dd  = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
            var now = new Date(); now.setHours(0, 0, 0, 0);
            if (dd < now) {
                var days = Math.round((now - dd) / 86400000);
                wTxt.textContent = 'Overdue by ' + days + ' day' + (days !== 1 ? 's' : '') + '. Was due ' + dd.toDateString() + '.';
                warn.classList.add('visible');
            } else {
                warn.classList.remove('visible');
            }
        } else if (warn) {
            warn.classList.remove('visible');
        }

        openModal('paymentModal');
    }

    /* ─────────────────────────────────────────
       Status modal
    ───────────────────────────────────────── */
    function openStatusModal(id, bStat, pStat) {
        var el;
        el = document.getElementById('status_booking_id');  if (el) el.value = id;
        el = document.getElementById('status_booking_val'); if (el) el.value = bStat || 'Active';
        el = document.getElementById('status_payment_val'); if (el) el.value = pStat || 'Pending';
        openModal('statusModal');
    }

    /* ─────────────────────────────────────────
       Checkout modal
    ───────────────────────────────────────── */
    function openCheckoutModal(id, name) {
        var el;
        el = document.getElementById('checkout_booking_id');    if (el) el.value = id;
        el = document.getElementById('checkout_resident_name'); if (el) el.textContent = name;
        el = document.getElementById('checkout_date');          if (el) el.value = todayStr();
        openModal('checkoutModal');
    }

    /* ─────────────────────────────────────────
       History modal
    ───────────────────────────────────────── */
    function openHistoryModal(id, name) {
        var el = document.getElementById('history_resident_name');
        if (el) el.textContent = name;

        var container = document.getElementById('historyContent');
        if (!container) { openModal('historyModal'); return; }

        var payData = (typeof PAYMENT_DATA !== 'undefined') ? PAYMENT_DATA : {};
        var bkgData = (typeof BOOKINGS_DATA !== 'undefined') ? BOOKINGS_DATA : {};
        var records = payData[id] || [];
        var b = bkgData[id];

        var html = '';

        if (b) {
            html += '<div style="background:#f8fafc; padding:1rem; border-radius:0.75rem; border:1px solid #e2e8f0; margin-bottom:1.25rem;">'
                 +  '<h3 style="font-size:0.8rem; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:0.75rem;"><i class="fas fa-id-badge"></i> Personal Info</h3>'
                 +  '<div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; font-size:0.8rem; color:#334155;">'
                 +    '<div><span style="color:#94a3b8; font-weight:600; font-size:0.7rem; display:block;">Category</span><span style="font-weight:700;">' + esc(b.category) + '</span></div>'
                 +    '<div><span style="color:#94a3b8; font-weight:600; font-size:0.7rem; display:block;">Phone</span><span style="font-weight:700;">' + esc(b.contact_number) + '</span></div>'
                 +    (!b.school_name ? '' : '<div style="grid-column: span 2;"><span style="color:#94a3b8; font-weight:600; font-size:0.7rem; display:block;">School / University</span><span style="font-weight:700;">' + esc(b.school_name) + '</span></div>')
                 +    '<div><span style="color:#94a3b8; font-weight:600; font-size:0.7rem; display:block;">Guardian</span><span style="font-weight:700;">' + esc(b.guardian_name) + '</span></div>'
                 +    '<div><span style="color:#94a3b8; font-weight:600; font-size:0.7rem; display:block;">Guardian Phone</span><span style="font-weight:700;">' + esc(b.guardian_contact) + '</span></div>'
                 +  '</div>'
                 +  '</div>'
                 +  '<h3 style="font-size:0.8rem; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:0.75rem;"><i class="fas fa-receipt"></i> Payment Records</h3>';
        }

        if (!records.length) {
            html += '<p style="text-align:center;color:#94a3b8;padding:2rem 0;font-size:0.85rem;border:1px dashed #cbd5e1;border-radius:0.75rem;">No payment records found.</p>';
        } else {
            var total = 0;
            records.forEach(function (r) {
                total += parseFloat(r.amount) || 0;
                var d = new Date(r.paid_at);
                var dateStr = isNaN(d) ? esc(r.paid_at) : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
                var rcptLnk = r.receipt_path ? '<a href="../' + esc(r.receipt_path) + '" target="_blank" style="margin-left:0.5rem; color:#3b82f6; font-size:0.75rem;"><i class="fas fa-external-link-alt"></i></a>' : '';
                
                html += '<div class="ph-row">'
                    + '<div>'
                    + '<div class="ph-amount">&#8369;' + (parseFloat(r.amount) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) + rcptLnk + '</div>'
                    + (r.notes ? '<div class="ph-notes">' + esc(r.notes) + '</div>' : '')
                    + '</div>'
                    + '<div class="ph-date">' + dateStr + '</div>'
                    + '</div>';
            });
            html += '<div class="ph-total"><span>Total Paid</span>'
                + '<span style="color:#10b981;">&#8369;' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 }) + '</span></div>';
        }
        container.innerHTML = html;

        openModal('historyModal');
    }

    /* ─────────────────────────────────────────
       SINGLE delegated listener for everything.
       Consolidated so nothing can fire out of
       order or interfere with each other.
    ───────────────────────────────────────── */
    document.addEventListener('click', function (e) {
        try {
            /* 1. data-action buttons (table row actions) */
            var btn = e.target.closest('[data-action]');
            if (btn) {
                e.preventDefault(); // Added to aggressively prevent default actions that might interfere!
                var action = btn.dataset.action;
                var id     = btn.dataset.id   || '';
                var name   = btn.dataset.name || '';
                var filter = (typeof BOOKING_FILTER !== 'undefined') ? BOOKING_FILTER : 'all';



                switch (action) {
                    case 'accept':
                        showConfirm(
                            '<i class="fas fa-check-circle" style="color:#10b981;"></i>', 'Accept Booking?',
                            "Accept " + name + "'s booking? Their bed will be marked Occupied and a due date will be set.",
                            'bookings.php?quick_action=accept&id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(filter),
                            'cb-accept', 'Yes, Accept'
                        );
                        return;

                    case 'decline':
                        showConfirm(
                            '<i class="fas fa-ban" style="color:#ef4444;"></i>', 'Decline Booking?',
                            "Decline " + name + "'s booking? Their bed will be freed back to Available.",
                            'bookings.php?quick_action=decline&id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(filter),
                            'cb-decline', 'Yes, Decline'
                        );
                        return;

                    case 'delete':
                        showConfirm(
                            '<i class="fas fa-trash-alt" style="color:#ef4444;"></i>', 'Delete Record?',
                            'This permanently removes the booking and all payment records. Cannot be undone.',
                            'bookings.php?action=delete&id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(filter),
                            'cb-decline', 'Delete Permanently'
                        );
                        return;

                    case 'payment':
                        var nextBtn = btn;

                        openPaymentModal(id, name, nextBtn.dataset.due || '', parseInt(nextBtn.dataset.rate, 10) || 1500);
                        return;

                    case 'status':
                        var stBtn = btn;

                        openStatusModal(id, stBtn.dataset.bstatus || '', stBtn.dataset.pstatus || '');
                        return;

                    case 'checkout':

                        openCheckoutModal(id, name);
                        return;

                    case 'receipt':
                        var img = document.getElementById('lightboxImg');
                        var ttl = document.getElementById('lightboxTitle');
                        if (img) img.src = btn.dataset.path || '';
                        if (ttl) ttl.textContent = 'Receipt — ' + name;
                        openModal('receiptLightbox');

                        return;

                    case 'history':

                        openHistoryModal(id, name);
                        return;
                }
                return; /* unknown action — stop here */
            }
        } catch (err) {
            console.error('CRITICAL ERROR processing click:', err);
        }

        /* 2. Named close buttons by ID */
        var closeMap = {
            paymentModalClose:  'paymentModal',
            statusModalClose:   'statusModal',
            checkoutModalClose: 'checkoutModal',
            historyModalClose:  'historyModal',
            lightboxCloseBtn:   'receiptLightbox',
            confirmCancelBtn:   'confirmOverlay'
        };
        /* Walk up from the actual target to handle icon-inside-button clicks */
        var cbtn = e.target.closest('button, a[id]');
        if (cbtn && cbtn.id && closeMap[cbtn.id]) {
            closeModal(closeMap[cbtn.id]);
            if (cbtn.id === 'lightboxCloseBtn') {
                var img2 = document.getElementById('lightboxImg');
                if (img2) img2.src = '';
            }
            return;
        }

        /* 3. data-close attribute (fallback) */
        var closer = e.target.closest('[data-close]');
        if (closer) {
            closeModal(closer.dataset.close);
            return;
        }

        /* 4. Click on the backdrop of modal-wrapper */
        if (e.target.classList.contains('modal-wrapper') && e.target.classList.contains('open')) {
            closeModal(e.target.id);
            return;
        }

        /* 5. Click on confirm overlay backdrop */
        if (e.target.id === 'confirmOverlay') {
            closeModal('confirmOverlay');
            return;
        }

        /* 6. Click on lightbox backdrop */
        if (e.target.id === 'receiptLightbox') {
            closeModal('receiptLightbox');
            var img3 = document.getElementById('lightboxImg');
            if (img3) img3.src = '';
            return;
        }

    });


    /* ─────────────────────────────────────────
       Live table search
    ───────────────────────────────────────── */
    var searchEl = document.getElementById('residentSearch');
    if (searchEl) {
        searchEl.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            document.querySelectorAll('#bookingsTable tbody tr').forEach(function (row) {
                row.style.display = (row.textContent || '').toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    /* ─────────────────────────────────────────
       Auto-dismiss flash toast
    ───────────────────────────────────────── */
    var toast = document.querySelector('.flash-toast');
    if (toast) {
        setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 4500);
    }

    /* ─────────────────────────────────────────
       Preserve dark-mode state from admin.js
    ───────────────────────────────────────── */
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-theme');
    }

}());