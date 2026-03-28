<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$message = '';
$messageType = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
        
        if (in_array($status, $validStatuses)) {
            // Check for unpriced items if status is not pending or cancelled
            $canUpdate = true;
            if (!in_array($status, ['pending', 'cancelled'])) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND unit_price IS NULL");
                $stmt->execute([$orderId]);
                $unpricedCount = $stmt->fetchColumn();
                
                if ($unpricedCount > 0) {
                    $canUpdate = false;
                    $message = "Cannot update status to '$status'. Order has $unpricedCount item(s) without pricing. Please set prices first.";
                    $messageType = 'error';
                }
            }
            
            if ($canUpdate) {
                $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $orderId]);
                $message = 'Order status updated to ' . ucfirst($status) . '.';
                $messageType = 'success';
            }
        }
    }
    
    if ($action === 'update_pricing') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        
        // Check if order is editable (must be pending and unpaid)
        $stmt = $pdo->prepare("SELECT status, payment_status FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $orderCheck = $stmt->fetch();
        
        if ($orderCheck && $orderCheck['payment_status'] === 'paid') {
            $message = 'Cannot modify order items. This order has already been marked as paid.';
            $messageType = 'error';
        } elseif ($orderCheck && $orderCheck['status'] !== 'pending') {
            $message = 'Cannot modify order items. Only orders with "Pending" status can be edited.';
            $messageType = 'error';
        } else {
            $itemPrices = $_POST['item_price'] ?? [];
            $itemTaxRates = $_POST['item_tax_rate'] ?? [];
            $itemQuantities = $_POST['item_qty'] ?? [];
            $deleteItems = $_POST['delete_items'] ?? [];
            
            // Validate existing items - check for invalid price or quantity
            $validationError = false;
            foreach ($itemPrices as $itemId => $price) {
                if (in_array($itemId, $deleteItems)) continue;
                $unitPrice = (float)$price;
                $qty = isset($itemQuantities[$itemId]) ? (int)$itemQuantities[$itemId] : 0;
                if ($unitPrice <= 0 || $qty <= 0) {
                    $validationError = true;
                    break;
                }
            }
            
            // Validate new items
            $newPrices = $_POST['new_price'] ?? [];
            $newQtys = $_POST['new_qty'] ?? [];
            foreach ($newPrices as $idx => $price) {
                $unitPrice = (float)$price;
                $qty = isset($newQtys[$idx]) ? (int)$newQtys[$idx] : 0;
                if ($unitPrice <= 0 || $qty <= 0) {
                    $validationError = true;
                    break;
                }
            }
            
            if ($validationError) {
                $message = 'Cannot save. All items must have unit price and quantity greater than 0.';
                $messageType = 'error';
            } else {
                // Delete removed items first
                if (!empty($deleteItems)) {
                    $deleteIds = array_map('intval', $deleteItems);
                    $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                    $pdo->prepare("DELETE FROM order_items WHERE id IN ($placeholders) AND order_id = ?")
                        ->execute(array_merge($deleteIds, [$orderId]));
                }
                
                $totalGross = 0;
                $totalTax = 0;
                $totalNet = 0;
                
                foreach ($itemPrices as $itemId => $price) {
                    // Skip deleted items
                    if (in_array($itemId, $deleteItems)) continue;
                    
                    $unitPrice = (float)$price; // This is tax-inclusive price
                    $taxRate = isset($itemTaxRates[$itemId]) ? (float)$itemTaxRates[$itemId] : 18.00;
                    $qty = isset($itemQuantities[$itemId]) ? (int)$itemQuantities[$itemId] : 1;
                    
                    // Price in DB is tax-inclusive (net)
                    // Net Total = unit_price * qty
                    // Gross Total = Net Total / (1 + tax_rate/100)
                    // Tax Amount = Net Total - Gross Total
                    $lineNet = $unitPrice * $qty;
                    $lineGross = $lineNet / (1 + ($taxRate / 100));
                    $lineTax = $lineNet - $lineGross;
                    
                    $pdo->prepare("UPDATE order_items SET unit_price = ?, quantity = ?, tax_rate = ?, tax_amount = ?, line_total = ? WHERE id = ?")
                        ->execute([$unitPrice, $qty, $taxRate, $lineTax, $lineNet, $itemId]);
                    
                    $totalGross += $lineGross;
                    $totalTax += $lineTax;
                    $totalNet += $lineNet;
                }
                
                // Handle new items
                $newProducts = $_POST['new_product_id'] ?? [];
                $newPrices = $_POST['new_price'] ?? [];
                $newQtys = $_POST['new_qty'] ?? [];
                $newTaxRates = $_POST['new_tax_rate'] ?? [];
                
                foreach ($newProducts as $idx => $productId) {
                    if (empty($productId)) continue;
                    
                    // Get product details
                    $stmt = $pdo->prepare("SELECT name, sku, unit FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch();
                    if (!$product) continue;
                    
                    $unitPrice = (float)($newPrices[$idx] ?? 0);
                    $qty = (int)($newQtys[$idx] ?? 1);
                    $taxRate = (float)($newTaxRates[$idx] ?? 18);
                    
                    $lineNet = $unitPrice * $qty;
                    $lineGross = $lineNet / (1 + ($taxRate / 100));
                    $lineTax = $lineNet - $lineGross;
                    
                    $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_sku, unit, quantity, unit_price, tax_rate, tax_amount, line_total, is_custom_item) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)")
                        ->execute([$orderId, $productId, $product['name'], $product['sku'], $product['unit'], $qty, $unitPrice, $taxRate, $lineTax, $lineNet]);
                    
                    $totalGross += $lineGross;
                    $totalTax += $lineTax;
                    $totalNet += $lineNet;
                }
                
                // Check if any items have null pricing
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND unit_price IS NULL");
                $stmt->execute([$orderId]);
                $hasUnpriced = $stmt->fetchColumn() > 0 ? 1 : 0;
                
                $pdo->prepare("UPDATE orders SET subtotal = ?, tax_amount = ?, total_amount = ?, has_unpriced_items = ?, price_confirmed = ? WHERE id = ?")
                    ->execute([$totalGross, $totalTax, $totalNet, $hasUnpriced, $hasUnpriced ? 0 : 1, $orderId]);
                
                $message = 'Order updated successfully.';
                $messageType = 'success';
            }
        }
    }
    
    if ($action === 'accept_payment') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $paymentMode = $_POST['payment_method'] ?? '';
        $paymentDate = $_POST['payment_date'] ?? null;
        $transactionId = trim($_POST['transaction_id'] ?? '');
        $paymentNotes = trim($_POST['payment_notes'] ?? '');
        
        $validModes = ['cash', 'cheque', 'bank_transfer', 'upi', 'credit'];
        
        // Check order status - cannot accept payment for pending or cancelled orders
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $orderCheck = $stmt->fetch();
        
        if ($orderCheck && in_array($orderCheck['status'], ['pending', 'cancelled'])) {
            $message = 'Cannot accept payment. Order status must not be Pending or Cancelled.';
            $messageType = 'error';
        } elseif ($orderId && in_array($paymentMode, $validModes)) {
            $pdo->prepare("UPDATE orders SET payment_mode = ?, payment_status = 'paid', payment_date = ?, payment_transaction_id = ?, payment_notes = ? WHERE id = ?")
                ->execute([$paymentMode, $paymentDate ?: null, $transactionId ?: null, $paymentNotes ?: null, $orderId]);
            
            $message = 'Payment marked as paid successfully.';
            $messageType = 'success';
        } else {
            $message = 'Invalid payment details.';
            $messageType = 'error';
        }
    }
    
    if ($action === 'clear_payment') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        
        if ($orderId) {
            $pdo->prepare("UPDATE orders SET payment_status = 'unpaid', payment_date = NULL, payment_transaction_id = NULL, payment_notes = NULL WHERE id = ?")
                ->execute([$orderId]);
            
            $message = 'Payment details cleared. You can now accept payment again.';
            $messageType = 'success';
        }
    }
}

// View single order
$viewOrder = null;
$orderItems = [];
$unpricedItemCount = 0;
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT o.*, u.email as user_email FROM orders o LEFT JOIN site_users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$_GET['view']]);
    $viewOrder = $stmt->fetch();
    
    if ($viewOrder) {
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$viewOrder['id']]);
        $orderItems = $stmt->fetchAll();
        
        // Count unpriced items
        foreach ($orderItems as $item) {
            if ($item['unit_price'] === null) {
                $unpricedItemCount++;
            }
        }
    }
}

// Get orders list
$filter = $_GET['filter'] ?? 'all';
$whereClause = '1=1';
if ($filter === 'pending') $whereClause = "status = 'pending'";
elseif ($filter === 'unpriced') $whereClause = "has_unpriced_items = 1";

$orders = $pdo->query("SELECT o.*, u.email as user_email FROM orders o LEFT JOIN site_users u ON o.user_id = u.id WHERE $whereClause ORDER BY o.created_at DESC")->fetchAll();

$pageTitle = 'Orders - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1><?php echo $viewOrder ? 'Order Details' : 'Orders'; ?></h1>
        <?php if ($viewOrder): ?>
        <div class="header-actions">
            <a href="orders.php" class="btn-back">← Back to Orders</a>
            <?php if (!in_array($viewOrder['status'], ['pending', 'cancelled'])): ?>
            <a href="invoice.php?id=<?php echo $viewOrder['id']; ?>" class="btn-invoice" target="_blank">📄 View Invoice</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($viewOrder): ?>
    <!-- Order Details View -->
    <div class="order-view">
        <div class="order-header-card">
            <div class="order-meta">
                <div><span class="label">Order #</span><span class="value"><?php echo htmlspecialchars($viewOrder['order_number']); ?></span></div>
                <div><span class="label">Date</span><span class="value"><?php echo date('d M Y, h:i A', strtotime($viewOrder['created_at'])); ?></span></div>
                <div><span class="label">Customer</span><span class="value"><?php echo htmlspecialchars($viewOrder['user_email']); ?></span></div>
                <div>
                    <form method="POST" class="status-form" id="statusForm">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                        <input type="hidden" id="unpricedCount" value="<?php echo $unpricedItemCount; ?>">
                        <input type="hidden" id="currentStatus" value="<?php echo $viewOrder['status']; ?>">
                        <select name="status" id="statusSelect">
                            <?php foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $viewOrder['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
            <?php if ($viewOrder['has_unpriced_items']): ?>
            <div class="pricing-alert">⚠️ This order has items that need pricing</div>
            <?php endif; ?>
        </div>

        <div class="details-grid">
            <div class="detail-card">
                <h3>Billing Address</h3>
                <p><strong><?php echo htmlspecialchars($viewOrder['billing_name']); ?></strong></p>
                <p><?php echo htmlspecialchars($viewOrder['billing_address']); ?></p>
                <p><?php echo htmlspecialchars($viewOrder['billing_city'] . ', ' . $viewOrder['billing_state'] . ' - ' . $viewOrder['billing_pincode']); ?></p>
                <p>Phone: <?php echo htmlspecialchars($viewOrder['billing_phone']); ?></p>
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

        <!-- Order Items with Pricing - Full Width -->
        <?php 
        $isPaid = ($viewOrder['payment_status'] ?? 'unpaid') === 'paid';
        $isEditable = $viewOrder['status'] === 'pending' && !$isPaid;
        ?>
        <div class="items-section">
            <h2>Order Items 
                <?php if ($isPaid): ?>
                    <span class="locked-badge">🔒 Locked (Paid)</span>
                <?php elseif (!$isEditable): ?>
                    <span class="locked-badge">🔒 Locked (Status: <?php echo ucfirst($viewOrder['status']); ?>)</span>
                <?php endif; ?>
            </h2>
            <form method="POST" id="pricingForm">
                <input type="hidden" name="action" value="update_pricing">
                <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                <div class="table-responsive">
                    <table class="items-table" id="itemsTable">
                        <colgroup>
                            <col><col><col><col><col><col><col><col><?php if ($isEditable): ?><col><?php endif; ?>
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
                                <?php if ($isEditable): ?><th>Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <?php foreach ($orderItems as $index => $item): 
                                $unitPrice = $item['unit_price'];
                                $taxRate = $item['tax_rate'] ?? 18;
                                $qty = $item['quantity'];
                                $netTotal = $unitPrice !== null ? $unitPrice * $qty : null;
                                $grossTotal = $netTotal !== null ? $netTotal / (1 + ($taxRate / 100)) : null;
                                $taxAmt = $netTotal !== null ? $netTotal - $grossTotal : null;
                            ?>
                            <tr class="<?php echo $item['is_custom_item'] ? 'custom-row' : ''; ?> item-row" data-row="<?php echo $index; ?>" data-item-id="<?php echo $item['id']; ?>">
                                <td>
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                    <?php if ($item['is_custom_item']): ?><span class="custom-badge">USER ADDED</span><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['product_sku'] ?? '-'); ?></td>
                                <td>
                                    <input type="number" name="item_price[<?php echo $item['id']; ?>]" 
                                           value="<?php echo $unitPrice !== null ? $unitPrice : ''; ?>" 
                                           step="0.01" min="0" placeholder="0.00" 
                                           class="edit-input price-input" data-row="<?php echo $index; ?>" <?php echo !$isEditable ? 'disabled' : 'required'; ?>>
                                </td>
                                <td>
                                    <input type="number" name="item_qty[<?php echo $item['id']; ?>]" 
                                           value="<?php echo $qty; ?>" 
                                           step="1" min="1" 
                                           class="edit-input qty-input" data-row="<?php echo $index; ?>" <?php echo !$isEditable ? 'disabled' : 'required'; ?>>
                                    <span class="unit-label"><?php echo htmlspecialchars($item['unit']); ?></span>
                                </td>
                                <td class="gross-total" data-row="<?php echo $index; ?>">
                                    <?php echo $grossTotal !== null ? '₹' . number_format($grossTotal, 2) : '-'; ?>
                                </td>
                                <td>
                                    <input type="number" name="item_tax_rate[<?php echo $item['id']; ?>]" 
                                           value="<?php echo $taxRate; ?>" 
                                           step="0.01" min="0" max="100" 
                                           class="edit-input tax-input" data-row="<?php echo $index; ?>" <?php echo !$isEditable ? 'disabled' : ''; ?>>
                                </td>
                                <td class="tax-amount" data-row="<?php echo $index; ?>">
                                    <?php echo $taxAmt !== null ? '₹' . number_format($taxAmt, 2) : '-'; ?>
                                </td>
                                <td class="net-total" data-row="<?php echo $index; ?>">
                                    <?php echo $netTotal !== null ? '₹' . number_format($netTotal, 2) : '-'; ?>
                                </td>
                                <?php if ($isEditable): ?>
                                <td>
                                    <button type="button" class="btn-remove-item" onclick="removeItem(this, <?php echo $item['id']; ?>)">✕</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" style="text-align: right;"><strong>Totals:</strong></td>
                                <td id="summaryGross"><strong>₹<?php echo number_format($viewOrder['subtotal'] ?? 0, 2); ?></strong></td>
                                <td></td>
                                <td id="summaryTax"><strong>₹<?php echo number_format($viewOrder['tax_amount'] ?? 0, 2); ?></strong></td>
                                <td id="summaryNet"><strong>₹<?php echo number_format($viewOrder['total_amount'] ?? 0, 2); ?></strong></td>
                                <?php if ($isEditable): ?><td></td><?php endif; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div id="deletedItems"></div>
                <?php if ($isEditable): ?>
                <div class="add-item-section">
                    <h4>Add Item</h4>
                    <div class="add-item-row" id="addItemRow">
                        <div class="add-item-field">
                            <label>Product</label>
                            <select id="newProductSelect" class="product-select">
                                <option value="">Select product...</option>
                            </select>
                        </div>
                        <div class="add-item-field">
                            <label>Unit Price</label>
                            <input type="number" id="newPrice" step="0.01" min="0" placeholder="0.00" class="edit-input">
                        </div>
                        <div class="add-item-field">
                            <label>Qty</label>
                            <input type="number" id="newQty" value="1" min="1" class="edit-input" style="width:60px;">
                        </div>
                        <div class="add-item-field">
                            <label>Tax %</label>
                            <input type="number" id="newTaxRate" value="18" step="0.01" min="0" max="100" class="edit-input" style="width:60px;">
                        </div>
                        <div class="add-item-field">
                            <button type="button" class="btn-add-item" onclick="addNewItem()">+ Add</button>
                        </div>
                    </div>
                </div>
                <div class="pricing-actions">
                    <button type="submit" class="btn-primary" onclick="return validateAndConfirmSave();">Save Changes</button>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Payment Section -->
        <?php 
        $paymentStatus = $viewOrder['payment_status'] ?? 'unpaid';
        $orderStatus = $viewOrder['status'];
        $canAcceptPayment = !in_array($orderStatus, ['pending', 'cancelled']);
        ?>
        <div class="payment-section">
            <div class="payment-status-card">
                <h3>Payment Status</h3>
                <div class="payment-summary">
                    <div class="payment-stat">
                        <span class="stat-label">Total Amount</span>
                        <span class="stat-value">₹<?php echo number_format($viewOrder['total_amount'], 2); ?></span>
                    </div>
                    <div class="payment-stat">
                        <span class="stat-label">Status</span>
                        <span class="payment-badge payment-<?php echo $paymentStatus; ?>"><?php echo ucfirst($paymentStatus); ?></span>
                    </div>
                </div>
                <?php if ($paymentStatus === 'paid'): ?>
                <div class="payment-details">
                    <p class="payment-info"><strong>Payment Method:</strong> <?php echo ucwords(str_replace('_', ' ', $viewOrder['payment_mode'])); ?></p>
                    <?php if ($viewOrder['payment_date']): ?>
                    <p class="payment-info"><strong>Payment Date:</strong> <?php echo date('d M Y', strtotime($viewOrder['payment_date'])); ?></p>
                    <?php endif; ?>
                    <?php if ($viewOrder['payment_transaction_id']): ?>
                    <p class="payment-info"><strong>Transaction ID:</strong> <?php echo htmlspecialchars($viewOrder['payment_transaction_id']); ?></p>
                    <?php endif; ?>
                    <?php if ($viewOrder['payment_notes']): ?>
                    <p class="payment-info"><strong>Notes:</strong> <?php echo htmlspecialchars($viewOrder['payment_notes']); ?></p>
                    <?php endif; ?>
                    <form method="POST" class="clear-payment-form" onsubmit="return confirm('Are you sure you want to clear the payment details? This will mark the order as unpaid.');">
                        <input type="hidden" name="action" value="clear_payment">
                        <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                        <button type="submit" class="btn-clear-payment">Clear Payment & Re-accept</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($paymentStatus !== 'paid'): ?>
            <div class="payment-form-card">
                <h3>Mark as Paid</h3>
                <?php if (!$canAcceptPayment): ?>
                <div class="payment-blocked-notice">
                    ⚠️ Cannot accept payment while order status is "<?php echo ucfirst($orderStatus); ?>". 
                    Change status to Confirmed, Processing, Shipped, or Delivered first.
                </div>
                <?php else: ?>
                <form method="POST" id="paymentForm">
                    <input type="hidden" name="action" value="accept_payment">
                    <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                    <div class="payment-form-grid">
                        <div class="form-field">
                            <label>Payment Method *</label>
                            <select name="payment_method" required>
                                <option value="bank_transfer" <?php echo $viewOrder['payment_mode'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="upi" <?php echo $viewOrder['payment_mode'] === 'upi' ? 'selected' : ''; ?>>UPI</option>
                                <option value="cheque" <?php echo $viewOrder['payment_mode'] === 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                                <option value="cash" <?php echo $viewOrder['payment_mode'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="credit" <?php echo $viewOrder['payment_mode'] === 'credit' ? 'selected' : ''; ?>>Credit</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Payment Date *</label>
                            <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-field">
                            <label>Transaction ID / Reference</label>
                            <input type="text" name="transaction_id" placeholder="e.g., UTR number, cheque no.">
                        </div>
                        <div class="form-field">
                            <label>Payment Notes</label>
                            <input type="text" name="payment_notes" placeholder="Any notes...">
                        </div>
                    </div>
                    <div class="payment-form-actions">
                        <button type="submit" class="btn-primary" onclick="return confirm('Are you sure you want to mark this order as paid?');">Mark as Paid</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('pricingForm');
        const rows = form.querySelectorAll('tbody tr');
        
        function calculateRow(row) {
            const priceInput = row.querySelector('.price-input');
            const taxInput = row.querySelector('.tax-input');
            const qtyInput = row.querySelector('.qty-input');
            
            if (!priceInput || !taxInput || !qtyInput) return;
            
            const unitPrice = parseFloat(priceInput.value) || 0;
            const taxRate = parseFloat(taxInput.value) || 0;
            const qty = parseInt(qtyInput.value) || 0;
            
            const netTotal = unitPrice * qty;
            const grossTotal = netTotal / (1 + (taxRate / 100));
            const taxAmount = netTotal - grossTotal;
            
            row.querySelector('.gross-total').textContent = '₹' + grossTotal.toFixed(2);
            row.querySelector('.tax-amount').textContent = '₹' + taxAmount.toFixed(2);
            row.querySelector('.net-total').textContent = '₹' + netTotal.toFixed(2);
            
            calculateSummary();
        }
        
        function calculateSummary() {
            let totalGross = 0, totalTax = 0, totalNet = 0;
            
            document.querySelectorAll('#itemsBody .item-row:not(.deleted), #newItemsContainer .new-item-row').forEach(row => {
                const grossEl = row.querySelector('.gross-total');
                const taxEl = row.querySelector('.tax-amount');
                const netEl = row.querySelector('.net-total');
                
                if (grossEl && taxEl && netEl) {
                    totalGross += parseFloat(grossEl.textContent.replace('₹', '').replace(/,/g, '')) || 0;
                    totalTax += parseFloat(taxEl.textContent.replace('₹', '').replace(/,/g, '')) || 0;
                    totalNet += parseFloat(netEl.textContent.replace('₹', '').replace(/,/g, '')) || 0;
                }
            });
            
            document.getElementById('summaryGross').innerHTML = '<strong>₹' + totalGross.toFixed(2) + '</strong>';
            document.getElementById('summaryTax').innerHTML = '<strong>₹' + totalTax.toFixed(2) + '</strong>';
            document.getElementById('summaryNet').innerHTML = '<strong>₹' + totalNet.toFixed(2) + '</strong>';
        }
        
        // Bind events to existing rows
        document.querySelectorAll('#itemsBody .item-row').forEach(row => {
            const priceInput = row.querySelector('.price-input');
            const taxInput = row.querySelector('.tax-input');
            const qtyInput = row.querySelector('.qty-input');
            
            if (priceInput) priceInput.addEventListener('input', () => calculateRow(row));
            if (taxInput) taxInput.addEventListener('input', () => calculateRow(row));
            if (qtyInput) qtyInput.addEventListener('input', () => calculateRow(row));
        });
        
        // Status change confirmation
        const statusSelect = document.getElementById('statusSelect');
        const statusForm = document.getElementById('statusForm');
        const unpricedCount = parseInt(document.getElementById('unpricedCount').value) || 0;
        const currentStatus = document.getElementById('currentStatus').value;
        
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                const newStatus = this.value;
                const statusLabels = {
                    'pending': 'Pending',
                    'confirmed': 'Confirmed',
                    'processing': 'Processing',
                    'shipped': 'Shipped',
                    'delivered': 'Delivered',
                    'cancelled': 'Cancelled'
                };
                
                if (unpricedCount > 0 && !['pending', 'cancelled'].includes(newStatus)) {
                    alert('Cannot change status to "' + statusLabels[newStatus] + '".\n\nThis order has ' + unpricedCount + ' item(s) without pricing.\nPlease set prices for all items first.');
                    this.value = currentStatus;
                    return;
                }
                
                const confirmMsg = 'Are you sure you want to change the order status from "' + statusLabels[currentStatus] + '" to "' + statusLabels[newStatus] + '"?';
                if (confirm(confirmMsg)) {
                    statusForm.submit();
                } else {
                    this.value = currentStatus;
                }
            });
        }
        
        // Load products for add item dropdown
        const productSelect = document.getElementById('newProductSelect');
        if (productSelect) {
            fetch('../api/search-products.php?q=&all=1')
                .then(res => res.json())
                .then(products => {
                    window._products = {};
                    products.forEach(p => {
                        window._products[p.id] = p;
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.name + (p.sku ? ' (' + p.sku + ')' : '');
                        opt.dataset.price = p.unit_price || '';
                        opt.dataset.tax = p.tax_rate || 18;
                        opt.dataset.unit = p.unit || 'piece';
                        productSelect.appendChild(opt);
                    });
                });
            
            productSelect.addEventListener('change', function() {
                const p = window._products[this.value];
                if (p) {
                    document.getElementById('newPrice').value = p.unit_price || '';
                    document.getElementById('newTaxRate').value = p.tax_rate || 18;
                }
            });
        }
    });
    
    let newItemIndex = 0;
    
    function removeItem(btn, itemId) {
        if (!confirm('Remove this item from the order?')) return;
        const row = btn.closest('tr');
        row.classList.add('deleted');
        row.style.display = 'none';
        // Only add to delete list if it's an existing item (has numeric ID)
        if (itemId && !String(itemId).startsWith('new_')) {
            document.getElementById('deletedItems').innerHTML += '<input type="hidden" name="delete_items[]" value="' + itemId + '">';
        }
        calculateSummary();
    }
    
    function removeNewItem(btn) {
        const row = btn.closest('tr');
        row.remove();
        calculateSummary();
    }
    
    function addNewItem() {
        const select = document.getElementById('newProductSelect');
        const productId = select.value;
        if (!productId) { alert('Please select a product'); return; }
        
        const product = window._products[productId];
        const price = parseFloat(document.getElementById('newPrice').value) || 0;
        const qty = parseInt(document.getElementById('newQty').value) || 1;
        const taxRate = parseFloat(document.getElementById('newTaxRate').value) || 18;
        
        const netTotal = price * qty;
        const grossTotal = netTotal / (1 + (taxRate / 100));
        const taxAmount = netTotal - grossTotal;
        
        const tbody = document.getElementById('itemsBody');
        const row = document.createElement('tr');
        row.className = 'item-row new-item-row';
        row.dataset.itemId = 'new_' + newItemIndex;
        row.innerHTML = `
            <td>
                ${escapeHtml(product.name)}
                <span class="custom-badge" style="background:#27ae60;">NEW</span>
                <input type="hidden" name="new_product_id[]" value="${productId}">
            </td>
            <td>${escapeHtml(product.sku || '-')}</td>
            <td>
                <input type="number" name="new_price[]" value="${price}" step="0.01" min="0" class="edit-input price-input" required>
            </td>
            <td>
                <input type="number" name="new_qty[]" value="${qty}" step="1" min="1" class="edit-input qty-input" required>
                <span class="unit-label">${escapeHtml(product.unit || 'piece')}</span>
            </td>
            <td class="gross-total">₹${grossTotal.toFixed(2)}</td>
            <td>
                <input type="number" name="new_tax_rate[]" value="${taxRate}" step="0.01" min="0" max="100" class="edit-input tax-input">
            </td>
            <td class="tax-amount">₹${taxAmount.toFixed(2)}</td>
            <td class="net-total">₹${netTotal.toFixed(2)}</td>
            <td>
                <button type="button" class="btn-remove-item" onclick="removeNewItem(this)">✕</button>
            </td>
        `;
        tbody.appendChild(row);
        
        // Bind events to new row inputs
        const priceInput = row.querySelector('.price-input');
        const qtyInput = row.querySelector('.qty-input');
        const taxInput = row.querySelector('.tax-input');
        
        [priceInput, qtyInput, taxInput].forEach(input => {
            if (input) input.addEventListener('input', () => calculateRowFromInputs(row));
        });
        
        newItemIndex++;
        
        // Reset form
        select.value = '';
        document.getElementById('newPrice').value = '';
        document.getElementById('newQty').value = '1';
        document.getElementById('newTaxRate').value = '18';
        
        calculateSummary();
    }
    
    function calculateRowFromInputs(row) {
        const priceInput = row.querySelector('.price-input');
        const qtyInput = row.querySelector('.qty-input');
        const taxInput = row.querySelector('.tax-input');
        
        if (!priceInput || !qtyInput || !taxInput) return;
        
        const price = parseFloat(priceInput.value) || 0;
        const qty = parseInt(qtyInput.value) || 0;
        const taxRate = parseFloat(taxInput.value) || 0;
        
        const netTotal = price * qty;
        const grossTotal = netTotal / (1 + (taxRate / 100));
        const taxAmount = netTotal - grossTotal;
        
        row.querySelector('.gross-total').textContent = '₹' + grossTotal.toFixed(2);
        row.querySelector('.tax-amount').textContent = '₹' + taxAmount.toFixed(2);
        row.querySelector('.net-total').textContent = '₹' + netTotal.toFixed(2);
        
        calculateSummary();
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
    
    function validateAndConfirmSave() {
        // Check all visible item rows (not deleted)
        const rows = document.querySelectorAll('#itemsBody .item-row:not(.deleted)');
        let hasInvalidItems = false;
        let invalidCount = 0;
        
        rows.forEach(row => {
            const priceInput = row.querySelector('.price-input');
            const qtyInput = row.querySelector('.qty-input');
            
            if (priceInput && qtyInput) {
                const price = parseFloat(priceInput.value) || 0;
                const qty = parseInt(qtyInput.value) || 0;
                
                if (price <= 0 || qty <= 0) {
                    hasInvalidItems = true;
                    invalidCount++;
                    row.style.outline = '2px solid #e74c3c';
                } else {
                    row.style.outline = '';
                }
            }
        });
        
        if (hasInvalidItems) {
            alert('Cannot save. ' + invalidCount + ' item(s) have unit price or quantity less than or equal to 0.\n\nPlease ensure all items have valid price and quantity greater than 0.');
            return false;
        }
        
        return confirm('Are you sure you want to save these changes?');
    }
    
    function calculateSummary() {
        let totalGross = 0, totalTax = 0, totalNet = 0;
        
        document.querySelectorAll('#itemsBody .item-row:not(.deleted)').forEach(row => {
            const grossEl = row.querySelector('.gross-total');
            const taxEl = row.querySelector('.tax-amount');
            const netEl = row.querySelector('.net-total');
            if (grossEl && taxEl && netEl) {
                totalGross += parseFloat(grossEl.textContent.replace('₹', '').replace(/,/g, '')) || 0;
                totalTax += parseFloat(taxEl.textContent.replace('₹', '').replace(/,/g, '')) || 0;
                totalNet += parseFloat(netEl.textContent.replace('₹', '').replace(/,/g, '')) || 0;
            }
        });
        
        document.getElementById('summaryGross').innerHTML = '<strong>₹' + totalGross.toFixed(2) + '</strong>';
        document.getElementById('summaryTax').innerHTML = '<strong>₹' + totalTax.toFixed(2) + '</strong>';
        document.getElementById('summaryNet').innerHTML = '<strong>₹' + totalNet.toFixed(2) + '</strong>';
    }
    </script>

    <?php else: ?>
    <!-- Orders List -->
    <div class="filter-tabs">
        <a href="?filter=all" class="tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All Orders</a>
        <a href="?filter=pending" class="tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="?filter=unpriced" class="tab <?php echo $filter === 'unpriced' ? 'active' : ''; ?>">Needs Pricing</a>
    </div>

    <div class="dashboard-section">
        <?php if (empty($orders)): ?>
        <div class="activity-card"><p>No orders found.</p></div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($order['order_number']); ?>
                        <?php if ($order['has_unpriced_items']): ?><span class="needs-pricing">⚠️</span><?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($order['billing_name']); ?><br><small><?php echo htmlspecialchars($order['user_email']); ?></small></td>
                    <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                    <td>
                        <?php if ($order['has_unpriced_items']): ?>
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
.order-header-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); width: 100%; max-width: none; }
.order-meta { display: flex; gap: 40px; flex-wrap: wrap; align-items: center; }
.order-meta .label { display: block; font-size: 12px; color: #7f8c8d; }
.order-meta .value { font-size: 16px; font-weight: 600; color: #2c3e50; }
.status-form select { padding: 8px 12px; border: 2px solid #3498db; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
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
    overflow: visible;
}
.items-section h2 { 
    font-size: 20px;
    color: #2c3e50;
    margin-bottom: 20px; 
}
.items-section form { width: 100%; max-width: none; }
.items-section .table-responsive { width: 100%; max-width: none; overflow-x: auto; }
.items-table { 
    width: 100%;
    min-width: 800px;
    table-layout: fixed;
    border-collapse: collapse;
    background: white;
}
.items-table colgroup col:nth-child(1) { width: 20%; } /* Product */
.items-table colgroup col:nth-child(2) { width: 9%; } /* SKU */
.items-table colgroup col:nth-child(3) { width: 11%; } /* Unit Price */
.items-table colgroup col:nth-child(4) { width: 9%; } /* Qty */
.items-table colgroup col:nth-child(5) { width: 11%; } /* Gross Total */
.items-table colgroup col:nth-child(6) { width: 9%; } /* Tax % */
.items-table colgroup col:nth-child(7) { width: 11%; } /* Tax Amount */
.items-table colgroup col:nth-child(8) { width: 11%; } /* Net Total */
.items-table colgroup col:nth-child(9) { width: 9%; } /* Actions */
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

.locked-badge { font-size: 12px; font-weight: 500; color: #7f8c8d; margin-left: 10px; }
.edit-input:disabled { background: #f5f5f5; color: #888; cursor: not-allowed; }

/* Remove Item Button */
.btn-remove-item { background: #e74c3c; color: white; border: none; width: 26px; height: 26px; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-remove-item:hover { background: #c0392b; }

/* New Item Row in Table */
.new-item-row { background: #e8f5e9 !important; }

/* Add Item Section */
.add-item-section { margin-top: 20px; padding-top: 20px; border-top: 2px dashed #ddd; }
.add-item-section h4 { font-size: 14px; color: #7f8c8d; margin-bottom: 12px; text-transform: uppercase; }
.add-item-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
.add-item-field { display: flex; flex-direction: column; }
.add-item-field label { font-size: 11px; color: #666; margin-bottom: 4px; }
.add-item-field .product-select { width: 250px; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
.btn-add-item { background: #27ae60; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; }
.btn-add-item:hover { background: #219a52; }

/* Editable Inputs */
.edit-input { width: 90px; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; text-align: right; }
.edit-input:focus { border-color: #3498db; outline: none; box-shadow: 0 0 0 2px rgba(52,152,219,0.2); }
.price-input { width: 100px; }
.tax-input { width: 70px; }
.qty-input { width: 70px; }
.unit-label { font-size: 12px; color: #7f8c8d; margin-left: 4px; }

.pricing-actions { margin-top: 20px; display: flex; justify-content: flex-end; }
.btn-primary { background: #3498db; color: white; border: none; padding: 12px 30px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-primary:hover { background: #2980b9; }

.filter-tabs { display: flex; gap: 0; margin-bottom: 20px; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.filter-tabs .tab { padding: 15px 25px; text-decoration: none; color: #7f8c8d; border-bottom: 3px solid transparent; transition: all 0.2s; }
.filter-tabs .tab:hover { background: #f8f9fa; color: #2c3e50; }
.filter-tabs .tab.active { color: #3498db; border-bottom-color: #3498db; background: #f8f9fa; font-weight: 600; }

.needs-pricing { margin-left: 5px; }
.estimate-mark { color: #f39c12; font-weight: bold; font-size: 16px; }
.status-pending { background: #fff3e0; color: #f57c00; }
.status-confirmed { background: #e3f2fd; color: #1976d2; }
.status-processing { background: #e8f5e9; color: #388e3c; }
.status-shipped { background: #f3e5f5; color: #7b1fa2; }
.status-delivered { background: #e8f5e9; color: #2e7d32; }
.status-cancelled { background: #ffebee; color: #c62828; }

/* Payment Section */
.payment-section { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; width: 100%; }
@media (max-width: 900px) { .payment-section { grid-template-columns: 1fr; } }

.payment-status-card, .payment-form-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.payment-status-card h3, .payment-form-card h3 { font-size: 14px; color: #7f8c8d; margin-bottom: 15px; text-transform: uppercase; }

.payment-summary { display: flex; gap: 20px; margin-bottom: 15px; }
.payment-stat { text-align: center; padding: 12px 20px; background: #f8f9fa; border-radius: 8px; }
.payment-stat .stat-label { display: block; font-size: 11px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 4px; }
.payment-stat .stat-value { font-size: 18px; font-weight: 700; color: #2c3e50; }

.payment-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
.payment-unpaid { background: #ffebee; color: #c62828; }
.payment-paid { background: #e8f5e9; color: #2e7d32; }

.payment-details { margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
.payment-info { margin: 8px 0; font-size: 13px; color: #555; }

.clear-payment-form { margin-top: 15px; }
.btn-clear-payment { background: #e74c3c; color: white; border: none; padding: 8px 16px; border-radius: 5px; font-size: 13px; cursor: pointer; }
.btn-clear-payment:hover { background: #c0392b; }

.payment-blocked-notice { background: #fff3cd; color: #856404; padding: 15px; border-radius: 6px; font-size: 13px; line-height: 1.5; }

.payment-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
.payment-form-grid .form-field { display: flex; flex-direction: column; }
.payment-form-grid .form-field label { font-size: 12px; font-weight: 500; color: #555; margin-bottom: 6px; }
.payment-form-grid .form-field input,
.payment-form-grid .form-field select { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit; }
.payment-form-grid .form-field input:focus,
.payment-form-grid .form-field select:focus { border-color: #3498db; outline: none; box-shadow: 0 0 0 2px rgba(52,152,219,0.2); }

.payment-form-actions { margin-top: 20px; display: flex; justify-content: flex-end; }

@media (max-width: 600px) { .payment-form-grid { grid-template-columns: 1fr; } }
</style>

<?php include 'includes/footer.php'; ?>
