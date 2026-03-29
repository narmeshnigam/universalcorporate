-- Migration: Add full_name and mobile columns to site_users table
-- Also add columns to pending_registrations table

USE universal_corporate;

-- Add columns to site_users table
ALTER TABLE site_users 
ADD COLUMN full_name VARCHAR(255) DEFAULT NULL AFTER email,
ADD COLUMN mobile VARCHAR(20) DEFAULT NULL AFTER full_name,
ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER mobile,
ADD INDEX idx_mobile (mobile),
ADD INDEX idx_active (is_active);

-- Add columns to pending_registrations table
ALTER TABLE pending_registrations 
ADD COLUMN full_name VARCHAR(255) DEFAULT NULL AFTER email,
ADD COLUMN mobile VARCHAR(20) DEFAULT NULL AFTER full_name;
