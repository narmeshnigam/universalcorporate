<?php
session_start();
require_once 'config/database.php';
require_once 'config/identity.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    die('Database connection failed. Please check your database configuration.');
}

$site = getSiteIdentity($pdo);

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: admin/index.php');
    exit;
}
if (isset($_SESSION['user_id'])) {
    header('Location: user/index.php');
    exit;
}

$error = '';
$mode = $_POST['mode'] ?? $_GET['mode'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        if ($mode === 'admin') {
            $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['admin_id'] = $user['id'];
                header('Location: admin/index.php');
                exit;
            } else {
                $error = 'Invalid email or password';
            }
        } else {
            $stmt = $pdo->prepare("SELECT id, password FROM site_users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                header('Location: user/index.php');
                exit;
            } else {
                $error = 'Invalid email or password';
            }
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($site['site_name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="index.php" class="auth-brand">
                    <img src="<?php echo htmlspecialchars($site['logo_path']); ?>" alt="<?php echo htmlspecialchars($site['site_name']); ?>">
                    <span><?php echo htmlspecialchars($site['site_name']); ?></span>
                </a>
                <div class="auth-toggle">
                    <button type="button" class="toggle-btn <?php echo $mode === 'user' ? 'active' : ''; ?>" data-mode="user">User</button>
                    <button type="button" class="toggle-btn <?php echo $mode === 'admin' ? 'active' : ''; ?>" data-mode="admin">Admin</button>
                </div>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="mode" id="modeInput" value="<?php echo htmlspecialchars($mode); ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>
            <div class="auth-footer" id="authFooter">
                <p>Don't have an account? <a href="user/register.php">Register</a></p>
            </div>
        </div>
    </div>
    <script>
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('modeInput').value = this.dataset.mode;
            const footer = document.getElementById('authFooter');
            if (this.dataset.mode === 'admin') {
                footer.innerHTML = '<p>Admin access only</p>';
            } else {
                footer.innerHTML = '<p>Don\'t have an account? <a href="user/register.php">Register</a></p>';
            }
        });
    });
    </script>
</body>
</html>
