<?php
require_once 'api/core.php';

require_user_auth();

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    $is_admin = isset($_SESSION['admin_id']);
    $user_id = $is_admin ? $_SESSION['admin_id'] : $_SESSION['user_id'];
    $table = $is_admin ? 'admins' : 'users';

    if ($new_pass !== $confirm_pass) {
        $error = "New passwords do not match!";
    } elseif (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM $table WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($old_pass, $user['password'])) {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt_up = $conn->prepare("UPDATE $table SET password = ? WHERE id = ?");
            if ($stmt_up->execute([$new_hash, $user_id])) {
                $success = "Password changed successfully!";
            } else {
                $error = "Error updating password. Try again.";
            }
        } else {
            $error = "Incorrect current password!";
        }
    }
}

$page_title = "Change Password";
include 'api/head.php';
include 'api/header.php';
?>

<style>
    .auth-container { 
        display: flex; 
        min-height: calc(100vh - 200px); 
        align-items: center; 
        justify-content: center; 
        padding: 4rem 2rem; 
        background: var(--bg); 
        gap: 2.5rem;
        flex-wrap: wrap;
    }
    .dark-theme .auth-container { background: var(--bg-dark); }
    
    .auth-card { 
        background: white; 
        border-radius: 2rem; 
        box-shadow: 0 15px 35px rgba(0,0,0,0.05); 
        padding: 3.5rem; 
        width: 100%; 
        max-width: 480px; 
        border: 1px solid #f1f5f9; 
        flex: 1;
        min-width: 320px;
        position: relative;
        transition: transform 0.3s ease;
    }
    .auth-card:hover { transform: translateY(-5px); }
    .dark-theme .auth-card { background: #1e293b; border-color: #334155; }
    
    .danger-card {
        border-top: 4px solid #ef4444;
        background: radial-gradient(circle at top right, #fff1f2 0%, white 40%);
    }
    .dark-theme .danger-card {
        background: radial-gradient(circle at top right, rgba(239, 68, 68, 0.05) 0%, #1e293b 50%);
        border-color: rgba(239, 68, 68, 0.2);
    }
    
    .auth-title { font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 800; color: #1e293b; text-align: center; margin-bottom: 2rem; }
    .dark-theme .auth-title { color: #f8fafc; }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 600; color: #475569; }
    .dark-theme .form-group label { color: #cbd5e1; }
    .form-control { width: 100%; padding: 0.85rem 1.2rem; border-radius: 0.75rem; border: 1px solid #e2e8f0; font-family: 'Inter', sans-serif; font-size: 1rem; transition: all 0.3s ease; }
    .dark-theme .form-control { background: #0f172a; border-color: #334155; color: #f8fafc; }
    .form-control:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,0.1); }
    .alert { padding: 1rem 1.5rem; border-radius: 0.75rem; margin-bottom: 1.5rem; font-weight: 500; font-size: 0.95rem; }
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .dark-theme .alert-danger { background: rgba(239, 68, 68, 0.2); color: #f87171; border: none; }
    .dark-theme .alert-success { background: rgba(16, 185, 129, 0.2); color: #34d399; border: none; }
    .btn-submit { width: 100%; background: #10b981; color: white; border: none; padding: 1.15rem; border-radius: 0.75rem; font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; margin-top: 1rem; }
    .btn-submit:hover { background: #059669; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
</style>

<div class="auth-container">
    <!-- Card 1: Security -->
    <div class="auth-card">
        <h1 class="auth-title"><i class="fas fa-shield-alt" style="color: #10b981; margin-right: 0.5rem;"></i> Identity & Security</h1>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="old_password" class="form-control" required placeholder="Verify current credentials">
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" required placeholder="Choose a strong password">
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required placeholder="Re-type new password">
            </div>
            <button type="submit" class="btn-submit">Update Security Credentials</button>
        </form>
    </div>

    <!-- Card 2: Danger Zone -->
    <div class="auth-card danger-card">
        <h1 class="auth-title" style="color: #e11d48;"><i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i> Account Termination</h1>
        
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="background: #fff1f2; color: #e11d48; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1.5rem;">
                <i class="fas fa-user-slash"></i>
            </div>
            <p style="color: #475569; font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem;">Proceed with Caution</p>
            <p style="color: #64748b; font-size: 0.85rem; line-height: 1.6;">Deleting your account is permanent. This will erase your personal profile, active bookings, payment history, and all correspondence with management.</p>
        </div>

        <button type="button" onclick="confirmDeleteAccount()" style="width: 100%; border: 2px solid #fee2e2; background: white; color: #e11d48; border-radius: 1rem; padding: 1.15rem; cursor: pointer; transition: all 0.3s ease; font-weight: 800; font-family: 'Outfit', sans-serif;">
            <i class="fas fa-trash-alt" style="margin-right: 0.5rem;"></i> Terminate My Account
        </button>

        <p style="font-size: 0.75rem; color: #94a3b8; text-align: center; margin-top: 1.5rem; font-style: italic;">
            * This action cannot be undone under any circumstances.
        </p>
    </div>
</div>

<script>
    function confirmDeleteAccount() {
        Swal.fire({
            title: 'Are you absolutely sure?',
            text: "This will permanently delete your account and clear all history. This action CANNOT be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete my account',
            cancelButtonText: 'Cancel',
            backdrop: `rgba(15, 23, 42, 0.4) blur(12px)`
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Final Confirmation',
                    html: "Please type <b style='color:#e11d48'>DELETE</b> to confirm account deletion:",
                    input: 'text',
                    inputPlaceholder: 'Type DELETE here',
                    showCancelButton: true,
                    confirmButtonText: 'Delete Permanently',
                    confirmButtonColor: '#e11d48',
                    preConfirm: (value) => {
                        if (value !== 'DELETE') {
                            Swal.showValidationMessage('You must type DELETE to proceed');
                        }
                        return value;
                    }
                }).then((finalResult) => {
                    if (finalResult.isConfirmed) {
                        deleteAccount();
                    }
                });
            }
        });
    }

    async function deleteAccount() {
        Swal.fire({
            title: 'Deleting Account...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const response = await fetch('api/delete_account.php', { method: 'POST' });
            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Account Deleted',
                    text: 'Your account and data have been successfully removed.',
                    confirmButtonColor: '#10b981'
                }).then(() => {
                    window.location.href = 'index.php';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Operation Failed',
                    text: data.message || 'Could not process deletion request.'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Network Interruption',
                text: 'Safe connection lost. Please verify your internet and try again.'
            });
        }
    }
</script>

<?php include 'api/footer.php'; ?>
