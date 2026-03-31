/**
 * ADMIN-LOGIC.JS - Interaction for Admin Pages
 */

const AdminLogic = {
    initDashboard() {
        const stats = DormState.getStats();
        const mapping = {
            'total-rooms': stats.rooms,
            'available-beds': stats.available,
            'potential-revenue': `₱${stats.revenue.toLocaleString()}`,
            'overdue-payments': stats.overdue
        };

        Object.keys(mapping).forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = mapping[id];
        });

        this.renderRecentReservations();
    },

    renderRecentReservations() {
        const bookings = DormState.getBookings().slice(-5).reverse();
        const tbody = document.querySelector('#recentBookingsTable tbody');
        if (!tbody) return;

        if (bookings.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">No recent bookings.</td></tr>';
            return;
        }

        tbody.innerHTML = bookings.map(b => {
            const bed = DormState.getBed(b.bed_id);
            const room = DormState.data.rooms.find(r => r.id == (bed ? bed.room_id : null));
            const hasReceipt = b.receipt_image && b.receipt_image.length > 0;
            
            return `
            <tr>
                <td class="font-mono font-bold color-primary">${b.booking_ref}</td>
                <td>
                    <div class="font-bold">${b.full_name}</div>
                    <small class="text-muted">${b.category}</small>
                </td>
                <td>
                    <strong class="text-slate-700">Floor ${room ? room.floor_no : '?'}</strong><br>
                    <small class="text-muted">Room ${room ? room.room_no : '?'} | Bed ${bed ? bed.bed_no : '?'}</small>
                </td>
                <td>
                    <span class="badge badge-${b.payment_status.toLowerCase()}">${b.payment_status}</span>
                </td>
                <td class="text-right">
                    <div class="actions-flex">
                        ${hasReceipt ? `
                            <button onclick="AdminLogic.openReceiptModal('${b.id}')" class="btn-action btn-history" style="font-size:0.75rem;" title="Verify Payment Image">
                                <i class="fas fa-image"></i> Receipt
                            </button>
                        ` : ''}
                        ${b.payment_status === 'Pending' ? `
                            <button onclick="AdminLogic.openPaymentModal(${b.id}, '${b.full_name}')" class="btn-action btn-confirm">
                                <i class="fas fa-check"></i>
                            </button>
                        ` : `
                            <button onclick="AdminLogic.openPaymentModal(${b.id}, '${b.full_name}')" class="btn-action btn-confirm">
                                <i class="fas fa-hand-holding-usd"></i>
                            </button>
                        `}
                    </div>
                </td>
            </tr>`;
        }).join('');
    },

    openPaymentModal(id, name) {
        const b = DormState.data.bookings.find(x => x.id == id);
        document.getElementById('pay_booking_id').value = id;
        document.getElementById('pay_resident_name').textContent = name;
        
        // Set default monthly amount
        const amtInput = document.querySelector('input[name="amount"]');
        if (amtInput) amtInput.value = b?.monthly_rent || 1600;
        
        // Calculate default next due date (1 month from now)
        const d = new Date();
        d.setMonth(d.getMonth() + 1);
        const dateInput = document.getElementById('pay_next_due');
        if (dateInput) dateInput.value = d.toISOString().split('T')[0];
        
        document.getElementById('paymentModal').style.display = 'flex';
    },

    closeModal(id) {
        document.getElementById(id).style.display = 'none';
    },

    openReceiptModal(id) {
        const b = DormState.data.bookings.find(x => x.id == id);
        if (!b || !b.receipt_image) return;

        let modal = document.getElementById('receiptModal');
        if (!modal) {
            // Create modal if missing in dashboard
            const html = `
                <div id="receiptModal" class="modal-wrapper">
                    <div class="modal-body max-w-500">
                        <h2 class="font-bold mb-4">Payment Receipt <i class="fas fa-file-invoice-dollar color-primary" style="margin-left:5px"></i></h2>
                        <div style="width: 100%; max-height: 500px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border-radius: 1rem; overflow: hidden; border: 2px dashed #cbd5e1;">
                            <img id="receiptModalImg" src="" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        </div>
                        <div class="text-center mt-6">
                            <button type="button" onclick="AdminLogic.closeModal('receiptModal')" class="btn-action btn-confirm">Ok</button>
                        </div>
                    </div>
                </div>`;
            document.body.insertAdjacentHTML('beforeend', html);
            modal = document.getElementById('receiptModal');
        }

        document.getElementById('receiptModalImg').src = b.receipt_image;
        modal.style.display = 'flex';
    },

    handlePaymentSubmit(e) {
        e.preventDefault();
        const id = document.getElementById('pay_booking_id').value;
        const form = e.target;
        const amount = form.querySelector('[name="amount"]').value;
        const nextDue = document.getElementById('pay_next_due').value;

        if (DormState.processPayment(id, amount, 'Cash (Dashboard)', nextDue)) {
            this.closeModal('paymentModal');
            this.initDashboard();
            // Show toast
            const toast = document.createElement('div');
            toast.className = 'flash-toast success';
            toast.innerHTML = '<i class="fas fa-hand-holding-usd"></i> Rent payment recorded successfully.';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }
    },

    handlePasswordChange(e) {
        e.preventDefault();
        const current = document.getElementById('current_password').value;
        const nPass = document.getElementById('new_password').value;
        const cPass = document.getElementById('confirm_password').value;
        const alertBox = document.getElementById('passwordAlert');
        
        const adminData = DormState.data.admin;

        if (current !== adminData.password) {
            this.showAlert(alertBox, 'Current password incorrect.', 'error');
            return;
        }

        if (nPass !== cPass) {
            this.showAlert(alertBox, 'New passwords do not match.', 'error');
            return;
        }

        adminData.password = nPass;
        DormState.save();
        this.showAlert(alertBox, 'Password updated successfully!', 'success');
        e.target.reset();
    },

    handleResetSystem() {
        if (confirm('CRITICAL: This will PERMANENTLY DELETE all rooms, beds, and residents. Continue?')) {
            if (confirm('ARE YOU ABSOLUTELY SURE? This cannot be undone.')) {
                localStorage.clear();
                window.location.href = '../index.html';
            }
        }
    },

    showAlert(el, msg, type) {
        el.textContent = msg;
        el.style.display = 'block';
        el.style.background = type === 'success' ? '#d1fae5' : '#fee2e2';
        el.style.color = type === 'success' ? '#065f46' : '#991b1b';
        setTimeout(() => el.style.display = 'none', 5000);
    }
};
