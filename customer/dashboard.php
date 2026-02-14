<?php
// customer/dashboard.php - CUSTOMER DASHBOARD
session_start();
require_once '../includes/config.php';
requireCustomer();

$user_id = $_SESSION['user_id'];
$page_title = 'Dashboard';

// Get customer data
$customer_sql = "SELECT * FROM users WHERE id = ?";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("i", $user_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();
$customer_stmt->close();

// Get booking statistics
$stats_sql = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN booking_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN booking_status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
    SUM(CASE WHEN booking_status = 'checked_out' THEN 1 ELSE 0 END) as checked_out,
    SUM(final_price) as total_spent
    FROM bookings 
    WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

// Get upcoming bookings
$upcoming_sql = "SELECT b.*, r.room_number, rc.name as room_type 
                 FROM bookings b
                 JOIN rooms r ON b.room_id = r.id
                 JOIN room_categories rc ON r.category_id = rc.id
                 WHERE b.user_id = ? AND b.check_in >= CURDATE() AND b.booking_status = 'confirmed'
                 ORDER BY b.check_in ASC
                 LIMIT 3";
$upcoming_stmt = $conn->prepare($upcoming_sql);
$upcoming_stmt->bind_param("i", $user_id);
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();

// Get recent bookings
$recent_sql = "SELECT b.*, r.room_number, rc.name as room_type 
               FROM bookings b
               JOIN rooms r ON b.room_id = r.id
               JOIN room_categories rc ON r.category_id = rc.id
               WHERE b.user_id = ?
               ORDER BY b.created_at DESC
               LIMIT 5";
$recent_stmt = $conn->prepare($recent_sql);
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();
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
            overflow-y: auto;
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
        }

        .nav-group {
            margin-bottom: 10px;
        }

        .nav-label {
            padding: 15px 25px 8px;
            color: #777;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #ccc;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
            border-left-color: var(--blue);
        }

        .nav-item.active {
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
            border-left-color: var(--blue);
        }

        .nav-item i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }

        .nav-divider {
            height: 1px;
            background: rgba(255,255,255,0.05);
            margin: 15px 25px;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
            position: absolute;
            bottom: 0;
            width: 100%;
            background: var(--navy);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
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

        .page-title h1 {
            font-size: 1.8rem;
            color: white;
        }

        .page-title p {
            color: #aaa;
            font-size: 0.9rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .last-login {
            color: #aaa;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

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

        /* === Content Area === */
        .content-area {
            padding: 30px;
        }

        /* === Welcome Card === */
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
        }

        .welcome-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-text h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .welcome-text p {
            opacity: 0.9;
            max-width: 600px;
        }

        .welcome-buttons {
            display: flex;
            gap: 15px;
        }

        /* === Stats Cards === */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-color: var(--blue);
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(76, 201, 240, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--blue);
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }

        .stat-label {
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

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .card-header h3 {
            font-size: 1.3rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
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

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-primary:hover {
            background: #3abde0;
            transform: translateY(-2px);
        }

        .btn-secondary:hover {
            background: rgba(76, 201, 240, 0.1);
            transform: translateY(-2px);
        }

        /* === Tables === */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            color: var(--blue);
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: #ccc;
            font-size: 14px;
        }

        tr:hover td {
            background: rgba(76, 201, 240, 0.05);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .status-warning { background: rgba(243, 156, 18, 0.2); color: #f39c12; }
        .status-danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .status-info { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        .status-purple { background: rgba(155, 89, 182, 0.2); color: #9b59b6; }

        /* === Empty State === */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #aaa;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
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
            .welcome-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            .welcome-buttons {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .top-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-right {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* === Room Cards === */
        .room-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .room-item {
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s;
        }

        .room-item:hover {
            border-color: var(--blue);
            transform: translateY(-3px);
        }

        .room-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .room-number {
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
        }

        .room-type {
            color: #aaa;
            font-size: 0.9rem;
        }

        .booking-date {
            color: #aaa;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }

        .booking-status {
            margin-top: 10px;
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
                <a href="dashboard.php" class="nav-item active">
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
                    <div class="page-title">
                        <h1>Welcome, <?= htmlspecialchars($customer['full_name']) ?>!</h1>
                        <p><?= htmlspecialchars($hotel_name) ?> Customer Dashboard</p>
                    </div>
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
                <!-- Welcome Card -->
                <div class="welcome-card">
                    <div class="welcome-content">
                        <div class="welcome-text">
                            <h2>Welcome to Your Dashboard</h2>
                            <p>Manage your bookings, view upcoming stays, and explore our exclusive services all in one place.</p>
                        </div>
                        <div class="welcome-buttons">
                            <a href="new-booking.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> New Booking
                            </a>
                            <a href="rooms.php" class="btn" style="background: white; color: #764ba2;">
                                <i class="fas fa-bed"></i> View Rooms
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-value"><?= $stats['total_bookings'] ?? 0 ?></div>
                        <div class="stat-label">Total Bookings</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?= $stats['confirmed'] ?? 0 ?></div>
                        <div class="stat-label">Confirmed</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?= $stats['pending'] ?? 0 ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-value"><?= formatCurrency($stats['total_spent'] ?? 0) ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                </div>

                <div class="content-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
                    <!-- Upcoming Bookings -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-day"></i> Upcoming Bookings</h3>
                            <a href="bookings.php?date=upcoming" class="btn btn-sm btn-secondary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if ($upcoming_result->num_rows > 0): ?>
                            <div class="room-list">
                                <?php while ($booking = $upcoming_result->fetch_assoc()): 
                                    $nights = calculateNights($booking['check_in'], $booking['check_out']);
                                ?>
                                <div class="room-item">
                                    <div class="room-header">
                                        <div>
                                            <div class="room-number">Room <?= $booking['room_number'] ?></div>
                                            <div class="room-type"><?= $booking['room_type'] ?></div>
                                        </div>
                                        <div style="color: var(--blue); font-weight: 600;">
                                            <?= formatCurrency($booking['final_price']) ?>
                                        </div>
                                    </div>
                                    <div class="booking-date">
                                        <i class="fas fa-calendar"></i> 
                                        <?= date('d M Y', strtotime($booking['check_in'])) ?> - 
                                        <?= date('d M Y', strtotime($booking['check_out'])) ?>
                                    </div>
                                    <div class="booking-status">
                                        <span class="status-badge status-success">Confirmed</span>
                                        <span style="color: #aaa; font-size: 0.9rem; margin-left: 10px;">
                                            <?= $nights ?> nights
                                        </span>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-plus fa-2x"></i>
                                <h3 style="color: #aaa; margin: 15px 0 10px 0;">No Upcoming Bookings</h3>
                                <p style="color: #777; margin-bottom: 20px;">You don't have any upcoming bookings.</p>
                                <a href="new-booking.php" class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i> Book Now
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Bookings -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-history"></i> Recent Bookings</h3>
                            <a href="bookings.php" class="btn btn-sm btn-secondary">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Room</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($booking = $recent_result->fetch_assoc()): 
                                            $status_color = [
                                                'confirmed' => 'success',
                                                'pending' => 'warning',
                                                'cancelled' => 'danger',
                                                'checked_in' => 'info',
                                                'checked_out' => 'purple'
                                            ][$booking['booking_status']] ?? 'info';
                                        ?>
                                        <tr>
                                            <td>#<?= str_pad($booking['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                            <td>
                                                <strong style="color: white;">Room <?= $booking['room_number'] ?></strong><br>
                                                <small style="color: #aaa;"><?= $booking['room_type'] ?></small>
                                            </td>
                                            <td>
                                                <div style="color: white;"><?= date('d M', strtotime($booking['check_in'])) ?></div>
                                                <small style="color: #aaa;"><?= date('Y', strtotime($booking['check_in'])) ?></small>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $status_color ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $booking['booking_status'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <a href="new-booking.php" class="btn btn-primary" style="padding: 20px; text-align: center; justify-content: center;">
                                <i class="fas fa-calendar-plus fa-2x"></i>
                                <div style="margin-top: 10px; font-size: 1.1rem;">New Booking</div>
                            </a>
                            
                            <a href="rooms.php" class="btn btn-secondary" style="padding: 20px; text-align: center; justify-content: center;">
                                <i class="fas fa-bed fa-2x"></i>
                                <div style="margin-top: 10px; font-size: 1.1rem;">Browse Rooms</div>
                            </a>
                            
                            <a href="profile.php" class="btn btn-secondary" style="padding: 20px; text-align: center; justify-content: center;">
                                <i class="fas fa-user-edit fa-2x"></i>
                                <div style="margin-top: 10px; font-size: 1.1rem;">Update Profile</div>
                            </a>
                            
                            <a href="services.php" class="btn btn-secondary" style="padding: 20px; text-align: center; justify-content: center;">
                                <i class="fas fa-concierge-bell fa-2x"></i>
                                <div style="margin-top: 10px; font-size: 1.1rem;">Hotel Services</div>
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

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.getElementById('menuToggle');
            
            if (window.innerWidth <= 992 && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Update last login time
        function updateTime() {
            const timeEl = document.querySelector('.last-login');
            if (timeEl) {
                const timeText = timeEl.textContent;
                if (timeText.includes('Last Login:')) {
                    const now = new Date();
                    const hours = now.getHours().toString().padStart(2, '0');
                    const minutes = now.getMinutes().toString().padStart(2, '0');
                    timeEl.innerHTML = `<i class="fas fa-clock"></i> Current Time: ${hours}:${minutes}`;
                }
            }
        }

        // Update time every minute
        setInterval(updateTime, 60000);

        // Initialize time on load
        document.addEventListener('DOMContentLoaded', updateTime);

        // Add animation to stats cards on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Animate stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>
<?php
// Close database connections
$upcoming_stmt->close();
$recent_stmt->close();
if (isset($conn)) {
    $conn->close();
}
?>