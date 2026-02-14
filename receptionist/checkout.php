<?php
session_start();
require_once '../includes/config.php';
requireReceptionist();

$user_id = $_SESSION['user_id'];
$page_title = 'Guest Check-out';
$hotel_name = $config['hotel_name'] ?? 'Hotel System';

// Ambil data receptionist — JANGAN TUTUP DI SINI
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist_result = $receptionist_stmt->get_result();
$receptionist = $receptionist_result->fetch_assoc();
// ❌ JANGAN TUTUP SEKARANG

// Pastikan fungsi helper tersedia
if (!function_exists('getTodaysCheckouts')) {
    function getTodaysCheckouts($conn) {
        $today = date('Y-m-d');
        $sql = "SELECT b.*, u.full_name, u.username, u.email, u.phone,
                       r.room_number, rc.name as room_type,
                       DATEDIFF(b.check_out, b.check_in) as total_nights,
                       b.final_price as total_price,
                       b.adults, b.children
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN rooms r ON b.room_id = r.id
                JOIN room_categories rc ON r.category_id = rc.id
                WHERE DATE(b.check_out) = ?
                AND b.booking_status = 'checked_in'
                ORDER BY b.check_out ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('getBookingServices')) {
    function getBookingServices($conn, $booking_id) {
        $sql = "SELECT *, 
                CASE WHEN service_id = 0 THEN 'Extra Charge' ELSE s.name END as name
                FROM booking_services bs
                LEFT JOIN services s ON bs.service_id = s.id
                WHERE bs.booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Ambil data
$today = date('Y-m-d');
$checkouts = getTodaysCheckouts($conn);

// Handle check-out
if (isset($_GET['checkout']) && isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    $booking = getBookingById($conn, $booking_id);
    
    if ($booking && $booking['booking_status'] == 'checked_in') {
        if (updateBookingStatus($conn, $booking_id, 'checked_out')) {
            updateRoomStatus($conn, $booking['room_id'], 'cleaning');
            
            // Log
            $log_sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'checkout', ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            if ($log_stmt) {
                $details = "Checked out booking #" . $booking['booking_code'] . " from room " . $booking['room_number'];
                $log_stmt->bind_param("is", $user_id, $details);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            $_SESSION['flash_message'] = 'Guest checked out successfully!';
            $_SESSION['flash_type'] = 'success';
            header("Location: checkout.php");
            exit();
        } else {
            $_SESSION['flash_message'] = 'Failed to check-out guest.';
            $_SESSION['flash_type'] = 'error';
        }
    } else {
        $_SESSION['flash_message'] = 'Booking not found or not checked in.';
        $_SESSION['flash_type'] = 'error';
    }
}

// Handle extra charge
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_charge'])) {
    $booking_id = intval($_POST['booking_id']);
    $description = sanitize($_POST['description'], $conn);
    $amount = floatval($_POST['amount']);
    
    if ($booking_id && $description && $amount > 0) {
        $service_sql = "INSERT INTO booking_services (booking_id, service_id, quantity, unit_price, total_price, notes, created_at)
                        VALUES (?, 0, 1, ?, ?, ?, NOW())";
        $service_stmt = $conn->prepare($service_sql);
        if ($service_stmt) {
            $service_stmt->bind_param("idds", $booking_id, $amount, $amount, $description);
            if ($service_stmt->execute()) {
                $_SESSION['flash_message'] = 'Extra charge added successfully!';
                $_SESSION['flash_type'] = 'success';
                header("Location: checkout.php?booking_id=" . $booking_id);
                exit();
            } else {
                $_SESSION['flash_message'] = 'Failed to add extra charge.';
                $_SESSION['flash_type'] = 'error';
            }
            $service_stmt->close();
        }
    }
}

// Get current booking
$current_booking = null;
$booking_services = [];
if (isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    $current_booking = getBookingById($conn, $booking_id);
    if ($current_booking) {
        $booking_services = getBookingServices($conn, $booking_id);
    }
}

// Handle search
$search_results = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = "%" . sanitize($_GET['search'], $conn) . "%";
    $search_sql = "SELECT b.*, u.full_name, u.username, u.email, u.phone,
                          r.room_number, rc.name as room_type,
                          DATEDIFF(b.check_out, b.check_in) as total_nights,
                          b.final_price as total_price,
                          b.adults, b.children
                   FROM bookings b
                   JOIN users u ON b.user_id = u.id
                   JOIN rooms r ON b.room_id = r.id
                   JOIN room_categories rc ON r.category_id = rc.id
                   WHERE (b.booking_code LIKE ? OR u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR r.room_number LIKE ?)
                   AND b.booking_status = 'checked_in'
                   ORDER BY b.check_out ASC
                   LIMIT 20";
    $search_stmt = $conn->prepare($search_sql);
    if ($search_stmt) {
        $search_stmt->bind_param("sssss", $search_term, $search_term, $search_term, $search_term, $search_term);
        $search_stmt->execute();
        $search_result = $search_stmt->get_result();
        while ($row = $search_result->fetch_assoc()) {
            $search_results[] = $row;
        }
        $search_stmt->close();
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
.card-body {
padding: 25px;
}
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
.badge {
padding: 5px 12px;
border-radius: 20px;
font-size: 0.75rem;
font-weight: 600;
text-transform: uppercase;
letter-spacing: 0.5px;
}
.badge-success { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.3); }
.badge-warning { background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.3); }
.badge-danger { background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.3); }
.badge-primary { background: rgba(76, 201, 240, 0.2); color: var(--blue); border: 1px solid rgba(76, 201, 240, 0.3); }
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
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-primary { background: var(--blue); color: var(--navy); }
.btn-success { background: var(--green); color: white; }
.btn-warning { background: var(--yellow); color: var(--navy); }
.btn-danger { background: var(--red); color: white; }
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
.alert-warning {
background: rgba(255, 193, 7, 0.2);
border-color: rgba(255, 193, 7, 0.3);
color: #ffc107;
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
.input-group input:focus,
.form-control:focus {
outline: none;
border-color: var(--blue);
}
.booking-card {
background: rgba(255,255,255,0.03);
border-radius: 15px;
padding: 25px;
margin-bottom: 20px;
border: 1px solid rgba(255,255,255,0.1);
transition: all 0.3s;
}
.booking-card:hover {
border-color: var(--blue);
background: rgba(76, 201, 240, 0.05);
}
.booking-header {
display: flex;
justify-content: space-between;
align-items: start;
margin-bottom: 20px;
}
.guest-info {
flex: 1;
}
.guest-name {
font-size: 1.3rem;
color: white;
margin-bottom: 5px;
}
.guest-details {
color: #aaa;
font-size: 0.9rem;
display: flex;
gap: 20px;
flex-wrap: wrap;
}
.room-info {
text-align: right;
}
.room-type {
font-size: 1.1rem;
color: var(--blue);
font-weight: 600;
}
.room-number {
color: #aaa;
font-size: 0.9rem;
}
.booking-details {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
gap: 15px;
margin-bottom: 20px;
}
.detail-item {
background: rgba(0,0,0,0.2);
padding: 12px 15px;
border-radius: 8px;
}
.detail-label {
color: #aaa;
font-size: 0.8rem;
margin-bottom: 5px;
}
.detail-value {
color: white;
font-weight: 500;
}
.booking-actions {
display: flex;
gap: 10px;
justify-content: flex-end;
}
.today-badge {
background: var(--green);
color: white;
padding: 3px 10px;
border-radius: 12px;
font-size: 0.8rem;
font-weight: 600;
margin-left: 10px;
}
.no-data {
text-align: center;
padding: 40px 20px;
color: #aaa;
}
.no-data i {
font-size: 48px;
margin-bottom: 15px;
opacity: 0.5;
}

/* === TAMBAHAN KHUSUS CHECKOUT === */
.extra-charges-section {
background: rgba(255,255,255,0.03);
border-radius: 12px;
padding: 25px;
margin: 25px 0;
border: 1px solid rgba(255,255,255,0.1);
}
.charges-header {
display: flex;
justify-content: space-between;
align-items: center;
margin-bottom: 20px;
}
.add-charge-btn {
background: rgba(76, 201, 240, 0.1);
border: 1px solid var(--blue);
color: var(--blue);
padding: 8px 15px;
border-radius: 6px;
text-decoration: none;
display: inline-flex;
align-items: center;
gap: 8px;
transition: all 0.3s;
cursor: pointer;
}
.add-charge-btn:hover {
background: rgba(76, 201, 240, 0.2);
}
.charges-list {
display: grid;
gap: 15px;
}
.charge-item {
background: rgba(0,0,0,0.2);
padding: 15px;
border-radius: 8px;
display: flex;
justify-content: space-between;
align-items: center;
border: 1px solid rgba(255,255,255,0.05);
}
.charge-info {
flex: 1;
}
.charge-name {
color: white;
font-weight: 500;
margin-bottom: 5px;
}
.charge-description {
color: #aaa;
font-size: 0.9rem;
}
.charge-amount {
font-weight: 600;
color: var(--blue);
font-size: 1.1rem;
}
.charge-actions {
display: flex;
gap: 8px;
margin-left: 15px;
}
.total-section {
background: rgba(76, 201, 240, 0.1);
border-radius: 15px;
padding: 25px;
margin-top: 25px;
border: 1px solid var(--blue);
}
.total-row {
display: flex;
justify-content: space-between;
align-items: center;
padding: 15px 0;
border-bottom: 1px solid rgba(255,255,255,0.1);
}
.total-row:last-child {
border-bottom: none;
font-weight: 700;
font-size: 1.3rem;
color: var(--blue);
padding-top: 20px;
}
.modal {
display: none;
position: fixed;
top: 0;
left: 0;
width: 100%;
height: 100%;
background: rgba(0,0,0,0.8);
z-index: 1000;
justify-content: center;
align-items: center;
padding: 20px;
}
.modal-content {
background: var(--card-bg);
border-radius: 15px;
width: 100%;
max-width: 500px;
border: 1px solid rgba(76, 201, 240, 0.2);
animation: modalFadeIn 0.3s;
}
@keyframes modalFadeIn {
from { opacity: 0; transform: translateY(-20px); }
to { opacity: 1; transform: translateY(0); }
}
.modal-header {
display: flex;
justify-content: space-between;
align-items: center;
padding: 20px 25px;
border-bottom: 1px solid rgba(255,255,255,0.1);
}
.modal-body {
padding: 25px;
}
.close-modal {
background: none;
border: none;
color: white;
font-size: 1.5rem;
cursor: pointer;
width: 40px;
height: 40px;
border-radius: 50%;
display: flex;
align-items: center;
justify-content: center;
transition: all 0.3s;
}
.close-modal:hover {
background: rgba(255,255,255,0.1);
}
.invoice-actions {
display: flex;
gap: 10px;
margin-top: 30px;
justify-content: center;
flex-wrap: wrap;
}
.no-charges {
text-align: center;
padding: 30px;
color: #777;
}
.no-charges i {
font-size: 36px;
margin-bottom: 10px;
opacity: 0.5;
}
.booking-details-expanded {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
gap: 20px;
margin: 25px 0;
}
.detail-card {
background: rgba(255,255,255,0.03);
padding: 20px;
border-radius: 10px;
border: 1px solid rgba(255,255,255,0.1);
}
.detail-card .detail-label {
color: #aaa;
font-size: 0.9rem;
margin-bottom: 5px;
}
.detail-card .detail-value {
color: white;
font-size: 1.1rem;
font-weight: 500;
}
.guest-photo-placeholder {
width: 100px;
height: 100px;
background: rgba(76, 201, 240, 0.1);
border-radius: 10px;
display: flex;
align-items: center;
justify-content: center;
font-size: 36px;
color: var(--blue);
margin-bottom: 15px;
}

@media (max-width: 992px) {
.sidebar { transform: translateX(-100%); }
.sidebar.active { transform: translateX(0); }
.main-content { margin-left: 0; }
.menu-toggle { display: block; }
}
@media (max-width: 768px) {
.booking-header { flex-direction: column; gap: 15px; }
.room-info { text-align: left; }
.booking-actions { flex-direction: column; }
.booking-actions .btn { width: 100%; justify-content: center; }
.invoice-actions { flex-direction: column; }
.invoice-actions .btn { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<div class="receptionist-wrapper">

<!-- Sidebar Lengkap -->
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
<a href="checkout.php" class="nav-item active">
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
<a href="guests.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'guests.php') ? 'active' : '' ?>">
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
    $alertClass = 'alert-warning'; // default
    if ($_SESSION['flash_type'] === 'success') {
        $alertClass = 'alert-success';
    } elseif ($_SESSION['flash_type'] === 'error') {
        $alertClass = 'alert-danger';
    }
?>
    <div class="alert <?= $alertClass ?>">
        <i class="fas fa-info-circle"></i> <?= htmlspecialchars($_SESSION['flash_message']) ?>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>

<!-- Search Section -->
<div class="search-box">
<h3 style="color: white; margin-bottom: 20px;">
<i class="fas fa-search"></i> Search Guest for Check-out
</h3>
<form method="GET" class="search-form">
<div class="form-group">
<label class="form-label">Search by Booking Code, Guest Name, Email, or Room Number</label>
<div class="input-group">
<input type="text" name="search" class="form-control"
placeholder="Enter booking code, guest name, email, or room number..."
value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
<button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 20px;">
<i class="fas fa-search"></i> Search
</button>
</div>
</div>
</form>
</div>

<!-- Search Results -->
<?php if (!empty($search_results)): ?>
<div class="search-results">
<h3>Search Results <span class="today-badge"><?= count($search_results) ?> found</span></h3>
<?php foreach ($search_results as $booking): ?>
<div class="booking-card">
<div class="booking-header">
<div class="guest-info">
<div class="guest-name"><?= htmlspecialchars($booking['full_name'] ?? $booking['username']) ?></div>
<div class="guest-details">
<span><i class="fas fa-tag"></i> <?= $booking['booking_code'] ?></span>
<span><i class="fas fa-phone"></i> <?= $booking['phone'] ?? 'N/A' ?></span>
<span><i class="fas fa-envelope"></i> <?= $booking['email'] ?></span>
</div>
</div>
<div class="room-info">
<div class="room-type"><?= htmlspecialchars($booking['room_type']) ?></div>
<div class="room-number">Room <?= $booking['room_number'] ?></div>
</div>
</div>
<div class="booking-details">
<div class="detail-item">
<div class="detail-label">Check-in Date</div>
<div class="detail-value"><?= date('d M Y', strtotime($booking['check_in'])) ?></div>
</div>
<div class="detail-item">
<div class="detail-label">Check-out Date</div>
<div class="detail-value"><?= date('d M Y', strtotime($booking['check_out'])) ?></div>
</div>
<div class="detail-item">
<div class="detail-label">Nights Stayed</div>
<div class="detail-value">
<?php
$check_in = new DateTime($booking['check_in']);
$check_out = new DateTime($booking['check_out']);
$interval = $check_in->diff($check_out);
echo $interval->days . ' nights';
?>
</div>
</div>
<div class="detail-item">
<div class="detail-label">Room Rate</div>
<div class="detail-value"><?= formatCurrency($booking['total_price']) ?></div>
</div>
</div>
<div class="booking-actions">
<a href="?booking_id=<?= $booking['id'] ?>" class="btn btn-primary">
<i class="fas fa-calculator"></i> Process Check-out
</a>
<a href="booking-details.php?id=<?= $booking['id'] ?>" class="btn btn-secondary">
<i class="fas fa-eye"></i> View Details
</a>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Current Booking -->
<?php if ($current_booking): ?>
<div class="card">
<div class="card-header">
<h3 class="card-title">
<i class="fas fa-user-check"></i> Guest Check-out Details
<span class="today-badge">Room <?= $current_booking['room_number'] ?></span>
</h3>
<div class="card-actions">
<span style="color: #aaa; font-size: 0.9rem;">
<i class="fas fa-calendar-alt"></i> <?= date('l, d F Y') ?>
</span>
</div>
</div>
<div class="card-body">
<div style="display: flex; gap: 30px; align-items: start; margin-bottom: 30px;">
<div><div class="guest-photo-placeholder"><i class="fas fa-user"></i></div></div>
<div style="flex: 1;">
<h3 style="color: white; margin-bottom: 15px; font-size: 1.5rem;">
<?= htmlspecialchars($current_booking['full_name'] ?? $current_booking['username']) ?>
</h3>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
<div><div style="color: #aaa; font-size: 0.9rem; margin-bottom: 5px;">Booking Code</div><div style="color: white; font-weight: 500;"><?= $current_booking['booking_code'] ?></div></div>
<div><div style="color: #aaa; font-size: 0.9rem; margin-bottom: 5px;">Email</div><div style="color: white; font-weight: 500;"><?= $current_booking['email'] ?></div></div>
<div><div style="color: #aaa; font-size: 0.9rem; margin-bottom: 5px;">Phone</div><div style="color: white; font-weight: 500;"><?= $current_booking['phone'] ?? 'N/A' ?></div></div>
<div><div style="color: #aaa; font-size: 0.9rem; margin-bottom: 5px;">Room</div><div style="color: var(--blue); font-weight: 500;">Room <?= $current_booking['room_number'] ?> (<?= $current_booking['room_type'] ?>)</div></div>
</div>
</div>
</div>

<div class="booking-details-expanded">
<div class="detail-card"><div class="detail-label">Check-in Date</div><div class="detail-value"><?= date('d M Y', strtotime($current_booking['check_in'])) ?></div></div>
<div class="detail-card"><div class="detail-label">Check-out Date</div><div class="detail-value"><?= date('d M Y', strtotime($current_booking['check_out'])) ?></div></div>
<div class="detail-card"><div class="detail-label">Total Nights</div><div class="detail-value"><?= $current_booking['total_nights'] ?> nights</div></div>
<div class="detail-card"><div class="detail-label">Guests</div><div class="detail-value">
<?php
$guests = [];
$guests[] = $current_booking['adults'] . ' adult' . ($current_booking['adults'] > 1 ? 's' : '');
if ($current_booking['children'] > 0) {
    $guests[] = $current_booking['children'] . ' child' . ($current_booking['children'] > 1 ? 'ren' : '');
}
echo htmlspecialchars(implode(', ', $guests));
?>
</div></div>
</div>

<div class="extra-charges-section">
<div class="charges-header">
<h4 style="color: white; margin: 0;"><i class="fas fa-receipt"></i> Extra Charges & Services</h4>
<button class="add-charge-btn" onclick="showAddChargeModal(<?= $current_booking['id'] ?>)"><i class="fas fa-plus"></i> Add Charge</button>
</div>
<?php if (!empty($booking_services)): ?>
<div class="charges-list">
<?php
$extra_charges_total = 0;
foreach ($booking_services as $service):
$extra_charges_total += $service['total_price'];
?>
<div class="charge-item">
<div class="charge-info">
<div class="charge-name"><?= htmlspecialchars($service['name'] ?? 'Extra Charge') ?></div>
<div class="charge-description"><?= htmlspecialchars($service['notes'] ?? 'Additional service charge') ?><?php if ($service['quantity'] > 1): ?><span style="color: #777;"> × <?= $service['quantity'] ?></span><?php endif; ?></div>
</div>
<div class="charge-amount"><?= formatCurrency($service['total_price']) ?></div>
<div class="charge-actions">
<button class="btn btn-sm" style="padding: 3px 8px; background: rgba(255,255,255,0.05); color: #aaa; border: none; border-radius: 4px;" onclick="editCharge(<?= $service['id'] ?>)"><i class="fas fa-edit"></i></button>
<a href="delete-charge.php?id=<?= $service['id'] ?>&booking_id=<?= $current_booking['id'] ?>" class="btn btn-sm" style="padding: 3px 8px; background: rgba(220,53,69,0.2); color: #dc3545; border: none; border-radius: 4px;" onclick="return confirm('Delete this charge?')"><i class="fas fa-trash"></i></a>
</div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="no-charges"><i class="fas fa-receipt fa-2x"></i><div>No extra charges added</div><p style="color: #777; font-size: 0.9rem; margin-top: 10px;">Click "Add Charge" to add extra services or fees</p></div>
<?php endif; ?>
</div>

<?php
$room_total = $current_booking['total_price'];
$tax_rate = 10;
$tax_amount = ($room_total + $extra_charges_total) * ($tax_rate / 100);
$grand_total = $room_total + $extra_charges_total + $tax_amount;
?>

<div class="total-section">
<div class="total-row"><span>Room Charges (<?= $current_booking['total_nights'] ?> nights):</span><span><?= formatCurrency($room_total) ?></span></div>
<div class="total-row"><span>Extra Charges:</span><span><?= formatCurrency($extra_charges_total) ?></span></div>
<div class="total-row"><span>Subtotal:</span><span><?= formatCurrency($room_total + $extra_charges_total) ?></span></div>
<div class="total-row"><span>Tax (<?= $tax_rate ?>%):</span><span><?= formatCurrency($tax_amount) ?></span></div>
<div class="total-row"><span>Grand Total:</span><span><?= formatCurrency($grand_total) ?></span></div>
</div>

<div class="invoice-actions">
<a href="?checkout=true&booking_id=<?= $current_booking['id'] ?>" class="btn btn-success" onclick="return confirm('Complete check-out for <?= addslashes(htmlspecialchars($current_booking['full_name'] ?? $current_booking['username'])) ?>?')"><i class="fas fa-check-circle"></i> Complete Check-out</a>
<a href="print-invoice.php?booking_id=<?= $current_booking['id'] ?>" class="btn btn-primary" target="_blank"><i class="fas fa-print"></i> Print Invoice</a>
<a href="checkout.php" class="btn" style="background: rgba(255,255,255,0.1); color: white;"><i class="fas fa-times"></i> Cancel</a>
</div>

</div>
</div>
<?php endif; ?>

<!-- Today's Check-outs -->
<div class="card">
<div class="card-header">
<h3 class="card-title">
<i class="fas fa-calendar-day"></i> Today's Scheduled Check-outs
<span class="today-badge"><?= count($checkouts) ?> guests</span>
</h3>
<div class="card-actions">
<span style="color: #aaa; font-size: 0.9rem;"><i class="fas fa-calendar-alt"></i> <?= date('l, d F Y') ?></span>
</div>
</div>
<div class="card-body">
<?php if (empty($checkouts)): ?>
<div class="no-data"><i class="fas fa-user-clock fa-3x"></i><h3 style="color: #aaa; margin: 15px 0 10px 0;">No Check-outs Today</h3><p style="color: #777;">No guests are scheduled to check-out today.</p></div>
<?php else: ?>
<?php foreach ($checkouts as $checkout): ?>
<div class="booking-card">
<div class="booking-header">
<div class="guest-info">
<div class="guest-name"><?= htmlspecialchars($checkout['full_name'] ?? $checkout['username']) ?></div>
<div class="guest-details">
<span><i class="fas fa-tag"></i> <?= $checkout['booking_code'] ?></span>
<span><i class="fas fa-phone"></i> <?= $checkout['phone'] ?? 'N/A' ?></span>
<span><i class="fas fa-calendar-alt"></i> Checked-in: <?= date('d M Y', strtotime($checkout['check_in'])) ?></span>
</div>
</div>
<div class="room-info">
<div class="room-type"><?= htmlspecialchars($checkout['room_type']) ?></div>
<div class="room-number">Room <?= $checkout['room_number'] ?></div>
</div>
</div>
<div class="booking-details">
<div class="detail-item"><div class="detail-label">Check-out Time</div><div class="detail-value"><?= date('H:i', strtotime($checkout['check_out'])) ?></div></div>
<div class="detail-item"><div class="detail-label">Nights Stayed</div><div class="detail-value"><?php
$check_in = new DateTime($checkout['check_in']);
$check_out = new DateTime($checkout['check_out']);
$interval = $check_in->diff($check_out);
echo $interval->days . ' nights';
?></div></div>
<div class="detail-item"><div class="detail-label">Room Rate</div><div class="detail-value"><?= formatCurrency($checkout['total_price']) ?></div></div>
<div class="detail-item"><div class="detail-label">Payment Status</div><div class="detail-value"><?= getPaymentStatusBadge($checkout['payment_status']) ?></div></div>
</div>
<div class="booking-actions">
<a href="?booking_id=<?= $checkout['id'] ?>" class="btn btn-primary"><i class="fas fa-calculator"></i> Process Check-out</a>
<a href="?checkout=true&booking_id=<?= $checkout['id'] ?>" class="btn btn-warning" onclick="return confirm('Quick check-out <?= addslashes(htmlspecialchars($checkout['full_name'] ?? $checkout['username'])) ?>?')"><i class="fas fa-sign-out-alt"></i> Quick Check-out</a>
<a href="booking-details.php?id=<?= $checkout['id'] ?>" class="btn btn-secondary"><i class="fas fa-eye"></i> View Details</a>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>

</div>
</main>
</div>

<!-- Add Charge Modal -->
<div class="modal" id="addChargeModal">
<div class="modal-content">
<div class="modal-header">
<h3 style="color: white; margin: 0;">Add Extra Charge</h3>
<button class="close-modal" onclick="closeModal()">&times;</button>
</div>
<div class="modal-body">
<form method="POST" action="">
<input type="hidden" name="add_charge" value="1">
<input type="hidden" id="modal_booking_id" name="booking_id">
<div style="margin-bottom: 20px;">
<label style="color: #ccc; font-size: 14px; margin-bottom: 5px; display: block;">Description *</label>
<input type="text" name="description" class="form-control" placeholder="e.g., Mini bar, Laundry, Room Service, Damage fee" required>
</div>
<div style="margin-bottom: 25px;">
<label style="color: #ccc; font-size: 14px; margin-bottom: 5px; display: block;">Amount (Rp) *</label>
<input type="number" name="amount" class="form-control" placeholder="Enter amount" min="0" step="1000" required>
</div>
<div style="display: flex; gap: 10px; justify-content: flex-end;">
<button type="button" class="btn" style="background: rgba(255,255,255,0.1); color: white;" onclick="closeModal()">Cancel</button>
<button type="submit" class="btn btn-primary">Add Charge</button>
</div>
</form>
</div>
</div>
</div>

<script>
document.getElementById('menuToggle').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('active');
});

function showAddChargeModal(bookingId) {
    document.getElementById('modal_booking_id').value = bookingId;
    document.getElementById('addChargeModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('addChargeModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('addChargeModal');
    if (event.target === modal) closeModal();
}

function updateTime() {
    const now = new Date();
    document.getElementById('currentTime').textContent = now.toLocaleTimeString('id-ID', {hour12:false});
}
setInterval(updateTime, 1000);
updateTime();
</script>

</body>
</html>
<?php
// ✅ TUTUP HANYA DI SINI
if (isset($receptionist_stmt)) {
    $receptionist_stmt->close();
}
$conn->close();
?>