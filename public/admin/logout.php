<?php
require_once '../api/core.php';
session_destroy();

// Absolute redirect — works regardless of subdirectory depth
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base   = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/'); // resolve /public
header("Location: $scheme://$host$base/login.php");
exit;

