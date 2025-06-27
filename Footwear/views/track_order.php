<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once INCLUDES_PATH . 'header.php';

if (!isset($_SESSION['user_id'], $_GET['order_id'])) {
    header('Location: orders.php');
    exit;
}
$order_id = (int)$_GET['order_id'];
$user_id = $_SESSION['user_id'];

$o = $connection->prepare("SELECT order_status, placed_at FROM orders WHERE order_id=? AND user_id=?");
$o->bind_param("ii", $order_id, $user_id);
$o->execute();
$order = $o->get_result()->fetch_assoc();
if (!$order) die("<p>Order not found.</p>");

$steps = ['pending'=>'Order Placed','processing'=>'Being Prepared','shipped'=>'Shipped','delivered'=>'Delivered','cancelled'=>'Cancelled'];
$status = $order['order_status'];
?>
<!DOCTYPE html><html><head><title>Track Order #<?= $order_id ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/orders.css"></head><body>
  <h2>Tracking Order #<?= $order_id ?></h2>
  <div class="track-steps">
    <?php foreach($steps as $key => $label): 
      $done = array_search($key, array_keys($steps)) <= array_search($status, array_keys($steps)); ?>
      <div class="step <?= $done ? 'done' : '' ?>">
        <span class="bullet"><?= $done ? '✓' : '∘' ?></span>
        <span class="label"><?= $label ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <p>Status: <strong><?= ucfirst($status) ?></strong></p>
  <div><a href="orders.php">← Back to Orders</a></div>
<?php require_once INCLUDES_PATH.'footer.php'; ?></body></html>
