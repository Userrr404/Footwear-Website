<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';

// Detect device (basic method: user agent, you can also use JS for client-side)
function getDeviceType() {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
    if (strpos($ua, 'mobile') !== false) return 'mobile';
    if (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) return 'tablet';
    return 'desktop';
}
$deviceType = getDeviceType();

// Fetch active banners
$banner_sql = "SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order ASC";
$banner_result = $connection->query($banner_sql);

$banners = [];
if ($banner_result && $banner_result->num_rows > 0) {
    while ($row = $banner_result->fetch_assoc()) {
        $image = $row['image_' . $deviceType]; // pick based on device
        $banners[] = [
            'title' => $row['title'],
            'description' => $row['description'],
            'image' => BASE_URL . "uploads/banners/" . $image
        ];
    }
}

// Fetch latest products
$sql = "SELECT p.*, c.category_name 
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        WHERE p.is_active = 1
        ORDER BY RAND() LIMIT 12";

$result = $connection->query($sql);
if (!$result) {
    die("Database query failed: " . $connection->error);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Elite Footwear</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" integrity="sha512-DxV+EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/index.css">
</head>
<body class="bg-white min-h-screen flex flex-col">

  <?php require_once INCLUDES_PATH . 'header.php'; ?>

    <main class="flex-grow">
      <!-- Hero Section -->
  <div class="hero">
    <img id="heroImage" src="<?= $banners[0]['image'] ?? '../assets/images/default-banner.jpg' ?>" alt="Hero Banner">
    <div class="hero-content">
      <h1 id="heroTitle"><?= htmlspecialchars($banners[0]['title']) ?></h1>
      <p id="heroDescription"><?= htmlspecialchars($banners[0]['description']) ?></p>
    </div>
  </div>

  <!-- Browse Categories Section -->
  <section class="categories bg-gray-50 py-10">
    <div class="max-w-6xl mx-auto px-4">
      <h2 class="text-xl font-semibold mb-6">Browse Categories</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">

        <?php
          // Fetch categories
          $cat_sql = "SELECT * FROM categories ORDER BY category_id ASC";
          $cat_result = $connection->query($cat_sql);

          // Map icons for categories (Font Awesome)
          $icons = [
            'Sneakers'       => 'fa-shoe-prints',
            'Running Shoes'  => 'fa-person-running',
            'Formal Shoes'   => 'fa-briefcase',
            'Sandals'        => 'fa-shoe-prints',
            'Causal Shoes'   => 'fa-walking'
          ];

          if ($cat_result && $cat_result->num_rows > 0) {
            while ($cat = $cat_result->fetch_assoc()) {
              $icon = $icons[$cat['category_name']] ?? 'fa-tag'; // fallback icon
                echo '
                <div class="category-card bg-white rounded-xl shadow p-4 flex flex-col items-center hover:shadow-lg transition">
                  <i class="fas '.$icon.' text-red-500 text-2xl"></i>
                  <p class="mt-2 text-sm font-medium">'.htmlspecialchars($cat['category_name']).'</p>
                </div>';
            }
          }
        ?>
      </div>
    </div>
  </section>

<!-- Products Section -->
<section class="products bg-gray-50 py-16">
  <div class="max-w-8xl mx-auto px-4">
    <div class="flex justify-between items-center mb-8">
      <h2 class="text-2xl md:text-3xl font-bold tracking-tight">Latest Arrivals</h2>
      <a href="<?= BASE_URL ?>views/products.php" class="text-sm md:text-base font-medium text-red-500 hover:text-red-600 transition">View All →</a>
    </div>

    <div class="product-grid">
      <?php if ($result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
          <a href="<?= BASE_URL ?>views/product_details.php?id=<?= $row['product_id'] ?>" class="product-card group">
          
            <div class="product-image">
              <img src="<?= BASE_URL ?>uploads/products/<?= $row['product_id'] ?>.jpg" alt="<?= htmlspecialchars($row['product_name']) ?>" />
            </div>
            <div class="product-info">
              <h3 class="product-name"><?= htmlspecialchars($row['product_name']) ?></h3>
              <p class="category"><?= htmlspecialchars($row['category_name']) ?></p>
              <p class="price"><?= CURRENCY . number_format($row['selling_price'], 2) ?></p>
            </div>
          
          </a>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="text-center text-gray-500">No products found.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
</main>


  <?php require_once INCLUDES_PATH . 'footer.php'; ?>

<!-- Pass banners dynamically to JS -->
<script>
  const banners = <?= json_encode($banners) ?>;
</script>
<script src="<?= BASE_URL ?>assets/js/index.js"></script>

</body>
</html>
