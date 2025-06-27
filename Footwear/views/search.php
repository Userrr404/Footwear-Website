<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once INCLUDES_PATH . 'header.php';

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$results = [];

if (!empty($query)) {
    if (is_numeric($query)) {
        $min_price = (float) $query;
        $max_price = $min_price + 100;

        $stmt = $connection->prepare(
            "SELECT p.product_id, p.product_name, p.price, pi.image_url
             FROM products p
             LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_default = 1
             WHERE (p.price <= ? OR (p.price > ? AND p.price <= ?))
             AND p.is_active = 1"
        );
        $stmt->bind_param("ddd", $min_price, $min_price, $max_price);
    } else {
        $search = "%{$query}%";
        $stmt = $connection->prepare(
            "SELECT p.product_id, p.product_name, p.price, pi.image_url
             FROM products p
             LEFT JOIN brands b ON p.brand_id = b.brand_id
             LEFT JOIN categories c ON p.category_id = c.category_id
             LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_default = 1
             WHERE (p.product_name LIKE ? OR b.brand_name LIKE ? OR c.category_name LIKE ?)
             AND p.is_active = 1"
        );
        $stmt->bind_param("sss", $search, $search, $search);
    }

    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Search Results | Elite Footwear</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/products.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/search.css" />
  
</head>
<body>

<div class="search-container">
  <h2>🔍 Search Results for "<?= htmlspecialchars($query) ?>"</h2>

  <?php if (empty($results)): ?>
    <p class="no-results">No products found for your search. Try a different keyword.</p>
  <?php else: ?>
    <div class="search-grid">
      <?php foreach ($results as $product): ?>
        <div class="card">
          <a href="product_details.php?id=<?= $product['product_id'] ?>" style="text-decoration:none; color:inherit;">
            <img src="<?= !empty($product['image_url']) ? UPLOADS_URL . $product['image_url'] : BASE_URL . 'assets/images/no-image.png' ?>" alt="<?= htmlspecialchars($product['product_name']) ?>">
            <h4><?= htmlspecialchars($product['product_name']) ?></h4>
            <p>₹<?= number_format($product['price'], 2) ?></p>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
</body>
</html>
