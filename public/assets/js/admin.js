/**
 * Admin Dashboard JS
 */

async function openHistory(bookingId, name) {
    const modal = document.getElementById('historyModal');
    if (!modal) return;
    const title = document.getElementById('historyTitle');
    const content = document.getElementById('historyContent');
    
    modal.classList.add('open');
    if (title) title.innerText = 'Rent History: ' + name;
    if (content) content.innerHTML = '<div class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    try {
        if (typeof window.supabaseReady === 'undefined' || !window.dormSupabase) {
            content.innerHTML = '<div class="empty-state text-muted text-center p-4">Open <a href="bookings.html" class="color-primary font-bold">bookings.html</a> for live Supabase data.</div>';
            return;
        }
        await window.supabaseReady;
        const { data, error } = await window.dormSupabase.from('bookings').select('service, date, name').eq('id', bookingId).maybeSingle();
        if (error) throw error;
        if (!data) {
            content.innerHTML = '<div class="empty-state text-muted text-center p-4">Booking not found.</div>';
            return;
        }
        const svc = (data.service || '').replace(/</g, '&lt;');
        content.innerHTML = '<div class="p-3 text-sm" style="line-height:1.5;"><strong>' + (data.name || '') + '</strong><br><span class="text-muted">' + (data.date || '') + '</span><pre style="margin-top:0.75rem;white-space:pre-wrap;font-family:inherit;">' + svc + '</pre></div>';
    } catch(e) { content.innerHTML = '<div class="error-state p-4">Error loading history.</div>'; }
}

function openAddPayment(id, name, currentDue) {
    const modal = document.getElementById('paymentModal');
    if (!modal) return;
    modal.classList.add('open');
    document.getElementById('pay_booking_id').value = id;
    const nameEl = document.getElementById('pay_resident_name') || document.getElementById('pay_resident_label');
    if (nameEl) nameEl.innerText = name;
    
    // Auto-calculate next month's due date
    let baseDate = (currentDue && currentDue !== '0000-00-00') ? new Date(currentDue) : new Date();
    
    // If the date is invalid, fallback to today
    if (isNaN(baseDate.getTime())) {
        baseDate = new Date();
    }
    
    baseDate.setMonth(baseDate.getMonth() + 1);
    document.getElementById('pay_next_due').value = baseDate.toISOString().split('T')[0];
}

function openStatusUpdate(id, bookingStatus, paymentStatus, filter) {
    const modal = document.getElementById('statusModal');
    if (!modal) return;
    modal.classList.add('open');
    document.getElementById('status_booking_id').value = id;
    document.getElementById('status_booking_val').value = bookingStatus;
    document.getElementById('status_payment_val').value = paymentStatus;
    document.getElementById('status_current_filter').value = filter;
}

// Mobile Sidebar Logic (High Priority Global Handler)
function toggleSidebar(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        const isOpening = !sidebar.classList.contains('show');
        sidebar.classList.toggle('show');
        sidebar.classList.toggle('active');
    }
}

// Global click-out to close menu on mobile
document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.getElementById('sidebarToggleBtn');
    if (window.innerWidth <= 1024 && sidebar && (sidebar.classList.contains('show') || sidebar.classList.contains('active'))) {
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('show');
            sidebar.classList.remove('active');
        }
    }
});

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
}

// Global click-out to close modals
window.onclick = function(event) {
    if (event.target.className === 'modal-wrapper') {
        event.target.style.display = "none";
    }
}

// Global Theme Handler
function toggleTheme() {
    const isDark = document.body.classList.toggle('dark-theme');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateThemeIcon(isDark);
}

function updateThemeIcon(isDark) {
    const btn = document.getElementById('themeToggleBtnAdmin');
    if(btn) {
        btn.innerHTML = isDark 
            ? '<i class="fas fa-sun" style="color:#f59e0b;"></i> <span>Light Mode</span>' 
            : '<i class="fas fa-moon"></i> <span>Dark Mode</span>';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if(localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-theme');
        updateThemeIcon(true);
    }
});