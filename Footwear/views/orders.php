<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once INCLUDES_PATH . 'header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql = "SELECT o.*, a.full_name, a.address_line, a.city, a.state, a.pincode 
        FROM orders o 
        LEFT JOIN addresses a ON o.address_id = a.address_id 
        WHERE o.user_id = ?";
$params = [$user_id];
$types = "i";

if (in_array($status_filter, ['pending', 'delivered', 'cancelled'])) {
    $sql .= " AND o.order_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY o.placed_at DESC";

$stmt = $connection->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Your Orders | Elite Footwear</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/orders.css">
</head>
<body>

<h1>🧾 Your Orders</h1>

<div class="status-tabs">
  <a href="?status=all" class="<?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
  <a href="?status=pending" class="<?= $status_filter === 'pending' ? 'active' : '' ?>">Pending</a>
  <a href="?status=delivered" class="<?= $status_filter === 'delivered' ? 'active' : '' ?>">Delivered</a>
  <a href="?status=cancelled" class="<?= $status_filter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
</div>

<?php if ($orders->num_rows > 0): ?>
  <?php while ($order = $orders->fetch_assoc()): ?>
    <div class="order-card">
      <p><strong>Order #<?= $order['order_id'] ?></strong></p>
      <p>Status: <span class="status <?= $order['order_status'] ?>"><?= ucfirst($order['order_status']) ?></span></p>
      <p>Total: ₹<?= number_format($order['total_amount'], 2) ?></p>
      <p>Placed On: <?= date('d M Y, h:i A', strtotime($order['placed_at'])) ?></p>
      <p><strong>Ship To:</strong> <?= htmlspecialchars($order['full_name']) ?>, <?= htmlspecialchars($order['address_line']) ?>, <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> - <?= $order['pincode'] ?></p>
      
      <div class="actions">
        <a href="order_details.php?order_id=<?= $order['order_id'] ?>">📄 View Details</a>
        <a href="track_order.php?order_id=<?= $order['order_id'] ?>">📍 Track</a>
        <?php if ($order['order_status'] === 'pending'): ?>
          <a class="cancel" href="cancel_order.php?order_id=<?= $order['order_id'] ?>" onclick="return confirm('Are you sure you want to cancel this order?')">❌ Cancel</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endwhile; ?>
<?php else: ?>
  <p style="text-align:center; margin-top: 40px;">No orders found for this filter.</p>
<?php endif; ?>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
</body>
</html>
