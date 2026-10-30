<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: cart.php");
    exit;
}

// Recalculate totals server-side (never trust values from the browser)
$ids = implode(',', array_map('intval', array_keys($cart)));
$sql = "SELECT item_id, name, price, calories FROM menu_items WHERE item_id IN ($ids)";
$result = $conn->query($sql);

$cart_details = [];
$total_price = 0;
$total_calories = 0;

while ($row = $result->fetch_assoc()) {
    $qty = $cart[$row['item_id']];
    $row['quantity'] = $qty;
    $total_price += $row['price'] * $qty;
    $total_calories += $row['calories'] * $qty;
    $cart_details[] = $row;
}

// Wow factor: check against this student's daily budget/calorie goal
$stmt = $conn->prepare("SELECT daily_budget, daily_calorie_goal FROM users WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$limits = $stmt->get_result()->fetch_assoc();

$over_budget = $limits['daily_budget'] !== null && $total_price > $limits['daily_budget'];
$over_calories = $limits['daily_calorie_goal'] !== null && $total_calories > $limits['daily_calorie_goal'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusEats - Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4" style="max-width: 500px;">
    <h1 class="mb-4">Checkout</h1>
    <a href="cart.php" class="btn btn-outline-secondary mb-4">&larr; Back to cart</a>

    <ul class="list-group mb-3">
        <?php foreach ($cart_details as $item): ?>
        <li class="list-group-item d-flex justify-content-between">
            <span><?php echo htmlspecialchars($item['name']); ?> x<?php echo $item['quantity']; ?></span>
            <span>৳<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
        </li>
        <?php endforeach; ?>
    </ul>

    <p><strong>Total:</strong> ৳<?php echo number_format($total_price, 2); ?> &nbsp; | &nbsp; <?php echo (int)$total_calories; ?> kcal</p>

    <?php if ($over_budget || $over_calories): ?>
        <div class="alert alert-danger">
            <strong>You're over your daily limit.</strong>
            <?php if ($over_budget): ?><br>Budget: ৳<?php echo number_format($total_price, 2); ?> vs limit ৳<?php echo number_format($limits['daily_budget'], 2); ?><?php endif; ?>
            <?php if ($over_calories): ?><br>Calories: <?php echo (int)$total_calories; ?> vs goal <?php echo (int)$limits['daily_calorie_goal']; ?><?php endif; ?>
            <br>Remove an item to stay within your goal.
        </div>
    <?php else: ?>
        <form method="POST" action="place_order.php">
            <div class="mb-3">
                <label class="form-label">Pickup date</label>
                <input type="date" name="pickup_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Pickup time</label>
                <input type="time" name="pickup_time" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Place order</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
