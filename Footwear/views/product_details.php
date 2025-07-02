<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once INCLUDES_PATH . 'header.php';

// Show flash message (success or error)
$wishlist_success = $_SESSION['success'] ?? '';
$wishlist_error   = $_SESSION['error'] ?? '';

// Clear the session messages (flash once)
unset($_SESSION['success'], $_SESSION['error']);


if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<p>Invalid product ID.</p>";
    exit;
}

$product_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Wishlist check
$wishlisted = false;
if (isset($_SESSION['user_id'])) {
    $wishlist_check = $connection->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?");
    $wishlist_check->bind_param("ii", $user_id, $product_id);
    $wishlist_check->execute();
    $wishlist_check->store_result();
    $wishlisted = $wishlist_check->num_rows > 0;
}

// Product Info
$sql = "SELECT p.*, c.category_name, b.brand_name 
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN brands b ON p.brand_id = b.brand_id
        WHERE p.product_id = ? AND p.is_active = 1 LIMIT 1";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "<p>Product not found.</p>";
    exit;
}
$product = $result->fetch_assoc();

// Images
$image_sql = "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_default DESC, image_id ASC";
$image_stmt = $connection->prepare($image_sql);
$image_stmt->bind_param("i", $product_id);
$image_stmt->execute();
$images = $image_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Reviews
$review_stmt = $connection->prepare("SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.user_id WHERE r.product_id = ? ORDER BY r.reviewed_at DESC");
$review_stmt->bind_param("i", $product_id);
$review_stmt->execute();
$reviews = $review_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars($product['product_name']) ?> | Elite Footwear</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/product_details.css" />
    <style>
.success {
  background-color: #d4edda;
  color: #155724;
  padding: 10px;
  margin: 10px 0;
  border: 1px solid #c3e6cb;
  border-radius: 5px;
}
.error {
  background-color: #f8d7da;
  color: #721c24;
  padding: 10px;
  margin: 10px 0;
  border: 1px solid #f5c6cb;
  border-radius: 5px;
}

.wishlist-icon {
  font-size: 2rem;
  cursor: pointer;
  transition: transform 0.2s ease;
}
.wishlist-icon:hover {
  transform: scale(1.2);
}
</style>

</head>
<body>

<div class="container">
  <!-- Image Gallery -->
  <div class="image-preview">
    <img id="mainImage" class="main-image" src="<?= UPLOADS_URL . $images[0]['image_url'] ?>" alt="Main Product Image">
    <div class="thumbnails">
      <?php foreach ($images as $index => $img): ?>
        <img src="<?= UPLOADS_URL . $img['image_url'] ?>" class="<?= $index === 0 ? 'active' : '' ?>" onclick="switchImage(this)">
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Product Info -->
  <div class="product-info">
    <h1><?= htmlspecialchars($product['product_name']) ?></h1>
    <p><strong>Brand:</strong> <?= htmlspecialchars($product['brand_name']) ?></p>
    <p><strong>Category:</strong> <?= htmlspecialchars($product['category_name']) ?></p>
    <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
    <p class="price">₹<?= number_format($product['price'], 2) ?></p>

    <form method="post" action="<?= BASE_URL ?>php/add_to_cart.php">
      <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
      <div class="form-group">
        <label>Size:
          <select name="size" required>
            <option value="">Select Size</option>
            <?php foreach ([6,7,8,9,10,11] as $size): ?>
              <option><?= $size ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="form-group">
        <label>Quantity:
          <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock'] ?>">
        </label>
      </div>
      <button type="submit">🛒 Add to Cart</button>
    </form>

    <?php if ($wishlist_success): ?>
  <p class="success"><?= htmlspecialchars($wishlist_success) ?></p>
<?php endif; ?>

<?php if ($wishlist_error): ?>
  <p class="error"><?= htmlspecialchars($wishlist_error) ?></p>
<?php endif; ?>
    <!-- Add to Wishlist -->
    <form method="post" action="<?= BASE_URL ?>php/<?= $wishlisted ? 'remove_from_wishlist.php' : 'add_to_wishlist.php' ?>" style="margin-top: 10px;">
    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
    <div class="wishlist-toggle" data-product-id="<?= $product['product_id'] ?>">
    <span class="wishlist-icon" style="font-size: 1.8rem; cursor: pointer;">
        <?= $wishlisted ? '❤️' : '🤍' ?>
    </span>
</div>
<p id="wishlist-status" style="font-size: 0.9rem; color: green;"></p>

</form>


    <p><strong>Stock:</strong> <?= $product['stock'] ?> available</p>
  </div>
</div>

<!-- Reviews -->
<div class="container review-section">
  <h2>Customer Reviews</h2>
  <?php while ($review = $reviews->fetch_assoc()): ?>
    <div class="review">
      <strong><?= htmlspecialchars($review['username']) ?></strong>
      <p><?= str_repeat('★', (int)$review['rating']) ?> <?= $review['rating'] ?>/5</p>
      <p><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
    </div>
  <?php endwhile; ?>
</div>

<!-- Similar Products -->
<div class="container suggested">
  <h2>You May Also Like</h2>
  <div class="similar-grid">
    <?php
      $similar_sql = "SELECT p.product_id, p.product_name, p.price, pi.image_url 
                      FROM products p 
                      LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_default = 1
                      WHERE p.category_id = ? AND p.product_id != ? LIMIT 4";
      $similar_stmt = $connection->prepare($similar_sql);
      $similar_stmt->bind_param("ii", $product['category_id'], $product_id);
      $similar_stmt->execute();
      $similar_result = $similar_stmt->get_result();
      while ($sim = $similar_result->fetch_assoc()):
    ?>
      <div class="similar-card">
        <a href="product_details.php?id=<?= $sim['product_id'] ?>">
          <img src="<?= UPLOADS_URL . $sim['image_url'] ?>" alt="<?= htmlspecialchars($sim['product_name']) ?>">
          <h4><?= htmlspecialchars($sim['product_name']) ?></h4>
          <p>₹<?= number_format($sim['price'], 2) ?></p>
        </a>
      </div>
    <?php endwhile; ?>
  </div>
  <p><a href="<?= BASE_URL ?>views/index.php">← Back to Home</a></p>
</div>

<!-- Footer -->
<?php require_once INCLUDES_PATH . 'footer.php'; ?>

<!-- Scripts -->
<script src="<?= BASE_URL ?>assets/js/product_details.js"></script>
<script>
document.querySelector('.wishlist-toggle')?.addEventListener('click', function () {
    const icon = this.querySelector('.wishlist-icon');
    const productId = this.dataset.productId;
    const isWishlisted = icon.textContent.trim() === '❤️';
    const action = isWishlisted ? 'remove_from_wishlist' : 'add_to_wishlist';

    fetch(`../php/${action}.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${productId}`
    })
    .then(response => response.text())
    .then(data => {
        if (isWishlisted) {
            icon.textContent = '🤍';
            document.getElementById('wishlist-status').textContent = 'Removed from wishlist.';
        } else {
            icon.textContent = '❤️';
            document.getElementById('wishlist-status').textContent = 'Added to wishlist.';
        }
    })
    .catch(error => {
        console.error('Wishlist error:', error);
        document.getElementById('wishlist-status').textContent = 'An error occurred.';
    });
});
</script>

</body>
</html>
