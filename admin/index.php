<?php
require_once '../includes/config.php';
requireAdmin();

$page_title = 'Dashboard';

// Get statistics
$total_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms")->fetch_assoc()['count'];
$available_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'available'")->fetch_assoc()['count'];
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE MONTH(created_at) = MONTH(CURDATE())")->fetch_assoc()['count'];
$pending_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'pending'")->fetch_assoc()['count'];
$total_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch_assoc()['count'];
$monthly_revenue = $conn->query("SELECT SUM(final_price) as total FROM bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURDATE())")->fetch_assoc()['total'] ?? 0;

// Get recent bookings
$recent_bookings = $conn->query("SELECT b.*, u.full_name, r.room_number 
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    JOIN rooms r ON b.room_id = r.id 
    ORDER BY b.created_at DESC LIMIT 5");

// Get room status for chart
$room_status_data = $conn->query("SELECT status, COUNT(*) as count FROM rooms GROUP BY status");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($hotel_name) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --navy: #0a192f;
            --navy-light: #112240;
            --blue: #4cc9f0;
            --blue-dark: #3a86ff;
            --blue-hover: #3abde0;
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

        .nav-badge {
            background: #f72585;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: auto;
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

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -8px;
            background: #f72585;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
        }

        .last-login {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #aaa;
        }

        .content-area {
            padding: 30px;
        }

        /* === Stats Grid === */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(76, 201, 240, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: var(--blue);
            font-size: 24px;
        }

        .stat-info h3 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            color: white;
        }

        .stat-info p {
            color: #aaa;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
        }

        .stat-trend.up {
            color: #2ecc71;
        }

        .stat-trend.down {
            color: #e74c3c;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .card-title {
            font-size: 1.2rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 25px;
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        /* === Table === */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .data-table th {
            color: #aaa;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-primary { background: rgba(76, 201, 240, 0.2); color: var(--blue); }
        .badge-success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .badge-warning { background: rgba(243, 156, 18, 0.2); color: #f39c12; }
        .badge-danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .badge-info { background: rgba(52, 152, 219, 0.2); color: #3498db; }

        .btn {
            padding: 8px 16px;
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

        .btn-primary:hover {
            background: var(--blue-hover);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--blue);
            color: var(--blue);
        }

        .btn-outline:hover {
            background: rgba(76, 201, 240, 0.1);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.9rem;
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
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header-right {
                gap: 15px;
            }
            .last-login span {
                display: none;
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
                <a href="index.php" class="nav-item active">
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
                        <?php if ($pending_bookings > 0): ?>
                        <span class="nav-badge"><?= $pending_bookings ?></span>
                        <?php endif; ?>
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
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Dashboard</h1>
                </div>
                
                <div class="header-right">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    
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

            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-bed"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $total_rooms ?></h3>
                            <p>Total Rooms</p>
                            <div class="stat-trend up">
                                <i class="fas fa-arrow-up"></i>
                                <?= $available_rooms ?> available
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $total_bookings ?></h3>
                            <p>Monthly Bookings</p>
                            <div class="stat-trend <?= $pending_bookings > 0 ? 'down' : 'up' ?>">
                                <i class="fas fa-<?= $pending_bookings > 0 ? 'exclamation' : 'check' ?>-circle"></i>
                                <?= $pending_bookings ?> pending
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $total_customers ?></h3>
                            <p>Total Customers</p>
                            <div class="stat-trend up">
                                <i class="fas fa-chart-line"></i>
                                Active
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= formatCurrency($monthly_revenue) ?></h3>
                            <p>Monthly Revenue</p>
                            <div class="stat-trend up">
                                <i class="fas fa-arrow-up"></i>
                                This month
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Room Status Distribution</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="roomStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-line"></i> Recent Revenue</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history"></i> Recent Bookings</h3>
                        <div class="card-actions">
                            <a href="bookings.php" class="btn btn-primary">
                                <i class="fas fa-eye"></i> View All
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Booking Code</th>
                                        <th>Customer</th>
                                        <th>Room</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $recent_bookings->fetch_assoc()): 
                                        $status_class = strtolower($booking['booking_status']);
                                        $status_colors = [
                                            'pending' => 'warning',
                                            'confirmed' => 'primary',
                                            'checked_in' => 'success',
                                            'checked_out' => 'info',
                                            'cancelled' => 'danger'
                                        ];
                                        $color = $status_colors[$status_class] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($booking['booking_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($booking['full_name']) ?></td>
                                        <td><?= htmlspecialchars($booking['room_number']) ?></td>
                                        <td><?= date('d M Y', strtotime($booking['check_in'])) ?></td>
                                        <td><?= date('d M Y', strtotime($booking['check_out'])) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $color ?>">
                                                <?= ucfirst($booking['booking_status']) ?>
                                            </span>
                                        </td>
                                        <td><?= formatCurrency($booking['final_price']) ?></td>
                                        <td>
                                            <a href="bookings.php?action=view&id=<?= $booking['id'] ?>" class="btn btn-sm btn-outline">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-plus-circle" style="font-size: 48px; color: var(--blue); margin-bottom: 20px;"></i>
                            <h4 style="margin-bottom: 10px;">New Booking</h4>
                            <p style="color: #aaa; margin-bottom: 20px;">Create a new room reservation</p>
                            <a href="bookings.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Booking
                            </a>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-bed" style="font-size: 48px; color: var(--blue); margin-bottom: 20px;"></i>
                            <h4 style="margin-bottom: 10px;">Manage Rooms</h4>
                            <p style="color: #aaa; margin-bottom: 20px;">View and manage all rooms</p>
                            <a href="rooms.php" class="btn btn-primary">
                                <i class="fas fa-cog"></i> Manage Rooms
                            </a>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-file-invoice-dollar" style="font-size: 48px; color: var(--blue); margin-bottom: 20px;"></i>
                            <h4 style="margin-bottom: 10px;">Generate Report</h4>
                            <p style="color: #aaa; margin-bottom: 20px;">Create financial reports</p>
                            <a href="reports.php" class="btn btn-primary">
                                <i class="fas fa-download"></i> View Reports
                            </a>
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
        
        // Room Status Chart
        const roomStatusCtx = document.getElementById('roomStatusChart');
        const roomStatusData = {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: [
                    '#4cc9f0',
                    '#4361ee',
                    '#f72585',
                    '#7209b7',
                    '#3a0ca3'
                ],
                borderWidth: 2,
                borderColor: 'white'
            }]
        };
        
        <?php
        $temp_data = [];
        while ($status = $room_status_data->fetch_assoc()) {
            echo "roomStatusData.labels.push('" . ucfirst($status['status']) . "');";
            echo "roomStatusData.datasets[0].data.push(" . $status['count'] . ");";
        }
        ?>
        
        new Chart(roomStatusCtx, {
            type: 'doughnut',
            data: roomStatusData,
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
        
        // Revenue Chart (sample data)
        const revenueCtx = document.getElementById('revenueChart');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue (IDR)',
                    data: [12000000, 19000000, 15000000, 25000000, 22000000, 30000000],
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#aaa',
                            callback: function(value) {
                                return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                            }
                        },
                        grid: {
                            color: 'rgba(255,255,255,0.1)'
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
    </script>
</body>
</html>