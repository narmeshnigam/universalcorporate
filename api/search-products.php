<?php
/**
 * Product Search API
 * Returns products matching search query
 */
header('Content-Type: application/json');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

try {
    require_once '../config/database.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Config load failed: ' . $e->getMessage()]);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Please login']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection error: ' . $e->getMessage()]);
    exit;
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$fetchAll = isset($_GET['all']) && $_GET['all'] == '1';

// For admin fetching all products
if ($fetchAll) {
    // Also allow admin access
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, sku, name, unit, unit_price, tax_rate, image_path 
            FROM products 
            WHERE is_active = 1 
            AND is_user_added = 0
            ORDER BY name ASC
            LIMIT 200
        ");
        $stmt->execute();
        $products = $stmt->fetchAll();
        echo json_encode($products);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Query failed']);
    }
    exit;
}

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $searchTerm = '%' . $query . '%';
    $stmt = $pdo->prepare("
        SELECT id, sku, name, unit, unit_price, tax_rate, image_path 
        FROM products 
        WHERE is_active = 1 
        AND is_user_added = 0
        AND (name LIKE ? OR sku LIKE ?)
        ORDER BY name ASC
        LIMIT 20
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    $products = $stmt->fetchAll();
    
    echo json_encode($products);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
}
