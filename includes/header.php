<?php
// includes/header.php
if (!isset($page_title)) {
    $page_title = 'Hotel Admin Dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Hotel Management System</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    
    <style>
        .dataTables_wrapper {
            padding: 0 !important;
        }
        .dt-button {
            background: #3498db !important;
            color: white !important;
            border: none !important;
            padding: 8px 15px !important;
            border-radius: 4px !important;
            margin: 5px !important;
        }
        .dt-button:hover {
            background: #2980b9 !important;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php if (file_exists(__DIR__ . '/sidebar.php')) {
    include __DIR__ . '/sidebar.php';
}
 ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1><?php echo $page_title; ?></h1>
                </div>
                
                <div class="header-right">
                    <div class="header-actions">
                        <button class="btn-notification">
                            <i class="fas fa-bell"></i>
                            <span class="notification-count">3</span>
                        </button>
                        
                        <div class="last-login-info">
                            <i class="fas fa-clock"></i>
                            <span>Last Login: <?php echo isset($_SESSION['last_login']) ? date('d M Y H:i', strtotime($_SESSION['last_login'])) : date('d M Y H:i'); ?></span>
                        </div>
                        
                        <div class="quick-stats">
                            <div class="stat-item">
                                <i class="fas fa-bed"></i>
                                <span>
                                    <?php 
                                    $available_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'available'")->fetch_assoc()['count'];
                                    echo $available_rooms;
                                    ?> Rooms Available
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-wrapper">