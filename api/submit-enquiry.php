<?php
/**
 * Enquiry Form Submission Handler
 * Processes form submissions and stores data with IP geolocation
 */

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

/**
 * Get client IP address
 */
function getClientIP() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle multiple IPs (take the first one)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

/**
 * Get geolocation data from IP address using ip-api.com (free, no key required)
 */
function getGeolocation($ip) {
    // Skip for local/private IPs
    if ($ip === 'Unknown' || 
        filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return ['city' => 'Local', 'country' => 'Local'];
    }
    
    try {
        $url = "http://ip-api.com/json/{$ip}?fields=status,country,city";
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return ['city' => 'Unknown', 'country' => 'Unknown'];
        }
        
        $data = json_decode($response, true);
        
        if ($data && $data['status'] === 'success') {
            return [
                'city' => $data['city'] ?? 'Unknown',
                'country' => $data['country'] ?? 'Unknown'
            ];
        }
    } catch (Exception $e) {
        error_log("Geolocation error: " . $e->getMessage());
    }
    
    return ['city' => 'Unknown', 'country' => 'Unknown'];
}

/**
 * Validate and sanitize input
 */
function validateInput($data) {
    $errors = [];
    
    // Required fields
    if (empty($data['name']) || strlen(trim($data['name'])) < 2) {
        $errors[] = 'Name is required and must be at least 2 characters';
    }
    
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required';
    }
    
    if (empty($data['message']) || strlen(trim($data['message'])) < 10) {
        $errors[] = 'Message is required and must be at least 10 characters';
    }
    
    // Optional phone validation
    if (!empty($data['phone'])) {
        $phone = preg_replace('/[^0-9+\-() ]/', '', $data['phone']);
        if (strlen($phone) < 10) {
            $errors[] = 'Please provide a valid phone number';
        }
    }
    
    return $errors;
}

try {
    // Get and validate form data
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'message' => trim($_POST['message'] ?? ''),
        'page_name' => trim($_POST['page_name'] ?? 'unknown')
    ];
    
    // Validate input
    $errors = validateInput($formData);
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => implode('. ', $errors)
        ]);
        exit;
    }
    
    // Get metadata
    $ip = getClientIP();
    $geolocation = getGeolocation($ip);
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Get database connection
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Insert enquiry into database
    $sql = "INSERT INTO enquiries 
            (name, email, phone, subject, message, page_name, ip_address, user_agent, city, country, submitted_at) 
            VALUES 
            (:name, :email, :phone, :subject, :message, :page_name, :ip_address, :user_agent, :city, :country, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':name' => $formData['name'],
        ':email' => $formData['email'],
        ':phone' => $formData['phone'] ?: null,
        ':subject' => $formData['subject'] ?: null,
        ':message' => $formData['message'],
        ':page_name' => $formData['page_name'],
        ':ip_address' => $ip,
        ':user_agent' => $userAgent,
        ':city' => $geolocation['city'],
        ':country' => $geolocation['country']
    ]);
    
    if ($result) {
        // Prepare notification data
        $notificationData = [
            'name' => $formData['name'],
            'email' => $formData['email'],
            'phone' => $formData['phone'],
            'subject' => $formData['subject'],
            'message' => $formData['message'],
            'page_name' => $formData['page_name'],
            'ip_address' => $ip,
            'city' => $geolocation['city'],
            'country' => $geolocation['country']
        ];
        
        // Send response to user immediately
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for your enquiry! We will get back to you soon.'
        ]);
        
        // Flush output to browser so user sees response immediately
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            // Fallback for non-FPM environments
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
            if (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }
        }
        
        // Now send email notification in background (after response sent)
        sendEnquiryNotificationAsync($pdo, $notificationData);
        
    } else {
        throw new Exception('Failed to save enquiry');
    }
    
} catch (Exception $e) {
    error_log("Enquiry submission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
}
