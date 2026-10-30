<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$plan_date = $_POST['plan_date'] ?? '';
$item_id = (int)($_POST['item_id'] ?? 0);
$quantity = max(1, (int)($_POST['quantity'] ?? 1));

// Only allow planning within the same rolling 7-day window the page shows,
// so a stray/edited request can't insert a date arbitrarily far in the future or past.
$today = new DateTime();
$plan_dt = DateTime::createFromFormat('Y-m-d', $plan_date);
if (!$plan_dt) {
    die("Invalid date.");
}
$diff_days = (int)$today->diff($plan_dt)->format('%r%a');
if ($diff_days < 0 || $diff_days > 6 || !$item_id) {
    die("Invalid plan entry.");
}

$stmt = $conn->prepare(
    "INSERT INTO weekly_plan_items (user_id, plan_date, item_id, quantity) VALUES (?, ?, ?, ?)"
);
$stmt->bind_param("isii", $user_id, $plan_date, $item_id, $quantity);
$stmt->execute();
$conn->close();

header("Location: weekly_schedule.php");
exit;