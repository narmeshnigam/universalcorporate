-- OTP System Database Setup
-- Run this script to add OTP verification tables

USE universal_corporate;

-- Create OTP verification table for registration and password reset
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create pending registrations table (stores user data until OTP is verified)
CREATE TABLE IF NOT EXISTS pending_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clean up expired OTPs (run periodically via cron or manually)
-- DELETE FROM otp_verifications WHERE expires_at < NOW();
-- DELETE FROM pending_registrations WHERE expires_at < NOW();
