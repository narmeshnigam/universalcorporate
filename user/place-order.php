<?php
session_start();
require_once '../config/database.php';
require_once '../config/email.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT email FROM site_users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $items = $_POST['items'] ?? [];
        if (empty($items)) throw new Exception('Please add at least one item.');
        
        $subtotal = 0; $taxAmount = 0; $validItems = []; $hasUnpricedItems = false;
        
        foreach ($items as $item) {
            if (empty($item['quantity']) || $item['quantity'] < 1) continue;
            $qty = (int)$item['quantity'];
            $isCustom = !empty($item['is_custom']);
            
            if ($isCustom) {
                $productName = trim($item['product_name'] ?? '');
                if (empty($productName)) continue;
                $stmt = $pdo->prepare("INSERT INTO products (name, unit, is_user_added, added_by_user_id) VALUES (?, 'piece', 1, ?)");
                $stmt->execute([$productName, $userId]);
                $validItems[] = ['product_id' => $pdo->lastInsertId(), 'product_name' => $productName, 'product_sku' => null, 'unit' => 'piece', 'quantity' => $qty, 'unit_price' => null, 'tax_rate' => 18.00, 'tax_amount' => null, 'line_total' => null, 'is_custom' => true];
                $hasUnpricedItems = true;
            } else {
                if (empty($item['product_id'])) continue;
                $stmt = $pdo->prepare("SELECT id, sku, name, unit, unit_price, tax_rate FROM products WHERE id = ? AND is_active = 1");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch();
                if (!$product) continue;
                
                $priceInclTax = $product['unit_price'] * $qty;
                $taxRate = $product['tax_rate'];
                $lineSubtotal = $priceInclTax / (1 + ($taxRate / 100));
                $lineTax = $priceInclTax - $lineSubtotal;
                
                $validItems[] = ['product_id' => $product['id'], 'product_name' => $product['name'], 'product_sku' => $product['sku'], 'unit' => $product['unit'], 'quantity' => $qty, 'unit_price' => $product['unit_price'], 'tax_rate' => $product['tax_rate'], 'tax_amount' => $lineTax, 'line_total' => $priceInclTax, 'is_custom' => false];
                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;
            }
        }
        
        if (empty($validItems)) throw new Exception('No valid items in the order.');
        $totalAmount = $subtotal + $taxAmount;
        $shippingSame = isset($_POST['shipping_same']) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO orders (order_number, user_id, has_unpriced_items, billing_name, billing_email, billing_phone, billing_address, billing_city, billing_state, billing_pincode, billing_country, shipping_same_as_billing, shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_pincode, shipping_country, gstin, pan_number, company_name, subtotal, tax_amount, total_amount, payment_mode, preferred_delivery_date, delivery_instructions, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$orderNumber, $userId, $hasUnpricedItems ? 1 : 0, $_POST['billing_name'], $_POST['billing_email'], $_POST['billing_phone'], $_POST['billing_address'], $_POST['billing_city'], $_POST['billing_state'], $_POST['billing_pincode'], $_POST['billing_country'] ?? 'India', $shippingSame, $shippingSame ? null : $_POST['shipping_name'], $shippingSame ? null : $_POST['shipping_phone'], $shippingSame ? null : $_POST['shipping_address'], $shippingSame ? null : $_POST['shipping_city'], $shippingSame ? null : $_POST['shipping_state'], $shippingSame ? null : $_POST['shipping_pincode'], $shippingSame ? null : ($_POST['shipping_country'] ?? 'India'), $_POST['gstin'] ?: null, $_POST['pan_number'] ?: null, $_POST['company_name'] ?: null, $subtotal, $taxAmount, $totalAmount, $_POST['payment_mode'], $_POST['delivery_date'] ?: null, $_POST['delivery_instructions'] ?: null, $_POST['notes'] ?: null]);
        
        $orderId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_sku, unit, quantity, unit_price, tax_rate, tax_amount, line_total, is_custom_item) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($validItems as $item) {
            $stmt->execute([$orderId, $item['product_id'], $item['product_name'], $item['product_sku'], $item['unit'], $item['quantity'], $item['unit_price'], $item['tax_rate'], $item['tax_amount'], $item['line_total'], $item['is_custom'] ? 1 : 0]);
        }
        $pdo->commit();
        
        // Send email notification to admin
        sendOrderNotificationAsync($pdo, $orderId);
        
        $success = $hasUnpricedItems ? "Order placed! Order #: <strong>{$orderNumber}</strong><br><small>Custom items will be priced and confirmed via email.</small>" : "Order placed successfully! Order #: <strong>{$orderNumber}</strong>";
    } catch (Exception $e) { $pdo->rollBack(); $error = $e->getMessage(); }
}

$pageTitle = 'Place Order';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header"><h1>Place New Order</h1></div>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="POST" id="orderForm" class="order-form">
        <input type="hidden" name="has_custom_items" id="hasCustomItems" value="0">
        
        <!-- Address Section: Billing & Shipping Side by Side -->
        <div class="address-row">
            <div class="address-card">
                <h3>Billing Address</h3>
                <div class="compact-grid">
                    <div class="field full"><label>Full Name *</label><input type="text" name="billing_name" required></div>
                    <div class="field"><label>Email *</label><input type="email" name="billing_email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
                    <div class="field"><label>Phone *</label><input type="tel" name="billing_phone" required></div>
                    <div class="field full"><label>Address *</label><input type="text" name="billing_address" required></div>
                    <div class="field"><label>City *</label><input type="text" name="billing_city" required></div>
                    <div class="field"><label>State *</label><input type="text" name="billing_state" required></div>
                    <div class="field"><label>PIN Code *</label><input type="text" name="billing_pincode" required></div>
                    <div class="field"><label>Country</label><input type="text" name="billing_country" value="India"></div>
                </div>
            </div>
            <div class="address-card">
                <h3>Shipping Address</h3>
                <label class="same-check"><input type="checkbox" name="shipping_same" id="shippingSame" checked> Same as billing</label>
                <div id="shippingFields" class="compact-grid" style="display:none;">
                    <div class="field full"><label>Recipient Name</label><input type="text" name="shipping_name"></div>
                    <div class="field"><label>Phone</label><input type="tel" name="shipping_phone"></div>
                    <div class="field full"><label>Address</label><input type="text" name="shipping_address"></div>
                    <div class="field"><label>City</label><input type="text" name="shipping_city"></div>
                    <div class="field"><label>State</label><input type="text" name="shipping_state"></div>
                    <div class="field"><label>PIN Code</label><input type="text" name="shipping_pincode"></div>
                    <div class="field"><label>Country</label><input type="text" name="shipping_country" value="India"></div>
                </div>
            </div>
        </div>

        <!-- Tax & Payment Row -->
        <div class="info-row">
            <div class="info-card">
                <h3>Tax Information <span class="opt">(Optional)</span></h3>
                <div class="compact-grid three-col">
                    <div class="field"><label>Company</label><input type="text" name="company_name"></div>
                    <div class="field"><label>GSTIN</label><input type="text" name="gstin" placeholder="22AAAAA0000A1Z5"></div>
                    <div class="field"><label>PAN</label><input type="text" name="pan_number" placeholder="AAAAA0000A"></div>
                </div>
            </div>
            <div class="info-card">
                <h3>Preferences <span class="opt">(No payment now)</span></h3>
                <div class="compact-grid three-col">
                    <div class="field"><label>Preferred Payment</label><select name="payment_mode" required><option value="bank_transfer">Bank Transfer</option><option value="upi">UPI</option><option value="cheque">Cheque</option><option value="cash">Cash on Delivery</option><option value="credit">Credit (30 Days)</option></select></div>
                    <div class="field"><label>Preferred Delivery</label><input type="date" name="delivery_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"></div>
                    <div class="field"><label>Instructions</label><input type="text" name="delivery_instructions" placeholder="Special instructions..."></div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="items-card">
            <div class="items-header">
                <h3>Order Items</h3>
                <div class="search-box">
                    <input type="text" id="productSearch" placeholder="Search products..." autocomplete="off">
                    <div id="searchResults" class="search-results"></div>
                </div>
            </div>
            <div id="estimateNotice" class="estimate-notice" style="display:none;">⚠️ Order contains unpriced items. Totals are estimates only.</div>
            <div class="table-wrap">
                <table class="items-table" id="itemsTable">
                    <thead><tr><th>Product</th><th>SKU</th><th>Unit Price</th><th>Qty</th><th>Gross</th><th>Tax %</th><th>Tax Amt</th><th>Net Total</th><th></th></tr></thead>
                    <tbody id="itemsBody"><tr class="empty-row"><td colspan="9">No items added. Search products above.</td></tr></tbody>
                    <tfoot><tr><td colspan="4" class="text-right"><strong>Totals:</strong></td><td id="subtotalDisplay"><strong>₹0.00</strong></td><td></td><td id="taxDisplay"><strong>₹0.00</strong></td><td id="totalDisplay"><strong>₹0.00</strong></td><td></td></tr></tfoot>
                </table>
            </div>
        </div>

        <div class="submit-row"><button type="submit" class="btn-submit">Place Order</button></div>
    </form>
</main>

<style>
/* Order Form Container */
.order-form { max-width: 100%; overflow: hidden; }

.address-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.address-card, .info-card, .items-card { background: #fff; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); overflow: hidden; }
.address-card h3, .info-card h3, .items-card h3 { font-size: 14px; font-weight: 600; color: #2c3e50; margin: 0 0 12px; text-transform: uppercase; letter-spacing: 0.5px; }
.address-card h3 .opt, .info-card h3 .opt { font-weight: 400; color: #95a5a6; text-transform: none; }
.same-check { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #555; margin-bottom: 12px; cursor: pointer; }
.same-check input { width: 16px; height: 16px; }

.compact-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.compact-grid.three-col { grid-template-columns: 1fr 1fr 1fr; }
.compact-grid .field { display: flex; flex-direction: column; min-width: 0; }
.compact-grid .field.full { grid-column: span 2; }
.compact-grid .field label { font-size: 11px; font-weight: 500; color: #666; margin-bottom: 4px; text-transform: uppercase; }
.compact-grid .field input, .compact-grid .field select { padding: 8px 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 13px; width: 100%; box-sizing: border-box; min-width: 0; }
.compact-grid .field input:focus, .compact-grid .field select:focus { outline: none; border-color: #3498db; }

.info-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }

.items-card { margin-bottom: 16px; }
.items-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 12px; }
.search-box { position: relative; width: 280px; flex-shrink: 0; }
.search-box input { width: 100%; padding: 9px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
.search-box input:focus { outline: none; border-color: #3498db; }
.search-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ddd; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.12); max-height: 280px; overflow-y: auto; z-index: 100; display: none; }
.search-results.show { display: block; }
.search-result-item { padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 10px; }
.search-result-item:hover { background: #f8f9fa; }
.search-result-item:last-child { border-bottom: none; }
.search-result-img { width: 40px; height: 40px; border-radius: 5px; object-fit: cover; border: 1px solid #eee; flex-shrink: 0; }
.search-result-img.no-img { background: #f5f5f5; display: flex; align-items: center; justify-content: center; font-size: 8px; color: #999; }
.search-result-info { flex: 1; min-width: 0; }
.search-result-item .product-name { font-weight: 500; color: #2c3e50; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.search-result-item .product-meta { font-size: 11px; color: #7f8c8d; margin-top: 2px; }
.search-result-item.add-new { background: #e8f4fd; border-left: 3px solid #3498db; }
.search-result-item.add-new:hover { background: #d4ebf9; }
.search-result-item.add-new .product-name { color: #2980b9; }

.estimate-notice { background: #fff3cd; color: #856404; padding: 10px 14px; border-radius: 5px; font-size: 13px; margin-bottom: 12px; }

.table-wrap { overflow-x: auto; margin: 0 -20px; padding: 0 20px; }
.items-table { width: 100%; min-width: 700px; border-collapse: collapse; font-size: 13px; table-layout: auto; }
.items-table th { background: #f8f9fa; color: #5a6a7a; font-size: 10px; font-weight: 600; text-transform: uppercase; padding: 10px 6px; text-align: left; border-bottom: 2px solid #e0e0e0; white-space: nowrap; }
.items-table td { padding: 10px 6px; border-bottom: 1px solid #eee; vertical-align: middle; }
.items-table .empty-row td { text-align: center; color: #95a5a6; padding: 25px; }
.items-table input[type="number"] { width: 55px; padding: 5px 4px; border: 1px solid #ddd; border-radius: 4px; text-align: center; font-size: 13px; }
.items-table .btn-remove { background: #e74c3c; color: #fff; border: none; padding: 5px 8px; border-radius: 4px; cursor: pointer; font-size: 11px; }
.items-table .btn-remove:hover { background: #c0392b; }
.items-table tfoot td { background: #f8f9fa; border-top: 2px solid #e0e0e0; border-bottom: none; padding: 10px 6px; }
.items-table tr.custom-item { background: #fffbf0; }
.items-table .custom-badge { background: #f39c12; color: #fff; font-size: 9px; padding: 2px 5px; border-radius: 3px; margin-left: 4px; }
.items-table .tbd { color: #f39c12; font-style: italic; }
.items-table .unit-label { font-size: 11px; color: #888; margin-left: 3px; }
.text-right { text-align: right !important; }

.submit-row { background: #fff; padding: 16px 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); text-align: right; }
.btn-submit { background: #3498db; color: #fff; border: none; padding: 12px 36px; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn-submit:hover { background: #2980b9; }

@media (max-width: 900px) { 
    .address-row, .info-row { grid-template-columns: 1fr; } 
    .compact-grid.three-col { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) { 
    .compact-grid, .compact-grid.three-col { grid-template-columns: 1fr; } 
    .compact-grid .field.full { grid-column: span 1; }
    .search-box { width: 100%; }
    .items-header { flex-direction: column; align-items: stretch; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const shippingSame = document.getElementById('shippingSame');
    const shippingFields = document.getElementById('shippingFields');
    const productSearch = document.getElementById('productSearch');
    const searchResults = document.getElementById('searchResults');
    const itemsBody = document.getElementById('itemsBody');
    const estimateNotice = document.getElementById('estimateNotice');
    const hasCustomItemsInput = document.getElementById('hasCustomItems');
    let itemIndex = 0, searchTimeout;

    shippingSame.addEventListener('change', function() { shippingFields.style.display = this.checked ? 'none' : 'grid'; });

    productSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        if (query.length < 2) { searchResults.classList.remove('show'); return; }
        searchTimeout = setTimeout(() => {
            fetch('../api/search-products.php?q=' + encodeURIComponent(query))
                .then(res => res.ok ? res.json() : Promise.reject('Search failed'))
                .then(products => {
                    let html = '';
                    if (Array.isArray(products) && products.length > 0) {
                        const exactMatch = products.some(p => p.name.toLowerCase() === query.toLowerCase());
                        window._searchProducts = products;
                        html = products.map((p, idx) => {
                            const hasPrice = p.unit_price !== null && p.unit_price !== undefined;
                            const priceDisplay = hasPrice ? '₹' + parseFloat(p.unit_price).toFixed(2) : 'TBD';
                            const imgHtml = p.image_path ? `<img src="../${escapeHtml(p.image_path)}" class="search-result-img">` : `<div class="search-result-img no-img">No Img</div>`;
                            return `<div class="search-result-item" data-product-index="${idx}">${imgHtml}<div class="search-result-info"><div class="product-name">${escapeHtml(p.name)}</div><div class="product-meta">SKU: ${escapeHtml(p.sku || '-')} | ${priceDisplay}/${escapeHtml(p.unit)}</div></div></div>`;
                        }).join('');
                        if (!exactMatch) html += `<div class="search-result-item add-new" data-custom-name="${escapeHtml(query)}"><div class="product-name">+ Add "${escapeHtml(query)}"</div><div class="product-meta">Price confirmed before processing</div></div>`;
                    } else {
                        html = `<div class="search-result-item add-new" data-custom-name="${escapeHtml(query)}"><div class="product-name">+ Add "${escapeHtml(query)}"</div><div class="product-meta">Price confirmed before processing</div></div>`;
                    }
                    searchResults.innerHTML = html;
                    searchResults.classList.add('show');
                }).catch(() => {
                    searchResults.innerHTML = `<div class="search-result-item add-new" data-custom-name="${escapeHtml(query)}"><div class="product-name">+ Add "${escapeHtml(query)}"</div></div>`;
                    searchResults.classList.add('show');
                });
        }, 300);
    });

    searchResults.addEventListener('click', function(e) {
        const item = e.target.closest('.search-result-item');
        if (!item) return;
        if (item.dataset.customName) addCustomItem(item.dataset.customName);
        else if (item.dataset.productIndex !== undefined && window._searchProducts) addItem(window._searchProducts[parseInt(item.dataset.productIndex)]);
        productSearch.value = '';
        searchResults.classList.remove('show');
    });

    document.addEventListener('click', function(e) { if (!e.target.closest('.search-box')) searchResults.classList.remove('show'); });

    function addItem(product) {
        const emptyRow = itemsBody.querySelector('.empty-row');
        if (emptyRow) emptyRow.remove();
        const hasPrice = product.unit_price !== null && product.unit_price !== undefined && product.unit_price !== '';
        const isUnpriced = !hasPrice;
        const existing = itemsBody.querySelector(`tr[data-product-id="${product.id}"]`);
        if (existing) { const qtyInput = existing.querySelector('input[type="number"]'); qtyInput.value = parseInt(qtyInput.value) + 1; updateRowTotal(existing); return; }
        
        const row = document.createElement('tr');
        row.dataset.productId = product.id;
        if (isUnpriced) { row.classList.add('custom-item'); row.dataset.isUnpriced = 'true'; }
        const priceDisplay = hasPrice ? '₹' + parseFloat(product.unit_price).toFixed(2) : '<span class="tbd">TBD</span>';
        const priceValue = hasPrice ? product.unit_price : 0;
        const taxRate = parseFloat(product.tax_rate) || 18;
        row.innerHTML = `<td>${escapeHtml(product.name)}${isUnpriced ? ' <span class="custom-badge">UNPRICED</span>' : ''}</td><td>${escapeHtml(product.sku || '-')}</td><td>${priceDisplay}</td><td><input type="hidden" name="items[${itemIndex}][product_id]" value="${product.id}"><input type="hidden" name="items[${itemIndex}][is_custom]" value="0"><input type="number" name="items[${itemIndex}][quantity]" value="1" min="1" class="qty-input" data-price="${priceValue}" data-tax="${taxRate}" data-unpriced="${isUnpriced ? '1' : '0'}"><span class="unit-label">${escapeHtml(product.unit)}</span></td><td class="item-gross">${isUnpriced ? '<span class="tbd">TBD</span>' : '₹0.00'}</td><td class="item-tax-rate">${taxRate.toFixed(2)}%</td><td class="item-tax">${isUnpriced ? '<span class="tbd">TBD</span>' : '₹0.00'}</td><td class="item-total">${isUnpriced ? '<span class="tbd">TBD</span>' : '₹0.00'}</td><td><button type="button" class="btn-remove">✕</button></td>`;
        itemsBody.appendChild(row);
        itemIndex++;
        if (!isUnpriced) updateRowTotal(row);
        updateCustomItemsState();
    }

    function addCustomItem(name) {
        const emptyRow = itemsBody.querySelector('.empty-row');
        if (emptyRow) emptyRow.remove();
        const row = document.createElement('tr');
        row.classList.add('custom-item');
        row.dataset.productId = 'custom-' + itemIndex;
        row.dataset.isCustom = 'true';
        row.innerHTML = `<td>${escapeHtml(name)} <span class="custom-badge">NEW</span></td><td>-</td><td class="tbd">TBD</td><td><input type="hidden" name="items[${itemIndex}][is_custom]" value="1"><input type="hidden" name="items[${itemIndex}][product_name]" value="${escapeHtml(name)}"><input type="number" name="items[${itemIndex}][quantity]" value="1" min="1" class="qty-input" data-price="0" data-tax="18"><span class="unit-label">pc</span></td><td class="tbd">TBD</td><td>18.00%</td><td class="tbd">TBD</td><td class="tbd">TBD</td><td><button type="button" class="btn-remove">✕</button></td>`;
        itemsBody.appendChild(row);
        itemIndex++;
        updateCustomItemsState();
    }

    function updateRowTotal(row) {
        if (row.dataset.isCustom || row.dataset.isUnpriced) return;
        const qtyInput = row.querySelector('.qty-input');
        if (qtyInput.dataset.unpriced === '1') return;
        const qty = parseInt(qtyInput.value) || 0;
        const priceInclTax = parseFloat(qtyInput.dataset.price) || 0;
        const taxRate = parseFloat(qtyInput.dataset.tax) || 0;
        const netTotal = priceInclTax * qty;
        const grossTotal = taxRate > 0 ? netTotal / (1 + (taxRate / 100)) : netTotal;
        const taxAmount = netTotal - grossTotal;
        row.querySelector('.item-gross').textContent = '₹' + grossTotal.toFixed(2);
        row.querySelector('.item-tax').textContent = '₹' + taxAmount.toFixed(2);
        row.querySelector('.item-total').textContent = '₹' + netTotal.toFixed(2);
        updateTotals();
    }

    function updateTotals() {
        let totalGross = 0, totalTax = 0, totalNet = 0;
        itemsBody.querySelectorAll('tr[data-product-id]').forEach(row => {
            if (row.dataset.isCustom || row.dataset.isUnpriced) return;
            const qtyInput = row.querySelector('.qty-input');
            if (qtyInput.dataset.unpriced === '1') return;
            const qty = parseInt(qtyInput.value) || 0, priceInclTax = parseFloat(qtyInput.dataset.price) || 0, taxRate = parseFloat(qtyInput.dataset.tax) || 0;
            const lineNet = priceInclTax * qty, lineGross = taxRate > 0 ? lineNet / (1 + (taxRate / 100)) : lineNet;
            totalGross += lineGross; totalTax += lineNet - lineGross; totalNet += lineNet;
        });
        const hasCustom = itemsBody.querySelector('tr[data-is-custom]') !== null || itemsBody.querySelector('tr[data-is-unpriced]') !== null;
        const suffix = hasCustom ? ' *' : '';
        document.getElementById('subtotalDisplay').innerHTML = '<strong>₹' + totalGross.toFixed(2) + suffix + '</strong>';
        document.getElementById('taxDisplay').innerHTML = '<strong>₹' + totalTax.toFixed(2) + suffix + '</strong>';
        document.getElementById('totalDisplay').innerHTML = '<strong>₹' + totalNet.toFixed(2) + suffix + '</strong>';
    }

    function updateCustomItemsState() {
        const hasCustom = itemsBody.querySelector('tr[data-is-custom]') !== null || itemsBody.querySelector('tr[data-is-unpriced]') !== null;
        estimateNotice.style.display = hasCustom ? 'block' : 'none';
        hasCustomItemsInput.value = hasCustom ? '1' : '0';
        updateTotals();
    }

    itemsBody.addEventListener('input', function(e) { if (e.target.classList.contains('qty-input')) { const row = e.target.closest('tr'); if (!row.dataset.isCustom) updateRowTotal(row); } });
    itemsBody.addEventListener('click', function(e) { if (e.target.classList.contains('btn-remove')) { e.target.closest('tr').remove(); updateCustomItemsState(); if (!itemsBody.querySelector('tr[data-product-id]')) itemsBody.innerHTML = '<tr class="empty-row"><td colspan="9">No items added.</td></tr>'; } });
    document.getElementById('orderForm').addEventListener('submit', function(e) { if (!itemsBody.querySelector('tr[data-product-id]')) { e.preventDefault(); alert('Please add at least one item.'); } });
    function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
});
</script>
<?php include 'includes/footer.php'; ?>
