<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once '../includes/user_activity.php';
session_start();

if (!isset($_SESSION['user_id'], $_GET['order_id'])) {
    header('Location: orders.php');
    exit;
}
$order_id = (int)$_GET['order_id'];
$user_id = $_SESSION['user_id'];

// Confirm it's pending & belongs to user
$stmt = $connection->prepare("SELECT order_status FROM orders WHERE order_id=? AND user_id=?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
if (!$res || $res['order_status'] !== 'pending') {
    die("<p>Cannot cancel this order.</p><a href='orders.php'>Back to Orders</a>");
}

// Update
$u = $connection->prepare("UPDATE orders SET order_status='cancelled' WHERE order_id=?");
$u->bind_param("i", $order_id);
$u->execute();

logUserActivity($user_id, 'cancel_order', 'Cancelled order ID: ' . $order_id);

header("Location: ../views/orders.php?status=cancelled");
exit;
?>