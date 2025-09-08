<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
session_start();

// if ($_SERVER['REQUEST_METHOD'] == 'POST') {
//     $username = trim($_POST['username']);
//     $user_email    = trim($_POST['user_email']);
//     $user_password = password_hash($_POST['user_password'], PASSWORD_DEFAULT);

//     $stmt = $connection->prepare("INSERT INTO users (`username`, `user_email`, `user_password`) VALUES (?, ?, ?)");
//     $stmt->bind_param("sss", $username, $user_email, $user_password);

//     if ($stmt->execute()) {
//         header("Location: login.php?success=1");
//     } else {
//         $error = "Username or Email already exists.";
//     }
// }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize input
    $username       = trim($_POST['username']);
    $full_name      = trim($_POST['full_name']);
    $user_email     = trim($_POST['user_email']);
    $user_phone     = trim($_POST['user_phone']);
    $user_password  = $_POST['user_password'];
    $city           = trim($_POST['city']);
    $state          = trim($_POST['state']);
    $country        = trim($_POST['country']);
    $referral_code  = trim($_POST['referral_code'] ?? '');
    $device_type    = preg_match('/mobile/i', $_SERVER['HTTP_USER_AGENT']) ? 'mobile' : 'desktop';
    $traffic_source = $_COOKIE['utm_source'] ?? 'direct';

    // Basic validation
    if (empty($username) || empty($user_email) || empty($user_password) || empty($full_name)) {
        $_SESSION['error'] = "⚠ Please fill all required fields.";
        header("Location: signup.php");
        exit;
    }

    // Hash password
    $hashed_password = password_hash($user_password, PASSWORD_BCRYPT);

    // Check if username already exists
    $stmt = $connection->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "⚠ Username is already taken.";
        header("Location: signup.php");
        exit;
    }

    // Check if email already exists
    $stmt = $connection->prepare("SELECT user_id FROM users WHERE user_email = ?");
    $stmt->bind_param('s', $user_email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "⚠ Email already registered.";
        header("Location: signup.php");
        exit;
    }

    // Insert user
    $query = "INSERT INTO users (
        username, full_name, user_email, user_password, user_phone,
        city, state, country, created_at,
        device_type, traffic_source,
        referral_code, role, status, is_active,
        loyalty_tier, cltv
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, 'customer', 'active', 1, 'Silver', 0.00)";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param(
        'sssssssssss',
        $username, $full_name, $user_email, $hashed_password, $user_phone,
        $city, $state, $country,
        $device_type, $traffic_source,
        $referral_code
    );

    if ($stmt->execute()) {
        // Send Welcome Email
        $subject = "Welcome to Elite Footwear!";
        $message = "Hi $full_name,\n\nThank you for signing up at Elite Footwear. We’re excited to have you on board!\n\n- Team Elite";
        $headers = "From: no-reply@elitefootwear.com";

        mail($user_email, $subject, $message, $headers);

        $_SESSION['success'] = "✅ Registration successful! Please check your email.";
        header("Location: login.php");
    } else {
        $_SESSION['error'] = "⚠ Registration failed. Please try again.";
        header("Location: signup.php");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Signup - Elite Footwear</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap + Tailwind -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

<div class="bg-white shadow-lg rounded-2xl p-6 w-full max-w-md">
  <h2 class="text-2xl font-bold text-center mb-4">Create Your Account</h2>

  <!-- Flash Messages -->
  <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
  <?php endif; ?>
  <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
  <?php endif; ?>

  <form method="POST" class="space-y-3">
    <input type="text" name="username" placeholder="Username" class="form-control" required>
    <input type="text" name="full_name" placeholder="Full Name" class="form-control" required>
    <input type="email" name="user_email" placeholder="Email" class="form-control" required>
    <input type="password" name="user_password" placeholder="Password" class="form-control" required>
    <input type="text" name="referral_code" placeholder="Referral Code (Optional)" class="form-control">

    <button type="submit" class="btn btn-dark w-100">Sign Up</button>
  </form>

  <p class="mt-4 text-center text-sm">
    Already have an account? <a href="login.php" class="text-blue-600 font-semibold">Login here</a>
  </p>
</div>

</body>
</html>

