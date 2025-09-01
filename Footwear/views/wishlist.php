<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Wishlist | Elite Footwear</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

  <?php require_once INCLUDES_PATH . 'header.php'; 

    if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $connection->prepare("
    SELECT 
        p.product_id, 
        p.product_name, 
        p.selling_price, 
        pi.image_url 
    FROM wishlist w
    JOIN products p ON w.product_id = p.product_id
    LEFT JOIN product_images pi ON pi.product_id = p.product_id AND pi.is_default = 1
    WHERE w.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();?>
  
  ?>

  <main class="flex-grow">
    <div class="max-w-7xl mx-auto px-6 py-10">
      
      <!-- Page Title -->
      <h2 class="text-2xl md:text-3xl font-bold text-center mb-10">❤️ My Wishlist</h2>

      <?php if ($result->num_rows > 0): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8 m-5">
          <?php while ($row = $result->fetch_assoc()): ?>
            <!-- Product Card -->
            <div class="bg-white rounded-2xl shadow-md hover:shadow-xl transition overflow-hidden flex flex-col">
              <div class="w-full h-52 sm:h-56 md:h-60 bg-gray-100">
                <img 
                  src="<?= UPLOADS_URL . ($row['image_url'] ?? 'default.png') ?>" 
                  alt="<?= htmlspecialchars($row['product_name']) ?>"
                  class="w-full h-full object-cover"
                  onerror="this.src='<?= UPLOADS_URL ?>default.png';"
                >
              </div>
              <div class="flex-1 p-5 flex flex-col justify-between gap-4">
                <div>
                  <h5 class="font-semibold text-lg line-clamp-1">
                    <?= htmlspecialchars($row['product_name']) ?>
                  </h5>
                  <p class="text-gray-600 text-base mt-1">
                    ₹<?= number_format($row['selling_price'], 2) ?>
                  </p>
                </div>
                <div class="flex items-center justify-between mt-auto gap-3">
                  <a href="product_details.php?id=<?= $row['product_id'] ?>" 
                     class="flex-1 text-center bg-red-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-red-700 transition">
                     View
                  </a>
                  <form method="POST" action="../php/remove_from_wishlist.php" class="flex-1">
                    <input type="hidden" name="product_id" value="<?= $row['product_id'] ?>">
                    <button type="submit" 
                            class="w-full bg-gray-200 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg hover:bg-red-600 hover:text-white transition">
                      Remove
                    </button>
                  </form>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <div class="bg-white rounded-2xl shadow p-10 text-center">
          <p class="text-gray-600 text-lg">Your wishlist is empty.</p>
          <a href="<?= BASE_URL ?>views/index.php" 
             class="mt-6 inline-block bg-red-600 text-white px-6 py-3 rounded-lg text-base font-medium hover:bg-red-700 transition">
            Browse Products
          </a>
        </div>
      <?php endif; ?>

    </div>
  </main>

  <?php require_once INCLUDES_PATH . 'footer.php'; ?>

</body>

</html>
