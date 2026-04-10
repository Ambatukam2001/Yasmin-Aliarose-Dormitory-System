-- Migrate from legacy bookings (booking_ref, bed_id, full_name, …) to simplified columns.
-- BACK UP DATA FIRST. Run once per project.

BEGIN;

DROP TABLE IF EXISTS payments CASCADE;

DROP TABLE IF EXISTS bookings CASCADE;

CREATE TABLE bookings (
    id BIGSERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    service TEXT NOT NULL,
    date DATE NOT NULL DEFAULT (CURRENT_DATE),
    status TEXT NOT NULL DEFAULT 'pending'
);

CREATE INDEX idx_bookings_status ON bookings (status);
CREATE INDEX idx_bookings_date ON bookings (date DESC);

ALTER TABLE bookings DISABLE ROW LEVEL SECURITY;

COMMIT;
