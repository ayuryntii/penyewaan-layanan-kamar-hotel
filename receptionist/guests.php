<?php
session_start();
require_once '../includes/config.php';
requireReceptionist();

$user_id = $_SESSION['user_id'];
$page_title = 'Guests List';
$hotel_name = $config['hotel_name'] ?? 'Hotel System';

// Ambil data receptionist
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist_result = $receptionist_stmt->get_result();
$receptionist = $receptionist_result->fetch_assoc();
$receptionist_stmt->close();

// Handle guest actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $guest_id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($guest_id > 0) {
        switch ($action) {
            case 'activate':
                $update_sql = "UPDATE users SET status = 'active' WHERE id = ? AND role = 'customer'";
                break;
            case 'deactivate':
                $update_sql = "UPDATE users SET status = 'inactive' WHERE id = ? AND role = 'customer'";
                break;
            case 'delete':
                // Cek apakah tamu punya booking
                $check_sql = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $guest_id);
                $check_stmt->execute();
                $count = $check_stmt->get_result()->fetch_assoc()['count'];
                $check_stmt->close();

                if ($count == 0) {
                    $update_sql = "DELETE FROM users WHERE id = ? AND role = 'customer'";
                } else {
                    $_SESSION['flash_message'] = 'Cannot delete guest with existing bookings!';
                    $_SESSION['flash_type'] = 'error';
                    header("Location: guests.php");
                    exit();
                }
                break;
            default:
                $update_sql = null;
        }

        if ($update_sql) {
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("i", $guest_id);
            if ($stmt->execute()) {
                $action_text = ucfirst(str_replace('_', ' ', $action));
                $_SESSION['flash_message'] = "Guest {$action_text}d successfully!";
                $_SESSION['flash_type'] = 'success';
            }
            $stmt->close();
        }

        header("Location: guests.php");
        exit();
    }
}

// Filter status
$status_filter = $_GET['status'] ?? '';
$search_term = $_GET['search'] ?? '';

// Bangun query dasar
$base_sql = "SELECT * FROM users WHERE role = 'customer'";
$params = [];
$types = "";

// Tambahkan filter status
if ($status_filter) {
    $base_sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Tambahkan pencarian
if ($search_term) {
    $base_sql .= " AND (full_name LIKE ? OR username LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like_term = "%" . sanitize($search_term, $conn) . "%";
    $params[] = $like_term;
    $params[] = $like_term;
    $params[] = $like_term;
    $params[] = $like_term;
    $types .= "ssss";
}

$base_sql .= " ORDER BY created_at DESC";

// Eksekusi query utama
$stmt = $conn->prepare($base_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$guests_result = $stmt->get_result();
$guests = $guests_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ambil statistik
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended
    FROM users WHERE role = 'customer'";
$stats_result = $conn->query($stats_sql);
$guest_stats = $stats_result->fetch_assoc();
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
/* === TAMBAHAN KHUSUS GUESTS === */
.guest-profile-card {
background: rgba(255,255,255,0.03);
border-radius: 15px;
padding: 25px;
margin-bottom: 20px;
border: 1px solid rgba(255,255,255,0.1);
transition: all 0.3s;
}
.guest-profile-card:hover {
border-color: var(--blue);
background: rgba(76, 201, 240, 0.05);
}
.guest-header {
display: flex;
gap: 20px;
margin-bottom: 25px;
}
.guest-avatar {
width: 80px;
height: 80px;
background: var(--blue);
border-radius: 50%;
display: flex;
align-items: center;
justify-content: center;
font-size: 32px;
font-weight: 700;
color: var(--navy);
flex-shrink: 0;
}
.guest-info {
flex: 1;
}
.guest-name {
font-size: 1.4rem;
color: white;
margin-bottom: 5px;
}
.guest-meta {
color: #aaa;
font-size: 0.9rem;
display: flex;
gap: 15px;
flex-wrap: wrap;
margin-bottom: 10px;
}
.guest-status {
display: inline-block;
padding: 4px 10px;
border-radius: 12px;
font-size: 0.75rem;
font-weight: 600;
text-transform: uppercase;
letter-spacing: 0.5px;
}
.status-active {
background: rgba(40, 167, 69, 0.2);
color: #28a745;
border: 1px solid rgba(40, 167, 69, 0.3);
}
.status-inactive {
background: rgba(108, 117, 125, 0.2);
color: #6c757d;
border: 1px solid rgba(108, 117, 125, 0.3);
}
.status-suspended {
background: rgba(220, 53, 69, 0.2);
color: #dc3545;
border: 1px solid rgba(220, 53, 69, 0.3);
}
.guest-details {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
gap: 15px;
margin-bottom: 20px;
}
.detail-group {
background: rgba(0,0,0,0.2);
padding: 15px;
border-radius: 8px;
}
.detail-label {
color: #aaa;
font-size: 0.8rem;
margin-bottom: 5px;
display: block;
}
.detail-value {
color: white;
font-weight: 500;
}
.guest-actions {
display: flex;
gap: 10px;
justify-content: flex-end;
border-top: 1px solid rgba(255,255,255,0.05);
padding-top: 20px;
}
.stats-grid {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
gap: 20px;
margin-bottom: 25px;
}
.stat-card {
background: var(--card-bg);
border-radius: 15px;
padding: 25px;
border: 1px solid rgba(255,255,255,0.1);
}
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
color: var(--blue);
}
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
.search-box {
background: rgba(76, 201, 240, 0.1);
border-radius: 15px;
padding: 25px;
margin-bottom: 30px;
border: 1px solid rgba(76, 201, 240, 0.2);
}
.search-form {
display: flex;
gap: 15px;
align-items: end;
}
.form-group {
flex: 1;
}
.form-label {
display: block;
color: #ccc;
font-size: 14px;
margin-bottom: 8px;
font-weight: 500;
}
.input-group {
display: flex;
gap: 10px;
}
.input-group input,
.form-control {
flex: 1;
padding: 10px 15px;
border-radius: 8px;
border: 1px solid rgba(255,255,255,0.1);
background: rgba(0,0,0,0.2);
color: white;
}
.guest-filters {
display: flex;
gap: 15px;
margin-top: 20px;
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
<p class="nav-label">GUESTS</p>
<a href="guests.php" class="nav-item active">
<i class="fas fa-users"></i> <span>Guests List</span>
</a>
<a href="active-guests.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'active-guests.php') ? 'active' : '' ?>">
<i class="fas fa-user-check"></i> <span>Active Guests</span>
</a>
</div>
<div class="nav-divider"></div>
<div class="nav-group">
<p class="nav-label">ROOMS</p>
<a href="rooms.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'rooms.php') ? 'active' : '' ?>">
<i class="fas fa-bed"></i> <span>Room Status</span>
</a>
<a href="room-management.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'room-management.php') ? 'active' : '' ?>">
<i class="fas fa-sliders-h"></i> <span>Room Management</span>
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

<!-- Stats Grid -->
<div class="stats-grid">
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-users"></i></div>
<div class="stat-value"><?= $guest_stats['total'] ?></div>
<div class="stat-label">Total Guests</div>
</div>
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-user-check"></i></div>
<div class="stat-value"><?= $guest_stats['active'] ?></div>
<div class="stat-label">Active Guests</div>
</div>
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-user-slash"></i></div>
<div class="stat-value"><?= $guest_stats['inactive'] + $guest_stats['suspended'] ?></div>
<div class="stat-label">Inactive/Suspended</div>
</div>
</div>

<!-- Search Section -->
<div class="search-box">
<h3 style="color: white; margin-bottom: 20px;"><i class="fas fa-search"></i> Search Guests</h3>
<form method="GET" class="search-form">
<div class="form-group">
<label class="form-label">Search by Name, Email, Phone, or Username</label>
<div class="input-group">
<input type="text" name="search" class="form-control"
placeholder="Enter guest name, email, phone, or username..."
value="<?= htmlspecialchars($search_term) ?>">
<button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 20px;">
<i class="fas fa-search"></i> Search
</button>
</div>
</div>
</form>
<div class="guest-filters">
<a href="guests.php" class="filter-badge <?= !$status_filter ? 'active' : '' ?>">All Guests (<?= $guest_stats['total'] ?>)</a>
<a href="?status=active" class="filter-badge <?= $status_filter === 'active' ? 'active' : '' ?>">Active (<?= $guest_stats['active'] ?>)</a>
<a href="?status=inactive" class="filter-badge <?= $status_filter === 'inactive' ? 'active' : '' ?>">Inactive (<?= $guest_stats['inactive'] ?>)</a>
<a href="?status=suspended" class="filter-badge <?= $status_filter === 'suspended' ? 'active' : '' ?>">Suspended (<?= $guest_stats['suspended'] ?>)</a>
</div>
</div>

<!-- Guests List -->
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-users"></i> Guest Profiles</h3>
<a href="add-guest.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add New Guest</a>
</div>
<div class="card-body">
<?php if (empty($guests)): ?>
<div class="empty-state">
<i class="fas fa-user-slash fa-2x"></i>
<h3>No Guests Found</h3>
<p>No guests match your criteria.</p>
</div>
<?php else: ?>
<?php foreach ($guests as $guest): 
    // Hitung total booking
    $booking_count = $conn->query("SELECT COUNT(*) FROM bookings WHERE user_id = " . $guest['id'])->fetch_row()[0];
?>
<div class="guest-profile-card">
<div class="guest-header">
<div class="guest-avatar"><?= strtoupper(substr($guest['full_name'] ?? $guest['username'], 0, 1)) ?></div>
<div class="guest-info">
<div class="guest-name"><?= htmlspecialchars($guest['full_name'] ?? $guest['username']) ?></div>
<div class="guest-meta">
<span><i class="fas fa-envelope"></i> <?= htmlspecialchars($guest['email']) ?></span>
<?php if ($guest['phone']): ?>
<span><i class="fas fa-phone"></i> <?= htmlspecialchars($guest['phone']) ?></span>
<?php endif; ?>
<span class="guest-status status-<?= $guest['status'] ?>"><?= ucfirst($guest['status']) ?></span>
</div>
<div style="color: #aaa; font-size: 0.85rem;">
<i class="fas fa-calendar-alt"></i> Joined: <?= date('d M Y', strtotime($guest['created_at'])) ?>
</div>
</div>
</div>
<div class="guest-details">
<div class="detail-group">
<span class="detail-label">Total Bookings</span>
<div class="detail-value"><?= $booking_count ?> bookings</div>
</div>
<?php if ($guest['phone']): ?>
<div class="detail-group">
<span class="detail-label">Phone Number</span>
<div class="detail-value"><?= htmlspecialchars($guest['phone']) ?></div>
</div>
<?php endif; ?>
<!-- Hanya tampilkan address jika kolom ada di database -->
<?php if (isset($guest['address']) && !empty($guest['address'])): ?>
<div class="detail-group">
<span class="detail-label">Address</span>
<div class="detail-value"><?= htmlspecialchars($guest['address']) ?></div>
</div>
<?php endif; ?>
<div class="detail-group">
<span class="detail-label">Account Status</span>
<div class="detail-value">
<span class="guest-status status-<?= $guest['status'] ?>"><?= ucfirst($guest['status']) ?></span>
</div>
</div>
</div>
<div class="guest-actions">
<a href="edit-guest.php?id=<?= $guest['id'] ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit</a>
<?php if ($guest['status'] === 'active'): ?>
<a href="?action=deactivate&id=<?= $guest['id'] ?>" class="btn btn-warning"
onclick="return confirm('Deactivate <?= addslashes(htmlspecialchars($guest['full_name'] ?? $guest['username'])) ?>?')">
<i class="fas fa-ban"></i> Deactivate
</a>
<?php else: ?>
<a href="?action=activate&id=<?= $guest['id'] ?>" class="btn btn-success"
onclick="return confirm('Activate <?= addslashes(htmlspecialchars($guest['full_name'] ?? $guest['username'])) ?>?')">
<i class="fas fa-check"></i> Activate
</a>
<?php endif; ?>
<?php if ($booking_count == 0): ?>
<a href="?action=delete&id=<?= $guest['id'] ?>" class="btn btn-danger"
onclick="return confirm('Delete <?= addslashes(htmlspecialchars($guest['full_name'] ?? $guest['username'])) ?> permanently?')">
<i class="fas fa-trash"></i> Delete
</a>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
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