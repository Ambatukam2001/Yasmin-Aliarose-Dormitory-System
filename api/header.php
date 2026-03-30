<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' | ' : ''; echo $site_name; ?></title>

    <!-- External Assets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Project Styles -->
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        /* ============================================================
           INLINE CSS — mirrors css/style.css for header consistency
           ============================================================ */

        /* ---------- CSS Custom Properties ---------- */
        :root {
            --primary:        #10b981;
            --primary-dark:   #059669;
            --primary-light:  #34d399;
            --primary-subtle: #ecfdf5;
            --accent:         #14b8a6;
            --white:          #ffffff;
            --off-white:      #f8fafc;
            --background:     #fbfdfb;
            --text-primary:   #1e293b;
            --text-secondary: #64748b;
            --text-muted:     #94a3b8;

            --glass:        rgba(255, 255, 255, 0.8);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-sm:    0 1px 2px 0 rgba(0,0,0,.05);
            --shadow-md:    0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);
            --shadow-lg:    0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -2px rgba(0,0,0,.05);
            --shadow-xl:    0 20px 25px -5px rgba(0,0,0,.1), 0 10px 10px -5px rgba(0,0,0,.04);

            --radius-sm:  0.375rem;
            --radius-md:  0.5rem;
            --radius-lg:  1rem;
            --radius-xl:  1.5rem;
            --max-width:  1200px;

            --transition-fast: 0.15s ease;
            --transition-base: 0.3s ease;
            --transition-slow: 0.5s ease;
        }

        /* ---------- Dark Theme Overrides ---------- */
        .dark-theme {
            --white:          #1e293b;
            --off-white:      #0f172a;
            --background:     #020617;
            --text-primary:   #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted:     #94a3b8;
            --glass:          rgba(15, 23, 42, 0.8);
            --glass-border:   rgba(255, 255, 255, 0.1);
            --primary-subtle: rgba(16, 185, 129, 0.1);
        }

        .dark-theme footer { color: #f8fafc; }

        /* ---------- Base Reset ---------- */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            overflow-x: clip;
            max-width: 100%;
            width: 100%;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--off-white);
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            position: relative;
        }

        * { overflow-wrap: break-word; word-wrap: break-word; }

        img, video { max-width: 100%; height: auto; display: block; }

        h1, h2, h3, h4, .logo {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            line-height: 1.2;
        }

        a { text-decoration: none; color: inherit; transition: var(--transition-base); }
        ul { list-style: none; }

        .container {
            width: 100%;
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* ---------- Buttons ---------- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.75rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all var(--transition-base);
            border: none;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
            box-shadow: 0 4px 14px 0 rgba(16,185,129,.39);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16,185,129,.23);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background-color: var(--primary-subtle);
            transform: translateY(-2px);
        }

        /* ---------- Header ---------- */
        header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--glass);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--glass-border);
            padding: 1rem 0;
            transition: var(--transition-base);
        }

        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            font-size: 1.5rem;
            color: var(--primary-dark);
            white-space: nowrap;
            flex: 1;
            line-height: 1;
        }

        .logo img {
            height: 38px;
            width: 38px;
            object-fit: contain;
            border-radius: 10px;
        }

        .logo span {
            display: inline-block;
            margin-top: 2px;
        }

        nav { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            flex: 2; 
        }

        .header-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 1.25rem;
            flex: 1;
        }

        .menu-toggle {
            display: none;
            background: transparent;
            border: none;
            font-size: 1.5rem;
            color: var(--primary);
            cursor: pointer;
            transition: 0.3s;
        }

        .menu-toggle:hover {
            color: var(--primary-dark);
            transform: scale(1.1);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2.25rem;
            white-space: nowrap;
            margin: 0;
        }

        .nav-links a {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .nav-links a:hover { color: var(--primary); }

        .auth-buttons { display: flex; gap: 0.75rem; }

        /* ---------- Minimalist Pill Switch ---------- */
        .theme-switch-pill {
            display: inline-block;
            position: relative;
            width: 48px;
            height: 24px;
        }

        .slider-pill {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #2d3748;
            transition: .4s;
            border-radius: 24px;
        }

        .slider-pill:before {
            position: absolute;
            content: "";
            height: 18px; width: 18px;
            left: 3px; bottom: 3px;
            background-color: #06b6d4;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(6, 182, 212, 0.4);
        }

        input:checked + .slider-pill { background-color: #1e293b; }
        input:checked + .slider-pill:before {
            transform: translateX(24px);
            background-color: #10b981;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.5);
        }

        .dark-theme .slider-pill { background-color: #334155; }

        /* ---------- Responsive ---------- */
        @media (max-width: 1024px) {
            .container { padding: 0 2rem; }
            .nav-links { gap: 1.25rem; }
            .nav-links a { font-size: 0.85rem; }
            .btn { padding: 0.6rem 1rem; font-size: 0.85rem; }
            .logo { font-size: 1.25rem; }
        }

        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .auth-buttons { display: none; }

            .nav-mobile-header {
                display: flex !important;
                align-items: center;
                justify-content: flex-end;
                width: 100%;
                padding: 0 0 1rem;
                margin-bottom: 1rem;
                border-bottom: 1px solid #f1f5f9;
            }

            .nav-mobile-header i {
                font-size: 1.6rem;
                cursor: pointer;
                color: var(--primary);
                background: var(--primary-subtle);
                width: 45px;
                height: 45px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
            }

            .nav-links {
                position: fixed;
                top: 0; right: 0;
                width: 80%;
                height: 100vh;
                background: white;
                box-shadow: -10px 0 50px rgba(0,0,0,.15);
                flex-direction: column;
                align-items: flex-start;
                justify-content: flex-start;
                padding: 1.5rem 2rem;
                gap: 1rem;
                z-index: 1500;
                transform: translateX(100%);
                visibility: hidden;
                opacity: 0;
                transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
                display: flex !important;
            }

            .dark-theme .nav-links { background: #1e293b; }

            .nav-links.active {
                transform: translateX(0);
                visibility: visible;
                opacity: 1;
            }

            .nav-links li { width: 100%; }

            .nav-links li a {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--text-primary);
                display: block;
                padding: 1rem 0;
                border-bottom: 1px solid rgba(0,0,0,0.03);
            }
        }

        /* Desktop hide header */
        .nav-mobile-header { display: none; }

        @media (max-width: 480px) {
            .btn { width: 100%; justify-content: center; }
            .header-right { gap: 0.75rem; }
        }
    </style>
</head>
<body>

<header>
    <div class="container">
        <a href="index.html" class="logo">
            <img src="assets/images/logo.png" alt="Logo" style="height:45px; width:45px; object-fit:contain; border-radius:12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
        </a>

        <nav>
            <ul class="nav-links">
                <li class="nav-mobile-header" onclick="toggleMenu()">
                    <div></div> <!-- spacing -->
                    <i class="fas fa-times"></i>
                </li>
                <li><a href="index.html#overview" onclick="toggleMenu()">Overview</a></li>
                <li><a href="index.html#gallery" onclick="toggleMenu()">Gallery</a></li>
                <li><a href="booking.php">Book Now</a></li>
                <li><a href="index.html#benefits" onclick="toggleMenu()">Benefits</a></li>
                <li><a href="index.html#rules" onclick="toggleMenu()">Rules</a></li>
            </ul>
        </nav>

        <div class="header-right">
            <div class="auth-buttons">
                <a href="login.php" class="btn btn-outline">Admin Login</a>
                <a href="booking.php" class="btn btn-primary">Book Now</a>
            </div>
            
            <label class="theme-switch-pill" style="margin: 0 0.5rem;">
                <input type="checkbox" id="themeToggleBtn" onclick="toggleTheme()" style="display:none;">
                <div class="slider-pill round"></div>
            </label>

            <button class="menu-toggle" onclick="toggleMenu()" aria-label="Open menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
</header>

<script>
    function toggleMenu() {
        document.querySelector('.nav-links').classList.toggle('active');
    }

    function toggleTheme() {
        const isDark = document.body.classList.toggle('dark-theme');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateThemeState(isDark);
    }

    function updateThemeState(isDark) {
        const toggle = document.getElementById('themeToggleBtn');
        if (toggle) toggle.checked = isDark;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const isDark = localStorage.getItem('theme') === 'dark';
        if (isDark) {
            document.body.classList.add('dark-theme');
            updateThemeState(true);
        }
    });
</script>
