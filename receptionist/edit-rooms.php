<?php
session_start();
require_once '../includes/config.php';
requireReceptionist();

$user_id = $_SESSION['user_id'];
$page_title = 'Edit Room';
$hotel_name = $config['hotel_name'] ?? 'Hotel System';

// Ambil data receptionist
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist_result = $receptionist_stmt->get_result();
$receptionist = $receptionist_result->fetch_assoc();
$receptionist_stmt->close();

// Ambil room ID
$room_id = $_GET['id'] ?? null;
if (!$room_id || !is_numeric($room_id)) {
    $_SESSION['flash_message'] = 'Invalid room ID.';
    $_SESSION['flash_type'] = 'error';
    header("Location: rooms.php");
    exit();
}

// Ambil data kamar
$room_sql = "SELECT r.*, rc.name as category_name, rc.id as category_id
             FROM rooms r
             JOIN room_categories rc ON r.category_id = rc.id
             WHERE r.id = ?";
$room_stmt = $conn->prepare($room_sql);
$room_stmt->bind_param("i", $room_id);
$room_stmt->execute();
$room_result = $room_stmt->get_result();
$room = $room_result->fetch_assoc();

if (!$room) {
    $_SESSION['flash_message'] = 'Room not found.';
    $_SESSION['flash_type'] = 'error';
    header("Location: rooms.php");
    exit();
}
$room_stmt->close();

// Ambil semua kategori kamar
$category_sql = "SELECT * FROM room_categories ORDER BY name";
$categories = $conn->query($category_sql)->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_number = sanitize($_POST['room_number'], $conn);
    $category_id = intval($_POST['category_id']);
    $floor = intval($_POST['floor']);
    $bed_type = sanitize($_POST['bed_type'], $conn);
    $view_type = sanitize($_POST['view_type'], $conn);
    $smoking = isset($_POST['smoking']) ? 1 : 0;

    // Validasi
    if (empty($room_number) || $category_id <= 0 || $floor <= 0) {
        $_SESSION['flash_message'] = 'Please fill all required fields.';
        $_SESSION['flash_type'] = 'error';
    } else {
        // Cek apakah nomor kamar sudah dipakai (kecuali milik sendiri)
        $check_sql = "SELECT id FROM rooms WHERE room_number = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $room_number, $room_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $_SESSION['flash_message'] = 'Room number already exists.';
            $_SESSION['flash_type'] = 'error';
        } else {
            $update_sql = "UPDATE rooms SET 
                room_number = ?, 
                category_id = ?, 
                floor = ?, 
                bed_type = ?, 
                view_type = ?, 
                smoking = ?
                WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("siiissi", $room_number, $category_id, $floor, $bed_type, $view_type, $smoking, $room_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['flash_message'] = 'Room updated successfully!';
                $_SESSION['flash_type'] = 'success';
                header("Location: rooms.php");
                exit();
            } else {
                $_SESSION['flash_message'] = 'Failed to update room.';
                $_SESSION['flash_type'] = 'error';
            }
            $update_stmt->close();
        }
        $check_stmt->close();
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
.form-group {
margin-bottom: 20px;
}
.form-label {
display: block;
color: #ccc;
font-size: 14px;
margin-bottom: 8px;
font-weight: 500;
}
.form-control,
.form-select {
width: 100%;
padding: 12px 15px;
background: rgba(255,255,255,0.05);
border: 1px solid rgba(255,255,255,0.1);
border-radius: 8px;
color: white;
font-size: 14px;
transition: all 0.3s;
}
.form-control:focus,
.form-select:focus {
outline: none;
border-color: var(--blue);
background: rgba(76, 201, 240, 0.05);
}
.form-check {
display: flex;
align-items: center;
gap: 10px;
margin-top: 10px;
}
.form-check input {
width: 18px;
height: 18px;
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
/* === TAMBAHAN KHUSUS EDIT ROOM === */
.room-details-card {
background: rgba(255,255,255,0.03);
border-radius: 15px;
padding: 25px;
border: 1px solid rgba(255,255,255,0.1);
}
.room-header {
display: flex;
justify-content: space-between;
align-items: center;
margin-bottom: 20px;
}
.room-number {
font-size: 1.4rem;
color: white;
font-weight: 600;
}
.room-category {
color: var(--blue);
font-size: 0.9rem;
}
.room-price {
color: #aaa;
font-size: 0.9rem;
}
.room-status {
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
<a href="rooms.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'rooms.php') ? 'active' : '' ?>">
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

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-edit"></i> Edit Room Details</h3>
</div>
<div class="card-body">
<form method="POST">
<div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
<div class="form-group">
<label class="form-label">Room Number *</label>
<input type="text" name="room_number" class="form-control" value="<?= htmlspecialchars($room['room_number']) ?>" required>
</div>
<div class="form-group">
<label class="form-label">Room Category *</label>
<select name="category_id" class="form-select" required>
<?php foreach ($categories as $cat): ?>
<option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $room['category_id']) ? 'selected' : '' ?>>
<?= htmlspecialchars($cat['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
</div>

<div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
<div class="form-group">
<label class="form-label">Floor *</label>
<input type="number" name="floor" class="form-control" value="<?= $room['floor'] ?>" min="1" required>
</div>
<div class="form-group">
<label class="form-label">Bed Type</label>
<input type="text" name="bed_type" class="form-control" value="<?= htmlspecialchars($room['bed_type']) ?>">
</div>
<div class="form-group">
<label class="form-label">View Type</label>
<input type="text" name="view_type" class="form-control" value="<?= htmlspecialchars($room['view_type']) ?>">
</div>
</div>

<div class="form-group">
<label class="form-label">Smoking Allowed?</label>
<div class="form-check">
<input type="checkbox" name="smoking" id="smoking" <?= $room['smoking'] ? 'checked' : '' ?>>
<label for="smoking" style="color: #ccc;">Yes, this is a smoking room</label>
</div>
</div>

<div style="display: flex; gap: 10px; margin-top: 20px;">
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Room</button>
<a href="rooms.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
</div>
</form>
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