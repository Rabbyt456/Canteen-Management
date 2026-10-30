<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$item_id = (int)($_GET['item_id'] ?? 0);

$item_stmt = $conn->prepare("SELECT name FROM menu_items WHERE item_id = ?");
$item_stmt->bind_param("i", $item_id);
$item_stmt->execute();
$item = $item_stmt->get_result()->fetch_assoc();

if (!$item) {
    die("Item not found.");
}

// Average + count for the header
$avg_stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE item_id = ?");
$avg_stmt->bind_param("i", $item_id);
$avg_stmt->execute();
$avg = $avg_stmt->get_result()->fetch_assoc();

// All reviews for this item, newest first
$review_stmt = $conn->prepare(
    "SELECT r.rating, r.comment, r.created_at, u.name AS reviewer_name
     FROM reviews r JOIN users u ON r.user_id = u.user_id
     WHERE r.item_id = ?
     ORDER BY r.created_at DESC"
);
$review_stmt->bind_param("i", $item_id);
$review_stmt->execute();
$reviews = $review_stmt->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusEats - Reviews for <?php echo htmlspecialchars($item['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-4" style="max-width: 600px;">
    <a href="index.php" class="btn btn-outline-secondary mb-4">&larr; Back to menu</a>
    <h1><?php echo htmlspecialchars($item['name']); ?></h1>

    <?php if ($avg['review_count'] > 0): ?>
        <p><i class="bi bi-star-fill text-warning"></i> <?php echo round($avg['avg_rating'], 1); ?>
            (<?php echo (int)$avg['review_count']; ?> review<?php echo $avg['review_count'] == 1 ? '' : 's'; ?>)</p>
    <?php else: ?>
        <p class="text-muted">No reviews yet — be the first!</p>
    <?php endif; ?>

    <hr>
    <h5>Leave a review</h5>
    <form method="POST" action="submit_review.php" class="mb-4">
        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
        <div class="mb-2">
            <label class="form-label">Rating</label>
            <select name="rating" class="form-select" required>
                <option value="">Select a rating</option>
                <option value="5">5 - Excellent</option>
                <option value="4">4 - Good</option>
                <option value="3">3 - Okay</option>
                <option value="2">2 - Poor</option>
                <option value="1">1 - Bad</option>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label">Comment (optional)</label>
            <textarea name="comment" class="form-control" rows="2" maxlength="500"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Submit review</button>
    </form>

    <hr>
    <h5>All reviews</h5>
    <?php if ($reviews->num_rows === 0): ?>
        <p class="text-muted">Nothing here yet.</p>
    <?php endif; ?>
    <?php while ($r = $reviews->fetch_assoc()): ?>
        <div class="border-bottom pb-2 mb-2">
            <strong><?php echo htmlspecialchars($r['reviewer_name']); ?></strong>
            &nbsp;<i class="bi bi-star-fill text-warning"></i> <?php echo (int)$r['rating']; ?>/5
            <br>
            <?php if (!empty($r['comment'])): ?>
                <span><?php echo htmlspecialchars($r['comment']); ?></span><br>
            <?php endif; ?>
            <small class="text-muted"><?php echo date('M j, Y', strtotime($r['created_at'])); ?></small>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>