<?php
// admin/reports.php - REPORTS MANAGEMENT
require_once '../includes/config.php';
requireAdmin();

$report_type = isset($_GET['type']) ? $_GET['type'] : 'financial';
$page_title = 'Reports - ' . ucfirst($report_type);

// Generate report data based on type
$chart_data = [];
$table_data = [];
$report_title = '';

switch ($report_type) {
    case 'financial':
        $report_title = 'Financial Reports';
        // Get monthly revenue for the last 6 months
        $result = $conn->query("
            SELECT DATE_FORMAT(payment_date, '%Y-%m') as month,
                   SUM(amount) as revenue,
                   COUNT(*) as transactions
            FROM payments 
            WHERE status = 'completed'
            AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
            ORDER BY month
        ");
        while ($row = $result->fetch_assoc()) {
            $chart_data['labels'][] = date('M Y', strtotime($row['month'] . '-01'));
            $chart_data['revenue'][] = $row['revenue'];
            $chart_data['transactions'][] = $row['transactions'];
        }
        break;
        
    case 'occupancy':
        $report_title = 'Occupancy Reports';
        // Get occupancy rate by month
        $result = $conn->query("
            SELECT DATE_FORMAT(check_in, '%Y-%m') as month,
                   COUNT(*) as total_bookings,
                   AVG(CASE WHEN booking_status IN ('confirmed', 'checked_in') THEN 1 ELSE 0 END) * 100 as occupancy_rate
            FROM bookings 
            WHERE check_in >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(check_in, '%Y-%m')
            ORDER BY month
        ");
        while ($row = $result->fetch_assoc()) {
            $chart_data['labels'][] = date('M Y', strtotime($row['month'] . '-01'));
            $chart_data['bookings'][] = $row['total_bookings'];
            $chart_data['occupancy'][] = round($row['occupancy_rate'], 2);
        }
        break;
        
    case 'revenue':
        $report_title = 'Revenue Reports';
        // Get revenue by room category
        $result = $conn->query("
            SELECT rc.name as category,
                   SUM(b.final_price) as revenue,
                   COUNT(b.id) as bookings
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            JOIN room_categories rc ON r.category_id = rc.id
            WHERE b.payment_status = 'paid'
            GROUP BY rc.name
            ORDER BY revenue DESC
        ");
        while ($row = $result->fetch_assoc()) {
            $chart_data['labels'][] = $row['category'];
            $chart_data['revenue'][] = $row['revenue'];
            $table_data[] = $row;
        }
        break;
        
    case 'customers':
        $report_title = 'Customer Reports';
        // Get customer demographics and spending
        $result = $conn->query("
            SELECT 
                COUNT(*) as total_customers,
                AVG(total_spent) as avg_spending,
                MAX(total_spent) as max_spending,
                SUM(total_spent) as total_spent
            FROM (
                SELECT u.id, 
                       SUM(CASE WHEN b.payment_status = 'paid' THEN b.final_price ELSE 0 END) as total_spent
                FROM users u
                LEFT JOIN bookings b ON u.id = b.user_id
                WHERE u.role = 'customer'
                GROUP BY u.id
            ) customer_stats
        ");
        $stats = $result->fetch_assoc();
        
        // Get top customers
        $top_customers = $conn->query("
            SELECT u.full_name, u.email, 
                   COUNT(b.id) as total_bookings,
                   SUM(CASE WHEN b.payment_status = 'paid' THEN b.final_price ELSE 0 END) as total_spent
            FROM users u
            LEFT JOIN bookings b ON u.id = b.user_id
            WHERE u.role = 'customer'
            GROUP BY u.id
            ORDER BY total_spent DESC
            LIMIT 10
        ");
        break;
}

// Handle export requests
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    
    if ($report_type == 'financial') {
        // Export financial report
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="financial_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Month', 'Revenue', 'Transactions', 'Average Transaction', 'Growth']);
        
        if (isset($chart_data['labels'])) {
            for ($i = 0; $i < count($chart_data['labels']); $i++) {
                $growth = $i > 0 ? (($chart_data['revenue'][$i] - $chart_data['revenue'][$i-1]) / $chart_data['revenue'][$i-1] * 100) : 0;
                $avg_transaction = $chart_data['transactions'][$i] > 0 ? $chart_data['revenue'][$i] / $chart_data['transactions'][$i] : 0;
                fputcsv($output, [
                    $chart_data['labels'][$i],
                    formatCurrency($chart_data['revenue'][$i]),
                    $chart_data['transactions'][$i],
                    formatCurrency($avg_transaction),
                    round($growth, 1) . '%'
                ]);
            }
        }
        fclose($output);
        exit();
    } elseif ($report_type == 'revenue') {
        // Export revenue by category
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="revenue_by_category_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Category', 'Revenue', 'Bookings', 'Average Revenue/Booking', 'Percentage']);
        
        $total_revenue = array_sum($chart_data['revenue'] ?? []);
        foreach ($table_data as $row) {
            $percentage = $total_revenue > 0 ? ($row['revenue'] / $total_revenue * 100) : 0;
            $avg_revenue = $row['bookings'] > 0 ? $row['revenue'] / $row['bookings'] : 0;
            fputcsv($output, [
                $row['category'],
                formatCurrency($row['revenue']),
                $row['bookings'],
                formatCurrency($avg_revenue),
                round($percentage, 1) . '%'
            ]);
        }
        fclose($output);
        exit();
    } elseif ($report_type == 'customers') {
        // Export customer report
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="top_customers_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Rank', 'Customer', 'Email', 'Bookings', 'Total Spent', 'Average/Booking', 'Last Booking']);
        
        $rank = 1;
        while ($customer = $top_customers->fetch_assoc()) {
            $avg_booking = $customer['total_bookings'] > 0 ? $customer['total_spent'] / $customer['total_bookings'] : 0;
            $last_booking = $conn->query("SELECT MAX(check_in) as last_booking FROM bookings WHERE user_id = (SELECT id FROM users WHERE email = '" . $customer['email'] . "')")->fetch_assoc();
            fputcsv($output, [
                '#' . $rank++,
                $customer['full_name'],
                $customer['email'],
                $customer['total_bookings'],
                formatCurrency($customer['total_spent']),
                formatCurrency($avg_booking),
                $last_booking['last_booking'] ? date('d M Y', strtotime($last_booking['last_booking'])) : 'Never'
            ]);
        }
        fclose($output);
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* === Chart Canvas === */
        canvas {
            max-width: 100%;
            height: 300px !important;
        }

        /* === Table === */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        th {
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
            font-weight: 600;
        }

        tr:hover {
            background: rgba(76, 201, 240, 0.05);
        }

        /* === Progress Bar === */
        .progress {
            height: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: var(--blue);
            color: white;
            text-align: center;
            font-size: 12px;
            line-height: 10px;
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
                    <a href="reports.php" class="nav-item active">
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
                        <h2 style="font-size: 24px; font-weight: 600; margin: 0;"><?= htmlspecialchars($report_title) ?></h2>
                        <p style="color: #aaa; margin-top: 5px;">View detailed reports for your hotel</p>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <select onchange="window.location.href='reports.php?type=' + this.value" class="form-control" style="padding: 8px 15px; border-radius: 5px; background: rgba(255,255,255,0.1); color: white;">
                            <option value="financial" <?= $report_type == 'financial' ? 'selected' : '' ?>>Financial Reports</option>
                            <option value="occupancy" <?= $report_type == 'occupancy' ? 'selected' : '' ?>>Occupancy Reports</option>
                            <option value="revenue" <?= $report_type == 'revenue' ? 'selected' : '' ?>>Revenue Reports</option>
                            <option value="customers" <?= $report_type == 'customers' ? 'selected' : '' ?>>Customer Reports</option>
                        </select>
                        <a href="?type=<?= $report_type ?>&export=csv" class="btn btn-secondary">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                        <button onclick="printReport()" class="btn btn-secondary">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>

                <!-- Date Range Selector -->
                <div class="card">
                    <div class="card-body">
                        <form id="dateRangeForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label class="form-label">From Date</label>
                                <input type="date" name="from_date" class="form-control"
                                       value="<?= date('Y-m-01', strtotime('-6 months')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">To Date</label>
                                <input type="date" name="to_date" class="form-control"
                                       value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Report Type</label>
                                <select name="report_type" class="form-control" onchange="loadReport(this.value)">
                                    <option value="daily" <?= ($_GET['period'] ?? '') == 'daily' ? 'selected' : '' ?>>Daily</option>
                                    <option value="weekly" <?= ($_GET['period'] ?? '') == 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                    <option value="monthly" <?= ($_GET['period'] ?? '') == 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                    <option value="yearly" <?= ($_GET['period'] ?? '') == 'yearly' ? 'selected' : '' ?>>Yearly</option>
                                </select>
                            </div>
                            <div class="form-group" style="align-self: end;">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">
                                    <i class="fas fa-chart-line"></i> Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($report_type == 'financial'): ?>
                <!-- Financial Report -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                    <div class="card">
                        <div class="card-header">
                            <h3>Revenue Trends (Last 6 Months)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="300"></canvas>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3>Summary</h3>
                        </div>
                        <div class="card-body">
                            <?php
                            $total_revenue = array_sum($chart_data['revenue'] ?? []);
                            $avg_revenue = $total_revenue / max(count($chart_data['revenue'] ?? []), 1);
                            $total_transactions = array_sum($chart_data['transactions'] ?? []);
                            ?>
                            <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                                <div style="background: rgba(76, 201, 240, 0.1); padding: 15px; border-radius: 8px;">
                                    <p style="margin: 0; color: #fff; font-weight: 500;">Total Revenue</p>
                                    <strong style="font-size: 1.2rem; color: #4cc9f0;"><?= formatCurrency($total_revenue) ?></strong>
                                </div>
                                <div style="background: rgba(46, 204, 113, 0.1); padding: 15px; border-radius: 8px;">
                                    <p style="margin: 0; color: #fff; font-weight: 500;">Average Monthly</p>
                                    <strong style="font-size: 1.2rem; color: #2ecc71;"><?= formatCurrency($avg_revenue) ?></strong>
                                </div>
                                <div style="background: rgba(243, 156, 18, 0.1); padding: 15px; border-radius: 8px;">
                                    <p style="margin: 0; color: #fff; font-weight: 500;">Total Transactions</p>
                                    <strong style="font-size: 1.2rem; color: #f39c12;"><?= $total_transactions ?></strong>
                                </div>
                                <div style="background: rgba(231, 76, 60, 0.1); padding: 15px; border-radius: 8px;">
                                    <p style="margin: 0; color: #fff; font-weight: 500;">Growth Rate</p>
                                    <strong style="font-size: 1.2rem; color: #e74c3c;">
                                        <?php
                                        if (count($chart_data['revenue'] ?? []) >= 2) {
                                            $last_month = end($chart_data['revenue']);
                                            $prev_month = prev($chart_data['revenue']);
                                            $growth = $prev_month > 0 ? (($last_month - $prev_month) / $prev_month * 100) : 0;
                                            echo round($growth, 1) . '%';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Detailed Financial Report</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Month</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Revenue</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Transactions</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Average Transaction</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Growth</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($chart_data['labels'])):
                                        for ($i = 0; $i < count($chart_data['labels']); $i++):
                                            $growth = $i > 0 ? (($chart_data['revenue'][$i] - $chart_data['revenue'][$i-1]) / $chart_data['revenue'][$i-1] * 100) : 0;
                                            $avg_transaction = $chart_data['transactions'][$i] > 0 ? $chart_data['revenue'][$i] / $chart_data['transactions'][$i] : 0;
                                    ?>
                                    <tr>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= $chart_data['labels'][$i]; ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= formatCurrency($chart_data['revenue'][$i]); ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= $chart_data['transactions'][$i]; ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= formatCurrency($avg_transaction); ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <span style="color: <?= $growth >= 0 ? '#2ecc71' : '#e74c3c'; ?>; font-weight: 600;">
                                                <?= round($growth, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endfor; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php elseif ($report_type == 'occupancy'): ?>
                <!-- Occupancy Report -->
                <div class="card">
                    <div class="card-header">
                        <h3>Occupancy Rate & Bookings (Last 6 Months)</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="occupancyChart" height="300"></canvas>
                    </div>
                </div>

                <?php elseif ($report_type == 'revenue'): ?>
                <!-- Revenue by Category -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div class="card">
                        <div class="card-header">
                            <h3>Revenue by Room Category</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="300"></canvas>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3>Revenue Distribution</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="revenuePieChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Revenue by Category Details</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Category</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Revenue</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Bookings</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Average Revenue/Booking</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_revenue = array_sum($chart_data['revenue'] ?? []);
                                    foreach ($table_data as $row):
                                        $percentage = $total_revenue > 0 ? ($row['revenue'] / $total_revenue * 100) : 0;
                                        $avg_revenue = $row['bookings'] > 0 ? $row['revenue'] / $row['bookings'] : 0;
                                    ?>
                                    <tr>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= htmlspecialchars($row['category']) ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= formatCurrency($row['revenue']) ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= $row['bookings'] ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= formatCurrency($avg_revenue) ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <div style="background: rgba(76, 201, 240, 0.1); padding: 5px; border-radius: 5px; width: 100%; height: 10px; overflow: hidden;">
                                                <div style="background: var(--blue); height: 100%; width: <?= $percentage ?>%; color: white; text-align: center; font-size: 12px; line-height: 10px;">
                                                    <?= round($percentage, 1) ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php elseif ($report_type == 'customers'): ?>
                <!-- Customer Report -->
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
                    <div class="card">
                        <div class="card-header">
                            <h3>Customer Statistics</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                                <div style="background: rgba(76, 201, 240, 0.1); padding: 15px; border-radius: 8px;">
                                    <p style="margin: 0; color: #fff; font-weight: 500;">Total Customers</p>
                                    <strong style="font-size: 1.2rem; color: #4cc9f0;"><?= $stats['total_customers'] ?? 0 ?></strong>
                                </div>
                                <div style="background: rgba(46, 204, 113, 0.1); padding: 15px; border-radius: 8px;">
                                    <p style="margin: 0; color: #fff; font-weight: 500;">Total Revenue</p>
                                    <strong style="font-size: 1.2rem; color: #2ecc71;"><?= formatCurrency($stats['total_spent'] ?? 0) ?></strong>
                                </div>
                                <div style="background: rgba(243, 156, 18, 0.1); padding: 15px; border-radius: 8px;">
                                    <p style="margin: 0; color: #fff; font-weight: 500;">Average Spending</p>
                                    <strong style="font-size: 1.2rem; color: #f39c12;"><?= formatCurrency($stats['avg_spending'] ?? 0) ?></strong>
                                </div>
                                <div style="background: rgba(231, 76, 60, 0.1); padding: 15px; border-radius: 8px;">
                                    <p style="margin: 0; color: #fff; font-weight: 500;">Highest Spending</p>
                                    <strong style="font-size: 1.2rem; color: #e74c3c;"><?= formatCurrency($stats['max_spending'] ?? 0) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3>Top Customers</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr>
                                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Rank</th>
                                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Customer</th>
                                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Bookings</th>
                                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Total Spent</th>
                                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Average/Booking</th>
                                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Last Booking</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $rank = 1;
                                        while ($customer = $top_customers->fetch_assoc()):
                                            $avg_booking = $customer['total_bookings'] > 0 ? $customer['total_spent'] / $customer['total_bookings'] : 0;
                                            $last_booking = $conn->query("SELECT MAX(check_in) as last_booking FROM bookings WHERE user_id = (SELECT id FROM users WHERE email = '" . $customer['email'] . "')")->fetch_assoc();
                                        ?>
                                        <tr>
                                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">#<?= $rank++ ?></td>
                                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                <strong><?= htmlspecialchars($customer['full_name']) ?></strong><br>
                                                <small><?= htmlspecialchars($customer['email']) ?></small>
                                            </td>
                                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= $customer['total_bookings'] ?></td>
                                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= formatCurrency($customer['total_spent']) ?></td>
                                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= formatCurrency($avg_booking) ?></td>
                                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                <?= $last_booking['last_booking'] ? date('d M Y', strtotime($last_booking['last_booking'])) : 'Never' ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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
        
        // Generate charts based on report type
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($report_type == 'financial' && isset($chart_data['labels'])): ?>
            // Revenue Chart
            const ctx = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_data['labels'] ?? []) ?>,
                    datasets: [{
                        label: 'Revenue (IDR)',
                        data: <?= json_encode($chart_data['revenue'] ?? []) ?>,
                        borderColor: 'rgba(52, 152, 219, 1)',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 2,
                        fill: true
                    }, {
                        label: 'Transactions',
                        data: <?= json_encode($chart_data['transactions'] ?? []) ?>,
                        borderColor: 'rgba(46, 204, 113, 1)',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
            
            <?php elseif ($report_type == 'occupancy' && isset($chart_data['labels'])): ?>
            // Occupancy Chart
            const occupancyCtx = document.getElementById('occupancyChart').getContext('2d');
            new Chart(occupancyCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_data['labels'] ?? []) ?>,
                    datasets: [{
                        label: 'Bookings',
                        data: <?= json_encode($chart_data['bookings'] ?? []) ?>,
                        borderColor: 'rgba(52, 152, 219, 1)',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 2,
                        fill: true
                    }, {
                        label: 'Occupancy Rate (%)',
                        data: <?= json_encode($chart_data['occupancy'] ?? []) ?>,
                        borderColor: 'rgba(243, 156, 18, 1)',
                        backgroundColor: 'rgba(243, 156, 18, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#aaa'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                color: '#f39c12',
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        x: {
                            ticks: {
                                color: '#aaa'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '#fff'
                            }
                        }
                    }
                }
            });
            
            <?php elseif ($report_type == 'revenue' && isset($chart_data['labels'])): ?>
            // Category Bar Chart
            const ctxBar = document.getElementById('categoryChart').getContext('2d');
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_data['labels'] ?? []) ?>,
                    datasets: [{
                        label: 'Revenue by Category',
                        data: <?= json_encode($chart_data['revenue'] ?? []) ?>,
                        backgroundColor: [
                            'rgba(52, 152, 219, 0.8)',
                            'rgba(46, 204, 113, 0.8)',
                            'rgba(155, 89, 182, 0.8)',
                            'rgba(241, 196, 15, 0.8)',
                            'rgba(230, 126, 34, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                                }
                            }
                        }
                    }
                }
            });
            
            // Revenue Pie Chart
            const ctxPie = document.getElementById('revenuePieChart').getContext('2d');
            new Chart(ctxPie, {
                type: 'pie',
                data: {
                    labels: <?= json_encode($chart_data['labels'] ?? []) ?>,
                    datasets: [{
                        data: <?= json_encode($chart_data['revenue'] ?? []) ?>,
                        backgroundColor: [
                            'rgba(52, 152, 219, 0.8)',
                            'rgba(46, 204, 113, 0.8)',
                            'rgba(155, 89, 182, 0.8)',
                            'rgba(241, 196, 15, 0.8)',
                            'rgba(230, 126, 34, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#fff'
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
        
        function printReport() {
            window.print();
        }
        
        function loadReport(period) {
            window.location.href = 'reports.php?type=<?= $report_type ?>&period=' + period;
        }
        
        document.getElementById('dateRangeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                if (value) params.append(key, value);
            }
            window.location.search = params.toString();
        });
    </script>
</body>
</html>