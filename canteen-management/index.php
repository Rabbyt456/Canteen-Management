<?php
session_start();
require 'config.php';

// Require login before viewing the menu
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// Pull all available menu items from the database
$sql = "SELECT item_id, name, description, price, calories, is_special
        FROM menu_items
        WHERE is_available = 1
        ORDER BY is_special DESC, name ASC";

$result = $conn->query($sql);

// Which items has this user already favorited? (for pre-filled hearts)
$favorited_ids = [];
$fav_stmt = $conn->prepare("SELECT item_id FROM item_favorites WHERE user_id = ?");
$fav_stmt->bind_param("i", $_SESSION['user_id']);
$fav_stmt->execute();
$fav_result = $fav_stmt->get_result();
while ($row = $fav_result->fetch_assoc()) {
    $favorited_ids[] = (int)$row['item_id'];
}

// Average rating + review count per item (single grouped query, no complicated logic)
$ratings = [];
$rating_result = $conn->query(
    "SELECT item_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews GROUP BY item_id"
);
while ($row = $rating_result->fetch_assoc()) {
    $ratings[$row['item_id']] = [
        'avg' => round($row['avg_rating'], 1),
        'count' => (int)$row['review_count'],
    ];
}

// Today's specials, shown in their own highlighted row above the regular grid
$specials_result = $conn->query(
    "SELECT item_id, name, description, price, calories FROM menu_items WHERE is_special = 1 AND is_available = 1"
);

// Last 4 orders, for the "Reorder" bar
$reorder_stmt = $conn->prepare(
    "SELECT o.order_id, o.created_at, o.total_price,
        GROUP_CONCAT(CONCAT(oi.quantity, 'x ', mi.name) SEPARATOR ', ') AS items_summary
     FROM orders o
     JOIN order_items oi ON o.order_id = oi.order_id
     JOIN menu_items mi ON oi.item_id = mi.item_id
     WHERE o.user_id = ?
     GROUP BY o.order_id
     ORDER BY o.created_at DESC
     LIMIT 4"
);
$reorder_stmt->bind_param("i", $_SESSION['user_id']);
$reorder_stmt->execute();
$recent_orders = $reorder_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusEats - Menu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>CampusEats Menu</h1>
        <div>
            <span class="me-3">Hi, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin_dashboard.php" class="btn btn-outline-danger">Admin Dashboard</a>
            <?php endif; ?>
            <a href="profile.php" class="btn btn-outline-dark">Profile</a>
            <a href="weekly_schedule.php" class="btn btn-outline-success">Weekly Planner</a>
            <a href="cart.php" class="btn btn-outline-primary">Cart (<?php echo $cart_count; ?>)</a>
            <a href="logout.php" class="btn btn-outline-secondary">Logout</a>
        </div>
    </div>

    <!-- Reorder bar: last 4 orders, horizontally scrollable, one-tap reorder -->
    <?php if ($recent_orders->num_rows > 0): ?>
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="mb-0">🔁 Reorder</h4>
                <a href="order_history.php" class="small">See all my orders &rarr;</a>
            </div>
            <div class="d-flex gap-3" style="overflow-x: auto; padding-bottom: 8px;">
                <?php while ($order = $recent_orders->fetch_assoc()): ?>
                    <div class="card flex-shrink-0" style="width: 220px;">
                        <div class="card-body">
                            <p class="small text-muted mb-1"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></p>
                            <p class="small mb-2" style="height: 3.2em; overflow: hidden;">
                                <?php echo htmlspecialchars($order['items_summary']); ?>
                            </p>
                            <p class="mb-2"><strong>৳<?php echo number_format($order['total_price'], 2); ?></strong></p>
                            <form method="POST" action="reorder.php">
                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-primary w-100">Reorder</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Today's Specials: highlighted separately from the regular grid -->
    <?php if ($specials_result && $specials_result->num_rows > 0): ?>
        <div class="mb-4 p-3 rounded" style="background:#f5f8ff; border:1px solid #cfe0fc;">
            <h4 class="mb-3">🌟 Today's Specials</h4>
            <div class="row">
                <?php while ($special = $specials_result->fetch_assoc()): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-primary">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($special['name']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($special['description']); ?></p>
                                <p class="mb-1"><strong>Price:</strong> ৳<?php echo number_format($special['price'], 2); ?></p>
                                <p class="mb-3 text-muted"><?php echo (int)$special['calories']; ?> kcal</p>
                                <form method="POST" action="addcart.php">
                                    <input type="hidden" name="item_id" value="<?php echo $special['item_id']; ?>">
                                    <button type="submit" class="btn btn-primary">Add to cart</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Search bar (replaces the old wait-time banner; wait time now lives on the Status page) -->
    <div class="mb-4">
        <div class="input-group input-group-lg">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
            <input type="text" id="menuSearch" class="form-control border-start-0"
                   placeholder="Search for meals, combos...">
        </div>
    </div>

    <div class="row" id="menuGrid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($item = $result->fetch_assoc()): ?>
                <?php $isFav = in_array($item['item_id'], $favorited_ids); ?>
                <div class="col-md-4 mb-4 menu-item-col" data-name="<?php echo htmlspecialchars(strtolower($item['name'])); ?>">
                    <div class="card h-100 position-relative">
                        <?php if ($item['is_special']): ?>
                            <span class="badge bg-danger position-absolute m-2">Special</span>
                        <?php endif; ?>

                        <button type="button"
                                class="btn btn-sm position-absolute top-0 end-0 m-2 bg-white rounded-circle shadow-sm favorite-btn <?php echo $isFav ? 'text-danger' : 'text-secondary'; ?>"
                                style="width:36px;height:36px;"
                                onclick="toggleFavorite(<?php echo (int)$item['item_id']; ?>, this)"
                                aria-label="Toggle favorite">
                            <i class="bi <?php echo $isFav ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                        </button>

                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($item['description']); ?></p>
                            <p class="mb-1"><strong>Price:</strong> ৳<?php echo number_format($item['price'], 2); ?></p>
                            <p class="mb-2 text-muted"><?php echo (int)$item['calories']; ?> kcal</p>

                            <?php if (isset($ratings[$item['item_id']])): ?>
                                <p class="mb-3">
                                    <i class="bi bi-star-fill text-warning"></i>
                                    <?php echo $ratings[$item['item_id']]['avg']; ?>
                                    <a href="item_reviews.php?item_id=<?php echo $item['item_id']; ?>" class="text-muted small">
                                        (<?php echo $ratings[$item['item_id']]['count']; ?> reviews)
                                    </a>
                                </p>
                            <?php else: ?>
                                <p class="mb-3">
                                    <a href="item_reviews.php?item_id=<?php echo $item['item_id']; ?>" class="text-muted small">No reviews yet</a>
                                </p>
                            <?php endif; ?>

                            <form method="POST" action="addcart.php">
                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                <button type="submit" class="btn btn-primary">Add to cart</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No menu items found. Did you import schema.sql and add items?</p>
        <?php endif; ?>
    </div>

    <p id="noResults" class="text-muted d-none">No dishes match your search.</p>

</div>

<?php $active = 'menu'; require 'footer_nav.php'; ?>

<script>
// Live search filter (client-side, no reload)
document.getElementById('menuSearch').addEventListener('input', function () {
    const query = this.value.trim().toLowerCase();
    let visibleCount = 0;
    document.querySelectorAll('.menu-item-col').forEach(function (col) {
        const matches = col.dataset.name.includes(query);
        col.style.display = matches ? '' : 'none';
        if (matches) visibleCount++;
    });
    document.getElementById('noResults').classList.toggle('d-none', visibleCount !== 0);
});

// Favorite heart toggle (AJAX, no reload)
function toggleFavorite(itemId, btn) {
    fetch('toggle_favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'item_id=' + encodeURIComponent(itemId)
    })
    .then(res => res.json())
    .then(data => {
        const icon = btn.querySelector('i');
        if (data.favorited) {
            btn.classList.remove('text-secondary');
            btn.classList.add('text-danger');
            icon.classList.remove('bi-heart');
            icon.classList.add('bi-heart-fill');
        } else {
            btn.classList.remove('text-danger');
            btn.classList.add('text-secondary');
            icon.classList.remove('bi-heart-fill');
            icon.classList.add('bi-heart');
        }
    })
    .catch(err => console.error('Favorite toggle failed:', err));
}
</script>
</body>
</html>
<?php $conn->close(); ?>