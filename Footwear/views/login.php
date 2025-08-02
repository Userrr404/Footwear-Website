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

        if($user['status'] !== 'active'){
            $error = "Your account is not active. Please contact support.";
            logUserActivity($user['user_id'], 'login', 'Inactive account attempted login', 0);
            header("Location: login.php?error=" . urlencode($error));
            exit;
        }elseif (password_verify($user_password, $user['user_password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_email'] = $user['user_email'];
            $_SESSION['full_name']   = $user['full_name'];
            $_SESSION['role']        = $user['role'];

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
<html>
<head>
    <title>Login - Elite Footwear</title>
    <link rel="stylesheet" href="../assets/css/login_signup.css" />
</head>
<body>
<div class="form-container">
    <h2>Login to Elite Footwear</h2>

    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    <?php if (isset($_GET['success'])) echo "<p class='success'>Account created! Please log in.</p>"; ?>

    <form method="post" autocomplete="off">
        <input type="email" name="user_email" placeholder="Email" required /><br>
        <input type="password" name="user_password" placeholder="Password" required /><br>
        <button type="submit">Login</button>
    </form>

    <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
</div>
</body>
</html>
