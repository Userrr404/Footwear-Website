<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Cart | Elite Footwear</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/cart.css">
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

<?php
  require_once INCLUDES_PATH . 'header.php';

if (!isset($_SESSION['user_id'])) {
    die("<p style='text-align:center;'>Please <a href='" . BASE_URL . "views/login.php'>login</a> to view your cart.</p>");
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT c.cart_id, c.quantity, p.product_name, p.selling_price, pi.image_url, s.size_value
        FROM cart c
        JOIN products p ON c.product_id = p.product_id
        JOIN sizes s ON c.size_id = s.size_id
        LEFT JOIN product_images pi ON c.product_id = pi.product_id AND pi.is_default = 1
        WHERE c.user_id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$grand_total = 0;
while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $grand_total += $row['selling_price'] * $row['quantity'];
}
?>

<main class="flex-grow">
  <div class="container">
  <h1>🛒 Your Shopping Cart</h1>

  <?php if (empty($cart_items)): ?>
    <p class="empty">Your cart is empty. <a href="<?= BASE_URL ?>views/products.php">Start shopping</a>!</p>
  <?php else: ?>
    <?php foreach ($cart_items as $item): ?>
      <div class="cart-item">
        <img src="<?= UPLOADS_URL . $item['image_url'] ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
        <div class="details">
          <h3><?= htmlspecialchars($item['product_name']) ?></h3>
          <p>Size: <?= $item['size_value'] ?></p>
          <p class="price">₹<?= number_format($item['selling_price'], 2) ?> x <?= $item['quantity'] ?></p>

          <div class="actions">
            <form method="post" action="../php/update_cart.php" style="display: inline-flex; align-items: center; gap: 10px;">
              <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
              <input type="number" name="quantity" min="1" value="<?= $item['quantity'] ?>">
              <button type="submit">Update</button>
            </form>

            <form method="post" action="../php/remove_from_cart.php" style="display:inline;">
              <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
              <button class="remove-btn" type="submit">Remove</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="total-box">
      Total: ₹<?= number_format($grand_total, 2) ?>
    </div>

    <div class="checkout-box">
      <a class="checkout-btn" href="<?= BASE_URL ?>views/checkout.php">Proceed to Checkout →</a>
    </div>
  <?php endif; ?>
</div>
</main>


<?php require_once INCLUDES_PATH . 'footer.php'; ?>
</body>
</html>
