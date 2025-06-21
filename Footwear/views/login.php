<?php
session_start();
require_once '../config.php'; // Define DB config paths
require_once INCLUDES_PATH . 'db_connection.php'; // Include DB connection

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_email = trim($_POST['user_email']);
    $user_password = $_POST['user_password'];

    // Prepare statement to avoid SQL injection
    $stmt = $connection->prepare("SELECT * FROM users WHERE user_email = ?");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();

        // Verify hashed password
        if (password_verify($user_password, $user['user_password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_email'] = $user['user_email'];

            header("Location: dashboard.php");
            exit;
        }
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
