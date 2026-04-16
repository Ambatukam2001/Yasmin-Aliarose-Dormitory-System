<?php
// Global path handler
$current_page = $_SERVER['PHP_SELF'];
$base_dir = (strpos($current_page, '/admin/') !== false) ? '../' : '';

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Authentication Helpers
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']);
}

function require_admin_auth() {
    if (!isset($_SESSION['admin_id'])) {
        redirect('login.php');
    }
}

function require_user_auth() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
        redirect('login.php');
    }
}


/**
 * Utility Helpers
 */
function format_price($amount) {
    return '₱' . number_format($amount, 2);
}

function get_badge_class($status) {
    $status = strtolower($status);
    switch ($status) {
        case 'pending': return 'badge-pending';
        case 'confirmed': return 'badge-confirmed';
        case 'cancelled': return 'badge-cancelled';
        case 'occupied': return 'badge-cancelled'; // Using same red for full
        case 'available': return 'badge-confirmed'; // Using same green
        default: return 'badge-muted';
    }
}

function redirect($path) {
    // Build absolute URL to avoid browser treating Location as a file path
    if (!preg_match('#^https?://#', $path)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Find the project base (everything up to /public/)
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        // Determine if we're inside /admin/ subfolder
        if (strpos($path, '../') === 0) {
            // Caller already prefixed ../  — resolve against current dir
            $base = rtrim(dirname($script), '/');
            $path = ltrim(str_replace('../', '', $path), '/');
            $base = dirname($base); // go up one level
            $absPath = $scheme . '://' . $host . $base . '/' . $path;
        } else {
            // Absolute from /public/ root
            $pubRoot = preg_replace('#/public/.*#', '/public', $script);
            $absPath = $scheme . '://' . $host . $pubRoot . '/' . ltrim($path, '/');
        }
        header("Location: $absPath");
    } else {
        header("Location: $path");
    }
    exit;
}

/**
 * Database Helpers (Optional but cleaner)
 */
function get_stats($conn) {
    return [
        'rooms' => $conn->query("SELECT COUNT(*) FROM rooms")->fetch_row()[0] ?: 0,
        'beds' => $conn->query("SELECT COUNT(*) FROM beds")->fetch_row()[0] ?: 0,
        'occupied' => $conn->query("SELECT COUNT(*) FROM beds WHERE status = 'Occupied'")->fetch_row()[0] ?: 0,
        'potential_revenue' => $conn->query("SELECT SUM(monthly_rent) FROM bookings WHERE payment_status = 'Confirmed' AND booking_status = 'Active'")->fetch_row()[0] ?: 0,
        'overdue_count' => $conn->query("SELECT COUNT(*) FROM bookings WHERE due_date < NOW() AND booking_status = 'Active' AND payment_status = 'Confirmed'")->fetch_row()[0] ?: 0,
        'due_this_week' => $conn->query("SELECT COUNT(*) FROM bookings WHERE due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) AND booking_status = 'Active'")->fetch_row()[0] ?: 0
    ];
}
?>
