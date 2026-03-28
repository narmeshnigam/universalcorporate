<?php
session_start();
require_once '../config/database.php';
require_once '../config/identity.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) { header('Location: orders.php'); exit; }

// Fetch order details
$stmt = $pdo->prepare("SELECT o.*, u.email as user_email FROM orders o LEFT JOIN site_users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Check if invoice is viewable (status must not be pending or cancelled)
if (in_array($order['status'], ['pending', 'cancelled'])) {
    $_SESSION['error_message'] = 'Invoice is not available for orders with status: ' . ucfirst($order['status']);
    header('Location: orders.php?view=' . $orderId);
    exit;
}

// Fetch order items
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();

// Get site identity
$site = getSiteIdentity($pdo);

$pageTitle = 'Invoice - ' . $order['order_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        /* Print Button - Hidden in print */
        .print-controls { 
            position: fixed; 
            top: 20px; 
            right: 20px; 
            z-index: 1000;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn-print { 
            background: #3498db; 
            color: white; 
            border: none; 
            padding: 12px 24px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print:hover { background: #2980b9; }
        .btn-back {
            background: #95a5a6;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 10px;
        }
        .btn-back:hover { background: #7f8c8d; }
        
        /* A4 Page Container */
        .page-container {
            width: 210mm;
            margin: 20px auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }
        
        /* Invoice Content */
        .invoice {
            padding: 20mm;
            width: 100%;
        }
        
        /* Header */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #3498db;
        }
        .company-info h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .company-info p {
            font-size: 13px;
            color: #7f8c8d;
            line-height: 1.6;
            margin: 2px 0;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h2 {
            font-size: 32px;
            color: #3498db;
            margin-bottom: 5px;
        }
        .invoice-title p {
            font-size: 13px;
            color: #7f8c8d;
        }
        
        /* Bill To / Ship To Section */
        .addresses {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .address-block h3 {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }
        .address-block p {
            font-size: 13px;
            color: #2c3e50;
            line-height: 1.6;
            margin: 3px 0;
        }
        .address-block strong {
            font-size: 15px;
            color: #2c3e50;
        }
        
        /* Order Info */
        .order-info {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .info-item {
            text-align: center;
        }
        .info-item label {
            display: block;
            font-size: 11px;
            color: #7f8c8d;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-item span {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Payment Details Box */
        .payment-details-box {
            background: #e8f5e9;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #27ae60;
        }
        .payment-details-box h3 {
            font-size: 13px;
            color: #27ae60;
            text-transform: uppercase;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .payment-detail-item {
            display: flex;
            gap: 8px;
        }
        .payment-detail-item label {
            font-size: 12px;
            color: #555;
            font-weight: 600;
        }
        .payment-detail-item span {
            font-size: 12px;
            color: #2c3e50;
        }
        }
        .info-item span {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table thead {
            background: #34495e;
            color: white;
        }
        .items-table th {
            padding: 12px 10px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 13px;
            color: #2c3e50;
        }
        .items-table tbody tr:hover {
            background: #f8f9fa;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        /* Totals Section */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }
        .totals {
            width: 350px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            font-size: 13px;
        }
        .total-row.subtotal {
            border-top: 1px solid #ecf0f1;
        }
        .total-row.tax {
            background: #f8f9fa;
        }
        .total-row.grand-total {
            background: #34495e;
            color: white;
            font-size: 16px;
            font-weight: 700;
            border-radius: 6px;
            margin-top: 5px;
        }
        
        /* Footer */
        .invoice-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ecf0f1;
        }
        .footer-notes h4 {
            font-size: 13px;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .footer-notes p {
            font-size: 12px;
            color: #7f8c8d;
            line-height: 1.6;
        }
        .footer-contact {
            margin-top: 20px;
            text-align: center;
            font-size: 11px;
            color: #95a5a6;
        }
        
        /* Payment Status Badge */
        .payment-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .payment-status.paid {
            background: #d4edda;
            color: #155724;
        }
        .payment-status.unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Print Styles */
        @media print {
            body { background: white; }
            .print-controls { display: none !important; }
            .page-container {
                width: 100%;
                margin: 0;
                box-shadow: none;
            }
            .invoice { padding: 10mm; }
            
            /* Compact header */
            .invoice-header { margin-bottom: 12px; padding-bottom: 8px; }
            .company-info h1 { font-size: 20px; margin-bottom: 2px; }
            .company-info p { font-size: 9px; margin: 1px 0; line-height: 1.3; }
            .company-info img { max-height: 40px !important; margin-bottom: 4px !important; }
            .invoice-title h2 { font-size: 24px; margin-bottom: 2px; }
            .invoice-title p { font-size: 10px; }
            
            /* Compact order info */
            .order-info { padding: 8px 12px; margin-bottom: 10px; gap: 12px; }
            .info-item label { font-size: 8px; margin-bottom: 2px; }
            .info-item span { font-size: 10px; }
            .payment-status { padding: 3px 6px; font-size: 9px; }
            
            /* Compact payment details */
            .payment-details-box { padding: 8px 12px; margin-bottom: 12px; }
            .payment-details-box h3 { font-size: 10px; margin-bottom: 6px; }
            .payment-grid { gap: 6px; }
            .payment-detail-item label { font-size: 9px; }
            .payment-detail-item span { font-size: 9px; }
            
            /* Compact addresses */
            .addresses { gap: 15px; margin-bottom: 12px; }
            .address-block h3 { font-size: 9px; margin-bottom: 5px; }
            .address-block strong { font-size: 11px; }
            .address-block p { font-size: 9px; margin: 1px 0; line-height: 1.3; }
            
            /* Compact table */
            .items-table { margin-bottom: 12px; }
            .items-table th { padding: 6px 4px; font-size: 8px; }
            .items-table td { padding: 6px 4px; font-size: 9px; }
            
            /* Compact totals */
            .totals-section { margin-bottom: 12px; }
            .totals { width: 280px; }
            .total-row { padding: 5px 10px; font-size: 10px; }
            .total-row.grand-total { font-size: 13px; padding: 6px 10px; }
            
            /* Compact footer */
            .invoice-footer { margin-top: 12px; padding-top: 10px; }
            .footer-notes h4 { font-size: 10px; margin-bottom: 4px; }
            .footer-notes p { font-size: 9px; line-height: 1.3; }
            .footer-contact { margin-top: 10px; font-size: 8px; }
        }
        
        /* Page Break Handling */
        @media print {
            .items-table { page-break-inside: auto; }
            .items-table tr { page-break-inside: avoid; page-break-after: auto; }
            .items-table thead { display: table-header-group; }
            .items-table tfoot { display: table-footer-group; }
            .totals-section, .invoice-footer { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="print-controls">
        <a href="orders.php?view=<?php echo $order['id']; ?>" class="btn-back">← Back</a>
        <button onclick="window.print()" class="btn-print">
            <span>🖨️</span> Print Invoice
        </button>
    </div>
    
    <div class="page-container">
        <div class="invoice">
            <!-- Header -->
            <div class="invoice-header">
                <div class="company-info">
                    <?php if (!empty($site['logo_path'])): ?>
                    <img src="../<?php echo htmlspecialchars($site['logo_path']); ?>" alt="Logo" style="max-height: 60px; margin-bottom: 10px;">
                    <?php endif; ?>
                    <h1><?php echo htmlspecialchars($site['legal_company_name'] ?? $site['site_name']); ?></h1>
                    <p><?php echo htmlspecialchars($site['legal_address'] ?? $site['address']); ?></p>
                    <p>Phone: <?php echo htmlspecialchars($site['legal_phone'] ?? $site['phone']); ?></p>
                    <p>Email: <?php echo htmlspecialchars($site['legal_email'] ?? $site['email']); ?></p>
                    <?php if (!empty($site['legal_gstin'])): ?>
                    <p>GSTIN: <?php echo htmlspecialchars($site['legal_gstin']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($site['legal_pan'])): ?>
                    <p>PAN: <?php echo htmlspecialchars($site['legal_pan']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="invoice-title">
                    <h2>INVOICE</h2>
                    <p><?php echo htmlspecialchars($order['order_number']); ?></p>
                </div>
            </div>
            
            <!-- Order Info Bar -->
            <div class="order-info">
                <div class="info-item">
                    <label>Invoice Date</label>
                    <span><?php echo date('d M Y', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <label>Order Status</label>
                    <span><?php echo ucfirst($order['status']); ?></span>
                </div>
                <div class="info-item">
                    <label>Payment Status</label>
                    <span class="payment-status <?php echo ($order['payment_status'] ?? 'unpaid'); ?>">
                        <?php echo ucfirst($order['payment_status'] ?? 'unpaid'); ?>
                    </span>
                </div>
                <?php if (($order['payment_status'] ?? 'unpaid') === 'paid'): ?>
                <div class="info-item">
                    <label>Payment Date</label>
                    <span><?php echo $order['payment_date'] ? date('d M Y', strtotime($order['payment_date'])) : '-'; ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (($order['payment_status'] ?? 'unpaid') === 'paid' && ($order['payment_transaction_id'] || $order['payment_mode'])): ?>
            <!-- Payment Details -->
            <div class="payment-details-box">
                <h3>Payment Details</h3>
                <div class="payment-grid">
                    <?php if ($order['payment_mode']): ?>
                    <div class="payment-detail-item">
                        <label>Payment Method:</label>
                        <span><?php echo ucwords(str_replace('_', ' ', $order['payment_mode'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['payment_transaction_id']): ?>
                    <div class="payment-detail-item">
                        <label>Transaction ID:</label>
                        <span><?php echo htmlspecialchars($order['payment_transaction_id']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['payment_notes']): ?>
                    <div class="payment-detail-item" style="grid-column: 1 / -1;">
                        <label>Notes:</label>
                        <span><?php echo htmlspecialchars($order['payment_notes']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Addresses -->
            <div class="addresses">
                <div class="address-block">
                    <h3>Bill To</h3>
                    <strong><?php echo htmlspecialchars($order['billing_name']); ?></strong>
                    <p><?php echo htmlspecialchars($order['billing_address']); ?></p>
                    <p><?php echo htmlspecialchars($order['billing_city'] . ', ' . $order['billing_state'] . ' - ' . $order['billing_pincode']); ?></p>
                    <p>Phone: <?php echo htmlspecialchars($order['billing_phone']); ?></p>
                    <?php if ($order['gstin']): ?>
                    <p>GSTIN: <?php echo htmlspecialchars($order['gstin']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="address-block">
                    <h3>Ship To</h3>
                    <?php if ($order['shipping_same_as_billing']): ?>
                    <p><em>Same as billing address</em></p>
                    <?php else: ?>
                    <strong><?php echo htmlspecialchars($order['shipping_name']); ?></strong>
                    <p><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                    <p><?php echo htmlspecialchars($order['shipping_city'] . ', ' . $order['shipping_state'] . ' - ' . $order['shipping_pincode']); ?></p>
                    <p>Phone: <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 35%;">Product</th>
                        <th style="width: 15%;">SKU</th>
                        <th style="width: 10%;" class="text-center">Qty</th>
                        <th style="width: 12%;" class="text-right">Unit Price</th>
                        <th style="width: 10%;" class="text-center">Tax %</th>
                        <th style="width: 13%;" class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $itemNumber = 1;
                    foreach ($orderItems as $item): 
                        $unitPrice = $item['unit_price'];
                        $qty = $item['quantity'];
                        $lineTotal = $unitPrice * $qty;
                    ?>
                    <tr>
                        <td><?php echo $itemNumber++; ?></td>
                        <td>
                            <?php echo htmlspecialchars($item['product_name']); ?>
                            <?php if ($item['is_custom_item']): ?>
                            <span style="font-size: 10px; color: #f39c12;">(Custom)</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['product_sku'] ?? '-'); ?></td>
                        <td class="text-center"><?php echo $qty; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                        <td class="text-right">₹<?php echo number_format($unitPrice, 2); ?></td>
                        <td class="text-center"><?php echo number_format($item['tax_rate'], 2); ?>%</td>
                        <td class="text-right">₹<?php echo number_format($lineTotal, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Totals -->
            <div class="totals-section">
                <div class="totals">
                    <div class="total-row subtotal">
                        <span>Subtotal (Gross):</span>
                        <strong>₹<?php echo number_format($order['subtotal'], 2); ?></strong>
                    </div>
                    <div class="total-row tax">
                        <span>Tax Amount:</span>
                        <strong>₹<?php echo number_format($order['tax_amount'], 2); ?></strong>
                    </div>
                    <div class="total-row grand-total">
                        <span>Total Amount:</span>
                        <strong>₹<?php echo number_format($order['total_amount'], 2); ?></strong>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="invoice-footer">
                <?php if ($order['delivery_instructions']): ?>
                <div class="footer-notes">
                    <h4>Delivery Instructions:</h4>
                    <p><?php echo htmlspecialchars($order['delivery_instructions']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="footer-contact">
                    <p>Thank you for your business!</p>
                    <p><?php echo htmlspecialchars($site['site_name']); ?> | <?php echo htmlspecialchars($site['phone']); ?> | <?php echo htmlspecialchars($site['email']); ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
