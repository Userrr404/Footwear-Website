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

$payment_method = $_POST['payment_method'];

if (!$payment_method) {
    die("❌ Payment method is required.");
}


// ------------------ Address Handling ------------------ //
if (!empty($_POST['shipping_address_id'])) {
    // ✅ Existing address selected
    $address_id = intval($_POST['shipping_address_id']);
    $addr_query = $connection->prepare("SELECT * FROM addresses WHERE address_id = ? AND user_id = ?");
    $addr_query->bind_param("ii", $address_id, $user_id);
    $addr_query->execute();
    $address = $addr_query->get_result()->fetch_assoc();

    if (!$address) {
        die("Invalid address selected.");
    }

    $full_name     = $address['full_name'];
    $address_line1 = $address['address_line1'];
    $city          = $address['city'];
    $state         = $address['state'];
    $pincode       = $address['pincode'];
    $phone         = $address['phone_number'];

} else {
    // ✅ Add new address
    $required_fields = ['full_name', 'address_line1', 'city', 'state', 'pincode', 'phone_number'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            die("Missing required field: $field");
        }
    }

    $full_name     = $_POST['full_name'];
    $address_line1 = $_POST['address_line1'];
    $city          = $_POST['city'];
    $state         = $_POST['state'];
    $pincode       = $_POST['pincode'];
    $country       = $_POST['country'];
    $phone         = $_POST['phone_number'];
    $address_type  = $_POST['address_type'] ?? 'shipping';

    // ✅ Check if user already has an address
    $check_addr = $connection->prepare("SELECT COUNT(*) as total FROM addresses WHERE user_id = ?");
    $check_addr->bind_param("i", $user_id);
    $check_addr->execute();
    $addr_count = $check_addr->get_result()->fetch_assoc()['total'];

    // ✅ If no address exists → make this default
    $is_default = ($addr_count == 0) ? 1 : 0;

    $insert_addr = $connection->prepare("
        INSERT INTO addresses 
        (user_id, full_name, address_line1, city, state, pincode, country, phone_number, address_type, is_default) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert_addr->bind_param(
        "issssssssi",
        $user_id,
        $full_name,
        $address_line1,
        $city,
        $state,
        $pincode,
        $country,
        $phone,
        $address_type,
        $is_default
    );
    $insert_addr->execute();
    $address_id = $insert_addr->insert_id;
}


// ------------------ Cart Handling ------------------ //
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
    $product = $connection->query("SELECT selling_price FROM products WHERE product_id = {$item['product_id']}")->fetch_assoc();
    $total += $product['selling_price'] * $item['quantity'];
}

// ------------------ Order Creation ------------------ //
$shipping_address_id = $_POST['shipping_address_id'] ?? null;
$subtotal_amount  = $_POST['subtotal_amount'] ?? 0;
$discount_amount  = $_POST['discount_amount'] ?? 0;
$tax_amount       = $_POST['tax_amount'] ?? 0;
$shipping_amount  = $_POST['shipping_amount'] ?? 0;
$total_amount     = $_POST['total_amount'] ?? 0;
$order_number = 'ORD' . strtoupper(uniqid());
$payment_status = 'pending';

$order_stmt = $connection->prepare("
    INSERT INTO orders 
    (order_number, address_id, user_id, subtotal_amount, discount_amount, tax_amount, shipping_amount, total_amount, 
     shipping_address_id, billing_address_id, payment_method, payment_status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$order_stmt->bind_param(
    "siiddddiisss",
    $order_number,
    $address_id,
    $user_id,
    $subtotal_amount,
    $discount_amount,
    $tax_amount,
    $shipping_amount,
    $total_amount,
    $shipping_address_id,
    $billing_address_id,
    $payment_method,
    $payment_status
);
$order_stmt->execute();
$order_id = $order_stmt->insert_id;

// ------------------ Order Items ------------------ //
foreach ($items as $item) {
    $product = $connection->query("SELECT product_name, selling_price FROM products WHERE product_id = {$item['product_id']}")->fetch_assoc();
    $price = $product['selling_price'];
    $product_name = $product['product_name'];

    $stmt = $connection->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, size_id, quantity, price, discount, tax, total) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisiidddd", $order_id, $item['product_id'], $product_name, $item['size_id'], $item['quantity'], $price, $discount_amount, $tax_amount, $total_amount);
    $stmt->execute();
}

// ------------------ Cart Cleanup ------------------ //
$connection->query("DELETE FROM cart WHERE user_id = $user_id");

// ------------------ Email ------------------ //
$user_email = $connection->query("SELECT user_email FROM users WHERE user_id = $user_id")->fetch_assoc()['user_email'];
$subject = "Your Elite Footwear Order #$order_number";
$body = "Hi $full_name,\n\nThank you for your order. Your order number is $order_number.\n\nTotal: ₹" . number_format($total, 2) . "\n\nPayment Method: " . strtoupper($payment_method) . "\n\nRegards,\nElite Footwear Team";
$headers = "From: elitefootwear@example.com";

mail($user_email, $subject, $body, $headers);

// ------------------ Activity Log ------------------ //
logUserActivity($user_id, 'place_order', 'Placed order ID: ' . $order_id);

// ------------------ Redirect ------------------ //
header("Location: ../views/order_success.php?order_id=$order_id");
exit;
?>
