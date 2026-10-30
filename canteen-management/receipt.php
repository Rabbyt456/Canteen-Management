<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$order_id = (int)($_GET['order_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
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
    <title>CampusEats - Receipt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4" style="max-width: 480px;">
    <div class="alert alert-success">Order #<?php echo $order['order_id']; ?> placed!</div>
    <p><strong>Pickup:</strong> <?php echo $order['pickup_date']; ?> at <?php echo $order['pickup_time']; ?></p>
    <ul class="list-group mb-3">
        <?php while ($item = $items->fetch_assoc()): ?>
        <li class="list-group-item d-flex justify-content-between">
            <span><?php echo htmlspecialchars($item['name']); ?> x<?php echo $item['quantity']; ?></span>
            <span>৳<?php echo number_format($item['price_at_order'] * $item['quantity'], 2); ?></span>
        </li>
        <?php endwhile; ?>
    </ul>
    <p><strong>Total:</strong> ৳<?php echo number_format($order['total_price'], 2); ?></p>
    <p><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></p>
    <a href="index.php" class="btn btn-primary w-100">Back to menu</a>
</div>

<?php $active = 'status'; require 'footer_nav.php'; ?>
</body>
</html>