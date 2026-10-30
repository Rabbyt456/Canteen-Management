<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$plan_item_id = (int)($_POST['plan_item_id'] ?? 0);

$stmt = $conn->prepare("DELETE FROM weekly_plan_items WHERE plan_item_id = ? AND user_id = ?");
$stmt->bind_param("ii", $plan_item_id, $_SESSION['user_id']);
$stmt->execute();
$conn->close();

header("Location: weekly_schedule.php");
exit;