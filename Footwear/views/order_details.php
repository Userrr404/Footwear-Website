<?php
// order_details.php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Order Details | Elite Footwear</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" />
  <style>
    /* small enhancement for timeline connecting line */
    .timeline-line { height: 4px; background: #E5E7EB; position: absolute; left: 2rem; right: 2rem; top: 2.2rem; z-index: 0;}
    @media (min-width: 768px) {
      .timeline-line { left: 5rem; right: 5rem; top: 2.2rem; }
    }

    .timeline-line-filled { height: 4px; width: 100%; position: absolute; left: 5rem; right: 5rem; top: 1.2rem; z-index: -1; }
    @media (min-width: 768px) {
      .timeline-line-filled { left: 5rem; right: 5rem; top: 1.2rem; }
    }

    @media (max-width: 767px) {
      .timeline-line-filled { left: 2rem; right: 2rem; top: 1.2rem; }
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">
  <?php 

    require_once INCLUDES_PATH . 'header.php';

// Ensure user logged in and order_id present
if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    header('Location: orders.php');
    exit;
}

$user_id  = (int) $_SESSION['user_id'];
$order_id = (int) $_GET['order_id'];

// Fetch order and shipping address (assumes orders.address_id -> addresses.address_id)
$orderSql = "
  SELECT o.*,
         a.full_name, a.address_line1, a.address_line2, a.city, a.state, a.pincode, a.phone_number AS phone,
         s.courier_name AS shipping_provider, s.tracking_number, s.shipped_at AS shipment_shipped_at, s.delivery_status
  FROM orders o
  LEFT JOIN addresses a ON o.address_id = a.address_id
  LEFT JOIN shipments s ON o.order_id = s.order_id
  WHERE o.order_id = ? AND o.user_id = ?
  LIMIT 1
";
$stmt = $connection->prepare($orderSql);
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$orderRes = $stmt->get_result();
$order = $orderRes->fetch_assoc();

if (!$order) {
    echo '<div class="p-8 max-w-3xl mx-auto text-center text-red-600">Order not found or you don\'t have permission to view it.</div>';
    require_once INCLUDES_PATH . 'footer.php';
    exit;
}

// Fetch order items
$itemsSql = "
  SELECT oi.*, p.product_name, s.size_value, pi.image_url
  FROM order_items oi
  LEFT JOIN products p ON oi.product_id = p.product_id
  LEFT JOIN sizes s ON oi.size_id = s.size_id
  LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_default = 1
  WHERE oi.order_id = ?
";
$stmt2 = $connection->prepare($itemsSql);
$stmt2->bind_param('i', $order_id);
$stmt2->execute();
$itemsRes = $stmt2->get_result();
$items = $itemsRes->fetch_all(MYSQLI_ASSOC);

// Helper: format money
function money($n) { return '₹' . number_format((float)$n, 2); }

// Map shipping provider to tracking URL template (replace {tn} with tracking number)
$courierTemplates = [
    'bluedart' => 'https://www.bluedart.com/tracking?AWB={tn}',
    'delhivery' => 'https://track.delhivery.com/?cn={tn}',
    'fedex' => 'https://www.fedex.com/apps/fedextrack/?tracknumbers={tn}',
    'dtdc' => 'https://www.dtdc.in/tracker?awb={tn}',
    'ekart' => 'https://ekartlogistics.com/Track/{tn}',
    'shadowfax' => 'https://track.shadowfax.in/track/{tn}',
    // add more as needed
];

// Determine human-friendly provider and tracking link
$providerRaw = trim(strtolower($order['shipping_provider'] ?? ''));
$providerKey = '';
foreach ($courierTemplates as $k => $t) {
    if ($providerRaw !== '' && strpos($providerRaw, $k) !== false) { $providerKey = $k; break; }
}
$trackingNumber = trim($order['tracking_number'] ?? '');
$trackingUrl = '';
if ($providerKey && $trackingNumber) {
    $trackingUrl = str_replace('{tn}', urlencode($trackingNumber), $courierTemplates[$providerKey]);
} elseif ($trackingNumber) {
    // fallback to Google search for tracking number + provider or standalone
    $query = urlencode(($order['shipping_provider'] ?? '') . ' ' . $trackingNumber);
    $trackingUrl = "https://www.google.com/search?q={$query}";
}

// Determine order progress steps
$steps = [
    ['key' => 'ordered', 'label' => 'Ordered', 'time' => $order['placed_at'] ?? null],
    ['key' => 'paid', 'label' => 'Payment Received', 'time' => $order['paid_at'] ?? null],
    ['key' => 'processing', 'label' => 'Processing', 'time' => $order['paid_at'] ?? null], // keep same as paid if no separate
    ['key' => 'shipped', 'label' => 'Shipped', 'time' => $order['shipment_shipped_at'] ?? null],
    ['key' => 'delivered', 'label' => 'Delivered', 'time' => $order['delivered_at'] ?? null],
];

// Compute active index for styling. Priority: delivered > shipped > paid > placed (placed always present)
$activeIndex = 0;
if (!empty($order['delivered_at'])) {
    foreach ($steps as $i => $s) if ($s['key'] === 'delivered') $activeIndex = $i;
} elseif (!empty($order['shipment_shipped_at'])) {
    foreach ($steps as $i => $s) if ($s['key'] === 'shipped') $activeIndex = $i;
} elseif (!empty($order['paid_at'])) {
    foreach ($steps as $i => $s) if ($s['key'] === 'paid') $activeIndex = $i;
} else {
    $activeIndex = 0;
}

// Expected delivery estimate: prefer shipped_at + 3 days, otherwise placed_at + 5 days.
$expectedDelivery = null;
if (!empty($order['shipped_at'])) {
    $expectedDelivery = date('d M Y', strtotime('+3 days', strtotime($order['shipped_at'])));
} elseif (!empty($order['placed_at'])) {
    $expectedDelivery = date('d M Y', strtotime('+5 days', strtotime($order['placed_at'])));
}

// Calculate totals (fallback to order totals if present)
$totalFromItems = 0;
foreach ($items as $it) {
    $qty = (int)($it['quantity'] ?? 1);
    $price = (float)($it['price'] ?? 0);
    $totalFromItems += $qty * $price;
}
$displaySubtotal = isset($order['subtotal_amount']) ? (float)$order['subtotal_amount'] : $totalFromItems;
$discount = isset($order['discount_amount']) ? (float)$order['discount_amount'] : 0.00;
$tax = isset($order['tax_amount']) ? (float)$order['tax_amount'] : 0.00;
$shippingCharge = isset($order['shipping_amount']) ? (float)$order['shipping_amount'] : 0.00;
$grandTotal = isset($order['total_amount']) ? (float)$order['total_amount'] : ($displaySubtotal - $discount + $tax + $shippingCharge);

  ?>

  <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-5xl mx-auto space-y-6">
      <!-- Header / Order Summary -->
      <div class="bg-white rounded-2xl shadow p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-2xl font-semibold">Order 
              <span class="text-indigo-600">#<?= htmlspecialchars($order['order_number'] ?? $order['order_id']) ?></span>
            </h1>
            <p class="text-sm text-gray-500 mt-1">
              Placed on <?= htmlspecialchars(date('d M Y, h:i A', strtotime($order['placed_at']))) ?>
              • Status: 
              <span class="ml-1 inline-block px-3 py-1 rounded-full text-xs font-medium
                <?php
                  $s = $order['order_status'] ?? 'pending';
                  if ($s === 'delivered') echo 'bg-green-100 text-green-700';
                  elseif ($s === 'shipped') echo 'bg-indigo-100 text-indigo-700';
                  elseif ($s === 'processing' || $s === 'paid') echo 'bg-blue-100 text-blue-700';
                  elseif ($s === 'cancelled') echo 'bg-red-100 text-red-700';
                  else echo 'bg-yellow-100 text-yellow-700';
                ?>
              ">
                <?= ucfirst($order['order_status']) ?>
              </span>
            </p>
          </div>

          <div class="flex items-center gap-3">
            <a href="track_order.php?order_id=<?= $order['order_id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 shadow">
              <i class="fas fa-map-marker-alt"></i> Track Order
            </a>
            <a href="invoice.php?order_id=<?= $order['order_id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-100">
              <i class="fas fa-file-invoice"></i> Invoice
            </a>
          </div>
        </div>
      </div>

      <!-- Progress Tracker + Courier -->
      <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="text-lg font-medium mb-4">Order Progress</h2>

        <!-- Timeline -->
        <div class="relative">
          <div class="timeline-line"></div>
          <div class="flex justify-between py-4 relative">
            <?php foreach ($steps as $idx => $step): 
              $done = $idx <= $activeIndex;
              $timeLabel = $step['time'] ? date('d M, h:i A', strtotime($step['time'])) : '';
            ?>
              <div class="flex flex-col items-center flex-1 text-center relative z-10">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold
                            <?= $done ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600' ?>">
                  <?= $idx + 1 ?>
                </div>
                 <?php if ($idx < count($steps)-1): ?>
                  <div class="timeline-line-filled <?= $done ? 'bg-indigo-600' : 'bg-gray-300' ?>"></div>
                <?php endif; ?>
                <div class="mt-2 text-xs <?= $done ? 'text-indigo-600 font-medium' : 'text-gray-500' ?>">
                  <?= htmlspecialchars($step['label']) ?>
                </div>
                <?php if ($timeLabel): ?>
                  <div class="mt-1 text-xs text-gray-400"><?= htmlspecialchars($timeLabel) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Courier & ETA -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="col-span-1 md:col-span-2">
            <p class="text-sm text-gray-700"><strong>Expected delivery:</strong> 
              <span class="text-indigo-600"><?= $expectedDelivery ?? '—' ?></span>
            </p>
            <p class="text-sm text-gray-700 mt-1"><strong>Courier:</strong> 
              <span class="text-gray-800"><?= htmlspecialchars($order['shipping_provider'] ?? '—') ?></span>
            </p>
            <p class="text-sm text-gray-700 mt-1 flex items-center gap-2">
              <strong>Tracking number:</strong>
              <?php if ($trackingNumber): ?>
                <?php if ($trackingUrl): ?>
                  <a href="<?= htmlspecialchars($trackingUrl) ?>" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline"><?= htmlspecialchars($trackingNumber) ?></a>
                <?php else: ?>
                  <span class="text-gray-800"><?= htmlspecialchars($trackingNumber) ?></span>
                <?php endif; ?>
                <button onclick="copyTracking('<?= htmlspecialchars(addslashes($trackingNumber)) ?>')" title="Copy" class="ml-2 text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200">
                  <i class="far fa-copy"></i>
                </button>
              <?php else: ?>
                <span class="text-gray-400">Not available</span>
              <?php endif; ?>
            </p>
          </div>

          <div class="col-span-1 text-right md:text-left">
            <!-- Quick actions -->
            <div class="inline-flex items-center gap-2">
              <?php if ($trackingUrl && $trackingNumber): ?>
                <a href="<?= htmlspecialchars($trackingUrl) ?>" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">
                  <i class="fas fa-truck"></i> Track on courier site
                </a>
              <?php endif; ?>
              <?php if (($order['order_status'] ?? '') === 'pending'): ?>
                <a href="../php/cancel_order.php?order_id=<?= $order['order_id'] ?>" onclick="return confirm('Cancel this order?')" class="px-3 py-2 rounded-lg border border-red-300 text-red-600 hover:bg-red-50">
                  Cancel
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Shipping Address & Order Details -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl shadow p-6">
          <h3 class="font-medium mb-3">Shipping Address</h3>
          <p class="text-sm text-gray-800 font-medium"><?= htmlspecialchars($order['full_name'] ?? '') ?></p>
          <p class="text-sm text-gray-600 mt-1"><?= nl2br(htmlspecialchars(trim(($order['address_line1'] ?? '') . ' ' . ($order['address_line2'] ?? '')))) ?></p>
          <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars(($order['city'] ?? '') . ', ' . ($order['state'] ?? '') . ' - ' . ($order['pincode'] ?? '')) ?></p>
          <?php if (!empty($order['phone'])): ?>
            <p class="text-sm text-gray-600 mt-1">Phone: <?= htmlspecialchars($order['phone']) ?></p>
          <?php endif; ?>
        </div>

        <div class="bg-white rounded-2xl shadow p-6 md:col-span-2">
          <div class="flex items-start justify-between">
            <h3 class="font-medium">Items in this order</h3>
            <p class="text-sm text-gray-500"><?= count($items) ?> items</p>
          </div>

          <div class="mt-4 space-y-4">
            <?php foreach ($items as $it): 
              $img = !empty($it['image_url']) ? UPLOADS_URL . $it['image_url'] : BASE_URL . 'assets/images/no-image.png';
              $qty = (int)($it['quantity'] ?? 1);
              $price = (float)($it['price'] ?? 0);
            ?>
              <div class="flex items-center gap-4 border-b pb-4">
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($it['product_name'] ?? 'Product') ?>" class="w-20 h-20 object-cover rounded-lg border">
                <div class="flex-1">
                  <div class="font-medium"><?= htmlspecialchars($it['product_name'] ?? 'Product') ?></div>
                  <?php if (!empty($it['size_value'])): ?>
                    <div class="text-xs text-gray-500 mt-1">Size: <?= htmlspecialchars($it['size_value']) ?></div>
                  <?php endif; ?>
        
                </div>
                <div class="text-right">
                  <div class="font-medium"><?= money($price) ?></div>
                  <div class="text-xs text-gray-500 mt-1">Qty: <?= $qty ?></div>
                  <div class="text-xs text-gray-500 mt-1">Subtotal: <?= money($qty * $price) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Price summary -->
          <div class="mt-6 border-t pt-4 flex flex-col md:flex-row md:items-center md:justify-end gap-4">
            <div class="w-full md:w-72 bg-gray-50 p-4 rounded-lg">
              <div class="flex justify-between text-sm text-gray-600"><span>Subtotal</span><span><?= money($displaySubtotal) ?></span></div>
              <div class="flex justify-between text-sm text-gray-600 mt-2"><span>Discount</span><span>- <?= money($discount) ?></span></div>
              <div class="flex justify-between text-sm text-gray-600 mt-2"><span>Tax</span><span><?= money($tax) ?></span></div>
              <div class="flex justify-between text-sm text-gray-600 mt-2"><span>Shipping</span><span><?= money($shippingCharge) ?></span></div>
              <div class="flex justify-between text-lg font-semibold mt-3"><span>Total</span><span class="text-indigo-600"><?= money($grandTotal) ?></span></div>
              <div class="text-xs text-gray-400 mt-2">Payment method: <?= htmlspecialchars($order['payment_method'] ?? '—') ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer actions -->
      <div class="flex justify-between items-center">
        <a href="orders.php" class="text-gray-600 hover:underline">← Back to Orders</a>
        <div class="flex items-center gap-3">
          <?php if (($order['order_status'] ?? '') === 'delivered'): ?>
            <a href="return.php?order_id=<?= $order['order_id'] ?>" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-100">Request Return</a>
          <?php endif; ?>
          <a href="contact_support.php?order_id=<?= $order['order_id'] ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Contact Support</a>
        </div>
      </div>
    </div>
  </main>

  <?php require_once INCLUDES_PATH . 'footer.php'; ?>

  <script>
    // copy tracking number
    function copyTracking(tn) {
      if (!tn) return;
      navigator.clipboard?.writeText(tn).then(function(){
        alert('Tracking number copied to clipboard');
      }).catch(function(){
        prompt('Copy tracking number', tn);
      });
    }
  </script>
</body>
</html>
