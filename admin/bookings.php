<?php
// admin/bookings.php - BOOKING MANAGEMENT WITH FULL FUNCTIONALITY
require_once '../includes/config.php';
requireAdmin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$page_title = 'Booking Management';

// Handle export requests
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    
    // Get all bookings data
    $query = "SELECT b.booking_code, u.full_name as customer, r.room_number, 
                     b.check_in, b.check_out, b.total_nights, b.booking_status, 
                     b.payment_status, b.final_price
              FROM bookings b 
              JOIN users u ON b.user_id = u.id 
              JOIN rooms r ON b.room_id = r.id 
              ORDER BY b.created_at DESC";
    $result = $conn->query($query);
    
    if ($type == 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="bookings_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Booking Code', 'Customer', 'Room', 'Check-in', 'Check-out', 'Nights', 'Status', 'Payment', 'Amount']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['booking_code'],
                $row['customer'],
                $row['room_number'],
                date('d M Y', strtotime($row['check_in'])),
                date('d M Y', strtotime($row['check_out'])),
                $row['total_nights'],
                ucfirst($row['booking_status']),
                ucfirst($row['payment_status']),
                formatCurrency($row['final_price'])
            ]);
        }
        fclose($output);
        exit();
    } elseif ($type == 'pdf') {
        // Simple HTML-to-PDF fallback
        echo "<html><head><title>Booking Report</title></head><body>";
        echo "<h2>Booking Report</h2>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Booking Code</th><th>Customer</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Nights</th><th>Status</th><th>Payment</th><th>Amount</th></tr>";
        
        $result->data_seek(0); // Reset pointer
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['booking_code']) . "</td>";
            echo "<td>" . htmlspecialchars($row['customer']) . "</td>";
            echo "<td>" . htmlspecialchars($row['room_number']) . "</td>";
            echo "<td>" . date('d M Y', strtotime($row['check_in'])) . "</td>";
            echo "<td>" . date('d M Y', strtotime($row['check_out'])) . "</td>";
            echo "<td>" . $row['total_nights'] . "</td>";
            echo "<td>" . ucfirst($row['booking_status']) . "</td>";
            echo "<td>" . ucfirst($row['payment_status']) . "</td>";
            echo "<td>" . formatCurrency($row['final_price']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<script>window.print();</script>";
        echo "</body></html>";
        exit();
    }
}

// Get booking stats
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];
$pending_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'pending'")->fetch_assoc()['count'];
$confirmed_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'confirmed'")->fetch_assoc()['count'];
$revenue_today = $conn->query("SELECT SUM(final_price) as total FROM bookings WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'")->fetch_assoc()['total'] ?? 0;

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Booking deleted successfully!';
    } else {
        $_SESSION['error'] = 'Failed to delete booking.';
    }
    header('Location: bookings.php');
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

        /* === Stats Grid === */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(76, 201, 240, 0.1);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .stat-icon.primary { background: rgba(76, 201, 240, 0.2); color: var(--blue); }
        .stat-icon.warning { background: rgba(243, 156, 18, 0.2); color: #f39c12; }
        .stat-icon.success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .stat-icon.danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }

        .stat-info h3 {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: white;
        }

        .stat-info p {
            color: #aaa;
            font-size: 0.9rem;
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

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: rgba(76, 201, 240, 0.2);
            color: var(--blue);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending { background: rgba(243, 156, 18, 0.2); color: #f39c12; }
        .status-confirmed { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .status-checked_in { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        .status-checked_out { background: rgba(155, 89, 182, 0.2); color: #9b59b6; }
        .status-cancelled { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .status-paid { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .status-pending-payment { background: rgba(243, 156, 18, 0.2); color: #f39c12; }

        /* === Export Buttons === */
        .export-buttons {
            display: flex;
            gap: 10px;
        }

        /* === Select Dropdown Style === */
        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z' fill='%23666'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px;
            color: white;
        }

        select.form-control:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(76, 201, 240, 0.15);
            background: rgba(255,255,255,0.1);
        }

        select.form-control option {
            color: #333;
            background: white;
            padding: 8px 15px;
        }

        select.form-control option:checked {
            background: var(--blue);
            color: white;
        }

        select.form-control option:hover {
            background: rgba(76, 201, 240, 0.1);
            color: #333;
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
            .export-buttons {
                flex-direction: column;
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
                    <a href="bookings.php" class="nav-item <?= ($action == 'list') ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span>All Bookings</span>
                    </a>
                    <a href="bookings.php?action=add" class="nav-item <?= ($action == 'add') ? 'active' : '' ?>">
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
                    <a href="settings.php" class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>System Settings</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
                        <div class="user-role"><?= ucfirst($_SESSION['role'] ?? 'admin') ?></div>
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
                        Last Login: <?= date('d M Y H:i', strtotime($_SESSION['last_login'] ?? date('Y-m-d H:i:s'))) ?>
                    </div>
                    
                    <!-- User Menu with Logout -->
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
                <?php if ($action == 'list'): ?>
                
                <!-- Page Header -->
                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <h2 style="font-size: 24px; font-weight: 600; margin: 0;">Booking Management</h2>
                        <p style="color: #aaa; margin-top: 5px;">Manage all hotel bookings</p>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="?action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Booking
                        </a>
                        <div class="export-buttons">
                            <a href="?export=csv" class="btn btn-secondary">
                                <i class="fas fa-file-csv"></i> CSV
                            </a>
                            <a href="?export=pdf" class="btn btn-secondary">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $total_bookings ?></h3>
                            <p>Total Bookings</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $pending_bookings ?></h3>
                            <p>Pending</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $confirmed_bookings ?></h3>
                            <p>Confirmed</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= formatCurrency($revenue_today) ?></h3>
                            <p>Today's Revenue</p>
                        </div>
                    </div>
                </div>

                <!-- Bookings Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Booking Code</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Customer</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Room</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Check-in</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Check-out</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Nights</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Status</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Payment</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Amount</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $query = "SELECT b.*, u.full_name, u.phone, r.room_number 
                                             FROM bookings b 
                                             JOIN users u ON b.user_id = u.id 
                                             JOIN rooms r ON b.room_id = r.id 
                                             ORDER BY b.created_at DESC";
                                    $result = $conn->query($query);
                                    
                                    while ($booking = $result->fetch_assoc()) {
                                        $status_class = strtolower($booking['booking_status']);
                                        $payment_class = strtolower($booking['payment_status']);
                                    ?>
                                    <tr>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <strong><?= htmlspecialchars($booking['booking_code']) ?></strong><br>
                                            <small style="color: #aaa;"><?= date('d M Y', strtotime($booking['created_at'])) ?></small>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <strong><?= htmlspecialchars($booking['full_name']) ?></strong><br>
                                            <small style="color: #aaa;"><?= htmlspecialchars($booking['phone']) ?></small>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= htmlspecialchars($booking['room_number']) ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= date('d M Y', strtotime($booking['check_in'])) ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= date('d M Y', strtotime($booking['check_out'])) ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= $booking['total_nights'] ?> nights</td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <span class="status-badge status-<?= $status_class ?>"><?= ucfirst($booking['booking_status']) ?></span>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <span class="status-badge status-<?= $payment_class ?>"><?= ucfirst($booking['payment_status']) ?></span>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= formatCurrency($booking['final_price']) ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <div style="display: flex; gap: 5px;">
                                                <a href="?action=view&id=<?= $booking['id'] ?>" class="btn btn-sm btn-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?action=edit&id=<?= $booking['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="payments.php?booking_id=<?= $booking['id'] ?>" class="btn btn-sm btn-success" title="Payment">
                                                    <i class="fas fa-credit-card"></i>
                                                </a>
                                                <button onclick="deleteBooking(<?= $booking['id'] ?>)" class="btn btn-sm btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php elseif ($action == 'add' || $action == 'edit'): ?>
                
                <?php
                $booking = null;
                if ($action == 'edit' && isset($_GET['id'])) {
                    $id = intval($_GET['id']);
                    $booking = $conn->query("SELECT * FROM bookings WHERE id = $id")->fetch_assoc();
                }
                ?>
                
                <!-- Add/Edit Booking Form -->
                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="font-size: 24px; font-weight: 600; margin: 0;">
                            <?= $action == 'add' ? 'Create New Booking' : 'Edit Booking' ?>
                        </h2>
                        <p style="color: #aaa; margin-top: 5px;">
                            <?= $action == 'add' ? 'Create a new room reservation' : 'Update booking information' ?>
                        </p>
                    </div>
                    <div>
                        <a href="bookings.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Bookings
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="../ajax/save_booking.php">
                            <?php if ($action == 'edit'): ?>
                            <input type="hidden" name="id" value="<?= $booking['id'] ?>">
                            <?php endif; ?>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label class="form-label">Customer *</label>
                                    <select name="user_id" class="form-control" required>
                                        <option value="">Select Customer</option>
                                        <?php
                                        $customers = $conn->query("SELECT * FROM users WHERE role = 'customer'");
                                        while ($customer = $customers->fetch_assoc()) {
                                            $selected = ($booking['user_id'] ?? '') == $customer['id'] ? 'selected' : '';
                                            echo '<option value="' . $customer['id'] . '" ' . $selected . '>' . 
                                                 htmlspecialchars($customer['full_name']) . ' (' . htmlspecialchars($customer['email']) . ')</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Room *</label>
                                    <select name="room_id" class="form-control" required>
                                        <option value="">Select Room</option>
                                        <?php
                                        $rooms = $conn->query("SELECT r.*, rc.base_price, rc.name as category_name 
                                                              FROM rooms r 
                                                              JOIN room_categories rc ON r.category_id = rc.id 
                                                              WHERE r.status = 'available'");
                                        while ($room = $rooms->fetch_assoc()) {
                                            $selected = ($booking['room_id'] ?? '') == $room['id'] ? 'selected' : '';
                                            echo '<option value="' . $room['id'] . '" ' . $selected . '>' . 
                                                 htmlspecialchars($room['room_number']) . ' - ' . htmlspecialchars($room['category_name']) . ' (' . formatCurrency($room['base_price']) . '/night)</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label class="form-label">Check-in Date *</label>
                                    <input type="date" name="check_in" class="form-control" required 
                                           value="<?= $booking['check_in'] ?? date('Y-m-d') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Check-out Date *</label>
                                    <input type="date" name="check_out" class="form-control" required 
                                           value="<?= $booking['check_out'] ?? date('Y-m-d', strtotime('+1 day')) ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Booking Status</label>
                                    <select name="booking_status" class="form-control">
                                        <option value="pending" <?= ($booking['booking_status'] ?? '') == 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="confirmed" <?= ($booking['booking_status'] ?? '') == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="checked_in" <?= ($booking['booking_status'] ?? '') == 'checked_in' ? 'selected' : '' ?>>Checked In</option>
                                        <option value="checked_out" <?= ($booking['booking_status'] ?? '') == 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
                                        <option value="cancelled" <?= ($booking['booking_status'] ?? '') == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label class="form-label">Adults *</label>
                                    <input type="number" name="adults" class="form-control" required 
                                           value="<?= $booking['adults'] ?? 1 ?>" min="1">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Children</label>
                                    <input type="number" name="children" class="form-control" 
                                           value="<?= $booking['children'] ?? 0 ?>" min="0">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Special Requests</label>
                                <textarea name="special_requests" class="form-control" rows="3"><?= htmlspecialchars($booking['special_requests'] ?? '') ?></textarea>
                            </div>
                            
                            <div style="display: flex; gap: 15px; margin-top: 20px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?= $action == 'add' ? 'Create Booking' : 'Update Booking' ?>
                                </button>
                                <a href="bookings.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action == 'view' && isset($_GET['id'])): ?>
                
                <?php
                $id = intval($_GET['id']);
                $booking = $conn->query("SELECT b.*, u.full_name, u.email, u.phone, r.room_number, rc.name as category_name 
                                        FROM bookings b 
                                        JOIN users u ON b.user_id = u.id 
                                        JOIN rooms r ON b.room_id = r.id 
                                        JOIN room_categories rc ON r.category_id = rc.id 
                                        WHERE b.id = $id")->fetch_assoc();
                ?>
                
                <!-- Booking Details -->
                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="font-size: 24px; font-weight: 600; margin: 0;">Booking Details</h2>
                        <p style="color: #aaa; margin-top: 5px;">View and manage booking information</p>
                    </div>
                    <div>
                        <a href="bookings.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <a href="?action=edit&id=<?= $id ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                    <div class="card">
                        <div class="card-body">
                            <div style="margin-bottom: 20px;">
                                <h3 style="font-size: 22px; margin: 0;">Booking #<?= htmlspecialchars($booking['booking_code']) ?></h3>
                                <span class="status-badge status-<?= strtolower($booking['booking_status']) ?>" style="margin-top: 10px; display: inline-block;">
                                    <?= ucfirst($booking['booking_status']) ?>
                                </span>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <h4 style="margin: 0 0 15px 0; font-size: 16px; color: var(--blue);">Customer Information</h4>
                                    <p><strong>Name:</strong> <?= htmlspecialchars($booking['full_name']) ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($booking['email']) ?></p>
                                    <p><strong>Phone:</strong> <?= htmlspecialchars($booking['phone']) ?></p>
                                </div>
                                
                                <div>
                                    <h4 style="margin: 0 0 15px 0; font-size: 16px; color: var(--blue);">Room Information</h4>
                                    <p><strong>Room:</strong> <?= htmlspecialchars($booking['room_number']) ?></p>
                                    <p><strong>Category:</strong> <?= htmlspecialchars($booking['category_name']) ?></p>
                                    <p><strong>Duration:</strong> <?= $booking['total_nights'] ?> nights</p>
                                </div>
                                
                                <div>
                                    <h4 style="margin: 0 0 15px 0; font-size: 16px; color: var(--blue);">Stay Details</h4>
                                    <p><strong>Check-in:</strong> <?= date('d M Y', strtotime($booking['check_in'])) ?></p>
                                    <p><strong>Check-out:</strong> <?= date('d M Y', strtotime($booking['check_out'])) ?></p>
                                    <p><strong>Guests:</strong> <?= $booking['adults'] ?> Adults, <?= $booking['children'] ?> Children</p>
                                </div>
                                
                                <div>
                                    <h4 style="margin: 0 0 15px 0; font-size: 16px; color: var(--blue);">Payment Details</h4>
                                    <p><strong>Payment Status:</strong>
                                        <span class="status-badge status-<?= strtolower($booking['payment_status']) ?>">
                                            <?= ucfirst($booking['payment_status']) ?>
                                        </span>
                                    </p>
                                    <p><strong>Payment Method:</strong> <?= $booking['payment_method'] ?? 'Not specified' ?></p>
                                    <p><strong>Total Amount:</strong> <?= formatCurrency($booking['final_price']) ?></p>
                                </div>
                            </div>
                            
                            <?php if ($booking['special_requests']): ?>
                            <div style="margin-top: 20px;">
                                <h4 style="margin: 0 0 15px 0; font-size: 16px; color: var(--blue);">Special Requests</h4>
                                <p><?= nl2br(htmlspecialchars($booking['special_requests'])) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div style="margin-top: 20px;">
                                <h4 style="margin: 0 0 15px 0; font-size: 16px; color: var(--blue);">Timeline</h4>
                                <p><strong>Created:</strong> <?= date('d M Y H:i', strtotime($booking['created_at'])) ?></p>
                                <p><strong>Last Updated:</strong> <?= date('d M Y H:i', strtotime($booking['updated_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="card">
                            <div class="card-body">
                                <h4 style="margin: 0 0 15px 0; font-size: 16px; color: var(--blue);">Quick Actions</h4>
                                <?php if ($booking['booking_status'] == 'pending'): ?>
                                <button onclick="updateStatus(<?= $id ?>, 'confirmed')" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                                    <i class="fas fa-check"></i> Confirm Booking
                                </button>
                                <?php elseif ($booking['booking_status'] == 'confirmed'): ?>
                                <button onclick="updateStatus(<?= $id ?>, 'checked_in')" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                                    <i class="fas fa-door-open"></i> Check In
                                </button>
                                <?php elseif ($booking['booking_status'] == 'checked_in'): ?>
                                <button onclick="updateStatus(<?= $id ?>, 'checked_out')" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                                    <i class="fas fa-door-closed"></i> Check Out
                                </button>
                                <?php endif; ?>
                                
                                <button onclick="cancelBooking(<?= $id ?>)" class="btn btn-danger" style="width: 100%;">
                                    <i class="fas fa-times"></i> Cancel Booking
                                </button>
                                
                                <a href="payments.php?booking_id=<?= $id ?>" class="btn btn-secondary" style="width: 100%; margin-top: 10px;">
                                    <i class="fas fa-credit-card"></i> Manage Payments
                                </a>
                            </div>
                        </div>
                        
                        <div class="card" style="margin-top: 20px;">
                            <div class="card-body">
                                <h4 style="margin: 0 0 15px 0; font-size: 16px; color: var(--blue);">Price Breakdown</h4>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                    <span>Room Price (<?= $booking['total_nights'] ?> nights)</span>
                                    <span><?= formatCurrency($booking['total_price']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                    <span>Discount</span>
                                    <span>- <?= formatCurrency($booking['discount_amount']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px; font-weight: 600;">
                                    <span>Total Amount</span>
                                    <span><?= formatCurrency($booking['final_price']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
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
        
        // Delete Booking
        function deleteBooking(id) {
            if (confirm('Delete this booking?')) {
                window.location.href = '?delete=' + id;
            }
        }
        
        // Update Status
        function updateStatus(bookingId, status) {
            if (confirm('Update booking status to ' + status + '?')) {
                fetch('../ajax/update_booking_status.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'id=' + bookingId + '&status=' + status
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Status updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
        
        // Cancel Booking
        function cancelBooking(bookingId) {
            if (confirm('Cancel this booking?')) {
                fetch('../ajax/cancel_booking.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'id=' + bookingId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Booking cancelled successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html>