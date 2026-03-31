/**
 * COMPONENTS.JS - Replaces PHP Includes (Header/Footer)
 */

const Components = {
    renderHeader(pageTitle = "") {
        const settings = DormState.data.settings;
        const header = document.createElement('header');
        header.innerHTML = `
            <div class="container">
                <a href="index.html" class="logo">
                    <i class="fas fa-house" style="color:var(--primary); font-size: 2rem;"></i>
                </a>
                <nav>
                    <ul class="nav-links">
                        <li class="nav-mobile-header" onclick="toggleMenu()">
                            <div></div>
                            <i class="fas fa-times"></i>
                        </li>
                        <li><a href="index.html#overview" onclick="toggleMenu()">Overview</a></li>
                        <li><a href="index.html#gallery" onclick="toggleMenu()">Gallery</a></li>
                        <li><a href="booking.html">Book Now</a></li>
                        <li><a href="index.html#benefits" onclick="toggleMenu()">Benefits</a></li>
                        <li><a href="index.html#rules" onclick="toggleMenu()">Rules</a></li>
                    </ul>
                </nav>
                <div class="header-right">
                    <div class="auth-buttons">
                        <a href="login.html" class="btn btn-outline" style="white-space:nowrap;">Admin Login</a>
                        <a href="booking.html" class="btn btn-primary" style="white-space:nowrap;">Book Now</a>
                    </div>
                    
                    <div class="theme-switch-wrapper" style="margin: 0 0.5rem; display: flex; align-items: center;">
                        <label class="theme-switch-pill" style="height:26px; width:52px; position:relative; display:inline-block; cursor:pointer;">
                            <input type="checkbox" id="themeToggleBtn" onclick="toggleTheme()" style="display:none;" />
                            <div class="slider-pill round">
                                <i class="fas fa-moon"></i>
                                <i class="fas fa-sun"></i>
                            </div>
                        </label>
                    </div>

                    <button class="menu-toggle" onclick="toggleMenu()" aria-label="Open menu">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>`;
        document.body.prepend(header);
        
        // Update Title
        if (pageTitle) document.title = `${pageTitle} | ${settings.site_name}`;
        
        // Finalize switch state
        setTimeout(() => {
            const isDark = localStorage.getItem('theme') === 'dark';
            const switchBtn = document.getElementById('themeToggleBtn');
            if (switchBtn) switchBtn.checked = isDark;
        }, 10);
    },
    
    renderFooter() {
        const settings = DormState.data.settings;
        const footer = document.createElement('footer');
        footer.style.cssText = "background:#0f172a;color:#f8fafc;padding:4rem 0 2rem;font-family:'Inter',system-ui,sans-serif;";
        footer.innerHTML = `
            <div style="width:100%;max-width:1200px;margin:0 auto;padding:0 1.5rem;text-align:center;">
                <h3 style="font-family:'Outfit',sans-serif;font-size:1.5rem;font-weight:700;color:#34d399;margin-bottom:0.75rem;">${settings.site_name}</h3>
                <p style="color:#94a3b8;font-size:0.95rem;margin-bottom:2rem;">Premium and affordable bedspacer accommodations in the heart of the city.</p>
                <div style="display:flex;justify-content:center;gap:2.5rem;flex-wrap:wrap;margin-bottom:2.5rem;">
                    <a href="index.html#overview" class="f-link">Overview</a>
                    <a href="index.html#gallery"  class="f-link">Gallery</a>
                    <a href="booking.html"       class="f-link">Book Now</a>
                    <a href="login.html"         class="f-link">Admin Login</a>
                </div>
                <div style="border-top:1px solid rgba(255,255,255,0.06);padding-top:1.5rem;font-size:0.85rem;color:#64748b;">
                    &copy; ${new Date().getFullYear()} ${settings.site_name}. All Rights Reserved.
                </div>
            </div>`;
        document.body.appendChild(footer);
        
        // Inject footer styles
        const style = document.createElement('style');
        style.textContent = `
            .f-link { color:#cbd5e1;font-size:0.9rem;font-weight:500;text-decoration:none;transition:color 0.3s; }
            .f-link:hover { color:#34d399; }
        `;
        document.head.appendChild(style);
    }
};

// Global Menu Toggle
window.toggleMenu = function() {
    document.querySelector('.nav-links').classList.toggle('active');
};

// Global Theme Handler
window.toggleTheme = function() {
    const isDark = document.body.classList.toggle('dark-theme');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    
    // Sync toggles
    const toggles = document.querySelectorAll('#themeToggleBtn, #adminThemeToggle');
    toggles.forEach(t => t.checked = isDark);
};

document.addEventListener('DOMContentLoaded', () => {
    const isDark = localStorage.getItem('theme') === 'dark';
    if (isDark) {
        document.body.classList.add('dark-theme');
    }
    const toggles = document.querySelectorAll('#themeToggleBtn, #adminThemeToggle');
    toggles.forEach(t => t.checked = isDark);
});
