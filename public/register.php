<?php
require_once 'api/core.php';

if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) {
    redirect('index.php');
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if username exists in admins or users
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? UNION SELECT id FROM admins WHERE username = ?");
        $stmt->execute([$username, $username]);
        $existing = $stmt->fetch();

        if ($existing) {
            $error = "Username is already taken.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_insert = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
            
            if ($stmt_insert->execute([$username, $hashed_password])) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | <?php echo $site_name; ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

<div class="split-login" id="authSplitRoot">
    <div class="login-brand">
        <div class="decoration-circle dc-1"></div>
        <div class="decoration-circle dc-2"></div>

        <div class="z-10" style="position: relative; z-index: 5; text-align: center; display: flex; flex-direction: column; align-items: center;">
            <div class="logo-circle shadow-xl">
                <div class="logo-stack-large">
                    <i class="fas fa-hotel"></i>
                </div>
            </div>
            <h1 class="text-5xl font-black mb-4 tracking-tighter" style="text-align: center; text-shadow: 0 10px 20px rgba(0,0,0,0.2);"><?php echo $site_name; ?></h1>
            <p class="text-lg opacity-80 max-w-400 font-medium" style="text-align: center; line-height: 1.6; letter-spacing: 0.02em;">Create an account to manage your bedspacer bookings and access the central dashboard.</p>
        </div>
    </div>

    <div class="login-form-side">
        <div class="login-box">
            <div class="text-center" style="margin-bottom: 3.5rem;">
                <h2 class="text-4xl font-black text-slate-800 tracking-tight mb-2">Register</h2>
                <p class="text-secondary text-sm font-medium opacity-80 mb-6">Join our community today</p>
                <div style="height: 4px; width: 40px; background: var(--primary); border-radius: 99px; margin: 0 auto; opacity: 0.7;"></div>
            </div>

            <?php if ($error): ?>
                <div class="error-alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="authRegisterForm">
                <div class="form-group mb-5">
                    <label style="margin-left: 0.5rem;">Username</label>
                    <div class="input-container">
                        <input type="text" name="username" required placeholder="Choose a username" style="height: 3.5rem;">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="form-group mb-5">
                    <label style="margin-left: 0.5rem;">Password</label>
                    <div class="input-container">
                        <input type="password" name="password" required placeholder="Create a password" style="height: 3.5rem;">
                        <i class="fas fa-lock"></i>
                    </div>
                </div>
                <div class="form-group mb-8">
                    <label style="margin-left: 0.5rem;">Confirm Password</label>
                    <div class="input-container">
                        <input type="password" name="confirm_password" required placeholder="Confirm your password" style="height: 3.5rem;">
                        <i class="fas fa-lock"></i>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-full" style="width: 100%; height: 3.75rem; font-size: 1.1rem;">Create Account <i class="fas fa-user-plus" style="margin-left: 0.75rem;"></i></button>
            </form>
            <div class="text-center mt-4">
                <p class="text-secondary text-sm font-medium">Already have an account? <a href="login.php" class="text-primary hover:underline">Log In here</a></p>
            </div>
            
            <div class="text-center" style="margin-top: 4rem; padding-top: 2rem; border-top: 1px solid #f1f5f9;">
                <a href="index.php" class="text-secondary font-bold text-xs uppercase tracking-[0.2em] hover:text-emerald-600 transition-all flex items-center justify-center gap-3" style="text-decoration: none;">
                    <i class="fas fa-arrow-left" style="font-size: 0.85rem;"></i> Return to Main Website
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('authRegisterForm');
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
