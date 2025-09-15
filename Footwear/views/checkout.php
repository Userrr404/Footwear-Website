<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
?>

<?php
// ---------------- HELPER FUNCTIONS ---------------- //
function getProductTaxRate($connection, $product_id) {
    $sql = "
        SELECT t.tax_rate
        FROM products p
        LEFT JOIN tax_rules t
          ON (
          (t.brand_id = p.brand_id)
           OR (t.category_id = p.category_id)
          )
        WHERE p.product_id = ?
          AND t.status = 'active'
          AND t.effective_from <= CURDATE()
          AND (t.effective_to IS NULL OR t.effective_to >= CURDATE())
        ORDER BY t.priority DESC
        LIMIT 1
    ";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row['tax_rate'];
    }
    return 0; // default no tax
}

function getProductDiscount($connection, $product_id, $price) {
    $sql = "
        SELECT d.discount_type, d.value
        FROM products p
        LEFT JOIN discount d
          ON (
              (d.applicable_to = 'product' AND d.applicable_id = p.product_id)
           OR (d.applicable_to = 'brand' AND d.applicable_id = p.brand_id)
           OR (d.applicable_to = 'category' AND d.applicable_id = p.category_id)
          )
        WHERE p.product_id = ?
          AND d.status = 'active'
          AND d.valid_from <= CURDATE()
          AND d.valid_to >= CURDATE()
        ORDER BY d.priority DESC
        LIMIT 1
    ";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['discount_type'] == 'percentage') {
            return $price * ($row['value'] / 100);
        } else {
            return $row['value'];
        }
    }
    return 0;
}

function getOrderDiscount($connection, $subtotal) {
    $sql = "
        SELECT discount_type, value
        FROM discount
        WHERE applicable_to = 'order'
          AND status = 'active'
          AND valid_from <= CURDATE()
          AND valid_to >= CURDATE()
          AND min_order_amount <= ?
        ORDER BY priority DESC
        LIMIT 1
    ";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("d", $subtotal);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['discount_type'] == 'percentage') {
            return $subtotal * ($row['value'] / 100);
        } else {
            return $row['value'];
        }
    }
    return 0;
}

function getShippingCharge($connection, $subtotal, $region = null){
  
  $sql = "
        SELECT charge
        FROM shipping_rules
        WHERE status = 'active'
          AND effective_from <= CURDATE()
          AND (effective_to IS NULL OR effective_to >= CURDATE())
          AND min_order_amount <= ?
          AND (max_order_amount IS NULL OR max_order_amount >= ?)
          " . ($region ? " AND (region IS NULL OR region = ?)" : "") . "
        ORDER BY priority DESC
        LIMIT 1
    ";

    if ($region) {
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("dds", $subtotal, $subtotal, $region);
    } else {
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("dd", $subtotal, $subtotal);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return (float)$row['charge'];
    }

    return 50.0; // default fallback
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Checkout | Elite Footwear</title>

  <!-- Tailwind CDN (good for prototyping / internal use). Swap to compiled Tailwind for production. -->
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    /* small extra: limit image flicker, smooth transitions */
    .image-cover { background-size: cover; background-position: center; }
    .safe-scroll { -webkit-overflow-scrolling: touch; }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

<?php require_once INCLUDES_PATH . 'header.php';

if (!isset($_SESSION['user_id'])) {
    die("<div class='max-w-xl mx-auto mt-20 p-6 bg-white rounded-xl shadow text-center'>Please <a class='text-blue-600 underline' href='" . BASE_URL . "views/login.php'>login</a> to continue to checkout.</div>");
}

$user_id = $_SESSION['user_id'];

$single_checkout = isset($_POST['single_checkout']) ? true : false;

if ($single_checkout && !empty($_POST['cart_id'])) {
    // Fetch only that cart item
    $cart_sql = "SELECT c.cart_id, c.quantity, p.product_id, p.product_name, p.selling_price, s.size_value, pi.image_url
                 FROM cart c
                 JOIN products p ON c.product_id = p.product_id
                 JOIN sizes s ON c.size_id = s.size_id
                 LEFT JOIN product_images pi ON c.product_id = pi.product_id AND pi.is_default = 1
                 WHERE c.user_id = ? AND c.cart_id = ?";
    $stmt = $connection->prepare($cart_sql);
    $stmt->bind_param("ii", $user_id, $_POST['cart_id']);
    $stmt->execute();
    $cart_result = $stmt->get_result();
} else {
    // Fetch all cart items
    $sql = "SELECT c.cart_id, c.quantity, p.product_id, p.product_name, p.selling_price, s.size_value, pi.image_url
            FROM cart c
            JOIN products p ON c.product_id = p.product_id
            JOIN sizes s ON c.size_id = s.size_id
            LEFT JOIN product_images pi ON c.product_id = pi.product_id AND pi.is_default = 1
            WHERE c.user_id = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();
}

$cart_items = [];
$subtotal = 0;
$discount_total = 0;
$tax_total = 0;

// remove earlier $product_id assignment — we'll use per-item product_id
while ($item = $cart_result->fetch_assoc()) {
    $base_price = $item['selling_price'] * $item['quantity'];
    $prod_id = (int)$item['product_id'];

    // Per-item discount (pass the product id and the base_price)
    $discount = getProductDiscount($connection, $prod_id, $base_price);
    $price_after_discount = $base_price - $discount;

    // Per-item tax rate and tax amount on discounted price
    $tax_rate = getProductTaxRate($connection, $prod_id);
    $tax = round($price_after_discount * ($tax_rate / 100), 2);

    // Totals
    $subtotal += $base_price;
    $discount_total += $discount;
    $tax_total += $tax;

    // Push item with computed values
    $item['base_price'] = $base_price;
    $item['discount'] = $discount;
    $item['tax'] = $tax;
    $item['subtotal'] = $price_after_discount + $tax;
    $cart_items[] = $item;
}

// Shipping
// e.g., if you have a default address earlier, set $region = $default_address['state'] ?? null;
$shipping = getShippingCharge($connection, $subtotal, null);

// Order-level discount
$order_discount = getOrderDiscount($connection, $subtotal - $discount_total);

// Final total
$grand_total = round($subtotal - $discount_total + $tax_total + $shipping - $order_discount, 2);


// Fetch addresses
$addr_sql = "SELECT * FROM addresses WHERE user_id = ?";
$addr_stmt = $connection->prepare($addr_sql);
$addr_stmt->bind_param("i", $user_id);
$addr_stmt->execute();
$addresses = $addr_stmt->get_result();
?>

<?php
// ✅ Debug block - only runs if you pass ?debug=1 in URL
if (isset($_GET['debug']) && $_GET['debug'] == 1) {
    echo "<pre>";
    echo "SESSION USER:\n";
    print_r($_SESSION);

    echo "\nCART ITEMS (raw from DB):\n";
    $cart_result->data_seek(0); // reset pointer in case loop already ran
    while ($row = $cart_result->fetch_assoc()) {
        print_r($row);
    }

    echo "\nCART ITEMS (after processing):\n";
    print_r($cart_items);

    echo "\nTOTALS:\n";
    echo "Subtotal: $subtotal\n";
    echo "Discount total: $discount_total\n";
    echo "Tax total: $tax_total\n";
    echo "Shipping: $shipping\n";
    echo "Order Discount: $order_discount\n";
    echo "Grand Total: $grand_total\n";

    echo "</pre>";
    // exit; // uncomment if you want to stop page rendering after debug
}
?>


<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-10">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- LEFT SIDE: checkout process -->
    <section class="lg:col-span-2 space-y-6">

      <!-- Page header -->
      <div class="bg-white rounded-2xl shadow p-4 sm:p-6 flex items-center justify-between">
        <h1 class="text-xl sm:text-2xl font-semibold">Checkout</h1>
        <span class="hidden sm:block text-sm text-gray-500">Secure checkout • SSL encrypted</span>
      </div>

      <!-- Inline order summary (only mobile & tablet) -->
      <div class="bg-white rounded-2xl shadow p-4 sm:p-6 lg:hidden">
        <h2 class="text-lg font-medium mb-3">Order Summary</h2>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between"><span>Subtotal</span><span>₹<?= number_format($subtotal,2) ?></span></div>
          <div class="flex justify-between"><span>Discounts</span><span class="text-red-600">-₹<?= number_format($discount_total + $order_discount,2) ?></span></div>
          <div class="flex justify-between"><span>Tax</span><span class="text-green-600">+₹<?= number_format($tax_total,2) ?></span></div>
          <div class="flex justify-between"><span>Shipping</span><span class="text-green-600"><?= $shipping ? '₹'.number_format($shipping,2) : 'Free' ?></span></div>
          <div class="border-t pt-2 flex justify-between font-bold"><span>Total</span><span>₹<?= number_format($grand_total,2) ?></span></div>
        </div>
      </div>

      <!-- Cart items (scrollable on small screens) -->
      <div class="bg-white rounded-2xl shadow p-4 sm:p-6 overflow-x-auto safe-scroll">
        <table class="w-full min-w-[600px] text-sm">
          <thead class="bg-gray-100">
            <tr>
              <th class="p-3 text-left">Item</th>
              <th class="p-3">Product</th>
              <th class="p-3">Price</th>
              <th class="p-3">Qty</th>
              <th class="p-3">Discount</th>
              <th class="p-3">Tax</th>
              <th class="p-3">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cart_items as $item): ?>
              <tr class="border-t">
                <td class="p-3 w-24">
                  <img src="<?= UPLOADS_URL . $item['image_url'] ?>" class="w-14 h-14 sm:w-16 sm:h-16 object-cover rounded-lg border">
                </td>
                <td class="p-3"><?= htmlspecialchars($item['product_name']) ?></td>
                <td class="p-3">₹<?= number_format($item['selling_price'], 2) ?></td>
                <td class="p-3"><?= $item['quantity'] ?></td>
                <td class="p-3">
                  <?php if ($item['discount'] > 0 || $order_discount > 0): ?>
                    <?php $item_discount = $item['discount'] + ($order_discount * ($item['base_price'] / $subtotal)); ?>
                    <span class="text-red-600">-₹<?= number_format($item_discount, 2) ?></span>
                  <?php else: ?> —
                  <?php endif; ?>
                </td>
                <td class="p-3 text-green-600"><?= $item['tax'] > 0 ? '-₹'.number_format($item['tax'], 2) : '—' ?></td>
                <td class="p-3">₹<?= number_format($item['subtotal'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Address + Payment form -->
      <form method="post" action="<?= BASE_URL ?>php/process_order.php" class="bg-white rounded-2xl shadow p-6 space-y-6">

        <!-- Hidden: include cart items (ids & quantities) so process_order knows what to create -->
        <?php if (!empty($cart_items)): ?>
          <?php foreach ($cart_items as $ci): ?>
            <input type="hidden" name="cart_id[]" value="<?= (int)$ci['cart_id'] ?>">
            <input type="hidden" name="quantity[]" value="<?= (int)$ci['quantity'] ?>">
          <?php endforeach; ?>
        <?php endif; ?>

        <h3 class="text-lg font-medium">📍 Shipping Address</h3>

        <?php if ($addresses->num_rows > 0): ?>
          <div class="grid gap-3 sm:grid-cols-2">
            <?php while ($addr = $addresses->fetch_assoc()): ?>
              <label class="p-3 border rounded-lg hover:shadow transition cursor-pointer">
                <input type="radio" name="shipping_address_id" value="<?= (int)$addr['address_id'] ?>" class="sr-only">
                <div class="flex items-start gap-3">
                  <div class="flex-1">
                    <div class="font-semibold"><?= htmlspecialchars($addr['full_name']) ?></div>
                    <div class="text-sm text-gray-600">
                      <?= htmlspecialchars($addr['address_line1']) ?>, <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state']) ?> - <?= htmlspecialchars($addr['pincode']) ?>
                    </div>
                  </div>
                  <div class="text-sm text-gray-500"><?= htmlspecialchars($addr['phone_number']) ?></div>
                </div>
              </label>
            <?php endwhile; ?>
          </div>
        <?php else: ?>
          <div class="text-sm text-gray-600">No saved addresses. Fill the form below to add one.</div>
        <?php endif; ?>

        <div>
          <h4 class="text-sm font-medium mt-2">➕ Add / Update Address</h4>
          <div class="grid gap-3 sm:grid-cols-2 mt-3">
            <input name="full_name" type="text" placeholder="Full name" class="py-2 px-3 border rounded-lg w-full" />
            <input name="phone_number" type="text" placeholder="Phone number" class="py-2 px-3 border rounded-lg w-full" />

            <input name="address_line1" type="text" placeholder="Address line" class="py-2 px-3 border rounded-lg w-full sm:col-span-2" />
            <input name="city" type="text" placeholder="City" class="py-2 px-3 border rounded-lg" />
            <input name="state" type="text" placeholder="State" class="py-2 px-3 border rounded-lg" />
            <input name="pincode" type="text" placeholder="PIN code" class="py-2 px-3 border rounded-lg" />
            <input name="country" type="text" placeholder="Country" class="py-2 px-3 border rounded-lg" />
            <select name="address_type" class="py-2 px-3 border rounded-lg">
              <option value="shipping">Shipping</option>
              <option value="billing">Billing</option>
            </select>
          </div>
        </div>

        <div>
          <h3 class="text-lg font-medium">💳 Payment Method</h3>

          <div class="mt-3 space-y-3">
            <label class="flex items-center gap-3 p-3 border rounded-lg hover:shadow transition cursor-pointer">
              <input type="radio" name="payment_method" value="cash" class="form-radio h-4 w-4" required>
              <div>
                <div class="font-semibold">Cash on Delivery</div>
                <div class="text-sm text-gray-500">Pay when you receive the order</div>
              </div>
            </label>

            <label class="flex items-center gap-3 p-3 border rounded-lg hover:shadow transition cursor-pointer">
              <input type="radio" name="payment_method" value="UPI" class="form-radio h-4 w-4">
              <div>
                <div class="font-semibold">UPI</div>
                <div class="text-sm text-gray-500">Pay instantly with UPI</div>
              </div>
            </label>

            <label class="flex items-center gap-3 p-3 border rounded-lg hover:shadow transition cursor-pointer">
              <input type="radio" name="payment_method" value="credit_card" class="form-radio h-4 w-4">
              <div>
                <div class="font-semibold">Credit card</div>
                <div class="text-sm text-gray-500">Visa, Mastercredit_card, Rupay</div>
              </div>
            </label>

            <label class="flex items-center gap-3 p-3 border rounded-lg hover:shadow transition cursor-pointer">
              <input type="radio" name="payment_method" value="debit_card" class="form-radio h-4 w-4">
              <div>
                <div class="font-semibold">Debit card</div>
                <div class="text-sm text-gray-500">Visa, Mastercredit_card, Rupay</div>
              </div>
            </label>
          </div>

          <!-- Dynamic payment details -->
          <div id="payment-details" class="mt-4 space-y-3">
            <!-- UPI input -->
            <div id="UPI-box" class="hidden">
              <label class="block text-sm font-medium text-gray-700">Enter UPI ID</label>
              <input name="UPI_id" type="text" placeholder="example@bank" class="mt-1 block w-full py-2 px-3 border rounded-lg" />
            </div>

            <!-- credit_card fields (dummy; in prod use tokenized gateway) -->
            <div id="credit_card-box" class="hidden space-y-2">
              <label class="block text-sm font-medium text-gray-700">credit card details</label>
              <input name="credit_card_number" type="text" placeholder="credit_card number" class="py-2 px-3 border rounded-lg w-full" />
              <div class="grid grid-cols-2 gap-3">
                <input name="credit_card_expiry" type="text" placeholder="MM/YY" class="py-2 px-3 border rounded-lg" />
                <input name="credit_card_cvc" type="text" placeholder="CVC" class="py-2 px-3 border rounded-lg" />
              </div>
              <div class="text-xs text-gray-500">We don't store credit card details — payments are processed via your chosen gateway.</div>
            </div>

            <div id="debit_card-box" class="hidden space-y-2">
              <label class="block text-sm font-medium text-gray-700">Debit card details</label>
              <input name="debit_card_number" type="text" placeholder="debit_card number" class="py-2 px-3 border rounded-lg w-full" />
              <div class="grid grid-cols-2 gap-3">
                <input name="debit_card_expiry" type="text" placeholder="MM/YY" class="py-2 px-3 border rounded-lg" />
                <input name="debit_card_cvc" type="text" placeholder="CVC" class="py-2 px-3 border rounded-lg" />
              </div>
              <div class="text-xs text-gray-500">We don't store debit card details — payments are processed via your chosen gateway.</div>
            </div>
          </div>
        </div>

        <!-- Totals & Place Order -->
        <div class="pt-4 border-t flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
          <div class="text-sm text-gray-700">
            <div>Order total <span class="font-semibold">₹<?= number_format($grand_total, 2) ?></span></div>
            <div class="text-xs text-gray-500">Inclusive of taxes & shipping</div>
          </div>

          <input type="hidden" name="subtotal_amount" value="<?= htmlspecialchars($subtotal) ?>">
          <input type="hidden" name="discount_amount" value="<?= htmlspecialchars($discount_total + $order_discount) ?>">
          <input type="hidden" name="tax_amount" value="<?= htmlspecialchars($tax_total) ?>">
          <input type="hidden" name="shipping_amount" value="<?= htmlspecialchars($shipping) ?>">
          <input type="hidden" name="total_amount" value="<?= htmlspecialchars($grand_total) ?>">

          <button id="placeOrderBtn" type="submit" class="w-full sm:w-auto px-6 py-3 bg-black text-white rounded-lg font-medium hover:bg-yellow-400 hover:text-black transition">
            ✅ Place Order
          </button>
        </div>

      </form>

    </section>

    <!-- RIGHT SIDE: sticky order summary (desktop only) -->
    <aside class="hidden lg:block">
      <div class="sticky top-24 space-y-4">
        <div class="bg-white rounded-2xl p-5 shadow">
          <h4 class="text-lg font-semibold mb-3">Order Summary</h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span>Subtotal</span><span>₹<?= number_format($subtotal,2) ?></span></div>
            <div class="flex justify-between"><span>Discounts</span><span class="text-red-600">-₹<?= number_format($discount_total + $order_discount,2) ?></span></div>
            <div class="flex justify-between"><span>Tax</span><span class="text-green-600">+₹<?= number_format($tax_total,2) ?></span></div>
            <div class="flex justify-between"><span>Shipping</span><span class="text-green-600"><?= $shipping ? '₹'.number_format($shipping,2) : 'Free' ?></span></div>
            <div class="border-t pt-3 flex justify-between font-bold text-lg"><span>Total</span><span>₹<?= number_format($grand_total,2) ?></span></div>
            <div class="mt-5 hover:shadow-lg transition">
            <a href="<?= BASE_URL ?>views/products.php" class="block text-center w-full py-2 border rounded-lg text-sm text-gray-700 border-gray-300 
            hover:bg-gray-100 hover:text-gray-900 hover:border-gray-400 
            transition duration-200 ease-in-out">Continue shopping</a>
          </div>
          </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow text-sm">
          <h5 class="font-medium mb-2">Need help?</h5>
          <p class="text-gray-600">Contact our 24/7 support or call <span class="font-semibold">+91 1800 123 456</span></p>
        </div>
      </div>
    </aside>
  </div>
</main>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>

<!-- Minimal JS for payment method toggle (progressive enhancement) -->
<script>
  (function() {
    const radios = document.querySelectorAll('input[name="payment_method"]');
    const UPIBox = document.getElementById('UPI-box');
    const credit_cardBox = document.getElementById('credit_card-box');
    const debit_cardBox = document.getElementById('debit_card-box');

    function togglePaymentBoxes() {
      const val = document.querySelector('input[name="payment_method"]:checked')?.value;
      UPIBox.classList.add('hidden');
      credit_cardBox.classList.add('hidden');
      debit_cardBox.classList.add('hidden');

      if (val === 'UPI') UPIBox.classList.remove('hidden');
      if (val === 'credit_card') credit_cardBox.classList.remove('hidden');
      if (val === 'debit_card') debit_cardBox.classList.remove('hidden');
    }

    radios.forEach(r => r.addEventListener('change', togglePaymentBoxes));
    // init on page load (if browser pre-selects)
    togglePaymentBoxes();
  })();
</script>

</body>
</html>
