-- ============================================================
-- Yasmin & Aliarose Dormitory System
-- PostgreSQL Schema for Supabase
-- Paste this entire file into: Supabase → SQL Editor → New Query
-- ============================================================

-- Drop tables if they exist (safe re-run)
DROP TABLE IF EXISTS messages CASCADE;
DROP TABLE IF EXISTS chats CASCADE;
DROP TABLE IF EXISTS request_out_requests CASCADE;
DROP TABLE IF EXISTS transfer_requests CASCADE;
DROP TABLE IF EXISTS payments CASCADE;
DROP TABLE IF EXISTS bookings CASCADE;
DROP TABLE IF EXISTS beds CASCADE;
DROP TABLE IF EXISTS rooms CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS admins CASCADE;

-- ============================================================
-- ADMINS
-- ============================================================
CREATE TABLE admins (
  id         SERIAL PRIMARY KEY,
  username   VARCHAR(100) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
  id                VARCHAR(255) DEFAULT NULL,
  username          VARCHAR(100) NOT NULL UNIQUE,
  password          VARCHAR(255) NOT NULL,
  role              VARCHAR(30)  DEFAULT 'user',
  full_name         VARCHAR(255) DEFAULT NULL,
  email             VARCHAR(255) DEFAULT NULL,
  phone             VARCHAR(60)  DEFAULT NULL,
  address           TEXT         DEFAULT NULL,
  emergency_contact VARCHAR(255) DEFAULT NULL,
  created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE users ADD COLUMN IF NOT EXISTS id SERIAL;
ALTER TABLE users ADD PRIMARY KEY (id);

-- ============================================================
-- ROOMS
-- ============================================================
CREATE TABLE rooms (
  id         SERIAL PRIMARY KEY,
  room_no    VARCHAR(30) NOT NULL UNIQUE,
  floor_no   INTEGER     NOT NULL,
  capacity   INTEGER     DEFAULT 0,
  status     VARCHAR(30) DEFAULT 'Available',
  created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- BEDS
-- ============================================================
CREATE TABLE beds (
  id          SERIAL PRIMARY KEY,
  room_id     INTEGER     NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
  floor_id    INTEGER     DEFAULT NULL,
  bed_no      VARCHAR(30) NOT NULL,
  status      VARCHAR(30) DEFAULT 'Available',
  reserved_at TIMESTAMP   NULL,
  created_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_beds_room ON beds(room_id);

-- ============================================================
-- BOOKINGS
-- ============================================================
CREATE TABLE bookings (
  id              SERIAL PRIMARY KEY,
  user_id         INTEGER         DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
  bed_id          INTEGER         DEFAULT NULL REFERENCES beds(id) ON DELETE SET NULL,
  booking_ref     VARCHAR(100)    DEFAULT NULL,
  full_name       VARCHAR(255)    DEFAULT NULL,
  contact_number  VARCHAR(100)    DEFAULT NULL,
  contact_no      VARCHAR(100)    DEFAULT NULL,
  guardian_name   VARCHAR(255)    DEFAULT NULL,
  guardian_contact VARCHAR(100)   DEFAULT NULL,
  category        VARCHAR(100)    DEFAULT NULL,
  payment_method  VARCHAR(100)    DEFAULT NULL,
  receipt_photo   VARCHAR(255)    DEFAULT NULL,
  receipt_path    VARCHAR(255)    DEFAULT NULL,
  booking_status  VARCHAR(40)     DEFAULT 'Pending',
  payment_status  VARCHAR(40)     DEFAULT 'Pending',
  monthly_rent    DECIMAL(10,2)   DEFAULT 0.00,
  current_balance DECIMAL(10,2)   DEFAULT 0.00,
  move_in_date    DATE            NULL,
  move_out_date   DATE            NULL,
  reserve_at      TIMESTAMP       NULL,
  due_date        DATE            NULL,
  remarks         TEXT            NULL,
  school_name     VARCHAR(255)    DEFAULT NULL,
  created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_bookings_user ON bookings(user_id);
CREATE INDEX idx_bookings_bed  ON bookings(bed_id);

-- ============================================================
-- PAYMENTS
-- ============================================================
CREATE TABLE payments (
  id             SERIAL PRIMARY KEY,
  booking_id     INTEGER       NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
  amount         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(100)  DEFAULT NULL,
  notes          TEXT          NULL,
  receipt_path   VARCHAR(255)  DEFAULT NULL,
  paid_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  payment_date   DATE          NULL,
  created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_payments_booking ON payments(booking_id);

-- ============================================================
-- TRANSFER REQUESTS
-- ============================================================
CREATE TABLE transfer_requests (
  id                  SERIAL PRIMARY KEY,
  user_id             INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  booking_id          INTEGER      NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
  requested_floor     INTEGER      NOT NULL,
  requested_room_id   INTEGER      NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
  requested_bed_type  VARCHAR(100) DEFAULT NULL,
  reason              TEXT         NOT NULL,
  status              VARCHAR(30)  DEFAULT 'Pending',
  created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_transfer_user    ON transfer_requests(user_id);
CREATE INDEX idx_transfer_booking ON transfer_requests(booking_id);
CREATE INDEX idx_transfer_room    ON transfer_requests(requested_room_id);

-- ============================================================
-- REQUEST OUT REQUESTS
-- ============================================================
CREATE TABLE request_out_requests (
  id               SERIAL PRIMARY KEY,
  user_id          INTEGER   NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  booking_id       INTEGER   NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
  request_out_date DATE      NOT NULL,
  reason           TEXT      NOT NULL,
  status           VARCHAR(30) DEFAULT 'Pending',
  reviewed_by      INTEGER   NULL REFERENCES admins(id) ON DELETE SET NULL,
  reviewed_at      TIMESTAMP NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_request_out_user    ON request_out_requests(user_id);
CREATE INDEX idx_request_out_booking ON request_out_requests(booking_id);
CREATE INDEX idx_request_out_admin   ON request_out_requests(reviewed_by);

-- ============================================================
-- CHATS
-- ============================================================
CREATE TABLE chats (
  id           SERIAL PRIMARY KEY,
  user_id      INTEGER   NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  last_message TEXT      NULL,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_chats_user ON chats(user_id);

-- ============================================================
-- MESSAGES
-- ============================================================
CREATE TABLE messages (
  id          SERIAL PRIMARY KEY,
  chat_id     INTEGER      NOT NULL REFERENCES chats(id) ON DELETE CASCADE,
  sender_type VARCHAR(30)  NOT NULL,
  sender_id   INTEGER      DEFAULT NULL,
  receiver_id INTEGER      DEFAULT NULL,
  message     TEXT         NULL,
  file_url    VARCHAR(255) DEFAULT NULL,
  is_voice    BOOLEAN      DEFAULT FALSE,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_messages_chat ON messages(chat_id);

-- ============================================================
-- SEED DATA: Default Admin Account
-- Password is: admin123  (bcrypt hash)
-- CHANGE THIS PASSWORD after first login!
-- ============================================================
INSERT INTO admins (username, password)
VALUES (
  'admin',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);

-- ============================================================
-- SEED DATA: Sample Rooms (2nd Floor)
-- ============================================================
INSERT INTO rooms (room_no, floor_no, capacity, status) VALUES
  ('201', 2, 2, 'Available'),
  ('202', 2, 2, 'Available'),
  ('203', 2, 2, 'Available'),
  ('301', 3, 2, 'Available'),
  ('302', 3, 2, 'Available');

-- ============================================================
-- SEED DATA: Beds for each room
-- ============================================================
INSERT INTO beds (room_id, floor_id, bed_no, status) VALUES
  (1, 2, '1', 'Available'),
  (1, 2, '2', 'Available'),
  (2, 2, '1', 'Available'),
  (2, 2, '2', 'Available'),
  (3, 2, '1', 'Available'),
  (3, 2, '2', 'Available'),
  (4, 3, '1', 'Available'),
  (4, 3, '2', 'Available'),
  (5, 3, '1', 'Available'),
  (5, 3, '2', 'Available');

-- Done! Schema created successfully.
