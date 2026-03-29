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
    $action = $_POST['action'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO site_identity (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    if ($action === 'save_branding') {
        $fields = ['site_name', 'site_tagline', 'site_description'];
        
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

        $message = 'Branding updated successfully.';
        $messageType = 'success';
    }

    if ($action === 'save_contact') {
        $fields = ['phone', 'phone_raw', 'whatsapp', 'email', 'address', 'working_hours'];
        
        foreach ($fields as $key) {
            $val = trim($_POST[$key] ?? '');
            $stmt->execute([$key, $val]);
        }

        $message = 'Contact information updated successfully.';
        $messageType = 'success';
    }

    if ($action === 'save_legal') {
        $fields = ['legal_company_name', 'legal_address', 'legal_email', 'legal_phone', 'legal_gstin', 'legal_pan'];
        
        foreach ($fields as $key) {
            $val = trim($_POST[$key] ?? '');
            $stmt->execute([$key, $val]);
        }

        $message = 'Legal information updated successfully.';
        $messageType = 'success';
    }

    if ($action === 'save_about') {
        $fields = ['about_heading', 'about_content', 'about_button_text', 'about_button_link'];
        
        foreach ($fields as $key) {
            $val = trim($_POST[$key] ?? '');
            $stmt->execute([$key, $val]);
        }

        $message = 'About section updated successfully.';
        $messageType = 'success';
    }

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
            <input type="hidden" name="action" value="save_branding">
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
            <input type="hidden" name="action" value="save_contact">
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

    <div class="dashboard-section">
        <h2>Legal Information</h2>
        <p style="color: #7f8c8d; margin-bottom: 20px; font-size: 14px;">This information will be used on invoices and legal documents.</p>
        <form method="POST" class="slider-form">
            <input type="hidden" name="action" value="save_legal">
            <div class="form-grid">
                <div class="form-group">
                    <label>Legal Company Name <span class="required">*</span></label>
                    <input type="text" name="legal_company_name" value="<?php echo htmlspecialchars($site['legal_company_name'] ?? ''); ?>" required>
                    <small>Official registered company name</small>
                </div>
                <div class="form-group">
                    <label>Legal Email Address <span class="required">*</span></label>
                    <input type="email" name="legal_email" value="<?php echo htmlspecialchars($site['legal_email'] ?? ''); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Legal Address (Single Line) <span class="required">*</span></label>
                <input type="text" name="legal_address" value="<?php echo htmlspecialchars($site['legal_address'] ?? ''); ?>" required>
                <small>Complete registered address in one line</small>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Legal Phone Number <span class="required">*</span></label>
                    <input type="text" name="legal_phone" value="<?php echo htmlspecialchars($site['legal_phone'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>GSTIN</label>
                    <input type="text" name="legal_gstin" value="<?php echo htmlspecialchars($site['legal_gstin'] ?? ''); ?>" placeholder="e.g. 22AAAAA0000A1Z5">
                    <small>Goods and Services Tax Identification Number</small>
                </div>
                <div class="form-group">
                    <label>PAN</label>
                    <input type="text" name="legal_pan" value="<?php echo htmlspecialchars($site['legal_pan'] ?? ''); ?>" placeholder="e.g. AAAAA0000A">
                    <small>Permanent Account Number</small>
                </div>
            </div>
            <button type="submit" class="btn-primary">Save Legal Info</button>
        </form>
    </div>

    <div class="dashboard-section" style="margin-top: 30px;">
        <h2>About Section</h2>
        <p style="color: #7f8c8d; margin-bottom: 20px; font-size: 14px;">Configure the About section content displayed on the homepage.</p>
        <form method="POST" class="slider-form">
            <input type="hidden" name="action" value="save_about">
            <div class="form-group">
                <label>Heading <span class="required">*</span></label>
                <input type="text" name="about_heading" value="<?php echo htmlspecialchars($site['about_heading'] ?? 'Your Trusted Partner for Daily Supplies'); ?>" required>
            </div>
            <div class="form-group">
                <label>Content <span class="required">*</span></label>
                <textarea name="about_content" rows="4" required><?php echo htmlspecialchars($site['about_content'] ?? 'We provide reliable delivery of essential supplies for offices, schools, and housekeeping needs. From stationery and cleaning products to pantry essentials, we ensure your workplace runs smoothly with timely doorstep delivery and competitive pricing.'); ?></textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Button Text <span class="optional">(optional)</span></label>
                    <input type="text" name="about_button_text" value="<?php echo htmlspecialchars($site['about_button_text'] ?? ''); ?>" placeholder="e.g. Learn More">
                    <small>Leave empty to hide the button</small>
                </div>
                <div class="form-group">
                    <label>Button Link <span class="optional">(optional)</span></label>
                    <input type="text" name="about_button_link" value="<?php echo htmlspecialchars($site['about_button_link'] ?? ''); ?>" placeholder="e.g. /about or https://example.com">
                    <small>URL the button should navigate to</small>
                </div>
            </div>
            <button type="submit" class="btn-primary">Save About Section</button>
        </form>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
