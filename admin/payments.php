<?php
// admin/payments.php - PAYMENT MANAGEMENT
require_once '../includes/config.php';
requireAdmin();

$page_title = 'Payment Management';

// Payment statistics
$total_payments = $conn->query("SELECT COUNT(*) as count FROM payments")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'")->fetch_assoc()['total'] ?? 0;
$pending_payments = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'")->fetch_assoc()['count'];
$failed_payments = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'failed'")->fetch_assoc()['count'];

// Handle export requests
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    
    // Get all payments data
    $query = "SELECT p.payment_code, b.booking_code, u.full_name as customer, 
                     p.amount, p.payment_method, p.payment_date, p.status, p.transaction_id
              FROM payments p 
              JOIN bookings b ON p.booking_id = b.id 
              JOIN users u ON b.user_id = u.id 
              ORDER BY p.created_at DESC";
    $result = $conn->query($query);
    
    if ($type == 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="payments_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Payment Code', 'Booking', 'Customer', 'Amount', 'Method', 'Date', 'Status', 'Transaction ID']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['payment_code'],
                $row['booking_code'],
                $row['customer'],
                formatCurrency($row['amount']),
                ucfirst(str_replace('_', ' ', $row['payment_method'])),
                date('d M Y', strtotime($row['payment_date'])),
                ucfirst($row['status']),
                $row['transaction_id'] ?? 'N/A'
            ]);
        }
        fclose($output);
        exit();
    } elseif ($type == 'pdf') {
        // Simple HTML-to-PDF fallback
        echo "<html><head><title>Payment Report</title></head><body>";
        echo "<h2>Payment Report</h2>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Payment Code</th><th>Booking</th><th>Customer</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th><th>Transaction ID</th></tr>";
        
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['payment_code']) . "</td>";
            echo "<td>" . htmlspecialchars($row['booking_code']) . "</td>";
            echo "<td>" . htmlspecialchars($row['customer']) . "</td>";
            echo "<td>" . formatCurrency($row['amount']) . "</td>";
            echo "<td>" . ucfirst(str_replace('_', ' ', $row['payment_method'])) . "</td>";
            echo "<td>" . date('d M Y', strtotime($row['payment_date'])) . "</td>";
            echo "<td>" . ucfirst($row['status']) . "</td>";
            echo "<td>" . ($row['transaction_id'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<script>window.print();</script>";
        echo "</body></html>";
        exit();
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
        .stat-icon.success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .stat-icon.warning { background: rgba(243, 156, 18, 0.2); color: #f39c12; }
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
        .status-completed { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .status-failed { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .status-refunded { background: rgba(155, 89, 182, 0.2); color: #9b59b6; }

        /* === Select Dropdown Style === */
        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z' fill='%23666'/%3E%3C/svg%3E");
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

        /* === Modal === */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 25px;
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
                    <a href="payments.php" class="nav-item active">
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
                <!-- Page Header -->
                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <h2 style="font-size: 24px; font-weight: 600; margin: 0;">Payment Management</h2>
                        <p style="color: #aaa; margin-top: 5px;">Manage all hotel payments</p>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
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

                <!-- Payment Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $total_payments ?></h3>
                            <p>Total Payments</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= formatCurrency($total_revenue) ?></h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $pending_payments ?></h3>
                            <p>Pending Payments</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $failed_payments ?></h3>
                            <p>Failed Payments</p>
                        </div>
                    </div>
                </div>

                <!-- Payment Filters -->
                <div class="card">
                    <div class="card-body">
                        <form id="paymentFilter" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                    <option value="failed">Failed</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Payment Method</label>
                                <select name="method" class="form-control">
                                    <option value="">All Methods</option>
                                    <option value="cash">Cash</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="e-wallet">E-Wallet</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Date From</label>
                                <input type="date" name="date_from" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Date To</label>
                                <input type="date" name="date_to" class="form-control">
                            </div>
                            
                            <div class="form-group" style="align-self: end;">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Payments Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Payment Code</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Booking</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Customer</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Amount</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Method</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Date</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Status</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Transaction ID</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $query = "SELECT p.*, b.booking_code, u.full_name 
                                             FROM payments p 
                                             JOIN bookings b ON p.booking_id = b.id 
                                             JOIN users u ON b.user_id = u.id 
                                             ORDER BY p.created_at DESC";
                                    $result = $conn->query($query);
                                    
                                    while ($payment = $result->fetch_assoc()) {
                                        $status_class = strtolower($payment['status']);
                                        $method_icons = [
                                            'cash' => 'fas fa-money-bill-wave',
                                            'credit_card' => 'fas fa-credit-card',
                                            'debit_card' => 'fas fa-credit-card',
                                            'bank_transfer' => 'fas fa-university',
                                            'e-wallet' => 'fas fa-mobile-alt'
                                        ];
                                        $icon = $method_icons[$payment['payment_method']] ?? 'fas fa-money-bill-wave';
                                    ?>
                                    <tr>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <strong><?= htmlspecialchars($payment['payment_code']) ?></strong><br>
                                            <small>ID: <?= $payment['id'] ?></small>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <a href="bookings.php?action=view&id=<?= $payment['booking_id'] ?>" style="color: var(--blue); text-decoration: none;">
                                                <?= htmlspecialchars($payment['booking_code']) ?>
                                            </a>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= htmlspecialchars($payment['full_name']) ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= formatCurrency($payment['amount']) ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <i class="<?= $icon ?>"></i>
                                            <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= date('d M Y', strtotime($payment['payment_date'])) ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <span class="status-badge status-<?= $status_class ?>"><?= ucfirst($payment['status']) ?></span>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <?php if ($payment['transaction_id']): ?>
                                            <code><?= substr(htmlspecialchars($payment['transaction_id']), 0, 15) ?>...</code>
                                            <?php else: ?>
                                            <em>N/A</em>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <div style="display: flex; gap: 5px;">
                                                <button onclick="viewPayment(<?= $payment['id'] ?>)" class="btn btn-sm btn-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="editPayment(<?= $payment['id'] ?>)" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($payment['status'] == 'pending'): ?>
                                                <button onclick="markAsPaid(<?= $payment['id'] ?>)" class="btn btn-sm btn-success" title="Mark as Paid">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Payment Modal -->
    <div class="modal" id="paymentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="paymentModalTitle">Payment Details</h3>
                <button class="close-modal" onclick="closePaymentModal()">&times;</button>
            </div>
            <div class="modal-body" id="paymentModalBody">
                <!-- Payment details will be loaded here -->
            </div>
        </div>
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
        
        // View Payment
        function viewPayment(id) {
            fetch('ajax/get_payment.php?id=' + id)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('paymentModalBody').innerHTML = html;
                    document.getElementById('paymentModalTitle').textContent = 'Payment Details';
                    document.getElementById('paymentModal').style.display = 'flex';
                })
                .catch(error => {
                    document.getElementById('paymentModalBody').innerHTML = '<div style="padding: 20px; color: #ff6b6b;">Failed to load payment details.</div>';
                    document.getElementById('paymentModalTitle').textContent = 'Error';
                    document.getElementById('paymentModal').style.display = 'flex';
                });
        }
        
        // Edit Payment
        function editPayment(id) {
            fetch('ajax/edit_payment.php?id=' + id)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('paymentModalBody').innerHTML = html;
                    document.getElementById('paymentModalTitle').textContent = 'Edit Payment';
                    document.getElementById('paymentModal').style.display = 'flex';
                })
                .catch(error => {
                    document.getElementById('paymentModalBody').innerHTML = '<div style="padding: 20px; color: #ff6b6b;">Failed to load edit form.</div>';
                    document.getElementById('paymentModalTitle').textContent = 'Error';
                    document.getElementById('paymentModal').style.display = 'flex';
                });
        }
        
        // Mark as Paid
        function markAsPaid(id) {
            if (confirm('Mark this payment as completed?')) {
                fetch('ajax/update_payment_status.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'id=' + id + '&status=completed'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Payment marked as completed!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
        
        // Close Modal
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }
        
        // Filter Form
        document.getElementById('paymentFilter').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                if (value) params.append(key, value);
            }
            
            // In a real app, you'd fetch filtered data via AJAX
            // For now, we'll just reload with query params
            window.location.search = params.toString();
        });
    </script>
</body>
</html>