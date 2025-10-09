<?php
// track_order.php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Track Order #<?= htmlspecialchars($order['order_number'] ?? $order['order_id']) ?> | Elite Footwear</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">
  <?php require_once INCLUDES_PATH . 'header.php'; 
  
    // Ensure logged in and order_id present
    if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
      header('Location: orders.php');
      exit;
    }

    $user_id  = (int) $_SESSION['user_id'];
    $order_id = (int) $_GET['order_id'];

    // Fetch order + shipment info
    $sql = "
      SELECT o.order_id, o.order_number, o.placed_at, o.paid_at, o.delivered_at, o.order_status, o.payment_status,
          s.courier_name, s.tracking_number, s.shipped_at, s.delivery_status
      FROM orders o
      LEFT JOIN shipments s ON o.order_id = s.order_id
      WHERE o.order_id = ? AND o.user_id = ?
      LIMIT 1
    ";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res->fetch_assoc();

    if (!$order) {
      echo "<div class='p-8 max-w-xl mx-auto text-center text-red-600'>Order not found or access denied.</div>";
      exit;
    }

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
    $actStmt->bind_param("i", $order_id);
    $actStmt->execute();
    $actRes = $actStmt->get_result();

    while ($row = $actRes->fetch_assoc()) {
      $statusKey = strtolower(str_replace(' ', '_', trim($row['status'])));
      if (array_key_exists($statusKey, $shipmentTimes)) {
        $shipmentTimes[$statusKey] = $row['created_at'];
      }
    }
    $actStmt->close();

    // ---------- Courier Tracking ----------
    $courierTemplates = [
      'bluedart' => 'https://www.bluedart.com/tracking?AWB={tn}',
      'delhivery' => 'https://track.delhivery.com/?cn={tn}',
      'fedex' => 'https://www.fedex.com/apps/fedextrack/?tracknumbers={tn}',
      'dtdc' => 'https://www.dtdc.in/tracker?awb={tn}',
      'ekart' => 'https://ekartlogistics.com/Track/{tn}',
      'shadowfax' => 'https://track.shadowfax.in/track/{tn}',
    ];

    $providerRaw = strtolower(trim($order['courier_name'] ?? ''));
    $trackingNumber = trim($order['tracking_number'] ?? '');
    $trackingUrl = '';

    foreach ($courierTemplates as $key => $tpl) {
      if ($providerRaw && strpos($providerRaw, $key) !== false && $trackingNumber) {
        $trackingUrl = str_replace('{tn}', urlencode($trackingNumber), $tpl);
        break;
      }
    }
    if (!$trackingUrl && $trackingNumber) {
      $query = urlencode(($order['courier_name'] ?? '') . " " . $trackingNumber);
      $trackingUrl = "https://www.google.com/search?q=$query";
    }

    // ---------- Build Tracking Steps ----------
    $steps = [
      ['key' => 'ordered', 'label' => 'Ordered', 'time' => $order['placed_at']],
      ['key' => 'paid', 'label' => $order['paid_at'] ? 'Payment Received' : 'Payment Pending', 'time' => $order['paid_at'] ?? null],
      ['key' => 'processing', 'label' => 'Processing', 'time' => $shipmentTimes['in_transit']],
      ['key' => 'shipped', 'label' => 'Shipped', 'time' => $shipmentTimes['shipped'] ?? $order['shipped_at']],
      ['key' => 'out_for_delivery', 'label' => 'Out for Delivery', 'time' => $shipmentTimes['out_for_delivery']],
      ['key' => 'delivered', 'label' => 'Delivered', 'time' => $shipmentTimes['delivered']],
    ];

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

  <main class="flex-grow px-8 py-8">
    <div class="bg-white rounded-2xl shadow p-6">
      <h1 class="text-2xl font-semibold">Track Order 
        <span class="text-indigo-600">#<?= htmlspecialchars($order['order_number'] ?? $order['order_id']) ?></span>
      </h1>
      <p class="text-sm text-gray-500 mt-1">
        Placed on <?= date("d M Y, h:i A", strtotime($order['placed_at'])) ?>
        • Order: 
              <span class="ml-1 inline-block px-3 py-1 rounded-full text-xs font-medium
                <?php
                  $s = $order['order_status'] ?? 'pending';
                  if ($s === 'delivered') echo 'bg-green-100 text-green-700';
                  elseif ($s === 'shipped') echo 'bg-indigo-100 text-indigo-700';
                  elseif ($s === 'processing') echo 'bg-blue-100 text-blue-700';
                  elseif ($s === 'out_for_delivery') echo 'bg-red-100 text-red-700';
                  elseif ($s === 'returned') echo 'bg-red-100 text-red-700';
                  elseif ($s === 'refunded') echo 'bg-red-100 text-red-700';
                  elseif ($s === 'cancelled') echo 'bg-red-100 text-red-700';
                  elseif ($s === 'failed') echo 'bg-red-100 text-red-700';
                  else echo 'bg-yellow-100 text-yellow-700';
                ?>
              ">
                <?= ucfirst($order['order_status']) ?>
              </span>

              <!-- Payment status -->
              • Payment:
              <span class="ml-1 inline-block px-3 py-1 rounded-full text-xs font-medium
                <?php
                  $s = $order['payment_status'] ?? 'pending';
                  if ($s === 'paid') echo 'bg-green-100 text-green-700';
                  elseif ($s === 'refunded') echo 'bg-blue-100 text-blue-700';
                  elseif ($s === 'partially_refunded') echo 'bg-red-100 text-red-700';
                  elseif ($s === 'failed') echo 'bg-indigo-red text-red-700';
                  else echo 'bg-yellow-100 text-yellow-700';
                ?>
              ">
                <?= ucfirst($order['payment_status']) ?>
              </span>
      </p>

      <!-- Timeline -->
      <div class="mt-8 hidden lg:block">
        <div class="flex justify-between relative">
          <?php foreach ($steps as $idx => $step): 
            $done = $idx <= $activeIndex;
            $timeLabel = $step['time'] ? date('d M, h:i A', strtotime($step['time'])) : '';
          ?>
            <div class="flex flex-col items-center flex-1 text-center relative">
              <div class="w-10 h-10 rounded-full flex items-center justify-center font-medium 
                <?= $done ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600' ?>">
                <?= $idx+1 ?>
              </div>
              <div class="mt-2 text-xs <?= $done ? 'text-indigo-600 font-semibold' : 'text-gray-500' ?>">
                <?= htmlspecialchars($step['label']) ?>
              </div>
              <?php if ($timeLabel): ?>
                <div class="mt-1 text-xs text-gray-400"><?= $timeLabel ?></div>
              <?php endif; ?>
              <?php if ($idx < count($steps)-1): ?>
                <div class="absolute top-5 left-1/2 w-full h-1 <?= $done ? 'bg-indigo-600' : 'bg-gray-300' ?>"></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Courier info -->
      <div class="mt-8 space-y-2">
        <p><strong>Courier:</strong> <?= htmlspecialchars($order['courier_name'] ?? '—') ?></p>
        <p class="flex items-center gap-2">
          <strong>Tracking Number:</strong>
          <?php if ($trackingNumber): ?>
            <a href="<?= htmlspecialchars($trackingUrl) ?>" target="_blank" class="text-indigo-600 hover:underline"><?= htmlspecialchars($trackingNumber) ?></a>
            <button onclick="copyTracking('<?= htmlspecialchars(addslashes($trackingNumber)) ?>')" class="ml-2 text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200">
              <i class="far fa-copy"></i>
            </button>
          <?php else: ?>
            <span class="text-gray-400">Not available</span>
          <?php endif; ?>
        </p>
      </div>

      <!-- Shipment Activity Feed -->
      <div class="bg-white rounded-2xl shadow p-6 mt-6">
        <h2 class="text-lg font-medium mb-4 flex items-center gap-2">
          <i class="fas fa-truck text-indigo-600"></i> Shipment Updates
        </h2>

        <?php
          // fetch shipment events (if you create a table `shipment_events`)
          $eventsSql = "
            SELECT status, location, description, created_at
            FROM shipment_activity
            WHERE order_id = ?
            ORDER BY created_at DESC
          ";
          $stmtEv = $connection->prepare($eventsSql);
          $stmtEv->bind_param('i', $order_id);
          $stmtEv->execute();
          $eventsRes = $stmtEv->get_result();
          $events = $eventsRes->fetch_all(MYSQLI_ASSOC);
        ?>

        <?php if ($events && count($events) > 0): ?>
          <ol class="relative border-l border-gray-200 ml-3">
            <?php foreach ($events as $ev): ?>
              <li class="mb-6 ml-4">
                <div class="absolute w-3 h-3 bg-indigo-600 rounded-full mt-1.5 -left-1.5 border border-white"></div>
                <time class="mb-1 text-xs font-normal leading-none text-gray-400">
                  <?= date('d M, h:i A', strtotime($ev['created_at'])) ?>
                </time>
                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($ev['status']) ?></p>
                <?php if (!empty($ev['location'])): ?>
                  <p class="text-xs text-gray-500"><?= htmlspecialchars($ev['location']) ?></p>
                <?php endif; ?>
                <?php if (!empty($ev['description'])): ?>
                  <p class="text-xs text-gray-500"><?= htmlspecialchars($ev['description']) ?></p>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p class="text-sm text-gray-500">No tracking updates yet.</p>
        <?php endif; ?>
      </div>



      <!-- Actions -->
      <div class="mt-6 flex flex-wrap gap-3">
        <?php if ($trackingUrl && $trackingNumber): ?>
          <a href="<?= htmlspecialchars($trackingUrl) ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">
            <i class="fas fa-truck"></i> Track on Courier Site
          </a>
        <?php endif; ?>
        <a href="order_details.php?order_id=<?= $order_id ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50">
          <i class="fas fa-info-circle"></i> View Order Details
        </a>
        <a href="contact_support.php?order_id=<?= $order_id ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
          <i class="fas fa-headset"></i> Contact Support
        </a>
      </div>
    </div>
  </main>

  <?php require_once INCLUDES_PATH . 'footer.php'; ?>

  <script>
    function copyTracking(tn) {
      if (!tn) return;
      navigator.clipboard.writeText(tn).then(() => {
        alert("Tracking number copied!");
      }).catch(() => {
        prompt("Copy tracking number", tn);
      });
    }
  </script>
</body>
</html>
