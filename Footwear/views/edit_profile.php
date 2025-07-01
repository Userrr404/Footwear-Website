<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once INCLUDES_PATH . 'header.php';
require_once '../includes/user_activity.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = $error = '';
$enable_2fa = false;

// Fetch user details
$stmt = $connection->prepare("SELECT username, user_email, user_phone, profile_img, twofa_enabled FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch recent activity
$logs_stmt = $connection->prepare("SELECT action_type, log_time FROM user_logs WHERE user_id = ? ORDER BY log_time DESC LIMIT 5");
$logs_stmt->bind_param("i", $user_id);
$logs_stmt->execute();
$user_logs = $logs_stmt->get_result();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Enable 2FA
    if (isset($_POST['toggle_2fa'])) {
        $enable_2fa = true;
        $otp = rand(100000, 999999);
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expires'] = time() + 300; // 5 minutes
        mail($user['user_email'], "Your OTP", "Use this OTP to enable 2FA: $otp");
        $success = "OTP sent to your email.";
    }

    // Confirm OTP
    if (isset($_POST['confirm_otp'])) {
        if ($_SESSION['otp_expires'] < time()) {
            $error = "OTP expired.";
        } elseif ($_POST['otp'] !== $_SESSION['otp']) {
            $error = "Incorrect OTP.";
        } else {
            $connection->prepare("UPDATE users SET twofa_enabled = 1 WHERE user_id = ?")
                       ->bind_param("i", $user_id)->execute();
            unset($_SESSION['otp']);
            $success = "Two-factor authentication enabled.";
        }
    }

    // Save profile changes
    if (isset($_POST['save_profile'])) {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['user_email'] ?? '');
        $phone    = trim($_POST['user_phone'] ?? '');
        $newName  = $user['profile_img'];

        // Email validation
        if (empty($email)) {
            $error = "Email cannot be empty.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if email exists for another user
            $check = $connection->prepare("SELECT user_id FROM users WHERE user_email = ? AND user_id != ?");
            $check->bind_param("si", $email, $user_id);
            $check->execute();
            $check_result = $check->get_result();
            if ($check_result->num_rows > 0) {
                $error = "This email is already registered.";
            }
        }

        // Handle image upload
        if (!$error && !empty($_FILES['profile_img']['name'])) {
            $ext = strtolower(pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png']) && $_FILES['profile_img']['size'] <= 2e6) {
                $newName = "profile_{$user_id}." . $ext;
                move_uploaded_file($_FILES['profile_img']['tmp_name'], UPLOADS_PROFILE_PATH . $newName);
                /* Even when only the profile image changes, both logging functions are executed, resulting in 2 rows inserted.
                logUserActivity($user_id, 'Profile updated', 'Profile photo updated successfully');
                */
            } else {
                $error = "Invalid image. Only JPG/PNG ≤ 2MB allowed.";
            }
        }

        // Update user
        if (!$error) {
            $upd = $connection->prepare("UPDATE users SET username=?, user_email=?, user_phone=?, profile_img=? WHERE user_id=?");
            $upd->bind_param("ssssi", $username, $email, $phone, $newName, $user_id);
            if ($upd->execute()) {
                $_SESSION['username'] = $username;
                $success = "Profile updated successfully.";
                $stmt->execute(); // Re-fetch updated user
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $error = "Failed to update profile.";
            }
            logUserActivity($user_id, 'Profile updated', 'Profile updated successfully');
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Profile | Elite Footwear</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/profile.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/edit_profile.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js"></script>
</head>
<body>

<div class="main_container">
    <div class="container">

        <form method="POST" enctype="multipart/form-data" class="container">
  <!-- LEFT: Profile image -->
  <div class="left-panel">
    <img id="preview" src="<?= UPLOADS_PROFILE_URL . ($user['profile_img'] ?: 'default-avatar.png') ?>" alt="Profile">
    <input type="file" name="profile_img" accept="image/*" onchange="preview(this)">
  </div>

  <!-- RIGHT: Profile fields -->
  <div class="right-panel">
    <h2>Edit Profile 🔧</h2>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>

    <input type="text" name="username" required value="<?= htmlspecialchars($user['username']) ?>" placeholder="Full Name">
    <input type="email" name="user_email" required value="<?= htmlspecialchars($user['user_email']) ?>" placeholder="Email">
    <input type="text" name="user_phone" value="<?= htmlspecialchars($user['user_phone'] ?? '') ?>" placeholder="Phone (optional)">

    <label for="pass">Change Password</label>
    <input type="password" id="pass" name="new_password" placeholder="New Password (optional)">
    <div class="strength" id="strength"></div>

    <h3>Two-Factor Authentication 🔐</h3>
    <?php if ($user['twofa_enabled']): ?>
      <p>✅ 2FA is <strong>enabled</strong>.</p>
    <?php elseif ($enable_2fa): ?>
      <input type="text" name="otp" placeholder="Enter OTP">
      <button type="submit" name="confirm_otp">Confirm OTP</button>
    <?php else: ?>
      <button type="submit" name="toggle_2fa">Enable 2FA</button>
    <?php endif; ?>

    <button type="submit" name="save_profile">💾 Save</button>
  </div>
</form>

    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/edit_profile.js"></script>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
</body>
</html>
