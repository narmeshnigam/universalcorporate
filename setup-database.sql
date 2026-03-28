-- Database Setup Script for Universal Corporate
-- Run this script to create the database and initial tables

-- Create database
CREATE DATABASE IF NOT EXISTS universal_corporate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE universal_corporate;

-- Create users table for admin authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create site_users table for user panel authentication
CREATE TABLE IF NOT EXISTS site_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grant privileges (adjust username/host as needed)
-- GRANT ALL PRIVILEGES ON universal_corporate.* TO 'root'@'localhost';
-- FLUSH PRIVILEGES;

-- Create enquiries table for enquiry form submissions
CREATE TABLE IF NOT EXISTS enquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    page_name VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
    INDEX idx_status (status),
    INDEX idx_submitted_at (submitted_at),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create hero_slides table for homepage slider
CREATE TABLE IF NOT EXISTS hero_slides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(500) NOT NULL,
    image_path_mobile VARCHAR(500) DEFAULT NULL,
    title VARCHAR(255) DEFAULT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create services table for services grid
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(500) NOT NULL,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create clients table for client logos
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    logo_path VARCHAR(500) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create brands table for brand logos
CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    logo_path VARCHAR(500) NOT NULL,
    brand_name VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create CTA banners table
CREATE TABLE IF NOT EXISTS cta_banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(500) NOT NULL,
    heading VARCHAR(255) DEFAULT NULL,
    subheading VARCHAR(500) DEFAULT NULL,
    button_text VARCHAR(100) DEFAULT NULL,
    button_link VARCHAR(500) DEFAULT '#contact',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create FAQs table
CREATE TABLE IF NOT EXISTS faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data (optional)
INSERT IGNORE INTO enquiries (name, email, message) VALUES
('John Doe', 'john@example.com', 'This is a test message'),
('Jane Smith', 'jane@example.com', 'Interested in your services');


-- Create site_identity table for site-wide settings
CREATE TABLE IF NOT EXISTS site_identity (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default identity values
INSERT IGNORE INTO site_identity (setting_key, setting_value) VALUES
('site_name', 'Universal Corporate'),
('site_tagline', 'Your trusted partner for daily supplies'),
('site_description', 'We deliver essential products for offices, schools, and housekeeping needs with reliability and competitive pricing.'),
('phone', '+91 12345 67890'),
('phone_raw', '911234567890'),
('whatsapp', '911234567890'),
('email', 'info@universalcorporate.com'),
('address', '123 Business Avenue, New Delhi, India'),
('working_hours', 'Mon - Sat: 9:00 AM - 6:00 PM'),
('logo_path', 'assets/branding/default_logo.png');

-- Create email_settings table for SMTP configuration
CREATE TABLE IF NOT EXISTS email_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default email settings
INSERT IGNORE INTO email_settings (setting_key, setting_value) VALUES
('smtp_enabled', '0'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_encryption', 'tls'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_from_email', ''),
('smtp_from_name', ''),
('notification_emails', '');
