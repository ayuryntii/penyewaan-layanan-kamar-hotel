<?php
// customer/new-booking.php - NEW BOOKING PAGE
session_start();
require_once '../includes/config.php';
requireCustomer();

$user_id = $_SESSION['user_id'];
$page_title = 'New Booking';

// Get customer data
$customer_sql = "SELECT * FROM users WHERE id = ?";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("i", $user_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();
$customer_stmt->close();

// Check for room availability if dates are provided
$check_in = $_GET['check_in'] ?? '';
$check_out = $_GET['check_out'] ?? '';

// Get available rooms based on dates if provided
$rooms_condition = "WHERE r.status = 'available'";
$rooms_params = [];

if ($check_in && $check_out) {
    // Validate dates
    $today = date('Y-m-d');
    if ($check_in < $today) {
        $_SESSION['error'] = 'Check-in date cannot be in the past.';
        header('Location: new-booking.php');
        exit();
    }
    
    if ($check_out <= $check_in) {
        $_SESSION['error'] = 'Check-out date must be after check-in date.';
        header('Location: new-booking.php');
        exit();
    }
    
    // Get rooms that are not booked for these dates
    $rooms_condition = "WHERE r.status = 'available' 
                       AND r.id NOT IN (
                           SELECT room_id FROM bookings 
                           WHERE booking_status NOT IN ('cancelled', 'checked_out')
                           AND (
                               (check_in <= ? AND check_out >= ?) OR
                               (check_in <= ? AND check_out >= ?) OR
                               (check_in >= ? AND check_out <= ?)
                           )
                       )";
    $rooms_params = [$check_out, $check_in, $check_in, $check_out, $check_in, $check_out];
}

// Get available rooms
$rooms_sql = "SELECT r.*, rc.name as category_name, rc.base_price, rc.max_capacity, 
                     rc.amenities, rc.description, r.view_type, r.bed_type
              FROM rooms r
              JOIN room_categories rc ON r.category_id = rc.id
              $rooms_condition
              ORDER BY rc.base_price ASC";

$rooms_stmt = $conn->prepare($rooms_sql);
if (!empty($rooms_params)) {
    $rooms_stmt->bind_param(str_repeat('s', count($rooms_params)), ...$rooms_params);
}
$rooms_stmt->execute();
$rooms_result = $rooms_stmt->get_result();

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_booking'])) {
    $room_id = intval($_POST['room_id']);
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $special_requests = trim($_POST['special_requests'] ?? '');
    
    // Validate dates
    $today = date('Y-m-d');
    if ($check_in < $today) {
        $_SESSION['error'] = 'Check-in date cannot be in the past.';
        header('Location: new-booking.php');
        exit();
    }
    
    if ($check_out <= $check_in) {
        $_SESSION['error'] = 'Check-out date must be after check-in date.';
        header('Location: new-booking.php');
        exit();
    }
    
    // Check room availability
    if (!checkRoomAvailability($conn, $room_id, $check_in, $check_out)) {
        $_SESSION['error'] = 'Selected room is not available for the chosen dates. Please select another room.';
        header('Location: new-booking.php');
        exit();
    }
    
    // Get room details including max capacity
    $room_sql = "SELECT rc.base_price, rc.max_capacity 
                 FROM rooms r
                 JOIN room_categories rc ON r.category_id = rc.id 
                 WHERE r.id = ?";
    $room_stmt = $conn->prepare($room_sql);
    $room_stmt->bind_param("i", $room_id);
    $room_stmt->execute();
    $room_result = $room_stmt->get_result();
    $room = $room_result->fetch_assoc();
    $room_price = $room['base_price'];
    $max_capacity = $room['max_capacity'];
    $room_stmt->close();
    
    // Set adults = max_capacity, children = 0
    $adults = $max_capacity;
    $children = 0;
    
    // Calculate nights and price
    $nights = calculateNights($check_in, $check_out);
    
    // Calculate total price
    $total_price = $room_price * $nights;
    $final_price = $total_price; // No discount for now
    
    // Generate booking code
    $booking_code = 'BK' . date('Ymd') . strtoupper(substr(uniqid(), 7, 4));
    
    // Insert booking
    $booking_sql = "INSERT INTO bookings (booking_code, user_id, room_id, check_in, check_out, 
                    total_nights, adults, children, total_price, final_price, special_requests, 
                    booking_status, payment_status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())";
    
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("siissiiidds", $booking_code, $user_id, $room_id, $check_in, $check_out, 
                             $nights, $adults, $children, $total_price, $final_price, $special_requests);
    
    if ($booking_stmt->execute()) {
        $booking_id = $conn->insert_id;
        
        // Update room status to reserved
        $update_room_sql = "UPDATE rooms SET status = 'reserved' WHERE id = ?";
        $update_stmt = $conn->prepare($update_room_sql);
        $update_stmt->bind_param("i", $room_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        $_SESSION['success'] = 'Booking created successfully! Your booking ID is: ' . $booking_code;
        $_SESSION['new_booking_id'] = $booking_id;
        header('Location: booking-success.php');
        exit();
    } else {
        $_SESSION['error'] = 'Failed to create booking. Please try again. Error: ' . $conn->error;
    }
    $booking_stmt->close();
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .card-title {
            font-size: 1.3rem;
            color: white;
        }

        .card-actions {
            display: flex;
            gap: 10px;
        }

        .card-body {
            padding: 25px;
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
            margin-bottom: 20px;
        }

        /* === Buttons === */
        .btn {
            padding: 12px 25px;
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

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
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

        .alert-info {
            background: rgba(76, 201, 240, 0.2);
            border: 1px solid rgba(76, 201, 240, 0.3);
            color: var(--blue);
        }

        /* === Room Grid === */
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }

        .room-card {
            background: rgba(255,255,255,0.03);
            border-radius: 15px;
            padding: 25px;
            border: 2px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }

        .room-card:hover {
            border-color: var(--blue);
            transform: translateY(-5px);
            background: rgba(76, 201, 240, 0.05);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .room-card.selected {
            border-color: var(--blue);
            background: rgba(76, 201, 240, 0.1);
            box-shadow: 0 0 0 3px rgba(76, 201, 240, 0.3);
        }

        .room-card.selected::before {
            content: '✓ SELECTED';
            position: absolute;
            top: -12px;
            right: 20px;
            background: var(--blue);
            color: var(--navy);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .room-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }

        .room-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }

        .room-type {
            color: var(--blue);
            font-size: 1rem;
            font-weight: 600;
        }

        .room-price {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--blue);
            text-align: right;
        }

        .room-price-per {
            font-size: 0.9rem;
            color: #aaa;
            font-weight: 500;
            display: block;
        }

        .room-features {
            margin: 20px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .feature-tag {
            background: rgba(255,255,255,0.08);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #ccc;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .room-card:hover .feature-tag {
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
        }

        .room-description {
            color: #ddd;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .room-amenities {
            color: #aaa;
            font-size: 0.9rem;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .room-amenities strong {
            color: var(--blue);
        }

        /* === Price Summary === */
        .price-summary {
            background: rgba(76, 201, 240, 0.1);
            padding: 30px;
            border-radius: 15px;
            margin: 40px 0;
            border: 2px solid rgba(76, 201, 240, 0.2);
        }

        .price-summary h4 {
            color: var(--blue);
            margin-bottom: 25px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .price-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.3rem;
            color: white;
            padding-top: 20px;
            margin-top: 10px;
            border-top: 2px solid rgba(255,255,255,0.1);
        }

        .price-label {
            color: #ccc;
            font-weight: 500;
        }

        .price-value {
            color: white;
            font-weight: 500;
        }

        .total-price {
            color: var(--blue);
            font-size: 1.8rem;
            font-weight: 800;
        }

        .price-note {
            margin-top: 20px;
            padding: 15px;
            background: rgba(76, 201, 240, 0.05);
            border-radius: 10px;
            border-left: 4px solid var(--blue);
            font-size: 0.9rem;
            color: #ccc;
        }

        /* === Step Indicator === */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }

        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(255,255,255,0.1);
            z-index: 1;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-weight: 700;
            margin-bottom: 12px;
            transition: all 0.3s;
            border: 3px solid transparent;
        }

        .step.active .step-circle {
            background: var(--blue);
            color: var(--navy);
            border-color: rgba(76, 201, 240, 0.3);
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(76, 201, 240, 0.4);
        }

        .step-label {
            color: #aaa;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .step.active .step-label {
            color: var(--blue);
            font-weight: 600;
        }

        /* === Empty State === */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 4rem;
            color: rgba(255,255,255,0.1);
            margin-bottom: 25px;
        }

        .empty-state h3 {
            color: #aaa;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #777;
            font-size: 1rem;
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* === Responsive === */
        @media (max-width: 1200px) {
            .room-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

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
            .room-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .step-indicator {
                flex-direction: column;
                gap: 25px;
                align-items: flex-start;
            }
            
            .step-indicator::before {
                display: none;
            }
            
            .step {
                flex-direction: row;
                gap: 15px;
                width: 100%;
            }
            
            .step-circle {
                margin-bottom: 0;
            }
            
            .step-label {
                font-size: 1rem;
            }
            
            .header-right {
                flex-direction: column;
                gap: 15px;
                align-items: flex-end;
            }
        }

        @media (max-width: 576px) {
            .content-area {
                padding: 20px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .room-card {
                padding: 20px;
            }
            
            .price-summary {
                padding: 20px;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 13px;
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
                    <a href="new-booking.php" class="nav-item active">
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
                    <h1 style="color: var(--blue); font-size: 1.8rem;"><?= htmlspecialchars($page_title) ?></h1>
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
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active" id="step1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Select Dates</div>
                    </div>
                    <div class="step" id="step2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Choose Room</div>
                    </div>
                    <div class="step" id="step3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Confirmation</div>
                    </div>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
                </div>
                <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                </div>
                <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-calendar-plus" style="color: var(--blue);"></i>
                            Create New Booking
                        </div>
                        <div class="card-actions">
                            <a href="rooms.php" class="btn btn-secondary">
                                <i class="fas fa-bed"></i> View All Rooms
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="bookingForm">
                            <input type="hidden" name="create_booking" value="1">
                            <input type="hidden" name="adults" id="adultsField" value="">
                            <input type="hidden" name="children" id="childrenField" value="0">
                            
                            <!-- Step 1: Select Dates -->
                            <div id="step1Content">
                                <h3 style="color: var(--blue); margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
                                    <i class="fas fa-calendar-alt"></i> Select Dates
                                </h3>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-sign-in-alt"></i> Check-in Date *
                                        </label>
                                        <input type="date" name="check_in" id="check_in" class="form-control" required 
                                               min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($check_in) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-sign-out-alt"></i> Check-out Date *
                                        </label>
                                        <input type="date" name="check_out" id="check_out" class="form-control" required 
                                               value="<?= htmlspecialchars($check_out) ?>">
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <div>
                                        <strong>Note:</strong> Number of guests will be automatically determined based on the room capacity you select.
                                    </div>
                                </div>
                                
                                <div style="text-align: center; margin-top: 40px; padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.1);">
                                    <button type="button" class="btn btn-primary" onclick="goToStep2()" style="padding: 15px 50px; font-size: 16px;">
                                        <i class="fas fa-arrow-right"></i> Check Availability
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Step 2: Select Room -->
                            <div id="step2Content" style="display: none;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                                    <h3 style="color: var(--blue); margin: 0; display: flex; align-items: center; gap: 12px;">
                                        <i class="fas fa-bed"></i> Select Your Room
                                    </h3>
                                    <button type="button" class="btn btn-secondary" onclick="goToStep1()">
                                        <i class="fas fa-arrow-left"></i> Change Dates
                                    </button>
                                </div>
                                
                                <?php if ($rooms_result->num_rows > 0): ?>
                                <div class="room-grid" id="roomSelection">
                                    <?php while ($room = $rooms_result->fetch_assoc()): 
                                        $room_id = $room['id'];
                                        $room_number = $room['room_number'];
                                        $category = $room['category_name'];
                                        $price = $room['base_price'];
                                        $capacity = $room['max_capacity'];
                                        $amenities = $room['amenities'] ?? 'WiFi, TV, AC, Mini Bar';
                                        $view_type = ucfirst($room['view_type'] ?? 'City');
                                        $bed_type = ucfirst($room['bed_type'] ?? 'Double');
                                        $description = $room['description'] ?? 'Comfortable room with modern amenities';
                                    ?>
                                    <div class="room-card" onclick="selectRoom(<?= $room_id ?>, <?= $price ?>, '<?= htmlspecialchars($category) ?>', '<?= htmlspecialchars($view_type) ?>', <?= $capacity ?>)">
                                        <div class="room-header">
                                            <div>
                                                <div class="room-number">Room <?= $room_number ?></div>
                                                <div class="room-type"><?= $category ?></div>
                                            </div>
                                            <div class="room-price">
                                                <?= formatCurrency($price) ?>
                                                <span class="room-price-per">per night</span>
                                            </div>
                                        </div>
                                        
                                        <div class="room-features">
                                            <span class="feature-tag">
                                                <i class="fas fa-user-friends"></i> Max <?= $capacity ?> Person<?= $capacity > 1 ? 's' : '' ?>
                                            </span>
                                            <span class="feature-tag">
                                                <i class="fas fa-bed"></i> <?= $bed_type ?> Bed
                                            </span>
                                            <span class="feature-tag">
                                                <i class="fas fa-eye"></i> <?= $view_type ?> View
                                            </span>
                                        </div>
                                        
                                        <div class="room-description">
                                            <?= htmlspecialchars($description) ?>
                                        </div>
                                        
                                        <div class="room-amenities">
                                            <strong><i class="fas fa-star"></i> Amenities:</strong> <?= htmlspecialchars($amenities) ?>
                                        </div>
                                        
                                        <input type="radio" name="room_id" value="<?= $room_id ?>" 
                                               style="display: none;" required>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                
                                <div id="priceSummary" class="price-summary" style="display: none;">
                                    <h4>
                                        <i class="fas fa-receipt"></i> Price Summary
                                    </h4>
                                    <div id="summaryContent"></div>
                                    <div class="price-note">
                                        <i class="fas fa-info-circle"></i> 
                                        Number of guests is automatically set to room capacity. All prices include tax and service fees.
                                    </div>
                                </div>
                                
                                <div style="margin-top: 40px;">
                                    <h4 style="color: var(--blue); margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                                        <i class="fas fa-comment-alt"></i> Special Requests (Optional)
                                    </h4>
                                    <textarea name="special_requests" class="form-control" rows="5" 
                                              placeholder="Any special requests or notes for your stay (e.g., early check-in, dietary requirements, room preferences, birthday celebration, etc.)..."></textarea>
                                </div>
                                
                                <div style="text-align: center; margin-top: 50px; padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.1);">
                                    <button type="submit" class="btn btn-primary" style="padding: 18px 60px; font-size: 18px; font-weight: 700;">
                                        <i class="fas fa-check-circle"></i> Confirm Booking
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="goToStep1()" style="margin-left: 20px; padding: 18px 40px;">
                                        <i class="fas fa-arrow-left"></i> Back to Dates
                                    </button>
                                </div>
                                <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-bed"></i>
                                    <h3>No Rooms Available</h3>
                                    <p>
                                        <?php if ($check_in && $check_out): ?>
                                        Sorry, no rooms are available for the selected dates. Please try selecting different dates.
                                        <?php else: ?>
                                        Please select check-in and check-out dates first to see available rooms.
                                        <?php endif; ?>
                                    </p>
                                    <button type="button" class="btn btn-primary" onclick="goToStep1()" style="padding: 15px 40px;">
                                        <i class="fas fa-arrow-left"></i> Select Different Dates
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </form>
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

        let selectedRoomPrice = 0;
        let selectedRoomType = '';
        let selectedRoomView = '';
        let selectedRoomCapacity = 0;

        // Step navigation
        function goToStep2() {
            const checkIn = document.getElementById('check_in').value;
            const checkOut = document.getElementById('check_out').value;
            
            if (!checkIn || !checkOut) {
                alert('Please select check-in and check-out dates.');
                return;
            }
            
            const today = new Date().toISOString().split('T')[0];
            if (checkIn < today) {
                alert('Check-in date cannot be in the past.');
                return;
            }
            
            if (checkOut <= checkIn) {
                alert('Check-out date must be after check-in date.');
                return;
            }
            
            // Show step 2
            document.getElementById('step1Content').style.display = 'none';
            document.getElementById('step2Content').style.display = 'block';
            
            // Update step indicator
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.add('active');
            document.getElementById('step3').classList.remove('active');
            
            // Update URL with dates for room filtering
            const url = new URL(window.location);
            url.searchParams.set('check_in', checkIn);
            url.searchParams.set('check_out', checkOut);
            window.history.pushState({}, '', url);
        }
        
        function goToStep1() {
            document.getElementById('step1Content').style.display = 'block';
            document.getElementById('step2Content').style.display = 'none';
            
            document.getElementById('step1').classList.add('active');
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step3').classList.remove('active');
        }

        // Room selection
        function selectRoom(roomId, price, roomType, viewType, capacity) {
            // Update radio button
            document.querySelector(`input[name="room_id"][value="${roomId}"]`).checked = true;
            
            // Remove selected class from all rooms
            document.querySelectorAll('.room-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked room
            event.currentTarget.classList.add('selected');
            
            // Store room details
            selectedRoomPrice = price;
            selectedRoomType = roomType;
            selectedRoomView = viewType;
            selectedRoomCapacity = capacity;
            
            // Set adults automatically based on room capacity
            document.getElementById('adultsField').value = capacity;
            document.getElementById('childrenField').value = 0;
            
            // Update price summary
            updatePriceSummary();
            
            // Show price summary
            document.getElementById('priceSummary').style.display = 'block';
            
            // Update step indicator to step 3
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step3').classList.add('active');
            
            // Scroll to price summary
            document.getElementById('priceSummary').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Update price summary
        function updatePriceSummary() {
            const checkIn = document.getElementById('check_in').value;
            const checkOut = document.getElementById('check_out').value;
            
            if (!checkIn || !checkOut || selectedRoomCapacity === 0) {
                return;
            }
            
            // Calculate nights
            const start = new Date(checkIn);
            const end = new Date(checkOut);
            const nights = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
            
            if (nights <= 0) {
                return;
            }
            
            // Calculate prices
            const roomPrice = selectedRoomPrice * nights;
            const tax = roomPrice * 0.10; // 10% tax
            const serviceFee = roomPrice * 0.05; // 5% service fee
            const totalPrice = roomPrice + tax + serviceFee;
            
            // Format currency
            const formatCurrency = (amount) => {
                return 'Rp ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            };
            
            // Format date
            const formatDate = (dateStr) => {
                const date = new Date(dateStr);
                return date.toLocaleDateString('en-US', { 
                    weekday: 'short', 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });
            };
            
            // Update summary
            document.getElementById('summaryContent').innerHTML = `
                <div class="price-row">
                    <span class="price-label">Room Type:</span>
                    <span class="price-value">${selectedRoomType}</span>
                </div>
                <div class="price-row">
                    <span class="price-label">View:</span>
                    <span class="price-value">${selectedRoomView}</span>
                </div>
                <div class="price-row">
                    <span class="price-label">Guests:</span>
                    <span class="price-value">${selectedRoomCapacity} Person${selectedRoomCapacity > 1 ? 's' : ''} (Auto)</span>
                </div>
                <div class="price-row">
                    <span class="price-label">Check-in:</span>
                    <span class="price-value">${formatDate(checkIn)}</span>
                </div>
                <div class="price-row">
                    <span class="price-label">Check-out:</span>
                    <span class="price-value">${formatDate(checkOut)}</span>
                </div>
                <div class="price-row">
                    <span class="price-label">Duration:</span>
                    <span class="price-value">${nights} night${nights > 1 ? 's' : ''}</span>
                </div>
                <div class="price-row">
                    <span class="price-label">Room Price (${formatCurrency(selectedRoomPrice)} × ${nights}):</span>
                    <span class="price-value">${formatCurrency(roomPrice)}</span>
                </div>
                <div class="price-row">
                    <span class="price-label">Tax (10%):</span>
                    <span class="price-value">${formatCurrency(tax)}</span>
                </div>
                <div class="price-row">
                    <span class="price-label">Service Fee (5%):</span>
                    <span class="price-value">${formatCurrency(serviceFee)}</span>
                </div>
                <div class="price-row">
                    <span class="price-label">Total Amount:</span>
                    <span class="price-value total-price">${formatCurrency(totalPrice)}</span>
                </div>
            `;
        }

        // Update price when dates change
        document.getElementById('check_in').addEventListener('change', function() {
            const checkInDate = new Date(this.value);
            checkInDate.setDate(checkInDate.getDate() + 1);
            const minCheckOut = checkInDate.toISOString().split('T')[0];
            document.getElementById('check_out').min = minCheckOut;
            
            if (document.getElementById('check_out').value && 
                document.getElementById('check_out').value <= this.value) {
                document.getElementById('check_out').value = minCheckOut;
            }
            
            // If a room is selected, update price summary
            if (selectedRoomPrice > 0) {
                updatePriceSummary();
            }
        });

        document.getElementById('check_out').addEventListener('change', function() {
            if (selectedRoomPrice > 0) {
                updatePriceSummary();
            }
        });

        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const roomSelected = document.querySelector('input[name="room_id"]:checked');
            if (!roomSelected) {
                e.preventDefault();
                alert('Please select a room before confirming your booking.');
                return false;
            }
            
            const checkIn = document.getElementById('check_in').value;
            const checkOut = document.getElementById('check_out').value;
            
            if (!checkIn || !checkOut) {
                e.preventDefault();
                alert('Please select check-in and check-out dates.');
                return false;
            }
            
            const today = new Date().toISOString().split('T')[0];
            if (checkIn < today) {
                e.preventDefault();
                alert('Check-in date cannot be in the past.');
                return false;
            }
            
            if (checkOut <= checkIn) {
                e.preventDefault();
                alert('Check-out date must be after check-in date.');
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            return true;
        });

        // Initialize if dates are already in URL
        document.addEventListener('DOMContentLoaded', function() {
            const checkIn = document.getElementById('check_in').value;
            const checkOut = document.getElementById('check_out').value;
            
            if (checkIn && checkOut) {
                goToStep2();
            }
            
            // Set minimum dates
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('check_in').min = today;
            
            if (checkIn) {
                const checkInDate = new Date(checkIn);
                checkInDate.setDate(checkInDate.getDate() + 1);
                const minCheckOut = checkInDate.toISOString().split('T')[0];
                document.getElementById('check_out').min = minCheckOut;
            }
            
            // Auto-focus on check-in date if empty
            if (!checkIn) {
                document.getElementById('check_in').focus();
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
$rooms_stmt->close();
if (isset($conn)) {
    $conn->close();
}
?>