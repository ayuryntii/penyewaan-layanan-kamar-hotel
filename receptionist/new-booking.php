<?php
session_start();
require_once '../includes/config.php';
requireReceptionist();

$user_id = $_SESSION['user_id'];
$page_title = 'New Booking';
$hotel_name = $config['hotel_name'] ?? 'Hotel System';

// Ambil data receptionist
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist_result = $receptionist_stmt->get_result();
$receptionist = $receptionist_result->fetch_assoc();
$receptionist_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guest_id = intval($_POST['guest_id']);
    $room_id = intval($_POST['room_id']);
    $check_in = sanitize($_POST['check_in'], $conn);
    $check_out = sanitize($_POST['check_out'], $conn);
    $adults = intval($_POST['adults']);
    $children = intval($_POST['children']);
    $special_requests = sanitize($_POST['special_requests'], $conn);
    $payment_method = sanitize($_POST['payment_method'], $conn);
    $payment_status = sanitize($_POST['payment_status'], $conn);

    // Validasi
    if (strtotime($check_in) >= strtotime($check_out)) {
        $_SESSION['flash_message'] = 'Check-out date must be after check-in date!';
        $_SESSION['flash_type'] = 'error';
    } else {
        // Cek ketersediaan kamar
        if (!checkRoomAvailability($conn, $room_id, $check_in, $check_out)) {
            $_SESSION['flash_message'] = 'Selected room is not available for these dates!';
            $_SESSION['flash_type'] = 'error';
        } else {
            // Ambil harga kamar
            $room_sql = "SELECT rc.base_price FROM rooms r
                         JOIN room_categories rc ON r.category_id = rc.id
                         WHERE r.id = ?";
            $room_stmt = $conn->prepare($room_sql);
            $room_stmt->bind_param("i", $room_id);
            $room_stmt->execute();
            $room = $room_stmt->get_result()->fetch_assoc();
            $room_stmt->close();

            if ($room) {
                $nights = max(1, (strtotime($check_out) - strtotime($check_in)) / 86400);
                $total_price = $room['base_price'] * $nights;
                $booking_code = generateBookingCode();

                // Simpan booking
                $booking_sql = "INSERT INTO bookings (
                    booking_code, user_id, room_id, check_in, check_out,
                    total_nights, adults, children, total_price, final_price,
                    special_requests, booking_status, payment_status, payment_method, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, NOW())";

                $booking_stmt = $conn->prepare($booking_sql);
                $booking_stmt->bind_param(
                    "siissiiiddsss",
                    $booking_code, $guest_id, $room_id, $check_in, $check_out,
                    $nights, $adults, $children, $total_price, $total_price,
                    $special_requests, $payment_status, $payment_method
                );

                if ($booking_stmt->execute()) {
                    updateRoomStatus($conn, $room_id, 'reserved');
                    $_SESSION['flash_message'] = "Booking created successfully! Code: $booking_code";
                    $_SESSION['flash_type'] = 'success';
                    header("Location: booking-details.php?id=" . $booking_stmt->insert_id);
                    exit();
                } else {
                    $_SESSION['flash_message'] = 'Failed to create booking. Please try again.';
                    $_SESSION['flash_type'] = 'error';
                }
                $booking_stmt->close();
            }
        }
    }
}

// Ambil data untuk form
$guests_sql = "SELECT id, full_name, username, email, phone 
               FROM users 
               WHERE role = 'customer' AND status = 'active' 
               ORDER BY full_name";
$guests = $conn->query($guests_sql)->fetch_all(MYSQLI_ASSOC);

$categories = getAllRoomCategories($conn);
$rooms = [];
$category_id = $_POST['category_id'] ?? null;
if ($category_id) {
    $rooms_sql = "SELECT id, room_number FROM rooms 
                  WHERE category_id = ? AND status = 'available'
                  ORDER BY room_number";
    $rooms_stmt = $conn->prepare($rooms_sql);
    $rooms_stmt->bind_param("i", $category_id);
    $rooms_stmt->execute();
    $rooms = $rooms_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $rooms_stmt->close();
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
box-shadow: 0 0 0 3px rgba(76, 201, 240, 0.1);
}
.form-textarea {
min-height: 120px;
resize: vertical;
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
@media (max-width: 992px) {
.sidebar { transform: translateX(-100%); }
.sidebar.active { transform: translateX(0); }
.main-content { margin-left: 0; }
.menu-toggle { display: block; }
}
/* === TAMBahan: Style Dropdown Agar Terbaca Jelas */
.form-select {
font-size: 14px;
color: white;
background: rgba(255,255,255,0.05);
border: 1px solid rgba(255,255,255,0.1);
}
.form-select option {
background: var(--card-bg);
color: white;
padding: 10px;
}
.form-select option:hover {
background: rgba(76, 201, 240, 0.1);
color: var(--navy);
}
/* === Tombol Create Booking & Cancel */
.create-btn {
background: var(--blue);
color: var(--navy);
padding: 12px 24px;
border-radius: 8px;
font-weight: 600;
text-decoration: none;
display: inline-flex;
align-items: center;
gap: 8px;
transition: all 0.3s;
}
.create-btn:hover {
background: #3abde0;
transform: translateY(-2px);
}
.cancel-btn {
background: rgba(255,255,255,0.1);
color: white;
padding: 12px 24px;
border-radius: 8px;
font-weight: 600;
text-decoration: none;
display: inline-flex;
align-items: center;
gap: 8px;
transition: all 0.3s;
}
.cancel-btn:hover {
background: rgba(255,255,255,0.2);
color: var(--blue);
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
<a href="new-booking.php" class="nav-item active">
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
<h3 class="card-title"><i class="fas fa-plus-circle"></i> Create New Booking</h3>
</div>
<div class="card-body">
<form method="POST">
<div class="form-group">
<label class="form-label">Select Guest *</label>
<select name="guest_id" class="form-select" required>
<option value="">-- Choose Guest --</option>
<?php foreach ($guests as $guest): ?>
<option value="<?= $guest['id'] ?>" <?= (isset($_POST['guest_id']) && $_POST['guest_id'] == $guest['id']) ? 'selected' : '' ?>>
<?= htmlspecialchars($guest['full_name'] ?? $guest['username']) ?> (<?= htmlspecialchars($guest['email']) ?>)
</option>
<?php endforeach; ?>
</select>
</div>

<div class="form-group">
<label class="form-label">Room Category *</label>
<select name="category_id" class="form-select" required onchange="this.form.submit()">
<option value="">-- Choose Room Category --</option>
<?php foreach ($categories as $cat): ?>
<option value="<?= $cat['id'] ?>" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
<?= htmlspecialchars($cat['name']) ?> (Rp <?= number_format($cat['base_price'], 0, ',', '.') ?>/night)
</option>
<?php endforeach; ?>
</select>
</div>

<?php if (!empty($rooms)): ?>
<div class="form-group">
<label class="form-label">Select Room *</label>
<select name="room_id" class="form-select" required>
<option value="">-- Choose Available Room --</option>
<?php foreach ($rooms as $room): ?>
<option value="<?= $room['id'] ?>" <?= (isset($_POST['room_id']) && $_POST['room_id'] == $room['id']) ? 'selected' : '' ?>>
Room <?= htmlspecialchars($room['room_number']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<?php endif; ?>

<div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
<div class="form-group">
<label class="form-label">Check-in Date *</label>
<input type="date" name="check_in" class="form-control" value="<?= $_POST['check_in'] ?? date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
</div>
<div class="form-group">
<label class="form-label">Check-out Date *</label>
<input type="date" name="check_out" class="form-control" value="<?= $_POST['check_out'] ?? date('Y-m-d', strtotime('+1 day')) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
</div>
</div>

<div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
<div class="form-group">
<label class="form-label">Adults *</label>
<select name="adults" class="form-select" required>
<?php for ($i = 1; $i <= 6; $i++): ?>
<option value="<?= $i ?>" <?= (($_POST['adults'] ?? 2) == $i) ? 'selected' : '' ?>><?= $i ?> Adult<?= $i > 1 ? 's' : '' ?></option>
<?php endfor; ?>
</select>
</div>
<div class="form-group">
<label class="form-label">Children</label>
<select name="children" class="form-select">
<?php for ($i = 0; $i <= 4; $i++): ?>
<option value="<?= $i ?>" <?= (($_POST['children'] ?? 0) == $i) ? 'selected' : '' ?>><?= $i ?> Child<?= $i == 1 ? '' : ($i > 1 ? 'ren' : '') ?></option>
<?php endfor; ?>
</select>
</div>
</div>

<div class="form-group">
<label class="form-label">Special Requests</label>
<textarea name="special_requests" class="form-control form-textarea"><?= $_POST['special_requests'] ?? '' ?></textarea>
</div>

<div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
<div class="form-group">
<label class="form-label">Payment Method *</label>
<select name="payment_method" class="form-select" required>
<option value="cash" <?= ($_POST['payment_method'] ?? 'cash') == 'cash' ? 'selected' : '' ?>>Cash</option>
<option value="credit_card" <?= ($_POST['payment_method'] ?? '') == 'credit_card' ? 'selected' : '' ?>>Credit Card</option>
<option value="bank_transfer" <?= ($_POST['payment_method'] ?? '') == 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
</select>
</div>
<div class="form-group">
<label class="form-label">Payment Status *</label>
<select name="payment_status" class="form-select" required>
<option value="pending" <?= ($_POST['payment_status'] ?? 'pending') == 'pending' ? 'selected' : '' ?>>Pending</option>
<option value="paid" <?= ($_POST['payment_status'] ?? '') == 'paid' ? 'selected' : '' ?>>Paid</option>
</select>
</div>
</div>

<div style="display: flex; gap: 10px; margin-top: 20px;">
<button type="submit" class="create-btn"><i class="fas fa-save"></i> Create Booking</button>
<a href="bookings.php" class="cancel-btn"><i class="fas fa-times"></i> Cancel</a>
</div>
</form>
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