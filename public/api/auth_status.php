<?php
require_once 'core.php';

header('Content-Type: application/json');
if (isset($_SESSION['user_id'])) {
    echo json_encode(['authenticated' => true, 'role' => 'user', 'id' => $_SESSION['user_id'], 'username' => $_SESSION['username']]);
} elseif (isset($_SESSION['admin_id'])) {
    echo json_encode(['authenticated' => true, 'role' => 'admin', 'id' => $_SESSION['admin_id'], 'username' => $_SESSION['admin_username']]);
} else {
    echo json_encode(['authenticated' => false]);
}
?>
