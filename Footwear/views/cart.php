<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Cart | Elite Footwear</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

<?php require_once INCLUDES_PATH . 'header.php'; ?>

<main class="flex-grow container mx-auto px-4 py-8">
  <?php if (!isset($_SESSION['user_id'])): ?>
    <div class="text-center py-10 bg-white rounded-xl shadow">
      <p class="text-lg">
        Please 
        <a class="text-blue-600 hover:underline" href="<?= BASE_URL ?>views/login.php">login</a> 
        to view your cart.
      </p>
    </div>
  <?php else: ?>
    <?php
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT c.cart_id, c.quantity, p.product_name, p.selling_price, pi.image_url, s.size_value
            FROM cart c
            JOIN products p ON c.product_id = p.product_id
            JOIN sizes s ON c.size_id = s.size_id
            LEFT JOIN product_images pi ON c.product_id = pi.product_id AND pi.is_default = 1
            WHERE c.user_id = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $cart_items = [];
    $grand_total = 0;
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $grand_total += $row['selling_price'] * $row['quantity'];
    }
    ?>

    <h1 class="text-3xl font-bold text-center mb-8">🛒 Your Shopping Cart</h1>

    <?php if (empty($cart_items)): ?>
      <div class="text-center bg-white p-10 rounded-xl shadow">
        <p class="text-lg text-gray-600">
          Your cart is empty. 
          <a class="text-blue-600 font-medium hover:underline" href="<?= BASE_URL ?>views/products.php">Start shopping</a>!
        </p>
      </div>
    <?php else: ?>
      <div class="grid lg:grid-cols-3 gap-6">
        
        <!-- Cart Items -->
        <div class="lg:col-span-2 space-y-6">
          <?php foreach ($cart_items as $item): ?>
            <div class="flex flex-col sm:flex-row items-start sm:items-center bg-white p-4 rounded-xl shadow hover:shadow-md transition">
              <img src="<?= UPLOADS_URL . $item['image_url'] ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="w-28 h-28 object-cover rounded-lg border mb-4 sm:mb-0 sm:mr-6">
              
              <div class="flex-1">
                <h3 class="text-lg font-semibold"><?= htmlspecialchars($item['product_name']) ?></h3>
                <p class="text-sm text-gray-500">Size: <?= $item['size_value'] ?></p>
                <p class="text-base font-bold mt-1">₹<?= number_format($item['selling_price'], 2) ?> 
                  <span class="text-gray-500 text-sm">× <?= $item['quantity'] ?></span>
                </p>
                <p class="text-base font-semibold mt-1">Subtotal: ₹<?= number_format($item['selling_price'] * $item['quantity'], 2) ?></p>

                <!-- Actions -->
                <div class="flex flex-wrap items-center gap-3 mt-4">
                  
                  <!-- Update Quantity -->
                  <form method="post" action="../php/update_cart.php" class="flex items-center gap-2">
                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                    <input type="number" name="quantity" min="1" value="<?= $item['quantity'] ?>"
                           class="w-16 px-2 py-1 border rounded-lg text-center">
                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-yellow-500 hover:text-black transition">Update</button>
                  </form>

                  <!-- Remove Product -->
                  <form method="post" action="../php/remove_from_cart.php">
                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                    <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Remove</button>
                  </form>

                  <!-- Checkout This Item -->
                  <form method="post" action="<?= BASE_URL ?>views/checkout.php">
                    <input type="hidden" name="single_checkout" value="1">
                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Checkout This Item</button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Summary -->
        <div class="bg-white p-6 rounded-xl shadow-lg sticky top-20 h-fit">
          <h2 class="text-xl font-bold mb-4">Order Summary</h2>
          <p class="flex justify-between text-lg font-medium">
            <span>Total</span>
            <span>₹<?= number_format($grand_total, 2) ?></span>
          </p>
          <a href="<?= BASE_URL ?>views/checkout.php" 
             class="mt-6 block w-full text-center px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition">
             Proceed to Checkout →
          </a>
        </div>

      </div>
    <?php endif; ?>
  <?php endif; ?>
</main>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
</body>
</html>
