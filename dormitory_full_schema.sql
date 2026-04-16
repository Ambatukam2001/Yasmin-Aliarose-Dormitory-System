-- Yasmin & Aliarose Dormitory System
-- Consolidated database structure
-- Import with: mysql -u root -p dormitory_db < dormitory_full_schema.sql

CREATE DATABASE IF NOT EXISTS dormitory_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE dormitory_db;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS admins (
  id INT NOT NULL AUTO_INCREMENT,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT NOT NULL AUTO_INCREMENT,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(30) DEFAULT 'user',
  full_name VARCHAR(255) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(60) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  emergency_contact VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rooms (
  id INT NOT NULL AUTO_INCREMENT,
  room_no VARCHAR(30) NOT NULL,
  floor_no INT NOT NULL,
  capacity INT DEFAULT 0,
  status VARCHAR(30) DEFAULT 'Available',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_room_no (room_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS beds (
  id INT NOT NULL AUTO_INCREMENT,
  room_id INT NOT NULL,
  floor_id INT DEFAULT NULL,
  bed_no VARCHAR(30) NOT NULL,
  status VARCHAR(30) DEFAULT 'Available',
  reserved_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_beds_room (room_id),
  CONSTRAINT fk_beds_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookings (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT DEFAULT NULL,
  bed_id INT DEFAULT NULL,
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
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bookings_user (user_id),
  KEY idx_bookings_bed (bed_id),
  CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_bookings_bed FOREIGN KEY (bed_id) REFERENCES beds(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id INT NOT NULL AUTO_INCREMENT,
  booking_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(100) DEFAULT NULL,
  notes TEXT NULL,
  receipt_path VARCHAR(255) DEFAULT NULL,
  paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  payment_date DATE NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payments_booking (booking_id),
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transfer_requests (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  booking_id INT NOT NULL,
  requested_floor INT NOT NULL,
  requested_room_id INT NOT NULL,
  requested_bed_type VARCHAR(100) DEFAULT NULL,
  reason TEXT NOT NULL,
  status VARCHAR(30) DEFAULT 'Pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_transfer_user (user_id),
  KEY idx_transfer_booking (booking_id),
  KEY idx_transfer_room (requested_room_id),
  CONSTRAINT fk_transfer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_transfer_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_transfer_room FOREIGN KEY (requested_room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_out_requests (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  booking_id INT NOT NULL,
  request_out_date DATE NOT NULL,
  reason TEXT NOT NULL,
  status VARCHAR(30) DEFAULT 'Pending',
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_request_out_user (user_id),
  KEY idx_request_out_booking (booking_id),
  KEY idx_request_out_admin (reviewed_by),
  CONSTRAINT fk_request_out_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_request_out_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_request_out_admin FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chats (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  last_message TEXT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chats_user (user_id),
  CONSTRAINT fk_chats_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
  id INT NOT NULL AUTO_INCREMENT,
  chat_id INT NOT NULL,
  sender_type VARCHAR(30) NOT NULL,
  sender_id INT DEFAULT NULL,
  receiver_id INT DEFAULT NULL,
  message TEXT NULL,
  file_url VARCHAR(255) DEFAULT NULL,
  is_voice TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_messages_chat (chat_id),
  CONSTRAINT fk_messages_chat FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
