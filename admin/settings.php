<?php
// admin/settings.php - SYSTEM SETTINGS
require_once '../includes/config.php';
requireAdmin();

$action = isset($_GET['action']) ? $_GET['action'] : 'general';
$page_title = 'System Settings';

// Helper function to get setting


// Handle save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // General settings
    $settings = [
        'hotel_name' => trim($_POST['hotel_name'] ?? ''),
        'hotel_address' => trim($_POST['hotel_address'] ?? ''),
        'hotel_phone' => trim($_POST['hotel_phone'] ?? ''),
        'hotel_email' => trim($_POST['hotel_email'] ?? ''),
        'checkin_time' => trim($_POST['checkin_time'] ?? '14:00'),
        'checkout_time' => trim($_POST['checkout_time'] ?? '12:00'),
        'currency' => trim($_POST['currency'] ?? 'IDR'),
        'timezone' => trim($_POST['timezone'] ?? 'Asia/Jakarta'),
        'booking_advance_days' => intval($_POST['booking_advance_days'] ?? 30),
        'min_stay_nights' => intval($_POST['min_stay_nights'] ?? 1),
        'max_stay_nights' => intval($_POST['max_stay_nights'] ?? 30),
        'tax_rate' => floatval($_POST['tax_rate'] ?? 11.0),
        'enable_smoking_rooms' => isset($_POST['enable_smoking_rooms']) ? 1 : 0,
        'enable_online_booking' => isset($_POST['enable_online_booking']) ? 1 : 0,
        'enable_guest_registration' => isset($_POST['enable_guest_registration']) ? 1 : 0
    ];
    
    foreach ($settings as $key => $value) {
        // Check if setting exists
        $check = $conn->query("SELECT id FROM settings WHERE setting_key = '$key'");
        if ($check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE setting_key = ?");
            $stmt->bind_param("ss", $value, $key);
        } else {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, value) VALUES (?, ?)");
            $stmt->bind_param("ss", $key, $value);
        }
        $stmt->execute();
    }
    
    $_SESSION['success'] = 'Settings saved successfully!';
    header('Location: settings.php');
    exit();
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

        .admin-wrapper {
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

        .card-body {
            padding: 25px;
        }

        /* === Form === */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: white;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
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
        }

        .btn-secondary:hover {
            background: rgba(76, 201, 240, 0.1);
        }

        /* === Tabs === */
        .settings-tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 15px;
        }

        .tab-btn {
            padding: 10px 20px;
            border-radius: 8px;
            background: transparent;
            border: 1px solid rgba(76, 201, 240, 0.3);
            color: #aaa;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .tab-btn.active,
        .tab-btn:hover {
            background: rgba(76, 201, 240, 0.2);
            color: var(--blue);
            border-color: var(--blue);
        }

        /* === User Menu in Header === */
        .user-menu-header {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(10, 25, 47, 0.8);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            cursor: pointer;
            position: relative;
        }

        .user-menu-header:hover {
            background: rgba(76, 201, 240, 0.1);
        }

        .user-menu-header .user-avatar {
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

        .user-menu-header .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-menu-header .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-menu-header .user-role {
            font-size: 0.8rem;
            color: #aaa;
        }

        .logout-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--card-bg);
            border: 1px solid rgba(76, 201, 240, 0.1);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
        }

        .logout-menu.show {
            display: block;
        }

        .logout-menu a {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            font-weight: 500;
        }

        .logout-menu a:hover {
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
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

    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-hotel"></i>
                </div>
                <div class="sidebar-title">
                    <h3><?= htmlspecialchars($hotel_name) ?></h3>
                    <p>Admin Dashboard</p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">ROOM MANAGEMENT</p>
                    <a href="rooms.php" class="nav-item">
                        <i class="fas fa-bed"></i>
                        <span>All Rooms</span>
                    </a>
                    <a href="rooms.php?action=add" class="nav-item">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add New Room</span>
                    </a>
                    <a href="rooms.php?action=categories" class="nav-item">
                        <i class="fas fa-tags"></i>
                        <span>Room Categories</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">BOOKINGS</p>
                    <a href="bookings.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>All Bookings</span>
                    </a>
                    <a href="bookings.php?action=add" class="nav-item">
                        <i class="fas fa-plus"></i>
                        <span>New Booking</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">CUSTOMERS</p>
                    <a href="customers.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span>All Customers</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">FINANCE</p>
                    <a href="payments.php" class="nav-item">
                        <i class="fas fa-credit-card"></i>
                        <span>Payments</span>
                    </a>
                    <a href="reports.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">SERVICES</p>
                    <a href="services.php" class="nav-item">
                        <i class="fas fa-concierge-bell"></i>
                        <span>Hotel Services</span>
                    </a>
                    <a href="staff.php" class="nav-item">
                        <i class="fas fa-user-tie"></i>
                        <span>Staff Management</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">SETTINGS</p>
                    <a href="settings.php" class="nav-item active">
                        <i class="fas fa-cog"></i>
                        <span>System Settings</span>
                    </a>
                </div>
            </nav>
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
                        Last Login: <?= date('d M Y H:i', strtotime($_SESSION['last_login'] ?? date('Y-m-d H:i:s'))) ?>
                    </div>
                    
                    <!-- User Menu in Header -->
                    <div class="user-menu-header" id="userMenuHeader">
                        <div class="user-avatar">
                            <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
                            <div class="user-role"><?= ucfirst($_SESSION['role'] ?? 'admin') ?></div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: #aaa;"></i>
                    </div>
                    
                    <!-- Logout Menu -->
                    <div class="logout-menu" id="logoutMenu">
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </header>

            <div class="content-area">
                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <button class="tab-btn active" onclick="showTab('general')">General</button>
                    <button class="tab-btn" onclick="showTab('booking')">Booking</button>
                    <button class="tab-btn" onclick="showTab('features')">Features</button>
                </div>

                <!-- General Settings -->
                <div class="tab-content" id="tab-general">
                    <div class="card">
                        <div class="card-body">
                            <h3 style="margin-bottom: 20px; color: var(--blue);">Hotel Information</h3>
                            <form method="POST" id="settingsForm">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label class="form-label">Hotel Name *</label>
                                        <input type="text" name="hotel_name" class="form-control" required 
                                               value="<?= htmlspecialchars(getSetting($conn, 'hotel_name')) ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Hotel Address *</label>
                                        <input type="text" name="hotel_address" class="form-control" required 
                                               value="<?= htmlspecialchars(getSetting($conn, 'hotel_address')) ?>">
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" name="hotel_phone" class="form-control" 
                                               value="<?= htmlspecialchars(getSetting($conn, 'hotel_phone')) ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" name="hotel_email" class="form-control" 
                                               value="<?= htmlspecialchars(getSetting($conn, 'hotel_email')) ?>">
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label class="form-label">Check-in Time</label>
                                        <input type="time" name="checkin_time" class="form-control" 
                                               value="<?= htmlspecialchars(getSetting($conn, 'checkin_time', '14:00')) ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Check-out Time</label>
                                        <input type="time" name="checkout_time" class="form-control" 
                                               value="<?= htmlspecialchars(getSetting($conn, 'checkout_time', '12:00')) ?>">
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label class="form-label">Currency</label>
                                        <select name="currency" class="form-control">
                                            <option value="IDR" <?= getSetting($conn, 'currency', 'IDR') == 'IDR' ? 'selected' : '' ?>>Indonesian Rupiah (IDR)</option>
                                            <option value="USD" <?= getSetting($conn, 'currency') == 'USD' ? 'selected' : '' ?>>US Dollar (USD)</option>
                                            <option value="EUR" <?= getSetting($conn, 'currency') == 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Timezone</label>
                                        <select name="timezone" class="form-control">
                                            <option value="Asia/Jakarta" <?= getSetting($conn, 'timezone', 'Asia/Jakarta') == 'Asia/Jakarta' ? 'selected' : '' ?>>Asia/Jakarta (WIB)</option>
                                            <option value="Asia/Makassar" <?= getSetting($conn, 'timezone') == 'Asia/Makassar' ? 'selected' : '' ?>>Asia/Makassar (WITA)</option>
                                            <option value="Asia/Jayapura" <?= getSetting($conn, 'timezone') == 'Asia/Jayapura' ? 'selected' : '' ?>>Asia/Jayapura (WIT)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 30px;">
                                    <h3 style="margin-bottom: 20px; color: var(--blue);">Tax Settings</h3>
                                    <div class="form-group">
                                        <label class="form-label">Tax Rate (%)</label>
                                        <input type="number" name="tax_rate" class="form-control" step="0.1"
                                               value="<?= htmlspecialchars(getSetting($conn, 'tax_rate', '11.0')) ?>">
                                        <small style="color: #aaa;">Default tax rate applied to all bookings</small>
                                    </div>
                                </div>
                                
                                <div style="display: flex; gap: 15px; margin-top: 30px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Booking Settings -->
                <div class="tab-content" id="tab-booking" style="display: none;">
                    <div class="card">
                        <div class="card-body">
                            <h3 style="margin-bottom: 20px; color: var(--blue);">Booking Configuration</h3>
                            <form method="POST" id="bookingSettingsForm">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label class="form-label">Maximum Advance Booking (Days)</label>
                                        <input type="number" name="booking_advance_days" class="form-control" min="1" max="365"
                                               value="<?= htmlspecialchars(getSetting($conn, 'booking_advance_days', '30')) ?>">
                                        <small style="color: #aaa;">How far in advance guests can book</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Minimum Stay (Nights)</label>
                                        <input type="number" name="min_stay_nights" class="form-control" min="1" max="30"
                                               value="<?= htmlspecialchars(getSetting($conn, 'min_stay_nights', '1')) ?>">
                                        <small style="color: #aaa;">Minimum nights for booking</small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Maximum Stay (Nights)</label>
                                    <input type="number" name="max_stay_nights" class="form-control" min="1" max="365"
                                           value="<?= htmlspecialchars(getSetting($conn, 'max_stay_nights', '30')) ?>">
                                    <small style="color: #aaa;">Maximum nights for booking</small>
                                </div>
                                
                                <div style="display: flex; gap: 15px; margin-top: 30px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Booking Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Features Settings -->
                <div class="tab-content" id="tab-features" style="display: none;">
                    <div class="card">
                        <div class="card-body">
                            <h3 style="margin-bottom: 20px; color: var(--blue);">Feature Toggles</h3>
                            <form method="POST" id="featuresSettingsForm">
                                <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" name="enable_smoking_rooms" value="1" 
                                               <?= getSetting($conn, 'enable_smoking_rooms') ? 'checked' : '' ?>>
                                        Enable Smoking Rooms
                                    </label>
                                </div>
                                
                                <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" name="enable_online_booking" value="1" 
                                               <?= getSetting($conn, 'enable_online_booking', '1') ? 'checked' : '' ?>>
                                        Enable Online Booking
                                    </label>
                                </div>
                                
                                <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" name="enable_guest_registration" value="1" 
                                               <?= getSetting($conn, 'enable_guest_registration', '1') ? 'checked' : '' ?>>
                                        Enable Guest Self-Registration
                                    </label>
                                </div>
                                
                                <div style="display: flex; gap: 15px; margin-top: 30px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Feature Settings
                                    </button>
                                </div>
                            </form>
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
        
        // Toggle Logout Menu
        const userMenuHeader = document.getElementById('userMenuHeader');
        const logoutMenu = document.getElementById('logoutMenu');
        
        if (userMenuHeader && logoutMenu) {
            userMenuHeader.addEventListener('click', function(e) {
                e.stopPropagation();
                logoutMenu.classList.toggle('show');
            });

            // Close menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!userMenuHeader.contains(e.target) && !logoutMenu.contains(e.target)) {
                    logoutMenu.classList.remove('show');
                }
            });
        }
        
        // Tab switching
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab and activate button
            document.getElementById('tab-' + tabName).style.display = 'block';
            event.target.classList.add('active');
        }
        
        // Form submission handling
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            this.submit();
        });
        
        document.getElementById('bookingSettingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            this.submit();
        });
        
        document.getElementById('featuresSettingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            this.submit();
        });
    </script>
</body>
</html>