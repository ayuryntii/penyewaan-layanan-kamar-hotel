<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireAdmin();

$page_title = "Admin Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo $hotel_name; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- DataTables -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- FullCalendar -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <!-- AdminLTE Style (Custom) -->
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    
    <style>
        :root {
            --sidebar-width: 250px;
            --header-height: 60px;
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--primary-color);
            color: white;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 20px;
            background: var(--secondary-color);
            text-align: center;
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
            color: white;
        }
        
        .sidebar-header p {
            margin: 5px 0 0;
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--accent-color);
        }
        
        .nav-link i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        
        .dropdown-menu {
            background: var(--secondary-color);
            border: none;
            border-radius: 0;
        }
        
        .dropdown-item {
            color: rgba(255,255,255,0.8);
            padding: 10px 20px 10px 45px;
        }
        
        .dropdown-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        /* Main Content */
        #content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
        }
        
        .top-navbar {
            background: white;
            padding: 15px 20px;
            margin: -20px -20px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title h1 {
            font-size: 1.8rem;
            margin: 0;
            color: var(--primary-color);
        }
        
        .page-title p {
            margin: 5px 0 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }
        
        .stat-card.primary::before { background: var(--accent-color); }
        .stat-card.success::before { background: var(--success-color); }
        .stat-card.warning::before { background: var(--warning-color); }
        .stat-card.danger::before { background: var(--danger-color); }
        .stat-card.info::before { background: #17a2b8; }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .stat-card.primary .stat-icon { background: rgba(52, 152, 219, 0.1); color: var(--accent-color); }
        .stat-card.success .stat-icon { background: rgba(39, 174, 96, 0.1); color: var(--success-color); }
        .stat-card.warning .stat-icon { background: rgba(243, 156, 18, 0.1); color: var(--warning-color); }
        .stat-card.danger .stat-icon { background: rgba(231, 76, 60, 0.1); color: var(--danger-color); }
        .stat-card.info .stat-icon { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-title {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Charts Container */
        .charts-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .chart-card h5 {
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        /* Tables */
        .data-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h5 {
            margin: 0;
            color: var(--primary-color);
        }
        
        .table-container {
            padding: 0;
        }
        
        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .action-btn {
            background: white;
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            color: #666;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .action-btn:hover {
            border-color: var(--accent-color);
            color: var(--accent-color);
            background: rgba(52, 152, 219, 0.05);
        }
        
        .action-btn i {
            font-size: 24px;
        }
        
        /* Calendar */
        #calendar {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            #sidebar {
                margin-left: -250px;
            }
            
            #content {
                margin-left: 0;
            }
            
            .sidebar-open #sidebar {
                margin-left: 0;
            }
            
            .sidebar-open #content {
                margin-left: 250px;
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-hotel"></i> Hotel Admin</h3>
            <p>Management System</p>
        </div>
        
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#roomsMenu">
                        <i class="fas fa-bed"></i> Room Management <i class="fas fa-chevron-down float-end"></i>
                    </a>
                    <div class="collapse show" id="roomsMenu">
                        <ul class="nav flex-column ps-3">
                            <li class="nav-item">
                                <a class="nav-link" href="rooms/index.php">All Rooms</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="rooms/add.php">Add New Room</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="rooms/categories.php">Room Categories</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="rooms/availability.php">Availability</a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="bookings/index.php">
                        <i class="fas fa-calendar-alt"></i> Bookings
                        <span class="badge bg-danger float-end">5</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="customers/index.php">
                        <i class="fas fa-users"></i> Customers
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="payments/index.php">
                        <i class="fas fa-credit-card"></i> Payments
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#reportsMenu">
                        <i class="fas fa-chart-bar"></i> Reports <i class="fas fa-chevron-down float-end"></i>
                    </a>
                    <div class="collapse" id="reportsMenu">
                        <ul class="nav flex-column ps-3">
                            <li class="nav-item">
                                <a class="nav-link" href="reports/financial.php">Financial Report</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="reports/occupancy.php">Occupancy Report</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="reports/guest.php">Guest Analysis</a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="services/index.php">
                        <i class="fas fa-concierge-bell"></i> Services
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="staff/index.php">
                        <i class="fas fa-user-tie"></i> Staff
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="settings/index.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-footer p-3">
            <div class="text-center">
                <p class="mb-1">Last Login: <?php echo date('d M Y H:i', strtotime($_SESSION['last_login'] ?? 'now')); ?></p>
                <a href="../logout.php" class="btn btn-sm btn-outline-light w-100">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div id="content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="page-title">
                <h1><i class="fas fa-tachometer-alt me-2"></i> Dashboard</h1>
                <p>Welcome back, <?php echo $_SESSION['full_name']; ?>! Here's what's happening today.</p>
            </div>
            
            <div class="user-info">
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                        <span class="ms-2"><?php echo $_SESSION['full_name']; ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="../my-bookings.php"><i class="fas fa-calendar me-2"></i>My Bookings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
                <button class="btn btn-primary d-lg-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <?php
            // Get statistics
            $stats = [
                'total_revenue' => $conn->query("SELECT SUM(final_price) as total FROM bookings WHERE payment_status = 'paid' AND YEAR(created_at) = YEAR(CURDATE())")->fetch_assoc()['total'] ?? 0,
                'total_bookings' => $conn->query("SELECT COUNT(*) as total FROM bookings WHERE YEAR(created_at) = YEAR(CURDATE())")->fetch_assoc()['total'] ?? 0,
                'active_bookings' => $conn->query("SELECT COUNT(*) as total FROM bookings WHERE booking_status IN ('confirmed', 'checked_in')")->fetch_assoc()['total'] ?? 0,
                'available_rooms' => $conn->query("SELECT COUNT(*) as total FROM rooms WHERE status = 'available'")->fetch_assoc()['total'] ?? 0,
                'total_customers' => $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'")->fetch_assoc()['total'] ?? 0,
                'occupancy_rate' => $conn->query("SELECT (COUNT(CASE WHEN status IN ('occupied', 'reserved') THEN 1 END) * 100.0 / COUNT(*)) as rate FROM rooms")->fetch_assoc()['rate'] ?? 0
            ];
            ?>
            
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-number"><?php echo formatCurrency($stats['total_revenue']); ?></div>
                <div class="stat-title">Total Revenue</div>
                <div class="stat-change text-success">
                    <i class="fas fa-arrow-up"></i> 12% from last month
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                <div class="stat-title">Total Bookings</div>
                <div class="stat-change text-success">
                    <i class="fas fa-arrow-up"></i> 8 new today
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="stat-number"><?php echo $stats['active_bookings']; ?></div>
                <div class="stat-title">Active Bookings</div>
                <div class="stat-change text-warning">
                    <i class="fas fa-clock"></i> 2 check-ins today
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-door-open"></i>
                </div>
                <div class="stat-number"><?php echo $stats['available_rooms']; ?></div>
                <div class="stat-title">Available Rooms</div>
                <div class="stat-change text-info">
                    <?php echo number_format($stats['occupancy_rate'], 1); ?>% occupancy
                </div>
            </div>
        </div>

        <!-- Charts & Calendar -->
        <div class="charts-container">
            <div class="chart-card">
                <h5>Revenue Overview <span class="float-end">
                    <select class="form-select form-select-sm d-inline-block w-auto">
                        <option>Last 7 Days</option>
                        <option selected>Last 30 Days</option>
                        <option>Last 90 Days</option>
                    </select>
                </span></h5>
                <canvas id="revenueChart"></canvas>
            </div>
            
            <div class="chart-card">
                <h5>Room Status Distribution</h5>
                <canvas id="roomStatusChart"></canvas>
            </div>
        </div>

        <!-- Recent Bookings & Quick Actions -->
        <div class="row">
            <div class="col-lg-8">
                <div class="data-table">
                    <div class="table-header">
                        <h5>Recent Bookings</h5>
                        <a href="bookings/index.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="table-container">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Customer</th>
                                    <th>Room</th>
                                    <th>Check-in</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT b.*, u.full_name, r.room_number, rc.name as room_type 
                                        FROM bookings b 
                                        JOIN users u ON b.user_id = u.id 
                                        JOIN rooms r ON b.room_id = r.id 
                                        JOIN room_categories rc ON r.category_id = rc.id 
                                        ORDER BY b.created_at DESC LIMIT 10";
                                $result = $conn->query($sql);
                                
                                if ($result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><strong><?php echo $row['booking_code']; ?></strong></td>
                                    <td><?php echo $row['full_name']; ?></td>
                                    <td><?php echo $row['room_number']; ?> (<?php echo $row['room_type']; ?>)</td>
                                    <td><?php echo date('d M', strtotime($row['check_in'])); ?></td>
                                    <td><?php echo formatCurrency($row['final_price']); ?></td>
                                    <td><?php echo getStatusBadge($row['booking_status'], 'booking'); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="bookings/view.php?id=<?php echo $row['id']; ?>" class="btn btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="bookings/edit.php?id=<?php echo $row['id']; ?>" class="btn btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="bookings/invoice.php?id=<?php echo $row['id']; ?>" class="btn btn-success" target="_blank">
                                                <i class="fas fa-file-invoice"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No bookings found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="chart-card">
                    <h5>Quick Actions</h5>
                    <div class="quick-actions">
                        <a href="rooms/add.php" class="action-btn">
                            <i class="fas fa-plus-circle"></i>
                            <span>Add New Room</span>
                        </a>
                        <a href="bookings/create.php" class="action-btn">
                            <i class="fas fa-calendar-plus"></i>
                            <span>New Booking</span>
                        </a>
                        <a href="payments/create.php" class="action-btn">
                            <i class="fas fa-credit-card"></i>
                            <span>Record Payment</span>
                        </a>
                        <a href="checkin.php" class="action-btn">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Check-in</span>
                        </a>
                        <a href="checkout.php" class="action-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Check-out</span>
                        </a>
                        <a href="reports/financial.php" class="action-btn">
                            <i class="fas fa-chart-line"></i>
                            <span>Generate Report</span>
                        </a>
                    </div>
                </div>
                
                <!-- Today's Calendar -->
                <div class="chart-card mt-4">
                    <h5>Today's Schedule</h5>
                    <div id="todayCalendar"></div>
                </div>
            </div>
        </div>

        <!-- Calendar -->
        <div class="chart-card mt-4">
            <h5>Booking Calendar <span class="float-end">
                <button class="btn btn-sm btn-primary" id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                <button class="btn btn-sm btn-primary" id="nextMonth"><i class="fas fa-chevron-right"></i></button>
            </span></h5>
            <div id="calendar"></div>
        </div>

        <!-- Export Options -->
        <div class="chart-card mt-4">
            <h5>Export Data</h5>
            <div class="row">
                <div class="col-md-3">
                    <button class="btn btn-outline-primary w-100 mb-2" onclick="exportToPDF('dashboard')">
                        <i class="fas fa-file-pdf"></i> Export as PDF
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-success w-100 mb-2" onclick="exportToExcel('bookings')">
                        <i class="fas fa-file-excel"></i> Export Bookings
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-info w-100 mb-2" onclick="exportToExcel('revenue')">
                        <i class="fas fa-file-excel"></i> Export Revenue
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-secondary w-100 mb-2" onclick="printDashboard()">
                        <i class="fas fa-print"></i> Print Dashboard
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- FullCalendar -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <!-- Chart.js -->
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Revenue',
                    data: [4500000, 5200000, 4800000, 6100000, 7200000, 8500000, 9200000, 8800000, 7800000, 9500000, 10200000, 11500000],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                            }
                        }
                    }
                }
            }
        });

        // Room Status Chart
        const roomCtx = document.getElementById('roomStatusChart').getContext('2d');
        const roomChart = new Chart(roomCtx, {
            type: 'doughnut',
            data: {
                labels: ['Available', 'Occupied', 'Reserved', 'Maintenance'],
                datasets: [{
                    data: [15, 8, 3, 2],
                    backgroundColor: [
                        '#27ae60',
                        '#e74c3c',
                        '#3498db',
                        '#f39c12'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Toggle Sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.body.classList.toggle('sidebar-open');
        });

        // Export Functions
        function exportToPDF(type) {
            alert('Exporting ' + type + ' to PDF...');
            // In production, implement actual PDF export
        }

        function exportToExcel(type) {
            alert('Exporting ' + type + ' to Excel...');
            // In production, implement actual Excel export
        }

        function printDashboard() {
            window.print();
        }

        // Initialize DataTable
        $(document).ready(function() {
            $('table').DataTable({
                pageLength: 5,
                lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
        });

        // Initialize Calendar
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                events: [
                    {
                        title: 'Check-in: Room 201',
                        start: '2024-01-15',
                        backgroundColor: '#27ae60'
                    },
                    {
                        title: 'Check-out: Room 101',
                        start: '2024-01-16',
                        backgroundColor: '#e74c3c'
                    },
                    {
                        title: 'Maintenance',
                        start: '2024-01-20',
                        end: '2024-01-22',
                        backgroundColor: '#f39c12'
                    }
                ]
            });
            calendar.render();
        });
    </script>
</body>
</html>