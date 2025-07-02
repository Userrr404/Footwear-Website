<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once INCLUDES_PATH . 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $connection->prepare("
    SELECT 
        p.product_id, 
        p.product_name, 
        p.price, 
        pi.image_url 
    FROM wishlist w
    JOIN products p ON w.product_id = p.product_id
    LEFT JOIN product_images pi ON pi.product_id = p.product_id AND pi.is_default = 1
    WHERE w.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Wishlist | Elite Footwear</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <style>
    .wishlist-container {
      max-width: 1200px;
      margin: auto;
      padding: 2rem;
    }

    .wishlist-header {
      margin-bottom: 2rem;
      text-align: center;
    }

    .card-img-top {
      object-fit: cover;
      height: 200px;
    }

    .wishlist-card {
      transition: transform 0.2s;
    }

    .wishlist-card:hover {
      transform: scale(1.02);
    }

    .btn-remove {
      background-color: #dc3545;
      border: none;
    }
  </style>
</head>
<body>

<div class="wishlist-container">
  <h2 class="wishlist-header">❤️ My Wishlist</h2>

  <?php if ($result->num_rows > 0): ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4">
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="col">
          <div class="card wishlist-card h-100 shadow-sm">
            <img src="<?= UPLOADS_URL . ($row['image_url'] ?? 'default.png') ?>" 
                 class="card-img-top" 
                 alt="<?= htmlspecialchars($row['product_name']) ?>" 
                 onerror="this.src='<?= UPLOADS_URL ?>default.png';" />

            <div class="card-body">
              <h5 class="card-title"><?= htmlspecialchars($row['product_name']) ?></h5>
              <p class="card-text text-muted">₹<?= number_format($row['price'], 2) ?></p>
              <a href="product_details.php?id=<?= $row['product_id'] ?>" class="btn btn-primary btn-sm">View Product</a>
              <form method="POST" action="../php/remove_from_wishlist.php" class="d-inline">
                <input type="hidden" name="product_id" value="<?= $row['product_id'] ?>">
                <button type="submit" class="btn btn-remove btn-sm text-white">❌ Remove</button>
              </form>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-info text-center">
      Your wishlist is empty.<br>
      <a href="<?= BASE_URL ?>views/index.php" class="btn btn-outline-dark mt-2">Browse Products</a>
    </div>
  <?php endif; ?>

  <div class="text-center mt-4">
    <a href="<?= BASE_URL ?>views/profile.php" class="btn btn-secondary">👤 Back to Profile</a>
  </div>
</div>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
