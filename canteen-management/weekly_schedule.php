<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// The rolling 7-day window: today + next 6 days (not tied to any fixed week start)
$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date('Y-m-d', strtotime("+$i day"));
}
$first_day = $days[0];
$last_day = $days[6];

// Student's calorie goal, for the weekly summary
$goal_stmt = $conn->prepare("SELECT daily_calorie_goal FROM users WHERE user_id = ?");
$goal_stmt->bind_param("i", $user_id);
$goal_stmt->execute();
$daily_calorie_goal = $goal_stmt->get_result()->fetch_assoc()['daily_calorie_goal'];

// Menu items, for the "add to a day" dropdown
$menu_result = $conn->query("SELECT item_id, name, price, calories FROM menu_items WHERE is_available = 1 ORDER BY name ASC");
$menu_items = [];
while ($row = $menu_result->fetch_assoc()) {
    $menu_items[] = $row;
}

// Everything this student has already planned within the 7-day window, grouped by day
$plan_stmt = $conn->prepare(
    "SELECT wpi.plan_item_id, wpi.plan_date, wpi.quantity, mi.item_id, mi.name, mi.price, mi.calories
     FROM weekly_plan_items wpi
     JOIN menu_items mi ON wpi.item_id = mi.item_id
     WHERE wpi.user_id = ? AND wpi.plan_date BETWEEN ? AND ?
     ORDER BY wpi.plan_date ASC, wpi.plan_item_id ASC"
);
$plan_stmt->bind_param("iss", $user_id, $first_day, $last_day);
$plan_stmt->execute();
$plan_result = $plan_stmt->get_result();

$plan_by_day = [];
foreach ($days as $d) {
    $plan_by_day[$d] = [];
}
$week_total_calories = 0;
while ($row = $plan_result->fetch_assoc()) {
    $plan_by_day[$row['plan_date']][] = $row;
    $week_total_calories += $row['calories'] * $row['quantity'];
}

$conn->close();

$week_calorie_goal = $daily_calorie_goal !== null ? $daily_calorie_goal * 7 : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusEats - Weekly Planner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <a href="index.php" class="btn btn-outline-secondary mb-4">&larr; Back to menu</a>
    <h1 class="mb-2">Weekly Planner</h1>
    <p class="text-muted">
        The <strong>Order</strong> button only works on today's card!
    </p>

    <div class="alert <?php echo ($week_calorie_goal !== null && $week_total_calories > $week_calorie_goal) ? 'alert-danger' : 'alert-info'; ?> mb-4">
        <strong>Week total:</strong> <?php echo (int)$week_total_calories; ?> kcal
        <?php if ($week_calorie_goal !== null): ?>
            / <?php echo (int)$week_calorie_goal; ?> kcal goal
            (<?php echo (int)$daily_calorie_goal; ?> kcal/day &times; 7)
        <?php else: ?>
            <span class="text-muted">(set a daily calorie goal on your profile to compare against a weekly target)</span>
        <?php endif; ?>
    </div>

    <div class="row">
        <?php foreach ($days as $d):
            $is_today = ($d === $today);
            $day_items = $plan_by_day[$d];
            $day_calories = 0;
            $day_price = 0;
            foreach ($day_items as $it) {
                $day_calories += $it['calories'] * $it['quantity'];
                $day_price += $it['price'] * $it['quantity'];
            }
        ?>
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card h-100 <?php echo $is_today ? 'border-primary' : ''; ?>">
                    <div class="card-header d-flex justify-content-between align-items-center <?php echo $is_today ? 'bg-primary text-white' : ''; ?>">
                        <span><?php echo date('D, M j', strtotime($d)); ?></span>
                        <?php if ($is_today): ?><span class="badge bg-light text-primary">Today</span><?php endif; ?>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <?php if (empty($day_items)): ?>
                            <p class="text-muted small">Nothing planned yet.</p>
                        <?php else: ?>
                            <ul class="list-unstyled small mb-2">
                                <?php foreach ($day_items as $it): ?>
                                    <li class="d-flex justify-content-between align-items-center mb-1">
                                        <span><?php echo $it['quantity']; ?>x <?php echo htmlspecialchars($it['name']); ?></span>
                                        <form method="POST" action="remove_plan_item.php" class="d-inline">
                                            <input type="hidden" name="plan_item_id" value="<?php echo $it['plan_item_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0">&times;</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="small text-muted mb-3">
                                <?php echo (int)$day_calories; ?> kcal &middot; ৳<?php echo number_format($day_price, 2); ?>
                            </p>
                        <?php endif; ?>

                        <form method="POST" action="add_plan_item.php" class="mb-3">
                            <input type="hidden" name="plan_date" value="<?php echo $d; ?>">
                            <select name="item_id" class="form-select form-select-sm mb-2" required>
                                <option value="">Add item...</option>
                                <?php foreach ($menu_items as $mi): ?>
                                    <option value="<?php echo $mi['item_id']; ?>"><?php echo htmlspecialchars($mi['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="d-flex gap-2">
                                <input type="number" name="quantity" value="1" min="1" class="form-control form-control-sm" style="width:70px;">
                                <button type="submit" class="btn btn-sm btn-outline-secondary flex-grow-1">Select</button>
                            </div>
                        </form>

                        <div class="mt-auto">
                            <form method="POST" action="order_plan_day.php">
                                <input type="hidden" name="plan_date" value="<?php echo $d; ?>">
                                <button type="submit" class="btn btn-success w-100" <?php echo (!$is_today || empty($day_items)) ? 'disabled' : ''; ?>>
                                    Order
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>