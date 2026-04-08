/**
 * STATE.JS - API-backed state engine for the Dormitory System.
 * All data is persisted in MySQL via PHP API endpoints.
 * LocalStorage is used only for UI preferences (theme, etc.).
 */

/* ── Resolve the API base path regardless of page depth ── */
const _STATE_BASE = (() => {
    const path = window.location.pathname.replace(/\/[^/]*$/, '/');
    return window.location.origin + path;
})();

function _apiUrl(file) {
    // Works from root pages (booking.html) and sub-pages (admin/)
    if (_STATE_BASE.includes('/admin/')) {
        return _STATE_BASE + '../api/' + file;
    }
    return _STATE_BASE + 'api/' + file;
}

/* ── Fallback settings used before the first API response ── */
const _DEFAULT_SETTINGS = {
    site_name:    "Yasmin & Aliarose Dormitory",
    bed_price:    1600,
    gcash_number: "09915740177"
};

/* ═══════════════════════════════════════════════════════════
   DormState — async API-backed interface
   ═══════════════════════════════════════════════════════════ */
const DormState = {

    /* Cached settings so synchronous callers (components.js, booking.js
       template strings) still work after init() resolves. */
    data: {
        settings: { ..._DEFAULT_SETTINGS }
    },

    /* ── init: fetch settings from the server ── */
    async init() {
        try {
            const res  = await fetch(_apiUrl('state_api.php?action=getSettings'));
            const json = await res.json();
            if (json && json.site_name) {
                this.data.settings = {
                    site_name:    json.site_name    || _DEFAULT_SETTINGS.site_name,
                    bed_price:    Number(json.bed_price)  || _DEFAULT_SETTINGS.bed_price,
                    gcash_number: json.gcash_number || _DEFAULT_SETTINGS.gcash_number
                };
            }
        } catch (e) {
            console.warn('DormState.init: could not fetch settings, using defaults.', e);
        }
        return this;
    },

    /* ── Rooms ── */
    async getRooms(floor) {
        const url = floor
            ? _apiUrl(`state_api.php?action=getRooms&floor=${floor}`)
            : _apiUrl('state_api.php?action=getRooms');
        const res  = await fetch(url);
        return res.json();
    },

    /* ── Beds ── */
    async getBeds(roomId) {
        const res = await fetch(_apiUrl(`state_api.php?action=getBeds&roomId=${roomId}`));
        return res.json();
    },

    /* ── Stats ── */
    async getStats() {
        const res = await fetch(_apiUrl('state_api.php?action=getStats'));
        return res.json();
    },

    /* ── Bookings ── */
    async getBookings(status = 'all') {
        const res = await fetch(_apiUrl(`state_api.php?action=getBookings&status=${status}`));
        return res.json();
    },

    /* ── Submit a new booking (multipart so receipt file can be included) ── */
    async addBooking(formData) {
        const payload = new FormData();
        const fields  = [
            'bed_id','room_id','full_name','category','school_name',
            'contact_number','guardian_name','guardian_contact',
            'payment_method','booking_ref'
        ];
        fields.forEach(k => { if (formData[k] !== undefined) payload.append(k, formData[k]); });

        // Attach receipt file if present
        if (formData._receiptFile) {
            payload.append('receipt', formData._receiptFile);
        }

        const res  = await fetch(_apiUrl('submit_booking.php'), { method: 'POST', body: payload });
        const json = await res.json();
        return json; // { success, booking_ref } or { success: false, message }
    },

    /* ── Process a rent payment ── */
    async processPayment(bookingId, amount, method, nextDue = null) {
        const payload = new FormData();
        payload.append('action',     'processPayment');
        payload.append('booking_id', bookingId);
        payload.append('amount',     amount);
        payload.append('method',     method);
        if (nextDue) payload.append('next_due', nextDue);

        const res  = await fetch(_apiUrl('state_api.php'), { method: 'POST', body: payload });
        const json = await res.json();
        return json.success === true;
    }
};

/* ── Bootstrap: fetch settings then expose globally ── */
DormState.init().then(() => {
    window.DormState = DormState;
    // Fire a custom event so pages can react once settings are ready
    document.dispatchEvent(new CustomEvent('dormstate:ready'));
});

// Also expose immediately so synchronous script tags don't throw
window.DormState = DormState;

