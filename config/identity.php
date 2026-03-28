<?php
/**
 * Site Identity Helper
 * Loads site-wide settings from the database.
 * Usage: $site = getSiteIdentity($pdo);
 *        echo $site['site_name'];
 */

function getSiteIdentity($pdo) {
    $defaults = [
        'site_name' => 'Universal Corporate',
        'site_tagline' => 'Your trusted partner for daily supplies',
        'site_description' => 'We deliver essential products for offices, schools, and housekeeping needs with reliability and competitive pricing.',
        'phone' => '+91 12345 67890',
        'phone_raw' => '911234567890',
        'whatsapp' => '911234567890',
        'email' => 'info@universalcorporate.com',
        'address' => '123 Business Avenue, New Delhi, India',
        'working_hours' => 'Mon - Sat: 9:00 AM - 6:00 PM',
        'logo_path' => 'assets/branding/default_logo.png',
        'legal_company_name' => 'Universal Corporate Private Limited',
        'legal_address' => '123 Business Avenue, New Delhi, India - 110001',
        'legal_email' => 'legal@universalcorporate.com',
        'legal_phone' => '+91 12345 67890',
        'legal_gstin' => '',
        'legal_pan' => '',
    ];

    if (!$pdo) return $defaults;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_identity (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_identity");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_merge($defaults, $rows);
    } catch (Exception $e) {
        return $defaults;
    }
}
