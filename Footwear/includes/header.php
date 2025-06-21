<?php
if (!defined('BASE_URL')) {
    require_once '../config.php';
}
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Elite Footwear</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/header.css">
  
</head>
<body>

<header>
  <div class="logo">
    <a href="<?= BASE_URL ?>views/index.php" style="color: white; text-decoration: none;">👟 Elite Footwear</a>
  </div>

  <nav>
    <a href="<?= BASE_URL ?>views/index.php">Home</a>
    <a href="<?= BASE_URL ?>views/products.php">Shop</a>
    <a href="<?= BASE_URL ?>views/cart.php">
      Cart
      <?php
        $cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
        echo "<span class='cart-count'>$cart_count</span>";
      ?>
    </a>
    <div class="search-box">
      <form action="<?= BASE_URL ?>views/search.php" method="get">
        <input type="text" name="query" placeholder="Search..." />
      </form>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="<?= BASE_URL ?>views/dashboard.php"><?= htmlspecialchars($_SESSION['username'])?></a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>views/login.php">Login</a>
    <?php endif; ?>
  </nav>
</header>
