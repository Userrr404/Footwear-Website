<?php
session_start();
date_default_timezone_set('Asia/Kolkata'); // Set this time zone as per our requirement because php date function uses this time zone (UTC instead of Asia/Kolkate).
require_once '../config.php'; // Define DB config paths
require_once INCLUDES_PATH . 'db_connection.php'; // Include DB connection
require_once '../includes/user_activity.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_email = trim($_POST['user_email']);
    $user_password = $_POST['user_password'];

    $stmt = $connection->prepare("SELECT * FROM users WHERE user_email = ?");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();

        if ($user['status'] !== 'active') {
            $error = "⚠ Your account is not active. Please contact support.";
            logUserActivity($user['user_id'], 'login', 'Inactive account attempted login', 0);
        }elseif (password_verify($user_password, $user['user_password'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['user_email']= $user['user_email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            logUserActivity($user['user_id'], 'login', 'Login successful');

            // Update last login time and IP
            $last_login_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            /*  
            USE PHP date function to get the current timestamp that will be used to update the last login time.
            But insert the current timestamp using php date time function.
                $last_login_at = date('Y-m-d H:i:s');
                $update = $connection->prepare("UPDATE users SET last_login_at = ?, last_login_ip = ? WHERE user_id = ?");
                $update->bind_param("ssi", $last_login_at, $last_login_ip, $user['user_id']);
            */

             // ✅ Update last login timestamp and IP using MySQL NOW()
            $update = $connection->prepare("
                UPDATE users 
                SET last_login_at = NOW(), last_login_ip = ? 
                WHERE user_id = ?
            ");
            $update->bind_param("si", $last_login_ip, $user['user_id']);
            $update->execute();
            $update->close();

            header("Location: dashboard.php");
            exit;
        } else {
            logUserActivity($user['user_id'], 'login', 'Wrong password', 0);
            $error = "Invalid email or password.";
        }
    } else {
        logUserActivity(null, 'login', "Failed login for unknown email: $user_email", 0);
        $error = "Invalid email or password.";
    }
    $error = "Invalid email or password.";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - Elite Footwear</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap + Tailwind -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

<div class="bg-white shadow-lg rounded-2xl p-6 w-full max-w-md">
  <h2 class="text-2xl font-bold text-center mb-4">Welcome Back 👋</h2>

  <!-- Flash Messages -->
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= $error; ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">✅ Account created! Please log in.</div>
  <?php endif; ?>

  <!-- Login Form -->
  <form method="POST" class="space-y-3" autocomplete="off">
    <input type="email" name="user_email" placeholder="Email" class="form-control" required>
    
    <div class="input-group">
      <input type="password" name="user_password" id="password" placeholder="Password" class="form-control" required>
      <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">👁</button>
    </div>

    <div class="d-flex justify-content-between align-items-center my-2">
      <div>
        <input type="checkbox" id="remember" class="form-check-input">
        <label for="remember" class="form-check-label text-sm">Remember me</label>
      </div>
      <a href="forgot_password.php" class="text-sm text-blue-600">Forgot Password?</a>
    </div>

    <button type="submit" class="btn btn-dark w-100">Login</button>
  </form>

  <p class="mt-4 text-center text-sm">
    Don’t have an account? <a href="signup.php" class="text-blue-600 font-semibold">Sign up</a>
  </p>
</div>

<script>
function togglePassword() {
  const pwd = document.getElementById("password");
  pwd.type = pwd.type === "password" ? "text" : "password";
}
</script>

</body>
</html>

