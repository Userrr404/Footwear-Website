<?php
require_once '../includes/user_activity.php';
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
session_start();
$user_id = $_SESSION['user_id'];
logUserActivity($user_id, 'logout', 'User logged out');
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
