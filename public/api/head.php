<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " | Yasmin & Aliarose Dormitory" : "Yasmin & Aliarose Dormitory"; ?></title>

    <!-- PWA & Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $base_dir; ?>assets/images/logo.png">
    <link rel="apple-touch-icon" href="<?php echo $base_dir; ?>assets/images/logo.png">
    <link rel="manifest" href="<?php echo $base_dir; ?>manifest.json">
    <meta name="theme-color" content="#10b981">

    <!-- Performance Optimization: Preconnect & DNS-Prefetch -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>

    <!-- External Assets -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- Project Styles -->
    <link rel="stylesheet" href="<?php echo $base_dir; ?>assets/css/style.css">

    <!-- Smart Prefetching Script -->
    <script>
        document.addEventListener('mouseover', function(e) {
            const link = e.target.closest('a');
            if (link && link.href && link.href.startsWith(window.location.origin)) {
                if (!document.querySelector(`link[rel="prefetch"][href="${link.href}"]`)) {
                    const prefetch = document.createElement('link');
                    prefetch.rel = 'prefetch';
                    prefetch.href = link.href;
                    document.head.appendChild(prefetch);
                }
            }
        }, { passive: true });
    </script>
</head>
<body class="<?php echo isset($body_class) ? $body_class : ''; ?>">
