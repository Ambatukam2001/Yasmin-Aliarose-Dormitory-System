-- Supabase / PostgreSQL schema — Dormitory System
-- Run in Supabase SQL Editor. For existing projects with legacy tables, see supabase_migration_bookings_v2.sql

-- 1. Admins
CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- 2. Rooms
CREATE TABLE IF NOT EXISTS rooms (
    id SERIAL PRIMARY KEY,
    room_no VARCHAR(50) NOT NULL,
    floor_no INTEGER NOT NULL,
    capacity INTEGER DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Available',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- 3. Beds
CREATE TABLE IF NOT EXISTS beds (
    id SERIAL PRIMARY KEY,
    room_id INTEGER REFERENCES rooms(id) ON DELETE CASCADE,
    floor_id INTEGER,
    bed_no INTEGER NOT NULL,
    status VARCHAR(50) DEFAULT 'Available',
    reserved_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- 4. Bookings — client-driven (anon key); service text may include [bed:ID] prefix for bed linkage
CREATE TABLE IF NOT EXISTS bookings (
    id BIGSERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    service TEXT NOT NULL,
    date DATE NOT NULL DEFAULT (CURRENT_DATE),
    status TEXT NOT NULL DEFAULT 'pending'
);

CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings (status);
CREATE INDEX IF NOT EXISTS idx_bookings_date ON bookings (date DESC);

-- Optional: payments (legacy); omit if unused
-- CREATE TABLE IF NOT EXISTS payments (...);

-- RLS: disabled for development anon access (see supabase_policies.sql for production policies)
ALTER TABLE bookings DISABLE ROW LEVEL SECURITY;
ALTER TABLE rooms DISABLE ROW LEVEL SECURITY;
ALTER TABLE beds DISABLE ROW LEVEL SECURITY;
