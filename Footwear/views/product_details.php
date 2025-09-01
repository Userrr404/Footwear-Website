<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';

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
// --- Review stats (counts + avg) ---
$stats_sql = "
  SELECT 
    COUNT(*) AS total,
    IFNULL(AVG(rating),0) AS avg_rating,
    SUM(rating = 5) AS r5,
    SUM(rating = 4) AS r4,
    SUM(rating = 3) AS r3,
    SUM(rating = 2) AS r2,
    SUM(rating = 1) AS r1
  FROM reviews
  WHERE product_id = ?";
$stats_stmt = $connection->prepare($stats_sql);
$stats_stmt->bind_param("i", $product_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// initial reviews (server-side render first page)
$per_page = 6;
$reviews_sql = "SELECT r.*, u.username 
                FROM reviews r
                JOIN users u ON r.user_id = u.user_id
                WHERE r.product_id = ?
                ORDER BY r.reviewed_at DESC
                LIMIT ?";
$rev_stmt = $connection->prepare($reviews_sql);
$rev_stmt->bind_param("ii", $product_id, $per_page);
$rev_stmt->execute();
$initial_reviews = $rev_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars($product['product_name']) ?> | Elite Footwear</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/product_details.css" />

</head>
<body class="bg-white">

<?php require_once INCLUDES_PATH . 'header.php'; 
    $user_id = null;
    if(isset($_SESSION['user_id'])){
    $user_id = (int) $_SESSION['user_id'];
}

// Wishlist check
$wishlisted = false;
if (isset($_SESSION['user_id'])) {
    $wishlist_check = $connection->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?");
    $wishlist_check->bind_param("ii", $user_id, $product_id);
    $wishlist_check->execute();
    $wishlist_check->store_result();
    $wishlisted = $wishlist_check->num_rows > 0;
}
?>

<div class="container">
  <!-- Left: Image Gallery -->
  <div class="gallery">
    <div class="main-image-wrapper">
      <img id="mainImage" class="main-image" src="<?= UPLOADS_URL . $images[0]['image_url'] ?>" alt="Main Product Image">
      <!-- Wishlist on Image -->
      <form method="post" action="<?= BASE_URL ?>php/<?= $wishlisted ? 'remove_from_wishlist.php' : 'add_to_wishlist.php' ?>" class="wishlist-btn">
        <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
        <button 
  type="button" 
  class="wishlist-toggle" 
  data-product-id="<?= $product['product_id'] ?>">
  <span class="wishlist-icon"><?= $wishlisted ? '❤️' : '🤍' ?></span>
</button>
<div id="wishlist-status"></div>
      </form>
    </div>
    <div class="thumbnails">
      <?php foreach ($images as $index => $img): ?>
        <img src="<?= UPLOADS_URL . $img['image_url'] ?>" class="<?= $index === 0 ? 'active' : '' ?>" onclick="switchImage(this)">
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Right: Product Info -->
  <div class="details">
    <h1 class="title"><?= htmlspecialchars($product['product_name']) ?></h1>
    <p class="brand"><strong>Brand:</strong> <?= htmlspecialchars($product['brand_name']) ?></p>
    <p class="category"><strong>Category:</strong> <?= htmlspecialchars($product['category_name']) ?></p>
    <p class="desc"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

    <div class="price-box">
      <span class="price">₹<?= number_format($product['selling_price'], 2) ?></span>
    </div>

    <!-- Add to Cart Form -->
    <form method="post" action="<?= BASE_URL ?>php/add_to_cart.php" class="cart-form">
      <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
      
      <div class="form-group">
        <label>Size</label>
        <select name="size" required>
          <option value="">Select Size</option>
          <?php foreach ([6,7,8,9,10,11] as $size): ?>
            <option><?= $size ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Quantity</label>
        <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock'] ?>">
      </div>

      <button type="submit" class="cart-btn">🛒 Add to Cart</button>
    </form>

    <?php if ($wishlist_success): ?><p class="success"><?= htmlspecialchars($wishlist_success) ?></p><?php endif; ?>
    <?php if ($wishlist_error): ?><p class="error"><?= htmlspecialchars($wishlist_error) ?></p><?php endif; ?>

    <p class="stock"><strong>Stock:</strong> <?= $product['stock'] ?> available</p>
  </div>
</div>



<!-- Reviews -->
<div class="review-section" id="reviewSection">
  <div class="review-top">
    <div class="rating-summary" id="ratingSummary">
      <div class="avg">
        <div class="avg-number"><?= round((float)$stats['avg_rating'],1) ?></div>
        <div class="avg-stars" aria-hidden="true">
          <?php
            $filled = (int) floor($stats['avg_rating']);
            for ($i=1;$i<=5;$i++){
              echo $i <= $filled ? "<span class='star filled'>★</span>" : "<span class='star'>★</span>";
            }
          ?>
        </div>
        <div class="total-reviews"><?= (int)$stats['total'] ?> reviews</div>
      </div>

      <div class="breakdown">
        <?php
        $total = max(1, (int)$stats['total']); // avoid division by zero for bar widths
        for ($r = 5; $r >= 1; $r--):
          $count = (int)$stats["r{$r}"];
          $pct = round(($count / $total) * 100);
        ?>
          <div class="row">
            <span class="row-label"><?= $r ?> <span class="star">★</span></span>
            <div class="bar">
              <div class="bar-fill" style="width: <?= $pct ?>%"></div>
            </div>
            <span class="row-count"><?= $count ?></span>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <div class="review-controls">
      <label for="reviewSort" class="visually-hidden">Sort reviews</label>
      <select id="reviewSort" aria-label="Sort reviews">
        <option value="recent">Most recent</option>
        <option value="top">Top rated</option>
        <option value="low">Lowest rated</option>
      </select>

      <button id="writeReviewToggle" class="btn outline">Write a review</button>
    </div>
  </div>

  <!-- reviews list -->
  <div class="reviews-list" id="reviewsList" data-offset="<?= $per_page ?>">
    <?php while ($review = $initial_reviews->fetch_assoc()): ?>
      <article class="review-card" data-review-id="<?= (int)$review['review_id'] ?>">
        <header class="rc-head">
          <div class="avatar"><?= strtoupper(substr($review['username'],0,1)) ?></div>
          <div class="meta">
            <div class="name"><?= htmlspecialchars($review['username']) ?></div>
            <div class="rating">
              <?php for ($i=1;$i<=5;$i++): ?>
                <span class="star <?= $i <= (int)$review['rating'] ? 'filled' : '' ?>">★</span>
              <?php endfor; ?>
              <time class="time"><?= htmlspecialchars(date('M j, Y', strtotime($review['reviewed_at']))) ?></time>
            </div>
          </div>
        </header>
        <div class="rc-body"><?= nl2br(htmlspecialchars($review['comment'])) ?></div>
      </article>
    <?php endwhile; ?>
  </div>

  <div class="reviews-footer">
    <button id="loadMoreReviews" class="btn">Load more reviews</button>
  </div>

  <!-- Write review form (hidden on mobile until toggled) -->
  <div class="write-review" id="writeReview" aria-hidden="true">
    <?php if (isset($user_id) && $user_id): ?>
      <form id="addReviewForm" method="post" action="<?= BASE_URL ?>php/add_review.php">
        <input type="hidden" name="product_id" value="<?= $product_id ?>">
        <div class="star-input" role="radiogroup" aria-label="Rating">
          <label class="sr-only">Rating</label>
          <?php for ($s=5;$s>=1;$s--): ?>
            <input type="radio" id="star<?= $s ?>" name="rating" value="<?= $s ?>">
            <label for="star<?= $s ?>" class="star-label" title="<?= $s ?> stars">★</label>
          <?php endfor; ?>
        </div>
        <div class="form-row">
          <label for="reviewComment">Your review</label>
          <textarea id="reviewComment" name="comment" rows="4" maxlength="2000" required></textarea>
        </div>
        <div class="form-row actions">
          <button type="submit" class="btn primary">Submit review</button>
          <button type="button" id="cancelReview" class="btn outline">Cancel</button>
        </div>
        <div id="reviewMessage" role="status" aria-live="polite"></div>
      </form>
    <?php else: ?>
      <div class="login-prompt">
        <p>You must <a href="<?= BASE_URL ?>views/login.php">log in</a> to write a review.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Similar Products -->
<div class="similar-products">
  <div class="similar-header">
    <h2>You May Also Like</h2>
    <a href="<?= BASE_URL ?>views/index.php" class="view-all">View All →</a>
  </div>

  <div class="similar-scroll-wrapper">
    <button class="scroll-btn left" onclick="scrollSimilar(-1)">&#10094;</button>
    <div class="similar-scroll" id="similarScroll">
      <?php
      $similar_sql = "SELECT p.product_id, p.product_name, p.selling_price, pi.image_url 
                      FROM products p 
                      LEFT JOIN product_images pi 
                      ON p.product_id = pi.product_id AND pi.is_default = 1
                      WHERE p.category_id = ? AND p.product_id != ? LIMIT 4";
      $similar_stmt = $connection->prepare($similar_sql);
      $similar_stmt->bind_param("ii", $product['category_id'], $product_id);
      $similar_stmt->execute();
      $similar_result = $similar_stmt->get_result();
      while ($sim = $similar_result->fetch_assoc()):
    ?>
      <div class="similar-card">
        <a href="product_details.php?id=<?= $sim['product_id'] ?>">
          <div class="similar-image">
            <img src="<?= UPLOADS_URL . $sim['image_url'] ?>" alt="<?= htmlspecialchars($sim['product_name']) ?>">
          </div>
          <div class="similar-info">
            <h4><?= htmlspecialchars($sim['product_name']) ?></h4>
            <p class="price">₹<?= number_format($sim['selling_price'], 2) ?></p>
            <button class="shop-btn">View Product</button>
          </div>
        </a>
      </div>
    <?php endwhile; ?>
    </div>
    <button class="scroll-btn right" onclick="scrollSimilar(1)">&#10095;</button>
  </div>
</div>


<!-- Footer -->
<?php require_once INCLUDES_PATH . 'footer.php'; ?>

<!-- Scripts -->
<script>
  window.REVIEW_CONFIG = {
    productId: <?= json_encode($product_id) ?>,
    perPage: <?= json_encode($per_page) ?>,
    baseUrl: <?= json_encode(BASE_URL) ?>,
    userId: <?= isset($user_id) ? (int)$user_id : 'null' ?>
  };
</script>
<script src="<?= BASE_URL ?>assets/js/product_details.js"></script>
<script src="<?= BASE_URL ?>assets/js/product_reviews.js"></script>
<script>
document.querySelector('.wishlist-toggle')?.addEventListener('click', function () {
    const icon = this.querySelector('.wishlist-icon');
    const productId = this.dataset.productId;
    const isWishlisted = icon.textContent.trim() === '❤️';
    const action = isWishlisted ? 'remove_from_wishlist' : 'add_to_wishlist';
    const statusBox = document.getElementById('wishlist-status');

    fetch(`../php/${action}.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
         },
        body: `product_id=${productId}`
    })
    .then(response => response.json()) // better: return JSON from PHP
    .then(data => {
        if (data.status === 'success' || data.status === 'exists') {
            if (isWishlisted) {
                icon.textContent = '🤍';
                document.getElementById('wishlist-status').textContent = 'Removed from wishlist.';
            } else {
                icon.textContent = '❤️';
                document.getElementById('wishlist-status').textContent = 'Added to wishlist.';
            }

            // show for 2 sec only
            statusBox.style.display = 'block';
            statusBox.style.opacity = '1';
            setTimeout(() => {
  statusBox.style.opacity = '0';
  setTimeout(() => {
    statusBox.style.display = 'none';
  }, 300);
}, 2000);
        } else {
            document.getElementById('wishlist-status').textContent = data.error || 'Something went wrong.';
            statusBox.style.display = "block";
            setTimeout(() => {
              statusBox.style.display = 'none';
            }, 2000);
        }
    })
    .catch(error => {
        console.error('Wishlist error:', error);
        document.getElementById('wishlist-status').textContent = 'An error occurred.';
        statusBox.textContent = 'An error occurred.';
        statusBox.style.display = 'block';
        setTimeout(() => {
            statusBox.style.display = 'none';
        }, 2000);
    });
});
</script>

<script>
function scrollSimilar(direction) {
  const container = document.getElementById('similarScroll');
  const scrollAmount = 250; // adjust card width
  container.scrollBy({
    left: direction * scrollAmount,
    behavior: 'smooth'
  });
}
</script>


</body>
</html>
