<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once INCLUDES_PATH . 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = $error = '';

// Handle Add Address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_address'])) {
    $name         = trim($_POST['full_name']);
    $phone        = trim($_POST['phone']);
    $pincode      = trim($_POST['pincode']);
    $address_line = trim($_POST['address_line']);
    $city         = trim($_POST['city']);
    $state        = trim($_POST['state']);
    // $country      = trim($_POST['country']);

    if (!$name || !$phone || !$pincode || !$address_line || !$city || !$state) {
        $error = "All fields are required.";
    } else {
        $stmt = $connection->prepare("INSERT INTO addresses (user_id, full_name, address_line, city, state, pincode, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $user_id, $name, $address_line, $city, $state, $pincode, $phone);
        if ($stmt->execute()) {
            $success = "Address added successfully!";
        } else {
            $error = "Something went wrong. Please try again.";
        }
        $stmt->close();
    }
}

// Handle Delete Address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_address'])) {
    $address_id = intval($_POST['address_id']);
    $stmt = $connection->prepare("DELETE FROM addresses WHERE address_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $address_id, $user_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $success = "Address deleted successfully.";
    } else {
        $error = "Failed to delete address.";
    }
    $stmt->close();
}

// Fetch addresses
$addresses_stmt = $connection->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY created_at DESC");
$addresses_stmt->bind_param("i", $user_id);
$addresses_stmt->execute();
$addresses = $addresses_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Addresses | Elite Footwear</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/manage_addresses.css">
    
</head>
<body>



    <div class="address-form">
        <h2>🏠 Manage Your Addresses</h2>
        <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>

    <!-- List of Addresses -->
    <?php while ($row = $addresses->fetch_assoc()): ?>
        <div class="address-box">
            <strong><?= htmlspecialchars($row['full_name']) ?></strong><br>
            📞 <?= htmlspecialchars($row['phone']) ?><br>
            🏠 <?= htmlspecialchars($row['address_line']) ?>,<br>
            <?= htmlspecialchars($row['city']) ?>, <?= htmlspecialchars($row['state']) ?> - <?= htmlspecialchars($row['pincode']) ?><br>
            <form method="POST" style="margin-top:10px;">
                <input type="hidden" name="address_id" value="<?= $row['address_id'] ?>">
                <button type="submit" name="delete_address" class="delete-btn">❌ Delete</button>
            </form>
        </div>
    <?php endwhile; ?>

    <!-- Add New Address -->
    <h3>➕ Add New Address</h3>
    <form id="new_address" method="POST">
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="text" name="phone" placeholder="Phone Number" required>
        <textarea name="address_line" placeholder="Full Address" required></textarea>
        <input type="text" name="city" placeholder="City" required>
        <input type="text" name="state" placeholder="State" required>
        <input type="text" name="pincode" placeholder="Pincode" required>
        <button type="submit" name="add_address">Add Address</button>
    </form>

    <a href="<?= BASE_URL ?>views/profile.php">← Back to Profile</a>
    </div>



</body>
</html>
