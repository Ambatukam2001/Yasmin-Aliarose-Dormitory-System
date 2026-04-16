<?php
require_once 'api/core.php';
session_destroy();
header("Location: login.php");
exit;

?>
