<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

// Ensure table exists with mobile column
$pdo->exec("CREATE TABLE IF NOT EXISTS hero_slides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(500) NOT NULL,
    image_path_mobile VARCHAR(500) DEFAULT NULL,
    title VARCHAR(255) DEFAULT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Add mobile column for existing installs
try {
    $pdo->exec("ALTER TABLE hero_slides ADD COLUMN image_path_mobile VARCHAR(500) DEFAULT NULL AFTER image_path");
} catch (Exception $e) {}

$message = '';
$messageType = '';
$baseDir = dirname(__DIR__);
$uploadDir = $baseDir . '/assets/slides/';
$allowed = ['image/jpeg', 'image/png', 'image/webp'];

// Helper function to upload a single image
function uploadImage($file, $prefix, $uploadDir, $allowed) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    if (!in_array($file['type'], $allowed)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, WebP allowed'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File must be under 5MB'];
    }
    
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        return ['success' => false, 'error' => 'Cannot create upload directory'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filepath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'error' => 'Failed to save file'];
    }
    
    return ['success' => true, 'path' => 'assets/slides/' . $filename];
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $desktopFile = $_FILES['slide_image'] ?? null;
        $mobileFile = $_FILES['slide_image_mobile'] ?? null;
        
        // Desktop is required
        if (!$desktopFile || $desktopFile['error'] === UPLOAD_ERR_NO_FILE) {
            $message = 'Desktop image is required.';
            $messageType = 'error';
        } else {
            $desktopResult = uploadImage($desktopFile, 'desktop', $uploadDir, $allowed);
            
            if (!$desktopResult['success']) {
                $message = 'Desktop: ' . $desktopResult['error'];
                $messageType = 'error';
            } else {
                $mobilePath = null;
                
                // Mobile is optional
                if ($mobileFile && $mobileFile['error'] === UPLOAD_ERR_OK) {
                    $mobileResult = uploadImage($mobileFile, 'mobile', $uploadDir, $allowed);
                    if ($mobileResult['success']) {
                        $mobilePath = $mobileResult['path'];
                    }
                }
                
                $title = trim($_POST['title'] ?? '');
                $subtitle = trim($_POST['subtitle'] ?? '');
                $maxOrder = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM hero_slides")->fetchColumn();
                
                $stmt = $pdo->prepare("INSERT INTO hero_slides (image_path, image_path_mobile, title, subtitle, sort_order) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$desktopResult['path'], $mobilePath, $title ?: null, $subtitle ?: null, $maxOrder + 1]);
                
                $message = 'Slide added successfully.';
                $messageType = 'success';
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['slide_id'] ?? 0);
        $pdo->prepare("UPDATE hero_slides SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $message = 'Status updated.';
        $messageType = 'success';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['slide_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT image_path, image_path_mobile FROM hero_slides WHERE id = ?");
        $stmt->execute([$id]);
        $slide = $stmt->fetch();
        if ($slide) {
            // Delete both images
            foreach (['image_path', 'image_path_mobile'] as $col) {
                if ($slide[$col]) {
                    $fullPath = $baseDir . '/' . $slide[$col];
                    if (file_exists($fullPath)) @unlink($fullPath);
                }
            }
            $pdo->prepare("DELETE FROM hero_slides WHERE id = ?")->execute([$id]);
            $message = 'Slide deleted.';
            $messageType = 'success';
        }
    }

    if ($action === 'update') {
        $id = (int)($_POST['slide_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $pdo->prepare("UPDATE hero_slides SET title = ?, subtitle = ? WHERE id = ?")->execute([$title ?: null, $subtitle ?: null, $id]);
        $message = 'Slide updated.';
        $messageType = 'success';
    }

    if ($action === 'reorder') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            $stmt = $pdo->prepare("UPDATE hero_slides SET sort_order = ? WHERE id = ?");
            foreach ($order as $i => $id) {
                $stmt->execute([$i + 1, (int)$id]);
            }
        }
    }
}

$slides = $pdo->query("SELECT * FROM hero_slides ORDER BY sort_order ASC")->fetchAll();
$pageTitle = 'Hero Slider - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Hero Slider</h1>
        <span class="badge"><?php echo count($slides); ?> Slides</span>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="dashboard-section" style="margin-bottom: 30px;">
        <h2>Add New Slide</h2>
        <form method="POST" enctype="multipart/form-data" class="slider-form">
            <input type="hidden" name="action" value="upload">
            <div class="form-grid">
                <div class="form-group">
                    <label>Desktop Image <span class="required">*</span></label>
                    <input type="file" name="slide_image" accept="image/jpeg,image/png,image/webp" required>
                    <small>1920×1080px recommended, max 5MB</small>
                </div>
                <div class="form-group">
                    <label>Mobile Image <span class="optional">(optional)</span></label>
                    <input type="file" name="slide_image_mobile" accept="image/jpeg,image/png,image/webp">
                    <small>768×1024px recommended, max 5MB</small>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" placeholder="Overlay heading text">
                </div>
                <div class="form-group">
                    <label>Subtitle</label>
                    <input type="text" name="subtitle" placeholder="Overlay subtext">
                </div>
            </div>
            <button type="submit" class="btn-primary">Add Slide</button>
        </form>
    </div>

    <div class="dashboard-section">
        <h2>Manage Slides</h2>
        <?php if (empty($slides)): ?>
        <div class="activity-card"><p>No slides yet. Add your first slide above.</p></div>
        <?php else: ?>
        <div class="slides-list" id="slidesGrid">
            <?php foreach ($slides as $slide): ?>
            <div class="slide-item" data-id="<?php echo $slide['id']; ?>">
                <div class="slide-handle" title="Drag to reorder">⠿</div>
                <div class="slide-images">
                    <div class="slide-img desktop">
                        <span>Desktop</span>
                        <img src="../<?php echo htmlspecialchars($slide['image_path']); ?>" alt="Desktop">
                    </div>
                    <div class="slide-img mobile">
                        <span>Mobile</span>
                        <?php if (!empty($slide['image_path_mobile'])): ?>
                        <img src="../<?php echo htmlspecialchars($slide['image_path_mobile']); ?>" alt="Mobile">
                        <?php else: ?>
                        <div class="no-img">Uses desktop</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="slide-details">
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="slide_id" value="<?php echo $slide['id']; ?>">
                        <input type="text" name="title" value="<?php echo htmlspecialchars($slide['title'] ?? ''); ?>" placeholder="Title">
                        <input type="text" name="subtitle" value="<?php echo htmlspecialchars($slide['subtitle'] ?? ''); ?>" placeholder="Subtitle">
                        <button type="submit" class="btn-sm btn-save">Save</button>
                    </form>
                </div>
                <div class="slide-controls">
                    <span class="status-pill <?php echo $slide['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $slide['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="slide_id" value="<?php echo $slide['id']; ?>">
                        <button type="submit" class="btn-sm <?php echo $slide['is_active'] ? 'btn-warn' : 'btn-activate'; ?>">
                            <?php echo $slide['is_active'] ? 'Disable' : 'Enable'; ?>
                        </button>
                    </form>
                    <form method="POST" class="inline" onsubmit="return confirm('Delete this slide?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="slide_id" value="<?php echo $slide['id']; ?>">
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
const grid = document.getElementById('slidesGrid');
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
