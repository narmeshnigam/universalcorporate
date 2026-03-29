-- Migration: Add About Section settings to site_identity table

USE universal_corporate;

-- Insert default about section values
INSERT IGNORE INTO site_identity (setting_key, setting_value) VALUES
('about_heading', 'Your Trusted Partner for Daily Supplies'),
('about_content', 'We provide reliable delivery of essential supplies for offices, schools, and housekeeping needs. From stationery and cleaning products to pantry essentials, we ensure your workplace runs smoothly with timely doorstep delivery and competitive pricing.'),
('about_button_text', ''),
('about_button_link', '');
