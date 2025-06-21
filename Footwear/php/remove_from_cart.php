<?php
session_start();
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';

$cart_id = $_POST['cart_id'];

$stmt = $connection->prepare("DELETE FROM cart WHERE cart_id = ?");
$stmt->bind_param("i", $cart_id);
$stmt->execute();

header("Location: ../views/cart.php");
exit;
?>
