<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once INCLUDES_PATH . 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $connection->prepare("SELECT username, user_email, user_phone FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Profile | Elite Footwear</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/profile.css">
</head>
<body>

<div class="profile-container">
  <h2>👤 My Profile</h2>

  <div class="profile-card">
    <p><strong>Name:</strong> <?= htmlspecialchars($user['username'] ?? '') ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($user['user_email'] ?? '') ?></p>
    <p><strong>Phone:</strong> <?= htmlspecialchars($user['user_phone'] ?? 'Not Proveded yet!') ?></p>
    <a class="btn" href="edit_profile.php">✏️ Edit Profile</a>
    <a class="btn" href="change_password.php">🔐 Change Password</a>
  </div>

  <div class="profile-links">
    <a href="orders.php">📦 My Orders</a>
    <a href="saved_items.php">❤️ Wishlist</a>
    <a href="manage_addresses.php">📍 My Addresses</a>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
  </div>
</div>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
</body>
</html>
