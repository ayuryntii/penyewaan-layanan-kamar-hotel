<?php
// customer/settings.php - SETTINGS PAGE
session_start();
require_once '../includes/config.php';
requireCustomer();

$user_id = $_SESSION['user_id'];
$page_title = 'Settings';

// ===================== GET CUSTOMER DATA =====================
$customer_sql = "SELECT * FROM users WHERE id = ?";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("i", $user_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();
$customer_stmt->close();

if (!$customer) {
    header("Location: dashboard.php");
    exit();
}

// ===================== LOAD NOTIFICATION SETTINGS =====================
$settings_sql = "SELECT email_notifications, sms_notifications, newsletter
                 FROM user_settings
                 WHERE user_id = ?
                 LIMIT 1";
$settings_stmt = $conn->prepare($settings_sql);
$settings_stmt->bind_param("i", $user_id);
$settings_stmt->execute();
$settings_result = $settings_stmt->get_result();
$settings = $settings_result->fetch_assoc();
$settings_stmt->close();

if (!$settings) {
    $insert_settings_sql = "INSERT INTO user_settings (user_id, email_notifications, sms_notifications, newsletter)
                            VALUES (?, 0, 0, 0)";
    $insert_stmt = $conn->prepare($insert_settings_sql);
    $insert_stmt->bind_param("i", $user_id);
    $insert_stmt->execute();
    $insert_stmt->close();

    $settings = [
        'email_notifications' => 0,
        'sms_notifications'   => 0,
        'newsletter'          => 0
    ];
}

// ===================== LOAD PREFERENCES =====================
$pref_sql = "SELECT language, timezone, currency
             FROM user_preferences
             WHERE user_id = ?
             LIMIT 1";
$pref_stmt = $conn->prepare($pref_sql);
$pref_stmt->bind_param("i", $user_id);
$pref_stmt->execute();
$pref_result = $pref_stmt->get_result();
$pref = $pref_result->fetch_assoc();
$pref_stmt->close();

if (!$pref) {
    $insert_pref_sql = "INSERT INTO user_preferences (user_id, language, timezone, currency)
                        VALUES (?, 'en', 'Asia/Jakarta', 'IDR')";
    $insert_pref_stmt = $conn->prepare($insert_pref_sql);
    $insert_pref_stmt->bind_param("i", $user_id);
    $insert_pref_stmt->execute();
    $insert_pref_stmt->close();

    $pref = [
        'language' => 'en',
        'timezone' => 'Asia/Jakarta',
        'currency' => 'IDR'
    ];
}

// ===================== HANDLE SETTINGS UPDATE =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ===== Notification settings update =====
    if (isset($_POST['update_notifications'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications   = isset($_POST['sms_notifications']) ? 1 : 0;
        $newsletter          = isset($_POST['newsletter']) ? 1 : 0;

        $update_sql = "UPDATE user_settings SET
                        email_notifications = ?,
                        sms_notifications = ?,
                        newsletter = ?
                      WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("iiii", $email_notifications, $sms_notifications, $newsletter, $user_id);

        if ($update_stmt->execute()) {
            $notification_success = 'Notification settings updated successfully!';
            $settings['email_notifications'] = $email_notifications;
            $settings['sms_notifications'] = $sms_notifications;
            $settings['newsletter'] = $newsletter;
        } else {
            $notification_error = 'Failed to update settings. Please try again.';
        }
        $update_stmt->close();
    }

    // ===== Preferences update =====
    if (isset($_POST['update_preferences'])) {
        $language = $_POST['language'] ?? 'en';
        $timezone = $_POST['timezone'] ?? 'Asia/Jakarta';
        $currency = $_POST['currency'] ?? 'IDR';

        $update_sql = "UPDATE user_preferences SET
                        language = ?,
                        timezone = ?,
                        currency = ?
                      WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssi", $language, $timezone, $currency, $user_id);

        if ($update_stmt->execute()) {
            $preference_success = 'Preferences updated successfully!';
            $pref['language'] = $language;
            $pref['timezone'] = $timezone;
            $pref['currency'] = $currency;
        } else {
            $preference_error = 'Failed to update preferences. Please try again.';
        }
        $update_stmt->close();
    }

    // ===== Deactivate account =====
    if (isset($_POST['deactivate_account'])) {
        $confirm_text = $_POST['confirm_text'] ?? '';

        if ($confirm_text === 'DELETE MY ACCOUNT') {
            // disable user (NO updated_at column used)
            $deactivate_sql = "UPDATE users SET status = 'inactive' WHERE id = ?";
            $deactivate_stmt = $conn->prepare($deactivate_sql);
            $deactivate_stmt->bind_param("i", $user_id);

            if ($deactivate_stmt->execute()) {
                session_destroy();
                header('Location: ../login.php?message=account_deactivated');
                exit();
            }
            $deactivate_stmt->close();
        } else {
            $deactivate_error = 'Please type "DELETE MY ACCOUNT" exactly to confirm.';
        }
    }

    // refresh customer data
    $refresh_sql = "SELECT * FROM users WHERE id = ?";
    $refresh_stmt = $conn->prepare($refresh_sql);
    $refresh_stmt->bind_param("i", $user_id);
    $refresh_stmt->execute();
    $refresh_result = $refresh_stmt->get_result();
    $customer = $refresh_result->fetch_assoc();
    $refresh_stmt->close();
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

        .btn-danger {
            background: #ef233c;
            color: white;
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

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z' fill='%23ccc'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px;
        }

        /* === Checkbox & Radio === */
        .checkbox-group,
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .checkbox-item,
        .radio-item {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .checkbox-item input[type="checkbox"],
        .radio-item input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-label,
        .radio-label {
            color: #ccc;
            cursor: pointer;
        }

        /* === Settings Sections === */
        .settings-section {
            margin-bottom: 40px;
        }

        .section-title {
            color: var(--blue);
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* === Danger Zone === */
        .danger-zone {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
            border-radius: 12px;
            padding: 25px;
            margin-top: 30px;
        }

        .danger-title {
            color: #e74c3c;
            font-size: 1.2rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
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
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="settings.php" class="nav-item active">
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
                <?php if (isset($notification_success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $notification_success ?>
                </div>
                <?php endif; ?>

                <?php if (isset($notification_error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $notification_error ?>
                </div>
                <?php endif; ?>

                <?php if (isset($preference_success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $preference_success ?>
                </div>
                <?php endif; ?>

                <?php if (isset($preference_error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $preference_error ?>
                </div>
                <?php endif; ?>

                <?php if (isset($deactivate_error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $deactivate_error ?>
                </div>
                <?php endif; ?>

                <!-- Notification Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>Notification Settings</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_notifications" value="1">

                            <div class="section-title">
                                <i class="fas fa-bell"></i> Email Notifications
                            </div>

                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="email_notifications" value="1"
                                           <?= ($settings['email_notifications'] ?? 0) ? 'checked' : '' ?>>
                                    <span class="checkbox-label">Receive email notifications</span>
                                </label>
                                <small style="color: #777; margin-left: 28px;">
                                    Get updates about your bookings, special offers, and hotel news via email.
                                </small>
                            </div>

                            <div class="section-title" style="margin-top: 30px;">
                                <i class="fas fa-sms"></i> SMS Notifications
                            </div>

                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="sms_notifications" value="1"
                                            <?= ($settings['sms_notifications'] ?? 0) ? 'checked' : '' ?>>
                                    <span class="checkbox-label">Receive SMS notifications</span>
                                </label>
                                <small style="color: #777; margin-left: 28px;">
                                    Get important updates and reminders via SMS on your registered phone number.
                                </small>
                            </div>

                            <div class="section-title" style="margin-top: 30px;">
                                <i class="fas fa-newspaper"></i> Newsletter
                            </div>

                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="newsletter" value="1"
                                            <?= ($settings['newsletter'] ?? 0) ? 'checked' : '' ?>>
                                    <span class="checkbox-label">Subscribe to newsletter</span>
                                </label>
                                <small style="color: #777; margin-left: 28px;">
                                    Receive our monthly newsletter with exclusive offers and updates.
                                </small>
                            </div>

                            <div style="text-align: center; margin-top: 30px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Notification Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Preferences -->
                <div class="card">
                    <div class="card-header">
                        <h3>Preferences</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_preferences" value="1">

                            <div class="form-group">
                                <label class="form-label">Language</label>
                                <select name="language" class="form-control">
                                    <option value="en" <?= ($pref['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                    <option value="id" <?= ($pref['language'] ?? 'en') === 'id' ? 'selected' : '' ?>>Bahasa Indonesia</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Timezone</label>
                                <select name="timezone" class="form-control">
                                    <option value="Asia/Jakarta" <?= ($pref['timezone'] ?? 'Asia/Jakarta') === 'Asia/Jakarta' ? 'selected' : '' ?>>Asia/Jakarta (GMT+7)</option>
                                    <option value="Asia/Singapore" <?= ($pref['timezone'] ?? 'Asia/Jakarta') === 'Asia/Singapore' ? 'selected' : '' ?>>Asia/Singapore (GMT+8)</option>
                                    <option value="Asia/Tokyo" <?= ($pref['timezone'] ?? 'Asia/Jakarta') === 'Asia/Tokyo' ? 'selected' : '' ?>>Asia/Tokyo (GMT+9)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Currency</label>
                                <select name="currency" class="form-control">
                                    <option value="IDR" <?= ($pref['currency'] ?? 'IDR') === 'IDR' ? 'selected' : '' ?>>Indonesian Rupiah (IDR)</option>
                                    <option value="USD" <?= ($pref['currency'] ?? 'IDR') === 'USD' ? 'selected' : '' ?>>US Dollar (USD)</option>
                                    <option value="EUR" <?= ($pref['currency'] ?? 'IDR') === 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option>
                                </select>
                            </div>

                            <div style="text-align: center; margin-top: 30px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security -->
                <div class="card">
                    <div class="card-header">
                        <h3>Security</h3>
                    </div>
                    <div class="card-body">
                        <div class="section-title">
                            <i class="fas fa-shield-alt"></i> Account Security
                        </div>

                        <div style="color: #ccc; margin-bottom: 25px;">
                            <p>Your account security is important to us. Here are some security features:</p>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                            <div style="background: rgba(255,255,255,0.03); padding: 20px; border-radius: 10px;">
                                <h4 style="color: white; margin-bottom: 10px;">Last Login</h4>
                                <p style="color: #aaa;">
                                    <?= $customer['last_login'] ? date('d M Y, H:i', strtotime($customer['last_login'])) : 'First login' ?>
                                </p>
                            </div>

                            <div style="background: rgba(255,255,255,0.03); padding: 20px; border-radius: 10px;">
                                <h4 style="color: white; margin-bottom: 10px;">Account Created</h4>
                                <p style="color: #aaa;">
                                    <?= isset($customer['created_at']) ? date('d M Y', strtotime($customer['created_at'])) : '-' ?>
                                </p>
                            </div>

                            <div style="background: rgba(255,255,255,0.03); padding: 20px; border-radius: 10px;">
                                <h4 style="color: white; margin-bottom: 10px;">Password Strength</h4>
                                <p style="color: #2ecc71;">
                                    <i class="fas fa-check-circle"></i> Strong
                                </p>
                            </div>
                        </div>

                        <div style="text-align: center;">
                            <a href="profile.php#password" class="btn btn-secondary">
                                <i class="fas fa-lock"></i> Change Password
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="danger-zone">
                    <h3 class="danger-title">
                        <i class="fas fa-exclamation-triangle"></i> Danger Zone
                    </h3>

                    <p style="color: #ccc; margin-bottom: 20px;">
                        These actions are irreversible. Please proceed with caution.
                    </p>

                    <form method="POST" id="deactivateForm" onsubmit="return confirmDeactivation()">
                        <input type="hidden" name="deactivate_account" value="1">

                        <div class="form-group">
                            <label class="form-label" style="color: #e74c3c;">
                                Type "DELETE MY ACCOUNT" to confirm deactivation
                            </label>
                            <input type="text" name="confirm_text" class="form-control"
                                   placeholder="DELETE MY ACCOUNT" required>
                        </div>

                        <div style="color: #777; font-size: 0.9rem; margin-bottom: 20px;">
                            <i class="fas fa-info-circle"></i>
                            Deactivating your account will:
                            <ul style="margin: 10px 0 10px 20px; color: #aaa;">
                                <li>Cancel all pending bookings</li>
                                <li>Remove your personal information from our system</li>
                                <li>Prevent you from accessing your account</li>
                                <li>Delete your booking history</li>
                            </ul>
                        </div>

                        <div style="text-align: center;">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash-alt"></i> Deactivate My Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Confirm account deactivation
        function confirmDeactivation() {
            const confirmText = document.querySelector('input[name="confirm_text"]').value;

            if (confirmText !== 'DELETE MY ACCOUNT') {
                alert('Please type "DELETE MY ACCOUNT" exactly to confirm.');
                return false;
            }

            return confirm('⚠️ WARNING: This action is irreversible!\n\nAre you sure you want to deactivate your account?\n\nAll your data will be permanently deleted.');
        }

        // Save settings warning
        let settingsChanged = false;
        const settingsForms = document.querySelectorAll('form');

        settingsForms.forEach(form => {
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    settingsChanged = true;
                });
            });
        });

        window.addEventListener('beforeunload', function(e) {
            if (settingsChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltips = {
                'email_notifications': 'Receive booking confirmations, reminders, and promotional emails.',
                'sms_notifications': 'Get important alerts and reminders via SMS.',
                'newsletter': 'Monthly newsletter with exclusive offers and hotel updates.',
                'language': 'Interface language for your account.',
                'timezone': 'Time zone for displaying dates and times.',
                'currency': 'Preferred currency for displaying prices.'
            };

            Object.keys(tooltips).forEach(key => {
                const input = document.querySelector(`[name="${key}"]`);
                if (input) input.title = tooltips[key];
            });
        });
    </script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>
