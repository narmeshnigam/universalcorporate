<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $pdo->prepare("UPDATE site_users SET is_active = NOT is_active WHERE id = ?")->execute([$userId]);
        $message = 'User status updated.';
        $messageType = 'success';
    }

    if ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        // Check if user has orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stmt->execute([$userId]);
        $orderCount = $stmt->fetchColumn();
        
        if ($orderCount > 0) {
            $message = 'Cannot delete user with existing orders.';
            $messageType = 'error';
        } else {
            $pdo->prepare("DELETE FROM site_users WHERE id = ?")->execute([$userId]);
            $message = 'User deleted successfully.';
            $messageType = 'success';
        }
    }
}

// Get filter and search parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';

// Build query
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(full_name LIKE ? OR email LIKE ? OR mobile LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($statusFilter === 'active') {
    $whereConditions[] = "is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $whereConditions[] = "is_active = 0";
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get users with order count
$query = "
    SELECT 
        u.id,
        u.email,
        u.full_name,
        u.mobile,
        u.is_active,
        u.created_at,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.total_amount), 0) as total_spent
    FROM site_users u
    LEFT JOIN orders o ON u.id = o.user_id
    $whereClause
    GROUP BY u.id
    ORDER BY u.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users
    FROM site_users
")->fetch();

$pageTitle = 'Site Users - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Site Users</h1>
        <span class="badge"><?php echo $stats['total_users']; ?> Total Users</span>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid" style="margin-bottom: 30px;">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_users']; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['active_users']; ?></div>
            <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['inactive_users']; ?></div>
            <div class="stat-label">Inactive Users</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="users-filter-form" style="margin-bottom: 20px;">
        <div class="filter-row">
            <div class="filter-field filter-search">
                <label>Search</label>
                <input type="text" name="search" placeholder="Search by name, email, or mobile" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-field">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Users</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter">Filter</button>
                <?php if ($search || $statusFilter !== 'all'): ?>
                <a href="users.php" class="btn-clear">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <!-- Users Table -->
    <div class="dashboard-section">
        <h2>User List</h2>
        <?php if (empty($users)): ?>
        <div class="activity-card"><p>No users found.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['full_name'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['mobile'] ?: 'N/A'); ?></td>
                        <td><?php echo $user['order_count']; ?></td>
                        <td>₹<?php echo number_format($user['total_spent'], 2); ?></td>
                        <td>
                            <span class="status-pill <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="user-details.php?id=<?php echo $user['id']; ?>" class="btn-sm btn-primary">View</a>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn-sm <?php echo $user['is_active'] ? 'btn-warn' : 'btn-activate'; ?>">
                                        <?php echo $user['is_active'] ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </form>
                                <?php if ($user['order_count'] == 0): ?>
                                <form method="POST" style="display: inline-block;" onsubmit="return confirm('Delete this user? This action cannot be undone.')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn-sm btn-danger">Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
