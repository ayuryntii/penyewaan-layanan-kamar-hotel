<?php
if (!isset($conn)) {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/functions.php';
}

// Default user info if not in session
$user_name  = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Admin';
$user_role  = isset($_SESSION['role']) ? $_SESSION['role'] : 'admin';
$last_login = isset($_SESSION['last_login']) ? $_SESSION['last_login'] : date('Y-m-d H:i:s');

/**
 * FIX LINK RELATIVE:
 * Sidebar ini dipakai di /admin/ dan juga /admin/rooms/
 * Jadi link harus menyesuaikan lokasi folder saat ini.
 *
 * Kalau sekarang berada di /admin/rooms/* maka butuh ../
 * Kalau berada di /admin/* maka tidak butuh ../
 */
$baseAdmin = (strpos($_SERVER['REQUEST_URI'], '/admin/rooms/') !== false) ? '../' : '';
?>
<nav id="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-hotel"></i> Hotel Admin</h3>
        <p>Management System</p>
    </div>

    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>"
                   href="<?php echo $baseAdmin; ?>dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'rooms') !== false ? 'active' : ''; ?>"
                   href="#"
                   data-bs-toggle="collapse"
                   data-bs-target="#roomsMenu">
                    <i class="fas fa-bed"></i> Room Management <i class="fas fa-chevron-down float-end"></i>
                </a>

                <div class="collapse <?php echo strpos($_SERVER['REQUEST_URI'], 'rooms') !== false ? 'show' : ''; ?>"
                     id="roomsMenu">
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
                            <a class="nav-link" href="<?php echo $baseAdmin; ?>rooms/availability.php">Availability</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'bookings') !== false ? 'active' : ''; ?>"
                   href="<?php echo $baseAdmin; ?>bookings/index.php">
                    <i class="fas fa-calendar-alt"></i> Bookings
                    <?php
                    $pending_count = 0;
                    if (isset($conn)) {
                        $pending_count = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'pending'")
                                              ->fetch_assoc()['count'];
                    }
                    if ($pending_count > 0): ?>
                        <span class="badge bg-danger float-end"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'customers') !== false ? 'active' : ''; ?>"
                   href="<?php echo $baseAdmin; ?>customers/index.php">
                    <i class="fas fa-users"></i> Customers
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'payments') !== false ? 'active' : ''; ?>"
                   href="<?php echo $baseAdmin; ?>payments/index.php">
                    <i class="fas fa-credit-card"></i> Payments
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'services') !== false ? 'active' : ''; ?>"
                   href="<?php echo $baseAdmin; ?>services/index.php">
                    <i class="fas fa-concierge-bell"></i> Services
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'settings') !== false ? 'active' : ''; ?>"
                   href="<?php echo $baseAdmin; ?>settings/index.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-footer p-3">
        <div class="text-center">
            <p class="mb-1">
                <small>Last Login:</small><br>
                <?php echo date('d M Y H:i', strtotime($last_login)); ?>
            </p>

            <!-- Logout aman dari semua folder -->
            <a href="<?php echo $baseAdmin; ?>../logout.php" class="btn btn-sm btn-outline-light w-100 mt-2">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</nav>
