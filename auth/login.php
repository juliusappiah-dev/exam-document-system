<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Define base path for consistent redirects
define('BASE_PATH', '/ExamQr/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        
        // Update last login
        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
            ->execute([$user['id']]);
        
        // Fixed redirect paths with BASE_PATH
        $redirect = $user['role'] === 'admin' 
            ? BASE_PATH . 'admin/dashboard.php' 
            : BASE_PATH . 'verify/';
        
        header("Location: $redirect");
        exit();
    } else {
        header("Location: " . BASE_PATH . "auth/login.php?error=invalid_credentials");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Access - Exam Verification System</title>
    <!-- Modern CSS frameworks -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a0ca3;
            --danger-color: #f72585;
            --success-color: #4cc9f0;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            color: var(--dark-color);
        }
        
        .login-container {
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-card {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: none;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 1.75rem;
            text-align: center;
            position: relative;
        }
        
        .login-header::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100%;
            height: 20px;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 1440 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath fill='white' d='M0,70 C150,100 350,0 500,20 C650,40 750,100 900,80 C1050,60 1200,0 1350,20 C1425,30 1440,50 1440,50 L1440,100 L0,100 Z'/%3E%3C/svg%3E") center/cover;
        }
        
        .login-body {
            padding: 2rem;
            background: white;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border-radius: 8px 0 0 8px !important;
        }
        
        .btn-login {
            background-color: var(--primary-color);
            border: none;
            padding: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 5;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        .alert-danger {
            border-left: 4px solid var(--danger-color);
            border-radius: 8px;
        }
        
        .security-features {
            font-size: 0.85rem;
            color: #6c757d;
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .security-features i {
            color: var(--success-color);
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <h3 class="fw-bold mb-2">
                        <i class="bi bi-shield-lock-fill me-2"></i>Exam Verification System
                    </h3>
                    <p class="mb-0 opacity-75">Secure document verification portal</p>
                </div>
                
                <div class="login-body">
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div>Invalid credentials. Please try again.</div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="loginForm">
                        <div class="mb-4">
                            <label for="username" class="form-label fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person-fill"></i>
                                </span>
                                <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-4 position-relative">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock-fill"></i>
                                </span>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                                <i class="bi bi-eye-fill password-toggle" id="togglePassword"></i>
                            </div>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-login btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                            </button>
                        </div>
                        
                        <div class="text-center mb-3">
                            <a href="#forgot-password" class="text-decoration-none" style="color: var(--primary-color);">Forgot password?</a>
                        </div>
                        
                        <div class="security-features">
                            <p class="mb-1"><i class="bi bi-check-circle-fill"></i>256-bit SSL encryption</p>
                            <p class="mb-0"><i class="bi bi-check-circle-fill"></i>Multi-factor authentication ready</p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modern JavaScript libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('bi-eye-fill');
            this.classList.toggle('bi-eye-slash-fill');
        });
        
        // Form submission animation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Authenticating...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>