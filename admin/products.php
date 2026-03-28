<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$message = '';
$messageType = '';
$baseDir = dirname(__DIR__);
$uploadDir = $baseDir . '/assets/products/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $id = $action === 'update' ? (int)($_POST['product_id'] ?? 0) : 0;
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '') ?: null;
        $description = trim($_POST['description'] ?? '') ?: null;
        $unit = trim($_POST['unit'] ?? 'piece');
        $unitPrice = is_numeric($_POST['unit_price'] ?? '') ? (float)$_POST['unit_price'] : null;
        $taxRate = is_numeric($_POST['tax_rate'] ?? '') ? (float)$_POST['tax_rate'] : 18.00;
        
        $specs = [];
        if (!empty($_POST['spec_key']) && is_array($_POST['spec_key'])) {
            foreach ($_POST['spec_key'] as $i => $key) {
                $key = trim($key);
                $val = trim($_POST['spec_value'][$i] ?? '');
                if ($key && $val) $specs[$key] = $val;
            }
        }
        $specsJson = !empty($specs) ? json_encode($specs) : null;
        
        if (!$name) {
            $message = 'Product name is required.';
            $messageType = 'error';
        } else {
            // Handle image upload
            $imagePath = $_POST['existing_image'] ?? null;
            if ($imagePath === '') $imagePath = null;
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] > 0) {
                $file = $_FILES['image'];
                
                // Validate file type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                if (in_array($mimeType, $allowedTypes)) {
                    if ($file['size'] <= 5 * 1024 * 1024) {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                            $ext = 'jpg'; // Default extension
                        }
                        $filename = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $targetPath = $uploadDir . $filename;
                        
                        // Ensure directory is writable
                        if (!is_writable($uploadDir)) {
                            @chmod($uploadDir, 0777);
                        }
                        
                        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                            // Delete old image if exists
                            if ($imagePath && file_exists($baseDir . '/' . $imagePath)) {
                                @unlink($baseDir . '/' . $imagePath);
                            }
                            $imagePath = 'assets/products/' . $filename;
                        } else {
                            $message = 'Failed to upload image. Check directory permissions.';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'Image must be under 5MB.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Only JPG, PNG, WebP images allowed. Got: ' . $mimeType;
                    $messageType = 'error';
                }
            }
            
            // Only proceed if no error
            if ($messageType !== 'error') {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO products (sku, name, description, image_path, specifications, unit, unit_price, tax_rate, is_user_added) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
                    $stmt->execute([$sku, $name, $description, $imagePath, $specsJson, $unit, $unitPrice, $taxRate]);
                    $message = 'Product added successfully.';
                } else {
                    $stmt = $pdo->prepare("UPDATE products SET sku=?, name=?, description=?, image_path=?, specifications=?, unit=?, unit_price=?, tax_rate=? WHERE id=?");
                    $stmt->execute([$sku, $name, $description, $imagePath, $specsJson, $unit, $unitPrice, $taxRate, $id]);
                    $message = 'Product updated successfully.';
                }
                $messageType = 'success';
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['product_id'] ?? 0);
        $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $message = 'Status updated.';
        $messageType = 'success';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['product_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT image_path FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if ($product) {
            if ($product['image_path'] && file_exists($baseDir . '/' . $product['image_path'])) @unlink($baseDir . '/' . $product['image_path']);
            $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
            $message = 'Product deleted.';
            $messageType = 'success';
        }
    }

    if ($action === 'convert') {
        $id = (int)($_POST['product_id'] ?? 0);
        $pdo->prepare("UPDATE products SET is_user_added = 0, added_by_user_id = NULL WHERE id = ?")->execute([$id]);
        $message = 'Product converted to admin product.';
        $messageType = 'success';
    }
}

$filter = $_GET['filter'] ?? 'admin';
$whereClause = $filter === 'user' ? 'is_user_added = 1' : 'is_user_added = 0';
$products = $pdo->query("SELECT * FROM products WHERE $whereClause ORDER BY created_at DESC")->fetchAll();
$adminCount = $pdo->query("SELECT COUNT(*) FROM products WHERE is_user_added = 0")->fetchColumn();
$userCount = $pdo->query("SELECT COUNT(*) FROM products WHERE is_user_added = 1")->fetchColumn();

$pageTitle = 'Products - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Products</h1>
        <div class="header-actions">
            <button class="btn-primary btn-add" onclick="openModal()">+ Add Product</button>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="filter-tabs">
        <a href="?filter=admin" class="tab <?php echo $filter === 'admin' ? 'active' : ''; ?>">Admin Products <span class="count"><?php echo $adminCount; ?></span></a>
        <a href="?filter=user" class="tab <?php echo $filter === 'user' ? 'active' : ''; ?>">User Added <span class="count"><?php echo $userCount; ?></span></a>
    </div>

    <div class="dashboard-section">
        <?php if ($filter === 'user'): ?>
        <p class="section-note">Products added by users during order placement. Convert them to make available in catalog.</p>
        <?php endif; ?>
        
        <?php if (empty($products)): ?>
        <div class="activity-card"><p>No products found.</p></div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): 
                        $specs = $product['specifications'] ? json_decode($product['specifications'], true) : [];
                    ?>
                    <tr>
                        <td class="img-cell">
                            <?php if ($product['image_path']): ?>
                            <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="" class="product-thumb">
                            <?php else: ?>
                            <div class="no-image">No Image</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                            <?php if ($product['description']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?><?php echo strlen($product['description']) > 50 ? '...' : ''; ?></small>
                            <?php endif; ?>
                            <?php if (!empty($specs)): ?>
                            <br><span class="specs-badge"><?php echo count($specs); ?> specs</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($product['sku'] ?? '-'); ?></td>
                        <td>
                            <?php if ($product['unit_price']): ?>
                            ₹<?php echo number_format($product['unit_price'], 2); ?>
                            <?php else: ?>
                            <span class="tbd">TBD</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($product['unit']); ?></td>
                        <td>
                            <span class="status-pill <?php echo $product['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="actions-cell">
                            <button class="btn-sm btn-save" onclick='editProduct(<?php echo json_encode($product); ?>)'>Edit</button>
                            <?php if ($filter === 'user'): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Convert to admin product?')">
                                <input type="hidden" name="action" value="convert">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" class="btn-sm btn-activate">Convert</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" class="btn-sm <?php echo $product['is_active'] ? 'btn-warn' : 'btn-activate'; ?>"><?php echo $product['is_active'] ? 'Disable' : 'Enable'; ?></button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this product?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" class="btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Product Modal -->
<div id="productModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add Product</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="productForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="product_id" id="productId" value="">
            <input type="hidden" name="existing_image" id="existingImage" value="">
            
            <div class="modal-body">
                <div class="form-grid">
                    <!-- Row 1: Name & SKU -->
                    <div class="form-group col-8">
                        <label>Product Name <span class="required">*</span></label>
                        <input type="text" name="name" id="prodName" required>
                    </div>
                    <div class="form-group col-4">
                        <label>SKU</label>
                        <input type="text" name="sku" id="prodSku" placeholder="PRD-001">
                    </div>
                    
                    <!-- Row 2: Price, Unit, Tax Rate -->
                    <div class="form-group col-4">
                        <label>Unit Price (₹) <small>incl. tax</small></label>
                        <input type="number" name="unit_price" id="prodPrice" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group col-4">
                        <label>Unit</label>
                        <select name="unit" id="prodUnit">
                            <option value="piece">Piece</option>
                            <option value="pack">Pack</option>
                            <option value="box">Box</option>
                            <option value="set">Set</option>
                            <option value="kg">Kg</option>
                            <option value="liter">Liter</option>
                            <option value="meter">Meter</option>
                            <option value="ream">Ream</option>
                            <option value="dozen">Dozen</option>
                            <option value="pair">Pair</option>
                            <option value="can">Can</option>
                            <option value="bottle">Bottle</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label>Tax Rate (%)</label>
                        <input type="number" name="tax_rate" id="prodTax" step="0.01" value="18">
                    </div>
                    
                    <!-- Row 3: Description (full width) -->
                    <div class="form-group col-12">
                        <label>Description</label>
                        <textarea name="description" id="prodDesc" rows="2" placeholder="Brief product description..."></textarea>
                    </div>
                    
                    <!-- Row 4: Image -->
                    <div class="form-group col-12">
                        <label>Image</label>
                        <div class="image-upload-box">
                            <input type="file" name="image" id="prodImage" accept="image/*">
                            <div id="currentImage" class="current-thumb"></div>
                        </div>
                    </div>
                    
                    <!-- Row 5: Specifications -->
                    <div class="form-group col-12">
                        <label>Specifications</label>
                        <div id="specsContainer"></div>
                        <button type="button" class="btn-add-spec" onclick="addSpecRow()">+ Add Specification</button>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-submit" id="submitBtn">Add Product</button>
            </div>
        </form>
    </div>
</div>

<style>
.header-actions { flex-shrink: 0; }
.btn-add { padding: 8px 16px !important; font-size: 13px !important; width: auto !important; }
.btn-primary { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-primary:hover { background: #2980b9; }

.filter-tabs { display: flex; margin-bottom: 20px; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.filter-tabs .tab { flex: 1; padding: 15px 20px; text-decoration: none; color: #7f8c8d; text-align: center; border-bottom: 3px solid transparent; }
.filter-tabs .tab:hover { background: #f8f9fa; }
.filter-tabs .tab.active { color: #3498db; border-bottom-color: #3498db; background: #f8f9fa; font-weight: 600; }
.filter-tabs .count { background: #ecf0f1; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 5px; }
.filter-tabs .tab.active .count { background: #3498db; color: white; }

.section-note { color: #856404; background: #fff3cd; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }

.table-wrapper { overflow-x: auto; }
.img-cell { width: 70px; }
.product-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #eee; }
.no-image { width: 50px; height: 50px; background: #f5f5f5; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999; text-align: center; }
.text-muted { color: #7f8c8d; }
.specs-badge { background: #e8f4fd; color: #2980b9; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
.tbd { color: #f39c12; font-style: italic; }
.actions-cell { white-space: nowrap; }
.actions-cell .inline { display: inline; }
.actions-cell .btn-sm { margin-right: 4px; margin-bottom: 4px; }

/* Modal */
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
.modal.show { display: flex; }
.modal-content { background: white; border-radius: 8px; width: 100%; max-width: 620px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
.modal-header { padding: 16px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
.modal-header h2 { margin: 0; font-size: 18px; color: #2c3e50; font-weight: 600; }
.modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #95a5a6; line-height: 1; padding: 0; width: 28px; height: 28px; }
.modal-close:hover { color: #e74c3c; }
.modal-body { padding: 20px; overflow-y: auto; }
.modal-footer { padding: 16px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; }

/* Form Grid */
.form-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 16px 12px; }
.form-group { display: flex; flex-direction: column; }
.form-group.col-4 { grid-column: span 4; }
.form-group.col-8 { grid-column: span 8; }
.form-group.col-12 { grid-column: span 12; }
.form-group label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.3px; }
.form-group label small { text-transform: none; font-weight: 400; color: #888; }
.form-group .required { color: #e74c3c; }
.form-group input, .form-group select, .form-group textarea { padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; font-family: inherit; width: 100%; box-sizing: border-box; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52,152,219,0.1); }
.form-group textarea { resize: vertical; min-height: 70px; }

/* Image Upload */
.image-upload-box { display: flex; align-items: center; gap: 12px; }
.image-upload-box input[type="file"] { font-size: 13px; padding: 8px 0; }
.current-thumb { width: 50px; height: 50px; border-radius: 4px; overflow: hidden; border: 1px solid #ddd; flex-shrink: 0; display: none; background: #f5f5f5; }
.current-thumb img { width: 100%; height: 100%; object-fit: cover; }
.current-thumb.has-image { display: block; }

/* Specs */
#specsContainer { display: flex; flex-direction: column; gap: 6px; margin-bottom: 8px; }
.spec-row { display: flex; gap: 8px; align-items: center; }
.spec-row input { flex: 1; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; }
.btn-remove-spec { background: #e74c3c; color: white; border: none; width: 26px; height: 26px; border-radius: 4px; cursor: pointer; font-size: 14px; flex-shrink: 0; }
.btn-remove-spec:hover { background: #c0392b; }
.btn-add-spec { background: none; color: #3498db; border: 1px dashed #3498db; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-add-spec:hover { background: #f0f7fc; }

/* Footer Buttons */
.btn-cancel { background: #f5f5f5; color: #555; border: 1px solid #ddd; padding: 8px 20px; border-radius: 4px; font-size: 13px; cursor: pointer; }
.btn-cancel:hover { background: #eee; }
.btn-submit { background: #3498db; color: white; border: none; padding: 8px 24px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-submit:hover { background: #2980b9; }

@media (max-width: 600px) {
    .form-group.col-4, .form-group.col-8 { grid-column: span 12; }
    .modal-content { max-width: 100%; }
}
</style>

<script>
function openModal(product = null) {
    document.getElementById('productModal').classList.add('show');
    document.getElementById('productForm').reset();
    document.getElementById('specsContainer').innerHTML = '';
    document.getElementById('currentImage').innerHTML = '';
    document.getElementById('currentImage').classList.remove('has-image');
    
    if (product) {
        document.getElementById('modalTitle').textContent = 'Edit Product';
        document.getElementById('formAction').value = 'update';
        document.getElementById('submitBtn').textContent = 'Update Product';
        document.getElementById('productId').value = product.id;
        document.getElementById('prodName').value = product.name || '';
        document.getElementById('prodSku').value = product.sku || '';
        document.getElementById('prodPrice').value = product.unit_price || '';
        document.getElementById('prodUnit').value = product.unit || 'piece';
        document.getElementById('prodTax').value = product.tax_rate || 18;
        document.getElementById('prodDesc').value = product.description || '';
        document.getElementById('existingImage').value = product.image_path || '';
        
        if (product.image_path) {
            document.getElementById('currentImage').innerHTML = '<img src="../' + product.image_path + '">';
            document.getElementById('currentImage').classList.add('has-image');
        }
        
        if (product.specifications) {
            const specs = typeof product.specifications === 'string' ? JSON.parse(product.specifications) : product.specifications;
            for (const [key, val] of Object.entries(specs)) {
                addSpecRow(key, val);
            }
        }
    } else {
        document.getElementById('modalTitle').textContent = 'Add Product';
        document.getElementById('formAction').value = 'add';
        document.getElementById('submitBtn').textContent = 'Add Product';
        document.getElementById('productId').value = '';
        document.getElementById('existingImage').value = '';
    }
}

function editProduct(product) {
    openModal(product);
}

function closeModal() {
    document.getElementById('productModal').classList.remove('show');
}

function addSpecRow(key = '', val = '') {
    const container = document.getElementById('specsContainer');
    const row = document.createElement('div');
    row.className = 'spec-row';
    row.innerHTML = `
        <input type="text" name="spec_key[]" placeholder="Key (e.g., Color)" value="${escapeHtml(key)}">
        <input type="text" name="spec_value[]" placeholder="Value (e.g., Blue)" value="${escapeHtml(val)}">
        <button type="button" class="btn-remove-spec" onclick="this.parentElement.remove()">✕</button>
    `;
    container.appendChild(row);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

document.getElementById('productModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

<?php include 'includes/footer.php'; ?>
