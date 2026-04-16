<footer class="main-footer">
    <div class="container footer-container">
        <h3 class="footer-logo"><?php echo $site_name; ?></h3>
        <p class="footer-tagline">Premium and affordable bedspacer accommodations in the heart of the city.</p>
        <div class="footer-links">
            <a href="index.php#overview" class="f-link">Overview</a>
            <a href="index.php#gallery"  class="f-link">Gallery</a>
            <a href="booking.php"        class="f-link" onclick="return YA_DORM.handleBookingClick(event)">Bookings</a>
            <a href="login.php"          class="f-link">Login</a>
        </div>
        <div class="footer-bottom">
            &copy; <?php echo date('Y'); ?> <?php echo $site_name; ?>. All Rights Reserved.
        </div>
    </div>
</footer>
<script src="<?php echo $base_dir; ?>assets/js/chat.js"></script>
</body>
</html>
