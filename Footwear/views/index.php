<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once INCLUDES_PATH . 'header.php';

// Fetch latest products
$sql = "SELECT p.*, c.category_name 
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        WHERE p.is_active = 1
        ORDER BY RAND() LIMIT 20";

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
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/index.css">
</head>
<body>

  <!-- Hero Section -->
  <div class="hero">
    <img id="heroImage" src="../assets/images/hero_image1.jpeg" alt="Hero Banner">
    <div class="hero-content">
      <h1>Step Into Style</h1>
      <p>Explore the latest collection of premium footwear</p>
    </div>
  </div>

  <!-- Products Section -->
  <section class="products">
    <h2>Latest Arrivals</h2>
    <div class="product-grid">
      <?php if ($result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
          <div class="product-card">
            <img src="<?= BASE_URL ?>uploads/<?= $row['product_id'] ?>.jpg" alt="<?= htmlspecialchars($row['product_name']) ?>" />
            <div class="product-info">
              <h3><?= htmlspecialchars($row['product_name']) ?></h3>
              <p class="category"><?= htmlspecialchars($row['category_name']) ?></p>
              <p class="price"><?= CURRENCY . number_format($row['price'], 2) ?></p>
              <a href="<?= BASE_URL ?>views/product_details.php?id=<?= $row['product_id'] ?>" class="btn">View Details</a>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p>No products found.</p>
      <?php endif; ?>
    </div>
  </section>

  <?php require_once INCLUDES_PATH . 'footer.php'; ?>

  <!-- Hero Slider Script -->
  <script>
    const heroImage = document.getElementById('heroImage');
    const heroImages = [
      '../assets/images/hero_image1.jpeg',
      '../assets/images/hero_image2.jpg',
      '../assets/images/hero_image3.jpg'
    ];

    let currentIndex = 0;

    setInterval(() => {
      heroImage.style.opacity = 0;

      setTimeout(() => {
        currentIndex = (currentIndex + 1) % heroImages.length;
        heroImage.src = heroImages[currentIndex];
        heroImage.style.opacity = 1;
      }, 1000);
    }, 4000);
  </script>
</body>
</html>
