<?php
/**
 * Admin Settings Standalone
 */
require_once '../api/core.php';
require_admin_auth();
require_once 'actions.php';

$route = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings | <?php echo $site_name; ?></title>
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
                <h1>System Settings <i class="fas fa-cog color-primary"></i></h1>
                <p>Manage security protocols and account preferences.</p>
            </div>
        </header>

        <div class="grid grid-2" style="gap: 2rem;">
            <!-- Security Card -->
            <div class="settings-section main-card">
                <h3 class="font-bold mb-2">Security Update <i class="fas fa-user-shield color-primary"></i></h3>
                <p class="text-sm text-muted mb-4">Manage your admin access password.</p>
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-group mb-4">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="input-text w-full" required placeholder="••••••••">
                    </div>
                    <div class="form-group mb-4">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="input-text w-full" required placeholder="Enter new password">
                    </div>
                    <div class="form-group mb-6">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="input-text w-full" required placeholder="Retype new password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">Update Password</button>
                </form>
            </div>

            <!-- Danger Zone -->
            <div class="settings-section danger-zone main-card">
                <h3 class="font-bold text-danger mb-2">Danger Zone <i class="fas fa-trash-alt"></i></h3>
                <p class="text-sm text-muted mb-4">Permanent account deletion.</p>
                <div class="alert alert-danger mb-6">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Wait! Deleting your account will revoke all your admin access to the dashboard.</span>
                </div>
                <form method="POST" onsubmit="return confirm('PERMANENTLY delete your account?')">
                    <input type="hidden" name="delete_account" value="1">
                    <button type="submit" class="btn btn-danger btn-full">Permanently Delete Account</button>
                </form>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
</body>
</html>
