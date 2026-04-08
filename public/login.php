<?php
require_once 'api/core.php';

if (is_admin_logged_in()) {
    redirect('admin/dashboard.php');
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        redirect('admin/dashboard.php');
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | <?php echo $site_name; ?></title>
    
    <!-- External Assets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Project Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .split-login { display: flex; min-height: 100vh; width: 100%; font-family: 'Inter', sans-serif; }
        .login-brand { flex: 1.1; background: var(--primary); display: flex; align-items: center; justify-content: center; padding: 4rem; color: #fff; position: relative; overflow: hidden; }
        
        /* Circle-2 High-Aesthetic Decorative Elements */
        .decoration-circle { position: absolute; border-radius: 50%; border: 1px solid rgba(255,255,255,0.08); background: radial-gradient(circle, rgba(255,255,255,0.1), transparent); }
        .dc-1 { width: 600px; height: 600px; top: -200px; left: -200px; }
        .dc-2 { width: 400px; height: 400px; bottom: -100px; right: -150px; opacity: 0.6; }
        .dc-3 { width: 200px; height: 200px; top: 10%; right: 10%; opacity: 0.4; }
        
        .login-form-side { flex: 1; display: flex; align-items: center; justify-content: center; padding: 4rem; background: #fff; }
        .login-box { width: 100%; max-width: 420px; margin: 0 auto; }
        
        /* Premium Logo-Stack-Large */
        .logo-circle { width: 110px; height: 110px; background: #fff; border-radius: 2.5rem; position: relative; display: flex; align-items: center; justify-content: center; margin: 0 auto 3rem; transform: rotate(-5deg); transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .logo-circle:hover { transform: rotate(0) scale(1.05); }
        .logo-stack-large { position: relative; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
        .logo-stack-large i { font-size: 3.2rem; color: var(--primary); position: relative; z-index: 5; }
        .circle-pulse-main { position: absolute; inset: 15%; background: var(--primary-subtle); border-radius: 1.75rem; animation: circle-pulse-anim 3s infinite ease-in-out; opacity: 0.5; }
        
        @keyframes circle-pulse-anim {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.4); opacity: 0.1; }
        }

        .error-alert { background: #fee2e2; color: #991b1b; padding: 1.25rem; border-radius: 1rem; margin-bottom: 2rem; font-size: 0.9rem; display: flex; align-items: center; gap: 0.75rem; border: 1px solid #fecaca; }
        @media (max-width: 900px) { 
            .split-login { flex-direction: column; overflow-x: hidden; } 
            .login-brand { min-height: 40vh; padding: 3rem 1.5rem; } 
            .login-brand h1 { font-size: 2.75rem !important; }
            .login-brand p { font-size: 0.9rem; }
            .login-form-side { padding: 3rem 1.5rem; }
            .login-box { max-width: 320px; }
            .login-box h2 { font-size: 1.85rem !important; }
            .dc-1 { width: 300px; height: 300px; top: -100px; left: -100px; }
            .dc-2 { width: 200px; height: 200px; bottom: -50px; right: -50px; }
            .dc-3 { display: none; }
            .logo-circle { width: 80px; height: 80px; margin-bottom: 1.5rem; }
            .logo-stack-large i { font-size: 2rem; }
        }
        @media (max-width: 480px) {
            .login-brand h1 { font-size: 2.25rem !important; }
            .login-box h2 { font-size: 1.5rem !important; }
        }
    </style>
</head>
<body>

<div class="split-login">
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
            <p class="text-lg opacity-80 max-w-400 font-medium" style="text-align: center; line-height: 1.6; letter-spacing: 0.02em;">Verified Administrative Management System. Use your authorized credentials to access the central dashboard.</p>
        </div>
    </div>

    <!-- Login Form -->
    <div class="login-form-side">
        <div class="login-box" style="margin-top: -2rem;">
            <div class="text-center" style="margin-bottom: 4.5rem;">
                <h2 class="text-4xl font-black text-slate-800 tracking-tight mb-2">Admin Security</h2>
                <p class="text-secondary text-sm font-medium opacity-80 mb-6">Secure portal for authorized administrators</p>
                <div style="height: 4px; width: 40px; background: var(--primary); border-radius: 99px; margin: 0 auto; opacity: 0.7;"></div>
            </div>

            <?php if ($error): ?>
                <div class="error-alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group mb-6">
                    <label style="margin-left: 0.5rem;">Admin Username</label>
                    <div class="input-container">
                        <input type="text" name="username" required placeholder="Enter username" style="height: 3.5rem;">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="form-group mb-8">
                    <label style="margin-left: 0.5rem;">Security Password</label>
                    <div class="input-container">
                        <input type="password" name="password" required placeholder="••••••••" style="height: 3.5rem;">
                        <i class="fas fa-lock"></i>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-full" style="height: 3.75rem; font-size: 1.1rem;">Sign In to Dashboard <i class="fas fa-sign-in-alt" style="margin-left: 0.75rem;"></i></button>
            </form>
            
            <div class="text-center" style="margin-top: 5rem; padding-top: 2rem; border-top: 1px solid #f1f5f9;">
                <a href="index.html" class="text-secondary font-bold text-xs uppercase tracking-[0.2em] hover:text-emerald-600 transition-all flex items-center justify-center gap-3" style="text-decoration: none;">
                    <i class="fas fa-arrow-left" style="font-size: 0.85rem;"></i> Return to Main Website
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
