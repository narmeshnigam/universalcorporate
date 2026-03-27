<?php
session_start();
require_once '../config/database.php';

// Get database connection
$pdo = getDatabaseConnection();

if (!$pdo) {
    die('Database connection failed. Please check your database configuration.');
}

// Check if admin already exists
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $adminCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

$success = '';
$error = '';

if ($adminCount > 0) {
    $adminExists = true;
} else {
    $adminExists = false;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($email && $password && $confirmPassword) {
            if ($password === $confirmPassword) {
                if (strlen($password) >= 6) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                        $stmt->execute([$email, $hashedPassword]);
                        $success = 'Admin created successfully! You can now login.';
                    } catch (PDOException $e) {
                        $error = 'Error creating admin: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Password must be at least 6 characters long';
                }
            } else {
                $error = 'Passwords do not match';
            }
        } else {
            $error = 'Please fill in all fields';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Create Admin Account</h1>
                <p>Set up your administrator account</p>
            </div>
            
            <?php if ($adminExists): ?>
                <div class="alert alert-info">
                    <h3>Admin Already Exists</h3>
                    <p>An administrator account has already been created. Please login to access the admin panel.</p>
                </div>
                <div class="auth-form">
                    <a href="login.php" class="btn btn-primary btn-block">Go to Login</a>
                </div>
            <?php else: ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <div class="auth-form">
                        <a href="login.php" class="btn btn-primary btn-block">Go to Login</a>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <form method="POST" action="" class="auth-form">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="admin@example.com" required autofocus>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Minimum 6 characters" required>
                            <small class="form-text">Password must be at least 6 characters long</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Create Admin Account</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</body>
</html>
