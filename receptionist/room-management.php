<?php
// receptionist/room-management.php - FIXED TEMPLATE (MATCH DASHBOARD)
session_start();
require_once '../includes/config.php';
requireReceptionist();

$user_id = $_SESSION['user_id'];
$page_title = 'Room Management';

// Get receptionist data
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist_result = $receptionist_stmt->get_result();
$receptionist = $receptionist_result->fetch_assoc();
$receptionist_stmt->close();

// Status yang didukung
$allowed_status = ['available','occupied','reserved','maintenance','cleaning'];

// Filter & search
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

// UPDATE STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $room_id = intval($_POST['room_id']);
    $new_status = trim($_POST['new_status']);

    if (in_array($new_status, $allowed_status, true)) {
        $stmt = $conn->prepare("UPDATE rooms SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $room_id);
        $stmt->execute();
        $stmt->close();

        header("Location: room-management.php?success=1");
        exit;
    }
}

// Query rooms (join kategori biar ada type + harga)
$sql = "SELECT r.id, r.room_number, r.status,
               rc.name AS room_type,
               rc.base_price AS price_per_night
        FROM rooms r
        LEFT JOIN room_categories rc ON r.category_id = rc.id
        WHERE 1=1";

$types = "";
$params = [];

if ($q !== '') {
    $sql .= " AND (r.room_number LIKE ? OR rc.name LIKE ?)";
    $types .= "ss";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if ($statusFilter !== '') {
    $sql .= " AND r.status = ?";
    $types .= "s";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY r.room_number ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$rooms = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Badge status
function getRoomStatusBadge($status) {
    $s = strtolower(trim($status));

    if ($s === 'available') return '<span class="badge badge-success">Available</span>';
    if ($s === 'occupied') return '<span class="badge badge-danger">Occupied</span>';
    if ($s === 'reserved') return '<span class="badge badge-warning">Reserved</span>';
    if ($s === 'maintenance') return '<span class="badge badge-secondary">Maintenance</span>';
    if ($s === 'cleaning') return '<span class="badge badge-info">Cleaning</span>';

    return '<span class="badge badge-primary">Unknown</span>';
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
/* ==== TEMPLATE CSS SAMA PERSIS DARI DASHBOARD ==== */
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

/* Sidebar */
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

/* Main */
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

/* Card */
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

/* Table */
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
vertical-align: middle;
}
.table tbody tr:hover {
background: rgba(76, 201, 240, 0.05);
}
.table tbody tr:last-child td {
border-bottom: none;
}

/* Badges */
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
.badge-danger  { background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.3); }
.badge-info    { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid rgba(23, 162, 184, 0.3); }
.badge-primary { background: rgba(76, 201, 240, 0.2); color: var(--blue); border: 1px solid rgba(76, 201, 240, 0.3); }
.badge-secondary { background: rgba(108, 117, 125, 0.2); color: #6c757d; border: 1px solid rgba(108, 117, 125, 0.3); }

/* Buttons */
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
.btn-secondary { background: var(--gray); color: white; }
.btn-primary:hover { background: #3abde0; transform: translateY(-1px); }
.btn-success:hover { background: #218838; transform: translateY(-1px); }

/* Forms */
.form-row{
display:flex;
gap:12px;
flex-wrap:wrap;
margin-bottom: 15px;
}
.form-control, .form-select{
padding: 12px 14px;
border-radius: 10px;
border: 1px solid rgba(255,255,255,0.1);
background: rgba(0,0,0,0.25);
color: #fff;
outline:none;
}
.form-control{
min-width: 280px;
flex: 1;
}
.form-select{
min-width: 200px;
}
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
.logout-btn:hover { background: rgba(231, 76, 60, 0.3); transform: translateY(-1px); }

/* responsive */
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
<div class="sidebar-logo">
<i class="fas fa-concierge-bell"></i>
</div>
<div class="sidebar-title">
<h3><?= htmlspecialchars($hotel_name) ?></h3>
<p>Receptionist Portal</p>
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
<a href="room-management.php" class="nav-item active">
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

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
<i class="fas fa-check-circle"></i> Room status updated successfully!
</div>
<?php endif; ?>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-sliders-h"></i> Manage Rooms</h3>
</div>
<div class="card-body">

<form method="GET" class="form-row">
<input class="form-control" type="text" name="q" placeholder="Search room number or type..." value="<?= htmlspecialchars($q) ?>">
<select class="form-select" name="status">
<option value="">All Status</option>
<?php foreach($allowed_status as $st): ?>
<option value="<?= $st ?>" <?= ($statusFilter===$st)?'selected':'' ?>><?= ucfirst($st) ?></option>
<?php endforeach; ?>
</select>
<button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
<a class="btn btn-secondary" href="room-management.php"><i class="fas fa-rotate-left"></i> Reset</a>
</form>

<div class="table-responsive">
<table class="table">
<thead>
<tr>
<th>Room No</th>
<th>Type</th>
<th>Price/Night</th>
<th>Status</th>
<th style="width:260px;">Update Status</th>
</tr>
</thead>
<tbody>
<?php if (!empty($rooms)): ?>
<?php foreach($rooms as $room): ?>
<tr>
<td><strong><?= htmlspecialchars($room['room_number']) ?></strong></td>
<td><?= htmlspecialchars($room['room_type'] ?? '-') ?></td>
<td>
<?php
if (isset($room['price_per_night']) && $room['price_per_night'] !== null) {
    echo formatCurrency($room['price_per_night']);
} else {
    echo '-';
}
?>
</td>
<td><?= getRoomStatusBadge($room['status']) ?></td>
<td>
<form method="POST" style="display:flex; gap:8px; align-items:center;">
<input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
<select name="new_status" class="form-select" style="min-width:140px;">
<?php foreach($allowed_status as $st): ?>
<option value="<?= $st ?>" <?= ($room['status']===$st)?'selected':'' ?>><?= ucfirst($st) ?></option>
<?php endforeach; ?>
</select>
<button type="submit" name="update_status" class="btn btn-success btn-sm">
<i class="fas fa-sync-alt"></i> Update
</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="5" style="text-align:center; color:#aaa; padding:30px;">
<i class="fas fa-bed fa-2x" style="margin-bottom:10px;"></i>
<div>No rooms found.</div>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>

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
const timeString = now.toLocaleTimeString('id-ID', {
hour: '2-digit',
minute: '2-digit',
second: '2-digit'
});
document.getElementById('currentTime').textContent = timeString;
}
setInterval(updateTime, 1000);
updateTime();
</script>

</body>
</html>
