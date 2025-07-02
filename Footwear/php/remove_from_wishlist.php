<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once '../includes/user_activity.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../views/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'] ?? null;

if ($product_id) {
    $stmt = $connection->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        // Successfully removed from wishlist
        $_SESSION['success'] = "Product removed from wishlist successfully.";
        logUserActivity($user_id, 'remove_from_wishlist', 'Removed product ID from wishlist: ' . $product_id);
    } else {
        // Not found or error
        $_SESSION['error'] = "This product was not found in your wishlist or an error occurred.";
    }
}

header("Location: ../views/wishlist.php");
exit;
?>