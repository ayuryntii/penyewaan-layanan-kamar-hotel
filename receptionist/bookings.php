<?php
session_start();
require_once '../includes/config.php';
requireReceptionist();

$page_title = 'Manage Bookings';
$hotel_name = $config['hotel_name'] ?? 'Hotel System';

// Ambil data receptionist untuk sidebar
$user_id = $_SESSION['user_id'];
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist_result = $receptionist_stmt->get_result();
$receptionist = $receptionist_result->fetch_assoc();
// ❌ JANGAN TUTUP DI SINI — nanti ditutup sekali di akhir

// Ambil semua booking
$bookings = getAllBookings($conn);

// === EXPORT CSV ===
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="bookings_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    
    fputcsv($output, [
        'Booking ID',
        'Booking Code',
        'Guest Name',
        'Room Number',
        'Check-in',
        'Check-out',
        'Status',
        'Total Amount',
        'Payment Status'
    ]);
    
    foreach ($bookings as $b) {
        fputcsv($output, [
            $b['id'],
            $b['booking_code'],
            htmlspecialchars_decode($b['full_name'] ?? $b['username']),
            $b['room_number'],
            $b['check_in'],
            $b['check_out'],
            $b['booking_status'],
            $b['final_price'],
            $b['payment_status']
        ]);
    }
    fclose($output);
    exit;
}

// === EXPORT PDF (sederhana via print) ===
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $_SESSION['print_bookings'] = $bookings;
    header("Location: print-bookings.php");
    exit;
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
.alert-danger {
background: rgba(220, 53, 69, 0.2);
border-color: rgba(220, 53, 69, 0.3);
color: #dc3545;
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
<a href="bookings.php" class="nav-item active">
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
<?= getFlashMessage() ?>

<div style="display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap;">
    <a href="new-booking.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Booking</a>
    <a href="?export=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> Export CSV</a>
    <a href="?export=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Export PDF</a>
</div>

<div class="card">
<div class="card-body">
<?php if (!empty($bookings)): ?>
<div class="table-responsive">
<table class="table">
<thead>
<tr>
<th>Code</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th>
<th>Status</th><th>Total</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($bookings as $b): ?>
<tr>
<td><?= htmlspecialchars($b['booking_code']) ?></td>
<td><?= htmlspecialchars($b['full_name'] ?? $b['username']) ?></td>
<td>Room <?= htmlspecialchars($b['room_number']) ?></td>
<td><?= date('d M Y', strtotime($b['check_in'])) ?></td>
<td><?= date('d M Y', strtotime($b['check_out'])) ?></td>
<td><?= getBookingStatusBadge($b['booking_status']) ?></td>
<td><?= formatCurrency($b['final_price']) ?></td>
<td>
<a href="booking-details.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>
<?php if ($b['booking_status'] === 'confirmed'): ?>
<a href="process-checkin.php?booking_id=<?= $b['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-sign-in-alt"></i></a>
<?php elseif ($b['booking_status'] === 'checked_in'): ?>
<a href="process-checkout.php?booking_id=<?= $b['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-sign-out-alt"></i></a>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state">
<i class="fas fa-calendar-times fa-2x"></i>
<h3>No Bookings Found</h3>
<p>There are no bookings in the system.</p>
</div>
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
    const now = new Date();
    document.getElementById('currentTime').textContent = now.toLocaleTimeString('id-ID', {hour12:false});
}
setInterval(updateTime, 1000);
updateTime();
</script>

</body>
</html>
<?php
// ✅ TUTUP HANYA DI SINI — SATU KALI SAJA
if (isset($receptionist_stmt)) {
    $receptionist_stmt->close();
}
$conn->close();
?>