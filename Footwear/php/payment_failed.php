<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
session_start();

$rzp_order_id = $_GET['rzp_order_id'] ?? '';
if (!$rzp_order_id) die('Invalid request');

$data = $_SESSION['order_temp_data'] ?? [];
$user_id = $_SESSION['user_id'] ?? 0;

// Generate identifiers
$order_number = 'ORD' . strtoupper(uniqid());
$order_uuid = uniqid('ORD_');

// Insert minimal failed order record
$stmt = $connection->prepare("
    INSERT INTO orders (
        order_number, order_uuid, user_id, address_id,
        subtotal_amount, tax_amount, shipping_amount, total_amount,
        payment_status, order_status, payment_method, currency,
        placed_at, cancelled_at, created_at
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'failed', 'cancelled', ?, 'INR', NOW(), NOW(), NOW())
");

$tax = $data['tax'];
$shipping = $data['shipping'];
$method = $data['payment_method'];
$address_id = $data['address_id'];

$stmt->bind_param(
    "ssiidddss",
    $order_number, $order_uuid, $user_id, $address_id,
    $data['subtotal'], $tax, $shipping, $data['total'], $method
);
$stmt->execute();

// Update payment_sessions table
$connection->query("UPDATE payment_sessions SET status='failed' WHERE rzp_order_id='$rzp_order_id'");

// Cleanup session
unset($_SESSION['order_temp_data']);

header("Location: ../views/order_failed.php?order_uuid=$order_uuid");
exit;
?>
