<?php
session_start();
require_once '../config/database.php';
require_once '../config/otp.php';
require_once '../config/identity.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    die('Database connection failed. Please check your database configuration.');
}

$site = getSiteIdentity($pdo);

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$step = $_SESSION['register_step'] ?? 'form';
$pendingEmail = $_SESSION['register_email'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'register';
    
    if ($action === 'register') {
        // Step 1: Initial registration form
        $fullName = trim($_POST['full_name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!$fullName || !$mobile || !$email || !$password || !$confirmPassword) {
            $error = 'Please fill in all fields';
        } elseif (strlen($fullName) < 2) {
            $error = 'Please enter your full name';
        } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
            $error = 'Please enter a valid 10-digit mobile number';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM site_users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email already exists';
            } else {
                // Check if mobile already exists
                $stmt = $pdo->prepare("SELECT id FROM site_users WHERE mobile = ?");
                $stmt->execute([$mobile]);
                if ($stmt->fetch()) {
                    $error = 'An account with this mobile number already exists';
                } else {
                    // Store pending registration
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    if (storePendingRegistration($pdo, $email, $passwordHash, $fullName, $mobile)) {
                        // Generate and send OTP
                        $otpCode = createOTP($pdo, $email, 'registration', 'user');
                        if (sendRegistrationOTP($pdo, $email, $otpCode)) {
                            $_SESSION['register_step'] = 'verify';
                            $_SESSION['register_email'] = $email;
                            $step = 'verify';
                            $pendingEmail = $email;
                            $success = 'OTP sent to your email. Please check your inbox.';
                        } else {
                            $error = 'Failed to send OTP. Please check email settings or try again.';
                        }
                    } else {
                        $error = 'Failed to process registration. Please try again.';
                    }
                }
            }
        }
    } elseif ($action === 'verify') {
        // Step 2: Verify OTP
        $otpCode = trim($_POST['otp'] ?? '');
        $email = $_SESSION['register_email'] ?? '';
        
        if (!$email) {
            $error = 'Session expired. Please start registration again.';
            $step = 'form';
            unset($_SESSION['register_step'], $_SESSION['register_email']);
        } elseif (!$otpCode) {
            $error = 'Please enter the OTP';
            $step = 'verify';
            $pendingEmail = $email;
        } else {
            $result = verifyOTP($pdo, $email, $otpCode, 'registration', 'user');
            if ($result['success']) {
                // Complete registration
                $regResult = completePendingRegistration($pdo, $email);
                if ($regResult['success']) {
                    unset($_SESSION['register_step'], $_SESSION['register_email']);
                    $success = 'Account created successfully! You can now login.';
                    $step = 'success';
                } else {
                    $error = $regResult['error'];
                    $step = 'verify';
                    $pendingEmail = $email;
                }
            } else {
                $error = $result['error'];
                $step = 'verify';
                $pendingEmail = $email;
            }
        }
    } elseif ($action === 'resend') {
        // Resend OTP
        $email = $_SESSION['register_email'] ?? '';
        if (!$email) {
            $error = 'Session expired. Please start registration again.';
            $step = 'form';
            unset($_SESSION['register_step'], $_SESSION['register_email']);
        } else {
            $canResend = canResendOTP($pdo, $email, 'registration', 'user');
            if (!$canResend['can_resend']) {
                $error = 'Please wait ' . $canResend['wait_seconds'] . ' seconds before requesting a new OTP.';
            } else {
                $otpCode = createOTP($pdo, $email, 'registration', 'user');
                if (sendRegistrationOTP($pdo, $email, $otpCode)) {
                    $success = 'New OTP sent to your email.';
                } else {
                    $error = 'Failed to send OTP. Please try again.';
                }
            }
            $step = 'verify';
            $pendingEmail = $email;
        }
    } elseif ($action === 'cancel') {
        // Cancel registration
        unset($_SESSION['register_step'], $_SESSION['register_email']);
        $step = 'form';
        $pendingEmail = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration - <?php echo htmlspecialchars($site['site_name']); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/auth.css">
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
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="../index.php" class="auth-brand">
                    <img src="../<?php echo htmlspecialchars($site['logo_path']); ?>" alt="<?php echo htmlspecialchars($site['site_name']); ?>">
                    <span><?php echo htmlspecialchars($site['site_name']); ?></span>
                </a>
                <h2 style="margin-top: 10px; margin-bottom: 0;"><?php echo $step === 'verify' ? 'Verify Email' : ($step === 'success' ? 'Success!' : 'Create Account'); ?></h2>
                <p style="margin: 5px 0 0; font-size: 12px; opacity: 0.8;"><?php echo $step === 'verify' ? 'Enter the OTP sent to your email' : ($step === 'success' ? 'Your account has been created' : 'Register to access your user panel'); ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($step === 'form'): ?>
            <!-- Registration Form -->
            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label for="full_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" placeholder="Enter your full name" required autofocus value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="mobile">Mobile Number <span class="required">*</span></label>
                    <input type="tel" id="mobile" name="mobile" placeholder="10-digit mobile number" required pattern="[0-9]{10}" maxlength="10" value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="user@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" placeholder="Minimum 8 characters" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Continue</button>
            </form>
            
            <?php elseif ($step === 'verify'): ?>
            <!-- OTP Verification Form -->
            <div class="email-display">
                <strong>Email:</strong> <?php echo htmlspecialchars($pendingEmail); ?>
            </div>
            <form method="POST" action="" class="auth-form" id="otpForm">
                <input type="hidden" name="action" value="verify">
                <div class="form-group">
                    <label for="otp">Enter OTP</label>
                    <input type="text" id="otp" name="otp" class="otp-input" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus autocomplete="one-time-code">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Verify & Create Account</button>
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
            
            <?php elseif ($step === 'success'): ?>
            <!-- Success Message -->
            <div style="text-align: center; padding: 30px 20px;">
                <div style="font-size: 48px; margin-bottom: 15px; color: #27ae60;">✓</div>
                <p style="margin-bottom: 20px;">Your account has been created successfully.</p>
                <a href="login.php" class="btn btn-primary btn-block">Login Now</a>
            </div>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    
    <script>
    // Auto-focus and format OTP input
    const otpInput = document.getElementById('otp');
    if (otpInput) {
        otpInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    }
    
    // Mobile number validation
    const mobileInput = document.getElementById('mobile');
    if (mobileInput) {
        mobileInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
    }
    </script>
</body>
</html>
