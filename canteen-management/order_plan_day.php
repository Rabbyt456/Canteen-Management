<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$plan_date = $_POST['plan_date'] ?? '';
$today = date('Y-m-d');

// Server-side guard: the Order button is only meant to work for today,
// regardless of what the disabled attribute on the button did client-side.
if ($plan_date !== $today) {
    die("You can only order today's plan.");
}

$stmt = $conn->prepare("SELECT item_id, quantity FROM weekly_plan_items WHERE user_id = ? AND plan_date = ?");
$stmt->bind_param("is", $user_id, $plan_date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Nothing planned for today.");
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

while ($row = $result->fetch_assoc()) {
    $item_id = $row['item_id'];
    $qty = $row['quantity'];
    if (isset($_SESSION['cart'][$item_id])) {
        $_SESSION['cart'][$item_id] += $qty;
    } else {
        $_SESSION['cart'][$item_id] = $qty;
    }
}

$conn->close();
header("Location: cart.php");
exit;