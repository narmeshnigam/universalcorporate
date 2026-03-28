<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    die('Database connection failed.');
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Get user's orders
$stmt = $pdo->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id AND unit_price IS NULL) as unpriced_count
    FROM orders o 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

// View order details
$viewOrder = null;
$orderItems = [];
$hasUnpricedItems = false;
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['view'], $userId]);
    $viewOrder = $stmt->fetch();
    
    if ($viewOrder) {
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$viewOrder['id']]);
        $orderItems = $stmt->fetchAll();
        
        // Check if any items have null unit_price
        foreach ($orderItems as $item) {
            if ($item['unit_price'] === null) {
                $hasUnpricedItems = true;
                break;
            }
        }
        // Also check the flag
        $hasUnpricedItems = $hasUnpricedItems || $viewOrder['has_unpriced_items'];
    }
}

$pageTitle = 'My Orders - User Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1><?php echo $viewOrder ? 'Order Details' : 'My Orders'; ?></h1>
        <?php if ($viewOrder): ?>
        <div class="header-actions">
            <a href="my-orders.php" class="btn-back">← Back to Orders</a>
            <?php if (!in_array($viewOrder['status'], ['pending', 'cancelled'])): ?>
            <a href="invoice.php?id=<?php echo $viewOrder['id']; ?>" class="btn-invoice" target="_blank">📄 View Invoice</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($viewOrder): ?>
    <!-- Order Details View -->
    <div class="order-view">
        <div class="order-header-card">
            <div class="order-meta">
                <div><span class="label">Order #</span><span class="value"><?php echo htmlspecialchars($viewOrder['order_number']); ?></span></div>
                <div><span class="label">Date</span><span class="value"><?php echo date('d M Y, h:i A', strtotime($viewOrder['created_at'])); ?></span></div>
                <div>
                    <span class="label">Status</span>
                    <span class="status-badge status-<?php echo $viewOrder['status']; ?>"><?php echo ucfirst($viewOrder['status']); ?></span>
                </div>
            </div>
            <?php if ($hasUnpricedItems): ?>
            <div class="pricing-alert">⚠️ This order has items pending pricing confirmation</div>
            <?php endif; ?>
        </div>

        <div class="details-grid">
            <div class="detail-card">
                <h3>Billing Address</h3>
                <p><strong><?php echo htmlspecialchars($viewOrder['billing_name']); ?></strong></p>
                <p><?php echo htmlspecialchars($viewOrder['billing_address']); ?></p>
                <p><?php echo htmlspecialchars($viewOrder['billing_city'] . ', ' . $viewOrder['billing_state'] . ' - ' . $viewOrder['billing_pincode']); ?></p>
                <p>Phone: <?php echo htmlspecialchars($viewOrder['billing_phone']); ?></p>
                <p>Email: <?php echo htmlspecialchars($viewOrder['billing_email']); ?></p>
            </div>
            <div class="detail-card">
                <h3>Shipping Address</h3>
                <?php if ($viewOrder['shipping_same_as_billing']): ?>
                <p><em>Same as billing</em></p>
                <?php else: ?>
                <p><strong><?php echo htmlspecialchars($viewOrder['shipping_name']); ?></strong></p>
                <p><?php echo htmlspecialchars($viewOrder['shipping_address']); ?></p>
                <p><?php echo htmlspecialchars($viewOrder['shipping_city'] . ', ' . $viewOrder['shipping_state'] . ' - ' . $viewOrder['shipping_pincode']); ?></p>
                <p>Phone: <?php echo htmlspecialchars($viewOrder['shipping_phone']); ?></p>
                <?php endif; ?>
            </div>
            <div class="detail-card">
                <h3>Preferences</h3>
                <p><strong>Preferred Payment:</strong> <?php echo ucwords(str_replace('_', ' ', $viewOrder['payment_mode'])); ?></p>
                <?php if ($viewOrder['preferred_delivery_date']): ?>
                <p><strong>Preferred Delivery:</strong> <?php echo date('d M Y', strtotime($viewOrder['preferred_delivery_date'])); ?></p>
                <?php endif; ?>
                <?php if ($viewOrder['delivery_instructions']): ?>
                <p><strong>Instructions:</strong> <?php echo htmlspecialchars($viewOrder['delivery_instructions']); ?></p>
                <?php endif; ?>
            </div>
            <?php if ($viewOrder['company_name'] || $viewOrder['gstin'] || $viewOrder['pan_number']): ?>
            <div class="detail-card">
                <h3>Tax Information</h3>
                <?php if ($viewOrder['company_name']): ?>
                <p><strong>Company:</strong> <?php echo htmlspecialchars($viewOrder['company_name']); ?></p>
                <?php endif; ?>
                <?php if ($viewOrder['gstin']): ?>
                <p><strong>GSTIN:</strong> <?php echo htmlspecialchars($viewOrder['gstin']); ?></p>
                <?php endif; ?>
                <?php if ($viewOrder['pan_number']): ?>
                <p><strong>PAN:</strong> <?php echo htmlspecialchars($viewOrder['pan_number']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Order Items - Full Width -->
        <div class="items-section">
            <h2>Order Items</h2>
            <div class="table-responsive">
                <table class="items-table">
                    <colgroup>
                        <col><col><col><col><col><col><col><col>
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Unit Price</th>
                            <th>Qty</th>
                            <th>Gross Total</th>
                            <th>Tax %</th>
                            <th>Tax Amount</th>
                            <th>Net Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): 
                            $unitPrice = $item['unit_price'];
                            $taxRate = $item['tax_rate'] ?? 18;
                            $qty = $item['quantity'];
                            $netTotal = $unitPrice !== null ? $unitPrice * $qty : null;
                            $grossTotal = $netTotal !== null ? $netTotal / (1 + ($taxRate / 100)) : null;
                            $taxAmt = $netTotal !== null ? $netTotal - $grossTotal : null;
                        ?>
                        <tr class="<?php echo $item['is_custom_item'] ? 'custom-row' : ''; ?>">
                            <td>
                                <?php echo htmlspecialchars($item['product_name']); ?>
                                <?php if ($item['is_custom_item']): ?><span class="custom-badge">CUSTOM</span><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['product_sku'] ?? '-'); ?></td>
                            <td><?php echo $unitPrice !== null ? '₹' . number_format($unitPrice, 2) : '<span class="tbd">TBD</span>'; ?></td>
                            <td><?php echo $qty; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                            <td><?php echo $grossTotal !== null ? '₹' . number_format($grossTotal, 2) : '<span class="tbd">TBD</span>'; ?></td>
                            <td><?php echo number_format($taxRate, 2); ?>%</td>
                            <td><?php echo $taxAmt !== null ? '₹' . number_format($taxAmt, 2) : '<span class="tbd">TBD</span>'; ?></td>
                            <td><?php echo $netTotal !== null ? '₹' . number_format($netTotal, 2) : '<span class="tbd">TBD</span>'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-right"><strong>Totals:</strong></td>
                            <td><strong>₹<?php echo number_format($viewOrder['subtotal'] ?? 0, 2); ?></strong></td>
                            <td></td>
                            <td><strong>₹<?php echo number_format($viewOrder['tax_amount'] ?? 0, 2); ?></strong></td>
                            <td><strong>₹<?php echo number_format($viewOrder['total_amount'] ?? 0, 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Orders List -->
    <div class="dashboard-section">
        <?php if (empty($orders)): ?>
        <div class="activity-card"><p>You haven't placed any orders yet. <a href="place-order.php">Place your first order</a></p></div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): 
                    $hasUnpriced = $order['has_unpriced_items'] || $order['unpriced_count'] > 0;
                ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($order['order_number']); ?>
                        <?php if ($hasUnpriced): ?><span class="needs-pricing">⚠️</span><?php endif; ?>
                    </td>
                    <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                    <td><?php echo $order['item_count']; ?> item(s)</td>
                    <td>
                        <?php if ($hasUnpriced): ?>
                            ₹<?php echo number_format($order['total_amount'], 2); ?> <span class="estimate-mark">*</span>
                        <?php else: ?>
                            ₹<?php echo number_format($order['total_amount'], 2); ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                    <td>
                        <?php 
                        $paymentStatus = $order['payment_status'] ?? 'unpaid';
                        ?>
                        <span class="payment-badge payment-<?php echo $paymentStatus; ?>"><?php echo ucfirst($paymentStatus); ?></span>
                    </td>
                    <td><a href="?view=<?php echo $order['id']; ?>" class="btn-sm btn-save">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<style>
.btn-back { background: #95a5a6; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; }
.btn-back:hover { background: #7f8c8d; }
.btn-invoice { background: #27ae60; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; margin-left: 10px; }
.btn-invoice:hover { background: #229954; }
.header-actions { display: flex; gap: 10px; align-items: center; }

.order-view { display: flex; flex-direction: column; gap: 20px; width: 100%; max-width: none; }
.order-header-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); width: 100%; }
.order-meta { display: flex; gap: 40px; flex-wrap: wrap; align-items: center; }
.order-meta .label { display: block; font-size: 12px; color: #7f8c8d; }
.order-meta .value { font-size: 16px; font-weight: 600; color: #2c3e50; }
.pricing-alert { background: #fff3cd; color: #856404; padding: 10px 15px; border-radius: 6px; margin-top: 15px; font-weight: 500; }

.details-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; width: 100%; }
@media (max-width: 1200px) { .details-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) { .details-grid { grid-template-columns: 1fr; } }
.detail-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.detail-card h3 { font-size: 14px; color: #7f8c8d; margin-bottom: 12px; text-transform: uppercase; }
.detail-card p { margin: 5px 0; color: #2c3e50; font-size: 14px; }

/* Order Items Section - Full Width */
.items-section { 
    width: 100%;
    max-width: none;
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    box-sizing: border-box;
}
.items-section h2 { 
    font-size: 20px;
    color: #2c3e50;
    margin-bottom: 20px; 
}
.table-responsive { width: 100%; overflow-x: auto; }
.items-table { 
    width: 100%;
    min-width: 800px;
    table-layout: fixed;
    border-collapse: collapse;
    background: white;
}
.items-table colgroup col:nth-child(1) { width: 22%; }
.items-table colgroup col:nth-child(2) { width: 10%; }
.items-table colgroup col:nth-child(3) { width: 12%; }
.items-table colgroup col:nth-child(4) { width: 10%; }
.items-table colgroup col:nth-child(5) { width: 12%; }
.items-table colgroup col:nth-child(6) { width: 10%; }
.items-table colgroup col:nth-child(7) { width: 12%; }
.items-table colgroup col:nth-child(8) { width: 12%; }
.items-table thead { background: #f8f9fa; }
.items-table th { 
    background: #f8f9fa; 
    color: #5a6a7a;
    font-size: 11px; 
    font-weight: 600; 
    text-transform: uppercase;
    white-space: nowrap;
    padding: 12px 10px;
    text-align: left;
    border-bottom: 2px solid #ddd;
}
.items-table td { 
    padding: 12px 10px; 
    font-size: 13px;
    vertical-align: middle;
    border-bottom: 1px solid #eee;
    text-align: left;
}
.items-table tfoot td { 
    background: #f8f9fa; 
    font-weight: 600; 
    border-top: 2px solid #ddd;
    border-bottom: none;
}
.text-right { text-align: right !important; }

.custom-row { background: #fffbf0; }
.custom-badge { background: #f39c12; color: white; font-size: 10px; padding: 2px 6px; border-radius: 3px; margin-left: 8px; }
.tbd { color: #f39c12; font-style: italic; }

.needs-pricing { margin-left: 5px; }
.estimate-mark { color: #f39c12; font-weight: bold; font-size: 16px; }

.status-badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.status-pending { background: #fff3e0; color: #f57c00; }
.status-confirmed { background: #e3f2fd; color: #1976d2; }
.status-processing { background: #e8f5e9; color: #388e3c; }
.status-shipped { background: #f3e5f5; color: #7b1fa2; }
.status-delivered { background: #e8f5e9; color: #2e7d32; }
.status-cancelled { background: #ffebee; color: #c62828; }
</style>

<?php include 'includes/footer.php'; ?>
