<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}

$order_id = (int)($_GET['order_id'] ?? 0);

$stmt = $conn->prepare(
    "SELECT o.*, u.name AS student_name, u.email AS student_email
     FROM orders o
     JOIN users u ON o.user_id = u.user_id
     WHERE o.order_id = ?"
);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found.");
}

$item_stmt = $conn->prepare(
    "SELECT m.name, oi.quantity, oi.price_at_order
     FROM order_items oi JOIN menu_items m ON oi.item_id = m.item_id
     WHERE oi.order_id = ?"
);
$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$items = $item_stmt->get_result();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusEats - Order #<?php echo $order['order_id']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4" style="max-width: 560px;">
    <a href="admin_orders.php" class="btn btn-outline-secondary mb-4">&larr; Back to orders</a>
    <h1>Order #<?php echo $order['order_id']; ?></h1>

    <p><strong>Student:</strong> <?php echo htmlspecialchars($order['student_name']); ?>
        (<?php echo htmlspecialchars($order['student_email']); ?>)</p>
    <p><strong>Pickup:</strong> <?php echo htmlspecialchars($order['pickup_date']); ?>
        at <?php echo date('g:i A', strtotime($order['pickup_time'])); ?></p>
    <p><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></p>
    <p><strong>Placed:</strong> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>

    <ul class="list-group mb-3">
        <?php while ($item = $items->fetch_assoc()): ?>
        <li class="list-group-item d-flex justify-content-between">
            <span><?php echo htmlspecialchars($item['name']); ?> x<?php echo $item['quantity']; ?></span>
            <span>৳<?php echo number_format($item['price_at_order'] * $item['quantity'], 2); ?></span>
        </li>
        <?php endwhile; ?>
    </ul>
    <p><strong>Total:</strong> ৳<?php echo number_format($order['total_price'], 2); ?></p>
</div>
</body>
</html>