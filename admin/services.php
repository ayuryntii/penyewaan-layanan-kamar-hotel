<?php
// admin/services.php - HOTEL SERVICES MANAGEMENT
require_once '../includes/config.php';
requireAdmin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$page_title = 'Hotel Services';

// Handle service operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_action'])) {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? 'other');
    $price = floatval($_POST['price'] ?? 0);
    $unit = trim($_POST['unit'] ?? 'per item');
    $description = trim($_POST['description'] ?? '');
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    if (empty($name) || $price <= 0) {
        $_SESSION['error'] = 'Service name and price are required.';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    if ($id > 0) {
        // Update existing
        $stmt = $conn->prepare("UPDATE services SET name = ?, category = ?, price = ?, unit = ?, description = ?, is_available = ? WHERE id = ?");
        $stmt->bind_param("ssdssii", $name, $category, $price, $unit, $description, $is_available, $id);
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO services (name, category, price, unit, description, is_available, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssdssi", $name, $category, $price, $unit, $description, $is_available);
    }
    
    if ($stmt && $stmt->execute()) {
        $_SESSION['success'] = 'Service saved successfully!';
        header('Location: services.php');
        exit();
    } else {
        $_SESSION['error'] = 'Failed to save service.';
    }
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Service deleted successfully!';
    } else {
        $_SESSION['error'] = 'Failed to delete service.';
    }
    header('Location: services.php');
    exit();
}

// Get services for list view
$services = [];
if ($action == 'list') {
    $result = $conn->query("SELECT * FROM services ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}

// Get service for edit view
$service = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $service = $conn->query("SELECT * FROM services WHERE id = $id")->fetch_assoc();
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark-bg);
            color: var(--light);
            overflow-x: hidden;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* === Sidebar === */
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

        /* === Main Content === */
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

        /* === Cards === */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            margin-bottom: 25px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            overflow: hidden;
        }

        .card-body {
            padding: 25px;
        }

        /* === Form === */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: white;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
            color: white;
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
        }

        .btn-primary {
            background: var(--blue);
            color: var(--navy);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--blue);
            color: var(--blue);
        }

        .btn-danger {
            background: #ef233c;
            color: white;
        }

        .btn-primary:hover {
            background: #3abde0;
        }

        .btn-secondary:hover {
            background: rgba(76, 201, 240, 0.1);
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: rgba(76, 201, 240, 0.2);
            color: var(--blue);
        }

        .badge-success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .badge-danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .badge-info { background: rgba(52, 152, 219, 0.2); color: #3498db; }

        /* === Table === */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        th {
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
            font-weight: 600;
        }

        tr:hover {
            background: rgba(76, 201, 240, 0.05);
        }

        /* === Select Dropdown Style === */
        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z' fill='%23666'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px;
            color: white;
        }

        select.form-control:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(76, 201, 240, 0.15);
            background: rgba(255,255,255,0.1);
        }

        select.form-control option {
            color: #333;
            background: white;
            padding: 8px 15px;
        }

        select.form-control option:checked {
            background: var(--blue);
            color: white;
        }

        select.form-control option:hover {
            background: rgba(76, 201, 240, 0.1);
            color: #333;
        }

        /* === User Menu in Header === */
        .user-menu-header {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(10, 25, 47, 0.8);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            cursor: pointer;
            position: relative;
        }

        .user-menu-header:hover {
            background: rgba(76, 201, 240, 0.1);
        }

        .user-menu-header .user-avatar {
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

        .user-menu-header .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-menu-header .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-menu-header .user-role {
            font-size: 0.8rem;
            color: #aaa;
        }

        .logout-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--card-bg);
            border: 1px solid rgba(76, 201, 240, 0.1);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
        }

        .logout-menu.show {
            display: block;
        }

        .logout-menu a {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            font-weight: 500;
        }

        .logout-menu a:hover {
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
        }

        /* === Responsive === */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>

    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-hotel"></i>
                </div>
                <div class="sidebar-title">
                    <h3><?= htmlspecialchars($hotel_name) ?></h3>
                    <p>Admin Dashboard</p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">ROOM MANAGEMENT</p>
                    <a href="rooms.php" class="nav-item">
                        <i class="fas fa-bed"></i>
                        <span>All Rooms</span>
                    </a>
                    <a href="rooms.php?action=add" class="nav-item">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add New Room</span>
                    </a>
                    <a href="rooms.php?action=categories" class="nav-item">
                        <i class="fas fa-tags"></i>
                        <span>Room Categories</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">BOOKINGS</p>
                    <a href="bookings.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>All Bookings</span>
                    </a>
                    <a href="bookings.php?action=add" class="nav-item">
                        <i class="fas fa-plus"></i>
                        <span>New Booking</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">CUSTOMERS</p>
                    <a href="customers.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span>All Customers</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">FINANCE</p>
                    <a href="payments.php" class="nav-item">
                        <i class="fas fa-credit-card"></i>
                        <span>Payments</span>
                    </a>
                    <a href="reports.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">SERVICES</p>
                    <a href="services.php" class="nav-item active">
                        <i class="fas fa-concierge-bell"></i>
                        <span>Hotel Services</span>
                    </a>
                    <a href="staff.php" class="nav-item">
                        <i class="fas fa-user-tie"></i>
                        <span>Staff Management</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">SETTINGS</p>
                    <a href="settings.php" class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>System Settings</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
                        <div class="user-role"><?= ucfirst($_SESSION['role'] ?? 'admin') ?></div>
                    </div>
                </div>
                <a href="../logout.php" class="btn btn-sm btn-secondary" style="margin-top: 15px; width: 100%; justify-content: center;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
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
                    <div class="last-login">
                        <i class="fas fa-clock"></i>
                        Last Login: <?= date('d M Y H:i', strtotime($_SESSION['last_login'] ?? date('Y-m-d H:i:s'))) ?>
                    </div>
                    
                    <!-- User Menu in Header -->
                    <div class="user-menu-header" id="userMenuHeader">
                        <div class="user-avatar">
                            <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
                            <div class="user-role"><?= ucfirst($_SESSION['role'] ?? 'admin') ?></div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: #aaa;"></i>
                    </div>
                    
                    <!-- Logout Menu -->
                    <div class="logout-menu" id="logoutMenu">
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </header>

            <div class="content-area">
                <?php if ($action == 'list'): ?>
                
                <!-- Page Header -->
                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="font-size: 24px; font-weight: 600; margin: 0;">Hotel Services</h2>
                        <p style="color: #aaa; margin-top: 5px;">Manage additional services for guests</p>
                    </div>
                    <div>
                        <a href="?action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Service
                        </a>
                    </div>
                </div>

                <!-- Services Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Service Name</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Category</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Price</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Unit</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Status</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <strong><?= htmlspecialchars($service['name']) ?></strong>
                                            <?php if ($service['description']): ?>
                                            <p style="color: #aaa; font-size: 12px; margin: 5px 0 0 0;"><?= htmlspecialchars($service['description']) ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <span class="badge badge-info"><?= ucfirst($service['category']) ?></span>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= formatCurrency($service['price']) ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= htmlspecialchars($service['unit']) ?></td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <span class="badge <?= $service['is_available'] ? 'badge-success' : 'badge-danger' ?>">
                                                <?= $service['is_available'] ? 'Available' : 'Unavailable' ?>
                                            </span>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <div style="display: flex; gap: 5px;">
                                                <a href="?action=edit&id=<?= $service['id'] ?>" class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="deleteService(<?= $service['id'] ?>)" class="btn btn-sm btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php elseif ($action == 'add' || $action == 'edit'): ?>
                
                <!-- Add/Edit Service Form -->
                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="font-size: 24px; font-weight: 600; margin: 0;">
                            <?= $action == 'add' ? 'Add New Service' : 'Edit Service' ?>
                        </h2>
                        <p style="color: #aaa; margin-top: 5px;">
                            <?= $action == 'add' ? 'Add a new hotel service' : 'Update service information' ?>
                        </p>
                    </div>
                    <div>
                        <a href="services.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Services
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="service_action" value="save">
                            <?php if ($action == 'edit'): ?>
                            <input type="hidden" name="id" value="<?= $service['id'] ?>">
                            <?php endif; ?>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label class="form-label">Service Name *</label>
                                    <input type="text" name="name" class="form-control" required 
                                           value="<?= htmlspecialchars($service['name'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-control">
                                        <option value="food" <?= ($service['category'] ?? '') == 'food' ? 'selected' : '' ?>>Food</option>
                                        <option value="beverage" <?= ($service['category'] ?? '') == 'beverage' ? 'selected' : '' ?>>Beverage</option>
                                        <option value="laundry" <?= ($service['category'] ?? '') == 'laundry' ? 'selected' : '' ?>>Laundry</option>
                                        <option value="transport" <?= ($service['category'] ?? '') == 'transport' ? 'selected' : '' ?>>Transport</option>
                                        <option value="spa" <?= ($service['category'] ?? '') == 'spa' ? 'selected' : '' ?>>Spa</option>
                                        <option value="other" <?= ($service['category'] ?? '') == 'other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label class="form-label">Price *</label>
                                    <input type="number" name="price" class="form-control" required step="0.01"
                                           value="<?= $service['price'] ?? '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Unit</label>
                                    <input type="text" name="unit" class="form-control" 
                                           value="<?= htmlspecialchars($service['unit'] ?? 'per item') ?>"
                                           placeholder="e.g., per item, per hour">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 10px;">
                                    <input type="checkbox" name="is_available" value="1" 
                                           <?= ($service['is_available'] ?? 1) ? 'checked' : '' ?>>
                                    Available for booking
                                </label>
                            </div>
                            
                            <div style="display: flex; gap: 15px; margin-top: 20px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?= $action == 'add' ? 'Add Service' : 'Update Service' ?>
                                </button>
                                <a href="services.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Toggle Logout Menu
        const userMenuHeader = document.getElementById('userMenuHeader');
        const logoutMenu = document.getElementById('logoutMenu');
        
        userMenuHeader.addEventListener('click', function(e) {
            e.stopPropagation();
            logoutMenu.classList.toggle('show');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenuHeader.contains(e.target) && !logoutMenu.contains(e.target)) {
                logoutMenu.classList.remove('show');
            }
        });
        
        // Delete Service
        function deleteService(id) {
            if (confirm('Delete this service?')) {
                window.location.href = '?delete=' + id;
            }
        }
    </script>
</body>
</html>