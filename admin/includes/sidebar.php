<?php
// admin/includes/sidebar.php
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-hotel"></i>
        </div>
        <div class="sidebar-title">
            <h3>Hotel Admin</h3>
            <p>Management System</p>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <div class="nav-group">
            <p class="nav-label">ROOM MANAGEMENT</p>
            <a href="rooms.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'rooms.php' && (!isset($_GET['action']) || $_GET['action'] == 'list') ? 'active' : ''; ?>">
                <i class="fas fa-bed"></i>
                <span>All Rooms</span>
            </a>
            <a href="rooms.php?action=add" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'rooms.php' && isset($_GET['action']) && $_GET['action'] == 'add' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Add New Room</span>
            </a>
            <a href="rooms.php?action=categories" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'rooms.php' && isset($_GET['action']) && $_GET['action'] == 'categories' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                <span>Room Categories</span>
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <div class="nav-group">
            <p class="nav-label">BOOKINGS</p>
            <a href="bookings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'bookings.php' && (!isset($_GET['action']) || $_GET['action'] == 'list') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>All Bookings</span>
                <?php
                $pending_count = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'pending'")->fetch_assoc()['count'];
                if ($pending_count > 0): ?>
                <span class="nav-badge"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="bookings.php?action=add" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'bookings.php' && isset($_GET['action']) && $_GET['action'] == 'add' ? 'active' : ''; ?>">
                <i class="fas fa-plus"></i>
                <span>New Booking</span>
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <div class="nav-group">
            <p class="nav-label">CUSTOMERS</p>
            <a href="customers.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>All Customers</span>
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <div class="nav-group">
            <p class="nav-label">FINANCE</p>
            <a href="payments.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="reports.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <div class="nav-group">
            <p class="nav-label">SERVICES</p>
            <a href="services.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">
                <i class="fas fa-concierge-bell"></i>
                <span>Hotel Services</span>
            </a>
            <a href="staff.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'staff.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i>
                <span>Staff Management</span>
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <div class="nav-group">
            <p class="nav-label">SETTINGS</p>
            <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>System Settings</span>
            </a>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-menu">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
            </div>
        </div>
        <a href="../logout.php" class="btn btn-sm btn-outline" style="margin-top: 15px;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>