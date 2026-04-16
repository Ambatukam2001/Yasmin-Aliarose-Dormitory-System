<?php
// Base path is now handled by core.php
?>

<!-- Component Specific Styles -->
<style>
    /* Profile Dropdown Component Styles */
    .profile-dropdown {
        position: relative;
        display: inline-block;
    }

    .profile-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: var(--white);
        border: 1px solid var(--glass-border);
        padding: 0.35rem 0.85rem 0.35rem 0.35rem;
        border-radius: 2rem;
        cursor: pointer;
        transition: var(--transition-base);
        color: var(--text-primary);
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        box-shadow: var(--shadow-sm);
    }
    
    .profile-btn:hover {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .profile-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: bold;
    }

    .dropdown-menu {
        position: absolute;
        top: calc(100% + 0.5rem);
        right: 0;
        background: var(--white);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-lg);
        min-width: 190px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.2s ease;
        z-index: 1000;
        overflow: hidden;
    }

    .dropdown-menu.active {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.9rem 1.25rem;
        color: var(--text-secondary);
        text-decoration: none;
        transition: var(--transition-base);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .dropdown-item:not(:last-child) {
        border-bottom: 1px solid rgba(0,0,0,0.03);
    }

    .dropdown-item:hover {
        background: var(--primary-subtle);
        color: var(--primary);
    }
    
    .dropdown-item i {
        width: 16px;
        text-align: center;
        font-size: 1rem;
    }

    .logo img {
        height: 35px !important;
        width: 35px !important;
    }

    /* Theme dropdown (replaces pill toggle) */
    .theme-dropdown-wrap {
        position: relative;
        margin-left: 0.5rem;
    }

    .theme-dropdown-trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-width: 2.5rem;
        height: 2.5rem;
        padding: 0 0.65rem;
        border-radius: 999px;
        border: 1px solid var(--glass-border);
        background: var(--white);
        color: var(--text-primary);
        cursor: pointer;
        font-size: 0.95rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition-base);
    }

    .theme-dropdown-trigger:hover {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    }

    .theme-dropdown-trigger .theme-dd-caret {
        font-size: 0.65rem;
        opacity: 0.55;
    }

    .theme-dropdown-panel {
        position: absolute;
        top: calc(100% + 0.45rem);
        right: 0;
        min-width: 11rem;
        background: var(--white);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-lg);
        padding: 0.35rem;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-6px);
        transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s;
        z-index: 1200;
    }

    .theme-dropdown-panel.open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .theme-dropdown-panel button {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        width: 100%;
        text-align: left;
        padding: 0.65rem 0.85rem;
        border: none;
        background: transparent;
        border-radius: 0.45rem;
        font-family: inherit;
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--text-secondary);
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
    }

    .theme-dropdown-panel button:hover {
        background: var(--primary-subtle);
        color: var(--primary-dark);
    }

    .theme-dropdown-panel button.is-active {
        color: var(--primary-dark);
        background: rgba(16, 185, 129, 0.08);
    }

    .dark-theme .theme-dropdown-trigger {
        background: #0f172a;
        border-color: rgba(255, 255, 255, 0.1);
        color: #f8fafc;
    }

    .dark-theme .theme-dropdown-panel {
        background: #0f172a;
        border-color: rgba(255, 255, 255, 0.1);
    }

    .dark-theme .theme-dropdown-panel button {
        color: #cbd5e1;
    }

    .dark-theme .theme-dropdown-panel button:hover {
        background: rgba(16, 185, 129, 0.12);
        color: #f8fafc;
    }

    @media (max-width: 768px) {
        .theme-dropdown-panel {
            right: auto;
            left: 50%;
            transform: translate(-50%, -6px);
        }
        .theme-dropdown-panel.open {
            transform: translate(-50%, 0);
        }
    }
</style>

<header>
    <div class="container">
        <a href="<?php echo $base_dir; ?>index.php" class="logo">
            <img src="<?php echo $base_dir; ?>assets/images/logo.svg" alt="YA Dormitory Logo" style="object-fit:contain; background:transparent; border-radius:0; box-shadow:none;">
            <span>YA Dormitory</span>
        </a>

        <nav>
            <ul class="nav-links">
                <li class="nav-mobile-header" onclick="toggleMenu()">
                    <div></div> <!-- spacing -->
                    <i class="fas fa-times"></i>
                </li>
                <li><a href="<?php echo $base_dir; ?>index.php#overview" onclick="toggleMenu()">Overview</a></li>
                <li><a href="<?php echo $base_dir; ?>index.php#gallery" onclick="toggleMenu()">Gallery</a></li>
                <li><a href="<?php echo $base_dir; ?>booking.php" style="white-space: nowrap;" onclick="return YA_DORM.handleBookingClick(event)">Book Now</a></li>
                <li><a href="<?php echo $base_dir; ?>index.php#benefits" onclick="toggleMenu()">Benefits</a></li>
                <li><a href="<?php echo $base_dir; ?>index.php#rules" onclick="toggleMenu()">Rules</a></li>
                <?php if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])): 
                    $is_admin = isset($_SESSION['admin_id']);
                ?>
                    <li class="mobile-only-link" style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 0.5rem; padding-top: 0.5rem;">
                        <?php if(!$is_admin): ?>
                            <a href="<?php echo $base_dir; ?>profile.php" onclick="toggleMenu()"><i class="fas fa-user-circle"></i> My Profile</a>
                        <?php else: ?>
                            <a href="<?php echo $base_dir; ?>admin/dashboard.php" onclick="toggleMenu()"><i class="fas fa-chart-line"></i> Dashboard</a>
                        <?php endif; ?>
                    </li>
                    <li class="mobile-only-link">
                        <a href="<?php echo $base_dir; ?>change_password.php" onclick="toggleMenu()"><i class="fas fa-key"></i> Passwords</a>
                    </li>
                    <li class="mobile-only-link">
                        <a href="<?php echo $base_dir; ?>logout.php" onclick="toggleMenu()" style="color: #fca5a5;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="header-right">
            <div class="auth-buttons">
                <a href="<?php echo $base_dir; ?>booking.php" class="btn btn-primary shadow-sm" style="white-space: nowrap;" onclick="return YA_DORM.handleBookingClick(event)">Book Now</a>
                <?php if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])): 
                    $is_admin = isset($_SESSION['admin_id']);
                    $dashboard_url = $is_admin ? $base_dir . 'admin/dashboard.php' : $base_dir . 'profile.php';
                    $display_name = $is_admin ? 'Admin' : ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User');
                    $initial = strtoupper(substr($display_name, 0, 1));
                ?>
                    <div class="profile-dropdown" id="profileDropdown">
                        <button class="profile-btn" onclick="toggleProfileDropdown(event)">
                            <div class="profile-avatar"><?php echo $initial; ?></div>
                            <span style="font-size: 0.85rem;"><?php echo htmlspecialchars($display_name); ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 0.7rem; opacity: 0.5;"></i>
                        </button>
                        <div class="dropdown-menu" id="profileDropdownMenu">
                            <?php if(!$is_admin): ?>
                            <a href="<?php echo $base_dir; ?>profile.php" class="dropdown-item"><i class="fas fa-user-circle"></i> My Profile</a>
                            <?php else: ?>
                            <a href="<?php echo $dashboard_url; ?>" class="dropdown-item"><i class="fas fa-chart-line"></i> Dashboard</a>
                            <?php endif; ?>
                            <a href="<?php echo $base_dir; ?>change_password.php" class="dropdown-item"><i class="fas fa-key"></i> Change Password</a>
                            <a href="<?php echo $base_dir; ?>logout.php" class="dropdown-item" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo $base_dir; ?>login.php" class="btn btn-outline" style="white-space: nowrap;">Login</a>
                <?php endif; ?>
            </div>
            
            <div class="theme-dropdown-wrap" id="themeDropdownWrap">
                <button type="button" class="theme-dropdown-trigger" id="themeDropdownBtn" onclick="toggleThemeDropdown(event)" aria-expanded="false" aria-haspopup="true" aria-label="Theme menu">
                    <i class="fas fa-circle-half-stroke" id="themeDropdownIcon" aria-hidden="true"></i>
                    <span class="theme-dd-caret"><i class="fas fa-chevron-down"></i></span>
                </button>
                <div class="theme-dropdown-panel" id="themeDropdownPanel" role="menu">
                    <button type="button" role="menuitem" data-theme="light" onclick="setThemeChoice('light')">
                        <i class="fas fa-sun" style="color:#f59e0b;width:1rem;text-align:center;"></i> Light
                    </button>
                    <button type="button" role="menuitem" data-theme="dark" onclick="setThemeChoice('dark')">
                        <i class="fas fa-moon" style="color:#94a3b8;width:1rem;text-align:center;"></i> Dark
                    </button>
                </div>
            </div>

            <button class="menu-toggle" onclick="toggleMenu()" aria-label="Open menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
</header>

<script src="<?php echo $base_dir; ?>assets/js/common.js"></script>

<script>
    const IS_LOGGED_IN = <?php echo (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) ? 'true' : 'false'; ?>;
    const IS_ADMIN = <?php echo isset($_SESSION['admin_id']) ? 'true' : 'false'; ?>;

    function toggleMenu() {
        const nav = document.querySelector('.nav-links');
        if (nav) nav.classList.toggle('active');
    }

    function setThemeChoice(mode) {
        const isDark = mode === 'dark';
        document.body.classList.toggle('dark-theme', isDark);
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateThemeDropdown(isDark);
        closeThemeDropdown();
    }

    function toggleThemeDropdown(e) {
        if (e) e.stopPropagation();
        const panel = document.getElementById('themeDropdownPanel');
        const btn = document.getElementById('themeDropdownBtn');
        if (!panel || !btn) return;
        const open = panel.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function closeThemeDropdown() {
        const panel = document.getElementById('themeDropdownPanel');
        const btn = document.getElementById('themeDropdownBtn');
        if (panel) panel.classList.remove('open');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    function updateThemeDropdown(isDark) {
        const panel = document.getElementById('themeDropdownPanel');
        if (!panel) return;
        panel.querySelectorAll('button[data-theme]').forEach(function (b) {
            var t = b.getAttribute('data-theme');
            b.classList.toggle('is-active', (t === 'dark') ? isDark : !isDark);
        });
        const icon = document.getElementById('themeDropdownIcon');
        if (icon) {
            icon.className = 'fas ' + (isDark ? 'fa-moon' : 'fa-sun');
        }
    }

    function toggleProfileDropdown(e) {
        e.stopPropagation();
        const menu = document.getElementById('profileDropdownMenu');
        if (menu) menu.classList.toggle('active');
    }
    
    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('profileDropdownMenu');
        if(dropdown && dropdown.classList.contains('active') && !e.target.closest('#profileDropdown')) {
            dropdown.classList.remove('active');
        }
        if (!e.target.closest('#themeDropdownWrap')) {
            closeThemeDropdown();
        }
    });

    // Check theme consistency on load
    document.addEventListener('DOMContentLoaded', () => {
        const isDark = localStorage.getItem('theme') === 'dark';
        if (isDark) {
            document.body.classList.add('dark-theme');
        }
        updateThemeDropdown(document.body.classList.contains('dark-theme'));
    });
</script>
