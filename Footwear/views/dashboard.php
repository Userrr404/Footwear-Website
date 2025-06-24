<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - Elite Footwear</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
  <div class="dashboard-container">
    <div class="dashboard-header">
      <h2>Welcome, <?= htmlspecialchars($username) ?> 👋</h2>
      <p class="welcome-message">Your personalized dashboard</p>
    </div>

    <div class="dashboard-links">
      <a href="index.php">🏠 Home</a>
      <a href="products.php">🛍️ Shop Products</a>
      <a href="cart.php">🛒 My Cart</a>
      <a href="orders.php">📦 My Orders</a>
      <a href="profile.php">👤 Account Settings</a>
      <a href="wishlist.php">❤️ Wishlist</a>
    </div>

    <div class="logout">
      <a href="logout.php">🚪 Logout</a>
    </div>
  </div>

</body>
</html>
