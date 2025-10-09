<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
require_once '../../razorpay-php-master/Razorpay.php';
use Razorpay\Api\Api;

session_start();

header('Content-Type: application/json');

// -------------------------
// 1. Read Razorpay response
// -------------------------
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['razorpay_order_id']) || !isset($input['razorpay_payment_id']) || !isset($input['razorpay_signature'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid payment data']);
    exit;
}

$razorpay_payment_id = $input['razorpay_payment_id'];
$razorpay_order_id   = $input['razorpay_order_id'];
$razorpay_signature  = $input['razorpay_signature'];

// -------------------------
// 2. Razorpay credentials
// -------------------------
$keyId = RAZORPAY_KEY_ID;
$keySecret = RAZORPAY_KEY_SECRET;
$api = new Api($keyId, $keySecret);

// -------------------------
// 3. Verify Razorpay signature
// -------------------------
try {
    $attributes = [
        'razorpay_order_id' => $razorpay_order_id,
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_signature' => $razorpay_signature
    ];
    $api->utility->verifyPaymentSignature($attributes);

    // Fetch payment details
    $payment = $api->payment->fetch($razorpay_payment_id);
    $amount = $payment['amount'] / 100; // convert from paise
    $payment_status = 'paid';

    // -------------------------
    // 4. Start inserting records
    // -------------------------
    $connection->begin_transaction();

    $user_id = $_SESSION['user_id'] ?? null;
    $data = $_SESSION['order_temp_data'] ?? null;

    if (!$data || !$user_id) {
        throw new Exception("Missing session order data.");
    }

    // --- Insert into orders table ---
    $order_uuid = uniqid('ORD_');
    $order_number = 'ORD' . strtoupper(uniqid());
    $currency = 'INR';
    $order_status = 'pending';

    $stmt = $connection->prepare("
        INSERT INTO orders (
            order_number, address_id, user_id, subtotal_amount, discount_amount,
            tax_amount, shipping_amount, total_amount, shipping_address_id, billing_address_id,
            order_status, payment_method, payment_status, currency, order_uuid, paid_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param(
        "siiddddiissssss",
        $order_number,
        $data['address_id'],
        $user_id,
        $data['subtotal'],
        $data['discount'],
        $data['tax'],
        $data['shipping'],
        $data['total'],
        $data['address_id'],
        $data['address_id'],
        $order_status,
        $data['payment_method'],
        $payment_status,
        $currency,
        $order_uuid
    );
    $stmt->execute();
    $order_id = $connection->insert_id;

    // --- Insert order_items ---
    $item_stmt = $connection->prepare("
        INSERT INTO order_items (
            order_id, product_id, product_name, size_id, quantity, price, discount, tax, total
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($data['items'] as $item) {
        $item_stmt->bind_param(
            "iisiidddd",
            $order_id,
            $item['product_id'],
            $item['product_name'],
            $item['size_id'],
            $item['quantity'],
            $item['unit_price'],
            $item['discount'],
            $item['tax'],
            $item['line_total']
        );
        $item_stmt->execute();
    }

    // --- Insert payments ---
    $payment_uuid = uniqid('pay_');
    $provider = 'razorpay';
    $pay_stmt = $connection->prepare("
        INSERT INTO payments (
            order_id, payment_uuid, payment_method, payment_provider, amount, currency, payment_status, transaction_id, paid_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $pay_stmt->bind_param(
        "isssdsss",
        $order_id,
        $payment_uuid,
        $data['payment_method'],
        $provider,
        $data['total'],
        $currency,
        $payment_status,
        $razorpay_payment_id
    );
    $pay_stmt->execute();

    // --- Insert shipments ---
    $shipment_uuid = uniqid('ship_');
    $tracking_number = 'TRK' . strtoupper(uniqid());
    $courier = 'Shiprocket';
    $tracking_url = BASE_URL . "views/track_order.php?tn=" . urlencode($tracking_number);

    $ship_stmt = $connection->prepare("
        INSERT INTO shipments (
            shipment_uuid, order_id, courier_name, tracking_number, tracking_url, delivery_status, estimated_delivery, shipped_at
        ) VALUES (?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 7 DAY), NULL)
    ");
    $ship_stmt->bind_param("sisss", $shipment_uuid, $order_id, $courier, $tracking_number, $tracking_url);
    $ship_stmt->execute();

    // --- Insert order_events ---
    $evt_stmt = $connection->prepare("
        INSERT INTO order_events (order_id, event_type, event_data, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $events = [
        ['order_created', ['user_id' => $user_id, 'order_uuid' => $order_uuid, 'total' => $data['total']]],
        ['payment_success', ['payment_uuid' => $payment_uuid, 'status' => $payment_status]],
        ['shipment_created', ['shipment_id' => $shipment_uuid, 'provider' => $courier]]
    ];
    foreach ($events as $e) {
        $event_type = $e[0];
        $event_data = json_encode($e[1]);
        $evt_stmt->bind_param("iss", $order_id, $event_type, $event_data);
        $evt_stmt->execute();
    }

    // --- Update payment_sessions ---
    $connection->query("UPDATE payment_sessions SET status='paid' WHERE rzp_order_id='" . $connection->real_escape_string($razorpay_order_id) . "'");

    // --- Clear user cart ---
    $cart_ids = array_column($data['items'], 'cart_id');
    if (!empty($cart_ids)) {
        $ids = implode(',', array_map('intval', $cart_ids));
        $connection->query("DELETE FROM cart WHERE cart_id IN ($ids)");
    }

    $connection->commit();

    // Cleanup
    unset($_SESSION['order_temp_data']);

    // Redirect payment.php to order_success.php
    echo json_encode([
        'status' => 'success',
        'order_id' => $order_id,
        'message' => 'Order placed successfully'
    ]);
    exit;

} catch (Exception $e) {
    $connection->rollback();

    // Mark failed attempt
    if (!empty($_SESSION['order_temp_data'])) {
        $data = $_SESSION['order_temp_data'];
        $user_id = $_SESSION['user_id'] ?? null;

        $fail_stmt = $connection->prepare("
            INSERT INTO orders (order_number, address_id, user_id, subtotal_amount, total_amount, payment_status, currency, order_uuid)
            VALUES (?, ?, ?, ?, ?, 'failed', 'INR', ?)
        ");
        $order_number = 'ORD' . strtoupper(uniqid());
        $order_uuid = uniqid('ORD_');
        $fail_stmt->bind_param("siidds", $order_number, $data['address_id'], $user_id, $data['subtotal'], $data['total'], $order_uuid);
        $fail_stmt->execute();
    }

    $connection->query("UPDATE payment_sessions SET status='failed' WHERE rzp_order_id='" . $connection->real_escape_string($razorpay_order_id) . "'");

    // Redirect payment.php to order_failed.php
    echo json_encode([
        'status' => 'error',
        'message' => 'Payment verification failed',
        'error' => $e->getMessage()
    ]);
    exit;
}
?>
