<?php
session_start();
require_once '../includes/config.php';
requireReceptionist();

$user_id = $_SESSION['user_id'];
$page_title = 'Room Status';
$hotel_name = $config['hotel_name'] ?? 'Hotel System';

// Ambil data receptionist
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist_result = $receptionist_stmt->get_result();
$receptionist = $receptionist_result->fetch_assoc();
$receptionist_stmt->close();

// Handle room status update
if (isset($_POST['update_status'])) {
    $room_id = intval($_POST['room_id']);
    $new_status = sanitize($_POST['status'], $conn);
    
    // Validasi status
    $valid_statuses = ['available', 'occupied', 'reserved', 'maintenance', 'cleaning'];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['flash_message'] = 'Invalid room status.';
        $_SESSION['flash_type'] = 'error';
    } else {
        $update_sql = "UPDATE rooms SET status = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $room_id);
        if ($update_stmt->execute()) {
            $_SESSION['flash_message'] = 'Room status updated successfully!';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to update room status.';
            $_SESSION['flash_type'] = 'error';
        }
        $update_stmt->close();
    }
    header("Location: rooms.php");
    exit();
}

// Ambil statistik kamar
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
    SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
    SUM(CASE WHEN status = 'cleaning' THEN 1 ELSE 0 END) as cleaning
FROM rooms";
$stats_result = $conn->query($stats_sql);
$room_stats = $stats_result->fetch_assoc();

// Ambil semua kamar
$rooms_sql = "SELECT r.*, rc.name as category_name, rc.base_price
              FROM rooms r
              JOIN room_categories rc ON r.category_id = rc.id
              ORDER BY r.room_number ASC";
$rooms_result = $conn->query($rooms_sql);
$rooms = [];
while ($row = $rooms_result->fetch_assoc()) {
    $rooms[] = $row;
}

// Handle search
$search_results = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = "%" . sanitize($_GET['search'], $conn) . "%";
    $search_sql = "SELECT r.*, rc.name as category_name, rc.base_price
                  FROM rooms r
                  JOIN room_categories rc ON r.category_id = rc.id
                  WHERE r.room_number LIKE ? OR rc.name LIKE ?
                  ORDER BY r.room_number ASC";
    $search_stmt = $conn->prepare($search_sql);
    $search_stmt->bind_param("ss", $search_term, $search_term);
    $search_stmt->execute();
    $search_result = $search_stmt->get_result();
    while ($row = $search_result->fetch_assoc()) {
        $search_results[] = $row;
    }
    $search_stmt->close();
}

// Filter status
$status_filter = $_GET['status'] ?? '';
$display_rooms = !empty($search_results) ? $search_results : $rooms;
if ($status_filter) {
    $display_rooms = array_filter($display_rooms, function($room) use ($status_filter) {
        return $room['status'] == $status_filter;
    });
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
.card {
background: var(--card-bg);
border-radius: 16px;
margin-bottom: 25px;
border: 1px solid rgba(76, 201, 240, 0.1);
overflow: hidden;
}
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
.alert-error,
.alert-danger {
background: rgba(220, 53, 69, 0.2);
border-color: rgba(220, 53, 69, 0.3);
color: #dc3545;
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
border: none;
cursor: pointer;
font-size: 14px;
}
.btn-primary { background: var(--blue); color: var(--navy); }
.btn-secondary { background: var(--gray); color: white; }
.btn-success { background: var(--green); color: white; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn:hover { transform: translateY(-1px); }
.time-display {
font-size: 1.5rem;
color: var(--blue);
font-weight: 600;
margin-bottom: 5px;
}
.date-display { color: #aaa; font-size: 0.9rem; }
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
/* === TAMBAHAN KHUSUS ROOMS === */
.room-status-grid {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
gap: 20px;
margin-bottom: 30px;
}
.room-status-card {
background: var(--card-bg);
border-radius: 15px;
padding: 25px;
border: 1px solid rgba(255,255,255,0.1);
}
.room-status-icon {
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
.room-status-count {
font-size: 2.2rem;
font-weight: 700;
color: white;
margin-bottom: 5px;
}
.room-status-label {
color: #aaa;
font-size: 0.9rem;
margin-bottom: 15px;
}
.rooms-grid {
display: grid;
grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
gap: 25px;
}
.room-item {
background: rgba(255,255,255,0.03);
border-radius: 15px;
overflow: hidden;
border: 1px solid rgba(255,255,255,0.1);
transition: all 0.3s;
}
.room-item:hover {
transform: translateY(-5px);
border-color: var(--blue);
box-shadow: 0 10px 20px rgba(0,0,0,0.2);
}
.room-item-header {
padding: 20px;
border-bottom: 1px solid rgba(255,255,255,0.05);
display: flex;
justify-content: space-between;
align-items: center;
}
.room-number {
font-size: 1.3rem;
color: white;
font-weight: 600;
}
.room-type {
color: var(--blue);
font-size: 0.9rem;
font-weight: 500;
}
.room-price {
color: #aaa;
font-size: 0.9rem;
}
.room-body {
padding: 20px;
}
.room-details {
display: grid;
grid-template-columns: repeat(2, 1fr);
gap: 15px;
margin-bottom: 20px;
}
.room-detail {
display: flex;
align-items: center;
gap: 8px;
color: #aaa;
font-size: 0.9rem;
}
.room-detail i {
color: var(--blue);
width: 20px;
}
.room-status-badge {
display: inline-block;
padding: 6px 12px;
border-radius: 20px;
font-size: 0.75rem;
font-weight: 600;
text-transform: uppercase;
letter-spacing: 0.5px;
}
.status-available { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.3); }
.status-occupied { background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.3); }
.status-reserved { background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.3); }
.status-maintenance { background: rgba(108, 117, 125, 0.2); color: #6c757d; border: 1px solid rgba(108, 117, 125, 0.3); }
.status-cleaning { background: rgba(0, 123, 255, 0.2); color: #007bff; border: 1px solid rgba(0, 123, 255, 0.3); }
.status-select {
width: 100%;
padding: 8px 12px;
border-radius: 6px;
background: rgba(255,255,255,0.05);
border: 1px solid rgba(255,255,255,0.1);
color: white;
font-size: 0.85rem;
}
.empty-rooms {
text-align: center;
padding: 60px 20px;
color: #aaa;
grid-column: 1 / -1;
}
.empty-rooms i {
font-size: 48px;
margin-bottom: 15px;
opacity: 0.5;
}
.room-filters {
display: flex;
gap: 15px;
margin-bottom: 25px;
flex-wrap: wrap;
}
.filter-badge {
padding: 8px 20px;
border-radius: 20px;
font-size: 0.9rem;
font-weight: 500;
cursor: pointer;
transition: all 0.3s;
border: 1px solid rgba(255,255,255,0.1);
background: rgba(255,255,255,0.03);
color: #ccc;
}
.filter-badge:hover {
border-color: var(--blue);
color: var(--blue);
}
.filter-badge.active {
border-color: var(--blue);
background: rgba(76, 201, 240, 0.1);
color: var(--blue);
}
@media (max-width: 992px) {
.sidebar { transform: translateX(-100%); }
.sidebar.active { transform: translateX(0); }
.main-content { margin-left: 0; }
.menu-toggle { display: block; }
}
</style>
</head>
<body>

<div class="receptionist-wrapper">

<!-- Sidebar -->
<aside class="sidebar">
<div class="sidebar-header">
<div class="sidebar-logo"><i class="fas fa-concierge-bell"></i></div>
<div class="sidebar-title">
<h3><?= htmlspecialchars($hotel_name) ?></h3>
<p>Receptionist Portal</p>
</div>
</div>
<nav class="sidebar-nav">
<a href="dashboard.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active' : '' ?>">
<i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
</a>
<div class="nav-divider"></div>
<div class="nav-group">
<p class="nav-label">BOOKINGS</p>
<a href="checkin.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'checkin.php') ? 'active' : '' ?>">
<i class="fas fa-sign-in-alt"></i> <span>Check-in</span>
</a>
<a href="checkout.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'checkout.php') ? 'active' : '' ?>">
<i class="fas fa-sign-out-alt"></i> <span>Check-out</span>
</a>
<a href="bookings.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'bookings.php') ? 'active' : '' ?>">
<i class="fas fa-calendar-alt"></i> <span>Manage Bookings</span>
</a>
<a href="new-booking.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'new-booking.php') ? 'active' : '' ?>">
<i class="fas fa-plus-circle"></i> <span>New Booking</span>
</a>
</div>
<div class="nav-divider"></div>
<div class="nav-group">
<p class="nav-label">ROOMS</p>
<a href="rooms.php" class="nav-item active">
<i class="fas fa-bed"></i> <span>Room Status</span>
</a>
<a href="room-management.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'room-management.php') ? 'active' : '' ?>">
<i class="fas fa-sliders-h"></i> <span>Room Management</span>
</a>
</div>
<div class="nav-divider"></div>
<div class="nav-group">
<p class="nav-label">GUESTS</p>
<a href="guests.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'guests.php') ? 'active' : '' ?>">
<i class="fas fa-users"></i> <span>Guests List</span>
</a>
<a href="active-guests.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'active-guests.php') ? 'active' : '' ?>">
<i class="fas fa-user-check"></i> <span>Active Guests</span>
</a>
</div>
<div class="nav-divider"></div>
<div class="nav-group">
<p class="nav-label">SERVICES</p>
<a href="service-requests.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'service-requests.php') ? 'active' : '' ?>">
<i class="fas fa-bell"></i> <span>Service Requests</span>
</a>
<a href="services.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'services.php') ? 'active' : '' ?>">
<i class="fas fa-concierge-bell"></i> <span>Hotel Services</span>
</a>
</div>
<div class="nav-divider"></div>
<div class="nav-group">
<p class="nav-label">REPORTS</p>
<a href="daily-report.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'daily-report.php') ? 'active' : '' ?>">
<i class="fas fa-chart-line"></i> <span>Daily Report</span>
</a>
<a href="reports.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'reports.php') ? 'active' : '' ?>">
<i class="fas fa-file-alt"></i> <span>Reports</span>
</a>
</div>
</nav>
<div class="sidebar-footer">
<div class="user-menu">
<div class="user-avatar"><?= strtoupper(substr($receptionist['full_name'] ?? $receptionist['username'], 0, 1)) ?></div>
<div class="user-info">
<div class="user-name"><?= htmlspecialchars($receptionist['full_name'] ?? $receptionist['username']) ?></div>
<div class="user-role"><?= ucfirst($receptionist['role']) ?></div>
</div>
</div>
</div>
</aside>

<main class="main-content">
<header class="top-header">
<div class="header-left">
<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
<h1><?= htmlspecialchars($page_title) ?></h1>
</div>
<div class="header-right">
<div style="text-align: right;">
<div class="time-display" id="currentTime"><?= date('H:i:s') ?></div>
<div class="date-display"><?= date('l, d F Y') ?></div>
</div>
<a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
</header>

<div class="content-area">
<?php if (isset($_SESSION['flash_message'])): 
    $alertClass = 'alert-warning';
    if (isset($_SESSION['flash_type'])) {
        if ($_SESSION['flash_type'] === 'success') $alertClass = 'alert-success';
        elseif ($_SESSION['flash_type'] === 'error') $alertClass = 'alert-danger';
    }
?>
    <div class="alert <?= $alertClass ?>"><?= htmlspecialchars($_SESSION['flash_message']) ?></div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>

<!-- Room Status Overview -->
<div class="room-status-grid">
<div class="room-status-card">
<div class="room-status-icon" style="color: #28a745;"><i class="fas fa-door-open"></i></div>
<div class="room-status-count"><?= $room_stats['available'] ?></div>
<div class="room-status-label">Available</div>
</div>
<div class="room-status-card">
<div class="room-status-icon" style="color: #dc3545;"><i class="fas fa-bed"></i></div>
<div class="room-status-count"><?= $room_stats['occupied'] ?></div>
<div class="room-status-label">Occupied</div>
</div>
<div class="room-status-card">
<div class="room-status-icon" style="color: #ffc107;"><i class="fas fa-calendar-check"></i></div>
<div class="room-status-count"><?= $room_stats['reserved'] ?></div>
<div class="room-status-label">Reserved</div>
</div>
<div class="room-status-card">
<div class="room-status-icon" style="color: #6c757d;"><i class="fas fa-tools"></i></div>
<div class="room-status-count"><?= $room_stats['maintenance'] + $room_stats['cleaning'] ?></div>
<div class="room-status-label">Maintenance/Cleaning</div>
</div>
</div>

<!-- Search & Filters -->
<div class="search-box" style="background: rgba(76, 201, 240, 0.1); border-radius: 15px; padding: 25px; margin-bottom: 30px; border: 1px solid rgba(76, 201, 240, 0.2);">
<h3 style="color: white; margin-bottom: 20px;"><i class="fas fa-search"></i> Search Rooms</h3>
<form method="GET" class="search-form" style="display: flex; gap: 15px; align-items: end;">
<div class="form-group" style="flex: 1;">
<label class="form-label" style="display: block; color: #ccc; font-size: 14px; margin-bottom: 8px;">Search by Room Number or Type</label>
<div class="input-group" style="display: flex; gap: 10px;">
<input type="text" name="search" class="form-control"
placeholder="Enter room number or room type..."
value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
style="flex: 1; padding: 10px 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white;">
<button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 20px;">
<i class="fas fa-search"></i> Search
</button>
</div>
</div>
</form>
<div class="room-filters">
<a href="rooms.php" class="filter-badge <?= !$status_filter ? 'active' : '' ?>">All Rooms (<?= $room_stats['total'] ?>)</a>
<a href="?status=available" class="filter-badge <?= $status_filter === 'available' ? 'active' : '' ?>">Available (<?= $room_stats['available'] ?>)</a>
<a href="?status=occupied" class="filter-badge <?= $status_filter === 'occupied' ? 'active' : '' ?>">Occupied (<?= $room_stats['occupied'] ?>)</a>
<a href="?status=reserved" class="filter-badge <?= $status_filter === 'reserved' ? 'active' : '' ?>">Reserved (<?= $room_stats['reserved'] ?>)</a>
<a href="?status=maintenance" class="filter-badge <?= $status_filter === 'maintenance' ? 'active' : '' ?>">Maintenance (<?= $room_stats['maintenance'] ?>)</a>
<a href="?status=cleaning" class="filter-badge <?= $status_filter === 'cleaning' ? 'active' : '' ?>">Cleaning (<?= $room_stats['cleaning'] ?>)</a>
</div>
</div>

<!-- Rooms Grid -->
<div class="rooms-grid">
<?php if (empty($display_rooms)): ?>
<div class="empty-rooms">
<i class="fas fa-bed fa-3x"></i>
<h3>No Rooms Found</h3>
<p>No rooms match your criteria.</p>
<a href="room-management.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Add New Room</a>
</div>
<?php else: ?>
<?php foreach ($display_rooms as $room): 
    $status_class = 'status-' . $room['status'];
    $status_text = ucfirst($room['status']);
?>
<div class="room-item">
<div class="room-item-header">
<div>
<div class="room-number">Room <?= $room['room_number'] ?></div>
<div class="room-type"><?= htmlspecialchars($room['category_name']) ?></div>
</div>
<div class="room-price"><?= formatCurrency($room['base_price']) ?>/night</div>
</div>
<div class="room-body">
<div class="room-details">
<div class="room-detail"><i class="fas fa-layer-group"></i> Floor <?= $room['floor'] ?></div>
<div class="room-detail"><i class="fas fa-eye"></i> <?= ucfirst($room['view_type']) ?> View</div>
<div class="room-detail"><i class="fas fa-bed"></i> <?= ucfirst($room['bed_type']) ?> Bed</div>
<div class="room-detail"><i class="fas fa-smoking"></i> <?= $room['smoking'] ? 'Smoking' : 'Non-smoking' ?></div>
</div>
<div style="margin-bottom: 15px;">
<span class="room-status-badge <?= $status_class ?>"><?= $status_text ?></span>
</div>
<form method="POST" action="">
<input type="hidden" name="room_id" value="<?= $room['id'] ?>">
<select name="status" class="status-select" onchange="this.form.submit()">
<option value="available" <?= $room['status'] == 'available' ? 'selected' : '' ?>>Available</option>
<option value="occupied" <?= $room['status'] == 'occupied' ? 'selected' : '' ?>>Occupied</option>
<option value="reserved" <?= $room['status'] == 'reserved' ? 'selected' : '' ?>>Reserved</option>
<option value="maintenance" <?= $room['status'] == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
<option value="cleaning" <?= $room['status'] == 'cleaning' ? 'selected' : '' ?>>Cleaning</option>
</select>
<input type="hidden" name="update_status" value="1">
</form>
<div style="display: flex; gap: 10px; margin-top: 20px;">

<?php if ($room['status'] == 'available'): ?>
<a href="new-booking.php?room_id=<?= $room['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-calendar-plus"></i> Book</a>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>
</main>
</div>

<script>
document.getElementById('menuToggle').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('active');
});
function updateTime() {
    document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('id-ID', {hour12:false});
}
setInterval(updateTime, 1000);
updateTime();
</script>

</body>
</html>
<?php
$conn->close();
?>