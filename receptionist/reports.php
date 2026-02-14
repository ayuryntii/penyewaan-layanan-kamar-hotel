<?php
// receptionist/reports.php - REPORTS HOME (RECEPTIONIST)
session_start();
require_once '../includes/config.php';
requireReceptionist();

$user_id = $_SESSION['user_id'];
$page_title = "Reports";

// Get receptionist data
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist = $receptionist_stmt->get_result()->fetch_assoc();
$receptionist_stmt->close();

// quick stats today
$today = date('Y-m-d');

$stats = [
    'bookings' => 0,
    'checkins' => 0,
    'checkouts' => 0,
    'requests' => 0
];

// bookings created today
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE DATE(created_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$stats['bookings'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// checkins today
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE DATE(check_in) = ? AND booking_status='confirmed'");
$stmt->bind_param("s", $today);
$stmt->execute();
$stats['checkins'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// checkouts today
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE DATE(check_out) = ? AND booking_status='checked_in'");
$stmt->bind_param("s", $today);
$stmt->execute();
$stats['checkouts'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// service requests today
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM service_requests WHERE DATE(created_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$stats['requests'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();
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

/* Cards */
.grid{
display:grid;
grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
gap:18px;
}
.card{
background:var(--card-bg);
border:1px solid rgba(255,255,255,0.1);
border-radius:16px;
padding:22px;
transition:all .25s ease;
}
.card:hover{transform:translateY(-3px);border-color:rgba(76,201,240,0.35)}
.card h3{
font-size:1.15rem;
margin-bottom:8px;
display:flex;align-items:center;gap:10px;
}
.mini{
color:#aaa;font-size:.9rem;line-height:1.5;margin-bottom:14px
}
.stat-row{display:flex;gap:12px;flex-wrap:wrap;margin-top:10px}
.pill{
display:inline-flex;gap:8px;align-items:center;
padding:8px 12px;border-radius:14px;
background:rgba(255,255,255,0.06);
border:1px solid rgba(255,255,255,0.08);
color:#ddd;font-size:.85rem
}
.btn{
padding:10px 16px;border-radius:10px;font-weight:600;text-decoration:none;
display:inline-flex;align-items:center;gap:8px;border:none;cursor:pointer;
}
.btn-primary{background:var(--blue);color:var(--navy)}
.btn-secondary{background:rgba(255,255,255,0.12);color:#fff}
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
                <a href="reports.php" class="nav-item active"><i class="fas fa-file-alt"></i><span>Reports</span></a>
                <a href="daily-report.php" class="nav-item"><i class="fas fa-chart-line"></i><span>Daily Report</span></a>
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

            <div style="margin-bottom:18px;color:#aaa;">
                Quick summary for today.
            </div>

            <div class="grid" style="margin-bottom:18px;">
                <div class="card">
                    <h3><i class="fas fa-calendar-check" style="color:var(--blue)"></i> Today Activity</h3>
                    <div class="mini">Overview of operational activity for <?= date('d M Y') ?>.</div>
                    <div class="stat-row">
                        <div class="pill"><i class="fas fa-receipt"></i> Bookings: <b><?= (int)$stats['bookings'] ?></b></div>
                        <div class="pill"><i class="fas fa-sign-in-alt"></i> Check-ins: <b><?= (int)$stats['checkins'] ?></b></div>
                        <div class="pill"><i class="fas fa-sign-out-alt"></i> Check-outs: <b><?= (int)$stats['checkouts'] ?></b></div>
                        <div class="pill"><i class="fas fa-bell"></i> Requests: <b><?= (int)$stats['requests'] ?></b></div>
                    </div>
                </div>

                <div class="card">
                    <h3><i class="fas fa-chart-line" style="color:var(--blue)"></i> Daily Report</h3>
                    <div class="mini">See full daily report: bookings, revenue, service requests.</div>
                    <a class="btn btn-primary" href="daily-report.php">
                        <i class="fas fa-eye"></i> View Daily Report
                    </a>
                </div>

                <div class="card">
                    <h3><i class="fas fa-file-alt" style="color:var(--blue)"></i> Other Reports</h3>
                    <div class="mini">More reports can be added later (weekly / monthly / finance export).</div>
                    <a class="btn btn-secondary" href="daily-report.php">
                        <i class="fas fa-plus-circle"></i> Use Daily Report for now
                    </a>
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
