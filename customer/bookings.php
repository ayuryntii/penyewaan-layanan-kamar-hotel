<?php
// customer/bookings.php - MY BOOKINGS PAGE
session_start();
require_once '../includes/config.php';
requireCustomer();

$user_id = $_SESSION['user_id'];
$page_title = 'My Bookings';

// Get customer data for sidebar
$customer_sql = "SELECT * FROM users WHERE id = ?";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("i", $user_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();
$customer_stmt->close();

// Filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query with filters
$query = "SELECT b.*, r.room_number, rc.name as room_type, rc.base_price 
          FROM bookings b
          JOIN rooms r ON b.room_id = r.id
          JOIN room_categories rc ON r.category_id = rc.id
          WHERE b.user_id = ?";
$params = ["i", $user_id];

if ($status_filter && $status_filter !== 'all') {
    $query .= " AND b.booking_status = ?";
    $params[0] .= "s";
    $params[] = $status_filter;
}

if ($date_filter) {
    if ($date_filter === 'upcoming') {
        $query .= " AND b.check_in >= CURDATE()";
    } elseif ($date_filter === 'past') {
        $query .= " AND b.check_in < CURDATE()";
    }
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);

// Bind parameters dynamically
if (count($params) > 1) {
    $stmt->bind_param(...$params);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

// Get booking statistics for filter
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN booking_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN booking_status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
    SUM(CASE WHEN booking_status = 'checked_out' THEN 1 ELSE 0 END) as checked_out
    FROM bookings WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .card-filters {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .form-control {
            width: 200px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(76, 201, 240, 0.15);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z' fill='%23ccc'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px;
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

        .btn-danger {
            background: #ef233c;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-info {
            background: #17a2b8;
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

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: rgba(76, 201, 240, 0.2);
            color: var(--blue);
            display: inline-block;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .status-warning { background: rgba(243, 156, 18, 0.2); color: #f39c12; }
        .status-danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .status-info { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        .status-purple { background: rgba(155, 89, 182, 0.2); color: #9b59b6; }

        /* === Filter Stats === */
        .filter-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-stat-item {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 15px;
            border: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .filter-stat-item:hover {
            border-color: var(--blue);
            transform: translateY(-2px);
        }

        .filter-stat-item.active {
            border-color: var(--blue);
            background: rgba(76, 201, 240, 0.1);
        }

        .filter-stat-count {
            font-size: 24px;
            font-weight: bold;
            color: var(--blue);
        }

        .filter-stat-label {
            color: #aaa;
            font-size: 14px;
        }

        /* === Table Styles === */
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

        /* === Empty State === */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
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
            .card-filters {
                flex-direction: column;
                align-items: stretch;
            }
            .form-control {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            th, td {
                padding: 10px 8px;
                font-size: 12px;
            }
            
            .btn {
                padding: 8px 15px;
                font-size: 12px;
            }
            
            .filter-stats {
                flex-direction: column;
            }
            
            .filter-stat-item {
                width: 100%;
            }
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

        /* === Booking Details Modal === */
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
                    <a href="bookings.php" class="nav-item active">
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
                <!-- Filter Statistics -->
                <div class="filter-stats">
                    <a href="?status=all" class="filter-stat-item <?= !$status_filter ? 'active' : '' ?>">
                        <div class="filter-stat-count"><?= $stats['total'] ?? 0 ?></div>
                        <div class="filter-stat-label">All Bookings</div>
                    </a>
                    <a href="?status=confirmed" class="filter-stat-item <?= $status_filter === 'confirmed' ? 'active' : '' ?>">
                        <div class="filter-stat-count"><?= $stats['confirmed'] ?? 0 ?></div>
                        <div class="filter-stat-label">Confirmed</div>
                    </a>
                    <a href="?status=pending" class="filter-stat-item <?= $status_filter === 'pending' ? 'active' : '' ?>">
                        <div class="filter-stat-count"><?= $stats['pending'] ?? 0 ?></div>
                        <div class="filter-stat-label">Pending</div>
                    </a>
                    <a href="?status=cancelled" class="filter-stat-item <?= $status_filter === 'cancelled' ? 'active' : '' ?>">
                        <div class="filter-stat-count"><?= $stats['cancelled'] ?? 0 ?></div>
                        <div class="filter-stat-label">Cancelled</div>
                    </a>
                    <a href="?status=checked_in" class="filter-stat-item <?= $status_filter === 'checked_in' ? 'active' : '' ?>">
                        <div class="filter-stat-count"><?= $stats['checked_in'] ?? 0 ?></div>
                        <div class="filter-stat-label">Checked In</div>
                    </a>
                    <a href="?status=checked_out" class="filter-stat-item <?= $status_filter === 'checked_out' ? 'active' : '' ?>">
                        <div class="filter-stat-count"><?= $stats['checked_out'] ?? 0 ?></div>
                        <div class="filter-stat-label">Checked Out</div>
                    </a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Booking History</h3>
                        <div class="card-filters">
                            <select id="dateFilter" class="form-control" onchange="filterByDate(this.value)">
                                <option value="">All Dates</option>
                                <option value="upcoming" <?= $date_filter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                <option value="past" <?= $date_filter === 'past' ? 'selected' : '' ?>>Past</option>
                            </select>
                            <a href="new-booking.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> New Booking
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>Room</th>
                                        <th>Check In/Out</th>
                                        <th>Nights</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $result->fetch_assoc()): 
                                        $nights = calculateNights($booking['check_in'], $booking['check_out']);
                                        $booking_status = $booking['booking_status'];
                                        $payment_status = $booking['payment_status'];
                                        $status_colors = [
                                            'confirmed' => 'success',
                                            'pending' => 'warning',
                                            'cancelled' => 'danger',
                                            'checked_in' => 'info',
                                            'checked_out' => 'primary'
                                        ];
                                        $status_color = $status_colors[$booking_status] ?? 'info';
                                        $payment_colors = [
                                            'paid' => 'success',
                                            'pending' => 'warning',
                                            'partial' => 'info',
                                            'failed' => 'danger',
                                            'refunded' => 'secondary'
                                        ];
                                        $payment_color = $payment_colors[$payment_status] ?? 'info';
                                    ?>
                                    <tr>
                                        <td>#BK<?= str_pad($booking['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                        <td>
                                            <strong style="color: white;">Room <?= $booking['room_number'] ?></strong><br>
                                            <small style="color: #aaa;"><?= $booking['room_type'] ?></small>
                                        </td>
                                        <td>
                                            <div>
                                                <div style="color: white;"><?= date('d M Y', strtotime($booking['check_in'])) ?></div>
                                                <small style="color: #aaa;">to <?= date('d M Y', strtotime($booking['check_out'])) ?></small>
                                            </div>
                                        </td>
                                        <td><?= $nights ?> nights</td>
                                        <td>
                                            <span class="status-badge status-<?= $status_color ?>">
                                                <?= ucfirst(str_replace('_', ' ', $booking_status)) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $payment_color ?>">
                                                <?= ucfirst($payment_status) ?>
                                            </span>
                                        </td>
                                        <td style="color: white; font-weight: 600;">
                                            <?= formatCurrency($booking['final_price']) ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <button onclick="viewBookingDetails(<?= $booking['id'] ?>)" class="btn btn-sm btn-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($booking_status == 'pending'): ?>
                                                <button onclick="cancelBooking(<?= $booking['id'] ?>)" class="btn btn-sm btn-danger" title="Cancel">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($payment_status == 'pending'): ?>
                                                <a href="payment.php?booking_id=<?= $booking['id'] ?>" class="btn btn-sm btn-success" title="Make Payment">
                                                    <i class="fas fa-credit-card"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times fa-3x"></i>
                            <h3 style="color: #aaa; margin: 15px 0 10px 0;">No Bookings Found</h3>
                            <p style="color: #777; margin-bottom: 20px;"><?= $status_filter ? 'No bookings match your filter.' : 'You haven\'t made any bookings yet.' ?></p>
                            <a href="new-booking.php" class="btn btn-primary">
                                <i class="fas fa-calendar-plus"></i> Make Your First Booking
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Booking Details Modal -->
    <div class="modal" id="bookingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Booking Details</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="bookingModalBody">
                <!-- Booking details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Filter by date
        function filterByDate(value) {
            const url = new URL(window.location);
            if (value) {
                url.searchParams.set('date', value);
            } else {
                url.searchParams.delete('date');
            }
            window.location.href = url.toString();
        }

        // View booking details
        function viewBookingDetails(id) {
            fetch(`booking-details.php?id=${id}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('bookingModalBody').innerHTML = html;
                    document.getElementById('modalTitle').textContent = 'Booking Details';
                    document.getElementById('bookingModal').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('bookingModalBody').innerHTML = 
                        '<div style="padding: 20px; color: #ff6b6b;">Failed to load booking details.</div>';
                    document.getElementById('modalTitle').textContent = 'Error';
                    document.getElementById('bookingModal').style.display = 'flex';
                });
        }

        // Cancel booking
        function cancelBooking(id) {
            if (confirm('Are you sure you want to cancel this booking?')) {
                fetch(`cancel-booking.php?id=${id}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to cancel booking.');
                });
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('bookingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Initialize filter stats
        document.addEventListener('DOMContentLoaded', function() {
            const filterItems = document.querySelectorAll('.filter-stat-item');
            filterItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = this.getAttribute('href');
                    window.location.href = url;
                });
            });
        });
    </script>

    <script>
function viewBookingDetails(id) {
    // Pastikan element modal & container ada
    const modal = document.getElementById("bookingDetailsModal");
    const body  = document.getElementById("bookingDetailsContent");

    if (!modal || !body) {
        alert("Modal element not found. Pastikan bookingDetailsModal dan bookingDetailsContent ada.");
        return;
    }

    // loading
    body.innerHTML = "<div style='padding:16px;color:white;'>Loading...</div>";
    modal.style.display = "flex";

    fetch("booking-details.php?id=" + id)
        .then(res => res.text())
        .then(html => {
            body.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            body.innerHTML = "<div style='padding:16px;color:white;'>Failed to load booking details</div>";
        });
}

// close modal
function closeBookingDetails() {
    const modal = document.getElementById("bookingDetailsModal");
    if (modal) modal.style.display = "none";
}

document.addEventListener("click", function(e){
    const modal = document.getElementById("bookingDetailsModal");
    if (!modal) return;

    if (e.target === modal) {
        modal.style.display = "none";
    }
});

</script>

<!-- Booking Details Modal -->
<div id="bookingDetailsModal" style="
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.65);
    z-index:9999;
    justify-content:center;
    align-items:center;
    padding:20px;
">
    <div style="
        width:100%;
        max-width:720px;
        background:rgba(20,30,50,0.95);
        border:1px solid rgba(255,255,255,0.1);
        border-radius:16px;
        overflow:hidden;
        box-shadow:0 20px 50px rgba(0,0,0,0.55);
    ">
        <div style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:14px 18px;
            border-bottom:1px solid rgba(255,255,255,0.08);
            color:white;
        ">
            <strong>Booking Details</strong>
            <button onclick="closeBookingDetails()" style="
                background:transparent;
                border:none;
                color:white;
                font-size:22px;
                cursor:pointer;
            ">&times;</button>
        </div>

        <div id="bookingDetailsContent"></div>
    </div>
</div>

</body>
</html>
<?php
// Close database connection
$stmt->close();
if (isset($conn)) {
    $conn->close();
}
?>