<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once '../includes/user_activity.php';
session_start();

// Redirect to login if user not logged in
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
    $stmt = $connection->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();

//     ALTER TABLE wishlist
// ADD CONSTRAINT unique_user_product UNIQUE (user_id, product_id);
// using this query in MySQL database same product with same user cannot be added to wishlist
//    This ensures that a user cannot add the same product to their wishlist multiple times.
    // Check if the insert was successful
    // If the product already exists, it will not insert a new row due to IGNORE

    if ($stmt->affected_rows > 0) {
        logUserActivity($user_id, 'add_to_wishlist', 'Added product ID to wishlist: ' . $product_id);
        $response = ['status' => 'success', 'message' => 'Product added to wishlist.'];
    } else {
        $response = ['status' => 'exists', 'message' => 'Product already in wishlist.'];
    }

    $stmt->close();
}

// If request is AJAX, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Else fallback to regular redirect
if ($response['status'] === 'success') {
    $_SESSION['success'] = $response['message'];
} else {
    $_SESSION['error'] = $response['message'];
}
header("Location: ../views/product_details.php?id=" . $product_id);
exit;
?>
