<?php
session_start();
require 'config.php';

$cart = $_SESSION['cart'] ?? [];
$cart_details = [];
$total_price = 0;
$total_calories = 0;

if (!empty($cart)) {
    // Build a comma-separated list of item_ids to fetch in one query
    $ids = implode(',', array_map('intval', array_keys($cart)));
    $sql = "SELECT item_id, name, price, calories FROM menu_items WHERE item_id IN ($ids)";
    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {
        $qty = $cart[$row['item_id']];
        $row['quantity'] = $qty;
        $row['line_total'] = $row['price'] * $qty;
        $total_price += $row['line_total'];
        $total_calories += $row['calories'] * $qty;
        $cart_details[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusEats - Cart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">

    <h1 class="mb-4">Your cart</h1>
    <a href="index.php" class="btn btn-outline-secondary mb-4">&larr; Back to menu</a>

    <?php if (empty($cart_details)): ?>
        <p>Your cart is empty. Go add something tasty.</p>
    <?php else: ?>
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Line total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart_details as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo (int)$item['quantity']; ?></td>
                    <td>৳<?php echo number_format($item['price'], 2); ?></td>
                    <td>৳<?php echo number_format($item['line_total'], 2); ?></td>
                    <td>
                        <form method="POST" action="removecart.php">
                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                            <button class="btn btn-sm btn-outline-danger">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-4">
            <p><strong>Total price:</strong> ৳<?php echo number_format($total_price, 2); ?></p>
            <p><strong>Total calories:</strong> <?php echo (int)$total_calories; ?> kcal</p>
        </div>

        <a href="checkout.php" class="btn btn-success">Proceed to checkout</a>
    <?php endif; ?>

</div>

<?php $active = 'cart'; require 'footer_nav.php'; ?>
</body>
</html>