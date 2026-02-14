<?php
// admin/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Get counts for badges
$pending_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'pending'")->fetch_assoc()['count'];
$maintenance_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'maintenance'")->fetch_assoc()['count'];
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-hotel"></i>
        </div>
        <div class="sidebar-title">
            <h3><?php echo $hotel_name; ?></h3>
            <p>Management System</p>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <a href="index.php" class="nav-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <!-- Room Management -->
        <div class="nav-group">
            <p class="nav-label">
                <i class="fas fa-bed"></i> ROOM MANAGEMENT
            </p>
            <a href="rooms.php?action=list" class="nav-item <?php echo ($current_page == 'rooms.php' && in_array($action, ['list', 'view', 'edit'])) ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>All Rooms</span>
                <?php if ($maintenance_rooms > 0): ?>
                <span class="nav-badge"><?php echo $maintenance_rooms; ?></span>
                <?php endif; ?>
            </a>
            <a href="rooms.php?action=add" class="nav-item <?php echo ($current_page == 'rooms.php' && $action == 'add') ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Add New Room</span>
            </a>
            <a href="rooms.php?action=categories" class="nav-item <?php echo ($current_page == 'rooms.php' && $action == 'categories') ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                <span>Room Categories</span>
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <!-- Booking Management -->
        <div class="nav-group">
            <p class="nav-label">
                <i class="fas fa-calendar-check"></i> BOOKINGS
            </p>
            <a href="bookings.php?action=list" class="nav-item <?php echo ($current_page == 'bookings.php' && in_array($action, ['list', 'view', 'edit'])) ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>All Bookings</span>
                <?php if ($pending_bookings > 0): ?>
                <span class="nav-badge"><?php echo $pending_bookings; ?></span>
                <?php endif; ?>
            </a>
            <a href="bookings.php?action=add" class="nav-item <?php echo ($current_page == 'bookings.php' && $action == 'add') ? 'active' : ''; ?>">
                <i class="fas fa-plus"></i>
                <span>New Booking</span>
            </a>
            <a href="bookings.php?action=checkin" class="nav-item <?php echo ($current_page == 'bookings.php' && $action == 'checkin') ? 'active' : ''; ?>">
                <i class="fas fa-sign-in-alt"></i>
                <span>Check-in</span>
            </a>
            <a href="bookings.php?action=checkout" class="nav-item <?php echo ($current_page == 'bookings.php' && $action == 'checkout') ? 'active' : ''; ?>">
                <i class="fas fa-sign-out-alt"></i>
                <span>Check-out</span>
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <!-- Customer Management -->
        <div class="nav-group">
            <p class="nav-label">
                <i class="fas fa-users"></i> CUSTOMERS
            </p>
            <a href="customers.php?action=list" class="nav-item <?php echo ($current_page == 'customers.php' && in_array($action, ['list', 'view', 'edit'])) ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>All Customers</span>
            </a>
            <a href="customers.php?action=add" class="nav-item <?php echo ($current_page == 'customers.php' && $action == 'add') ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i>
                <span>Add Customer</span>
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <!-- Finance Management -->
        <div class="nav-group">
            <p class="nav-label">
                <i class="fas fa-chart-line"></i> FINANCE
            </p>
            <a href="payments.php?action=list" class="nav-item <?php echo ($current_page == 'payments.php' && in_array($action, ['list', 'view'])) ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="payments.php?action=pending" class="nav-item <?php echo ($current_page == 'payments.php' && $action == 'pending') ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i>
                <span>Pending Payments</span>
            </a>
            <a href="reports.php" class="nav-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <!-- Services Management -->
        <div class="nav-group">
            <p class="nav-label">
                <i class="fas fa-concierge-bell"></i> SERVICES
            </p>
            <a href="services.php?action=list" class="nav-item <?php echo ($current_page == 'services.php' && in_array($action, ['list', 'add', 'edit'])) ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>Hotel Services</span>
            </a>
            <a href="services.php?action=add" class="nav-item <?php echo ($current_page == 'services.php' && $action == 'add') ? 'active' : ''; ?>">
                <i class="fas fa-plus"></i>
                <span>Add Service</span>
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <!-- Staff Management -->
        <div class="nav-group">
            <p class="nav-label">
                <i class="fas fa-user-tie"></i> STAFF
            </p>
            <a href="staff.php?action=list" class="nav-item <?php echo ($current_page == 'staff.php' && in_array($action, ['list', 'view', 'edit'])) ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>All Staff</span>
            </a>
            <a href="staff.php?action=add" class="nav-item <?php echo ($current_page == 'staff.php' && $action == 'add') ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i>
                <span>Add Staff</span>
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <!-- Settings -->
        <div class="nav-group">
            <p class="nav-label">
                <i class="fas fa-cog"></i> SETTINGS
            </p>
            <a href="settings.php?action=general" class="nav-item <?php echo ($current_page == 'settings.php' && $action == 'general') ? 'active' : ''; ?>">
                <i class="fas fa-sliders-h"></i>
                <span>General Settings</span>
            </a>
            <a href="settings.php?action=profile" class="nav-item <?php echo ($current_page == 'settings.php' && $action == 'profile') ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i>
                <span>Profile Settings</span>
            </a>
            <a href="settings.php?action=users" class="nav-item <?php echo ($current_page == 'settings.php' && $action == 'users') ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>
                <span>User Management</span>
            </a>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-menu">
            <div class="user-avatar">
                <?php 
                $initials = '';
                if (!empty($_SESSION['full_name'])) {
                    $names = explode(' ', $_SESSION['full_name']);
                    $initials = strtoupper(substr($names[0], 0, 1));
                    if (count($names) > 1) {
                        $initials .= strtoupper(substr($names[1], 0, 1));
                    }
                } else {
                    $initials = 'AD';
                }
                echo $initials;
                ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name'] ?? 'Administrator'; ?></div>
                <div class="user-role"><?php echo ucfirst($_SESSION['role'] ?? 'admin'); ?></div>
            </div>
        </div>
        <a href="../logout.php" class="btn btn-sm btn-outline" style="margin-top: 15px; width: 100%;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

<script>
    // Menu toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 992) {
                    if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                        sidebar.classList.remove('active');
                    }
                }
            });
        }
        
        // Highlight current menu
        const currentPath = window.location.pathname;
        const currentAction = '<?php echo $action; ?>';
        
        document.querySelectorAll('.sidebar-nav a').forEach(link => {
            const href = link.getAttribute('href');
            if (href) {
                const urlParams = new URLSearchParams(href.split('?')[1]);
                const action = urlParams.get('action');
                
                if (href.includes(currentPath) && (!action || action === currentAction)) {
                    link.classList.add('active');
                }
            }
        });
    });
</script>