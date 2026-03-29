# OTP Verification System Guide

This document explains the OTP (One-Time Password) verification system implemented for user registration and password reset functionality.

## Features

1. **Registration OTP Verification** - New users must verify their email via OTP before account creation
2. **Forgot Password (Users)** - Site users can reset their password using email OTP
3. **Forgot Password (Admins)** - Admin users can reset their password using email OTP

## Database Setup

Run the SQL migration to create the required tables:

```bash
mysql -u root -p universal_corporate < setup-otp.sql
```

Or execute the SQL manually:

```sql
-- Create OTP verification table
CREATE TABLE IF NOT EXISTS otp_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    otp_type ENUM('registration', 'password_reset') NOT NULL,
    user_type ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_type (email, otp_type, user_type),
    INDEX idx_expires (expires_at)
);

-- Create pending registrations table
CREATE TABLE IF NOT EXISTS pending_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
);
```

## Configuration

OTP settings are defined in `config/otp.php`:

| Setting | Default | Description |
|---------|---------|-------------|
| `OTP_LENGTH` | 6 | Number of digits in OTP |
| `OTP_EXPIRY_MINUTES` | 10 | OTP validity period |
| `OTP_MAX_ATTEMPTS` | 3 | Max wrong attempts before OTP invalidation |
| `OTP_RESEND_COOLDOWN_SECONDS` | 60 | Minimum wait time between OTP requests |

## Email Configuration

The OTP system uses the existing SMTP configuration. Ensure email settings are configured in the admin panel:

1. Go to Admin Panel → Email Settings
2. Enable SMTP
3. Configure SMTP host, port, username, password
4. Set "From Email" and "From Name"
5. Test the configuration using the "Send Test Email" button

## User Flows

### Registration Flow

1. User fills registration form (email, password)
2. System validates input and checks for existing account
3. OTP is generated and sent to email
4. User enters OTP on verification page
5. On successful verification, account is created
6. User can now login

### Forgot Password Flow (Users & Admins)

1. User clicks "Forgot Password" on login page
2. Selects account type (User/Admin) and enters email
3. System sends OTP to registered email
4. User enters OTP for verification
5. On successful verification, user sets new password
6. Password is updated, user can login with new password

## File Structure

```
config/
  └── otp.php           # OTP helper functions
user/
  └── register.php      # Updated registration with OTP
forgot-password.php     # Unified forgot password page
login.php               # Updated with forgot password link
setup-otp.sql           # Database migration
```

## Security Features

- OTPs expire after 10 minutes
- Maximum 3 verification attempts per OTP
- 60-second cooldown between OTP requests
- Pending registrations expire after 30 minutes
- OTPs are marked as used after successful verification
- Password reset doesn't reveal if email exists (security through obscurity)

## Cleanup

To clean up expired OTPs and pending registrations, you can:

1. Call `cleanupExpiredOTPs($pdo)` function periodically
2. Or set up a cron job:

```bash
# Run daily at midnight
0 0 * * * mysql -u root -p'password' universal_corporate -e "DELETE FROM otp_verifications WHERE expires_at < NOW(); DELETE FROM pending_registrations WHERE expires_at < NOW();"
```

## Troubleshooting

### OTP not received
- Check SMTP settings in admin panel
- Verify "From Email" is set correctly
- Check spam/junk folder
- Review PHP error logs for SMTP errors

### OTP expired
- Request a new OTP using "Resend OTP" button
- Check server time is correct

### Too many attempts
- Wait for OTP to expire or request a new one
- Each OTP allows 3 verification attempts
