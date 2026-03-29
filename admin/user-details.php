<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) {
    header('Location: users.php');
    exit;
}

// Get user details
$stmt = $pdo->prepare("SELECT * FROM site_users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// Get user orders
$stmt = $pdo->prepare("
    SELECT 
        id,
        order_number,
        billing_name,
        billing_email,
        billing_phone,
        subtotal,
        tax_amount,
        total_amount,
        payment_mode,
        status,
        has_unpriced_items,
        price_confirmed,
        created_at
    FROM orders 
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

// Get order statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_spent,
        COALESCE(AVG(total_amount), 0) as avg_order_value
    FROM orders 
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$orderStats = $stmt->fetch();

$pageTitle = 'User Details - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <div>
            <a href="users.php" style="color: #3498db; text-decoration: none; font-size: 14px;">← Back to Users</a>
            <h1 style="margin-top: 10px;">User Details</h1>
        </div>
        <span class="status-pill <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
        </span>
    </div>

    <!-- User Information -->
    <div class="dashboard-section" style="margin-bottom: 30px;">
        <h2>User Information</h2>
        <div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="info-item">
                <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; display: block; margin-bottom: 5px;">User ID</label>
                <div style="font-size: 16px;"><?php echo $user['id']; ?></div>
            </div>
            <div class="info-item">
                <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; display: block; margin-bottom: 5px;">Full Name</label>
                <div style="font-size: 16px;"><?php echo htmlspecialchars($user['full_name'] ?: 'N/A'); ?></div>
            </div>
            <div class="info-item">
                <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; display: block; margin-bottom: 5px;">Email</label>
                <div style="font-size: 16px;"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            <div class="info-item">
                <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; display: block; margin-bottom: 5px;">Mobile</label>
                <div style="font-size: 16px;"><?php echo htmlspecialchars($user['mobile'] ?: 'N/A'); ?></div>
            </div>
            <div class="info-item">
                <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; display: block; margin-bottom: 5px;">Registered On</label>
                <div style="font-size: 16px;"><?php echo date('d M Y, h:i A', strtotime($user['created_at'])); ?></div>
            </div>
            <div class="info-item">
                <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; display: block; margin-bottom: 5px;">Account Status</label>
                <div style="font-size: 16px;">
                    <span class="status-pill <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Statistics -->
    <div class="stats-grid" style="margin-bottom: 30px;">
        <div class="stat-card">
            <div class="stat-value"><?php echo $orderStats['total_orders']; ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">₹<?php echo number_format($orderStats['total_spent'], 2); ?></div>
            <div class="stat-label">Total Spent</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">₹<?php echo number_format($orderStats['avg_order_value'], 2); ?></div>
            <div class="stat-label">Average Order Value</div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="dashboard-section">
        <h2>Order History</h2>
        <?php if (empty($orders)): ?>
        <div class="activity-card"><p>No orders placed yet.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                            <?php if ($order['has_unpriced_items']): ?>
                            <br><span class="status-pill" style="background: #f39c12; font-size: 11px;">Unpriced Items</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($order['billing_name']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($order['billing_email']); ?><br>
                            <small><?php echo htmlspecialchars($order['billing_phone']); ?></small>
                        </td>
                        <td>
                            <?php if ($order['has_unpriced_items'] && !$order['price_confirmed']): ?>
                                <span style="color: #f39c12;">Pending</span>
                            <?php else: ?>
                                ₹<?php echo number_format($order['total_amount'], 2); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $order['payment_mode'])); ?></td>
                        <td>
                            <?php
                            $statusColors = [
                                'pending' => 'background: #f39c12;',
                                'confirmed' => 'background: #3498db;',
                                'processing' => 'background: #9b59b6;',
                                'shipped' => 'background: #1abc9c;',
                                'delivered' => 'background: #27ae60;',
                                'cancelled' => 'background: #e74c3c;'
                            ];
                            $statusColor = $statusColors[$order['status']] ?? '';
                            ?>
                            <span class="status-pill" style="<?php echo $statusColor; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="orders.php?id=<?php echo $order['id']; ?>" class="btn-sm btn-primary">View</a>
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
