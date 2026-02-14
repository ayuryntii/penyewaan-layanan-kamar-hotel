<?php
// customer/profile.php - PROFILE PAGE
session_start();
require_once '../includes/config.php';
requireCustomer();

$user_id = $_SESSION['user_id'];
$page_title = 'My Profile';

// Get customer data
$customer_sql = "SELECT * FROM users WHERE id = ?";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("i", $user_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();
$customer_stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    
    // Check if email is already taken by another user
    $email_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
    $email_stmt = $conn->prepare($email_sql);
    $email_stmt->bind_param("si", $email, $user_id);
    $email_stmt->execute();
    $email_result = $email_stmt->get_result();
    if ($email_result->num_rows > 0) {
        $errors[] = 'Email address is already registered.';
    }
    $email_stmt->close();
    
    // Handle password change if provided
    if (!empty($current_password)) {
        if (password_verify($current_password, $customer['password'])) {
            if (!empty($new_password) && !empty($confirm_password)) {
                if ($new_password === $confirm_password) {
                    if (strlen($new_password) >= 6) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    } else {
                        $errors[] = 'New password must be at least 6 characters long.';
                    }
                } else {
                    $errors[] = 'New passwords do not match.';
                }
            } else {
                $errors[] = 'Please fill in both new password fields.';
            }
        } else {
            $errors[] = 'Current password is incorrect.';
        }
    }
    
    if (empty($errors)) {
        // Update profile
       if (isset($hashed_password)) {
    $update_sql = "UPDATE users 
                   SET full_name = ?, email = ?, phone = ?, password = ?
                   WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssssi", $full_name, $email, $phone, $hashed_password, $user_id);
} else {
    $update_sql = "UPDATE users 
                   SET full_name = ?, email = ?, phone = ?
                   WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssi", $full_name, $email, $phone, $user_id);
}

        
        if ($update_stmt->execute()) {
            // Update session data
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            // Refresh customer data
            $refresh_sql = "SELECT * FROM users WHERE id = ?";
            $refresh_stmt = $conn->prepare($refresh_sql);
            $refresh_stmt->bind_param("i", $user_id);
            $refresh_stmt->execute();
            $refresh_result = $refresh_stmt->get_result();
            $customer = $refresh_result->fetch_assoc();
            $refresh_stmt->close();
            
            $success_message = 'Profile updated successfully!';
        } else {
            $errors[] = 'Failed to update profile. Please try again.';
        }
        $update_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($hotel_name) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #0a192f;
            --blue: #4cc9f0;
            --blue-dark: #3a86ff;
            --light: #f8f9fa;
            --gray: #6c757d;
            --dark-bg: #0a192f;
            --card-bg: rgba(20, 30, 50, 0.85);
            --sidebar-width: 260px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark-bg);
            color: var(--light);
            overflow-x: hidden;
        }

        .customer-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* === Sidebar === */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--navy);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
            transition: all 0.3s ease;
            border-right: 1px solid rgba(76, 201, 240, 0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            background: var(--blue);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy);
            font-size: 18px;
        }

        .sidebar-title h3 {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .sidebar-title p {
            font-size: 0.85rem;
            color: #aaa;
        }

        .sidebar-nav {
            padding: 20px 0;
            overflow-y: auto;
            height: calc(100vh - 180px);
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #ccc;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
        }

        .nav-item i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }

        .nav-label {
            padding: 15px 25px 8px;
            color: #777;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-divider {
            height: 1px;
            background: rgba(255,255,255,0.05);
            margin: 15px 0;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--navy);
        }

        .user-info .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-info .user-role {
            font-size: 0.8rem;
            color: #aaa;
        }

        /* === Main Content === */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: rgba(10, 25, 47, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(76, 201, 240, 0.1);
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            display: none;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .content-area {
            padding: 30px;
        }

        /* === Cards === */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            margin-bottom: 25px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .card-body {
            padding: 25px;
        }

        /* === Buttons === */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--blue);
            color: var(--navy);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--blue);
            color: var(--blue);
        }

        .btn-primary:hover {
            background: #3abde0;
            transform: translateY(-1px);
        }

        .btn-secondary:hover {
            background: rgba(76, 201, 240, 0.1);
            transform: translateY(-1px);
        }

        /* === Last Login === */
        .last-login {
            color: #aaa;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* === Logout Button === */
        .logout-btn {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(231, 76, 60, 0.3);
            transform: translateY(-1px);
        }

        /* === Alert Messages === */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.2);
            border: 1px solid rgba(46, 204, 113, 0.3);
            color: #2ecc71;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }

        /* === Form Styles === */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #ccc;
            font-weight: 500;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: rgba(255,255,255,0.05);
            color: white;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(76, 201, 240, 0.15);
            background: rgba(255,255,255,0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* === Profile Header === */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 30px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 700;
            color: white;
        }

        .profile-info h2 {
            font-size: 1.8rem;
            color: white;
            margin-bottom: 5px;
        }

        .profile-info p {
            color: #aaa;
            font-size: 0.9rem;
        }

        /* === Stats === */
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-item {
            background: rgba(255,255,255,0.03);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--blue);
            display: block;
        }

        .stat-label {
            color: #aaa;
            font-size: 0.9rem;
        }

        /* === Password Toggle === */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 42px;
            background: none;
            border: none;
            color: #aaa;
            cursor: pointer;
        }

        .input-group {
            position: relative;
        }

        /* === Responsive === */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <div class="customer-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-hotel"></i>
                </div>
                <div class="sidebar-title">
                    <h3><?= htmlspecialchars($hotel_name) ?></h3>
                    <p>Customer Portal</p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">BOOKINGS</p>
                    <a href="bookings.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>My Bookings</span>
                    </a>
                    <a href="new-booking.php" class="nav-item">
                        <i class="fas fa-plus-circle"></i>
                        <span>New Booking</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">PROFILE</p>
                    <a href="profile.php" class="nav-item active">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="settings.php" class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">HOTEL</p>
                    <a href="rooms.php" class="nav-item">
                        <i class="fas fa-bed"></i>
                        <span>View Rooms</span>
                    </a>
                    <a href="services.php" class="nav-item">
                        <i class="fas fa-concierge-bell"></i>
                        <span>Services</span>
                    </a>
                </div>

                <div class="nav-divider"></div>

<div class="nav-group">
    <p class="nav-label">INVOICES</p>
    <a href="invoice.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'invoice.php') ? 'active' : '' ?>">
        <i class="fas fa-file-invoice-dollar"></i>
        <span>My Invoices</span>
    </a>
</div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($customer['full_name'], 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($customer['full_name']) ?></div>
                        <div class="user-role"><?= ucfirst($customer['role']) ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1><?= htmlspecialchars($page_title) ?></h1>
                </div>
                
                <div class="header-right">
                    <div class="last-login">
                        <i class="fas fa-clock"></i>
                        Last Login: <?= $customer['last_login'] ? date('d M Y H:i', strtotime($customer['last_login'])) : 'First login' ?>
                    </div>
                    
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <div class="content-area">
                <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                </div>
                <?php endif; ?>

                <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Profile Header -->
                <div class="card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?= strtoupper(substr($customer['full_name'], 0, 1)) ?>
                        </div>
                        <div class="profile-info">
                            <h2><?= htmlspecialchars($customer['full_name']) ?></h2>
                            <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($customer['email']) ?></p>
                            <p><i class="fas fa-user-tag"></i> <?= ucfirst($customer['role']) ?> Account</p>
                            <p><i class="fas fa-calendar-alt"></i> Member since <?= date('F Y', strtotime($customer['created_at'])) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Profile Stats -->
                <?php
                // Get user stats
                $stats_sql = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN booking_status = 'checked_out' THEN 1 ELSE 0 END) as completed,
                    SUM(final_price) as total_spent
                    FROM bookings 
                    WHERE user_id = ?";
                $stats_stmt = $conn->prepare($stats_sql);
                $stats_stmt->bind_param("i", $user_id);
                $stats_stmt->execute();
                $stats_result = $stats_stmt->get_result();
                $stats = $stats_result->fetch_assoc();
                $stats_stmt->close();
                ?>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?= $stats['total_bookings'] ?? 0 ?></span>
                        <span class="stat-label">Total Bookings</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $stats['confirmed'] ?? 0 ?></span>
                        <span class="stat-label">Confirmed</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $stats['completed'] ?? 0 ?></span>
                        <span class="stat-label">Completed</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= formatCurrency($stats['total_spent'] ?? 0) ?></span>
                        <span class="stat-label">Total Spent</span>
                    </div>
                </div>

                <!-- Profile Form -->
                <div class="card">
                    <div class="card-header">
                        <h3>Edit Profile Information</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="profileForm">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="full_name" class="form-control" 
                                           value="<?= htmlspecialchars($customer['full_name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($customer['email']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="address" class="form-control" 
                                           value="<?= htmlspecialchars($customer['address'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div style="margin: 40px 0 20px 0; padding: 20px 0; border-top: 1px solid rgba(255,255,255,0.1); border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <h4 style="color: var(--blue); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-lock"></i> Change Password (Optional)
                                </h4>
                                
                                <div class="form-row">
                                    <div class="form-group input-group">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" name="current_password" id="current_password" class="form-control">
                                        <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-group input-group">
                                        <label class="form-label">New Password</label>
                                        <input type="password" name="new_password" id="new_password" class="form-control">
                                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-group input-group">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control">
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                
                                <div style="color: #aaa; font-size: 0.9rem; margin-top: 10px;">
                                    <i class="fas fa-info-circle"></i> Leave password fields empty if you don't want to change your password.
                                </div>
                            </div>
                            
                            <div style="text-align: center; margin-top: 30px;">
                                <button type="submit" class="btn btn-primary" style="padding: 15px 40px; font-size: 16px;">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary" style="margin-left: 15px;">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>Account Information</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                            <div>
                                <h4 style="color: var(--blue); margin-bottom: 15px;">Account Details</h4>
                                <div style="color: #ccc;">
                                    <p><strong>Username:</strong> <?= htmlspecialchars($customer['username']) ?></p>
                                    <p><strong>Account Type:</strong> <?= ucfirst($customer['role']) ?></p>
                                    <p><strong>Status:</strong> 
                                        <span style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 3px 10px; border-radius: 20px; font-size: 0.9rem;">
                                            <?= ucfirst($customer['status'] ?? 'active') ?>
                                        </span>
                                    </p>
                                    <p><strong>Last Updated:</strong> <?= date('d M Y H:i', strtotime($customer['updated_at'] ?? $customer['created_at'])) ?></p>
                                </div>
                            </div>
                            
                            <div>
                                <h4 style="color: var(--blue); margin-bottom: 15px;">Preferences</h4>
                                <div style="color: #ccc;">
                                    <p><strong>Email Notifications:</strong> Enabled</p>
                                    <p><strong>SMS Notifications:</strong> Enabled</p>
                                    <p><strong>Newsletter:</strong> Subscribed</p>
                                    <p><strong>Language:</strong> English</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Password toggle function
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = event.currentTarget.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (currentPassword || newPassword || confirmPassword) {
                if (!currentPassword) {
                    e.preventDefault();
                    alert('Please enter your current password to change it.');
                    return false;
                }
                
                if (!newPassword) {
                    e.preventDefault();
                    alert('Please enter a new password.');
                    return false;
                }
                
                if (!confirmPassword) {
                    e.preventDefault();
                    alert('Please confirm your new password.');
                    return false;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match.');
                    return false;
                }
                
                if (newPassword.length < 6) {
                    e.preventDefault();
                    alert('New password must be at least 6 characters long.');
                    return false;
                }
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
            
            return true;
        });

        // Auto-save warning
        let formChanged = false;
        const formInputs = document.querySelectorAll('#profileForm input, #profileForm textarea');
        
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                formChanged = true;
            });
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });

        // Save form data to localStorage periodically
        setInterval(function() {
            if (formChanged) {
                const formData = new FormData(document.getElementById('profileForm'));
                const data = {};
                formData.forEach((value, key) => {
                    data[key] = value;
                });
                localStorage.setItem('profileFormDraft', JSON.stringify(data));
            }
        }, 5000);

        // Load draft from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const draft = localStorage.getItem('profileFormDraft');
            if (draft) {
                const data = JSON.parse(draft);
                Object.keys(data).forEach(key => {
                    const input = document.querySelector(`[name="${key}"]`);
                    if (input) {
                        input.value = data[key];
                    }
                });
                localStorage.removeItem('profileFormDraft');
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>