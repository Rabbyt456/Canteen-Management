<?php
// Include this file near the end of <body>, right before </body>.
// Set $active to one of: 'menu' | 'cart' | 'favorites' | 'status' | 'profile'
// in the including page BEFORE requiring this file.
$active = $active ?? '';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
    body { padding-bottom: 76px; }
    .bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #fff;
        border-top: 1px solid #dee2e6;
        display: flex;
        justify-content: space-around;
        padding: 8px 0 6px;
        z-index: 1030;
        box-shadow: 0 -2px 8px rgba(0,0,0,0.05);
    }
    .bottom-nav a {
        display: flex;
        flex-direction: column;
        align-items: center;
        font-size: 0.72rem;
        color: #6c757d;
        text-decoration: none;
        gap: 2px;
        min-width: 60px;
    }
    .bottom-nav a.active { color: #fd7e14; font-weight: 600; }
    .bottom-nav i { font-size: 1.3rem; }
</style>
<nav class="bottom-nav">
    <a href="index.php" class="<?php echo $active === 'menu' ? 'active' : ''; ?>">
        <i class="bi bi-grid-fill"></i> Menu
    </a>
    <a href="cart.php" class="<?php echo $active === 'cart' ? 'active' : ''; ?>">
        <i class="bi bi-cart3"></i> Cart
    </a>
    <a href="favorites.php" class="<?php echo $active === 'favorites' ? 'active' : ''; ?>">
        <i class="bi bi-heart-fill"></i> Favorites
    </a>
    <a href="order_status.php" class="<?php echo $active === 'status' ? 'active' : ''; ?>">
        <i class="bi bi-clock-history"></i> Status
    </a>
    <a href="profile.php" class="<?php echo $active === 'profile' ? 'active' : ''; ?>">
        <i class="bi bi-person-fill"></i> Profile
    </a>
</nav>