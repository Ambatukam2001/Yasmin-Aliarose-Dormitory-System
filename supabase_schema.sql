-- Supabase / PostgreSQL Schema for Dormitory System

-- 1. Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Rooms Table
CREATE TABLE IF NOT EXISTS rooms (
    id SERIAL PRIMARY KEY,
    room_no VARCHAR(50) NOT NULL,
    floor_no INTEGER NOT NULL,
    capacity INTEGER DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Available',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Beds Table
CREATE TABLE IF NOT EXISTS beds (
    id SERIAL PRIMARY KEY,
    room_id INTEGER REFERENCES rooms(id) ON DELETE CASCADE,
    floor_id INTEGER, -- Simplified floor tracking
    bed_no INTEGER NOT NULL,
    status VARCHAR(50) DEFAULT 'Available',
    reserved_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 4. Bookings Table
CREATE TABLE IF NOT EXISTS bookings (
    id SERIAL PRIMARY KEY,
    booking_ref VARCHAR(100) NOT NULL UNIQUE,
    bed_id INTEGER REFERENCES beds(id) ON DELETE SET NULL,
    full_name VARCHAR(255) NOT NULL,
    category VARCHAR(100), -- Reviewer, College, High School
    school_name VARCHAR(255),
    contact_number VARCHAR(100),
    guardian_name VARCHAR(255),
    guardian_contact VARCHAR(100),
    payment_method VARCHAR(100),
    receipt_path VARCHAR(255),
    booking_status VARCHAR(50) DEFAULT 'Active',
    payment_status VARCHAR(50) DEFAULT 'Pending',
    reserve_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    due_date TIMESTAMP WITH TIME ZONE,
    monthly_rent DECIMAL(15, 2),
    current_balance DECIMAL(15, 2),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 5. Payments Table
CREATE TABLE IF NOT EXISTS payments (
    id SERIAL PRIMARY KEY,
    booking_id INTEGER REFERENCES bookings(id) ON DELETE CASCADE,
    payment_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(15, 2) NOT NULL,
    payment_mode VARCHAR(100),
    reference_no VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
