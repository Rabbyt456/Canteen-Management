<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}

// ---------- Date range filter (applies to summary + best sellers + peak hours) ----------
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$has_range  = ($start_date !== '' && $end_date !== '');

// Small helper so we don't repeat the "bind params only if filtering" logic 3 times
function run_report_query($conn, $base_sql, $has_range, $start_date, $end_date) {
    $sql = $base_sql . ($has_range ? " AND DATE(o.created_at) BETWEEN ? AND ? " : " ");
    // (base_sql already ends in a WHERE/GROUP BY-safe spot, see call sites)
    $stmt = $conn->prepare($sql);
    if ($has_range) {
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Summary stats
$summary_sql = "SELECT COUNT(*) AS total_orders, COALESCE(SUM(total_price), 0) AS total_revenue
                FROM orders o WHERE 1=1";
$summary = run_report_query($conn, $summary_sql, $has_range, $start_date, $end_date)->fetch_assoc();

// Best-selling items
$best_sellers_sql = "SELECT m.name, SUM(oi.quantity) AS total_sold, SUM(oi.quantity * oi.price_at_order) AS revenue
     FROM order_items oi
     JOIN menu_items m ON oi.item_id = m.item_id
     JOIN orders o ON oi.order_id = o.order_id
     WHERE 1=1";
$best_sellers_sql .= $has_range ? " AND DATE(o.created_at) BETWEEN ? AND ? " : " ";
$best_sellers_sql .= " GROUP BY m.item_id ORDER BY total_sold DESC LIMIT 10";
$stmt = $conn->prepare($best_sellers_sql);
if ($has_range) { $stmt->bind_param("ss", $start_date, $end_date); }
$stmt->execute();
$best_sellers = $stmt->get_result();

// Peak order hours
$peak_times_sql = "SELECT HOUR(o.pickup_time) AS hour_slot, COUNT(*) AS order_count
     FROM orders o WHERE 1=1";
$peak_times_sql .= $has_range ? " AND DATE(o.created_at) BETWEEN ? AND ? " : " ";
$peak_times_sql .= " GROUP BY hour_slot ORDER BY order_count DESC";
$stmt = $conn->prepare($peak_times_sql);
if ($has_range) { $stmt->bind_param("ss", $start_date, $end_date); }
$stmt->execute();
$peak_times = $stmt->get_result();

// ---------- Kitchen load overview (always "right now", not affected by date filter) ----------
$load_result = $conn->query(
    "SELECT status, COUNT(*) AS cnt FROM orders WHERE status IN ('pending','preparing','ready') GROUP BY status"
);
$load = ['pending' => 0, 'preparing' => 0, 'ready' => 0];
while ($row = $load_result->fetch_assoc()) {
    $load[$row['status']] = (int)$row['cnt'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusEats - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <a href="logout.php" class="btn btn-outline-secondary">&larr; log out</a>
        <a href="admin_orders.php" class="btn btn-primary">Manage Orders &rarr;</a>
    </div>

    <h1 class="mb-4">Admin Dashboard</h1>

    <!-- Kitchen load overview: what's happening right now -->
    <h5 class="mb-2">Kitchen load right now</h5>
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-secondary">
                <div class="card-body">
                    <h6 class="text-muted">Pending</h6>
                    <h3><?php echo $load['pending']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning">
                <div class="card-body">
                    <h6 class="text-muted">Preparing</h6>
                    <h3><?php echo $load['preparing']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body">
                    <h6 class="text-muted">Ready for pickup</h6>
                    <h3><?php echo $load['ready']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Date range filter -->
    <form method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-auto">
            <label class="form-label mb-0">From</label>
            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div class="col-auto">
            <label class="form-label mb-0">To</label>
            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary">Reset (all-time)</a>
        </div>
    </form>
    <?php if ($has_range): ?>
        <p class="text-muted">Showing stats from <strong><?php echo htmlspecialchars($start_date); ?></strong>
            to <strong><?php echo htmlspecialchars($end_date); ?></strong> (based on when orders were placed).</p>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card"><div class="card-body">
                <h6 class="text-muted">Total orders</h6>
                <h3><?php echo (int)$summary['total_orders']; ?></h3>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card"><div class="card-body">
                <h6 class="text-muted">Total revenue</h6>
                <h3>৳<?php echo number_format($summary['total_revenue'], 2); ?></h3>
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <h4>Best-selling items</h4>
            <table class="table">
                <thead><tr><th>Item</th><th>Qty sold</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php if ($best_sellers->num_rows === 0): ?>
                    <tr><td colspan="3" class="text-muted">No orders in this range.</td></tr>
                <?php endif; ?>
                <?php while ($row = $best_sellers->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo (int)$row['total_sold']; ?></td>
                        <td>৳<?php echo number_format($row['revenue'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="col-md-6">
            <h4>Peak order hours</h4>
            <table class="table">
                <thead><tr><th>Hour</th><th>Orders</th></tr></thead>
                <tbody>
                <?php if ($peak_times->num_rows === 0): ?>
                    <tr><td colspan="2" class="text-muted">No orders in this range.</td></tr>
                <?php endif; ?>
                <?php while ($row = $peak_times->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo sprintf('%02d:00', $row['hour_slot']); ?></td>
                        <td><?php echo (int)$row['order_count']; ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>