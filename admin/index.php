<?php
session_start();
require_once '../config/database.php';

// Get database connection
$pdo = getDatabaseConnection();

if (!$pdo) {
    die('Database connection failed. Please check your database configuration.');
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Get admin details
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

// Get statistics
$enquiriesStmt = $pdo->query("SELECT COUNT(*) FROM enquiries");
$totalEnquiries = $enquiriesStmt->fetchColumn();

$recentEnquiriesStmt = $pdo->query("SELECT COUNT(*) FROM enquiries WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$recentEnquiries = $recentEnquiriesStmt->fetchColumn();

$pageTitle = 'Dashboard - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Dashboard</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($admin['email']); ?></span>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-details">
                <h3>Total Enquiries</h3>
                <p class="stat-number"><?php echo $totalEnquiries; ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-details">
                <h3>This Week</h3>
                <p class="stat-number"><?php echo $recentEnquiries; ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-details">
                <h3>Admin Users</h3>
                <p class="stat-number">1</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-details">
                <h3>System Status</h3>
                <p class="stat-number">Active</p>
            </div>
        </div>
    </div>

    <div class="dashboard-section">
        <h2>Recent Activity</h2>
        <div class="activity-card">
            <p>Welcome to your admin dashboard. You can manage your website from here.</p>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
