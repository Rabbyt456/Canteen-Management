<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}

// Helper to bind a dynamic number of params by reference (needed since
// mysqli's bind_param requires references, and we don't know the filter
// combination ahead of time).
function bind_params_dynamic($stmt, $types, $params) {
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

$status_filter = $_GET['status'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$search     = trim($_GET['search'] ?? '');

$conditions = [];
$params = [];
$types = "";

if ($status_filter !== '' && $status_filter !== 'all') {
    $conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if ($start_date !== '' && $end_date !== '') {
    $conditions[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}
if ($search !== '') {
    $conditions[] = "(u.name LIKE ? OR o.order_id = ?)";
    $params[] = "%$search%";
    $params[] = is_numeric($search) ? (int)$search : 0;
    $types .= "si";
}

$where_sql = $conditions ? ("WHERE " . implode(" AND ", $conditions)) : "";

$sql = "SELECT o.order_id, o.pickup_date, o.pickup_time, o.status, o.total_price, o.created_at,
                u.name AS student_name, u.email AS student_email
         FROM orders o
         JOIN users u ON o.user_id = u.user_id
         $where_sql
         ORDER BY o.created_at DESC
         LIMIT 200";

$stmt = $conn->prepare($sql);
if ($types !== "") {
    bind_params_dynamic($stmt, $types, $params);
}
$stmt->execute();
$orders = $stmt->get_result();

$statuses = ['pending', 'preparing', 'ready', 'picked_up', 'cancelled'];
$status_badges = [
    'pending'   => 'secondary',
    'preparing' => 'warning text-dark',
    'ready'     => 'success',
    'picked_up' => 'dark',
    'cancelled' => 'danger',
];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusEats - Manage Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid py-4" style="max-width: 1100px;">
    <a href="admin_dashboard.php" class="btn btn-outline-secondary mb-4">&larr; Back to dashboard</a>
    <h1 class="mb-4">Manage Orders</h1>

    <form method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-auto">
            <label class="form-label mb-0">Status</label>
            <select name="status" class="form-select">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $status_filter === $s ? 'selected' : ''; ?>>
                        <?php echo ucfirst(str_replace('_', ' ', $s)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label mb-0">From</label>
            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div class="col-auto">
            <label class="form-label mb-0">To</label>
            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        <div class="col-auto">
            <label class="form-label mb-0">Search (student name or order #)</label>
            <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="e.g. Jarin or 42">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="admin_orders.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <table class="table align-middle">
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Pickup</th>
                <th>Total</th>
                <th>Placed at</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($orders->num_rows === 0): ?>
                <tr><td colspan="7" class="text-muted">No orders match these filters.</td></tr>
            <?php endif; ?>
            <?php while ($order = $orders->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $order['order_id']; ?></td>
                    <td>
                        <?php echo htmlspecialchars($order['student_name']); ?><br>
                        <small class="text-muted"><?php echo htmlspecialchars($order['student_email']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($order['pickup_date']); ?><br>
                        <small><?php echo date('g:i A', strtotime($order['pickup_time'])); ?></small></td>
                    <td>৳<?php echo number_format($order['total_price'], 2); ?></td>
                    <td><?php echo date('M j, g:i A', strtotime($order['created_at'])); ?></td>
                    <td>
                        <span class="badge bg-<?php echo $status_badges[$order['status']] ?? 'secondary'; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <a href="admin_view_order.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-outline-dark">View</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>