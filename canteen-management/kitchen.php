<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if (!in_array($_SESSION['role'], ['kitchen_staff', 'admin'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result()->fetch_assoc();

if (!$role_result || !in_array($role_result['role'], ['kitchen_staff', 'admin'])) {
    die("Access denied. Kitchen staff only.");
}

// CSRF token for the forms on this page
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// One-time flash message from update_order_status.php
$flash = $_SESSION['kitchen_flash'] ?? null;
unset($_SESSION['kitchen_flash']);

// View + status filters (from the tabs/buttons below)
$view = $_GET['view'] ?? 'today';               // today | all | history
$status_filter = $_GET['status'] ?? 'active';   // active | pending | preparing | ready

$where = [];
$params = [];
$types = '';

if ($view === 'today') {
    $where[] = "o.pickup_date = CURDATE()";
} elseif ($view === 'history') {
    $where[] = "o.status IN ('picked_up', 'cancelled')";
}

if ($view !== 'history') {
    if ($status_filter === 'active') {
        $where[] = "o.status IN ('pending', 'preparing', 'ready')";
    } else {
        $where[] = "o.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
}

$where_sql = $where ? "WHERE " . implode(' AND ', $where) : '';
$order_by = $view === 'history'
    ? "o.created_at DESC LIMIT 50"
    : "FIELD(o.status, 'pending', 'preparing', 'ready', 'picked_up', 'cancelled'), o.pickup_time ASC";

$sql = "SELECT o.order_id, o.pickup_date, o.pickup_time, o.status, o.total_price, o.total_calories, u.name AS student_name
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        $where_sql
        ORDER BY $order_by";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();

$next_status = [
    'pending'   => 'preparing',
    'preparing' => 'ready',
    'ready'     => 'picked_up',
];

$status_label = [
    'pending'    => 'Pending',
    'preparing'  => 'Preparing',
    'ready'      => 'Ready for Pickup',
    'picked_up'  => 'Picked Up',
    'cancelled'  => 'Cancelled',
];

$status_badge = [
    'pending'    => 'warning',
    'preparing'  => 'info',
    'ready'      => 'success',
    'picked_up'  => 'secondary',
    'cancelled'  => 'danger',
];

$pending_count = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="30">
    <title>Kitchen Dashboard - CampusEats</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>
            Kitchen Dashboard
            <?php if ($pending_count > 0): ?>
                <span class="badge bg-warning text-dark"><?= $pending_count ?> pending</span>
            <?php endif; ?>
        </h1>
        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
    </div>

    <p class="text-muted">Page auto-refreshes every 30s</p>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>">
            <?= htmlspecialchars($flash['msg']) ?>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $view === 'today' ? 'active' : '' ?>" href="?view=today">Today</a></li>
        <li class="nav-item"><a class="nav-link <?= $view === 'all' ? 'active' : '' ?>" href="?view=all">All Upcoming</a></li>
        <li class="nav-item"><a class="nav-link <?= $view === 'history' ? 'active' : '' ?>" href="?view=history">History</a></li>
    </ul>

    <?php if ($view !== 'history'): ?>
    <div class="btn-group mb-3">
        <a href="?view=<?= $view ?>&status=active"    class="btn btn-sm btn-outline-secondary <?= $status_filter === 'active' ? 'active' : '' ?>">All Active</a>
        <a href="?view=<?= $view ?>&status=pending"    class="btn btn-sm btn-outline-warning <?= $status_filter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="?view=<?= $view ?>&status=preparing" class="btn btn-sm btn-outline-info <?= $status_filter === 'preparing' ? 'active' : '' ?>">Preparing</a>
        <a href="?view=<?= $view ?>&status=ready"      class="btn btn-sm btn-outline-success <?= $status_filter === 'ready' ? 'active' : '' ?>">Ready</a>
    </div>
    <?php endif; ?>

    <?php if ($orders->num_rows === 0): ?>
        <p class="text-muted">No orders here right now.</p>
    <?php endif; ?>

    <?php while ($order = $orders->fetch_assoc()): ?>
        <?php
            $item_stmt = $conn->prepare(
                "SELECT mi.name, oi.quantity
                 FROM order_items oi
                 JOIN menu_items mi ON oi.item_id = mi.item_id
                 WHERE oi.order_id = ?"
            );
            $item_stmt->bind_param("i", $order['order_id']);
            $item_stmt->execute();
            $items = $item_stmt->get_result();
        ?>
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title mb-1">
                    Order #<?= $order['order_id'] ?> &mdash; <?= htmlspecialchars($order['student_name']) ?>
                    <span class="badge bg-<?= $status_badge[$order['status']] ?>"><?= $status_label[$order['status']] ?></span>
                </h5>
                <p class="mb-1 text-muted">
                    Pickup: <?= $order['pickup_date'] ?> at <?= $order['pickup_time'] ?>
                    &middot; &#2547;<?= number_format($order['total_price'], 2) ?>
                    &middot; <?= $order['total_calories'] ?> cal
                </p>
                <ul class="mb-2">
                    <?php while ($item = $items->fetch_assoc()): ?>
                        <li><?= $item['quantity'] ?>x <?= htmlspecialchars($item['name']) ?></li>
                    <?php endwhile; ?>
                </ul>

                <?php if (isset($next_status[$order['status']])): ?>
                    <form action="update_order_status.php" method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                        <input type="hidden" name="new_status" value="<?= $next_status[$order['status']] ?>">
                        <button type="submit" class="btn btn-primary btn-sm">
                            Mark as <?= $status_label[$next_status[$order['status']]] ?>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (!in_array($order['status'], ['picked_up', 'cancelled'])): ?>
                    <form action="update_order_status.php" method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                        <input type="hidden" name="new_status" value="cancelled">
                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Cancel this order?')">Cancel</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>

</div>
</body>
</html>