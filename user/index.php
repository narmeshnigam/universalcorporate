<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    die('Database connection failed. Please check your database configuration.');
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user details
$stmt = $pdo->prepare("SELECT email FROM site_users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$pageTitle = 'Dashboard - User Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Dashboard</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($user['email']); ?></span>
        </div>
    </div>

    <div class="dashboard-section">
        <div class="activity-card">
            <p>Welcome to your user dashboard.</p>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
