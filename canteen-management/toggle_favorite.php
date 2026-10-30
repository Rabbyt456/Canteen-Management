<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$item_id = (int)($_POST['item_id'] ?? 0);
if (!$item_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing item_id']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if already favorited
$check = $conn->prepare("SELECT favorite_id FROM item_favorites WHERE user_id = ? AND item_id = ?");
$check->bind_param("ii", $user_id, $item_id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    // Already favorited -> remove it
    $del = $conn->prepare("DELETE FROM item_favorites WHERE favorite_id = ?");
    $del->bind_param("i", $existing['favorite_id']);
    $del->execute();
    echo json_encode(['favorited' => false]);
} else {
    // Not favorited yet -> add it
    $ins = $conn->prepare("INSERT INTO item_favorites (user_id, item_id) VALUES (?, ?)");
    $ins->bind_param("ii", $user_id, $item_id);
    $ins->execute();
    echo json_encode(['favorited' => true]);
}

$conn->close();