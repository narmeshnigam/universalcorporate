-- Dummy Data Import Script for Universal Corporate
-- Run this after setup-database.sql to populate tables with sample data

USE universal_corporate;

-- =====================================================
-- HERO SLIDES - Office/Business Supply Images
-- =====================================================
INSERT INTO hero_slides (image_path, image_path_mobile, title, subtitle, sort_order, is_active) VALUES
('https://images.unsplash.com/photo-1497366216548-37526070297c?w=1920&q=80', 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&q=80', 'Your One-Stop Shop for Office Supplies', 'Quality products delivered to your doorstep', 1, 1),
('https://images.unsplash.com/photo-1542744173-8e7e53415bb0?w=1920&q=80', 'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?w=800&q=80', 'Bulk Orders Made Easy', 'Special pricing for businesses and institutions', 2, 1),
('https://images.unsplash.com/photo-1586281380349-632531db7ed4?w=1920&q=80', 'https://images.unsplash.com/photo-1586281380349-632531db7ed4?w=800&q=80', 'Premium Stationery Collection', 'From pens to paper, we have it all', 3, 1),
('https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=1920&q=80', 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=800&q=80', 'Trusted by 500+ Businesses', 'Join our growing family of satisfied customers', 4, 1);

-- =====================================================
-- SERVICES - Business Service Categories
-- =====================================================
INSERT INTO services (image_path, title, subtitle, sort_order, is_active) VALUES
('https://images.unsplash.com/photo-1586281380349-632531db7ed4?w=800&q=80', 'Office Stationery', 'Pens, notebooks, files, and all essential office supplies', 1, 1),
('https://images.unsplash.com/photo-1563453392212-326f5e854473?w=800&q=80', 'Cleaning Supplies', 'Professional cleaning products for spotless workspaces', 2, 1),
('https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=800&q=80', 'School Supplies', 'Complete range of educational materials and stationery', 3, 1),
('https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=800&q=80', 'Pantry Essentials', 'Tea, coffee, snacks, and refreshments for your office', 4, 1),
('https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=800&q=80', 'Housekeeping Items', 'Brooms, mops, dustbins, and maintenance supplies', 5, 1),
('https://images.unsplash.com/photo-1612815154858-60aa4c59eaa6?w=800&q=80', 'IT Accessories', 'Cables, adapters, mouse pads, and tech essentials', 6, 1);

-- =====================================================
-- CLIENTS/CUSTOMERS - Company Logos
-- =====================================================
INSERT INTO clients (logo_path, client_name, sort_order, is_active) VALUES
('https://upload.wikimedia.org/wikipedia/commons/thumb/2/2f/Google_2015_logo.svg/200px-Google_2015_logo.svg.png', 'Google', 1, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/4/44/Microsoft_logo.svg/200px-Microsoft_logo.svg.png', 'Microsoft', 2, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/a/a9/Amazon_logo.svg/200px-Amazon_logo.svg.png', 'Amazon', 3, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/5/51/IBM_logo.svg/200px-IBM_logo.svg.png', 'IBM', 4, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/0/08/Netflix_2015_logo.svg/200px-Netflix_2015_logo.svg.png', 'Netflix', 5, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/e/e8/Tesla_logo.png/200px-Tesla_logo.png', 'Tesla', 6, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/2/26/Spotify_logo_with_text.svg/200px-Spotify_logo_with_text.svg.png', 'Spotify', 7, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/9/96/Infosys_logo.svg/200px-Infosys_logo.svg.png', 'Infosys', 8, 1);

-- =====================================================
-- BRANDS - Product Brand Logos
-- =====================================================
INSERT INTO brands (logo_path, brand_name, sort_order, is_active) VALUES
('https://upload.wikimedia.org/wikipedia/commons/thumb/4/4b/Staples_Logo.svg/200px-Staples_Logo.svg.png', 'Staples', 1, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/d/d9/Faber-Castell_logo.svg/200px-Faber-Castell_logo.svg.png', 'Faber-Castell', 2, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/f/f9/HP_logo_2012.svg/200px-HP_logo_2012.svg.png', 'HP', 3, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/b/b8/Epson_logo.svg/200px-Epson_logo.svg.png', 'Epson', 4, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/2/24/Samsung_Logo.svg/200px-Samsung_Logo.svg.png', 'Samsung', 5, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/0/0e/Logitech_logo.svg/200px-Logitech_logo.svg.png', 'Logitech', 6, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/4/48/Dell_Logo.svg/200px-Dell_Logo.svg.png', 'Dell', 7, 1),
('https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/3M_wordmark.svg/200px-3M_wordmark.svg.png', '3M', 8, 1);

-- =====================================================
-- CTA BANNER
-- =====================================================
INSERT INTO cta_banners (image_path, heading, subheading, button_text, button_link, is_active) VALUES
('https://images.unsplash.com/photo-1497366811353-6870744d04b2?w=1200&q=80', 'Ready to Simplify Your Procurement?', 'Get exclusive discounts on bulk orders. Contact us today for a custom quote tailored to your business needs.', 'Get Started', '#contact', 1);

-- =====================================================
-- FAQs
-- =====================================================
INSERT INTO faqs (question, answer, sort_order, is_active) VALUES
('What is the minimum order quantity?', 'There is no minimum order quantity for most products. However, for bulk orders above ₹5,000, you get additional discounts ranging from 5% to 15% depending on the order value.', 1, 1),
('How long does delivery take?', 'For orders within the city, we offer same-day or next-day delivery. For outstation orders, delivery typically takes 3-5 business days depending on the location.', 2, 1),
('Do you offer credit terms for businesses?', 'Yes, we offer credit terms of 15-30 days for registered businesses after verification. Please contact our sales team to set up a business account.', 3, 1),
('Can I return or exchange products?', 'Yes, we have a hassle-free return policy. Unopened products can be returned within 7 days of delivery. Defective items are replaced immediately at no extra cost.', 4, 1),
('Do you provide GST invoices?', 'Yes, we provide proper GST invoices for all orders. Please ensure you provide your GSTIN during checkout to avail input tax credit.', 5, 1),
('What payment methods do you accept?', 'We accept all major payment methods including UPI, credit/debit cards, net banking, and bank transfers. For business accounts, we also accept cheques and offer credit terms.', 6, 1),
('Do you deliver to all locations in India?', 'Yes, we deliver across India. Metro cities have faster delivery times, while remote areas may take slightly longer. Shipping is free for orders above ₹2,000.', 7, 1),
('Can I track my order?', 'Yes, once your order is dispatched, you will receive a tracking link via SMS and email. You can track your order in real-time through our delivery partner\'s website.', 8, 1);

-- =====================================================
-- ENQUIRIES - Sample Enquiries
-- =====================================================
INSERT INTO enquiries (name, email, phone, subject, message, page_name, ip_address, city, country, status, submitted_at) VALUES
('Rajesh Kumar', 'rajesh.kumar@techcorp.in', '+91 98765 43210', 'Bulk Order Inquiry', 'We are looking to place a bulk order for office stationery for our new branch. Please share your catalog and pricing for orders above 50,000 INR.', 'services', '103.45.67.89', 'Mumbai', 'India', 'new', NOW() - INTERVAL 2 HOUR),
('Priya Sharma', 'priya.sharma@eduschool.org', '+91 87654 32109', 'School Supplies Quote', 'Our school needs supplies for the upcoming academic year. We need notebooks, pens, geometry boxes, and art supplies for approximately 500 students.', 'index', '182.73.45.12', 'Delhi', 'India', 'read', NOW() - INTERVAL 1 DAY),
('Amit Patel', 'amit.patel@cleanpro.com', '+91 76543 21098', 'Cleaning Products Partnership', 'We are a facility management company and would like to discuss a long-term partnership for cleaning supplies. Please contact us to schedule a meeting.', 'contact', '157.32.89.45', 'Bangalore', 'India', 'replied', NOW() - INTERVAL 3 DAY),
('Sneha Reddy', 'sneha.r@startupinc.io', '+91 65432 10987', 'Office Setup Requirements', 'We are setting up a new office for 50 employees. Need complete office supplies including stationery, pantry items, and housekeeping materials. Please send a comprehensive quote.', 'services', '203.56.78.90', 'Hyderabad', 'India', 'new', NOW() - INTERVAL 5 HOUR),
('Vikram Singh', 'vikram@hospitalitygroup.in', '+91 54321 09876', 'Hotel Supplies Inquiry', 'Looking for housekeeping and cleaning supplies for our hotel chain. We have 5 properties and need regular monthly supplies. Interested in discussing bulk pricing.', 'index', '115.89.23.67', 'Jaipur', 'India', 'new', NOW() - INTERVAL 30 MINUTE);

-- =====================================================
-- SITE IDENTITY - Update with business card data
-- =====================================================
UPDATE site_identity SET setting_value = 'Universal Corporate' WHERE setting_key = 'site_name';
UPDATE site_identity SET setting_value = 'Office & Educational Services' WHERE setting_key = 'site_tagline';
UPDATE site_identity SET setting_value = 'Universal Corporate is your one-stop solution for office supplies, cleaning products, pantry essentials, and housekeeping items. We serve businesses, schools, and institutions across India with quality products and reliable delivery.' WHERE setting_key = 'site_description';
UPDATE site_identity SET setting_value = '+91 98705 56310' WHERE setting_key = 'phone';
UPDATE site_identity SET setting_value = '919870556310' WHERE setting_key = 'phone_raw';
UPDATE site_identity SET setting_value = '919870556310' WHERE setting_key = 'whatsapp';
UPDATE site_identity SET setting_value = 'rahul@universalcorporate.in' WHERE setting_key = 'email';
UPDATE site_identity SET setting_value = 'Bhagwati Vihar, Block-V, Sector-C, Uttam Nagar, New Delhi 110059' WHERE setting_key = 'address';
UPDATE site_identity SET setting_value = 'Mon - Sat: 9:00 AM - 7:00 PM' WHERE setting_key = 'working_hours';

-- =====================================================
-- EMAIL SETTINGS - Sample SMTP config (disabled by default)
-- =====================================================
INSERT INTO email_settings (setting_key, setting_value) VALUES
('smtp_enabled', '0'),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_encryption', 'tls'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_from_email', 'noreply@universalcorporate.in'),
('smtp_from_name', 'Universal Corporate'),
('notification_emails', '')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- Verify data import
-- =====================================================
SELECT 'Hero Slides' as TableName, COUNT(*) as Records FROM hero_slides
UNION ALL
SELECT 'Services', COUNT(*) FROM services
UNION ALL
SELECT 'Clients', COUNT(*) FROM clients
UNION ALL
SELECT 'Brands', COUNT(*) FROM brands
UNION ALL
SELECT 'CTA Banners', COUNT(*) FROM cta_banners
UNION ALL
SELECT 'FAQs', COUNT(*) FROM faqs
UNION ALL
SELECT 'Enquiries', COUNT(*) FROM enquiries
UNION ALL
SELECT 'Site Identity', COUNT(*) FROM site_identity
UNION ALL
SELECT 'Email Settings', COUNT(*) FROM email_settings;
