<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

$stmt = $conn->prepare(
    "SELECT m.item_id, m.name, m.description, m.price, m.calories, m.is_special
     FROM item_favorites f
     JOIN menu_items m ON f.item_id = m.item_id
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC"
);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusEats - Favorites</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>My Favorites</h1>
        <a href="index.php" class="btn btn-outline-secondary">&larr; Back to menu</a>
    </div>

    <div class="row" id="favGrid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($item = $result->fetch_assoc()): ?>
                <div class="col-md-4 mb-4" id="fav-card-<?php echo $item['item_id']; ?>">
                    <div class="card h-100 position-relative">
                        <?php if ($item['is_special']): ?>
                            <span class="badge bg-danger position-absolute m-2">Special</span>
                        <?php endif; ?>

                        <button type="button"
                                class="btn btn-sm position-absolute top-0 end-0 m-2 bg-white rounded-circle shadow-sm text-danger"
                                style="width:36px;height:36px;"
                                onclick="removeFavorite(<?php echo (int)$item['item_id']; ?>)"
                                aria-label="Remove favorite">
                            <i class="bi bi-heart-fill"></i>
                        </button>

                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($item['description']); ?></p>
                            <p class="mb-1"><strong>Price:</strong> ৳<?php echo number_format($item['price'], 2); ?></p>
                            <p class="mb-3 text-muted"><?php echo (int)$item['calories']; ?> kcal</p>
                            <form method="POST" action="addcart.php">
                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                <button type="submit" class="btn btn-primary">Add to cart</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-muted">No favorites yet — tap the heart on any dish in the menu to save it here.</p>
        <?php endif; ?>
    </div>

</div>

<?php $active = 'favorites'; require 'footer_nav.php'; ?>

<script>
function removeFavorite(itemId) {
    fetch('toggle_favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'item_id=' + encodeURIComponent(itemId)
    })
    .then(res => res.json())
    .then(data => {
        if (!data.favorited) {
            const card = document.getElementById('fav-card-' + itemId);
            if (card) card.remove();
        }
    })
    .catch(err => console.error('Remove favorite failed:', err));
}
</script>
</body>
</html>
<?php $conn->close(); ?>