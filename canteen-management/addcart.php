<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Make sure a cart array exists in the session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // will look like [ item_id => quantity ]
}

// Get the item_id sent from the "Add to cart" button
$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

if ($item_id > 0) {
    if (isset($_SESSION['cart'][$item_id])) {
        $_SESSION['cart'][$item_id] += 1; // already in cart, bump quantity
    } else {
        $_SESSION['cart'][$item_id] = 1; // new item, quantity 1
    }
}

// Go back to the menu page after adding
header("Location: index.php");
exit;
?>