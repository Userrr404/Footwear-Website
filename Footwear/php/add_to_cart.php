<?php
session_start();
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    die("Please login to add items to cart.");
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$size_value = $_POST['size'] ?? '';
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if (!$product_id || !$size_value || $quantity < 1) {
    die("Invalid input.");
}

// Get size_id from size_value
$size_stmt = $connection->prepare("SELECT size_id FROM sizes WHERE size_value = ?");
$size_stmt->bind_param("s", $size_value);
$size_stmt->execute();
$size_result = $size_stmt->get_result();

if ($size_result->num_rows === 0) {
    die("Invalid size selected.");
}
$size_id = $size_result->fetch_assoc()['size_id'];

// Check if product with same size is already in cart
$check_stmt = $connection->prepare("SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND size_id = ?");
$check_stmt->bind_param("iii", $user_id, $product_id, $size_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    // Update quantity
    $existing = $check_result->fetch_assoc();
    $new_qty = $existing['quantity'] + $quantity;
    $update_stmt = $connection->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
    $update_stmt->bind_param("ii", $new_qty, $existing['cart_id']);
    $update_stmt->execute();
} else {
    // Insert new cart item
    $insert_stmt = $connection->prepare("INSERT INTO cart (user_id, product_id, size_id, quantity) VALUES (?, ?, ?, ?)");
    $insert_stmt->bind_param("iiii", $user_id, $product_id, $size_id, $quantity);
    $insert_stmt->execute();
}

// Redirect or confirm
header("Location: ../views/cart.php");
exit;
?>
