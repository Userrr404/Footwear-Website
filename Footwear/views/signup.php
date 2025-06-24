<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $user_email    = trim($_POST['user_email']);
    $user_password = password_hash($_POST['user_password'], PASSWORD_DEFAULT);

    $stmt = $connection->prepare("INSERT INTO users (`username`, `user_email`, `user_password`) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $user_email, $user_password);

    if ($stmt->execute()) {
        header("Location: login.php?success=1");
    } else {
        $error = "Username or Email already exists.";
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
        <input type="email" name="user_email" placeholder="Email" required /><br>
        <input type="password" name="user_password" placeholder="Password" required /><br>
        <button type="submit">Sign Up</button>
    </form>
    <p>Already registered? <a href="login.php">Login here</a></p>
</div>
</body>
</html>
