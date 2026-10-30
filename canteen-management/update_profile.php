<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$department = trim($_POST['department'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

// Department is required (name is fixed at registration and isn't editable here)
if ($department === '') {
    header("Location: profile.php?error=" . urlencode("Department is required."));
    exit;
}

// Email is optional to *change*, but the column itself can't be blank (NOT NULL + UNIQUE) -
// so an empty submission just means "leave it as-is" rather than wiping it out.
if ($email !== '') {
    $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $check_stmt->bind_param("si", $email, $user_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        header("Location: profile.php?error=" . urlencode("That email is already in use by another account."));
        exit;
    }
} else {
    // Keep the current email unchanged
    $current_stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $current_stmt->bind_param("i", $user_id);
    $current_stmt->execute();
    $email = $current_stmt->get_result()->fetch_assoc()['email'];
}

$phone = ($phone === '') ? null : $phone;

// Empty field = no limit (store as NULL), otherwise store the number
$budget = ($_POST['daily_budget'] === '') ? null : (float)$_POST['daily_budget'];
$calories = ($_POST['daily_calorie_goal'] === '') ? null : (int)$_POST['daily_calorie_goal'];

$stmt = $conn->prepare(
    "UPDATE users SET department = ?, email = ?, phone = ?, daily_budget = ?, daily_calorie_goal = ?
     WHERE user_id = ?"
);
$stmt->bind_param("sssdii", $department, $email, $phone, $budget, $calories, $user_id);
$stmt->execute();
$conn->close();

header("Location: profile.php?saved=1");
exit;
?>