<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result()->fetch_assoc();

if (!$role_result || !in_array($role_result['role'], ['kitchen_staff', 'admin'])) {
    die("Access denied.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: kitchen.php");
    exit;
}

// CSRF check
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['kitchen_flash'] = ['type' => 'error', 'msg' => 'Session expired, please try again.'];
    header("Location: kitchen.php");
    exit;
}

$order_id = intval($_POST['order_id'] ?? 0);
$new_status = $_POST['new_status'] ?? '';

$allowed_transitions = [
    'pending'   => ['preparing', 'cancelled'],
    'preparing' => ['ready', 'cancelled'],
    'ready'     => ['picked_up', 'cancelled'],
];

$stmt = $conn->prepare("SELECT status FROM orders WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    $_SESSION['kitchen_flash'] = ['type' => 'error', 'msg' => "Order #$order_id not found."];
    header("Location: kitchen.php");
    exit;
}

$current_status = $order['status'];

if (!isset($allowed_transitions[$current_status]) || !in_array($new_status, $allowed_transitions[$current_status], true)) {
    $_SESSION['kitchen_flash'] = ['type' => 'error', 'msg' => "Can't move order #$order_id from '$current_status' to '$new_status'."];
    header("Location: kitchen.php");
    exit;
}

$timestamp_column = match ($new_status) {
    'preparing' => 'preparing_at',
    'ready'     => 'ready_at',
    'picked_up' => 'picked_up_at',
    default     => null,
};

if ($timestamp_column) {
    $sql = "UPDATE orders SET status = ?, $timestamp_column = NOW() WHERE order_id = ?";
} else {
    $sql = "UPDATE orders SET status = ? WHERE order_id = ?";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $new_status, $order_id);

if ($stmt->execute()) {
    $label = ucfirst(str_replace('_', ' ', $new_status));
    $_SESSION['kitchen_flash'] = ['type' => 'success', 'msg' => "Order #$order_id marked as $label."];
} else {
    $_SESSION['kitchen_flash'] = ['type' => 'error', 'msg' => "Failed to update order #$order_id."];
}

header("Location: kitchen.php");
exit;