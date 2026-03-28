<?php
$currentPage = basename($_SERVER['PHP_SELF']);

// Load site identity for branding
if (!isset($site)) {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/identity.php';
    $sidePdo = getDatabaseConnection();
    $site = getSiteIdentity($sidePdo);
}
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-brand">
            <img src="../<?php echo htmlspecialchars($site['logo_path']); ?>" alt="<?php echo htmlspecialchars($site['site_name']); ?>">
            <div class="sidebar-brand-text">
                <span class="sidebar-brand-name"><?php echo htmlspecialchars($site['site_name']); ?></span>
                <span class="sidebar-brand-sub">User Panel</span>
            </div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>">
            <span class="nav-text">Dashboard</span>
        </a>
        <a href="place-order.php" class="nav-item <?php echo $currentPage == 'place-order.php' ? 'active' : ''; ?>">
            <span class="nav-text">Place Order</span>
        </a>
        <a href="my-orders.php" class="nav-item <?php echo $currentPage == 'my-orders.php' ? 'active' : ''; ?>">
            <span class="nav-text">My Orders</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="change-password.php" class="nav-item <?php echo $currentPage == 'change-password.php' ? 'active' : ''; ?>">
            <span class="nav-text">Change Password</span>
        </a>
        <a href="logout.php" class="nav-item logout-btn">
            <span class="nav-text">Logout</span>
        </a>
    </div>
</aside>
