<?php
session_start();
session_unset();
session_destroy();
// Absolute redirect — works regardless of subdirectory depth
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base   = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/'); // resolve /public
header("Location: $scheme://$host$base/login.php");
exit;
?>
