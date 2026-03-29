-- Migration: Add payment tracking columns to orders table
-- Run this script to add payment acceptance functionality

USE universal_corporate;

-- Add payment tracking columns
ALTER TABLE orders
    ADD COLUMN payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid' AFTER payment_mode,
    ADD COLUMN payment_date DATE DEFAULT NULL AFTER payment_status,
    ADD COLUMN payment_transaction_id VARCHAR(100) DEFAULT NULL AFTER payment_date,
    ADD COLUMN payment_notes TEXT DEFAULT NULL AFTER payment_transaction_id;

-- Add index for payment status
ALTER TABLE orders ADD INDEX idx_payment_status (payment_status);
