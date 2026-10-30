<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit;
}

$pickup_date = $_POST['pickup_date'];
$pickup_time = $_POST['pickup_time'];
$user_id = $_SESSION['user_id'];

// Recalculate totals server-side again - never trust posted totals
$ids = implode(',', array_map('intval', array_keys($cart)));
$sql = "SELECT item_id, price, calories FROM menu_items WHERE item_id IN ($ids)";
$result = $conn->query($sql);

$items = [];
$total_price = 0;
$total_calories = 0;
while ($row = $result->fetch_assoc()) {
    $qty = $cart[$row['item_id']];
    $items[] = ['item_id' => $row['item_id'], 'qty' => $qty, 'price' => $row['price']];
    $total_price += $row['price'] * $qty;
    $total_calories += $row['calories'] * $qty;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        "INSERT INTO orders (user_id, pickup_date, pickup_time, status, total_price, total_calories)
         VALUES (?, ?, ?, 'pending', ?, ?)"
    );
    $stmt->bind_param("issdi", $user_id, $pickup_date, $pickup_time, $total_price, $total_calories);
    $stmt->execute();
    $order_id = $conn->insert_id;

    $item_stmt = $conn->prepare(
        "INSERT INTO order_items (order_id, item_id, quantity, price_at_order) VALUES (?, ?, ?, ?)"
    );
    foreach ($items as $item) {
        $item_stmt->bind_param("iiid", $order_id, $item['item_id'], $item['qty'], $item['price']);
        $item_stmt->execute();
    }

    $conn->commit();
    unset($_SESSION['cart']); // clear the cart after a successful order

    header("Location: receipt.php?order_id=" . $order_id);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    die("Order failed, please try again.");
}
?>