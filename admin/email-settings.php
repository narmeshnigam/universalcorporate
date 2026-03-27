<?php
session_start();
require_once '../config/database.php';
require_once '../config/email.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

// Ensure email_settings table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

$message = '';
$messageType = '';

// Default settings
$defaults = [
    'smtp_enabled' => '0',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_encryption' => 'tls',
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_from_email' => '',
    'smtp_from_name' => '',
    'notification_emails' => '',
];

// Load current settings
$settings = $defaults;
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM email_settings");
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = array_merge($defaults, $rows);
} catch (Exception $e) {}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO email_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    
    if ($action === 'save_smtp') {
        $fields = [
            'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption',
            'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name'
        ];

        foreach ($fields as $key) {
            $val = trim($_POST[$key] ?? '');
            // For checkbox
            if ($key === 'smtp_enabled') {
                $val = isset($_POST[$key]) ? '1' : '0';
            }
            $stmt->execute([$key, $val]);
        }

        $message = 'SMTP settings saved successfully.';
        $messageType = 'success';
        
        // Reload settings
        $rows = $pdo->query("SELECT setting_key, setting_value FROM email_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings = array_merge($defaults, $rows);
    }
    
    if ($action === 'save_notifications') {
        $val = trim($_POST['notification_emails'] ?? '');
        $stmt->execute(['notification_emails', $val]);

        $message = 'Notification settings saved successfully.';
        $messageType = 'success';
        
        // Reload settings
        $rows = $pdo->query("SELECT setting_key, setting_value FROM email_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings = array_merge($defaults, $rows);
    }
    
    if ($action === 'test') {
        $testEmail = trim($_POST['test_email'] ?? '');
        
        if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'error';
        } else {
            // Test email functionality
            if ($settings['smtp_enabled'] === '1') {
                // Check if all required settings are filled
                if (empty($settings['smtp_host']) || empty($settings['smtp_username']) || 
                    empty($settings['smtp_password']) || empty($settings['smtp_from_email'])) {
                    $message = 'Please fill in all required SMTP settings before testing.';
                    $messageType = 'error';
                } else {
                    // Send test email
                    $result = sendTestEmail($pdo, $testEmail);
                    if ($result) {
                        $message = "Test email sent successfully to $testEmail. Please check your inbox (and spam folder).";
                        $messageType = 'success';
                    } else {
                        $error = getLastSMTPError();
                        $message = 'Failed to send test email: ' . htmlspecialchars($error);
                        $messageType = 'error';
                    }
                }
            } else {
                $message = 'SMTP is disabled. Enable it first to send test emails.';
                $messageType = 'error';
            }
        }
    }
}

$pageTitle = 'Email Settings - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Email Settings</h1>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="dashboard-section" style="margin-bottom: 30px;">
        <h2>SMTP Configuration</h2>
        <form method="POST" class="slider-form">
            <input type="hidden" name="action" value="save_smtp">
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="smtp_enabled" value="1" <?php echo $settings['smtp_enabled'] === '1' ? 'checked' : ''; ?> style="width: auto; cursor: pointer;">
                    <span>Enable SMTP Email</span>
                </label>
                <small>Enable this to use SMTP for sending emails instead of PHP mail()</small>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>SMTP Host <span class="required">*</span></label>
                    <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>" placeholder="e.g. smtp.gmail.com">
                    <small>Gmail: <strong>smtp.gmail.com</strong> &nbsp;|&nbsp; Outlook: <strong>smtp-mail.outlook.com</strong> &nbsp;|&nbsp; Yahoo: <strong>smtp.mail.yahoo.com</strong></small>
                </div>
                <div class="form-group">
                    <label>SMTP Port <span class="required">*</span></label>
                    <input type="text" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port']); ?>" placeholder="587">
                    <small>Common ports: 587 (TLS), 465 (SSL), 25 (unsecured)</small>
                </div>
            </div>

            <div class="form-group">
                <label>Encryption Type</label>
                <select name="smtp_encryption" style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem;">
                    <option value="tls" <?php echo $settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                    <option value="ssl" <?php echo $settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="none" <?php echo $settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                </select>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>SMTP Username <span class="required">*</span></label>
                    <input type="text" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username']); ?>" placeholder="your-email@example.com" autocomplete="off">
                    <small>Usually your email address</small>
                </div>
                <div class="form-group">
                    <label>SMTP Password <span class="required">*</span></label>
                    <input type="password" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password']); ?>" placeholder="••••••••" autocomplete="new-password">
                    <small>Your email password or app-specific password</small>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>From Email <span class="required">*</span></label>
                    <input type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email']); ?>" placeholder="noreply@example.com">
                    <small>Email address that appears in "From" field</small>
                </div>
                <div class="form-group">
                    <label>From Name</label>
                    <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name']); ?>" placeholder="Your Company Name">
                    <small>Name that appears in "From" field</small>
                </div>
            </div>

            <button type="submit" class="btn-primary">Save Settings</button>
        </form>
    </div>

    <div class="dashboard-section" style="margin-bottom: 30px;">
        <h2>Notification Settings</h2>
        <form method="POST" class="slider-form">
            <input type="hidden" name="action" value="save_notifications">
            
            <div class="form-group">
                <label>Admin Notification Emails</label>
                <textarea name="notification_emails" rows="3" placeholder="admin@example.com&#10;manager@example.com"><?php echo htmlspecialchars($settings['notification_emails']); ?></textarea>
                <small>Enter email addresses to receive enquiry notifications (one per line). Leave empty to disable notifications.</small>
            </div>

            <button type="submit" class="btn-primary">Save Notification Settings</button>
        </form>
    </div>

    <div class="dashboard-section">
        <h2>Test Email Configuration</h2>
        <form method="POST" class="slider-form">
            <input type="hidden" name="action" value="test">
            <div class="form-group">
                <label>Test Email Address</label>
                <input type="email" name="test_email" placeholder="test@example.com" required>
                <small>Send a test email to verify your SMTP configuration</small>
            </div>
            <button type="submit" class="btn-primary" style="background: #27ae60;">Send Test Email</button>
        </form>
    </div>

    <div class="dashboard-section" style="margin-top: 30px;">
        <h2>Configuration Notes</h2>
        <div class="activity-card">
            <p style="margin-bottom: 12px;"><strong>Gmail Users:</strong></p>
            <ul style="margin-left: 20px; color: #555; line-height: 1.8;">
                <li>Use <code>smtp.gmail.com</code> as host and port <code>587</code></li>
                <li>Enable "Less secure app access" or use an App Password</li>
                <li>Generate App Password: Google Account → Security → 2-Step Verification → App passwords</li>
            </ul>
            <p style="margin: 12px 0;"><strong>Other Providers:</strong></p>
            <ul style="margin-left: 20px; color: #555; line-height: 1.8;">
                <li>Outlook/Hotmail: <code>smtp-mail.outlook.com</code>, port <code>587</code></li>
                <li>Yahoo: <code>smtp.mail.yahoo.com</code>, port <code>587</code></li>
                <li>Check your email provider's documentation for SMTP settings</li>
            </ul>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
