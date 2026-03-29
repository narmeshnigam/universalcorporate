<?php
/**
 * Debug script for OTP system - DELETE THIS FILE AFTER DEBUGGING
 */
session_start();
require_once 'config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    die('Database connection failed');
}

echo "<h2>OTP System Debug</h2>";

// Check if tables exist
echo "<h3>1. Checking Tables</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'pending_registrations'");
    $exists = $stmt->fetch();
    echo "pending_registrations table: " . ($exists ? "✓ EXISTS" : "✗ MISSING") . "<br>";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'otp_verifications'");
    $exists = $stmt->fetch();
    echo "otp_verifications table: " . ($exists ? "✓ EXISTS" : "✗ MISSING") . "<br>";
} catch (Exception $e) {
    echo "Error checking tables: " . $e->getMessage() . "<br>";
}

// Check pending registrations
echo "<h3>2. Pending Registrations</h3>";
try {
    $stmt = $pdo->query("SELECT *, expires_at > NOW() as is_valid, NOW() as current_time FROM pending_registrations ORDER BY created_at DESC LIMIT 10");
    $rows = $stmt->fetchAll();
    if (empty($rows)) {
        echo "No pending registrations found.<br>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Email</th><th>Created</th><th>Expires</th><th>Current Time</th><th>Valid?</th></tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "<td>" . $row['expires_at'] . "</td>";
            echo "<td>" . $row['current_time'] . "</td>";
            echo "<td>" . ($row['is_valid'] ? '✓ Yes' : '✗ No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Check OTP verifications
echo "<h3>3. OTP Verifications</h3>";
try {
    $stmt = $pdo->query("SELECT *, expires_at > NOW() as is_valid FROM otp_verifications ORDER BY created_at DESC LIMIT 10");
    $rows = $stmt->fetchAll();
    if (empty($rows)) {
        echo "No OTP verifications found.<br>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Email</th><th>OTP</th><th>Type</th><th>Expires</th><th>Used?</th><th>Attempts</th><th>Valid?</th></tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . $row['otp_code'] . "</td>";
            echo "<td>" . $row['otp_type'] . "</td>";
            echo "<td>" . $row['expires_at'] . "</td>";
            echo "<td>" . ($row['is_used'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . $row['attempts'] . "</td>";
            echo "<td>" . ($row['is_valid'] ? '✓ Yes' : '✗ No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Check session
echo "<h3>4. Session Data</h3>";
echo "<pre>";
echo "register_step: " . ($_SESSION['register_step'] ?? 'not set') . "\n";
echo "register_email: " . ($_SESSION['register_email'] ?? 'not set') . "\n";
echo "</pre>";

// Server time
echo "<h3>5. Server Time</h3>";
echo "PHP time: " . date('Y-m-d H:i:s') . "<br>";
try {
    $stmt = $pdo->query("SELECT NOW() as db_time");
    $row = $stmt->fetch();
    echo "MySQL time: " . $row['db_time'] . "<br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "<br><br><strong>Delete this file after debugging!</strong>";
