/**
 * ADMIN-LOGIC.JS - Interaction for Admin Pages (API-backed)
 * Used by the legacy .html admin pages. All data comes from the PHP API.
 */

const AdminLogic = {
    async initDashboard() {
        try {
            const stats = await DormState.getStats();
            const mapping = {
                'total-rooms':      stats.rooms      ?? 0,
                'available-beds':   stats.available  ?? 0,
                'potential-revenue': `₱${Number(stats.potential_revenue || 0).toLocaleString()}`,
                'overdue-payments': stats.overdue_count ?? 0
            };
            Object.keys(mapping).forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = mapping[id];
            });
        } catch (e) {
            console.warn('AdminLogic.initDashboard: stats fetch failed', e);
        }

        await this.renderRecentReservations();
    },

    async renderRecentReservations() {
        const tbody = document.querySelector('#recentBookingsTable tbody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>';

        try {
            const bookings = await DormState.getBookings('all');
            const recent   = bookings.slice(0, 5);

            if (!recent.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No recent bookings.</td></tr>';
                return;
            }

            tbody.innerHTML = recent.map(b => {
                const hasReceipt = !!b.receipt_path;
                const floorNo    = b.floor_no  || '?';
                const roomNo     = b.room_no   || '?';
                const bedNo      = b.bed_no    || '?';
                return `
                <tr>
                    <td class="font-mono font-bold color-primary">${b.booking_ref}</td>
                    <td>
                        <div class="font-bold">${b.full_name}</div>
                        <small class="text-muted">${b.category}</small>
                    </td>
                    <td>
                        <strong class="text-slate-700">Floor ${floorNo}</strong><br>
                        <small class="text-muted">Room ${roomNo} | Bed ${bedNo}</small>
                    </td>
                    <td>
                        <span class="badge badge-${(b.payment_status || '').toLowerCase()}">${b.payment_status}</span>
                    </td>
                    <td class="text-right">
                        <div class="actions-flex">
                            ${hasReceipt ? `
                                <a href="../${b.receipt_path}" target="_blank" class="btn-action btn-history" style="font-size:0.75rem;" title="View Receipt">
                                    <i class="fas fa-image"></i> Receipt
                                </a>
                            ` : ''}
                            <button onclick="AdminLogic.openPaymentModal(${b.id}, '${(b.full_name || '').replace(/'/g, "\\'")}')" class="btn-action btn-confirm">
                                <i class="fas fa-hand-holding-usd"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load bookings.</td></tr>';
        }
    },

    openPaymentModal(id, name) {
        const el = document.getElementById('pay_booking_id');
        if (el) el.value = id;

        const nameEl = document.getElementById('pay_resident_name');
        if (nameEl) nameEl.textContent = name;

        // Set default monthly amount from settings
        const amtInput = document.querySelector('input[name="amount"]');
        if (amtInput) amtInput.value = DormState.data.settings.bed_price || 1600;

        // Calculate default next due date (1 month from now)
        const d = new Date();
        d.setMonth(d.getMonth() + 1);
        const dateInput = document.getElementById('pay_next_due');
        if (dateInput) dateInput.value = d.toISOString().split('T')[0];

        const modal = document.getElementById('paymentModal');
        if (modal) modal.style.display = 'flex';
    },

    closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    },

    async handlePaymentSubmit(e) {
        e.preventDefault();
        const id      = document.getElementById('pay_booking_id').value;
        const form    = e.target;
        const amount  = form.querySelector('[name="amount"]').value;
        const nextDue = document.getElementById('pay_next_due').value;

        try {
            const ok = await DormState.processPayment(id, amount, 'Cash (Dashboard)', nextDue);
            if (ok) {
                this.closeModal('paymentModal');
                await this.initDashboard();
                const toast = document.createElement('div');
                toast.className = 'flash-toast success';
                toast.innerHTML = '<i class="fas fa-hand-holding-usd"></i> Rent payment recorded successfully.';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 4000);
            } else {
                alert('Payment failed. Please try again.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    },

    handlePasswordChange(e) {
        e.preventDefault();
        const alertBox = document.getElementById('passwordAlert');
        // Password changes are handled server-side via the PHP admin panel.
        this.showAlert(alertBox, 'Please use the PHP admin panel to change your password.', 'error');
    },

    handleResetSystem() {
        if (confirm('CRITICAL: This action cannot be undone. Please use the PHP admin panel for system management.')) {
            window.location.href = '../admin/dashboard.php';
        }
    },

    showAlert(el, msg, type) {
        if (!el) return;
        el.textContent = msg;
        el.style.display = 'block';
        el.style.background = type === 'success' ? '#d1fae5' : '#fee2e2';
        el.style.color      = type === 'success' ? '#065f46' : '#991b1b';
        setTimeout(() => { if (el) el.style.display = 'none'; }, 5000);
    }
};
