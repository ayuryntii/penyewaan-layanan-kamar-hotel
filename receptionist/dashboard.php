<?php
// receptionist/dashboard.php - FIXED VERSION (NO REDIRECT LOOP)
session_start();
require_once '../includes/config.php';
requireReceptionist(); // Ini akan redirect ke login jika bukan receptionist

$user_id = $_SESSION['user_id'];
$page_title = 'Receptionist Dashboard';

// Get receptionist data
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist_result = $receptionist_stmt->get_result();
$receptionist = $receptionist_result->fetch_assoc();
$receptionist_stmt->close();

// Get dashboard stats
$stats = getReceptionistDashboardStats($conn);

// Get today's date
$today = date('Y-m-d');

// Get today's arrivals
$arrivals_sql = "SELECT b.*, u.full_name, r.room_number, rc.name as room_type
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN rooms r ON b.room_id = r.id
JOIN room_categories rc ON r.category_id = rc.id
WHERE DATE(b.check_in) = ?
AND b.booking_status = 'confirmed'
ORDER BY b.check_in ASC
LIMIT 5";
$arrivals_stmt = $conn->prepare($arrivals_sql);
$arrivals_stmt->bind_param("s", $today);
$arrivals_stmt->execute();
$arrivals_result = $arrivals_stmt->get_result();

// Get today's departures
$departures_sql = "SELECT b.*, u.full_name, r.room_number, rc.name as room_type
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN rooms r ON b.room_id = r.id
JOIN room_categories rc ON r.category_id = rc.id
WHERE DATE(b.check_out) = ?
AND b.booking_status = 'checked_in'
ORDER BY b.check_out ASC
LIMIT 5";
$departures_stmt = $conn->prepare($departures_sql);
$departures_stmt->bind_param("s", $today);
$departures_stmt->execute();
$departures_result = $departures_stmt->get_result();

// Get active guests
$active_guests = getActiveGuests($conn);
$active_guests_count = count($active_guests);

// Get recent bookings
$recent_bookings = getAllBookings($conn, 10);

// Get service requests
$pending_requests_sql = "SELECT COUNT(*) as count FROM booking_services WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
$pending_result = $conn->query($pending_requests_sql);
$pending_requests = $pending_result->fetch_assoc()['count'] ?? 0;
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
--green: #28a745;
--yellow: #ffc107;
--red: #dc3545;
--purple: #6f42c1;
--orange: #fd7e14;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
font-family: 'Poppins', sans-serif;
background: var(--dark-bg);
color: var(--light);
overflow-x: hidden;
}
.receptionist-wrapper {
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
/* === Quick Actions === */
.quick-actions {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
gap: 15px;
margin-bottom: 30px;
}
.action-btn {
background: rgba(255,255,255,0.05);
border: 1px solid rgba(255,255,255,0.1);
padding: 20px;
border-radius: 12px;
text-align: center;
text-decoration: none;
color: white;
transition: all 0.3s;
position: relative;
}
.action-btn:hover {
background: rgba(76, 201, 240, 0.1);
border-color: var(--blue);
transform: translateY(-3px);
}
.action-btn i {
font-size: 24px;
color: var(--blue);
margin-bottom: 10px;
}
.action-btn span {
display: block;
font-weight: 500;
}
.action-badge {
position: absolute;
top: -8px;
right: -8px;
background: var(--red);
color: white;
font-size: 0.7rem;
font-weight: 600;
padding: 3px 8px;
border-radius: 10px;
min-width: 20px;
text-align: center;
}
/* === Stats Grid === */
.stats-grid {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
gap: 20px;
margin-bottom: 30px;
}
.stat-card {
background: var(--card-bg);
border-radius: 15px;
padding: 25px;
border: 1px solid rgba(255,255,255,0.1);
transition: all 0.3s;
position: relative;
overflow: hidden;
}
.stat-card:hover {
transform: translateY(-5px);
border-color: var(--blue);
}
.stat-card:nth-child(1) { border-top: 4px solid var(--blue); }
.stat-card:nth-child(2) { border-top: 4px solid var(--green); }
.stat-card:nth-child(3) { border-top: 4px solid var(--yellow); }
.stat-card:nth-child(4) { border-top: 4px solid var(--red); }
.stat-card:nth-child(5) { border-top: 4px solid var(--purple); }
.stat-icon {
width: 60px;
height: 60px;
border-radius: 12px;
display: flex;
align-items: center;
justify-content: center;
font-size: 24px;
margin-bottom: 20px;
background: rgba(255,255,255,0.05);
}
.stat-card:nth-child(1) .stat-icon { color: var(--blue); }
.stat-card:nth-child(2) .stat-icon { color: var(--green); }
.stat-card:nth-child(3) .stat-icon { color: var(--yellow); }
.stat-card:nth-child(4) .stat-icon { color: var(--red); }
.stat-card:nth-child(5) .stat-icon { color: var(--purple); }
.stat-value {
font-size: 2.2rem;
font-weight: 700;
color: white;
margin-bottom: 5px;
}
.stat-label {
color: #aaa;
font-size: 0.9rem;
margin-bottom: 15px;
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
display: flex;
align-items: center;
gap: 10px;
}
.card-actions {
display: flex;
gap: 10px;
}
.card-body {
padding: 25px;
}
/* === Tables === */
.table-responsive {
overflow-x: auto;
}
.table {
width: 100%;
border-collapse: collapse;
}
.table th {
background: rgba(255,255,255,0.03);
padding: 15px;
text-align: left;
color: #aaa;
font-weight: 600;
font-size: 0.9rem;
border-bottom: 1px solid rgba(255,255,255,0.1);
}
.table td {
padding: 15px;
border-bottom: 1px solid rgba(255,255,255,0.05);
color: #ddd;
}
.table tbody tr:hover {
background: rgba(76, 201, 240, 0.05);
}
.table tbody tr:last-child td {
border-bottom: none;
}
/* === Badges === */
.badge {
padding: 5px 12px;
border-radius: 20px;
font-size: 0.75rem;
font-weight: 600;
text-transform: uppercase;
letter-spacing: 0.5px;
}
.badge-success {
background: rgba(40, 167, 69, 0.2);
color: #28a745;
border: 1px solid rgba(40, 167, 69, 0.3);
}
.badge-warning {
background: rgba(255, 193, 7, 0.2);
color: #ffc107;
border: 1px solid rgba(255, 193, 7, 0.3);
}
.badge-danger {
background: rgba(220, 53, 69, 0.2);
color: #dc3545;
border: 1px solid rgba(220, 53, 69, 0.3);
}
.badge-info {
background: rgba(23, 162, 184, 0.2);
color: #17a2b8;
border: 1px solid rgba(23, 162, 184, 0.3);
}
.badge-primary {
background: rgba(76, 201, 240, 0.2);
color: var(--blue);
border: 1px solid rgba(76, 201, 240, 0.3);
}
.badge-secondary {
background: rgba(108, 117, 125, 0.2);
color: #6c757d;
border: 1px solid rgba(108, 117, 125, 0.3);
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
.btn-success {
background: var(--green);
color: white;
}
.btn-warning {
background: var(--yellow);
color: var(--navy);
}
.btn-danger {
background: var(--red);
color: white;
}
.btn-secondary {
background: var(--gray);
color: white;
}
.btn-primary:hover {
background: #3abde0;
transform: translateY(-1px);
}
.btn-success:hover {
background: #218838;
transform: translateY(-1px);
}
.btn-warning:hover {
background: #e0a800;
transform: translateY(-1px);
}
.btn-danger:hover {
background: #c82333;
transform: translateY(-1px);
}
/* === Time Display === */
.time-display {
font-size: 1.5rem;
color: var(--blue);
font-weight: 600;
margin-bottom: 5px;
}
.date-display {
color: #aaa;
font-size: 0.9rem;
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
/* === Empty State === */
.empty-state {
text-align: center;
padding: 40px 20px;
color: #aaa;
}
.empty-state i {
font-size: 36px;
margin-bottom: 15px;
opacity: 0.5;
}
/* === Alert Messages === */
.alert {
padding: 15px 20px;
border-radius: 10px;
margin-bottom: 25px;
font-size: 0.95rem;
border: 1px solid transparent;
}
.alert-success {
background: rgba(40, 167, 69, 0.2);
border-color: rgba(40, 167, 69, 0.3);
color: #28a745;
}
.alert-danger {
background: rgba(220, 53, 69, 0.2);
border-color: rgba(220, 53, 69, 0.3);
color: #dc3545;
}
.alert-warning {
background: rgba(255, 193, 7, 0.2);
border-color: rgba(255, 193, 7, 0.3);
color: #ffc107;
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
.quick-actions {
grid-template-columns: repeat(2, 1fr);
}
.stats-grid {
grid-template-columns: 1fr;
}
}
@media (max-width: 576px) {
.quick-actions {
grid-template-columns: 1fr;
}
.card-header {
flex-direction: column;
align-items: flex-start;
gap: 15px;
}
}
</style>
</head>
<body>
<div class="receptionist-wrapper">
<!-- Sidebar -->
<aside class="sidebar">
<div class="sidebar-header">
<div class="sidebar-logo">
<i class="fas fa-concierge-bell"></i>
</div>
<div class="sidebar-title">
<h3><?= htmlspecialchars($hotel_name) ?></h3>
<p>Receptionist Portal</p>
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
<a href="checkin.php" class="nav-item">
<i class="fas fa-sign-in-alt"></i>
<span>Check-in</span>
</a>
<a href="checkout.php" class="nav-item">
<i class="fas fa-sign-out-alt"></i>
<span>Check-out</span>
</a>
<a href="bookings.php" class="nav-item">
<i class="fas fa-calendar-alt"></i>
<span>Manage Bookings</span>
</a>
<a href="new-booking.php" class="nav-item">
<i class="fas fa-plus-circle"></i>
<span>New Booking</span>
</a>
</div>
<div class="nav-divider"></div>
<div class="nav-group">
<p class="nav-label">GUESTS</p>
<a href="guests.php" class="nav-item">
<i class="fas fa-users"></i>
<span>Guests List</span>
</a>
<a href="active-guests.php" class="nav-item">
<i class="fas fa-user-check"></i>
<span>Active Guests</span>
</a>
</div>
<div class="nav-divider"></div>
<div class="nav-group">
<p class="nav-label">ROOMS</p>
<a href="rooms.php" class="nav-item">
<i class="fas fa-bed"></i>
<span>Room Status</span>
</a>
<a href="room-management.php" class="nav-item">
<i class="fas fa-sliders-h"></i>
<span>Room Management</span>
</a>
</div>
<div class="nav-divider"></div>
<div class="nav-group">
<p class="nav-label">SERVICES</p>
<a href="service-requests.php" class="nav-item">
<i class="fas fa-bell"></i>
<span>Service Requests</span>
</a>
<a href="services.php" class="nav-item">
<i class="fas fa-concierge-bell"></i>
<span>Hotel Services</span>
</a>
</div>
<div class="nav-divider"></div>
<div class="nav-group">
<p class="nav-label">REPORTS</p>
<a href="daily-report.php" class="nav-item">
<i class="fas fa-chart-line"></i>
<span>Daily Report</span>
</a>
<a href="reports.php" class="nav-item">
<i class="fas fa-file-alt"></i>
<span>Reports</span>
</a>
</div>
</nav>
<div class="sidebar-footer">
<div class="user-menu">
<div class="user-avatar">
<?= strtoupper(substr($receptionist['full_name'] ?? $receptionist['username'], 0, 1)) ?>
</div>
<div class="user-info">
<div class="user-name"><?= htmlspecialchars($receptionist['full_name'] ?? $receptionist['username']) ?></div>
<div class="user-role"><?= ucfirst($receptionist['role']) ?></div>
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
<div style="text-align: right;">
<div class="time-display" id="currentTime"><?= date('H:i:s') ?></div>
<div class="date-display"><?= date('l, d F Y') ?></div>
</div>
<a href="../logout.php" class="logout-btn">
<i class="fas fa-sign-out-alt"></i> Logout
</a>
</div>
</header>
<div class="content-area">
<?= getFlashMessage() ?>
<!-- Quick Actions -->
<div class="quick-actions">
<a href="checkin.php" class="action-btn">
<i class="fas fa-sign-in-alt"></i>
<span>Check-in Guest</span>
<?php if ($stats['today_checkins'] > 0): ?>
<span class="action-badge"><?= $stats['today_checkins'] ?></span>
<?php endif; ?>
</a>
<a href="checkout.php" class="action-btn">
<i class="fas fa-sign-out-alt"></i>
<span>Check-out Guest</span>
<?php if ($stats['today_checkouts'] > 0): ?>
<span class="action-badge"><?= $stats['today_checkouts'] ?></span>
<?php endif; ?>
</a>
<a href="new-booking.php" class="action-btn">
<i class="fas fa-calendar-plus"></i>
<span>New Booking</span>
</a>
<a href="service-requests.php" class="action-btn">
<i class="fas fa-bell"></i>
<span>Service Requests</span>
<?php if ($pending_requests > 0): ?>
<span class="action-badge"><?= $pending_requests ?></span>
<?php endif; ?>
</a>
<a href="rooms.php" class="action-btn">
<i class="fas fa-bed"></i>
<span>Room Status</span>
</a>
</div>
<!-- Stats Grid -->
<div class="stats-grid">
<div class="stat-card">
<div class="stat-icon">
<i class="fas fa-sign-in-alt"></i>
</div>
<div class="stat-value"><?= $stats['today_checkins'] ?></div>
<div class="stat-label">Today's Check-ins</div>
<div style="color: #aaa; font-size: 0.85rem;">
<i class="fas fa-calendar-check"></i> Ready for arrival
</div>
</div>
<div class="stat-card">
<div class="stat-icon">
<i class="fas fa-sign-out-alt"></i>
</div>
<div class="stat-value"><?= $stats['today_checkouts'] ?></div>
<div class="stat-label">Today's Check-outs</div>
<div style="color: #aaa; font-size: 0.85rem;">
<i class="fas fa-calendar-times"></i> Prepare for departure
</div>
</div>
<div class="stat-card">
<div class="stat-icon">
<i class="fas fa-users"></i>
</div>
<div class="stat-value"><?= $active_guests_count ?></div>
<div class="stat-label">Active Guests</div>
<div style="color: #aaa; font-size: 0.85rem;">
<i class="fas fa-user-check"></i> Currently in hotel
</div>
</div>
<div class="stat-card">
<div class="stat-icon">
<i class="fas fa-bed"></i>
</div>
<div class="stat-value"><?= $stats['available_rooms'] ?></div>
<div class="stat-label">Available Rooms</div>
<div style="color: #aaa; font-size: 0.85rem;">
<i class="fas fa-door-closed"></i> Ready for booking
</div>
</div>
<div class="stat-card">
<div class="stat-icon">
<i class="fas fa-bell"></i>
</div>
<div class="stat-value"><?= $pending_requests ?></div>
<div class="stat-label">Pending Requests</div>
<div style="color: #aaa; font-size: 0.85rem;">
<i class="fas fa-clock"></i> Require attention
</div>
</div>
</div>
<!-- Today's Arrivals & Departures -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
<!-- Today's Arrivals -->
<div class="card">
<div class="card-header">
<h3 class="card-title">
<i class="fas fa-plane-arrival"></i>
Today's Arrivals
</h3>
<a href="checkin.php" class="btn btn-primary btn-sm">
<i class="fas fa-list"></i> View All
</a>
</div>
<div class="card-body">
<div class="table-responsive">
<table class="table">
<thead>
<tr>
<th>Guest</th>
<th>Room</th>
<th>Check-in</th>
<th>Status</th>
</tr>
</thead>
<tbody>
<?php if ($arrivals_result->num_rows > 0): ?>
<?php while ($arrival = $arrivals_result->fetch_assoc()): ?>
<tr>
<td>
<div style="font-weight: 600;"><?= htmlspecialchars($arrival['full_name'] ?? $arrival['username']) ?></div>
<div style="font-size: 0.8rem; color: #aaa;">#<?= $arrival['booking_code'] ?></div>
</td>
<td>
<div><?= htmlspecialchars($arrival['room_type']) ?></div>
<div style="font-size: 0.8rem; color: var(--blue);">Room <?= $arrival['room_number'] ?></div>
</td>
<td><?= date('H:i', strtotime($arrival['check_in'])) ?></td>
<td>
<span class="badge badge-success">Confirmed</span>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="4" style="text-align: center; color: #aaa; padding: 30px;">
<i class="fas fa-calendar-times fa-2x" style="margin-bottom: 10px;"></i>
<div>No arrivals scheduled for today</div>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<!-- Today's Departures -->
<div class="card">
<div class="card-header">
<h3 class="card-title">
<i class="fas fa-plane-departure"></i>
Today's Departures
</h3>
<a href="checkout.php" class="btn btn-warning btn-sm">
<i class="fas fa-list"></i> View All
</a>
</div>
<div class="card-body">
<div class="table-responsive">
<table class="table">
<thead>
<tr>
<th>Guest</th>
<th>Room</th>
<th>Check-out</th>
<th>Status</th>
</tr>
</thead>
<tbody>
<?php if ($departures_result->num_rows > 0): ?>
<?php while ($departure = $departures_result->fetch_assoc()): ?>
<tr>
<td>
<div style="font-weight: 600;"><?= htmlspecialchars($departure['full_name'] ?? $departure['username']) ?></div>
<div style="font-size: 0.8rem; color: #aaa;">#<?= $departure['booking_code'] ?></div>
</td>
<td>
<div><?= htmlspecialchars($departure['room_type']) ?></div>
<div style="font-size: 0.8rem; color: var(--blue);">Room <?= $departure['room_number'] ?></div>
</td>
<td><?= date('H:i', strtotime($departure['check_out'])) ?></td>
<td>
<span class="badge badge-warning">Checked-in</span>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="4" style="text-align: center; color: #aaa; padding: 30px;">
<i class="fas fa-calendar-times fa-2x" style="margin-bottom: 10px;"></i>
<div>No departures scheduled for today</div>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
<!-- Recent Bookings -->
<div class="card">
<div class="card-header">
<h3 class="card-title">
<i class="fas fa-history"></i>
Recent Bookings
</h3>
<a href="bookings.php" class="btn btn-primary">
<i class="fas fa-eye"></i> View All Bookings
</a>
</div>
<div class="card-body">
<?php if (!empty($recent_bookings)): ?>
<div class="table-responsive">
<table class="table">
<thead>
<tr>
<th>Booking Code</th>
<th>Guest</th>
<th>Room</th>
<th>Dates</th>
<th>Total</th>
<th>Status</th>
<th>Payment</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($recent_bookings as $booking): ?>
<tr>
<td><strong><?= $booking['booking_code'] ?></strong></td>
<td>
<div style="font-weight: 600;"><?= htmlspecialchars($booking['full_name'] ?? $booking['username']) ?></div>
<div style="font-size: 0.8rem; color: #aaa;">
<?= date('d M', strtotime($booking['created_at'])) ?>
</div>
</td>
<td>
<div><?= htmlspecialchars($booking['room_type']) ?></div>
<div style="font-size: 0.8rem; color: var(--blue);">Room <?= $booking['room_number'] ?></div>
</td>
<td>
<div><?= date('d M', strtotime($booking['check_in'])) ?></div>
<div style="font-size: 0.8rem; color: #aaa;">to <?= date('d M', strtotime($booking['check_out'])) ?></div>
</td>
<td><strong><?= formatCurrency($booking['final_price']) ?></strong></td>
<td>
<?= getBookingStatusBadge($booking['booking_status']) ?>
</td>
<td>
<?= getPaymentStatusBadge($booking['payment_status']) ?>
</td>
<td>
<div style="display: flex; gap: 5px;">
<?php if ($booking['booking_status'] == 'confirmed'): ?>
<a href="checkin.php?booking_id=<?= $booking['id'] ?>"
class="btn btn-success btn-sm" title="Check-in">
<i class="fas fa-sign-in-alt"></i>
</a>
<?php elseif ($booking['booking_status'] == 'checked_in'): ?>
<a href="checkout.php?booking_id=<?= $booking['id'] ?>"
class="btn btn-warning btn-sm" title="Check-out">
<i class="fas fa-sign-out-alt"></i>
</a>
<?php endif; ?>
<a href="booking-details.php?id=<?= $booking['id'] ?>"
class="btn btn-primary btn-sm" title="View Details">
<i class="fas fa-eye"></i>
</a>
<a href="print-invoice.php?booking_id=<?= $booking['id'] ?>"
class="btn btn-secondary btn-sm" title="Print Invoice" target="_blank">
<i class="fas fa-print"></i>
</a>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state">
<i class="fas fa-calendar-times fa-2x"></i>
<h3 style="color: #aaa; margin: 15px 0 10px 0;">No Recent Bookings</h3>
<p style="color: #777; margin-bottom: 20px;">
No bookings have been made recently.
</p>
</div>
<?php endif; ?>
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
// Update time display
function updateTime() {
const now = new Date();
const timeString = now.toLocaleTimeString('id-ID', {
hour: '2-digit',
minute: '2-digit',
second: '2-digit'
});
document.getElementById('currentTime').textContent = timeString;
}
// Update time every second
setInterval(updateTime, 1000);
// Initialize time
updateTime();
// Auto-refresh dashboard every 60 seconds
setTimeout(function() {
location.reload();
}, 60000);
</script>
</body>
</html>
<?php
$arrivals_stmt->close();
$departures_stmt->close();
?>
