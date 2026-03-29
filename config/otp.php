<?php
/**
 * OTP Helper Functions
 * Provides OTP generation, verification, and email sending functionality
 */

require_once __DIR__ . '/email.php';

// OTP Configuration
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_MAX_ATTEMPTS', 3);
define('OTP_RESEND_COOLDOWN_SECONDS', 60);

/**
 * Generate a random OTP code
 */
function generateOTP() {
    return str_pad(random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
}

/**
 * Create and store OTP for email verification
 */
function createOTP($pdo, $email, $otpType, $userType = 'user') {
    // Delete any existing unused OTPs for this email/type combination
    $stmt = $pdo->prepare("DELETE FROM otp_verifications WHERE email = ? AND otp_type = ? AND user_type = ? AND is_used = 0");
    $stmt->execute([$email, $otpType, $userType]);
    
    $otpCode = generateOTP();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    
    $stmt = $pdo->prepare("INSERT INTO otp_verifications (email, otp_code, otp_type, user_type, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$email, $otpCode, $otpType, $userType, $expiresAt]);
    
    return $otpCode;
}

/**
 * Verify OTP code
 */
function verifyOTP($pdo, $email, $otpCode, $otpType, $userType = 'user') {
    // Get the latest OTP for this email/type
    $stmt = $pdo->prepare("
        SELECT id, otp_code, expires_at, is_used, attempts 
        FROM otp_verifications 
        WHERE email = ? AND otp_type = ? AND user_type = ? AND is_used = 0
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$email, $otpType, $userType]);
    $otp = $stmt->fetch();
    
    if (!$otp) {
        return ['success' => false, 'error' => 'No OTP found. Please request a new one.'];
    }
    
    // Check if expired
    if (strtotime($otp['expires_at']) < time()) {
        return ['success' => false, 'error' => 'OTP has expired. Please request a new one.'];
    }
    
    // Check attempts
    if ($otp['attempts'] >= OTP_MAX_ATTEMPTS) {
        return ['success' => false, 'error' => 'Too many failed attempts. Please request a new OTP.'];
    }
    
    // Verify OTP
    if ($otp['otp_code'] !== $otpCode) {
        // Increment attempts
        $stmt = $pdo->prepare("UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = ?");
        $stmt->execute([$otp['id']]);
        $remainingAttempts = OTP_MAX_ATTEMPTS - $otp['attempts'] - 1;
        return ['success' => false, 'error' => "Invalid OTP. $remainingAttempts attempts remaining."];
    }
    
    // Mark OTP as used
    $stmt = $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE id = ?");
    $stmt->execute([$otp['id']]);
    
    return ['success' => true];
}

/**
 * Check if user can request new OTP (cooldown check)
 */
function canResendOTP($pdo, $email, $otpType, $userType = 'user') {
    $stmt = $pdo->prepare("
        SELECT created_at FROM otp_verifications 
        WHERE email = ? AND otp_type = ? AND user_type = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$email, $otpType, $userType]);
    $lastOtp = $stmt->fetch();
    
    if (!$lastOtp) {
        return ['can_resend' => true];
    }
    
    $timeSinceLastOtp = time() - strtotime($lastOtp['created_at']);
    if ($timeSinceLastOtp < OTP_RESEND_COOLDOWN_SECONDS) {
        $waitTime = OTP_RESEND_COOLDOWN_SECONDS - $timeSinceLastOtp;
        return ['can_resend' => false, 'wait_seconds' => $waitTime];
    }
    
    return ['can_resend' => true];
}

/**
 * Send OTP email for registration
 */
function sendRegistrationOTP($pdo, $email, $otpCode) {
    $settings = getEmailSettings($pdo);
    $siteName = $settings['smtp_from_name'] ?: 'Our Website';
    
    $subject = "Your Registration OTP - $siteName";
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
            .wrapper { padding: 20px; }
            .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: #3498db; color: white; padding: 25px 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 22px; }
            .content { padding: 30px; text-align: center; }
            .otp-box { background: #f8f9fa; border: 2px dashed #3498db; padding: 20px; margin: 20px 0; border-radius: 8px; }
            .otp-code { font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #2c3e50; font-family: monospace; }
            .info { color: #7f8c8d; font-size: 14px; margin-top: 20px; }
            .warning { background: #fff3cd; padding: 12px; border-radius: 5px; color: #856404; font-size: 13px; margin-top: 15px; }
            .footer { background: #f8f9fa; padding: 15px 30px; text-align: center; border-top: 1px solid #eee; }
            .footer p { margin: 0; font-size: 12px; color: #95a5a6; }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <div class="container">
                <div class="header">
                    <h1>Email Verification</h1>
                </div>
                <div class="content">
                    <p>Please use the following OTP to complete your registration:</p>
                    <div class="otp-box">
                        <div class="otp-code">' . $otpCode . '</div>
                    </div>
                    <p class="info">This OTP is valid for ' . OTP_EXPIRY_MINUTES . ' minutes.</p>
                    <div class="warning">
                        If you did not request this verification, please ignore this email.
                    </div>
                </div>
                <div class="footer">
                    <p>This is an automated message. Please do not reply.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($pdo, $email, $subject, $body, true);
}

/**
 * Send OTP email for password reset
 */
function sendPasswordResetOTP($pdo, $email, $otpCode, $userType = 'user') {
    $settings = getEmailSettings($pdo);
    $siteName = $settings['smtp_from_name'] ?: 'Our Website';
    $accountType = $userType === 'admin' ? 'Admin' : 'User';
    
    $subject = "Password Reset OTP - $siteName";
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
            .wrapper { padding: 20px; }
            .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: #e74c3c; color: white; padding: 25px 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 22px; }
            .content { padding: 30px; text-align: center; }
            .otp-box { background: #f8f9fa; border: 2px dashed #e74c3c; padding: 20px; margin: 20px 0; border-radius: 8px; }
            .otp-code { font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #2c3e50; font-family: monospace; }
            .info { color: #7f8c8d; font-size: 14px; margin-top: 20px; }
            .warning { background: #fff3cd; padding: 12px; border-radius: 5px; color: #856404; font-size: 13px; margin-top: 15px; }
            .footer { background: #f8f9fa; padding: 15px 30px; text-align: center; border-top: 1px solid #eee; }
            .footer p { margin: 0; font-size: 12px; color: #95a5a6; }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <div class="container">
                <div class="header">
                    <h1>Password Reset Request</h1>
                </div>
                <div class="content">
                    <p>We received a request to reset your ' . $accountType . ' account password.</p>
                    <p>Use the following OTP to reset your password:</p>
                    <div class="otp-box">
                        <div class="otp-code">' . $otpCode . '</div>
                    </div>
                    <p class="info">This OTP is valid for ' . OTP_EXPIRY_MINUTES . ' minutes.</p>
                    <div class="warning">
                        If you did not request a password reset, please ignore this email and your password will remain unchanged.
                    </div>
                </div>
                <div class="footer">
                    <p>This is an automated message. Please do not reply.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($pdo, $email, $subject, $body, true);
}

/**
 * Store pending registration data
 */
function storePendingRegistration($pdo, $email, $passwordHash, $fullName = null, $mobile = null) {
    try {
        // Delete any existing pending registration for this email
        $stmt = $pdo->prepare("DELETE FROM pending_registrations WHERE email = ?");
        $stmt->execute([$email]);
        
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        
        $stmt = $pdo->prepare("INSERT INTO pending_registrations (email, password_hash, full_name, mobile, expires_at) VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute([$email, $passwordHash, $fullName, $mobile, $expiresAt]);
        
        return $result;
    } catch (Exception $e) {
        error_log("storePendingRegistration error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get pending registration data
 */
function getPendingRegistration($pdo, $email) {
    $stmt = $pdo->prepare("SELECT * FROM pending_registrations WHERE email = ?");
    $stmt->execute([$email]);
    $result = $stmt->fetch();
    
    if (!$result) {
        return false;
    }
    
    // Use PHP time for comparison to avoid timezone issues
    $now = time();
    $expiresAt = strtotime($result['expires_at']);
    
    if ($now > $expiresAt) {
        error_log("getPendingRegistration: Record expired for email: $email");
        return false;
    }
    
    return $result;
}

/**
 * Complete registration after OTP verification
 */
function completePendingRegistration($pdo, $email) {
    $pending = getPendingRegistration($pdo, $email);
    if (!$pending) {
        return ['success' => false, 'error' => 'Registration session expired. Please register again.'];
    }
    
    // Check if email already exists in site_users
    $stmt = $pdo->prepare("SELECT id FROM site_users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'An account with this email already exists.'];
    }
    
    // Create the user account
    $stmt = $pdo->prepare("INSERT INTO site_users (email, full_name, mobile, password) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$email, $pending['full_name'], $pending['mobile'], $pending['password_hash']])) {
        // Delete pending registration
        $stmt = $pdo->prepare("DELETE FROM pending_registrations WHERE email = ?");
        $stmt->execute([$email]);
        return ['success' => true];
    }
    
    return ['success' => false, 'error' => 'Failed to create account. Please try again.'];
}

/**
 * Clean up expired OTPs and pending registrations
 */
function cleanupExpiredOTPs($pdo) {
    $pdo->exec("DELETE FROM otp_verifications WHERE expires_at < NOW()");
    $pdo->exec("DELETE FROM pending_registrations WHERE expires_at < NOW()");
}
