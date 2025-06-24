<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'header.php';
$order_id = $_GET['order_id'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Success | Elite Footwear</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/order_success.css">
</head>
<body>
  <div class="success-container">
    <h1>✅ Thank you! Your order has been placed.</h1>
    <p>Your order ID is <strong>#<?= htmlspecialchars($order_id) ?></strong></p>
    <p>You will receive a confirmation email shortly.</p>
    <a href="<?= BASE_URL ?>views/index.php">Continue Shopping</a>
  </div>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
</body>
</html>
