<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if ($order_id <= 0) {
    http_response_code(400);
    echo "<div class='max-w-xl mx-auto mt-20 p-6 bg-white rounded-xl shadow text-center'>Invalid order ID.</div>";
    require_once INCLUDES_PATH . 'footer.php';
    exit;
}

// Fetch order + user info
$order_stmt = $connection->prepare("
    SELECT o.order_number, o.placed_at, o.total_amount, o.payment_method, o.payment_status, o.shipping_amount, o.discount_amount, o.tax_amount, u.user_email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ?
    LIMIT 1
");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_res = $order_stmt->get_result();
$order = $order_res->fetch_assoc();

if (!$order) {
    http_response_code(404);
    echo "<div class='max-w-xl mx-auto mt-20 p-6 bg-white rounded-xl shadow text-center'>Order not found.</div>";
    exit;
}

// Fetch items
$items_stmt = $connection->prepare("
    SELECT oi.product_name, oi.quantity, oi.price, oi.size_id, oi.discount, oi.tax, oi.total, s.size_value
    FROM order_items oi
    LEFT JOIN sizes s ON oi.size_id = s.size_id
    WHERE order_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_res = $items_stmt->get_result();

// Format placed_at in Asia/Kolkata
$dt = new DateTime($order['placed_at']);
$placed_at = $dt->format('j M Y, H:i'); // e.g., 12 Sep 2025, 11:34

// Friendly estimated delivery (simple heuristic: +5 business days)
$est = clone $dt;
$days_added = 7; // conservative
$est->modify("+{$days_added} days");
$est_delivery = $est->format('j M Y');

// safe outputs
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Order Success | Elite Footwear</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* small polish */
    .glass { background: rgba(255,255,255,0.8); backdrop-filter: blur(6px); }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">
  <?php require_once INCLUDES_PATH . 'header.php'; ?>

<main class="flex-grow container mx-auto px-4 py-8">
  <div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-2xl shadow p-6 md:p-10 glass">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-extrabold text-green-600 mb-1">🎉 Order Confirmed</h1>
          <p class="text-sm text-gray-600">Thanks for shopping with Elite Footwear — your order is being processed.</p>
        </div>
        <div class="text-right">
          <div class="inline-flex items-center gap-2">
             <span class="px-3 py-1 rounded-full text-sm font-semibold
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
          </div>
        </div>
      </div>

      <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 border rounded-lg">
          <div class="text-xs text-gray-500">Order</div>
          <div class="mt-1 flex items-center gap-3">
            <div class="font-mono font-semibold text-lg">#<?= e($order['order_number']) ?></div>
            <button id="copyOrderBtn" class="text-xs text-blue-600 underline">Copy</button>
          </div>
          <div class="text-sm text-gray-500 mt-1">Placed at: <span class="font-medium text-gray-700"><?= e($placed_at) ?></span></div>
        </div>

        <div class="p-4 border rounded-lg">
          <div class="text-xs text-gray-500">Customer</div>
          <div class="mt-1 text-gray-800 font-medium"><?= e($order['user_email'] ?? '—') ?></div>
          <div class="text-sm text-gray-500 mt-1">Payment: <span class="font-medium"><?= e(ucfirst($order['payment_method'] ?? '—')) ?></span></div>
        </div>

        <div class="p-4 border rounded-lg">
          <div class="text-xs text-gray-500">Total</div>
          <div class="mt-1 text-2xl font-extrabold">₹<?= number_format((float)$order['total_amount'] ?? (float)$order['total'] ?? 0, 2) ?></div>
          <div class="text-sm text-gray-500 mt-1">Est. delivery by <span class="font-medium"><?= e($est_delivery) ?></span></div>
        </div>
      </div>

      <!-- Items -->
      <div class="mt-6">
        <h2 class="text-lg font-semibold">Order summary</h2>

        <div class="mt-3 bg-gray-50 rounded-lg border overflow-hidden">
          <?php if ($items_res->num_rows > 0): ?>
            <ul class="divide-y">
              <?php while ($it = $items_res->fetch_assoc()): ?>
                <li class="flex items-center justify-between p-4">
                  <div class="min-w-0">
                    <div class="font-medium text-gray-800"><?= e($it['product_name']) ?></div>
                    <div class="text-sm text-gray-500">Qty: <?= (int)$it['quantity'] ?><?= !empty($it['size_value']) ? " • Size: " . ((int)$it['size_value']) : "" ?></div>
                  </div>
                  <div class="text-right">
                    <div class="font-medium">₹<?= number_format((float)$it['price'], 2) ?></div>
                    <?php if(isset($it['total'])): ?>
                      <div class="text-sm text-gray-500">Item total: ₹<?= number_format((float)$it['total'], 2) ?></div>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endwhile; ?>
            </ul>
          <?php else: ?>
            <div class="p-4 text-sm text-gray-600">No items found for this order.</div>
          <?php endif; ?>
        </div>

        <!-- Price breakdown -->
        <div class="mt-4 bg-white rounded-lg p-4 border">
          <div class="flex justify-between text-sm text-gray-600"><span>Subtotal</span><span>₹<?= number_format(((float)$order['total_amount'] - ((float)$order['shipping_amount'] ?? 0) + ((float)$order['discount_amount'] ?? 0)), 2) ?></span></div>
          <div class="flex justify-between text-sm text-gray-600"><span>Discount</span><span>-₹<?= number_format((float)($order['discount_amount'] ?? 0), 2) ?></span></div>
          <div class="flex justify-between text-sm text-gray-600"><span>Tax</span><span>₹<?= number_format((float)($order['tax_amount'] ?? 0), 2) ?></span></div>
          <div class="flex justify-between text-sm text-gray-600"><span>Shipping</span><span>₹<?= number_format((float)($order['shipping_amount'] ?? 0), 2) ?></span></div>
          <div class="mt-3 border-t pt-3 flex justify-between items-center">
            <div class="text-sm text-gray-600">Total paid</div>
            <div class="text-xl font-extrabold">₹<?= number_format((float)$order['total_amount'] ?? 0, 2) ?></div>
          </div>
        </div>
      </div>

      <!-- CTAs -->
      <div class="mt-6 flex flex-col sm:flex-row gap-3">
        <a href="<?= BASE_URL ?>views/products.php" class="flex-1 inline-flex justify-center items-center gap-2 px-4 py-3 rounded-lg bg-black text-white hover:bg-gray-900 transition">Continue shopping</a>
        <a href="<?= BASE_URL ?>views/orders.php" class="flex-1 inline-flex justify-center items-center gap-2 px-4 py-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition">View my orders</a>
        <button id="printBtn" class="px-4 py-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition">Print receipt</button>
      </div>

      <div class="mt-4 text-xs text-gray-500">
        Need help? Contact support at <a href="tel:+911800123456" class="underline">+91 1800 123 456</a> or email <a href="mailto:support@elitefootwear.example" class="underline">support@elitefootwear.example</a>
      </div>
    </div>
  </div>
</main>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>

<script>
  // copy order number
  document.getElementById('copyOrderBtn')?.addEventListener('click', function(){
    const txt = "<?= e($order['order_number']) ?>";
    navigator.clipboard?.writeText(txt).then(() => {
      this.textContent = 'Copied';
      setTimeout(()=> this.textContent = 'Copy', 1500);
    }).catch(()=> alert('Copy not supported'));
  });

  // print
  document.getElementById('printBtn')?.addEventListener('click', function(){
    window.print();
  });
</script>

</body>
</html>
