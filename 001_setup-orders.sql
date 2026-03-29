-- Orders System Database Setup
-- Run this script to add products and orders tables

USE universal_corporate;

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    image_path VARCHAR(500) DEFAULT NULL,
    specifications JSON DEFAULT NULL,
    unit VARCHAR(50) DEFAULT 'piece',
    unit_price DECIMAL(10,2) DEFAULT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 18.00,
    is_active TINYINT(1) DEFAULT 1,
    is_user_added TINYINT(1) DEFAULT 0,
    added_by_user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sku (sku),
    INDEX idx_name (name),
    INDEX idx_active (is_active),
    INDEX idx_user_added (is_user_added),
    INDEX idx_priced (unit_price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    
    -- Price Status
    has_unpriced_items TINYINT(1) DEFAULT 0,
    price_confirmed TINYINT(1) DEFAULT 0,
    
    -- Billing Information
    billing_name VARCHAR(255) NOT NULL,
    billing_email VARCHAR(255) NOT NULL,
    billing_phone VARCHAR(20),
    billing_address TEXT NOT NULL,
    billing_city VARCHAR(100) NOT NULL,
    billing_state VARCHAR(100) NOT NULL,
    billing_pincode VARCHAR(20) NOT NULL,
    billing_country VARCHAR(100) DEFAULT 'India',
    
    -- Shipping Information
    shipping_same_as_billing TINYINT(1) DEFAULT 1,
    shipping_name VARCHAR(255),
    shipping_phone VARCHAR(20),
    shipping_address TEXT,
    shipping_city VARCHAR(100),
    shipping_state VARCHAR(100),
    shipping_pincode VARCHAR(20),
    shipping_country VARCHAR(100) DEFAULT 'India',
    
    -- Tax Information
    gstin VARCHAR(20),
    pan_number VARCHAR(20),
    company_name VARCHAR(255),
    
    -- Order Details
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    
    -- Payment & Delivery
    payment_mode ENUM('cash', 'cheque', 'bank_transfer', 'upi', 'credit') DEFAULT 'bank_transfer',
    preferred_delivery_date DATE,
    delivery_instructions TEXT,
    
    -- Status
    status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_order_number (order_number),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES site_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(50),
    unit VARCHAR(50) DEFAULT 'piece',
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) DEFAULT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 18.00,
    tax_amount DECIMAL(10,2) DEFAULT NULL,
    line_total DECIMAL(12,2) DEFAULT NULL,
    is_custom_item TINYINT(1) DEFAULT 0,
    
    INDEX idx_order_id (order_id),
    INDEX idx_product_id (product_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Products
INSERT INTO products (sku, name, description, unit, unit_price, tax_rate) VALUES
('OFF-PEN-001', 'Ball Point Pen (Pack of 10)', 'Blue ink ball point pens', 'pack', 50.00, 18.00),
('OFF-PAP-001', 'A4 Paper Ream (500 sheets)', 'Premium quality A4 paper', 'ream', 350.00, 18.00),
('OFF-STP-001', 'Stapler with Pins', 'Heavy duty stapler with 1000 pins', 'piece', 150.00, 18.00),
('OFF-FLD-001', 'File Folder (Pack of 10)', 'Plastic file folders assorted colors', 'pack', 120.00, 18.00),
('OFF-NTB-001', 'Spiral Notebook A5', 'Ruled spiral notebook 200 pages', 'piece', 80.00, 18.00),
('CLN-MOP-001', 'Floor Mop with Bucket', 'Spin mop with wringer bucket', 'set', 650.00, 18.00),
('CLN-DSF-001', 'Disinfectant (5L)', 'Surface disinfectant liquid', 'can', 450.00, 18.00),
('CLN-TIS-001', 'Tissue Paper Roll (Pack of 6)', 'Soft tissue paper rolls', 'pack', 180.00, 18.00),
('CLN-GLV-001', 'Rubber Gloves (Pair)', 'Heavy duty cleaning gloves', 'pair', 75.00, 18.00),
('CLN-BRM-001', 'Broom and Dustpan Set', 'Long handle broom with dustpan', 'set', 220.00, 18.00);
