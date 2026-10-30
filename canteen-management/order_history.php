<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $conn->prepare(
    "SELECT o.order_id, o.created_at, o.total_price, o.status,
        GROUP_CONCAT(CONCAT(oi.quantity, 'x ', mi.name) SEPARATOR ', ') AS items_summary
     FROM orders o
     JOIN order_items oi ON o.order_id = oi.order_id
     JOIN menu_items mi ON oi.item_id = mi.item_id
     WHERE o.user_id = ?
     GROUP BY o.order_id
     ORDER BY o.created_at DESC"
);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$orders = $stmt->get_result();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusEats - Order History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4" style="max-width: 700px;">
    <a href="index.php" class="btn btn-outline-secondary mb-4">&larr; Back to menu</a>
    <h1 class="mb-4">Order History</h1>

    <?php if ($orders->num_rows === 0): ?>
        <p class="text-muted">You haven't placed any orders yet.</p>
    <?php endif; ?>

    <?php while ($order = $orders->fetch_assoc()): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <span class="text-muted small"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></span>
                    <span class="badge bg-secondary text-capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $order['status'])); ?></span>
                </div>
                <p class="mb-1 mt-2"><?php echo htmlspecialchars($order['items_summary']); ?></p>
                <p class="mb-2"><strong>৳<?php echo number_format($order['total_price'], 2); ?></strong></p>
                <form method="POST" action="reorder.php">
                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-primary">Reorder</button>
                </form>
            </div>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>