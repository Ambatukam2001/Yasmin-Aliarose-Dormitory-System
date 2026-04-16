<?php
// Global path handler
$current_page = $_SERVER['PHP_SELF'];
$base_dir = (strpos($current_page, '/admin/') !== false) ? '../' : '';

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    // Session optimizations for Vercel/Stateless environments
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_path', '/');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
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
    // If it's already an absolute URL, just go there
    if (preg_match('#^https?://#', $path)) {
        header("Location: $path");
        exit;
    }

    // Resolve ../ paths manually if needed, or just let the browser handle it
    // On Vercel, we often want to redirect to the root-relative path.
    $clean_path = ltrim($path, '/');
    
    // If we are in /admin/ we might need to go up
    $is_admin = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false);
    
    if (strpos($path, '../') === 0) {
        $final_path = '/' . ltrim(str_replace('../', '', $path), '/');
    } elseif ($is_admin && strpos($path, 'admin/') === false && $path !== 'logout.php') {
        // If in admin and path is not admin-relative, it's likely root-relative
        $final_path = '/' . $clean_path;
    } else {
        $final_path = $path;
    }

    header("Location: $final_path");
    exit;
}


/**
 * Database Helpers (Optional but cleaner)
 */
function get_stats($conn) {
    // Optimization: Run all counts in a single query to reduce latency between Vercel and Supabase
    $sql = "SELECT 
        (SELECT COUNT(*) FROM rooms) as rooms,
        (SELECT COUNT(*) FROM beds) as beds,
        (SELECT COUNT(*) FROM beds WHERE status = 'Occupied') as occupied,
        (SELECT COALESCE(SUM(monthly_rent), 0) FROM bookings WHERE payment_status = 'Confirmed' AND booking_status = 'Active') as potential_revenue,
        (SELECT COUNT(*) FROM bookings WHERE due_date < CURRENT_DATE AND booking_status = 'Active' AND payment_status = 'Confirmed') as overdue_count";
    
    $stats = $conn->query($sql)->fetch();

    // Due this week needs a separate check for pgsql vs mysql date syntax
    $is_pgsql = (strpos($conn->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql') !== false);
    $due_week_q = $is_pgsql 
        ? "SELECT COUNT(*) FROM bookings WHERE due_date <= (CURRENT_DATE + INTERVAL '7 days') AND booking_status = 'Active'"
        : "SELECT COUNT(*) FROM bookings WHERE due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) AND booking_status = 'Active'";
    
    $stats['due_this_week'] = $conn->query($due_week_q)->fetchColumn() ?: 0;

    return $stats;
}

?>
