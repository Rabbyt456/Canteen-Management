<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// How many orders are currently ahead in the queue
$load_result = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status IN ('pending','preparing')");
$pending_count = (int)$load_result->fetch_assoc()['cnt'];

// Real average prep time per order, from the last 7 days of completed orders.
// Falls back to a sensible default when there isn't enough history yet.
$avg_result = $conn->query(
    "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, ready_at)) AS avg_minutes
     FROM orders
     WHERE ready_at IS NOT NULL
       AND created_at >= NOW() - INTERVAL 7 DAY"
);
$avg_row = $avg_result ? $avg_result->fetch_assoc() : null;
$minutes_per_order = ($avg_row && $avg_row['avg_minutes'] !== null)
    ? (int) round($avg_row['avg_minutes'])
    : 5; // default until there's enough history

$estimated_wait = $pending_count * $minutes_per_order;

// This user's orders, most recent first
$stmt = $conn->prepare(
    "SELECT order_id, pickup_date, pickup_time, status, total_price, created_at
     FROM orders
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$orders = $stmt->get_result();

$status_badges = [
    'pending'   => 'secondary',
    'preparing' => 'warning text-dark',
    'ready'     => 'success',
    'picked_up' => 'dark',
    'cancelled' => 'danger',
];
$status_labels = [
    'pending'   => 'Pending',
    'preparing' => 'Preparing',
    'ready'     => 'Ready for pickup',
    'picked_up' => 'Picked up',
    'cancelled' => 'Cancelled',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusEats - Order Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Order Status</h1>
        <a href="index.php" class="btn btn-outline-secondary">&larr; Back to menu</a>
    </div>

    <?php if ($pending_count > 0): ?>
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="bi bi-clock"></i>
            <span>Estimated wait time right now: <strong>~<?php echo $estimated_wait; ?> minutes</strong>
            (<?php echo $pending_count; ?> order<?php echo $pending_count === 1 ? '' : 's'; ?> currently in progress)</span>
        </div>
    <?php endif; ?>

    <?php if ($orders && $orders->num_rows > 0): ?>
        <?php while ($order = $orders->fetch_assoc()): ?>
            <div class="card mb-3">
                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">Order #<?php echo $order['order_id']; ?></h5>
                        <p class="mb-1 text-muted">
                            Pickup: <?php echo htmlspecialchars($order['pickup_date']); ?>
                            at <?php echo date('g:i A', strtotime($order['pickup_time'])); ?>
                        </p>
                        <p class="mb-0"><strong>৳<?php echo number_format($order['total_price'], 2); ?></strong></p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-<?php echo $status_badges[$order['status']] ?? 'secondary'; ?> mb-2">
                            <?php echo $status_labels[$order['status']] ?? ucfirst($order['status']); ?>
                        </span>
                        <br>
                        <a href="receipt.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-outline-primary">View order</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="text-muted">You haven't placed any orders yet.</p>
    <?php endif; ?>

</div>

<?php $active = 'status'; require 'footer_nav.php'; ?>
</body>
</html>
<?php $conn->close(); ?>