<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$item_id = (int)($_POST['item_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if (!$item_id || $rating < 1 || $rating > 5) {
    die("Invalid review.");
}

$stmt = $conn->prepare("INSERT INTO reviews (item_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiis", $item_id, $_SESSION['user_id'], $rating, $comment);
$stmt->execute();
$conn->close();

header("Location: item_reviews.php?item_id=" . $item_id);
exit;