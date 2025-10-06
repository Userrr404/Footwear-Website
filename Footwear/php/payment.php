<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
session_start();

$orderId = $_GET['order_id'] ?? null;
if (!$orderId) die('Invalid request');

$total = $_SESSION['razorpay_amount'] ?? 0;
$keyId = "rzp_test_RJKlS0sGzGVCrp";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment - Elite Footwear</title>
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body>
<script>
var options = {
    "key": "<?= $keyId ?>",
    "amount": <?= intval($total * 100) ?>, // paise (integer)
    "currency": "INR",
    "name": "Elite Footwear",
    "description": "Order Payment",
    "order_id": "<?= htmlspecialchars($orderId, ENT_QUOTES) ?>",
    "handler": function (response){
        // send response to server to verify signature + finalize order
        fetch('verify_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(response)
        }).then(r=>r.json()).then(data=>{
            if(data.status === 'success'){
                window.location.href = "../views/order_success.php?order_id="+encodeURIComponent(data.order_id);
            } else {
                window.location.href = "payment_failed.php";
            }
        }).catch(err=>{
            console.error(err);
            window.location.href = "payment_failed.php";
        });
    },
    "modal": {
        "ondismiss": function() {
            window.location.href = "payment_failed.php?rzp_order_id=<?= urlencode($orderId) ?>";
        }
    }
};
var rzp1 = new Razorpay(options);
rzp1.open();
</script>
</body>
</html>
