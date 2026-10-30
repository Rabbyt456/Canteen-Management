<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT name, email, department, phone, profile_picture_url, daily_budget, daily_calorie_goal
     FROM users WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Recent reviews left by this student, shown inline as a preview (no separate page)
$review_stmt = $conn->prepare(
    "SELECT r.rating, r.comment, r.created_at, mi.name AS item_name
     FROM reviews r
     JOIN menu_items mi ON r.item_id = mi.item_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC
     LIMIT 5"
);
$review_stmt->bind_param("i", $user_id);
$review_stmt->execute();
$my_reviews = $review_stmt->get_result();

$conn->close();

// Initials for the placeholder avatar, used until a real photo upload is built
$initials = strtoupper(substr(trim($user['name']), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusEats - Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4" style="max-width: 480px;">
    <a href="index.php" class="btn btn-outline-secondary mb-4">&larr; Back to menu</a>
    <h1 class="mb-4">My profile</h1>

    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Profile saved.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <!-- Placeholder avatar - no upload yet, just reserving the spot/column for later -->
    <div class="d-flex justify-content-center mb-4">
        <?php if (!empty($user['profile_picture_url'])): ?>
            <img src="<?php echo htmlspecialchars($user['profile_picture_url']); ?>" alt="Profile picture"
                 class="rounded-circle" style="width:96px; height:96px; object-fit:cover;">
        <?php else: ?>
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-secondary text-white"
                 style="width:96px; height:96px; font-size:2rem;">
                <?php echo htmlspecialchars($initials); ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="POST" action="update_profile.php">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" disabled>
            <div class="form-text">Set at registration, can't be changed here.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Department <span class="text-danger">*</span></label>
            <input type="text" name="department" class="form-control" required
                   value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Email <span class="text-muted">(optional to change)</span></label>
            <input type="email" name="email" class="form-control"
                   value="<?php echo htmlspecialchars($user['email']); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Phone <span class="text-muted">(optional)</span></label>
            <input type="text" name="phone" class="form-control"
                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
        </div>

        <hr>
        <h5>Daily limits (wow factor guardrail)</h5>
        <p class="text-muted">Leave blank for no limit on that field.</p>

        <div class="mb-3">
            <label class="form-label">Daily budget (৳)</label>
            <input type="number" step="0.01" min="0" name="daily_budget" class="form-control"
                   value="<?php echo htmlspecialchars($user['daily_budget'] ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Daily calorie goal</label>
            <input type="number" min="0" name="daily_calorie_goal" class="form-control"
                   value="<?php echo htmlspecialchars($user['daily_calorie_goal'] ?? ''); ?>">
        </div>
        <button type="submit" class="btn btn-primary w-100">Save</button>
    </form>

    <hr class="mt-4">
    <h5>My reviews</h5>
    <?php if ($my_reviews->num_rows === 0): ?>
        <p class="text-muted small">You haven't reviewed anything yet.</p>
    <?php endif; ?>
    <?php while ($r = $my_reviews->fetch_assoc()): ?>
        <div class="border-bottom pb-2 mb-2 small">
            <strong><?php echo htmlspecialchars($r['item_name']); ?></strong>
            &nbsp;<span class="text-warning">&#9733;</span> <?php echo (int)$r['rating']; ?>/5
            <br>
            <?php if (!empty($r['comment'])): ?>
                <span class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($r['comment'], 0, 80, '...')); ?></span>
                <br>
            <?php endif; ?>
            <span class="text-muted" style="font-size:0.8em;"><?php echo date('M j, Y', strtotime($r['created_at'])); ?></span>
        </div>
    <?php endwhile; ?>
</div>

<?php $active = 'profile'; require 'footer_nav.php'; ?>
</body>
</html>