<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once '../includes/user_activity.php';

// ---------------- HELPER FUNCTIONS ---------------- //
function getProductTaxRate($connection, $product_id) {
    $sql = "
        SELECT t.tax_rate
        FROM products p
        LEFT JOIN tax_rules t
          ON (
          (t.brand_id = p.brand_id)
           OR (t.category_id = p.category_id)
          )
        WHERE p.product_id = ?
          AND t.status = 'active'
          AND t.effective_from <= CURDATE()
          AND (t.effective_to IS NULL OR t.effective_to >= CURDATE())
        ORDER BY t.priority DESC
        LIMIT 1
    ";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row['tax_rate'];
    }
    return 0; // default no tax
}

function getProductDiscount($connection, $product_id, $price) {
    $sql = "
        SELECT d.discount_type, d.value
        FROM products p
        LEFT JOIN discount d
          ON (
              (d.applicable_to = 'product' AND d.applicable_id = p.product_id)
           OR (d.applicable_to = 'brand' AND d.applicable_id = p.brand_id)
           OR (d.applicable_to = 'category' AND d.applicable_id = p.category_id)
          )
        WHERE p.product_id = ?
          AND d.status = 'active'
          AND d.valid_from <= CURDATE()
          AND d.valid_to >= CURDATE()
        ORDER BY d.priority DESC
        LIMIT 1
    ";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['discount_type'] == 'percentage') {
            return $price * ($row['value'] / 100);
        } else {
            return $row['value'];
        }
    }
    return 0;
}

function getOrderDiscount($connection, $subtotal) {
    $sql = "
        SELECT discount_type, value
        FROM discount
        WHERE applicable_to = 'order'
          AND status = 'active'
          AND valid_from <= CURDATE()
          AND valid_to >= CURDATE()
          AND min_order_amount <= ?
        ORDER BY priority DESC
        LIMIT 1
    ";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("d", $subtotal);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['discount_type'] == 'percentage') {
            return $subtotal * ($row['value'] / 100);
        } else {
            return $row['value'];
        }
    }
    return 0;
}

function getShippingCharge($connection, $subtotal, $region = null){
  
  $sql = "
        SELECT charge
        FROM shipping_rules
        WHERE status = 'active'
          AND effective_from <= CURDATE()
          AND (effective_to IS NULL OR effective_to >= CURDATE())
          AND min_order_amount <= ?
          AND (max_order_amount IS NULL OR max_order_amount >= ?)
          " . ($region ? " AND (region IS NULL OR region = ?)" : "") . "
        ORDER BY priority DESC
        LIMIT 1
    ";

    if ($region) {
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("dds", $subtotal, $subtotal, $region);
    } else {
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("dd", $subtotal, $subtotal);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return (float)$row['charge'];
    }

    return 50.0; // default fallback
}
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


// ------------------ Cart Handling (respect posted cart_id[] if present) ------------------ //
$posted_cart_ids = $_POST['cart_id'] ?? []; // from checkout.php hidden inputs
if (!empty($posted_cart_ids) && is_array($posted_cart_ids)) {
    // Sanitize and prepare for SQL IN clause
    $placeholders = implode(',', array_fill(0, count($posted_cart_ids), '?'));
    $types = str_repeat('i', count($posted_cart_ids));
    $cart_sql = "SELECT * FROM cart WHERE user_id = ? AND cart_id IN ($placeholders)";
    $cart_stmt = $connection->prepare($cart_sql);

    // bind dynamically (first param user_id, then cart_ids)
    $bind_names[] = $user_id;
    for ($i = 0; $i < count ($posted_cart_ids); $i++) {
        $bind_names[] = (int)$posted_cart_ids[$i];
    }

    // create types string like "i" + "iii..." for bind_param
    $full_types = 'i' . $types;
    // reflection to bind params dynamically
    $a_params = array();
    $a_params[] = & $full_types;
    for ($i = 0; $i < count($bind_names); $i++){
        $a_params[] = & $bind_names[$i];
    }
    call_user_func_array(array($cart_stmt, 'bind_param'), $a_params);
    $cart_stmt->execute();
    $cart_items_result = $cart_stmt->get_result();
}else {
    // Fetch all cart items for user
    // No specific cart_id sent — use all items
    $cart_sql = "SELECT * FROM cart WHERE user_id = ?";
    $cart_stmt = $connection->prepare($cart_sql);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_items_result = $cart_stmt->get_result();
}

if ($cart_items_result->num_rows === 0) {
    die("Your cart is empty.");
}

// recompute subtotal, discounts, tax per-item (do not trust hidden fields)
$subtotal = 0;
$discount_total = 0;
$tax_total = 0;
$items = [];

while ($item = $cart_items_result->fetch_assoc()) {
    $product_id = (int)$item['product_id'];
    $qty = (int)$item['quantity'];
    $product_row = $connection->query("SELECT product_name, selling_price FROM products WHERE product_id = $product_id")->fetch_assoc();
    $unit_price = (float)$product_row['selling_price'];
    $base_price = $unit_price * $qty;

    // use same helper functions as checkout.php
    $discount = getProductDiscount($connection, $product_id, $base_price);
    $price_after_disc = $base_price - $discount;
    $tax_rate = getProductTaxRate($connection, $product_id);
    $tax = round($price_after_disc * ($tax_rate / 100), 2);

    $line_total = $price_after_disc + $tax;

    $subtotal += $base_price;
    $discount_total += $discount;
    $tax_total += $tax;
    
    $items[] = [
        'cart_id' => (int)$item['cart_id'],
        'product_id' => $product_id,
        'product_name' => $product_row['product_name'],
        'size_id' => (int)$item['size_id'],
        'quantity' => $qty,
        'unit_price' => $unit_price,
        'line_base' => $base_price,
        'discount' => $discount,
        'tax' => $tax,
        'line_total' => $line_total
    ];
}

// shipping and order-level discount (recompute)
$shipping = getShippingCharge($connection, $subtotal /*, optional region */);
$order_discount = getOrderDiscount($connection, $subtotal - $discount_total);

// Final total
$total_amount = round($subtotal - $discount_total + $tax_total + $shipping - $order_discount, 2);

// ... Address handling above should have set $address_id, $billing_address_id etc.
// ensure $billing_address_id is defined (set to $address_id or via form)
$billing_address_id = $_POST['billing_address_id'] ?? $address_id ?? null;

// ------------------ Order Creation ------------------ //
$order_number = 'ORD' . strtoupper(uniqid());
$payment_status = 'pending';

$order_stmt = $connection->prepare("
    INSERT INTO orders 
    (order_number, address_id, user_id, subtotal_amount, discount_amount, tax_amount, shipping_amount, total_amount, 
     shipping_address_id, billing_address_id, payment_method, payment_status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$final_discount = $discount_total + $order_discount; // final discount (product + order-level)

$order_stmt->bind_param(
    "siiddddiisss",
    $order_number,
    $address_id,
    $user_id,
    $subtotal,
    $final_discount,
    $tax_total,
    $shipping,
    $total_amount,
    $address_id, // shipping_address_id
    $billing_address_id,
    $payment_method,
    $payment_status
);
$order_stmt->execute();
$order_id = $order_stmt->insert_id;

// ------------------ Insert Order Items (per-item values) ------------------ //
$item_stmt = $connection->prepare("
    INSERT INTO order_items (order_id, product_id, product_name, size_id, quantity, price, discount, tax, total) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($items as $it) {
    $item_stmt->bind_param(
        "iisiidddd",
        $order_id,
        $it['product_id'],
        $it['product_name'],
        $it['size_id'],
        $it['quantity'],
        $it['unit_price'],
        $it['discount'],
        $it['tax'],
        $it['line_total']
    );
    $item_stmt->execute();
}

// ------------------ Cart Cleanup ------------------ //
// Delete only the cart_ids we inserted (safer for single-item checkout)
$cart_ids_to_remove = array_column($items, 'cart_id');
if (!empty($cart_ids_to_remove)) {
    $ids = implode(',', array_map('intval', $cart_ids_to_remove));
    $connection->query("DELETE FROM cart WHERE cart_id IN ($ids)");
}

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
