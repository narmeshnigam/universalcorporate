<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Fetch current hash
    $stmt = $pdo->prepare("SELECT password FROM site_users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password'])) {
        $message = 'Current password is incorrect.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = 'New password must be at least 8 characters.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New passwords do not match.';
        $messageType = 'error';
    } elseif (password_verify($newPassword, $user['password'])) {
        $message = 'New password must be different from the current password.';
        $messageType = 'error';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE site_users SET password = ? WHERE id = ?")->execute([$hash, $_SESSION['user_id']]);
        $message = 'Password updated successfully.';
        $messageType = 'success';
    }
}

$pageTitle = 'Change Password - User Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>Change Password</h1>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="dashboard-section">
        <h2>Update Your Password</h2>
        <form method="POST" class="slider-form" autocomplete="off">
            <div class="form-group">
                <label>Current Password <span class="required">*</span></label>
                <input type="password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>New Password <span class="required">*</span></label>
                    <input type="password" name="new_password" required autocomplete="new-password" id="newPassword">
                    <small>Minimum 8 characters</small>
                </div>
                <div class="form-group">
                    <label>Confirm New Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" required autocomplete="new-password" id="confirmPassword">
                    <small id="matchHint" style="display:none; color: #e74c3c;">Passwords do not match</small>
                </div>
            </div>
            <button type="submit" class="btn-primary">Update Password</button>
        </form>
    </div>
</main>

<script>
(function () {
    const np = document.getElementById('newPassword');
    const cp = document.getElementById('confirmPassword');
    const hint = document.getElementById('matchHint');

    function check() {
        if (cp.value.length === 0) { hint.style.display = 'none'; return; }
        hint.style.display = np.value !== cp.value ? 'block' : 'none';
    }

    np.addEventListener('input', check);
    cp.addEventListener('input', check);
})();
</script>

<?php include 'includes/footer.php'; ?>
