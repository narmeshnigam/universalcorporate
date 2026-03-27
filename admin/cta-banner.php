<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$pdo->exec("CREATE TABLE IF NOT EXISTS cta_banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(500) NOT NULL,
    heading VARCHAR(255) DEFAULT NULL,
    subheading VARCHAR(500) DEFAULT NULL,
    button_text VARCHAR(100) DEFAULT NULL,
    button_link VARCHAR(500) DEFAULT '#contact',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$message = '';
$messageType = '';
$baseDir = dirname(__DIR__);
$uploadDir = $baseDir . '/assets/banners/';
$allowed = ['image/jpeg', 'image/png', 'image/webp'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $heading = trim($_POST['heading'] ?? '');
        $subheading = trim($_POST['subheading'] ?? '');
        $buttonText = trim($_POST['button_text'] ?? '');
        $buttonLink = trim($_POST['button_link'] ?? '#contact');
        $existingId = (int)($_POST['banner_id'] ?? 0);

        $imagePath = null;
        $file = $_FILES['image'] ?? null;

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if (!in_array($file['type'], $allowed)) {
                $message = 'Only JPG, PNG, WebP allowed.';
                $messageType = 'error';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $message = 'Image must be under 5MB.';
                $messageType = 'error';
            } else {
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'cta_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $imagePath = 'assets/banners/' . $filename;
                } else {
                    $message = 'Failed to upload image.';
                    $messageType = 'error';
                }
            }
        }

        if ($messageType !== 'error') {
            if ($existingId) {
                // Update existing
                if ($imagePath) {
                    // Delete old image
                    $old = $pdo->prepare("SELECT image_path FROM cta_banners WHERE id = ?");
                    $old->execute([$existingId]);
                    $oldBanner = $old->fetch();
                    if ($oldBanner) {
                        $fp = $baseDir . '/' . $oldBanner['image_path'];
                        if (file_exists($fp)) @unlink($fp);
                    }
                    $pdo->prepare("UPDATE cta_banners SET image_path=?, heading=?, subheading=?, button_text=?, button_link=? WHERE id=?")
                        ->execute([$imagePath, $heading ?: null, $subheading ?: null, $buttonText ?: null, $buttonLink, $existingId]);
                } else {
                    $pdo->prepare("UPDATE cta_banners SET heading=?, subheading=?, button_text=?, button_link=? WHERE id=?")
                        ->execute([$heading ?: null, $subheading ?: null, $buttonText ?: null, $buttonLink, $existingId]);
                }
                $message = 'Banner updated.'; $messageType = 'success';
            } else {
                // New banner — need image
                if (!$imagePath) {
                    $message = 'Image is required for a new banner.';
                    $messageType = 'error';
                } else {
                    // Deactivate all others
                    $pdo->exec("UPDATE cta_banners SET is_active = 0");
                    $pdo->prepare("INSERT INTO cta_banners (image_path, heading, subheading, button_text, button_link) VALUES (?,?,?,?,?)")
                        ->execute([$imagePath, $heading ?: null, $subheading ?: null, $buttonText ?: null, $buttonLink]);
                    $message = 'Banner created.'; $messageType = 'success';
                }
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['banner_id'] ?? 0);
        // Only one active at a time
        $pdo->exec("UPDATE cta_banners SET is_active = 0");
        $pdo->prepare("UPDATE cta_banners SET is_active = 1 WHERE id = ?")->execute([$id]);
        $message = 'Active banner changed.'; $messageType = 'success';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['banner_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT image_path FROM cta_banners WHERE id = ?"); $stmt->execute([$id]);
        $banner = $stmt->fetch();
        if ($banner) {
            $fp = $baseDir . '/' . $banner['image_path'];
            if (file_exists($fp)) @unlink($fp);
            $pdo->prepare("DELETE FROM cta_banners WHERE id = ?")->execute([$id]);
            $message = 'Banner deleted.'; $messageType = 'success';
        }
    }
}

$banners = $pdo->query("SELECT * FROM cta_banners ORDER BY is_active DESC, created_at DESC")->fetchAll();
$activeBanner = null;
foreach ($banners as $b) { if ($b['is_active']) { $activeBanner = $b; break; } }

$pageTitle = 'CTA Banner - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>CTA Banner</h1>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="dashboard-section" style="margin-bottom:30px;">
        <h2><?php echo $activeBanner ? 'Update Banner' : 'Create Banner'; ?></h2>
        <form method="POST" enctype="multipart/form-data" class="slider-form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="banner_id" value="<?php echo $activeBanner['id'] ?? ''; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Background Image <?php echo $activeBanner ? '' : '<span class="required">*</span>'; ?></label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" <?php echo $activeBanner ? '' : 'required'; ?>>
                    <small>1200×400px recommended, max 5MB<?php echo $activeBanner ? ' (leave empty to keep current)' : ''; ?></small>
                </div>
                <div class="form-group">
                    <label>Heading</label>
                    <input type="text" name="heading" value="<?php echo htmlspecialchars($activeBanner['heading'] ?? ''); ?>" placeholder="e.g. GET UPTO 50% DISCOUNT">
                </div>
                <div class="form-group">
                    <label>Subheading</label>
                    <input type="text" name="subheading" value="<?php echo htmlspecialchars($activeBanner['subheading'] ?? ''); ?>" placeholder="e.g. On School Supplies">
                </div>
                <div class="form-group">
                    <label>Button Text</label>
                    <input type="text" name="button_text" value="<?php echo htmlspecialchars($activeBanner['button_text'] ?? ''); ?>" placeholder="e.g. EXPLORE OUR PRODUCTS">
                </div>
                <div class="form-group">
                    <label>Button Link</label>
                    <input type="text" name="button_link" value="<?php echo htmlspecialchars($activeBanner['button_link'] ?? '#contact'); ?>" placeholder="#contact">
                </div>
            </div>
            <button type="submit" class="btn-primary"><?php echo $activeBanner ? 'Update Banner' : 'Create Banner'; ?></button>
        </form>
        <?php if ($activeBanner): ?>
        <div style="margin-top:20px;">
            <p style="font-size:0.85rem;color:#888;margin-bottom:10px;">Current banner preview:</p>
            <img src="../<?php echo htmlspecialchars($activeBanner['image_path']); ?>" alt="Banner" style="max-width:100%;height:auto;border-radius:10px;">
        </div>
        <?php endif; ?>
    </div>

    <?php if (count($banners) > 1): ?>
    <div class="dashboard-section">
        <h2>All Banners</h2>
        <div class="slides-list">
            <?php foreach ($banners as $banner): ?>
            <div class="slide-item">
                <div class="slide-images">
                    <div class="slide-img" style="width:200px;height:80px;">
                        <img src="../<?php echo htmlspecialchars($banner['image_path']); ?>" alt="Banner">
                    </div>
                </div>
                <div class="slide-details">
                    <span style="font-size:0.9rem;color:#333;"><?php echo htmlspecialchars($banner['heading'] ?? 'No heading'); ?></span>
                </div>
                <div class="slide-controls">
                    <span class="status-pill <?php echo $banner['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $banner['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <?php if (!$banner['is_active']): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                        <button type="submit" class="btn-sm btn-activate">Set Active</button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" class="inline" onsubmit="return confirm('Delete this banner?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                        <button type="submit" class="btn-sm btn-danger">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
