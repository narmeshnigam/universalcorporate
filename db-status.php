<?php
/**
 * Database Connection Status Page
 * Tests database connectivity and displays detailed diagnostics
 */

require_once 'config/database.php';
require_once 'config/identity.php';
$pdo = getDatabaseConnection();
$site = getSiteIdentity($pdo);

$pageTitle = 'Database Connection Status';
include 'includes/header.php';

// Test results array
$tests = [];
$overallStatus = true;

// Test 1: Check if .env file exists
$tests['env_file'] = [
    'name' => 'Environment File (.env)',
    'status' => file_exists('.env'),
    'message' => file_exists('.env') ? 'File exists' : 'File not found - copy .env.example to .env',
];
$overallStatus = $overallStatus && $tests['env_file']['status'];

// Test 2: Check if config file exists
$tests['config_file'] = [
    'name' => 'Database Config File',
    'status' => file_exists('config/database.php'),
    'message' => file_exists('config/database.php') ? 'File exists' : 'Configuration file missing',
];
$overallStatus = $overallStatus && $tests['config_file']['status'];

// Test 3: Check environment variables
$requiredVars = ['DB_HOST', 'DB_NAME', 'DB_USER'];
$missingVars = [];
foreach ($requiredVars as $var) {
    if (empty(env($var))) {
        $missingVars[] = $var;
    }
}
$tests['env_vars'] = [
    'name' => 'Environment Variables',
    'status' => empty($missingVars),
    'message' => empty($missingVars) ? 'All required variables set' : 'Missing: ' . implode(', ', $missingVars),
];
$overallStatus = $overallStatus && $tests['env_vars']['status'];

// Test 4: Check PDO extension
$tests['pdo_extension'] = [
    'name' => 'PDO Extension',
    'status' => extension_loaded('pdo'),
    'message' => extension_loaded('pdo') ? 'PDO extension loaded' : 'PDO extension not available',
];
$overallStatus = $overallStatus && $tests['pdo_extension']['status'];

// Test 5: Check MySQL PDO driver
$tests['pdo_mysql'] = [
    'name' => 'PDO MySQL Driver',
    'status' => extension_loaded('pdo_mysql'),
    'message' => extension_loaded('pdo_mysql') ? 'MySQL driver loaded' : 'MySQL driver not available',
];
$overallStatus = $overallStatus && $tests['pdo_mysql']['status'];

// Test 6: Attempt database connection
$connectionError = null;
$connectionDetails = [];
try {
    $pdo = getDatabaseConnection();
    if ($pdo) {
        $tests['connection'] = [
            'name' => 'Database Connection',
            'status' => true,
            'message' => 'Successfully connected to database',
        ];
        
        // Get additional connection info
        $connectionDetails['server_version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $connectionDetails['connection_status'] = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        
        // Test query
        $stmt = $pdo->query('SELECT DATABASE() as db_name, NOW() as server_time');
        $result = $stmt->fetch();
        $connectionDetails['current_database'] = $result['db_name'];
        $connectionDetails['server_time'] = $result['server_time'];
    } else {
        $tests['connection'] = [
            'name' => 'Database Connection',
            'status' => false,
            'message' => 'Failed to connect - check credentials and database exists',
        ];
        $overallStatus = false;
    }
} catch (PDOException $e) {
    $tests['connection'] = [
        'name' => 'Database Connection',
        'status' => false,
        'message' => 'Connection failed: ' . $e->getMessage(),
    ];
    $connectionError = $e->getMessage();
    $overallStatus = false;
}

?>

<section class="section">
    <h2>Database Connection Status</h2>
    
    <div class="status-overview" style="padding: 20px; margin: 20px 0; border-radius: 8px; background: <?php echo $overallStatus ? '#d4edda' : '#f8d7da'; ?>; border: 1px solid <?php echo $overallStatus ? '#c3e6cb' : '#f5c6cb'; ?>;">
        <h3 style="margin: 0 0 10px 0; color: <?php echo $overallStatus ? '#155724' : '#721c24'; ?>;">
            <?php echo $overallStatus ? '✓ All Systems Operational' : '✗ Issues Detected'; ?>
        </h3>
        <p style="margin: 0; color: <?php echo $overallStatus ? '#155724' : '#721c24'; ?>;">
            <?php echo $overallStatus ? 'Database connection is working properly.' : 'Please review the diagnostics below.'; ?>
        </p>
    </div>

    <h3>Diagnostic Tests</h3>
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <thead>
            <tr style="background: #f8f9fa;">
                <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Test</th>
                <th style="padding: 12px; text-align: center; border: 1px solid #dee2e6; width: 100px;">Status</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Details</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tests as $test): ?>
            <tr>
                <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($test['name']); ?></td>
                <td style="padding: 12px; text-align: center; border: 1px solid #dee2e6;">
                    <span style="color: <?php echo $test['status'] ? '#28a745' : '#dc3545'; ?>; font-weight: bold; font-size: 18px;">
                        <?php echo $test['status'] ? '✓' : '✗'; ?>
                    </span>
                </td>
                <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($test['message']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($connectionDetails)): ?>
    <h3>Connection Details</h3>
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tbody>
            <?php foreach ($connectionDetails as $key => $value): ?>
            <tr>
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold; width: 200px;">
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?>
                </td>
                <td style="padding: 12px; border: 1px solid #dee2e6;">
                    <?php echo htmlspecialchars($value); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <h3>Current Configuration</h3>
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tbody>
            <tr>
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold; width: 200px;">Host</td>
                <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars(env('DB_HOST', 'Not set')); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Port</td>
                <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars(env('DB_PORT', 'Not set')); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Database</td>
                <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars(env('DB_NAME', 'Not set')); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Username</td>
                <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars(env('DB_USER', 'Not set')); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Password</td>
                <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo empty(env('DB_PASS')) ? 'Empty' : '••••••••'; ?></td>
            </tr>
        </tbody>
    </table>

    <?php if (!$overallStatus): ?>
    <div style="padding: 20px; margin: 20px 0; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
        <h3 style="margin-top: 0; color: #856404;">Troubleshooting Steps</h3>
        <ol style="color: #856404;">
            <li>Ensure MySQL/MariaDB server is running</li>
            <li>Verify database credentials in .env file</li>
            <li>Check if the database exists: <code>CREATE DATABASE universal_corporate;</code></li>
            <li>Verify user has proper permissions: <code>GRANT ALL ON universal_corporate.* TO 'root'@'localhost';</code></li>
            <li>Check PHP extensions: PDO and PDO_MySQL must be enabled</li>
            <li>Review error logs for detailed information</li>
        </ol>
    </div>
    <?php endif; ?>

    <div style="margin-top: 30px;">
        <a href="index.php" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">
            ← Back to Home
        </a>
        <a href="db-status.php" style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;">
            🔄 Refresh Status
        </a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
