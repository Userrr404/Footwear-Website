<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../views/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate form inputs
$required_fields = ['full_name', 'address_line', 'city', 'state', 'pincode', 'phone'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        die("Missing required field: $field");
    }
}

// Insert/update shipping address
$check = $connection->prepare("SELECT address_id FROM addresses WHERE user_id = ? AND type = 'shipping'");
$check->bind_param("i", $user_id);
$check->execute();
$exists = $check->get_result()->num_rows > 0;

if ($exists) {
    $sql = "UPDATE addresses SET full_name=?, address_line=?, city=?, state=?, pincode=?, phone=? 
            WHERE user_id=? AND type='shipping'";
} else {
    $sql = "INSERT INTO addresses (full_name, address_line, city, state, pincode, phone, user_id, type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'shipping')";
}
$stmt = $connection->prepare($sql);
$stmt->bind_param("ssssssi", $_POST['full_name'], $_POST['address_line'], $_POST['city'], $_POST['state'], $_POST['pincode'], $_POST['phone'], $user_id);
$stmt->execute();

// Fetch cart
$cart_sql = "SELECT * FROM cart WHERE user_id = ?";
$cart_stmt = $connection->prepare($cart_sql);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_items = $cart_stmt->get_result();

if ($cart_items->num_rows === 0) {
    die("Your cart is empty.");
}

$total = 0;
$items = [];

while ($item = $cart_items->fetch_assoc()) {
    $items[] = $item;
    $product = $connection->query("SELECT price FROM products WHERE product_id = {$item['product_id']}")->fetch_assoc();
    $total += $product['price'] * $item['quantity'];
}

// Insert into orders table
$order_stmt = $connection->prepare("INSERT INTO orders (user_id, total_amount) VALUES (?, ?)");
$order_stmt->bind_param("id", $user_id, $total);
$order_stmt->execute();
$order_id = $order_stmt->insert_id;

// Insert into order_items
foreach ($items as $item) {
    $price = $connection->query("SELECT price FROM products WHERE product_id = {$item['product_id']}")->fetch_assoc()['price'];
    $stmt = $connection->prepare("INSERT INTO order_items (order_id, product_id, size_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiid", $order_id, $item['product_id'], $item['size_id'], $item['quantity'], $price);
    $stmt->execute();
}

// Clear cart
$connection->query("DELETE FROM cart WHERE user_id = $user_id");

// Optional: send email (see below)
// Send confirmation email
$user_email = $connection->query("SELECT user_email FROM users WHERE user_id = $user_id")->fetch_assoc()['user_email'];
$subject = "Your Elite Footwear Order #$order_id";
$body = "Hi {$_POST['full_name']},\n\nThank you for your order. Your order ID is $order_id.\n\nTotal: ₹" . number_format($total, 2) . "\n\nRegards,\nElite Footwear Team";
$headers = "From: yogeshlilakedev02@gmail.com.com";

mail($user_email, $subject, $body, $headers);


// Redirect to success
header("Location: ../views/order_success.php?order_id=" . $order_id);
exit;
?>
