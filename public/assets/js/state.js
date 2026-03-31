/**
 * STATE.JS - The "Static Engine" for Dormitory System
 * This file replaces the MySQL database with LocalStorage.
 */

const DORM_STATE_KEY = 'dormitory_system_state';

// 1. Initial Data (Based on SQL dump)
const INITIAL_DATA = {
    settings: {
        site_name: "Yasmin & Aliarose Dormitory",
        bed_price: 1600,
        gcash_number: "09915740177"
    },
    rooms: [
        {id: 1, floor_no: 2, room_no: '1', capacity: 4}, {id: 2, floor_no: 2, room_no: '2', capacity: 4},
        {id: 3, floor_no: 2, room_no: '3', capacity: 4}, {id: 4, floor_no: 2, room_no: '4', capacity: 4},
        {id: 5, floor_no: 2, room_no: '5', capacity: 5}, {id: 6, floor_no: 2, room_no: '6', capacity: 4},
        {id: 7, floor_no: 3, room_no: '1', capacity: 4}, {id: 8, floor_no: 3, room_no: '2', capacity: 4},
        {id: 9, floor_no: 3, room_no: '3', capacity: 4}, {id: 10, floor_no: 3, room_no: '4', capacity: 4},
        {id: 11, floor_no: 3, room_no: '5', capacity: 4}, {id: 12, floor_no: 3, room_no: '6', capacity: 4},
        {id: 13, floor_no: 4, room_no: '1', capacity: 2}, {id: 14, floor_no: 4, room_no: '2', capacity: 2},
        {id: 15, floor_no: 4, room_no: '3', capacity: 2}, {id: 16, floor_no: 4, room_no: '4', capacity: 2},
        {id: 17, floor_no: 4, room_no: '5', capacity: 2}, {id: 18, floor_no: 4, room_no: '6', capacity: 3}
    ],
    beds: [
        // Generated beds based on capacity above
        ...generateInitialBeds()
    ],
    bookings: [],
    deletedBookings: [],
    payments: [],
    admin: {
        username: "admin",
        password: "123"
    }
};

function generateInitialBeds() {
    let beds = [];
    let idCounter = 1;
    let rooms = [
        {id: 1, cap: 4}, {id: 2, cap: 4}, {id: 3, cap: 4}, {id: 4, cap: 4}, {id: 5, cap: 5}, {id: 6, cap: 4},
        {id: 7, cap: 4}, {id: 8, cap: 4}, {id: 9, cap: 4}, {id: 10, cap: 4}, {id: 11, cap: 4}, {id: 12, cap: 4},
        {id: 13, cap: 2}, {id: 14, cap: 2}, {id: 15, cap: 2}, {id: 16, cap: 2}, {id: 17, cap: 2}, {id: 18, cap: 3}
    ];
    rooms.forEach(r => {
        for(let i=1; i<=r.cap; i++) {
            beds.push({id: idCounter++, room_id: r.id, bed_no: i, status: 'Available'});
        }
    });
    return beds;
}

// 2. State Handlers
const DormState = {
    init() {
        if (!localStorage.getItem(DORM_STATE_KEY)) {
            localStorage.setItem(DORM_STATE_KEY, JSON.stringify(INITIAL_DATA));
        }
        this.data = JSON.parse(localStorage.getItem(DORM_STATE_KEY));
        
        // Ensure new structures exist (migration)
        if (!this.data.admin) {
            this.data.admin = INITIAL_DATA.admin;
            this.save();
        }
        if (!this.data.deletedBookings) {
            this.data.deletedBookings = [];
            this.save();
        }
    },
    save() {
        localStorage.setItem(DORM_STATE_KEY, JSON.stringify(this.data));
    },
    
    // Getters
    getRooms(floor) {
        return floor ? this.data.rooms.filter(r => r.floor_no == floor) : this.data.rooms;
    },
    getBeds(roomId) {
        return this.data.beds.filter(b => b.room_id == roomId);
    },
    getBed(bedId) {
        return this.data.beds.find(b => b.id == bedId);
    },
    getBookings(status = 'all') {
        if (status === 'all') return this.data.bookings;
        return this.data.bookings.filter(b => b.payment_status === status || b.booking_status === status);
    },
    getStats() {
        const rooms = this.data.rooms.length;
        const totalBeds = this.data.beds.length;
        const occupied = this.data.beds.filter(b => b.status === 'Occupied').length;
        const reserved = this.data.beds.filter(b => b.status === 'Reserved').length;
        const available = totalBeds - occupied - reserved;
        
        // Revenue logic
        const revenue = this.data.bookings.reduce((sum, b) => {
            const payments = b.payments || [];
            return sum + payments.reduce((pSum, p) => pSum + parseFloat(p.amount), 0);
        }, 0);

        // Overdue logic
        const now = new Date();
        const today = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
        const overdue = this.data.bookings.filter(b => b.booking_status === 'Active' && b.payment_status === 'Confirmed' && b.due_date && b.due_date < today).length;

        return { rooms, totalBeds, occupied, reserved, available, revenue, overdue };
    },

    getOverdueBookings() {
        const now = new Date();
        const today = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
        return this.data.bookings.filter(b => b.booking_status === 'Active' && b.payment_status === 'Confirmed' && b.due_date && b.due_date < today);
    },
    
    // Actions
    addBooking(formData) {
        const id = this.data.bookings.length + 1;
        const booking = {
            id,
            ...formData,
            school_name: formData.school_name || "—",
            created_at: new Date().toISOString(),
            payment_status: 'Pending',
            booking_status: 'Active',
            monthly_rent: this.data.settings.bed_price,
            due_date: new Date(new Date().setMonth(new Date().getMonth() + 1)).toISOString().split('T')[0],
            remarks: "",
            payments: []
        };
        this.data.bookings.push(booking);
        
        // Mark bed as Reserved
        const bed = this.getBed(formData.bed_id);
        if (bed) bed.status = 'Reserved';
        
        this.save();
        return booking;
    },

    processPayment(bookingId, amount, method, nextDue = null) {
        const b = this.data.bookings.find(x => x.id == bookingId);
        if (b) {
            b.payment_status = 'Confirmed';
            if (nextDue) b.due_date = nextDue;
            else {
                // Default to +30 days if no date provided
                let d = b.due_date ? new Date(b.due_date) : new Date();
                d.setMonth(d.getMonth() + 1);
                b.due_date = d.toISOString().split('T')[0];
            }
            
            // Update bed status too
            const bed = this.data.beds.find(x => x.id == b.bed_id);
            if (bed) bed.status = 'Occupied';
            
            // Log payment history
            if (!b.payments) b.payments = [];
            b.payments.push({
                date: new Date().toISOString(),
                amount: parseFloat(amount),
                method: method
            });
            
            this.save();
            return true;
        }
        return false;
    },

    // Room Management
    addRoom(roomNo, floorNo, bedsCount, roomName = "") {
        const roomId = Date.now();
        const room = {
            id: roomId,
            room_no: roomNo,
            room_name: roomName,
            floor_no: parseInt(floorNo),
            status: 'Available',
            capacity: parseInt(bedsCount)
        };
        this.data.rooms.push(room);
        
        // Add initial beds
        for (let i = 1; i <= bedsCount; i++) {
            this.addBed(roomId, i.toString().padStart(2, '0'));
        }
        
        this.save();
        return room;
    },

    deleteRoom(roomId) {
        this.data.rooms = this.data.rooms.filter(r => r.id != roomId);
        this.data.beds = this.data.beds.filter(b => b.room_id != roomId);
        this.save();
    },

    addBed(roomId, bedNo = null) {
        if (!bedNo) {
            const roomBeds = this.getBeds(roomId);
            bedNo = (roomBeds.length + 1).toString().padStart(2, '0');
        }
        const bed = {
            id: Date.now() + Math.floor(Math.random() * 1000),
            room_id: roomId,
            bed_no: bedNo,
            status: 'Available'
        };
        this.data.beds.push(bed);
        this.save();
        return bed;
    },

    deleteBed(bedId) {
        this.data.beds = this.data.beds.filter(b => b.id != bedId);
        this.save();
    },

    toggleBedStatus(bedId, newStatus) {
        const bed = this.data.beds.find(b => b.id == bedId);
        if (bed) {
            bed.status = newStatus;
            this.save();
            return true;
        }
        return false;
    },
    updateBookingStatus(id, bStat, pStat) {
        const booking = this.data.bookings.find(b => b.id == id);
        if (!booking) return false;
        
        booking.booking_status = bStat;
        booking.payment_status = pStat;
        
        // Update Bed Status
        const bed = this.getBed(booking.bed_id);
        if (bed) {
            if (bStat === 'Cancelled' || bStat === 'Completed') {
                bed.status = 'Available';
            } else if (pStat === 'Confirmed' && bStat === 'Active') {
                bed.status = 'Occupied';
            } else if (pStat === 'Pending') {
                bed.status = 'Reserved';
            }
        }
        
        this.save();
        return true;
    },

    acceptBooking(id) {
        return this.updateBookingStatus(id, 'Active', 'Confirmed');
    },

    declineBooking(id) {
        return this.updateBookingStatus(id, 'Cancelled', 'Declined');
    },

    checkoutBooking(id) {
        return this.updateBookingStatus(id, 'Completed', 'Cleared');
    },

    deleteBooking(id) {
        const index = this.data.bookings.findIndex(x => x.id == id);
        if (index !== -1) {
            const b = this.data.bookings[index];
            // Free bed
            this.toggleBedStatus(b.bed_id, 'Available');
            
            // Move to deletedBookings (Soft Delete)
            b.deleted_at = new Date().toISOString();
            this.data.deletedBookings.push(b);
            this.data.bookings.splice(index, 1);
            
            this.save();
            return true;
        }
        return false;
    },

    restoreBooking(id) {
        // Find in archive first
        let index = this.data.deletedBookings.findIndex(x => x.id == id);
        let b = null;
        
        if (index !== -1) {
            b = this.data.deletedBookings[index];
            this.data.deletedBookings.splice(index, 1);
        } else {
            // Find in current bookings (maybe they were just completed/checked out)
            b = this.data.bookings.find(x => x.id == id);
        }

        if (b) {
            // Reset status
            b.booking_status = 'Active';
            b.payment_status = 'Confirmed';
            
            // Check if bed is still free
            const bed = this.getBed(b.bed_id);
            if (bed && bed.status === 'Available') {
                bed.status = 'Occupied';
            }
            
            if (index !== -1) this.data.bookings.push(b);
            this.save();
            return true;
        }
        return false;
    },

    updateBooking(id, newData) {
        const b = this.data.bookings.find(x => x.id == id);
        if (b) {
            Object.assign(b, newData);
            this.save();
            return true;
        }
        return false;
    }
};

// Initialize on Load
DormState.init();
window.DormState = DormState; // Make globally accessible
