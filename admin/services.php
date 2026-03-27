<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(500) NOT NULL,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$message = '';
$messageType = '';
$baseDir = dirname(__DIR__);
$uploadDir = $baseDir . '/assets/services/';
$allowed = ['image/jpeg', 'image/png', 'image/webp'];

// Helper function
function uploadServiceImage($file, $uploadDir, $allowed) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    if (!in_array($file['type'], $allowed)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, WebP allowed'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File must be under 5MB'];
    }
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0777, true)) {
        return ['success' => false, 'error' => 'Cannot create upload directory'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'service_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['success' => false, 'error' => 'Failed to save file'];
    }
    return ['success' => true, 'path' => 'assets/services/' . $filename];
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        if (!$title) {
            $message = 'Title is required.';
            $messageType = 'error';
        } else {
            $imageFile = $_FILES['image'] ?? null;
            if (!$imageFile || $imageFile['error'] === UPLOAD_ERR_NO_FILE) {
                $message = 'Image is required.';
                $messageType = 'error';
            } else {
                $result = uploadServiceImage($imageFile, $uploadDir, $allowed);
                if (!$result['success']) {
                    $message = $result['error'];
                    $messageType = 'error';
                } else {
                    $subtitle = trim($_POST['subtitle'] ?? '');
                    $maxOrder = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM services")->fetchColumn();
                    $stmt = $pdo->prepare("INSERT INTO services (image_path, title, subtitle, sort_order) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$result['path'], $title, $subtitle ?: null, $maxOrder + 1]);
                    $message = 'Service added successfully.';
                    $messageType = 'success';
                }
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['service_id'] ?? 0);
        $pdo->prepare("UPDATE services SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $message = 'Status updated.';
        $messageType = 'success';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['service_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT image_path FROM services WHERE id = ?");
        $stmt->execute([$id]);
        $service = $stmt->fetch();
        if ($service) {
            $fullPath = $baseDir . '/' . $service['image_path'];
            if (file_exists($fullPath)) @unlink($fullPath);
            $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
            $message = 'Service deleted.';
            $messageType = 'success';
        }
    }

    if ($action === 'update') {
        $id = (int)($_POST['service_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $pdo->prepare("UPDATE services SET title = ?, subtitle = ? WHERE id = ?")->execute([$title, $subtitle ?: null, $id]);
        $message = 'Service updated.';
        $messageType = 'success';
    }

    if ($action === 'reorder') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            $stmt = $pdo->prepare("UPDATE services SET sort_order = ? WHERE id = ?");
            foreach ($order as $i => $id) {
                $stmt->execute([$i + 1, (int)$id]);
            }
        }
    }
}

$services = $pdo->query("SELECT * FROM services ORDER BY sort_order ASC")->fetchAll();
$pageTitle = 'Services - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Services</h1>
        <span class="badge"><?php echo count($services); ?> Services</span>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="dashboard-section" style="margin-bottom: 30px;">
        <h2>Add New Service</h2>
        <form method="POST" enctype="multipart/form-data" class="slider-form">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group">
                    <label>Image <span class="required">*</span></label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
                    <small>600×400px recommended, max 5MB</small>
                </div>
                <div class="form-group">
                    <label>Title <span class="required">*</span></label>
                    <input type="text" name="title" required placeholder="e.g. Office Supplies">
                </div>
                <div class="form-group">
                    <label>Subtitle</label>
                    <input type="text" name="subtitle" placeholder="e.g. Stationery, files, and desk essentials">
                </div>
            </div>
            <button type="submit" class="btn-primary">Add Service</button>
        </form>
    </div>

    <div class="dashboard-section">
        <h2>Manage Services</h2>
        <?php if (empty($services)): ?>
        <div class="activity-card"><p>No services yet. Add your first service above.</p></div>
        <?php else: ?>
        <div class="slides-list" id="servicesGrid">
            <?php foreach ($services as $service): ?>
            <div class="slide-item" data-id="<?php echo $service['id']; ?>">
                <div class="slide-handle" title="Drag to reorder">⠿</div>
                <div class="slide-images">
                    <div class="slide-img service-img">
                        <img src="../<?php echo htmlspecialchars($service['image_path']); ?>" alt="<?php echo htmlspecialchars($service['title']); ?>">
                    </div>
                </div>
                <div class="slide-details">
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                        <input type="text" name="title" value="<?php echo htmlspecialchars($service['title']); ?>" placeholder="Title" required>
                        <input type="text" name="subtitle" value="<?php echo htmlspecialchars($service['subtitle'] ?? ''); ?>" placeholder="Subtitle">
                        <button type="submit" class="btn-sm btn-save">Save</button>
                    </form>
                </div>
                <div class="slide-controls">
                    <span class="status-pill <?php echo $service['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                        <button type="submit" class="btn-sm <?php echo $service['is_active'] ? 'btn-warn' : 'btn-activate'; ?>">
                            <?php echo $service['is_active'] ? 'Disable' : 'Enable'; ?>
                        </button>
                    </form>
                    <form method="POST" class="inline" onsubmit="return confirm('Delete this service?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
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
const grid = document.getElementById('servicesGrid');
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
