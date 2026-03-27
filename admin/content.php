<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

// Get counts for each content type
$slidesCount = $pdo->query("SELECT COUNT(*) FROM hero_slides")->fetchColumn();
$servicesCount = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
$clientsCount = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$brandsCount = $pdo->query("SELECT COUNT(*) FROM brands")->fetchColumn();
$ctaCount = $pdo->query("SELECT COUNT(*) FROM cta_banners")->fetchColumn();
$faqsCount = $pdo->query("SELECT COUNT(*) FROM faqs")->fetchColumn();

$pageTitle = 'Content Management - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Content Management</h1>
    </div>

    <div class="dashboard-section">
        <h2>Manage Website Content</h2>
        
        <div class="content-grid">
            <a href="slider.php" class="content-card">
                <div class="content-icon hero-slider">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect>
                        <line x1="7" y1="2" x2="7" y2="22"></line>
                        <line x1="17" y1="2" x2="17" y2="22"></line>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                    </svg>
                </div>
                <div class="content-info">
                    <h3>Hero Slider</h3>
                    <p>Homepage slider images</p>
                </div>
                <span class="content-badge"><?php echo $slidesCount; ?></span>
            </a>

            <a href="services.php" class="content-card">
                <div class="content-icon services">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                </div>
                <div class="content-info">
                    <h3>Services</h3>
                    <p>Service cards</p>
                </div>
                <span class="content-badge"><?php echo $servicesCount; ?></span>
            </a>

            <a href="clients.php" class="content-card">
                <div class="content-icon customers">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div class="content-info">
                    <h3>Customers</h3>
                    <p>Customer logos</p>
                </div>
                <span class="content-badge"><?php echo $clientsCount; ?></span>
            </a>

            <a href="brands.php" class="content-card">
                <div class="content-icon brands">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="7"></circle>
                        <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                    </svg>
                </div>
                <div class="content-info">
                    <h3>Brands</h3>
                    <p>Brand partnerships</p>
                </div>
                <span class="content-badge"><?php echo $brandsCount; ?></span>
            </a>

            <a href="cta-banner.php" class="content-card">
                <div class="content-icon cta">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect>
                        <polyline points="17 2 12 7 7 2"></polyline>
                    </svg>
                </div>
                <div class="content-info">
                    <h3>CTA Banner</h3>
                    <p>Call-to-action banner</p>
                </div>
                <span class="content-badge"><?php echo $ctaCount; ?></span>
            </a>

            <a href="faqs.php" class="content-card">
                <div class="content-icon faqs">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                </div>
                <div class="content-info">
                    <h3>FAQs</h3>
                    <p>Frequently asked questions</p>
                </div>
                <span class="content-badge"><?php echo $faqsCount; ?></span>
            </a>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
