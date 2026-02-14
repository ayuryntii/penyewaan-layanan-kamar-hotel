<?php
// login.php - FIXED VERSION FOR RECEPTIONIST
session_start();
require_once 'includes/config.php';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'customer';
    
    if ($role == 'admin') {
        header('Location: admin/index.php');
    } elseif ($role == 'receptionist') {
        header('Location: receptionist/dashboard.php');
    } else {
        header('Location: customer/dashboard.php');
    }
    exit();
}

$error = '';
$success = '';

// Reset demo password
if (isset($_GET['reset']) && $_GET['reset'] == '1') {
    $hashed = password_hash('123', PASSWORD_DEFAULT);

    $stmtReset = $conn->prepare("UPDATE users SET password = ? WHERE username IN ('admin', 'receptionist', 'customer')");
    if ($stmtReset) {
        $stmtReset->bind_param("s", $hashed);
        $stmtReset->execute();
        $stmtReset->close();
        $success = 'Password semua akun demo telah direset ke: <strong>123</strong>';
    } else {
        $error = "Gagal reset password demo.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT id, username, email, password, role, full_name, status 
            FROM users 
            WHERE (username = ? OR email = ?) AND status = 'active'
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id']    = (int)$user['id'];
                $_SESSION['username']   = $user['username'];
                $_SESSION['role']       = $user['role'];
                $_SESSION['full_name']  = $user['full_name'] ?? $user['username'];
                $_SESSION['email']      = $user['email'] ?? '';

                // Update last login
                $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                if ($update) {
                    $update->bind_param("i", $user['id']);
                    $update->execute();
                    $update->close();
                }

                // Redirect berdasarkan role
                if ($user['role'] == 'admin') {
                    header("Location: admin/index.php");
                } elseif ($user['role'] == 'receptionist') {
                    header("Location: receptionist/dashboard.php");
                } else {
                    header("Location: customer/dashboard.php");
                }
                exit();

            } else {
                $error = 'Password salah.';
            }
        } else {
            $error = 'Username atau email tidak ditemukan.';
        }

        $stmt->close();
    } else {
        $error = 'Terjadi kesalahan sistem.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – <?= htmlspecialchars($hotel_name) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #0a192f;
            --blue: #4cc9f0;
            --blue-hover: #3abde0;
            --light: #f8f9fa;
            --gray: #6c757d;
            --dark-bg: rgba(10, 25, 47, 0.85);
            --card-bg: rgba(20, 30, 50, 0.8);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: url('https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') center/cover fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--light);
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--dark-bg);
            z-index: -1;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            background: var(--card-bg);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            animation: fadeInUp 0.6s ease;
            border: 1px solid rgba(76, 201, 240, 0.2);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .hotel-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--blue), #3a86ff);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: white;
        }

        .login-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--blue), #3a86ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-header p {
            color: #ccc;
            font-size: 14px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.2);
            border-left: 4px solid #e74c3c;
            color: #ffcccc;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.2);
            border-left: 4px solid #2ecc71;
            color: #ccffcc;
        }

        .form-group {
            margin-bottom: 22px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #ddd;
            font-weight: 600;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 50px;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            font-size: 16px;
            font-family: inherit;
            background: rgba(255,255,255,0.05);
            color: white;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(76, 201, 240, 0.15);
            background: rgba(255,255,255,0.1);
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 42px;
            color: #aaa;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 42px;
            background: none;
            border: none;
            color: #aaa;
            cursor: pointer;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .remember-group {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ccc;
        }

        .forgot-link {
            color: var(--blue);
            text-decoration: none;
            font-weight: 600;
        }

        .forgot-link:hover { text-decoration: underline; }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--blue);
            color: var(--navy);
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            background: var(--blue-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(76, 201, 240, 0.3);
        }

        .demo-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .demo-section h4 {
            font-size: 15px;
            color: #ddd;
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .demo-list {
            list-style: none;
        }

        .demo-list li {
            padding: 10px 15px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .demo-list li:hover {
            background: rgba(255,255,255,0.1);
        }

        .demo-cred {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .role-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-admin {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        .badge-receptionist {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
        }

        .badge-customer {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .register-link a {
            color: var(--blue);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="hotel-logo">
                <i class="fas fa-hotel"></i>
            </div>
            <h1><?= htmlspecialchars($hotel_name) ?></h1>
            <p>Hotel Management System</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= $success ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label class="form-label">Username atau Email</label>
                <i class="fas fa-user input-icon"></i>
                <input type="text" name="username" class="form-control" placeholder="Masukkan username atau email" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <i class="fas fa-lock input-icon"></i>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                <button type="button" class="password-toggle" id="togglePassword">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <div class="form-options">
                <label class="remember-group">
                    <input type="checkbox" name="remember"> Ingat saya
                </label>
                <a href="#" class="forgot-link">Lupa password?</a>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Masuk Sekarang
            </button>
        </form>

        <div class="demo-section">
            <h4><i class="fas fa-users"></i> Akun Demo</h4>
            <ul class="demo-list">
                <li onclick="fill('admin', '123')">
                    <div class="demo-cred">
                        <span>
                            <i class="fas fa-crown"></i> Administrator
                        </span>
                        <span class="role-badge badge-admin">Admin</span>
                    </div>
                </li>
                <li onclick="fill('receptionist', '123')">
                    <div class="demo-cred">
                        <span>
                            <i class="fas fa-concierge-bell"></i> Resepsionis
                        </span>
                        <span class="role-badge badge-receptionist">Resepsionis</span>
                    </div>
                </li>
                <li onclick="fill('customer', '123')">
                    <div class="demo-cred">
                        <span>
                            <i class="fas fa-user"></i> Tamu
                        </span>
                        <span class="role-badge badge-customer">Customer</span>
                    </div>
                </li>
            </ul>
            <p style="font-size:12px; color:#aaa; text-align:center; margin-top:10px;">Password: <strong>123</strong></p>
        </div>

        <div class="register-link">
            Belum punya akun? <a href="register.php"><i class="fas fa-user-plus"></i> Daftar Sekarang</a>
        </div>

        <div style="text-align:center; margin-top:15px; font-size:11px; color:#777;">
            <small>Reset demo: <a href="?reset=1" style="color:var(--blue);">Klik di sini</a></small>
        </div>
    </div>

    <script>
        const toggle = document.getElementById('togglePassword');
        const pass = document.getElementById('password');
        toggle.addEventListener('click', () => {
            const icon = toggle.querySelector('i');
            if (pass.type === 'password') {
                pass.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pass.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        function fill(user, pwd) {
            document.querySelector('input[name="username"]').value = user;
            pass.value = pwd;
            document.getElementById('loginForm').submit();
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = this.querySelector('.btn-login');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            btn.disabled = true;
        });

        // Auto focus on username field
        document.querySelector('input[name="username"]').focus();
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>