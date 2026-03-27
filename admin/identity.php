<?php
session_start();
require_once '../config/database.php';
require_once '../config/identity.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$site = getSiteIdentity($pdo);
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'site_name', 'site_tagline', 'site_description',
        'phone', 'phone_raw', 'whatsapp',
        'email', 'address', 'working_hours'
    ];

    $stmt = $pdo->prepare("INSERT INTO site_identity (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($fields as $key) {
        $val = trim($_POST[$key] ?? '');
        $stmt->execute([$key, $val]);
    }

    // Handle logo upload
    $logoFile = $_FILES['logo'] ?? null;
    if ($logoFile && $logoFile['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        if (in_array($logoFile['type'], $allowed) && $logoFile['size'] <= 2 * 1024 * 1024) {
            $baseDir = dirname(__DIR__);
            $uploadDir = $baseDir . '/assets/branding/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
            $ext = strtolower(pathinfo($logoFile['name'], PATHINFO_EXTENSION));
            $filename = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($logoFile['tmp_name'], $uploadDir . $filename)) {
                $stmt->execute(['logo_path', 'assets/branding/' . $filename]);
            }
        }
    }

    $message = 'Identity updated successfully.';
    $messageType = 'success';
    $site = getSiteIdentity($pdo);
}

$pageTitle = 'Identity - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Site Identity</h1>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="dashboard-section" style="margin-bottom: 30px;">
        <h2>Branding</h2>
        <form method="POST" enctype="multipart/form-data" class="slider-form">
            <div class="form-grid">
                <div class="form-group">
                    <label>Site Name <span class="required">*</span></label>
                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($site['site_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Tagline</label>
                    <input type="text" name="site_tagline" value="<?php echo htmlspecialchars($site['site_tagline']); ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Site Description</label>
                <textarea name="site_description" rows="3"><?php echo htmlspecialchars($site['site_description']); ?></textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Logo Upload</label>
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/svg+xml">
                    <small>Leave empty to keep current. Max 2MB. Formats: JPG, PNG, WebP, SVG</small>
                </div>
                <div class="form-group">
                    <label>Current Logo</label>
                    <div class="logo-preview">
                        <img src="../<?php echo htmlspecialchars($site['logo_path']); ?>" alt="Logo">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-primary">Save Branding</button>
        </form>
    </div>

    <div class="dashboard-section">
        <h2>Contact Information</h2>
        <form method="POST" class="slider-form">
            <div class="form-grid">
                <div class="form-group">
                    <label>Phone (display format)</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($site['phone']); ?>" placeholder="e.g. +91 12345 67890">
                    <small>Format as you want it displayed on the website</small>
                </div>
                <div class="form-group">
                    <label>Phone (raw for tel: link)</label>
                    <input type="text" name="phone_raw" value="<?php echo htmlspecialchars($site['phone_raw']); ?>" placeholder="e.g. 911234567890">
                    <small>Numbers only, with country code (no spaces or symbols)</small>
                </div>
                <div class="form-group">
                    <label>WhatsApp Number</label>
                    <input type="text" name="whatsapp" value="<?php echo htmlspecialchars($site['whatsapp']); ?>" placeholder="e.g. 911234567890">
                    <small>Numbers only, with country code (no spaces or symbols)</small>
                </div>
                <div class="form-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($site['email']); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Physical Address</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($site['address']); ?>">
            </div>
            <div class="form-group">
                <label>Working Hours</label>
                <input type="text" name="working_hours" value="<?php echo htmlspecialchars($site['working_hours']); ?>" placeholder="e.g. Mon - Sat: 9:00 AM - 6:00 PM">
            </div>
            <button type="submit" class="btn-primary">Save Contact Info</button>
        </form>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
