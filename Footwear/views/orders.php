<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Your Orders | Elite Footwear</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">
<?php require_once INCLUDES_PATH . 'header.php'; ?>
<?php
  if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Filters
$order_status_filter = $_GET['order_status'] ?? 'all';
$payment_status_filter = $_GET['payment_status'] ?? 'all';
$search_order = trim($_GET['search'] ?? '');

// Query
$sql = "SELECT * FROM orders WHERE user_id=?";
$params = [$user_id];
$types = "i";

if (in_array($order_status_filter, ['pending','processing','shipped','delivered','cancelled','failed'])) {
    $sql .= " AND order_status = ?";
    $params[] = $order_status_filter;
    $types .= "s";
}

if (in_array($payment_status_filter, ['paid','failed','refunded','pending','partially_refunded'])) {
    $sql .= " AND payment_status = ?";
    $params[] = $payment_status_filter;
    $types .= "s";
}

if (!empty($search_order)) {
    $sql .= " AND order_number LIKE ?";
    $params[] = "%$search_order%";
    $types .= "s";
}

$sql .= " ORDER BY placed_at DESC LIMIT 20";
$stmt = $connection->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result();
?>

<main class="flex-grow container mx-auto px-4 py-8">
  <h1 class="text-2xl md:text-3xl font-bold text-center py-6">🧾 Your Orders</h1>

  <!-- Filters -->
  <form method="get" class="flex flex-wrap justify-center items-center gap-3 mb-6">
    <select name="order_status" class="border border-gray-300 rounded-full px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="all" <?= $order_status_filter=='all'?'selected':'' ?>>All Orders</option>
      <option value="pending" <?= $order_status_filter=='pending'?'selected':'' ?>>Pending</option>
      <option value="processing" <?= $order_status_filter=='processing'?'selected':'' ?>>Processing</option>
      <option value="shipped" <?= $order_status_filter=='shipped'?'selected':'' ?>>Shipped</option>
      <option value="delivered" <?= $order_status_filter=='delivered'?'selected':'' ?>>Delivered</option>
      <option value="cancelled" <?= $order_status_filter=='cancelled'?'selected':'' ?>>Cancelled</option>
      <option value="failed" <?= $order_status_filter=='failed'?'selected':'' ?>>Failed</option>
    </select>

    <select name="payment_status" class="border border-gray-300 rounded-full px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="all" <?= $payment_status_filter=='all'?'selected':'' ?>>All Payments</option>
      <option value="paid" <?= $payment_status_filter=='paid'?'selected':'' ?>>Paid</option>
      <option value="pending" <?= $payment_status_filter=='pending'?'selected':'' ?>>Pending</option>
      <option value="failed" <?= $payment_status_filter=='failed'?'selected':'' ?>>Failed</option>
      <option value="refunded" <?= $payment_status_filter=='refunded'?'selected':'' ?>>Refunded</option>
      <option value="partially_refunded" <?= $payment_status_filter=='partially_refunded'?'selected':'' ?>>Partially Refunded</option>
    </select>

    <div class="relative">
      <i class="fa-solid fa-magnifying-glass text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
      <input type="text" name="search" placeholder="Search Order #"
             value="<?= htmlspecialchars($search_order) ?>"
             class="pl-10 pr-4 py-2 rounded-full border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500">
    </div>

    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-full text-sm hover:bg-blue-700">
      Apply
    </button>
  </form>

  <div class="max-w-6xl mx-auto px-4">
    <?php if ($orders->num_rows > 0): ?>
      <?php while ($order = $orders->fetch_assoc()): ?>
        <?php
          $items_sql = "SELECT oi.product_name, oi.quantity, s.size_value, pi.image_url
                        FROM order_items oi
                        LEFT JOIN sizes s ON oi.size_id = s.size_id
                        LEFT JOIN product_images pi ON oi.product_id = pi.product_id AND pi.is_default=1
                        WHERE oi.order_id=?";
          $stmt_items = $connection->prepare($items_sql);
          $stmt_items->bind_param("i", $order['order_id']);
          $stmt_items->execute();
          $items = $stmt_items->get_result();
          $all_items = $items->fetch_all(MYSQLI_ASSOC);
        ?>

        <div class="bg-white rounded-xl shadow-md p-5 mb-6 hover:shadow-lg transition">
          <!-- Header -->
          <div class="flex flex-wrap justify-between items-center mb-3">
            <h3 class="text-lg font-semibold">Order #<?= htmlspecialchars($order['order_number']) ?></h3>
            <div class="flex gap-2 items-center">
              <span class="px-3 py-1 rounded-full text-xs font-semibold
                <?php if ($order['order_status']=='pending') echo 'bg-yellow-100 text-yellow-700';
                      elseif ($order['order_status'] == 'shipped') echo 'bg-indigo-100 text-indigo-700';
                  elseif ($order['order_status'] == 'processing') echo 'bg-blue-100 text-blue-700';
                  elseif ($order['order_status']== 'out_for_delivery') echo 'bg-red-100 text-red-700';
                  elseif ($order['order_status'] == 'returned') echo 'bg-red-100 text-red-700';
                  elseif ($order['order_status'] == 'refunded') echo 'bg-red-100 text-red-700';
                  elseif ($order['order_status'] == 'cancelled') echo 'bg-red-100 text-red-700';
                  elseif ($order['order_status'] == 'failed') echo 'bg-red-100 text-red-700';
                ?>">
                <?= ucfirst($order['order_status']) ?>
              </span>

              <span class="px-3 py-1 rounded-full text-xs font-semibold
                <?php if ($order['payment_status']=='paid') echo 'bg-green-100 text-green-700';
                      elseif ($order['payment_status']=='pending') echo 'bg-yellow-100 text-yellow-700';
                      elseif ($order['payment_status']=='failed') echo 'bg-red-100 text-red-700';
                      elseif ($order['payment_status']=='refunded') echo 'bg-gray-200 text-gray-700';
                      elseif ($order['payment_status'] == 'partially_refunded') echo 'bg-red-100 text-red-700'; ?>">
                <?= ucfirst($order['payment_status']) ?>
              </span>
            </div>
          </div>

          <!-- Payment failed alert -->
          <?php if ($order['order_status'] == 'failed'): ?>
            <div class="mt-2 p-3 bg-red-50 border border-red-300 text-red-700 rounded-lg text-sm">
              ⚠️ This order failed due to a payment or system error. You can retry the payment below.
            </div>
          <?php endif; ?>

          <!-- Show First Product -->
          <?php if (!empty($all_items)): $first = $all_items[0]; ?>
            <div class="flex flex-col sm:flex-row items-start gap-4 mt-4">
              <?php if ($order['order_status'] == 'failed'): ?>
                <div class="w-24 h-24 flex items-center justify-center bg-gray-200 rounded-lg text-gray-500">
                  No Image
                </div>
              <?php elseif (!empty($first['image_url'])): ?>
                <img src="<?= BASE_URL . 'uploads/products/' . htmlspecialchars($first['image_url']) ?>" 
                     alt="<?= htmlspecialchars($first['product_name']) ?>" 
                     class="w-24 h-24 object-cover rounded-lg border">
              <?php else: ?>
                <div class="w-24 h-24 flex items-center justify-center bg-gray-200 rounded-lg text-gray-500">
                  No Image
                </div>
              <?php endif; ?>

              <div class="flex-1">
                <p class="font-semibold"><?= htmlspecialchars($first['product_name']) ?></p>
                <p class="text-sm text-gray-600">Qty: <?= (int)$first['quantity'] ?> 
                  <?= !empty($first['size_value']) ? "• Size: " . htmlspecialchars($first['size_value']) : "" ?>
                </p>
                <p class="text-sm text-gray-500"><strong>Placed On:</strong> 
                  <?= date('d M Y, h:i A', strtotime($order['placed_at'])) ?></p>
              </div>
            </div>
          <?php endif; ?>

          <!-- Collapsible Section for More Items -->
          <?php if (count($all_items) > 1): ?>
            <button id="toggle-btn-<?= $order['order_id'] ?>" onclick="toggleItems(<?= $order['order_id'] ?>)"
                    class="mt-3 text-blue-600 text-sm font-medium">Show all items ▾</button>

            <div id="order-items-<?= $order['order_id'] ?>" class="hidden mt-3 space-y-3">
              <?php foreach (array_slice($all_items, 1) as $it): ?>
                <div class="flex items-center gap-4">
                  <?php if ($order['order_status'] == 'failed'): ?>
                    <div class="w-16 h-16 flex items-center justify-center bg-gray-200 rounded-lg text-gray-500">No Image</div>
                  <?php elseif (!empty($it['image_url'])): ?>
                    <img src="<?= BASE_URL . 'uploads/products/' . htmlspecialchars($it['image_url']) ?>" 
                         alt="<?= htmlspecialchars($it['product_name']) ?>" 
                         class="w-16 h-16 object-cover rounded-lg border">
                  <?php else: ?>
                    <div class="w-16 h-16 flex items-center justify-center bg-gray-200 rounded-lg text-gray-500">No Image</div>
                  <?php endif; ?>
                  <div>
                    <p class="font-medium"><?= htmlspecialchars($it['product_name']) ?></p>
                    <p class="text-sm text-gray-600">Qty: <?= (int)$it['quantity'] ?> 
                      <?= !empty($it['size_value']) ? "• Size: " . htmlspecialchars($it['size_value']) : "" ?>
                    </p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- Progress bar (skip if failed) -->
          <?php if ($order['order_status'] != 'failed'): ?>
            <?php

              // ---------- Fetch shipment activity (for timestamps) ----------
              $shipmentTimes = [
                'ordered' => null,
                'payment_received' => null,
                'in_transit' => null,
                'shipped' => null,
                'out_for_delivery' => null,
                'delivered' => null
              ];

              $actSql = "SELECT status, created_at FROM shipment_activity WHERE order_id = ? ORDER BY created_at ASC";
              $actStmt = $connection->prepare($actSql);
              $actStmt->bind_param("i", $order['order_id']);
              $actStmt->execute();
              $actRes = $actStmt->get_result();

              while ($row = $actRes->fetch_assoc()) {
                $statusKey = strtolower(str_replace(' ', '_', trim($row['status'])));
                if (array_key_exists($statusKey, $shipmentTimes)) {
                  $shipmentTimes[$statusKey] = $row['created_at'];
                }
              }
              $actStmt->close();
              // Build timeline steps similar to track_order.php
              $steps = [
                ['key' => 'ordered', 'label' => 'Ordered', 'time' => $order['placed_at']],
                ['key' => 'paid', 'label' => $order['paid_at'] ? 'Payment Received' : 'Payment Pending', 'time' => $order['paid_at'] ?? null],
                ['key' => 'processing', 'label' => 'Processing', 'time' => $shipmentTimes['in_transit']],
                ['key' => 'shipped', 'label' => 'Shipped', 'time' => $shipmentTimes['shipped'] ?? $order['shipped_at']],
                ['key' => 'out_for_delivery', 'label' => 'Out for Delivery', 'time' => $shipmentTimes['out_for_delivery']],
                ['key' => 'delivered', 'label' => 'Delivered', 'time' => $shipmentTimes['delivered']],
              ];

              // Determine active index
              $statusOrder = ['pending','processing','shipped','out_for_delivery','delivered'];
              // ---------- Determine active index ----------
              $activeIndex = 0;
              if (!empty($shipmentTimes['delivered'])) {
                foreach ($steps as $i => $s) if ($s['key'] === 'delivered') $activeIndex = $i;
              } elseif (!empty($shipmentTimes['out_for_delivery'])) {
                foreach ($steps as $i => $s) if ($s['key'] === 'out_for_delivery') $activeIndex = $i;
              } elseif (!empty($shipmentTimes['shipped'])) {
                foreach ($steps as $i => $s) if ($s['key'] === 'shipped') $activeIndex = $i;
              } elseif (!empty($shipmentTimes['in_transit'])) {
                foreach ($steps as $i => $s) if ($s['key'] === 'processing') $activeIndex = $i;
              } elseif (!empty($order['paid_at'])) {
                foreach ($steps as $i => $s) if ($s['key'] === 'paid') $activeIndex = $i;
              } else {
                $activeIndex = 0;
              }
            ?>

            <div class="mt-6">
              <div class="flex justify-between relative">
                <?php
                  foreach ($steps as $idx => $step):
                    $done = $activeIndex >= $idx;
                    $timeLabel = $step['time'] ? date('d M, h:i A', strtotime($step['time'])) : '';
                ?>
                  <div class="flex-1 flex flex-col items-center text-center relative">
                    <div class="w-8 h-8 flex items-center justify-center rounded-full text-xs font-bold z-10
                      <?= $done ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600' ?>">
                      <?= $idx+1 ?>
                    </div>
                    <div class="mt-1 text-[10px] <?= $done ? 'text-blue-600 font-medium' : 'text-gray-600' ?> ">
                      <?= htmlspecialchars($step['label']) ?>
                    </div>  
                    <?php if($timeLabel): ?>
                      <div class="mt-1 text-[10px] text-gray-400"><?= $timeLabel ?></div> 
                    <?php endif; ?>  
                    <?php if ($idx < count($steps)-1): ?>
                      <div class="absolute top-4 left-1/2 w-full h-1 <?= $done ? 'bg-blue-600' : 'bg-gray-300' ?>"></div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Actions -->
          <div class="flex flex-wrap gap-3 mt-6">
            <a href="order_details.php?order_id=<?= $order['order_id'] ?>" 
               class="px-4 py-2 rounded-full border border-blue-600 text-blue-600 text-sm hover:bg-blue-600 hover:text-white">
               📄 View
            </a>

            <?php if ($order['order_status'] != 'failed'): ?>
              <a href="track_order.php?order_id=<?= $order['order_id'] ?>" 
                 class="px-4 py-2 rounded-full border border-green-600 text-green-600 text-sm hover:bg-green-600 hover:text-white">
                 📍 Track
              </a>

              <a href="invoice.php?order_id=<?= $order['order_id'] ?>" 
                 class="px-4 py-2 rounded-full border border-gray-600 text-gray-600 text-sm hover:bg-gray-600 hover:text-white">
                 🧾 Invoice
              </a>
            <?php endif; ?>

            <?php if ($order['payment_status'] === 'failed'): ?>
              <a href="retry_payment.php?order_id=<?= $order['order_id'] ?>" 
                 class="px-4 py-2 rounded-full border border-yellow-600 text-yellow-600 text-sm hover:bg-yellow-600 hover:text-white">
                 🔁 Retry Payment
              </a>
            <?php endif; ?>

            <?php if ($order['order_status'] === 'pending'): ?>
              <a href="../php/cancel_order.php?order_id=<?= $order['order_id'] ?>" 
                 onclick="return confirm('Are you sure you want to cancel this order?')"
                 class="px-4 py-2 rounded-full border border-red-600 text-red-600 text-sm hover:bg-red-600 hover:text-white">
                 ❌ Cancel
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="text-center text-gray-500 py-10">No orders found for this filter.</p>
    <?php endif; ?>
  </div>
</main>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>

<script>
function toggleItems(orderId) {
  const section = document.getElementById("order-items-" + orderId);
  const btn = document.getElementById("toggle-btn-" + orderId);
  section.classList.toggle("hidden");
  btn.innerText = section.classList.contains("hidden") ? "Show all items ▾" : "Hide items ▴";
}
</script>
</body>
</html>
