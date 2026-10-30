<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$order_id = (int)($_POST['order_id'] ?? $_GET['order_id'] ?? 0);

// Make sure this order actually belongs to the logged-in student
$check_stmt = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ?");
$check_stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows === 0) {
    die("Order not found.");
}

$items_stmt = $conn->prepare("SELECT item_id, quantity FROM order_items WHERE order_id = ?");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

while ($row = $items_result->fetch_assoc()) {
    $item_id = $row['item_id'];
    $qty = $row['quantity'];
    if (isset($_SESSION['cart'][$item_id])) {
        $_SESSION['cart'][$item_id] += $qty;
    } else {
        $_SESSION['cart'][$item_id] = $qty;
    }
}

$conn->close();
header("Location: cart.php");
exit;