<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
session_start();

$order_id = $_GET['order_id'] ?? null;
$message  = $_GET['msg'] ?? 'Your payment could not be completed.';

// Optional: Try to log the failure if not already recorded
if ($order_id) {
    $stmt = $connection->prepare("SELECT * FROM orders WHERE order_number = ? OR order_uuid = ?");
    $stmt->bind_param("ss", $order_id, $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // If no order exists, insert a failed entry (failsafe)
    if ($result->num_rows === 0 && isset($_SESSION['order_temp_data'])) {
        $data = $_SESSION['order_temp_data'];
        $user_id = $_SESSION['user_id'] ?? 0;
        $order_number = 'ORD' . strtoupper(uniqid());
        $order_uuid = uniqid('ORD_');

        $insert = $connection->prepare("
            INSERT INTO orders (order_number, address_id, user_id, subtotal_amount, total_amount, payment_status, currency, order_uuid)
            VALUES (?, ?, ?, ?, ?, 'failed', 'INR', ?)
        ");
        $insert->bind_param("siidds", $order_number, $data['address_id'], $user_id, $data['subtotal'], $data['total'], $order_uuid);
        $insert->execute();
    }
}

unset($_SESSION['order_temp_data']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Failed - Elite Footwear</title>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9f9f9;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }
    .container {
      background: #fff;
      padding: 40px;
      border-radius: 20px;
      text-align: center;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      max-width: 500px;
      width: 100%;
    }
    .icon {
      font-size: 60px;
      color: #e74c3c;
    }
    h1 {
      color: #333;
      margin-top: 10px;
    }
    p {
      color: #555;
      margin-bottom: 30px;
    }
    a.button {
      display: inline-block;
      text-decoration: none;
      background: #e74c3c;
      color: white;
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: bold;
      transition: 0.3s;
    }
    a.button:hover {
      background: #c0392b;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="icon">❌</div>
    <h1>Payment Failed</h1>
    <p><?= htmlspecialchars($message) ?></p>
    <?php if ($order_id): ?>
      <p><strong>Order ID:</strong> <?= htmlspecialchars($order_id) ?></p>
    <?php endif; ?>
    <a href="../views/checkout.php" class="button">Try Again</a>
    <br><br>
    <a href="../views/index.php" style="color:#555;text-decoration:none;">Back to Home</a>
  </div>
</body>
</html>
