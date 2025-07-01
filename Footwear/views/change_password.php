<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once '../includes/user_activity.php';
require_once INCLUDES_PATH . 'header.php';


// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = $error = '';

// Generate CSRF token if not already
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Invalid session token. Please try again.";
    } else {
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password     = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        // Field validation
        if (!$current_password || !$new_password || !$confirm_password) {
            $error = "All fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (
            strlen($new_password) < 8 ||
            !preg_match('/[A-Z]/', $new_password) ||
            !preg_match('/[0-9]/', $new_password)
        ) {
            $error = "Password must be at least 8 characters long, include a capital letter and a number.";
        } else {
            // Fetch user password hash
            $stmt = $connection->prepare("SELECT user_password FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result($stored_hash);
            $stmt->fetch();
            $stmt->close();

            // Check current password
            if (!password_verify($current_password, $stored_hash)) {
                $error = "Current password is incorrect.";
            } else {
                // Hash new password
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password
                $update = $connection->prepare("UPDATE users SET user_password = ? WHERE user_id = ?");
                $update->bind_param("si", $new_hash, $user_id);
                if ($update->execute()) {
                    // Optional: invalidate session and regenerate
                    session_regenerate_id(true);
                    logUserActivity($user_id, 'Password Changed', 'User changed password.');

                    // Optional: Email alert
                    // mail($user_email, "Password Changed", "Your password was changed successfully.");

                    $success = "✅ Password changed successfully.";
                } else {
                    $error = "❌ Failed to update password. Please try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/change_password.css">
    <style>
        body { font-family: Arial; background: #f2f2f2; }
        .container {
            max-width: 400px;
            margin: auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h2 { margin-bottom: 1rem; }
        input, button {
            width: 100%;
            padding: 0.6rem;
            margin: 0.5rem 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            background: #212529;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }
        .success { color: green; margin: 1rem 0; }
        .error { color: red; margin: 1rem 0; }
    </style>
</head>
<body>

<div class="container">
    <h2>🔒 Change Password</h2>

    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="password" name="current_password" placeholder="Current Password" required>
        <input type="password" name="new_password" placeholder="New Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
        <button type="submit">🔁 Update Password</button>
    </form>
</div>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
</body>
</html>
