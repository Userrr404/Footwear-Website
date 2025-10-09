<?php
// order_invoice.php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';

session_start();

// Ensure authentication
if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
  header('Location: orders.php');
  exit;
}

$user_id  = (int)$_SESSION['user_id'];
$order_id = (int)$_GET['order_id'];

// ---------- Fetch Order + Address + Shipment ----------
$sql = "
  SELECT o.*, 
         a.full_name, a.address_line1, a.address_line2, a.city, a.state, a.pincode, a.phone_number,
         s.courier_name, s.tracking_number, s.shipped_at, s.delivery_status
  FROM orders o
  LEFT JOIN addresses a ON o.address_id = a.address_id
  LEFT JOIN shipments s ON o.order_id = s.order_id
  WHERE o.order_id = ? AND o.user_id = ?
  LIMIT 1
";
$stmt = $connection->prepare($sql);
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
  echo "<div class='p-8 text-center text-red-600'>Invalid order or access denied.</div>";
  exit;
}

// ---------- Fetch Order Items ----------
$itemSql = "
  SELECT oi.*, p.product_name, s.size_value, pi.image_url
  FROM order_items oi
  LEFT JOIN products p ON oi.product_id = p.product_id
  LEFT JOIN sizes s ON oi.size_id = s.size_id
  LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_default = 1
  WHERE oi.order_id = ?
";
$itStmt = $connection->prepare($itemSql);
$itStmt->bind_param('i', $order_id);
$itStmt->execute();
$items = $itStmt->get_result()->fetch_all(MYSQLI_ASSOC);

function money($v) { return '₹' . number_format((float)$v, 2); }

$subtotal = $order['subtotal_amount'] ?? 0;
$discount = $order['discount_amount'] ?? 0;
$tax      = $order['tax_amount'] ?? 0;
$shipping = $order['shipping_amount'] ?? 0;
$total    = $order['total_amount'] ?? ($subtotal - $discount + $tax + $shipping);

$invoiceNo = 'INV-' . str_pad($order['order_id'], 6, '0', STR_PAD_LEFT);
$date = date('d M Y, h:i A', strtotime($order['placed_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice #<?= htmlspecialchars($invoiceNo) ?> | Elite Footwear</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<style>
  @media print {
    .no-print { display: none !important; }
    body { background: white !important; }
  }
</style>
</head>
<body class="bg-gray-50 text-gray-800">
  <div class="max-w-4xl mx-auto my-10 bg-white shadow-xl rounded-2xl p-8">
    <!-- Header -->
    <div class="flex justify-between items-start border-b pb-6 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-indigo-700">Elite Footwear</h1>
        <p class="text-sm text-gray-500">Luxury Shoes • Crafted with Precision</p>
      </div>
      <div class="text-right">
        <h2 class="text-lg font-semibold text-gray-700">Invoice</h2>
        <p class="text-sm text-gray-500"><?= htmlspecialchars($invoiceNo) ?></p>
        <p class="text-sm text-gray-500">Date: <?= htmlspecialchars($date) ?></p>
      </div>
    </div>

    <!-- Addresses -->
    <div class="grid md:grid-cols-2 gap-8 mb-6">
      <div>
        <h3 class="font-semibold mb-2 text-gray-700">Billed To:</h3>
        <p class="text-sm text-gray-800"><?= htmlspecialchars($order['full_name']) ?></p>
        <p class="text-sm text-gray-600"><?= htmlspecialchars($order['address_line1'] . ' ' . $order['address_line2']) ?></p>
        <p class="text-sm text-gray-600"><?= htmlspecialchars($order['city'] . ', ' . $order['state'] . ' - ' . $order['pincode']) ?></p>
        <p class="text-sm text-gray-600">Phone: <?= htmlspecialchars($order['phone_number']) ?></p>
      </div>
      <div class="md:text-right">
        <h3 class="font-semibold mb-2 text-gray-700">Order Details:</h3>
        <p class="text-sm text-gray-800">Order ID: <?= htmlspecialchars($order['order_number'] ?? $order['order_id']) ?></p>
        <p class="text-sm text-gray-800">Payment: <span class="font-medium text-indigo-700"><?= ucfirst($order['payment_status']) ?></span></p>
        <p class="text-sm text-gray-800">Method: <?= htmlspecialchars($order['payment_method'] ?? '—') ?></p>
        <?php if (!empty($order['courier_name'])): ?>
          <p class="text-sm text-gray-800">Courier: <?= htmlspecialchars($order['courier_name']) ?></p>
        <?php endif; ?>
        <?php if (!empty($order['tracking_number'])): ?>
          <p class="text-sm text-gray-800">Tracking #: <?= htmlspecialchars($order['tracking_number']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Items Table -->
    <div class="overflow-x-auto mb-6">
      <table class="min-w-full border border-gray-200">
        <thead class="bg-gray-100">
          <tr>
            <th class="text-left px-4 py-2 text-sm font-semibold text-gray-700">#</th>
            <th class="text-left px-4 py-2 text-sm font-semibold text-gray-700">Product</th>
            <th class="text-center px-4 py-2 text-sm font-semibold text-gray-700">Size</th>
            <th class="text-center px-4 py-2 text-sm font-semibold text-gray-700">Qty</th>
            <th class="text-right px-4 py-2 text-sm font-semibold text-gray-700">Price</th>
            <th class="text-right px-4 py-2 text-sm font-semibold text-gray-700">Total</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($items as $i => $it): 
            $qty = (int)$it['quantity'];
            $price = (float)$it['price'];
          ?>
          <tr>
            <td class="px-4 py-2 text-sm text-gray-600"><?= $i+1 ?></td>
            <td class="px-4 py-2 text-sm text-gray-800"><?= htmlspecialchars($it['product_name']) ?></td>
            <td class="px-4 py-2 text-center text-sm text-gray-600"><?= htmlspecialchars($it['size_value'] ?? '-') ?></td>
            <td class="px-4 py-2 text-center text-sm text-gray-600"><?= $qty ?></td>
            <td class="px-4 py-2 text-right text-sm text-gray-600"><?= money($price) ?></td>
            <td class="px-4 py-2 text-right text-sm text-gray-800 font-medium"><?= money($qty * $price) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Totals -->
    <div class="flex justify-end">
      <div class="w-full md:w-72 space-y-2 text-sm">
        <div class="flex justify-between"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
        <div class="flex justify-between"><span>Discount</span><span>- <?= money($discount) ?></span></div>
        <div class="flex justify-between"><span>Tax</span><span><?= money($tax) ?></span></div>
        <div class="flex justify-between"><span>Shipping</span><span><?= money($shipping) ?></span></div>
        <hr>
        <div class="flex justify-between font-semibold text-lg text-gray-800"><span>Grand Total</span><span><?= money($total) ?></span></div>
      </div>
    </div>

    <!-- Footer -->
    <div class="border-t mt-8 pt-4 text-center text-sm text-gray-500">
      <p>Thank you for shopping with <span class="font-semibold text-indigo-600">Elite Footwear</span>.</p>
      <p>Your satisfaction is our priority — reach us anytime at <a href="mailto:support@elitefootwear.com" class="text-indigo-600 hover:underline">support@elitefootwear.com</a>.</p>
    </div>

    <!-- Actions -->
    <div class="mt-6 flex justify-end gap-4 no-print">
      <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"><i class="fa fa-print mr-1"></i>Print Invoice</button>
      <a href="orders.php" class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50">Back to Orders</a>
    </div>
  </div>
</body>
</html>
