<?php
session_start();
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';

$cart_id = $_POST['cart_id'];
$quantity = max(1, (int)$_POST['quantity']);

$stmt = $connection->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
$stmt->bind_param("ii", $quantity, $cart_id);
$stmt->execute();

header("Location: ../views/cart.php");
exit;
?>
