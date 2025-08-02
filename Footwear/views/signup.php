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
    $user_email          = trim($_POST['user_email']);
    $user_phone          = trim($_POST['user_phone']);
    $user_password       = $_POST['user_password'];
    $city           = trim($_POST['city']);
    $state          = trim($_POST['state']);
    $country        = trim($_POST['country']);
    $referral_code  = trim($_POST['referral_code'] ?? '');
    $device_type    = preg_match('/mobile/i', $_SERVER['HTTP_USER_AGENT']) ? 'mobile' : 'desktop';
    $traffic_source = $_COOKIE['utm_source'] ?? 'direct';

    // Basic validation
    if (empty($username) || empty($user_email) || empty($user_password) || empty($full_name)) {
        $_SESSION['error'] = "Please fill all required fields.";
        header("Location: signup.php");
        exit;
    }

    // Hash password
    $hashed_password = password_hash($user_password, PASSWORD_BCRYPT);

    // Check if email already exists
    $stmt = $connection->prepare("SELECT user_id FROM users WHERE user_email = ?");
    $stmt->bind_param('s', $user_email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "Email already registered.";
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
        $_SESSION['success'] = "Registration successful!";
        header("Location: login.php");
    } else {
        $_SESSION['error'] = "Registration failed. Please try again.";
        header("Location: signup.php");
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Signup - Elite Footwear</title>
    <link rel="stylesheet" href="../assets/css/login_signup.css" />
</head>
<body>
<div class="form-container">
    <h2>Create Account</h2>
    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="post">
        <input type="text" name="username" placeholder="Username" required /><br>
        <input type="text" name="full_name" placeholder="Full Name" required /><br>
        <input type="email" name="user_email" placeholder="Email" required /><br>
        <input type="password" name="user_password" placeholder="Password" required /><br>
        <input type="text" name="user_phone" placeholder="Phone Number" />
        <input name="city" placeholder="City" />
        <input name="state" placeholder="State" />
        <input name="country" placeholder="Country" />
        <input name="referral_code" placeholder="Referral Code (optional)" />
        <button type="submit">Sign Up</button>
    </form>
    <p>Already registered? <a href="login.php">Login here</a></p>
</div>
</body>
</html>
