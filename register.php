<?php
require_once 'includes/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';
$form_data = ['full_name' => '', 'username' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['username'] = trim($_POST['username'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($form_data['full_name']) || empty($form_data['username']) || empty($form_data['email']) || empty($password)) {
        $error = 'Semua field bertanda * wajib diisi.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password tidak cocok.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $form_data['username'])) {
        $error = 'Username hanya boleh berisi huruf, angka, dan underscore (_).';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $form_data['username'], $form_data['email']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Username atau email sudah terdaftar.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO users (username, password, email, full_name, phone, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'customer', 'active', NOW())");
            $insert->bind_param("sssss", $form_data['username'], $hashed, $form_data['email'], $form_data['full_name'], $form_data['phone']);
            
            if ($insert->execute()) {
                $success = 'Pendaftaran berhasil! Silakan login untuk melanjutkan.';
                $form_data = ['full_name' => '', 'username' => '', 'email' => '', 'phone' => ''];
            } else {
                $error = 'Gagal membuat akun. Silakan coba lagi.';
            }
            $insert->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar – <?= htmlspecialchars($hotel_name) ?></title>
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
            background: url('https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') center/cover fixed;
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

        .register-container {
            width: 100%;
            max-width: 450px;
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

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-header h1 {
            color: white;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .register-header p {
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
            margin-bottom: 20px;
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
            padding: 14px 16px;
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

        .input-with-icon {
            position: relative;
        }

        .input-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            cursor: pointer;
        }

        .password-strength, .password-match {
            margin-top: 6px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }
        .match-good { color: #27ae60; }
        .match-bad { color: #e74c3c; }

        .btn-register {
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
            margin-top: 10px;
        }

        .btn-register:hover {
            background: var(--blue-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(76, 201, 240, 0.3);
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .login-link a {
            color: var(--blue);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        small { color: #aaa; font-size: 12px; }

        @media (max-width: 480px) {
            .register-container { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Buat Akun Baru</h1>
            <p><?= htmlspecialchars($hotel_name) ?> – Sistem Manajemen Hotel</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <div class="form-group">
                <label class="form-label">Nama Lengkap *</label>
                <input type="text" name="full_name" class="form-control" 
                       value="<?= htmlspecialchars($form_data['full_name']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Username *</label>
                <input type="text" name="username" class="form-control" 
                       value="<?= htmlspecialchars($form_data['username']) ?>" required>
                <small>Contoh: john_doe123</small>
            </div>

            <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" 
                       value="<?= htmlspecialchars($form_data['email']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Nomor Telepon</label>
                <input type="tel" name="phone" class="form-control" 
                       value="<?= htmlspecialchars($form_data['phone']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Password *</label>
                <div class="input-with-icon">
                    <input type="password" name="password" id="password" class="form-control" required>
                    <button type="button" class="input-icon" id="togglePassword1">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div id="passwordStrength" class="password-strength"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Konfirmasi Password *</label>
                <div class="input-with-icon">
                    <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required>
                    <button type="button" class="input-icon" id="togglePassword2">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div id="passwordMatch" class="password-match"></div>
            </div>

            <button type="submit" class="btn-register">
                <i class="fas fa-user-plus"></i> Daftar Sekarang
            </button>
        </form>

        <div class="login-link">
            Sudah punya akun? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Masuk di sini</a>
        </div>
    </div>

    <script>
        function setupToggle(toggleId, inputId) {
            const toggle = document.getElementById(toggleId);
            const input = document.getElementById(inputId);
            toggle.addEventListener('click', () => {
                const icon = toggle.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
        }

        setupToggle('togglePassword1', 'password');
        setupToggle('togglePassword2', 'confirmPassword');

        const passInput = document.getElementById('password');
        const strengthDiv = document.getElementById('passwordStrength');
        passInput.addEventListener('input', () => {
            const p = passInput.value;
            let msg = '', cls = '';
            if (p.length === 0) {
                msg = ''; cls = '';
            } else if (p.length < 6) {
                msg = 'Lemah'; cls = 'strength-weak';
            } else if (p.length < 10) {
                msg = 'Cukup'; cls = 'strength-medium';
            } else {
                msg = 'Kuat'; cls = 'strength-strong';
            }
            strengthDiv.innerHTML = msg ? `<i class="fas fa-shield-alt"></i> ${msg}` : '';
            strengthDiv.className = `password-strength ${cls}`;
        });

        const confirmInput = document.getElementById('confirmPassword');
        const matchDiv = document.getElementById('passwordMatch');
        confirmInput.addEventListener('input', () => {
            const p = passInput.value;
            const cp = confirmInput.value;
            if (cp === '') {
                matchDiv.innerHTML = '';
            } else if (p === cp) {
                matchDiv.innerHTML = '<i class="fas fa-check-circle"></i> Password cocok';
                matchDiv.className = 'password-match match-good';
            } else {
                matchDiv.innerHTML = '<i class="fas fa-times-circle"></i> Tidak cocok';
                matchDiv.className = 'password-match match-bad';
            }
        });

        document.getElementById('registerForm').addEventListener('submit', function() {
            const btn = this.querySelector('.btn-register');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            btn.disabled = true;
        });
    </script>
</body>
</html>