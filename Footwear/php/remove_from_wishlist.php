<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once '../includes/user_activity.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode(['status' => 'unauthenticated']);
        exit;
    }
    header("Location: ../views/login.php");
    exit;
}

$user_id    = $_SESSION['user_id'];
$product_id = $_POST['product_id'] ?? null;

$response = ['status' => 'error', 'message' => 'Something went wrong.'];

if ($product_id) {
    $stmt = $connection->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        logUserActivity($user_id, 'remove_from_wishlist', 'Removed product ID from wishlist: ' . $product_id);
        $response = ['status' => 'success', 'message' => 'Product removed from wishlist.'];
    } else {
        $response = ['status' => 'not_found', 'message' => 'Product was not in your wishlist.'];
    }

    $stmt->close();
}

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($response['status'] === 'success') {
    $_SESSION['success'] = $response['message'];
} else {
    $_SESSION['error'] = $response['message'];
}
header("Location: ../views/wishlist.php");
exit;
?>
