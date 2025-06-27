<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once INCLUDES_PATH . 'header.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    header('Location: orders.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = (int)$_GET['order_id'];

// Fetch order
$stmt = $connection->prepare("
    SELECT o.*, a.full_name, a.address_line, a.city, a.state, a.pincode
    FROM orders o
    JOIN addresses a ON o.address_id = a.address_id
    WHERE o.order_id = ? AND o.user_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) {
    echo "<p>Order not found.</p>";
    exit;
}

// Fetch items
$itemStmt = $connection->prepare("
    SELECT oi.quantity, oi.price, p.product_name, pi.image_url, s.size_value
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN sizes s ON oi.size_id = s.size_id
    LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_default = 1
    WHERE oi.order_id = ?
");
$itemStmt->bind_param("i", $order_id);
$itemStmt->execute();
$items = $itemStmt->get_result();
?>
<!DOCTYPE html><html><head><title>Order #<?= $order['order_id'] ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/orders.css"></head><body>
  <h2>Order #<?= $order['order_id'] ?> Details</h2>
  <p>Status: <strong><?= ucfirst($order['order_status']) ?></strong></p>
  <p>Placed on: <?= date('d M Y, h:i A', strtotime($order['placed_at'])) ?></p>
  <p>Shipping To: <?= htmlspecialchars($order['full_name']) ?>, <?= htmlspecialchars($order['address_line']) ?>, <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> - <?= htmlspecialchars($order['pincode']) ?></p>
  <table class="order-detail-table">
    <tr><th>Product</th><th>Size</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
    <?php $total = 0; while($i = $items->fetch_assoc()): ?>
      <tr>
        <td><img src="<?= UPLOADS_URL . $i['image_url'] ?>" alt="" class="img-thumb"> <?= htmlspecialchars($i['product_name']) ?></td>
        <td><?= $i['size_value'] ?></td>
        <td><?= $i['quantity'] ?></td>
        <td>₹<?= number_format($i['price'],2) ?></td>
        <td>₹<?= number_format($i['price'] * $i['quantity'], 2) ?></td>
      </tr>
      <?php $total += $i['price'] * $i['quantity']; endwhile; ?>
    <tr class="total-row"><td colspan="4">TOTAL</td><td>₹<?= number_format($total,2) ?></td></tr>
  </table>
  <div><a href="track_order.php?order_id=<?= $order_id ?>">Track My Order</a></div>
  <div><a href="orders.php">← Back to Orders</a></div>
<?php require_once INCLUDES_PATH.'footer.php'; ?></body></html>
