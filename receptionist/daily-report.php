<?php
// receptionist/daily-report.php - DAILY REPORT (RECEPTIONIST)
session_start();
require_once '../includes/config.php';
requireReceptionist();

$user_id = $_SESSION['user_id'];
$page_title = "Daily Report";
$today = date('Y-m-d');

// Get receptionist data
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist = $receptionist_stmt->get_result()->fetch_assoc();
$receptionist_stmt->close();

// =========================
// STATS
// =========================

// Total bookings today (created today)
$total_bookings_today = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE DATE(created_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$total_bookings_today = ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Check-ins today
$checkins_today = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE DATE(check_in) = ? AND booking_status = 'confirmed'");
$stmt->bind_param("s", $today);
$stmt->execute();
$checkins_today = ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Check-outs today
$checkouts_today = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE DATE(check_out) = ? AND booking_status = 'checked_in'");
$stmt->bind_param("s", $today);
$stmt->execute();
$checkouts_today = ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Revenue today (sum of final_price for paid bookings today)
$revenue_today = 0;
$rev_sql = "
SELECT SUM(final_price) as total
FROM bookings
WHERE DATE(created_at) = ?
AND payment_status IN ('paid','completed','success')
";
$stmt = $conn->prepare($rev_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$revenue_today = ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Service requests today
$request_stats = [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0
];

$req_sql = "
SELECT
COUNT(*) as total,
SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) as in_progress,
SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed
FROM service_requests
WHERE DATE(created_at) = ?
";
$stmt = $conn->prepare($req_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$request_stats = $stmt->get_result()->fetch_assoc() ?? $request_stats;
$stmt->close();

// =========================
// LIST BOOKINGS TODAY
// =========================
$today_bookings = [];
$bookings_sql = "
SELECT b.*, u.full_name, u.username, r.room_number, rc.name AS room_type
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN rooms r ON b.room_id = r.id
LEFT JOIN room_categories rc ON r.category_id = rc.id
WHERE DATE(b.created_at) = ?
ORDER BY b.created_at DESC
LIMIT 20
";
$stmt = $conn->prepare($bookings_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $today_bookings[] = $row;
}
$stmt->close();

// =========================
// SERVICE REQUESTS TODAY
// =========================
$today_requests = [];
$requests_sql = "
SELECT sr.*, u.full_name, u.username, b.booking_code, r.room_number
FROM service_requests sr
LEFT JOIN bookings b ON sr.booking_id = b.id
LEFT JOIN users u ON sr.user_id = u.id
LEFT JOIN rooms r ON b.room_id = r.id
WHERE DATE(sr.created_at) = ?
ORDER BY sr.created_at DESC
LIMIT 20
";
$stmt = $conn->prepare($requests_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $today_requests[] = $row;
}
$stmt->close();

// =========================
// Helper format
// =========================
function moneyIDR($num) {
    return "Rp " . number_format((float)$num, 0, ',', '.');
}
function badgeBooking($status) {
    $status = strtolower($status);
    if ($status === 'confirmed') return '<span class="badge badge-success">Confirmed</span>';
    if ($status === 'checked_in') return '<span class="badge badge-warning">Checked-in</span>';
    if ($status === 'checked_out') return '<span class="badge badge-primary">Checked-out</span>';
    if ($status === 'cancelled') return '<span class="badge badge-danger">Cancelled</span>';
    return '<span class="badge badge-secondary">'.htmlspecialchars($status).'</span>';
}
function badgeRequest($status) {
    $status = strtolower($status);
    if ($status === 'pending') return '<span class="badge badge-warning">Pending</span>';
    if ($status === 'in_progress') return '<span class="badge badge-primary">In Progress</span>';
    if ($status === 'completed') return '<span class="badge badge-success">Completed</span>';
    if ($status === 'cancelled') return '<span class="badge badge-danger">Cancelled</span>';
    return '<span class="badge badge-secondary">'.htmlspecialchars($status).'</span>';
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
:root{
--navy:#0a192f;
--blue:#4cc9f0;
--light:#f8f9fa;
--gray:#6c757d;
--dark-bg:#0a192f;
--card-bg: rgba(20, 30, 50, 0.85);
--sidebar-width:260px;
--green:#28a745;
--yellow:#ffc107;
--red:#dc3545;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Poppins',sans-serif;background:var(--dark-bg);color:var(--light);overflow-x:hidden}
.receptionist-wrapper{display:flex;min-height:100vh}

/* Sidebar */
.sidebar{
width:var(--sidebar-width);
background:var(--navy);
height:100vh;
position:fixed;left:0;top:0;
z-index:100;
transition:all .3s ease;
border-right:1px solid rgba(76,201,240,0.1);
}
.sidebar-header{
padding:25px 20px;
display:flex;align-items:center;gap:15px;
border-bottom:1px solid rgba(255,255,255,0.1);
}
.sidebar-logo{
width:40px;height:40px;background:var(--blue);
border-radius:8px;display:flex;align-items:center;justify-content:center;
color:var(--navy);font-size:18px;
}
.sidebar-title h3{font-size:1.2rem;font-weight:600}
.sidebar-title p{font-size:.85rem;color:#aaa}
.sidebar-nav{
padding:20px 0;overflow-y:auto;height:calc(100vh - 180px);
}
.nav-item{
display:flex;align-items:center;
padding:12px 25px;color:#ccc;text-decoration:none;font-weight:500;
transition:all .3s;
}
.nav-item:hover,.nav-item.active{
background:rgba(76,201,240,0.1);color:var(--blue);
}
.nav-item i{margin-right:15px;width:20px;text-align:center}
.nav-label{
padding:15px 25px 8px;
color:#777;font-size:.8rem;text-transform:uppercase;letter-spacing:1px;
}
.nav-divider{height:1px;background:rgba(255,255,255,0.05);margin:15px 0}
.sidebar-footer{padding:20px;border-top:1px solid rgba(255,255,255,0.05)}
.user-menu{display:flex;align-items:center;gap:12px}
.user-avatar{
width:40px;height:40px;background:var(--blue);
border-radius:50%;display:flex;align-items:center;justify-content:center;
font-weight:600;color:var(--navy)
}
.user-info .user-name{font-weight:600;font-size:.95rem}
.user-info .user-role{font-size:.8rem;color:#aaa}

/* Main content */
.main-content{flex:1;margin-left:var(--sidebar-width);transition:all .3s ease}
.top-header{
display:flex;justify-content:space-between;align-items:center;
padding:20px 30px;
background:rgba(10,25,47,0.95);
backdrop-filter:blur(10px);
border-bottom:1px solid rgba(76,201,240,0.1);
}
.menu-toggle{
background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;display:none
}
.content-area{padding:30px}

/* Cards & tables */
.card{
background:var(--card-bg);
border-radius:16px;margin-bottom:25px;
border:1px solid rgba(76,201,240,0.1);
overflow:hidden;
}
.card-header{
display:flex;justify-content:space-between;align-items:center;
padding:20px 25px;border-bottom:1px solid rgba(255,255,255,0.05);
}
.card-title{font-size:1.3rem;color:white;display:flex;align-items:center;gap:10px}
.card-body{padding:25px}

.stats-grid{
display:grid;
grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
gap:18px;margin-bottom:25px;
}
.stat-card{
background:var(--card-bg);
border-radius:15px;padding:22px;
border:1px solid rgba(255,255,255,0.1);
transition:all .3s;
}
.stat-card:hover{transform:translateY(-3px);border-color:var(--blue)}
.stat-icon{
width:55px;height:55px;border-radius:12px;
display:flex;align-items:center;justify-content:center;
font-size:22px;margin-bottom:14px;
background:rgba(255,255,255,0.05);
color:var(--blue);
}
.stat-value{font-size:2rem;font-weight:700;color:white;margin-bottom:4px}
.stat-label{color:#aaa;font-size:.9rem}

.table-responsive{overflow-x:auto}
.table{width:100%;border-collapse:collapse}
.table th{
background:rgba(255,255,255,0.03);
padding:15px;text-align:left;color:#aaa;font-weight:600;font-size:.9rem;
border-bottom:1px solid rgba(255,255,255,0.1);
}
.table td{
padding:15px;border-bottom:1px solid rgba(255,255,255,0.05);
color:#ddd;vertical-align:top;
}
.table tbody tr:hover{background:rgba(76,201,240,0.05)}

/* badges */
.badge{
padding:5px 12px;border-radius:20px;font-size:.75rem;font-weight:600;
text-transform:uppercase;letter-spacing:.5px;
display:inline-block;
}
.badge-success{background:rgba(40,167,69,0.2);color:#28a745;border:1px solid rgba(40,167,69,0.3)}
.badge-warning{background:rgba(255,193,7,0.2);color:#ffc107;border:1px solid rgba(255,193,7,0.3)}
.badge-danger{background:rgba(220,53,69,0.2);color:#dc3545;border:1px solid rgba(220,53,69,0.3)}
.badge-primary{background:rgba(76,201,240,0.2);color:var(--blue);border:1px solid rgba(76,201,240,0.3)}
.badge-secondary{background:rgba(108,117,125,0.2);color:#6c757d;border:1px solid rgba(108,117,125,0.3)}

/* Button */
.btn{
padding:10px 16px;border-radius:10px;font-weight:600;text-decoration:none;
display:inline-flex;align-items:center;gap:8px;border:none;cursor:pointer;
}
.btn-primary{background:var(--blue);color:var(--navy)}
.btn-danger{background:rgba(231,76,60,0.2);border:1px solid rgba(231,76,60,0.3);color:#e74c3c}

@media(max-width:992px){
.sidebar{transform:translateX(-100%)}
.sidebar.active{transform:translateX(0)}
.main-content{margin-left:0}
.menu-toggle{display:block}
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
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a>

            <div class="nav-divider"></div>

            <div class="nav-group">
                <p class="nav-label">BOOKINGS</p>
                <a href="checkin.php" class="nav-item"><i class="fas fa-sign-in-alt"></i><span>Check-in</span></a>
                <a href="checkout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>Check-out</span></a>
                <a href="bookings.php" class="nav-item"><i class="fas fa-calendar-alt"></i><span>Manage Bookings</span></a>
                <a href="new-booking.php" class="nav-item"><i class="fas fa-plus-circle"></i><span>New Booking</span></a>
            </div>

            <div class="nav-divider"></div>

            <div class="nav-group">
                <p class="nav-label">SERVICES</p>
                <a href="service-requests.php" class="nav-item"><i class="fas fa-bell"></i><span>Service Requests</span></a>
                <a href="services.php" class="nav-item"><i class="fas fa-concierge-bell"></i><span>Hotel Services</span></a>
            </div>

            <div class="nav-divider"></div>

            <div class="nav-group">
                <p class="nav-label">REPORTS</p>
                <a href="daily-report.php" class="nav-item active"><i class="fas fa-chart-line"></i><span>Daily Report</span></a>
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

    <!-- Main -->
    <main class="main-content">
        <header class="top-header">
            <div style="display:flex;align-items:center;gap:15px">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <h1><?= htmlspecialchars($page_title) ?></h1>
            </div>

            <div style="display:flex;align-items:center;gap:15px;">
                <div style="text-align:right;">
                    <div style="color:var(--blue);font-weight:700;font-size:1.2rem;" id="clock"><?= date('H:i:s') ?></div>
                    <div style="color:#aaa;font-size:0.9rem;"><?= date('l, d F Y') ?></div>
                </div>
                <a href="../logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>

        <div class="content-area">

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-value"><?= (int)$total_bookings_today ?></div>
                    <div class="stat-label">Bookings Created Today</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-sign-in-alt"></i></div>
                    <div class="stat-value"><?= (int)$checkins_today ?></div>
                    <div class="stat-label">Check-ins Today</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-sign-out-alt"></i></div>
                    <div class="stat-value"><?= (int)$checkouts_today ?></div>
                    <div class="stat-label">Check-outs Today</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-value"><?= moneyIDR($revenue_today) ?></div>
                    <div class="stat-label">Revenue Today</div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-bell"></i></div>
                    <div class="stat-value"><?= (int)($request_stats['total'] ?? 0) ?></div>
                    <div class="stat-label">Service Requests Today</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-value"><?= (int)($request_stats['pending'] ?? 0) ?></div>
                    <div class="stat-label">Pending</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-spinner"></i></div>
                    <div class="stat-value"><?= (int)($request_stats['in_progress'] ?? 0) ?></div>
                    <div class="stat-label">In Progress</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?= (int)($request_stats['completed'] ?? 0) ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>

            <!-- Today Bookings -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> Today Bookings</h3>
                </div>
                <div class="card-body">
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
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($today_bookings)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;color:#aaa;padding:25px;">
                                        No bookings created today.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($today_bookings as $b): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($b['booking_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($b['full_name'] ?? $b['username']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($b['room_type'] ?? '-') ?>
                                            <div style="color:var(--blue);font-size:0.85rem;">Room <?= htmlspecialchars($b['room_number']) ?></div>
                                        </td>
                                        <td>
                                            <?= date('d M', strtotime($b['check_in'])) ?>
                                            <div style="color:#aaa;font-size:0.85rem;">to <?= date('d M', strtotime($b['check_out'])) ?></div>
                                        </td>
                                        <td><strong><?= moneyIDR($b['final_price']) ?></strong></td>
                                        <td><?= badgeBooking($b['booking_status']) ?></td>
                                        <td><span class="badge badge-secondary"><?= htmlspecialchars($b['payment_status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Service Requests -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-bell"></i> Service Requests Today</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Room</th>
                                <th>Type</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($today_requests)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;color:#aaa;padding:25px;">
                                        No service requests today.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($today_requests as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['full_name'] ?? $r['username'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['room_number'] ?? '-') ?></td>
                                        <td><strong><?= htmlspecialchars($r['service_type']) ?></strong></td>
                                        <td><span class="badge badge-secondary"><?= htmlspecialchars($r['priority']) ?></span></td>
                                        <td><?= badgeRequest($r['status']) ?></td>
                                        <td><?= date('H:i', strtotime($r['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
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
document.getElementById('menuToggle').addEventListener('click', function(){
    document.querySelector('.sidebar').classList.toggle('active');
});

function updateClock(){
    const now = new Date();
    const timeString = now.toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    document.getElementById('clock').textContent = timeString;
}
setInterval(updateClock, 1000);
updateClock();
</script>

</body>
</html>
