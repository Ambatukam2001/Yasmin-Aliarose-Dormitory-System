<?php
require_once 'api/core.php';

if (is_admin_logged_in()) {
    redirect('admin/dashboard.php');
} elseif (isset($_SESSION['user_id'])) {
    redirect('index.php');
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // Check Admins
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['role'] = 'admin';
        redirect('admin/dashboard.php');
    } else {
        // Check Users
        $stmt_user = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt_user->bind_param("s", $username);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        $user = $result_user->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = 'user';
            
            if (isset($_SESSION['redirect_after_login'])) {
                $redirect = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                redirect($redirect);
            } else {
                redirect('index.php');
            }
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo $site_name; ?></title>
    
    <!-- External Assets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Project Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

<div class="split-login" id="authSplitRoot">
    <!-- Brand Info -->
    <div class="login-brand">
        <!-- Enhanced Circle-2 Motif -->
        <div class="decoration-circle dc-1"></div>
        <div class="decoration-circle dc-2"></div>
        <div class="decoration-circle dc-3"></div>

        <div class="z-10" style="position: relative; z-index: 5; text-align: center; display: flex; flex-direction: column; align-items: center;">
            <div class="logo-circle shadow-xl">
                <div class="logo-stack-large">
                    <div class="circle-pulse-main"></div>
                    <i class="fas fa-hotel"></i>
                </div>
            </div>
            <h1 class="text-5xl font-black mb-4 tracking-tighter" style="text-align: center; text-shadow: 0 10px 20px rgba(0,0,0,0.2);"><?php echo $site_name; ?></h1>
            <p class="text-lg opacity-80 max-w-400 font-medium" style="text-align: center; line-height: 1.6; letter-spacing: 0.02em;">Verified Management & Bedspacer System. Use your authorized credentials to access your portal.</p>
        </div>
    </div>

    <!-- Login Form -->
    <div class="login-form-side">
        <div class="login-box">
            <div class="text-center" style="margin-bottom: 3.5rem;">
                <h2 class="text-4xl font-black text-slate-800 tracking-tight mb-2">Login Portal</h2>
                <p class="text-secondary text-sm font-medium opacity-80 mb-6">Secure portal for users and administrators</p>
                <div style="height: 4px; width: 40px; background: var(--primary); border-radius: 99px; margin: 0 auto; opacity: 0.7;"></div>
            </div>

            <?php if ($error): ?>
                <div class="error-alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="authLoginForm">
                <div class="form-group mb-6">
                    <label style="margin-left: 0.5rem;">Username</label>
                    <div class="input-container">
                        <input type="text" name="username" required placeholder="Enter username" style="height: 3.5rem;">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="form-group mb-8">
                    <label style="margin-left: 0.5rem;">Password</label>
                    <div class="input-container">
                        <input type="password" name="password" required placeholder="••••••••" style="height: 3.5rem;">
                        <i class="fas fa-lock"></i>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-full" style="width: 100%; height: 3.75rem; font-size: 1.1rem;">Sign In <i class="fas fa-sign-in-alt" style="margin-left: 0.75rem;"></i></button>
            </form>
            <div class="text-center mt-4">
                <p class="text-secondary text-sm font-medium">Don't have an account? <a href="register.php" class="text-primary hover:underline">Register here</a></p>
            </div>
            
            <div class="text-center" style="margin-top: 5rem; padding-top: 2rem; border-top: 1px solid #f1f5f9;">
                <a href="index.php" class="text-secondary font-bold text-xs uppercase tracking-[0.2em] hover:text-emerald-600 transition-all flex items-center justify-center gap-3" style="text-decoration: none;">
                    <i class="fas fa-arrow-left" style="font-size: 0.85rem;"></i> Return to Main Website
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('authLoginForm');
    var root = document.getElementById('authSplitRoot');
    if (!form || !root) return;

    form.addEventListener('submit', function (e) {
        if (form.getAttribute('data-auth-submitting') === '1') return;
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;
        e.preventDefault();
        root.classList.add('auth-split-exit');
        document.body.classList.add('auth-split-exit-body');
        var ms = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 100 : 500;
        setTimeout(function () {
            form.setAttribute('data-auth-submitting', '1');
            if (typeof HTMLFormElement.prototype.submit === 'function') {
                HTMLFormElement.prototype.submit.call(form);
            }
        }, ms);
    });
})();
</script>

</body>
</html>
