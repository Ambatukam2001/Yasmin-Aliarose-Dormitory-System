-- Yasmin & Aliarose Dormitory System
-- Consolidated database structure (PostgreSQL/Supabase Compatible)

-- 1. Admins Table
CREATE TABLE IF NOT EXISTS admins (
  id SERIAL PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Users Table
CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(30) DEFAULT 'user',
  full_name VARCHAR(255) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(60) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  emergency_contact VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Rooms Table
-- Note: Room number is unique PER FLOOR (allows Room 1 on every floor)
CREATE TABLE IF NOT EXISTS rooms (
  id SERIAL PRIMARY KEY,
  room_no VARCHAR(30) NOT NULL,
  floor_no INT NOT NULL,
  capacity INT DEFAULT 0,
  status VARCHAR(30) DEFAULT 'Available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uniq_room_floor UNIQUE (room_no, floor_no)
);

-- 4. Beds Table
CREATE TABLE IF NOT EXISTS beds (
  id SERIAL PRIMARY KEY,
  room_id INT NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
  floor_id INT DEFAULT NULL,
  bed_no VARCHAR(30) NOT NULL,
  status VARCHAR(30) DEFAULT 'Available',
  reserved_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Bookings Table
CREATE TABLE IF NOT EXISTS bookings (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE SET NULL,
  bed_id INT REFERENCES beds(id) ON DELETE SET NULL,
  booking_ref VARCHAR(100) DEFAULT NULL,
  full_name VARCHAR(255) DEFAULT NULL,
  contact_number VARCHAR(100) DEFAULT NULL,
  contact_no VARCHAR(100) DEFAULT NULL,
  guardian_name VARCHAR(255) DEFAULT NULL,
  guardian_contact VARCHAR(100) DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  payment_method VARCHAR(100) DEFAULT NULL,
  receipt_photo VARCHAR(255) DEFAULT NULL,
  receipt_path VARCHAR(255) DEFAULT NULL,
  booking_status VARCHAR(40) DEFAULT 'Pending',
  payment_status VARCHAR(40) DEFAULT 'Pending',
  monthly_rent DECIMAL(10,2) DEFAULT 0.00,
  current_balance DECIMAL(10,2) DEFAULT 0.00,
  move_in_date DATE NULL,
  move_out_date DATE NULL,
  due_date DATE NULL,
  remarks TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Payments Table
CREATE TABLE IF NOT EXISTS payments (
  id SERIAL PRIMARY KEY,
  booking_id INT NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(100) DEFAULT NULL,
  notes TEXT NULL,
  receipt_path VARCHAR(255) DEFAULT NULL,
  paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  payment_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. Transfer Requests Table
CREATE TABLE IF NOT EXISTS transfer_requests (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  booking_id INT REFERENCES bookings(id) ON DELETE CASCADE,
  requested_floor INT NOT NULL,
  requested_room_id INT REFERENCES rooms(id) ON DELETE CASCADE,
  requested_bed_type VARCHAR(100) DEFAULT NULL,
  reason TEXT NOT NULL,
  status VARCHAR(30) DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. Request Out Requests Table
CREATE TABLE IF NOT EXISTS request_out_requests (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  booking_id INT REFERENCES bookings(id) ON DELETE CASCADE,
  request_out_date DATE NOT NULL,
  reason TEXT NOT NULL,
  status VARCHAR(30) DEFAULT 'Pending',
  reviewed_by INT REFERENCES admins(id) ON DELETE SET NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. Chats Table
CREATE TABLE IF NOT EXISTS chats (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  last_message TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 10. Messages Table
CREATE TABLE IF NOT EXISTS messages (
  id SERIAL PRIMARY KEY,
  chat_id INT NOT NULL REFERENCES chats(id) ON DELETE CASCADE,
  sender_type VARCHAR(30) NOT NULL,
  sender_id INT DEFAULT NULL,
  receiver_id INT DEFAULT NULL,
  message TEXT NULL,
  file_url VARCHAR(255) DEFAULT NULL,
  is_voice BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 11. Sessions Table (for Vercel persistence)
CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(255) PRIMARY KEY,
  data TEXT NOT NULL,
  last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create some useful indexes for speed
CREATE INDEX IF NOT EXISTS idx_beds_room ON beds(room_id);
CREATE INDEX IF NOT EXISTS idx_bookings_user ON bookings(user_id);
CREATE INDEX IF NOT EXISTS idx_bookings_bed ON bookings(bed_id);
CREATE INDEX IF NOT EXISTS idx_payments_booking ON payments(booking_id);
CREATE INDEX IF NOT EXISTS idx_messages_chat ON messages(chat_id);
CREATE INDEX IF NOT EXISTS idx_sessions_access ON sessions(last_accessed);
