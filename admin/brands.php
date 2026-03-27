<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$pdo->exec("CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    logo_path VARCHAR(500) NOT NULL,
    brand_name VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$message = '';
$messageType = '';
$baseDir = dirname(__DIR__);
$uploadDir = $baseDir . '/assets/brands/';
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

function uploadBrandLogo($file, $uploadDir, $allowed) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) return ['success' => false, 'error' => 'No file uploaded'];
    if (!in_array($file['type'], $allowed)) return ['success' => false, 'error' => 'Only JPG, PNG, WebP, SVG allowed'];
    if ($file['size'] > 2 * 1024 * 1024) return ['success' => false, 'error' => 'File must be under 2MB'];
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0777, true)) return ['success' => false, 'error' => 'Cannot create directory'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'brand_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) return ['success' => false, 'error' => 'Failed to save'];
    return ['success' => true, 'path' => 'assets/brands/' . $filename];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['brand_name'] ?? '');
        $logoFile = $_FILES['logo'] ?? null;
        if (!$name) { $message = 'Brand name is required.'; $messageType = 'error'; }
        elseif (!$logoFile || $logoFile['error'] === UPLOAD_ERR_NO_FILE) { $message = 'Logo is required.'; $messageType = 'error'; }
        else {
            $result = uploadBrandLogo($logoFile, $uploadDir, $allowed);
            if (!$result['success']) { $message = $result['error']; $messageType = 'error'; }
            else {
                $max = $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM brands")->fetchColumn();
                $pdo->prepare("INSERT INTO brands (logo_path, brand_name, sort_order) VALUES (?,?,?)")->execute([$result['path'], $name, $max + 1]);
                $message = 'Brand added.'; $messageType = 'success';
            }
        }
    }


    if ($action === 'toggle') {
        $id = (int)($_POST['brand_id'] ?? 0);
        $pdo->prepare("UPDATE brands SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $message = 'Status updated.'; $messageType = 'success';
    }
    if ($action === 'delete') {
        $id = (int)($_POST['brand_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT logo_path FROM brands WHERE id = ?"); $stmt->execute([$id]);
        $brand = $stmt->fetch();
        if ($brand) {
            $fp = $baseDir . '/' . $brand['logo_path'];
            if (file_exists($fp)) @unlink($fp);
            $pdo->prepare("DELETE FROM brands WHERE id = ?")->execute([$id]);
            $message = 'Brand deleted.'; $messageType = 'success';
        }
    }
    if ($action === 'update') {
        $id = (int)($_POST['brand_id'] ?? 0);
        $name = trim($_POST['brand_name'] ?? '');
        $pdo->prepare("UPDATE brands SET brand_name = ? WHERE id = ?")->execute([$name, $id]);
        $message = 'Brand updated.'; $messageType = 'success';
    }
    if ($action === 'reorder') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            $stmt = $pdo->prepare("UPDATE brands SET sort_order = ? WHERE id = ?");
            foreach ($order as $i => $id) $stmt->execute([$i + 1, (int)$id]);
        }
    }
}

$brands = $pdo->query("SELECT * FROM brands ORDER BY sort_order ASC")->fetchAll();
$pageTitle = 'Brands - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Brands</h1>
        <span class="badge"><?php echo count($brands); ?> Brands</span>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="dashboard-section" style="margin-bottom:30px;">
        <h2>Add New Brand</h2>
        <form method="POST" enctype="multipart/form-data" class="slider-form">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group">
                    <label>Logo <span class="required">*</span></label>
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/svg+xml" required>
                    <small>PNG/SVG recommended, max 2MB</small>
                </div>
                <div class="form-group">
                    <label>Brand Name <span class="required">*</span></label>
                    <input type="text" name="brand_name" required placeholder="e.g. Dettol">
                </div>
            </div>
            <button type="submit" class="btn-primary">Add Brand</button>
        </form>
    </div>


    <div class="dashboard-section">
        <h2>Manage Brands</h2>
        <?php if (empty($brands)): ?>
        <div class="activity-card"><p>No brands yet. Add your first brand above.</p></div>
        <?php else: ?>
        <div class="slides-list" id="brandsGrid">
            <?php foreach ($brands as $brand): ?>
            <div class="slide-item" data-id="<?php echo $brand['id']; ?>">
                <div class="slide-handle" title="Drag to reorder">⠿</div>
                <div class="slide-images">
                    <div class="slide-img client-logo">
                        <img src="../<?php echo htmlspecialchars($brand['logo_path']); ?>" alt="<?php echo htmlspecialchars($brand['brand_name']); ?>">
                    </div>
                </div>
                <div class="slide-details">
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                        <input type="text" name="brand_name" value="<?php echo htmlspecialchars($brand['brand_name']); ?>" required>
                        <button type="submit" class="btn-sm btn-save">Save</button>
                    </form>
                </div>
                <div class="slide-controls">
                    <span class="status-pill <?php echo $brand['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $brand['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                        <button type="submit" class="btn-sm <?php echo $brand['is_active'] ? 'btn-warn' : 'btn-activate'; ?>">
                            <?php echo $brand['is_active'] ? 'Disable' : 'Enable'; ?>
                        </button>
                    </form>
                    <form method="POST" class="inline" onsubmit="return confirm('Delete this brand?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                        <button type="submit" class="btn-sm btn-danger">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <form method="POST" id="reorderForm" style="display:none">
            <input type="hidden" name="action" value="reorder">
            <input type="hidden" name="order" id="reorderInput">
        </form>
        <?php endif; ?>
    </div>
</main>

<script>
const grid = document.getElementById('brandsGrid');
if (grid) {
    let dragEl = null;
    grid.querySelectorAll('.slide-item').forEach(item => {
        item.draggable = true;
        item.addEventListener('dragstart', () => { dragEl = item; item.classList.add('dragging'); });
        item.addEventListener('dragend', () => { item.classList.remove('dragging'); saveOrder(); });
        item.addEventListener('dragover', e => {
            e.preventDefault();
            const after = [...grid.querySelectorAll('.slide-item:not(.dragging)')].reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = e.clientY - box.top - box.height / 2;
                return offset < 0 && offset > closest.offset ? { offset, element: child } : closest;
            }, { offset: Number.NEGATIVE_INFINITY }).element;
            after ? grid.insertBefore(dragEl, after) : grid.appendChild(dragEl);
        });
    });
    function saveOrder() {
        const ids = [...grid.querySelectorAll('.slide-item')].map(c => c.dataset.id);
        document.getElementById('reorderInput').value = JSON.stringify(ids);
        document.getElementById('reorderForm').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
