<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-stack">
            <div class="logo-circle-inner"></div>
            <i class="fas fa-hotel"></i>
        </div>
        <span>DormAdmin</span>
    </div>
    <nav>
        <a href="dashboard.php" class="nav-item <?php echo $route === 'overview' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> <span>Overview</span>
        </a>
        <a href="bookings.php" class="nav-item <?php echo $route === 'bookings' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i> <span>Bookings</span>
        </a>
        <a href="users.php" class="nav-item <?php echo $route === 'users' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> <span>Residents</span>
        </a>
        <a href="room_management.php" class="nav-item <?php echo $route === 'rooms' ? 'active' : ''; ?>">
            <i class="fas fa-bed"></i> <span>Room Management</span>
        </a>
        <a href="settings.php" class="nav-item <?php echo $route === 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i> <span>Settings</span>
        </a>
        <div class="nav-logout">
            <button id="themeToggleBtnAdmin" onclick="toggleTheme()" class="nav-item" style="background:transparent; border:none; width:100%; text-align:left; cursor: pointer; display: flex; align-items: center; gap: 0.75rem; font-family: inherit; font-size: inherit;">
                <i class="fas fa-moon"></i> <span>Dark Mode</span>
            </button>
            <a href="logout.php" class="nav-item nav-item-logout">
                <i class="fas fa-sign-out-alt"></i> <span>Sign Out</span>
            </a>
        </div>
    </nav>
</aside>
