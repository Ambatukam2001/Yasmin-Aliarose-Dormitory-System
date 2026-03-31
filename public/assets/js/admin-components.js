/**
 * Admin Shared Components for Static Build
 */
const AdminComponents = {
    renderSidebar(activeRoute = 'dashboard') {
        const settings = DormState.data.settings;
        const navItems = [
            { id: 'dashboard', icon: 'fa-chart-pie', label: 'Overview', href: 'dashboard.html' },
            { id: 'bookings', icon: 'fa-calendar-check', label: 'Bookings', href: 'bookings.html' },
            { id: 'users', icon: 'fa-users', label: 'Residents', href: 'users.html' },
            { id: 'rooms', icon: 'fa-bed', label: 'Room Management', href: 'room_management.html' },
            { id: 'settings', icon: 'fa-cog', label: 'Settings', href: 'settings.html' },
        ];

        const sidebar = document.createElement('aside');
        sidebar.className = 'sidebar';
        sidebar.id = 'adminSidebar';

        const isDark = localStorage.getItem('theme') === 'dark';

        sidebar.innerHTML = `
            <div class="sidebar-logo">
                <div class="logo-stack">
                    <i class="fas fa-house" style="color:var(--primary); font-size: 1.5rem; position:relative; z-index:2;"></i>
                </div>
                <span>${settings.site_name.split(' ')[0]} Admin</span>
            </div>
            <nav id="adminNav">
                ${navItems.map(item => `
                    <a href="${item.href}" class="nav-item ${activeRoute === item.id ? 'active' : ''}">
                        <i class="fas ${item.icon}"></i>
                        <span>${item.label}</span>
                    </a>
                `).join('')}
                <div class="nav-logout" style="margin-top:auto; padding-top:2rem; border-top:1px solid rgba(255,255,255,0.05);">
                    <div class="nav-item" style="justify-content: space-between; cursor:default; background:transparent !important;">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                             <span>Dark Mode</span>
                        </div>
                        <label class="theme-switch-pill" style="height:24px; width:48px; position:relative; display:inline-block; cursor:pointer;">
                            <input type="checkbox" id="adminThemeToggle" onclick="AdminComponents.toggleTheme()" style="display:none;" ${isDark ? 'checked' : ''} />
                            <div class="slider-pill round">
                                <i class="fas fa-moon"></i>
                                <i class="fas fa-sun"></i>
                            </div>
                        </label>
                    </div>
                    <a href="#" onclick="AdminComponents.logout()" class="nav-item nav-item-logout">
                        <i class="fas fa-sign-out-alt"></i> <span>Sign Out</span>
                    </a>
                </div>
            </nav>
        `;

        document.body.prepend(sidebar);
    },

    renderMobileHeader(title) {
        const isDark = localStorage.getItem('theme') === 'dark';
        const header = document.createElement('header');
        header.className = 'mobile-admin-header';
        header.innerHTML = `
            <div class="logo-stack-mini">
                <i class="fas fa-house" style="color:var(--primary); font-size: 1.25rem;"></i>
            </div>
            <span class="font-bold text-sm tracking-wide" style="flex:1; margin-left:1rem;">${title.toUpperCase()}</span>
            
            <label class="theme-switch-pill" style="height:22px; width:42px; position:relative; display:inline-block; cursor:pointer; margin-right:1rem;">
                <input type="checkbox" id="adminHeaderToggle" onclick="AdminComponents.toggleTheme()" style="display:none;" ${isDark ? 'checked' : ''} />
                <div class="slider-pill round">
                    <i class="fas fa-moon" style="font-size:8px;"></i>
                    <i class="fas fa-sun" style="font-size:8px;"></i>
                </div>
            </label>

            <button class="sidebar-toggle" onclick="AdminComponents.toggleSidebar()"><i class="fas fa-bars"></i></button>
        `;
        document.body.prepend(header);
    },

    toggleSidebar() {
        document.getElementById('adminSidebar').classList.toggle('show');
    },

    toggleTheme() {
        const isDark = document.body.classList.toggle('dark-theme');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        
        // Sync toggles
        const toggles = document.querySelectorAll('#themeToggleBtn, #adminThemeToggle, #adminHeaderToggle');
        toggles.forEach(t => t.checked = isDark);
    },

    logout() {
        if (confirm('Sign out of admin portal?')) {
            localStorage.removeItem('dorm_admin_logged_in');
            localStorage.removeItem('dorm_admin_user');
            window.location.href = '../login.html';
        }
    },

    checkAuth() {
        if (localStorage.getItem('dorm_admin_logged_in') !== 'true') {
            const isLoginPage = window.location.pathname.includes('login.html');
            if (!isLoginPage) window.location.href = '../login.html';
        }
        // Apply theme
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-theme');
        }
    }
};

// Auto-check auth
AdminComponents.checkAuth();
