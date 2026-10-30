<?php
session_start();

$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

if ($item_id > 0 && isset($_SESSION['cart'][$item_id])) {
    // Decrease quantity
    $_SESSION['cart'][$item_id]--;

    // Remove item if quantity becomes 0
    if ($_SESSION['cart'][$item_id] <= 0) {
        unset($_SESSION['cart'][$item_id]);}
}

header("Location: cart.php");
exit;
?>