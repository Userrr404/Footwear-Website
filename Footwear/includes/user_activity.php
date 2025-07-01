<?php
function logUserActivity($user_id, $action_type, $details = null, $success = 1) {
    global $connection;

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    if ($user_id === null) {
        $stmt = $connection->prepare("
            INSERT INTO user_logs (action_type, action_details, ip_address, user_agent, success) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssi", $action_type, $details, $ip, $agent, $success);
    } else {
        $stmt = $connection->prepare("
            INSERT INTO user_logs (user_id, action_type, action_details, ip_address, user_agent, success) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issssi", $user_id, $action_type, $details, $ip, $agent, $success);
    }

    $stmt->execute();
    $stmt->close();
}
?>
