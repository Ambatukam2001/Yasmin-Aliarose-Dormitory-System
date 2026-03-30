<?php
/**
 * Admin Users (Residents) Standalone
 */
require_once '../api/core.php';
require_admin_auth();
require_once 'actions.php';

$route = 'users'; // sidebar will need to handle this
$users = $conn->query("SELECT * FROM bookings WHERE booking_status = 'Active' ORDER BY full_name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Directory | <?php echo $site_name; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-page">
    <header class="mobile-admin-header">
        <div class="logo-stack-mini" style="background: rgba(255,255,255,0.2); width: 35px; height: 35px; border-radius: 50%; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-hotel" style="font-size: 0.85rem; color: #fff;"></i>
        </div>
        <span class="font-bold text-sm tracking-wide">ADMIN PORTAL</span>
        <button id="sidebarToggleBtn" class="sidebar-toggle" onclick="toggleSidebar(event)"><i class="fas fa-bars"></i></button>
    </header>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="view-header">
            <div>
                <h1>Resident Directory <i class="fas fa-users color-primary"></i></h1>
                <p>Manage all active residents and their information.</p>
            </div>
        </header>

        <div class="table-container main-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Resident Name</th>
                        <th>Contact</th>
                        <th>Guardian</th>
                        <th>Resident Type</th>
                        <th>Joined Date</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" class="text-center py-8 text-muted">No active residents found.</td></tr>
                    <?php else: foreach($users as $u): ?>
                    <tr>
                        <td class="font-bold"><?php echo $u['full_name']; ?></td>
                        <td class="font-mono text-xs"><?php echo $u['contact_number']; ?></td>
                        <td><?php echo $u['guardian_name']; ?> (<?php echo $u['guardian_contact']; ?>)</td>
                        <td><span class="badge badge-info"><?php echo $u['category']; ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        <td class="text-right">
                            <a href="bookings.php?status=all" class="btn-action btn-outline">Profile</a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
</body>
</html>
