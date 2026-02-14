<?php
// receptionist/service-requests.php - SERVICE REQUESTS (FIXED + FUNCTIONAL)
session_start();
require_once '../includes/config.php';
requireReceptionist();

$user_id = $_SESSION['user_id'];
$page_title = 'Service Requests';

// Get receptionist data
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist_result = $receptionist_stmt->get_result();
$receptionist = $receptionist_result->fetch_assoc();
$receptionist_stmt->close();

// =========================
// HANDLE SERVICE REQUEST ACTIONS
// =========================
if (isset($_GET['action'])) {
    $request_id = intval($_GET['id'] ?? 0);
    $action = $_GET['action'];

    if ($request_id > 0) {

        $update_sql = null;

        if ($action === 'complete') {
            $update_sql = "UPDATE service_requests 
                           SET status = 'completed', completed_at = NOW(), completed_by = ? 
                           WHERE id = ?";
        } elseif ($action === 'cancel') {
            $update_sql = "UPDATE service_requests 
                           SET status = 'cancelled', completed_at = NOW(), completed_by = ? 
                           WHERE id = ?";
        } elseif ($action === 'in_progress') {
            $update_sql = "UPDATE service_requests 
                           SET status = 'in_progress', updated_at = NOW() 
                           WHERE id = ?";
        }

        if ($update_sql) {
            $stmt = $conn->prepare($update_sql);

            if ($stmt) {
                if ($action === 'complete' || $action === 'cancel') {
                    $stmt->bind_param("ii", $user_id, $request_id);
                } else {
                    $stmt->bind_param("i", $request_id);
                }

                if ($stmt->execute()) {
                    $action_text = ucfirst(str_replace('_', ' ', $action));
                    $_SESSION['flash_message'] = "Service request {$action_text} successfully!";
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = "Failed to update service request!";
                    $_SESSION['flash_type'] = 'error';
                }

                $stmt->close();
            }
        }

        // Redirect to avoid resubmit
        header("Location: service-requests.php");
        exit();
    }
}

// =========================
// GET ACTIVE REQUESTS
// =========================
$requests_sql = "
SELECT 
    sr.*,
    b.booking_code,
    u.full_name,
    u.username,
    r.room_number,
    rc.name AS room_type
FROM service_requests sr
JOIN bookings b ON sr.booking_id = b.id
JOIN users u ON b.user_id = u.id
JOIN rooms r ON b.room_id = r.id
LEFT JOIN room_categories rc ON r.category_id = rc.id
WHERE sr.status IN ('pending','in_progress')
ORDER BY sr.created_at DESC
";

$requests_result = $conn->query($requests_sql);
$active_requests = [];
if ($requests_result) {
    while ($row = $requests_result->fetch_assoc()) {
        $active_requests[] = $row;
    }
}

// =========================
// GET COMPLETED REQUESTS (LAST 24H)
// =========================
$completed_sql = "
SELECT 
    sr.*,
    b.booking_code,
    u.full_name,
    u.username,
    r.room_number,
    rc.name AS room_type
FROM service_requests sr
JOIN bookings b ON sr.booking_id = b.id
JOIN users u ON b.user_id = u.id
JOIN rooms r ON b.room_id = r.id
LEFT JOIN room_categories rc ON r.category_id = rc.id
WHERE sr.status = 'completed'
AND sr.completed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY sr.completed_at DESC
LIMIT 12
";

$completed_result = $conn->query($completed_sql);
$completed_requests = [];
if ($completed_result) {
    while ($row = $completed_result->fetch_assoc()) {
        $completed_requests[] = $row;
    }
}

// =========================
// REQUEST STATISTICS (LAST 24H)
// =========================
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
FROM service_requests
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
$stats_result = $conn->query($stats_sql);
$request_stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0
];
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
        /* ===== TEMPLATE DASHBOARD STYLE (WAJIB ADA) ===== */
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

        .content-area {
            padding: 30px;
        }

        /* Stats */
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
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--blue);
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
        .btn-danger  { background: var(--red); color: white; }
        .btn-secondary { background: var(--gray); color: white; }

        .btn-primary:hover { background: #3abde0; transform: translateY(-1px); }
        .btn-success:hover { background: #218838; transform: translateY(-1px); }
        .btn-danger:hover { background: #c82333; transform: translateY(-1px); }

        /* Logout */
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

        /* Alerts */
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
        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border-color: rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
        .alert-warning {
            background: rgba(255, 193, 7, 0.2);
            border-color: rgba(255, 193, 7, 0.3);
            color: #ffc107;
        }

        /* ===== PAGE SPECIFIC STYLE ===== */
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .request-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
            position: relative;
        }

        .request-card:hover {
            transform: translateY(-5px);
            border-color: var(--blue);
        }

        .request-card.priority-high {
            border-left: 5px solid #dc3545;
            background: rgba(220, 53, 69, 0.05);
        }

        .request-card.priority-medium {
            border-left: 5px solid #ffc107;
            background: rgba(255, 193, 7, 0.05);
        }

        .request-card.priority-low {
            border-left: 5px solid #28a745;
            background: rgba(40, 167, 69, 0.05);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }

        .request-title {
            font-size: 1.2rem;
            color: white;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .request-guest {
            color: #aaa;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .request-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-in_progress {
            background: rgba(0, 123, 255, 0.2);
            color: #007bff;
            border: 1px solid rgba(0, 123, 255, 0.3);
        }

        .status-completed {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .status-cancelled {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }

        .request-details { margin-bottom: 20px; }

        .request-description {
            color: #ccc;
            line-height: 1.6;
            margin-bottom: 15px;
            padding: 15px;
            background: rgba(0,0,0,0.2);
            border-radius: 8px;
            border-left: 3px solid var(--blue);
        }

        .request-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #aaa;
            font-size: 0.85rem;
        }

        .info-item i {
            color: var(--blue);
            width: 20px;
        }

        .request-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 20px;
            flex-wrap: wrap;
        }

        .action-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 140px;
        }

        .time-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255,255,255,0.1);
            color: #aaa;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .empty-requests {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
            grid-column: 1 / -1;
        }

        .empty-requests i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .requests-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filter-tag {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.03);
            color: #ccc;
            user-select: none;
        }

        .filter-tag:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        .filter-tag.active {
            border-color: var(--blue);
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
        }

        .completed-section {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .completed-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .completed-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .completed-item {
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            padding: 15px;
            border: 1px solid rgba(255,255,255,0.05);
            opacity: 0.7;
            transition: all 0.3s;
        }

        .completed-item:hover {
            opacity: 1;
            border-color: rgba(40, 167, 69, 0.3);
        }

        /* Responsive */
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
                <p class="nav-label">SERVICES</p>
                <a href="service-requests.php" class="nav-item active">
                    <i class="fas fa-bell"></i>
                    <span>Service Requests</span>
                </a>
                <a href="services.php" class="nav-item">
                    <i class="fas fa-concierge-bell"></i>
                    <span>Hotel Services</span>
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
                <div style="display: flex; gap: 15px; align-items: center;">
                    <span style="color: #aaa; font-size: 0.9rem;">
                        <i class="fas fa-bell"></i>
                        <?= (int)$request_stats['pending'] + (int)$request_stats['in_progress'] ?> active requests
                    </span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <div class="content-area">

            <?php
            if (isset($_SESSION['flash_message'])) {
                $alert_class = 'alert-success';
                if ($_SESSION['flash_type'] === 'error') $alert_class = 'alert-error';
                if ($_SESSION['flash_type'] === 'warning') $alert_class = 'alert-warning';

                echo '<div class="alert ' . $alert_class . '">
                        <i class="fas fa-info-circle"></i> ' . $_SESSION['flash_message'] . '
                      </div>';

                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
            }
            ?>

            <!-- Request Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-value"><?= (int)$request_stats['pending'] ?></div>
                    <div class="stat-label">Pending Requests</div>
                    <div style="color: #aaa; font-size: 0.85rem;">
                        <i class="fas fa-hourglass-half"></i> Waiting for action
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-spinner"></i></div>
                    <div class="stat-value"><?= (int)$request_stats['in_progress'] ?></div>
                    <div class="stat-label">In Progress</div>
                    <div style="color: #aaa; font-size: 0.85rem;">
                        <i class="fas fa-cogs"></i> Being handled
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?= (int)$request_stats['completed'] ?></div>
                    <div class="stat-label">Completed Today</div>
                    <div style="color: #aaa; font-size: 0.85rem;">
                        <i class="fas fa-calendar-day"></i> Last 24 hours
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                    <div class="stat-value"><?= (int)$request_stats['total'] ?></div>
                    <div class="stat-label">Total Today</div>
                    <div style="color: #aaa; font-size: 0.85rem;">
                        <i class="fas fa-chart-line"></i> All requests
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="requests-filter">
                <span class="filter-tag active" onclick="filterRequests('all')">
                    All Active (<?= count($active_requests) ?>)
                </span>
                <span class="filter-tag" onclick="filterRequests('pending')">
                    Pending (<?= (int)$request_stats['pending'] ?>)
                </span>
                <span class="filter-tag" onclick="filterRequests('in_progress')">
                    In Progress (<?= (int)$request_stats['in_progress'] ?>)
                </span>
                <span class="filter-tag" onclick="filterRequests('high')">
                    High Priority
                </span>
                <span class="filter-tag" onclick="filterRequests('room_service')">
                    Room Service
                </span>
                <span class="filter-tag" onclick="filterRequests('maintenance')">
                    Maintenance
                </span>
            </div>

            <!-- Active Requests -->
            <div class="requests-grid" id="requestsContainer">
                <?php if (empty($active_requests)): ?>
                    <div class="empty-requests">
                        <i class="fas fa-bell-slash fa-3x"></i>
                        <h3 style="color: #aaa; margin: 15px 0 10px 0;">No Active Service Requests</h3>
                        <p style="color: #777; margin-bottom: 20px;">
                            All service requests have been completed. Great job!
                        </p>
                        <div style="color: #aaa; font-size: 0.9rem; margin-top: 10px;">
                            <i class="fas fa-info-circle"></i> New requests will appear here automatically
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($active_requests as $request): 
                        $priority_class = 'priority-' . ($request['priority'] ?? 'low');
                        $status_class = 'status-' . ($request['status'] ?? 'pending');

                        $created_time = strtotime($request['created_at'] ?? date('Y-m-d H:i:s'));
                        $now = time();
                        $hours_ago = floor(($now - $created_time) / 3600);
                        $minutes_ago = floor(($now - $created_time) / 60);

                        $serviceType = $request['service_type'] ?? 'Other';
                        $typeLower = strtolower($serviceType);
                    ?>
                    <div class="request-card <?= $priority_class ?>" 
                         data-status="<?= htmlspecialchars($request['status'] ?? '') ?>" 
                         data-priority="<?= htmlspecialchars($request['priority'] ?? '') ?>" 
                         data-type="<?= htmlspecialchars($typeLower) ?>">

                        <div class="time-badge">
                            <i class="fas fa-clock"></i>
                            <?php if ($hours_ago > 0): ?>
                                <?= $hours_ago ?> hour<?= $hours_ago > 1 ? 's' : '' ?> ago
                            <?php else: ?>
                                <?= max($minutes_ago, 1) ?> minute<?= $minutes_ago > 1 ? 's' : '' ?> ago
                            <?php endif; ?>
                        </div>

                        <div class="request-header">
                            <div>
                                <div class="request-title">
                                    <i class="fas 
                                        <?= $serviceType == 'Room Service' ? 'fa-utensils' : '' ?>
                                        <?= $serviceType == 'Maintenance' ? 'fa-tools' : '' ?>
                                        <?= $serviceType == 'Cleaning' ? 'fa-broom' : '' ?>
                                        <?= $serviceType == 'Laundry' ? 'fa-tshirt' : '' ?>
                                        <?= $serviceType == 'Transportation' ? 'fa-car' : '' ?>
                                        <?= $serviceType == 'Other' ? 'fa-question-circle' : '' ?>
                                    "></i>
                                    <?= htmlspecialchars($serviceType) ?>
                                </div>

                                <div class="request-guest">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($request['full_name'] ?? $request['username'] ?? '-') ?>

                                    <?php if (!empty($request['room_number'])): ?>
                                        <span style="margin-left: 10px;">
                                            <i class="fas fa-door-closed"></i> Room <?= htmlspecialchars($request['room_number']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <span class="request-status <?= $status_class ?>">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $request['status'] ?? 'pending'))) ?>
                            </span>
                        </div>

                        <div class="request-details">
                            <div class="request-description">
                                <?= nl2br(htmlspecialchars($request['description'] ?? '-')) ?>
                            </div>

                            <div class="request-info">
                                <div class="info-item">
                                    <i class="fas fa-tag"></i>
                                    <span>Booking: <?= htmlspecialchars($request['booking_code'] ?? 'N/A') ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-flag"></i>
                                    <span>Priority: 
                                        <span style="color:
                                            <?= ($request['priority'] ?? '') === 'high' ? '#dc3545' : '' ?>
                                            <?= ($request['priority'] ?? '') === 'medium' ? '#ffc107' : '' ?>
                                            <?= ($request['priority'] ?? '') === 'low' ? '#28a745' : '' ?>
                                        ">
                                            <?= htmlspecialchars(ucfirst($request['priority'] ?? 'low')) ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Requested: <?= date('H:i', strtotime($request['created_at'] ?? date('Y-m-d H:i:s'))) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="request-actions">
                            <?php if (($request['status'] ?? '') == 'pending'): ?>
                                <a href="?action=in_progress&id=<?= (int)$request['id'] ?>" class="btn btn-primary action-btn">
                                    <i class="fas fa-play-circle"></i> Start
                                </a>
                                <a href="?action=complete&id=<?= (int)$request['id'] ?>" class="btn btn-success action-btn"
                                   onclick="return confirm('Mark this request as completed?')">
                                    <i class="fas fa-check-circle"></i> Complete
                                </a>
                                <a href="?action=cancel&id=<?= (int)$request['id'] ?>" class="btn btn-danger action-btn"
                                   onclick="return confirm('Cancel this request?')">
                                    <i class="fas fa-times-circle"></i> Cancel
                                </a>
                            <?php elseif (($request['status'] ?? '') == 'in_progress'): ?>
                                <a href="?action=complete&id=<?= (int)$request['id'] ?>" class="btn btn-success action-btn"
                                   onclick="return confirm('Mark this request as completed?')">
                                    <i class="fas fa-check-circle"></i> Complete
                                </a>
                                <a href="?action=cancel&id=<?= (int)$request['id'] ?>" class="btn btn-danger action-btn"
                                   onclick="return confirm('Cancel this request?')">
                                    <i class="fas fa-times-circle"></i> Cancel
                                </a>
                            <?php endif; ?>

                            <button class="btn action-btn" style="background: rgba(255,255,255,0.1); color: white;"
                                    onclick='showRequestDetails(<?= json_encode($request, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>

                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Completed Requests -->
            <?php if (!empty($completed_requests)): ?>
            <div class="completed-section">
                <div class="completed-header">
                    <h3 style="color: white; margin: 0;">
                        <i class="fas fa-check-circle"></i>
                        Recently Completed Requests
                        <span style="color: #aaa; font-size: 0.9rem; font-weight: normal; margin-left: 10px;">
                            Last 24 hours (<?= count($completed_requests) ?>)
                        </span>
                    </h3>
                    <a href="request-history.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-history"></i> View Full History
                    </a>
                </div>

                <div class="completed-list">
                    <?php foreach ($completed_requests as $request): ?>
                    <div class="completed-item">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <div>
                                <div style="color: white; font-weight: 500; margin-bottom: 5px;">
                                    <?= htmlspecialchars($request['service_type'] ?? '-') ?>
                                </div>
                                <div style="color: #aaa; font-size: 0.85rem;">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($request['full_name'] ?? $request['username'] ?? '-') ?>
                                    <?php if (!empty($request['room_number'])): ?>
                                    <span style="margin-left: 10px;">
                                        <i class="fas fa-door-closed"></i> Room <?= htmlspecialchars($request['room_number']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="request-status status-completed">Completed</span>
                        </div>

                        <div style="color: #ccc; font-size: 0.9rem; margin-bottom: 10px; line-height: 1.4;">
                            <?= htmlspecialchars(substr($request['description'] ?? '', 0, 100)) ?>...
                        </div>

                        <div style="display: flex; justify-content: space-between; color: #777; font-size: 0.8rem;">
                            <span>
                                <i class="fas fa-clock"></i>
                                <?= date('H:i', strtotime($request['created_at'] ?? date('Y-m-d H:i:s'))) ?>
                            </span>
                            <span>
                                <i class="fas fa-check-circle"></i>
                                <?= date('H:i', strtotime($request['completed_at'] ?? date('Y-m-d H:i:s'))) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<!-- Modal Dummy (biar tidak error) -->
<div class="modal" id="requestModal" style="display:none;"></div>

<script>
    // Menu toggle
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });

    // Filter requests
    function filterRequests(filter) {
        const requests = document.querySelectorAll('.request-card');
        const filterTags = document.querySelectorAll('.filter-tag');

        // Update active filter tag (simple)
        filterTags.forEach(tag => tag.classList.remove('active'));
        const clicked = Array.from(filterTags).find(t => t.textContent.toLowerCase().includes(filter));
        if (clicked) clicked.classList.add('active');

        if (filter === 'all') {
            requests.forEach(r => r.style.display = 'block');
            return;
        }

        requests.forEach(request => {
            if (filter === 'pending' || filter === 'in_progress') {
                request.style.display = (request.dataset.status === filter) ? 'block' : 'none';
            } else if (filter === 'high') {
                request.style.display = (request.dataset.priority === 'high') ? 'block' : 'none';
            } else if (filter === 'room_service' || filter === 'maintenance') {
                request.style.display = request.dataset.type.includes(filter.replace('_','')) ? 'block' : 'none';
            }
        });
    }

    // Show request details (simple alert version)
    function showRequestDetails(request) {
        alert(
            "Service: " + request.service_type + "\n" +
            "Guest: " + (request.full_name || request.username) + "\n" +
            "Room: " + (request.room_number || '-') + "\n" +
            "Status: " + request.status + "\n\n" +
            request.description
        );
    }
</script>

</body>
</html>
