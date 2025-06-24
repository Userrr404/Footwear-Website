<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once INCLUDES_PATH . 'header.php';

if (!isset($_SESSION['user_id'])) {
    die("<p style='text-align:center;'>Please <a href='" . BASE_URL . "views/login.php'>login</a> to continue to checkout.</p>");
}

$user_id = $_SESSION['user_id'];

// Get cart items
$sql = "SELECT c.cart_id, c.quantity, p.product_name, p.price, s.size_value, pi.image_url
        FROM cart c
        JOIN products p ON c.product_id = p.product_id
        JOIN sizes s ON c.size_id = s.size_id
        LEFT JOIN product_images pi ON c.product_id = pi.product_id AND pi.is_default = 1
        WHERE c.user_id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();

$cart_items = [];
$grand_total = 0;

while ($item = $cart_result->fetch_assoc()) {
    $cart_items[] = $item;
    $grand_total += $item['price'] * $item['quantity'];
}

// Fetch existing addresses
$addr_sql = "SELECT * FROM addresses WHERE user_id = ?";
$addr_stmt = $connection->prepare($addr_sql);
$addr_stmt->bind_param("i", $user_id);
$addr_stmt->execute();
$addresses = $addr_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Checkout | Elite Footwear</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/checkout.css">
</head>
<body>

<div class="checkout-container">
  <h1>Checkout</h1>

  <!-- Cart Summary -->
  <div class="section">
    <h2>🛒 Your Items</h2>
    <?php foreach ($cart_items as $item): ?>
      <div class="cart-item">
        <img src="<?= UPLOADS_URL . $item['image_url'] ?>" alt="<?= $item['product_name'] ?>">
        <div class="cart-details">
          <p><strong><?= htmlspecialchars($item['product_name']) ?></strong></p>
          <p>Size: <?= $item['size_value'] ?> | Qty: <?= $item['quantity'] ?></p>
          <p>₹<?= number_format($item['price'], 2) ?> each</p>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="total">Total: ₹<?= number_format($grand_total, 2) ?></div>
  </div>

  <!-- Address Section -->
  <div class="section">
    <h2>📍 Select Address</h2>
    <form method="post" action="<?= BASE_URL ?>php/process_order.php">
      <?php if ($addresses->num_rows > 0): ?>
        <?php while ($addr = $addresses->fetch_assoc()): ?>
          <div class="address-box">
            <label>
              <input type="radio" name="address_id" value="<?= $addr['address_id'] ?>" required>
              <?= htmlspecialchars($addr['full_name']) ?>, <?= htmlspecialchars($addr['address_line']) ?>, <?= $addr['city'] ?>, <?= $addr['state'] ?> - <?= $addr['pincode'] ?>
              <br>Phone: <?= $addr['phone'] ?>
            </label>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p>No address found. Please add one:</p>
      <?php endif; ?>

      <div class="address-form">
        <h3>➕ Add New Address</h3>
        <input type="text" name="full_name" placeholder="Full Name">
        <textarea name="address_line" placeholder="Address Line"></textarea>
        <input type="text" name="city" placeholder="City">
        <input type="text" name="state" placeholder="State">
        <input type="text" name="pincode" placeholder="PIN Code">
        <input type="text" name="phone" placeholder="Phone">
        <select name="type">
          <option value="shipping">Shipping</option>
          <option value="billing">Billing</option>
        </select>
      </div>

      <input type="hidden" name="total" value="<?= $grand_total ?>">
      <button type="submit" style="margin-top: 20px;">✅ Place Order</button>
    </form>
  </div>
</div>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
</body>
</html>
