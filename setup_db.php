<?php
require_once __DIR__ . '/public/api/db.php';

// Users table (both admin and user can be here, or we can keep admins separate. User requested: "Allow users to log in through a login page. Admin should log in using the same login button... Implement role-based access")
// I will create `users` table for booking users. Since `admins` already exists, maybe we can combine them, or just use `users` as the unified table. Let's create `users` and just check if the username exists in `admins` or `users` during login. Or we can migrate `admins` to `users`. Let's create `users` with `role`='user'.

$conn->query("
CREATE TABLE IF NOT EXISTS users (
  id INT NOT NULL AUTO_INCREMENT,
  username VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'user',
  full_name VARCHAR(255) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  emergency_contact VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
");

// Safely add columns if they don't exist
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(50) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS emergency_contact VARCHAR(255) DEFAULT NULL");

$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS user_id INT NULL");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS monthly_rent DECIMAL(10,2) DEFAULT 0");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS due_date DATE NULL");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) DEFAULT 'Pending'");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS booking_status VARCHAR(50) DEFAULT 'Active'");


$conn->query("
CREATE TABLE IF NOT EXISTS chats (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  last_message TEXT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS messages (
  id INT NOT NULL AUTO_INCREMENT,
  chat_id INT NOT NULL,
  sender_type VARCHAR(50) NOT NULL,
  message TEXT,
  file_url VARCHAR(255),
  is_voice BOOLEAN DEFAULT FALSE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS request_out_requests (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  booking_id INT NOT NULL,
  request_out_date DATE NOT NULL,
  reason TEXT NOT NULL,
  status VARCHAR(20) DEFAULT 'Pending',
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
");

// Let's also check if user table exists and insert a dummy user if it's empty
$res = $conn->query("SELECT COUNT(*) FROM users");
if ($res) {
    if ($res->fetch_row()[0] == 0) {
        // password is 'password'
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, role) VALUES ('testuser', '$hash', 'user')");
    }
}

// In admins, let's also make sure admin exists
$res = $conn->query("SELECT COUNT(*) FROM admins");
if ($res) {
    if ($res->fetch_row()[0] == 0) {
        $hash = password_hash('admin', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO admins (username, password) VALUES ('admin', '$hash')");
    }
}

echo "Database updated successfully.\n";
?>
