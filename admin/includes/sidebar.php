<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$contentPages = ['slider.php', 'services.php', 'clients.php', 'brands.php', 'cta-banner.php', 'faqs.php'];
$isContentPage = in_array($currentPage, $contentPages);

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
                <span class="sidebar-brand-sub">Admin Panel</span>
            </div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>">
            <span class="nav-text">Dashboard</span>
        </a>
        <a href="identity.php" class="nav-item <?php echo $currentPage == 'identity.php' ? 'active' : ''; ?>">
            <span class="nav-text">Identity</span>
        </a>
        <a href="content.php" class="nav-item <?php echo $currentPage == 'content.php' || $isContentPage ? 'active' : ''; ?>">
            <span class="nav-text">Content</span>
        </a>
        <a href="products.php" class="nav-item <?php echo $currentPage == 'products.php' ? 'active' : ''; ?>">
            <span class="nav-text">Products</span>
        </a>
        <a href="orders.php" class="nav-item <?php echo $currentPage == 'orders.php' ? 'active' : ''; ?>">
            <span class="nav-text">Orders</span>
        </a>
        <a href="users.php" class="nav-item <?php echo $currentPage == 'users.php' || $currentPage == 'user-details.php' ? 'active' : ''; ?>">
            <span class="nav-text">Site Users</span>
        </a>
        <a href="email-settings.php" class="nav-item <?php echo $currentPage == 'email-settings.php' ? 'active' : ''; ?>">
            <span class="nav-text">Email Settings</span>
        </a>
        <a href="enquiries.php" class="nav-item <?php echo $currentPage == 'enquiries.php' ? 'active' : ''; ?>">
            <span class="nav-text">Enquiries</span>
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
