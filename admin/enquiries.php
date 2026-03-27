<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Manage Enquiries';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $enquiryId = (int)$_POST['enquiry_id'];
    $newStatus = $_POST['status'];
    $pdo = getDatabaseConnection();
    if ($pdo) {
        $stmt = $pdo->prepare("UPDATE enquiries SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $enquiryId]);
        $successMessage = "Status updated successfully!";
    }
}

$pdo = getDatabaseConnection();
$enquiries = [];
$errorMessage = null;

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM enquiries ORDER BY submitted_at DESC");
        $enquiries = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errorMessage = "Table 'enquiries' does not exist. Please run the database setup script.";
    }
} else {
    $errorMessage = "Database connection failed.";
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Enquiries Management</h1>
        <div class="user-info">
            <span class="badge">Total: <?php echo count($enquiries); ?></span>
        </div>
    </div>

    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success"><?php echo $successMessage; ?></div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert" style="background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px;">
            <?php echo $errorMessage; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-section">
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Message</th>
                        <th>Location</th>
                        <th>Page</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($enquiries)): ?>
                        <tr><td colspan="10" style="text-align:center;padding:30px;">No enquiries yet</td></tr>
                    <?php else: ?>
                        <?php foreach ($enquiries as $enq): ?>
                            <tr>
                                <td><?php echo $enq['id']; ?></td>
                                <td><?php echo htmlspecialchars($enq['name']); ?></td>
                                <td><?php echo htmlspecialchars($enq['email']); ?></td>
                                <td><?php echo htmlspecialchars($enq['phone'] ?: '-'); ?></td>
                                <td title="<?php echo htmlspecialchars($enq['message']); ?>">
                                    <?php echo htmlspecialchars(substr($enq['message'], 0, 50)); ?>
                                </td>
                                <td><?php echo htmlspecialchars(($enq['city'] ?: '-') . ', ' . ($enq['country'] ?: '-')); ?></td>
                                <td><?php echo htmlspecialchars($enq['page_name'] ?: '-'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($enq['submitted_at'])); ?></td>
                                <td><span class="status-badge status-<?php echo $enq['status']; ?>"><?php echo ucfirst($enq['status']); ?></span></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="enquiry_id" value="<?php echo $enq['id']; ?>">
                                        <select name="status" onchange="this.form.submit()">
                                            <option value="new" <?php echo $enq['status']==='new'?'selected':''; ?>>New</option>
                                            <option value="read" <?php echo $enq['status']==='read'?'selected':''; ?>>Read</option>
                                            <option value="replied" <?php echo $enq['status']==='replied'?'selected':''; ?>>Replied</option>
                                            <option value="closed" <?php echo $enq['status']==='closed'?'selected':''; ?>>Closed</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
