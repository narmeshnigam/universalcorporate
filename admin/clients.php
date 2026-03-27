<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    logo_path VARCHAR(500) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$message = '';
$messageType = '';
$baseDir = dirname(__DIR__);
$uploadDir = $baseDir . '/assets/clients/';
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

function uploadClientLogo($file, $uploadDir, $allowed) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    if (!in_array($file['type'], $allowed)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, WebP, SVG allowed'];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File must be under 2MB'];
    }
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0777, true)) {
        return ['success' => false, 'error' => 'Cannot create upload directory'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'client_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['success' => false, 'error' => 'Failed to save file'];
    }
    return ['success' => true, 'path' => 'assets/clients/' . $filename];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['client_name'] ?? '');
        if (!$name) {
            $message = 'Client name is required.';
            $messageType = 'error';
        } else {
            $logoFile = $_FILES['logo'] ?? null;
            if (!$logoFile || $logoFile['error'] === UPLOAD_ERR_NO_FILE) {
                $message = 'Logo is required.';
                $messageType = 'error';
            } else {
                $result = uploadClientLogo($logoFile, $uploadDir, $allowed);
                if (!$result['success']) {
                    $message = $result['error'];
                    $messageType = 'error';
                } else {
                    $maxOrder = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM clients")->fetchColumn();
                    $stmt = $pdo->prepare("INSERT INTO clients (logo_path, client_name, sort_order) VALUES (?, ?, ?)");
                    $stmt->execute([$result['path'], $name, $maxOrder + 1]);
                    $message = 'Client added successfully.';
                    $messageType = 'success';
                }
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['client_id'] ?? 0);
        $pdo->prepare("UPDATE clients SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $message = 'Status updated.';
        $messageType = 'success';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['client_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT logo_path FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch();
        if ($client) {
            $fullPath = $baseDir . '/' . $client['logo_path'];
            if (file_exists($fullPath)) @unlink($fullPath);
            $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
            $message = 'Client deleted.';
            $messageType = 'success';
        }
    }

    if ($action === 'update') {
        $id = (int)($_POST['client_id'] ?? 0);
        $name = trim($_POST['client_name'] ?? '');
        $pdo->prepare("UPDATE clients SET client_name = ? WHERE id = ?")->execute([$name, $id]);
        $message = 'Client updated.';
        $messageType = 'success';
    }

    if ($action === 'reorder') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            $stmt = $pdo->prepare("UPDATE clients SET sort_order = ? WHERE id = ?");
            foreach ($order as $i => $id) {
                $stmt->execute([$i + 1, (int)$id]);
            }
        }
    }
}

$clients = $pdo->query("SELECT * FROM clients ORDER BY sort_order ASC")->fetchAll();
$pageTitle = 'Customers - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Customers</h1>
        <span class="badge"><?php echo count($clients); ?> Customers</span>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="dashboard-section" style="margin-bottom: 30px;">
        <h2>Add New Customer</h2>
        <form method="POST" enctype="multipart/form-data" class="slider-form">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group">
                    <label>Logo <span class="required">*</span></label>
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/svg+xml" required>
                    <small>PNG/SVG recommended, max 2MB</small>
                </div>
                <div class="form-group">
                    <label>Client Name <span class="required">*</span></label>
                    <input type="text" name="client_name" required placeholder="e.g. ABC Corporation">
                </div>
            </div>
            <button type="submit" class="btn-primary">Add Client</button>
        </form>
    </div>

    <div class="dashboard-section">
        <h2>Manage Customers</h2>
        <?php if (empty($clients)): ?>
        <div class="activity-card"><p>No clients yet. Add your first client above.</p></div>
        <?php else: ?>
        <div class="slides-list" id="clientsGrid">
            <?php foreach ($clients as $client): ?>
            <div class="slide-item" data-id="<?php echo $client['id']; ?>">
                <div class="slide-handle" title="Drag to reorder">⠿</div>
                <div class="slide-images">
                    <div class="slide-img client-logo">
                        <img src="../<?php echo htmlspecialchars($client['logo_path']); ?>" alt="<?php echo htmlspecialchars($client['client_name']); ?>">
                    </div>
                </div>
                <div class="slide-details">
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <input type="text" name="client_name" value="<?php echo htmlspecialchars($client['client_name']); ?>" placeholder="Client Name" required>
                        <button type="submit" class="btn-sm btn-save">Save</button>
                    </form>
                </div>
                <div class="slide-controls">
                    <span class="status-pill <?php echo $client['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $client['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <button type="submit" class="btn-sm <?php echo $client['is_active'] ? 'btn-warn' : 'btn-activate'; ?>">
                            <?php echo $client['is_active'] ? 'Disable' : 'Enable'; ?>
                        </button>
                    </form>
                    <form method="POST" class="inline" onsubmit="return confirm('Delete this client?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
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
const grid = document.getElementById('clientsGrid');
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
