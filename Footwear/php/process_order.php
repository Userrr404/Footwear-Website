<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once '../includes/user_activity.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../views/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if existing address is selected
if (isset($_POST['address_id']) && !empty($_POST['address_id'])) {
    // ✅ Use existing address
    $address_id = intval($_POST['address_id']);
    $addr_query = $connection->prepare("SELECT * FROM addresses WHERE address_id = ? AND user_id = ?");
    $addr_query->bind_param("ii", $address_id, $user_id);
    $addr_query->execute();
    $address = $addr_query->get_result()->fetch_assoc();

    if (!$address) {
        die("Invalid address selected.");
    }

    $full_name = $address['full_name'];
    $address_line = $address['address_line'];
    $city = $address['city'];
    $state = $address['state'];
    $pincode = $address['pincode'];
    $phone = $address['phone'];
} else {
    // ✅ Use new address
    $required_fields = ['full_name', 'address_line', 'city', 'state', 'pincode', 'phone'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            die("Missing required field: $field");
        }
    }

    $full_name = $_POST['full_name'];
    $address_line = $_POST['address_line'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $pincode = $_POST['pincode'];
    $phone = $_POST['phone'];
    $type = $_POST['type'] ?? 'shipping';

    // Insert the new address
    $insert_addr = $connection->prepare("INSERT INTO addresses (user_id, full_name, address_line, city, state, pincode, phone, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insert_addr->bind_param("isssssis", $user_id, $full_name, $address_line, $city, $state, $pincode, $phone, $type);
    $insert_addr->execute();
    $address_id = $insert_addr->insert_id;
}

// Get cart items
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
$order_stmt = $connection->prepare("INSERT INTO orders (user_id, address_id, total_amount) VALUES (?, ?, ?)");
$order_stmt->bind_param("iid", $user_id, $address_id, $total);
$order_stmt->execute();
$order_id = $order_stmt->insert_id;

// Insert order items
foreach ($items as $item) {
    $price = $connection->query("SELECT price FROM products WHERE product_id = {$item['product_id']}")->fetch_assoc()['price'];
    $stmt = $connection->prepare("INSERT INTO order_items (order_id, product_id, size_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiid", $order_id, $item['product_id'], $item['size_id'], $item['quantity'], $price);
    $stmt->execute();
}

// Clear cart
$connection->query("DELETE FROM cart WHERE user_id = $user_id");

// Send email
$user_email = $connection->query("SELECT user_email FROM users WHERE user_id = $user_id")->fetch_assoc()['user_email'];
$subject = "Your Elite Footwear Order #$order_id";
$body = "Hi $full_name,\n\nThank you for your order. Your order ID is $order_id.\n\nTotal: ₹" . number_format($total, 2) . "\n\nRegards,\nElite Footwear Team";
$headers = "From: elitefootwear@example.com";

mail($user_email, $subject, $body, $headers);

logUserActivity($user_id, 'place_order', 'Placed order ID: ' . $order_id);

// Redirect to success
header("Location: ../views/order_success.php?order_id=$order_id");
exit;
?>
