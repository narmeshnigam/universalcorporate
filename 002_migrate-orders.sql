-- Migration script for orders system
-- Run this if you already have the tables created

USE universal_corporate;

-- Add new columns to products table
ALTER TABLE products 
    MODIFY COLUMN sku VARCHAR(50) NULL,
    MODIFY COLUMN unit_price DECIMAL(10,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS image_path VARCHAR(500) DEFAULT NULL AFTER description,
    ADD COLUMN IF NOT EXISTS specifications JSON DEFAULT NULL AFTER image_path,
    ADD COLUMN IF NOT EXISTS is_user_added TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS added_by_user_id INT DEFAULT NULL;

-- Add new columns to orders table
ALTER TABLE orders 
    ADD COLUMN IF NOT EXISTS has_unpriced_items TINYINT(1) DEFAULT 0 AFTER user_id,
    ADD COLUMN IF NOT EXISTS price_confirmed TINYINT(1) DEFAULT 0 AFTER has_unpriced_items,
    MODIFY COLUMN subtotal DECIMAL(12,2) DEFAULT NULL,
    MODIFY COLUMN tax_amount DECIMAL(12,2) DEFAULT NULL,
    MODIFY COLUMN total_amount DECIMAL(12,2) DEFAULT NULL;

-- Modify order_items table
ALTER TABLE order_items 
    MODIFY COLUMN product_id INT DEFAULT NULL,
    MODIFY COLUMN product_sku VARCHAR(50) DEFAULT NULL,
    MODIFY COLUMN unit_price DECIMAL(10,2) DEFAULT NULL,
    MODIFY COLUMN tax_amount DECIMAL(10,2) DEFAULT NULL,
    MODIFY COLUMN line_total DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_custom_item TINYINT(1) DEFAULT 0;

-- Remove foreign key constraint if exists (to allow null product_id)
-- Note: You may need to find the actual constraint name first
-- ALTER TABLE order_items DROP FOREIGN KEY order_items_ibfk_2;
