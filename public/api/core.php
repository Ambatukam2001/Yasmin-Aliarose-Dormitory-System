<?php
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
    if (!is_admin_logged_in()) {
        // If we're in a subdirectory (like admin/), go back up
        $login_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../login.php' : 'login.php';
        header("Location: $login_path");
        exit;
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
    header("Location: $path");
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
