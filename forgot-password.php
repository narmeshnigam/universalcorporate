<?php
session_start();
require_once 'config/database.php';
require_once 'config/otp.php';
require_once 'config/identity.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    die('Database connection failed. Please check your database configuration.');
}

$site = getSiteIdentity($pdo);

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: admin/index.php');
    exit;
}
if (isset($_SESSION['user_id'])) {
    header('Location: user/index.php');
    exit;
}

$error = '';
$success = '';
$mode = $_POST['mode'] ?? $_GET['mode'] ?? 'user';
$step = $_SESSION['reset_step'] ?? 'email';
$resetEmail = $_SESSION['reset_email'] ?? '';
$resetMode = $_SESSION['reset_mode'] ?? $mode;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'request';
    $mode = $_POST['mode'] ?? 'user';
    
    if ($action === 'request') {
        // Step 1: Request password reset
        $email = trim($_POST['email'] ?? '');
        
        if (!$email) {
            $error = 'Please enter your email address';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            // Check if email exists
            $table = $mode === 'admin' ? 'users' : 'site_users';
            $stmt = $pdo->prepare("SELECT id FROM $table WHERE email = ?");
            $stmt->execute([$email]);
            
            if (!$stmt->fetch()) {
                // Don't reveal if email exists or not for security
                $error = 'If an account exists with this email, you will receive an OTP.';
            } else {
                // Generate and send OTP
                $otpCode = createOTP($pdo, $email, 'password_reset', $mode);
                if (sendPasswordResetOTP($pdo, $email, $otpCode, $mode)) {
                    $_SESSION['reset_step'] = 'verify';
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_mode'] = $mode;
                    $step = 'verify';
                    $resetEmail = $email;
                    $resetMode = $mode;
                    $success = 'OTP sent to your email. Please check your inbox.';
                } else {
                    $error = 'Failed to send OTP. Please check email settings or try again.';
                }
            }
        }
    } elseif ($action === 'verify') {
        // Step 2: Verify OTP
        $otpCode = trim($_POST['otp'] ?? '');
        $email = $_SESSION['reset_email'] ?? '';
        $mode = $_SESSION['reset_mode'] ?? 'user';
        
        if (!$email) {
            $error = 'Session expired. Please start again.';
            $step = 'email';
            unset($_SESSION['reset_step'], $_SESSION['reset_email'], $_SESSION['reset_mode']);
        } elseif (!$otpCode) {
            $error = 'Please enter the OTP';
            $step = 'verify';
            $resetEmail = $email;
            $resetMode = $mode;
        } else {
            $result = verifyOTP($pdo, $email, $otpCode, 'password_reset', $mode);
            if ($result['success']) {
                $_SESSION['reset_step'] = 'newpass';
                $_SESSION['reset_verified'] = true;
                $step = 'newpass';
                $resetEmail = $email;
                $resetMode = $mode;
            } else {
                $error = $result['error'];
                $step = 'verify';
                $resetEmail = $email;
                $resetMode = $mode;
            }
        }
    } elseif ($action === 'reset') {
        // Step 3: Set new password
        $email = $_SESSION['reset_email'] ?? '';
        $mode = $_SESSION['reset_mode'] ?? 'user';
        $verified = $_SESSION['reset_verified'] ?? false;
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!$email || !$verified) {
            $error = 'Session expired. Please start again.';
            $step = 'email';
            unset($_SESSION['reset_step'], $_SESSION['reset_email'], $_SESSION['reset_mode'], $_SESSION['reset_verified']);
        } elseif (!$password || !$confirmPassword) {
            $error = 'Please fill in all fields';
            $step = 'newpass';
            $resetEmail = $email;
            $resetMode = $mode;
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
            $step = 'newpass';
            $resetEmail = $email;
            $resetMode = $mode;
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
            $step = 'newpass';
            $resetEmail = $email;
            $resetMode = $mode;
        } else {
            // Update password
            $table = $mode === 'admin' ? 'users' : 'site_users';
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE $table SET password = ? WHERE email = ?");
            
            if ($stmt->execute([$passwordHash, $email])) {
                unset($_SESSION['reset_step'], $_SESSION['reset_email'], $_SESSION['reset_mode'], $_SESSION['reset_verified']);
                $step = 'success';
                $resetMode = $mode;
            } else {
                $error = 'Failed to update password. Please try again.';
                $step = 'newpass';
                $resetEmail = $email;
                $resetMode = $mode;
            }
        }
    } elseif ($action === 'resend') {
        // Resend OTP
        $email = $_SESSION['reset_email'] ?? '';
        $mode = $_SESSION['reset_mode'] ?? 'user';
        
        if (!$email) {
            $error = 'Session expired. Please start again.';
            $step = 'email';
            unset($_SESSION['reset_step'], $_SESSION['reset_email'], $_SESSION['reset_mode']);
        } else {
            $canResend = canResendOTP($pdo, $email, 'password_reset', $mode);
            if (!$canResend['can_resend']) {
                $error = 'Please wait ' . $canResend['wait_seconds'] . ' seconds before requesting a new OTP.';
            } else {
                $otpCode = createOTP($pdo, $email, 'password_reset', $mode);
                if (sendPasswordResetOTP($pdo, $email, $otpCode, $mode)) {
                    $success = 'New OTP sent to your email.';
                } else {
                    $error = 'Failed to send OTP. Please try again.';
                }
            }
            $step = 'verify';
            $resetEmail = $email;
            $resetMode = $mode;
        }
    } elseif ($action === 'cancel') {
        // Cancel reset
        unset($_SESSION['reset_step'], $_SESSION['reset_email'], $_SESSION['reset_mode'], $_SESSION['reset_verified']);
        $step = 'email';
        $resetEmail = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo htmlspecialchars($site['site_name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <style>
        .otp-input { letter-spacing: 8px; font-size: 24px; text-align: center; font-family: monospace; }
        .resend-section { text-align: center; color: #7f8c8d; font-size: 14px; margin: 15px 20px; }
        .resend-btn { 
            color: #3498db; 
            cursor: pointer; 
            text-decoration: underline; 
            background: none; 
            border: none; 
            font-size: 14px;
            padding: 0;
            font-family: inherit;
        }
        .resend-btn:hover { color: #2980b9; }
        .back-section { padding: 0 20px 20px; }
        .email-display { background: #f8f9fa; padding: 10px 15px; border-radius: 5px; margin: 0 20px 15px; word-break: break-all; font-size: 14px; }
        .success-icon { font-size: 48px; margin-bottom: 15px; color: #27ae60; }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="index.php" class="auth-brand">
                    <img src="<?php echo htmlspecialchars($site['logo_path']); ?>" alt="<?php echo htmlspecialchars($site['site_name']); ?>">
                    <span><?php echo htmlspecialchars($site['site_name']); ?></span>
                </a>
                
                <?php if ($step === 'email'): ?>
                <div class="auth-toggle">
                    <button type="button" class="toggle-btn <?php echo $mode === 'user' ? 'active' : ''; ?>" data-mode="user">User</button>
                    <button type="button" class="toggle-btn <?php echo $mode === 'admin' ? 'active' : ''; ?>" data-mode="admin">Admin</button>
                </div>
                <?php endif; ?>
                
                <h2 style="margin-top: 15px;">
                    <?php 
                    if ($step === 'email') echo 'Forgot Password';
                    elseif ($step === 'verify') echo 'Verify OTP';
                    elseif ($step === 'newpass') echo 'Set New Password';
                    else echo 'Password Reset';
                    ?>
                </h2>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($step === 'email'): ?>
            <!-- Email Form -->
            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="action" value="request">
                <input type="hidden" name="mode" id="modeInput" value="<?php echo htmlspecialchars($mode); ?>">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your registered email" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Send OTP</button>
            </form>
            
            <?php elseif ($step === 'verify'): ?>
            <!-- OTP Verification Form -->
            <div class="email-display">
                <strong>Email:</strong> <?php echo htmlspecialchars($resetEmail); ?>
                <br><small>(<?php echo $resetMode === 'admin' ? 'Admin' : 'User'; ?> Account)</small>
            </div>
            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="action" value="verify">
                <div class="form-group">
                    <label for="otp">Enter OTP</label>
                    <input type="text" id="otp" name="otp" class="otp-input" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus autocomplete="one-time-code">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Verify OTP</button>
            </form>
            
            <div class="resend-section">
                Didn't receive the code?
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="resend">
                    <button type="submit" class="resend-btn">Resend OTP</button>
                </form>
            </div>
            
            <div class="back-section">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-secondary btn-block">← Start Over</button>
                </form>
            </div>
            
            <?php elseif ($step === 'newpass'): ?>
            <!-- New Password Form -->
            <div class="email-display">
                <strong>Email:</strong> <?php echo htmlspecialchars($resetEmail); ?>
            </div>
            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="action" value="reset">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" placeholder="Minimum 8 characters" required autofocus>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
            </form>
            
            <?php elseif ($step === 'success'): ?>
            <!-- Success Message -->
            <div style="text-align: center; padding: 20px 0;">
                <div class="success-icon">✓</div>
                <p>Your password has been reset successfully.</p>
                <a href="login.php?mode=<?php echo htmlspecialchars($resetMode); ?>" class="btn btn-primary btn-block" style="margin-top: 20px;">Login Now</a>
            </div>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p><a href="login.php">← Back to Login</a></p>
            </div>
        </div>
    </div>
    
    <script>
    // Toggle between user and admin mode
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('modeInput').value = this.dataset.mode;
        });
    });
    
    // Auto-format OTP input
    const otpInput = document.getElementById('otp');
    if (otpInput) {
        otpInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    }
    </script>
</body>
</html>
