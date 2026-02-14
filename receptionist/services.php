<?php
// receptionist/services.php - HOTEL SERVICES (CRUD)
session_start();
require_once '../includes/config.php';
requireReceptionist();

$user_id = $_SESSION['user_id'];
$page_title = 'Hotel Services';

// Get receptionist data
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist_result = $receptionist_stmt->get_result();
$receptionist = $receptionist_result->fetch_assoc();
$receptionist_stmt->close();

// ==========================
// Detect services table & columns (auto compatible)
// ==========================
$services_table = null;
$service_name_col = null;
$service_desc_col = null;
$service_price_col = null;
$service_category_col = null;
$service_status_col = null;
$service_created_col = null;

$possible_tables = ['hotel_services', 'services'];
foreach ($possible_tables as $t) {
    $check = $conn->query("SHOW TABLES LIKE '{$t}'");
    if ($check && $check->num_rows > 0) {
        $services_table = $t;
        break;
    }
}

// If table doesn't exist, show error message
if (!$services_table) {
    $_SESSION['flash_message'] = "Table for services not found. Please create table 'hotel_services' or 'services'.";
    $_SESSION['flash_type'] = "error";
}

// helper: check column exists
function colExists($conn, $table, $col) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return $res && $res->num_rows > 0;
}

// if table exists, determine columns
if ($services_table) {
    // name
    if (colExists($conn, $services_table, 'name')) $service_name_col = 'name';
    else if (colExists($conn, $services_table, 'service_name')) $service_name_col = 'service_name';
    else if (colExists($conn, $services_table, 'title')) $service_name_col = 'title';

    // description
    if (colExists($conn, $services_table, 'description')) $service_desc_col = 'description';
    else if (colExists($conn, $services_table, 'details')) $service_desc_col = 'details';

    // price
    if (colExists($conn, $services_table, 'price')) $service_price_col = 'price';
    else if (colExists($conn, $services_table, 'service_price')) $service_price_col = 'service_price';
    else if (colExists($conn, $services_table, 'base_price')) $service_price_col = 'base_price';

    // category
    if (colExists($conn, $services_table, 'category')) $service_category_col = 'category';
    else if (colExists($conn, $services_table, 'service_type')) $service_category_col = 'service_type';

    // status
    if (colExists($conn, $services_table, 'status')) $service_status_col = 'status';
    else if (colExists($conn, $services_table, 'is_active')) $service_status_col = 'is_active';

    // created_at
    if (colExists($conn, $services_table, 'created_at')) $service_created_col = 'created_at';
}

// ==========================
// CRUD HANDLERS
// ==========================
if ($services_table && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // sanitize
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = $_POST['price'] ?? '';
    $status = $_POST['status'] ?? 'active';

    if ($action === 'add') {
        if ($name === '') {
            $_SESSION['flash_message'] = "Service name is required!";
            $_SESSION['flash_type'] = "error";
            header("Location: services.php");
            exit;
        }

        $cols = [];
        $vals = [];
        $types = "";
        $params = [];

        if ($service_name_col) { $cols[] = $service_name_col; $vals[] = "?"; $types .= "s"; $params[] = $name; }
        if ($service_desc_col) { $cols[] = $service_desc_col; $vals[] = "?"; $types .= "s"; $params[] = $desc; }
        if ($service_category_col) { $cols[] = $service_category_col; $vals[] = "?"; $types .= "s"; $params[] = $category; }
        if ($service_price_col) { $cols[] = $service_price_col; $vals[] = "?"; $types .= "d"; $params[] = (float)$price; }

        // status handling
        if ($service_status_col) {
            $cols[] = $service_status_col;
            $vals[] = "?";
            if ($service_status_col === 'is_active') {
                $types .= "i";
                $params[] = ($status === 'active') ? 1 : 0;
            } else {
                $types .= "s";
                $params[] = $status;
            }
        }

        $sql = "INSERT INTO `$services_table` (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Service added successfully!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Failed to add service!";
                $_SESSION['flash_type'] = "error";
            }
            $stmt->close();
        }

        header("Location: services.php");
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            header("Location: services.php");
            exit;
        }

        $sets = [];
        $types = "";
        $params = [];

        if ($service_name_col) { $sets[] = "$service_name_col = ?"; $types .= "s"; $params[] = $name; }
        if ($service_desc_col) { $sets[] = "$service_desc_col = ?"; $types .= "s"; $params[] = $desc; }
        if ($service_category_col) { $sets[] = "$service_category_col = ?"; $types .= "s"; $params[] = $category; }
        if ($service_price_col) { $sets[] = "$service_price_col = ?"; $types .= "d"; $params[] = (float)$price; }

        if ($service_status_col) {
            if ($service_status_col === 'is_active') {
                $sets[] = "$service_status_col = ?";
                $types .= "i";
                $params[] = ($status === 'active') ? 1 : 0;
            } else {
                $sets[] = "$service_status_col = ?";
                $types .= "s";
                $params[] = $status;
            }
        }

        $types .= "i";
        $params[] = $id;

        $sql = "UPDATE `$services_table` SET " . implode(", ", $sets) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Service updated successfully!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Failed to update service!";
                $_SESSION['flash_type'] = "error";
            }
            $stmt->close();
        }

        header("Location: services.php");
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM `$services_table` WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $_SESSION['flash_message'] = "Service deleted successfully!";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $_SESSION['flash_message'] = "Failed to delete service!";
                    $_SESSION['flash_type'] = "error";
                }
                $stmt->close();
            }
        }

        header("Location: services.php");
        exit;
    }
}

// ==========================
// GET DATA
// ==========================
$services = [];
if ($services_table) {
    $q = trim($_GET['q'] ?? '');
    $where = "";
    if ($q !== '' && $service_name_col) {
        $safe = $conn->real_escape_string($q);
        $where = "WHERE `$service_name_col` LIKE '%$safe%'";
    }

    $sql = "SELECT * FROM `$services_table` $where ORDER BY id DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $services[] = $row;
        }
    }
}

// get value helper
function getVal($row, $col, $default = '') {
    return isset($row[$col]) ? $row[$col] : $default;
}

// format money
function moneyIDR($num) {
    if ($num === null || $num === '') return '-';
    return "Rp " . number_format((float)$num, 0, ',', '.');
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
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
font-family: 'Poppins', sans-serif;
background: var(--dark-bg);
color: var(--light);
overflow-x: hidden;
}
.receptionist-wrapper { display: flex; min-height: 100vh; }

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
width: 40px; height: 40px;
background: var(--blue);
border-radius: 8px;
display: flex; align-items: center; justify-content: center;
color: var(--navy);
font-size: 18px;
}
.sidebar-title h3 { font-size: 1.2rem; font-weight: 600; }
.sidebar-title p { font-size: 0.85rem; color: #aaa; }
.sidebar-nav {
padding: 20px 0;
overflow-y: auto;
height: calc(100vh - 180px);
}
.nav-item {
display: flex; align-items: center;
padding: 12px 25px;
color: #ccc;
text-decoration: none;
font-weight: 500;
transition: all 0.3s;
}
.nav-item:hover,
.nav-item.active {
background: rgba(76, 201, 240, 0.1);
color: var(--blue);
}
.nav-item i { margin-right: 15px; width: 20px; text-align: center; }
.nav-label {
padding: 15px 25px 8px;
color: #777;
font-size: 0.8rem;
text-transform: uppercase;
letter-spacing: 1px;
}
.nav-divider { height: 1px; background: rgba(255,255,255,0.05); margin: 15px 0; }
.sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); }
.user-menu { display: flex; align-items: center; gap: 12px; }
.user-avatar {
width: 40px; height: 40px;
background: var(--blue);
border-radius: 50%;
display: flex; align-items: center; justify-content: center;
font-weight: 600; color: var(--navy);
}
.user-info .user-name { font-weight: 600; font-size: 0.95rem; }
.user-info .user-role { font-size: 0.8rem; color: #aaa; }

/* Main */
.main-content { flex: 1; margin-left: var(--sidebar-width); transition: all 0.3s ease; }
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
.content-area { padding: 30px; }

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
.card-body { padding: 25px; }

/* Forms */
.form-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
.form-control, .form-select {
padding: 12px 14px;
border-radius: 10px;
border: 1px solid rgba(255,255,255,0.1);
background: rgba(0,0,0,0.25);
color: #fff;
outline: none;
}
.form-control { flex: 1; min-width: 250px; }
.form-select { min-width: 200px; }

/* Table */
.table-responsive { overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; }
.table th {
background: rgba(255,255,255,0.03);
padding: 15px;
text-align: left;
color: #aaa;
font-weight: 600;
font-size: 0.9rem;
border-bottom: 1px solid rgba(255,255,255,0.1);
}
.table td {
padding: 15px;
border-bottom: 1px solid rgba(255,255,255,0.05);
color: #ddd;
vertical-align: top;
}
.table tbody tr:hover { background: rgba(76, 201, 240, 0.05); }

/* Buttons */
.btn {
padding: 10px 16px;
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
.btn-danger { background: var(--red); color: white; }
.btn-secondary { background: var(--gray); color: white; }
.btn-primary:hover { background: #3abde0; transform: translateY(-1px); }
.btn-success:hover { background: #218838; transform: translateY(-1px); }
.btn-danger:hover { background: #c82333; transform: translateY(-1px); }

/* Alerts */
.alert {
padding: 15px 20px;
border-radius: 10px;
margin-bottom: 20px;
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
            <div class="sidebar-logo"><i class="fas fa-concierge-bell"></i></div>
            <div class="sidebar-title">
                <h3><?= htmlspecialchars($hotel_name) ?></h3>
                <p>Receptionist Portal</p>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>

            <div class="nav-divider"></div>

            <div class="nav-group">
                <p class="nav-label">SERVICES</p>
                <a href="service-requests.php" class="nav-item"><i class="fas fa-bell"></i><span>Service Requests</span></a>
                <a href="services.php" class="nav-item active"><i class="fas fa-concierge-bell"></i><span>Hotel Services</span></a>
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
            <div class="header-left" style="display:flex;align-items:center;gap:15px;">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <h1><?= htmlspecialchars($page_title) ?></h1>
            </div>
            <div class="header-right">
                <a href="../logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>

        <div class="content-area">

            <?php
            if (isset($_SESSION['flash_message'])) {
                $class = ($_SESSION['flash_type'] ?? '') === 'error' ? 'alert-error' : 'alert-success';
                echo '<div class="alert ' . $class . '"><i class="fas fa-info-circle"></i> ' . $_SESSION['flash_message'] . '</div>';
                unset($_SESSION['flash_message'], $_SESSION['flash_type']);
            }
            ?>

            <?php if (!$services_table): ?>
                <div class="alert alert-error">
                    <strong>Missing Table!</strong> Table <code>hotel_services</code> atau <code>services</code> tidak ditemukan.
                    <br><br>
                    Buat table dulu, contoh minimal:
                    <pre style="margin-top:10px;background:rgba(0,0,0,0.3);padding:12px;border-radius:10px;overflow:auto;color:#fff;">
CREATE TABLE hotel_services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT NULL,
  category VARCHAR(50) NULL,
  price DECIMAL(10,2) DEFAULT 0,
  status VARCHAR(20) DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
                    </pre>
                </div>
            <?php else: ?>

            <!-- Add Service -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-plus-circle"></i> Add New Service</h3>
                </div>
                <div class="card-body">

                    <form method="POST" class="form-row">
                        <input type="hidden" name="action" value="add">

                        <input class="form-control" type="text" name="name" placeholder="Service name..." required>
                        <input class="form-control" type="text" name="category" placeholder="Category (e.g. Laundry, Cleaning)">
                        <input class="form-control" type="number" step="0.01" name="price" placeholder="Price (Rp)">
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>

                        <input class="form-control" type="text" name="description" placeholder="Description...">

                        <button class="btn btn-success" type="submit"><i class="fas fa-save"></i> Save</button>
                    </form>

                </div>
            </div>

            <!-- List Services -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-concierge-bell"></i> Services List</h3>
                    <form method="GET" style="display:flex; gap:10px; align-items:center;">
                        <input class="form-control" style="min-width:220px;" type="text" name="q" placeholder="Search service..."
                               value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search"></i> Search</button>
                        <a href="services.php" class="btn btn-secondary btn-sm"><i class="fas fa-rotate-left"></i> Reset</a>
                    </form>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Service</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th style="width:260px;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($services)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; color:#aaa; padding:30px;">
                                        <i class="fas fa-info-circle"></i> No services found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($services as $srv): ?>
                                    <?php
                                    $srvName = $service_name_col ? getVal($srv, $service_name_col, '-') : '-';
                                    $srvDesc = $service_desc_col ? getVal($srv, $service_desc_col, '') : '';
                                    $srvCat  = $service_category_col ? getVal($srv, $service_category_col, '-') : '-';
                                    $srvPrice = $service_price_col ? getVal($srv, $service_price_col, 0) : 0;

                                    $srvStatus = 'active';
                                    if ($service_status_col) {
                                        if ($service_status_col === 'is_active') {
                                            $srvStatus = (int)getVal($srv, $service_status_col, 1) === 1 ? 'active' : 'inactive';
                                        } else {
                                            $srvStatus = getVal($srv, $service_status_col, 'active');
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td><?= (int)$srv['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($srvName) ?></strong>
                                            <?php if ($srvDesc): ?>
                                                <div style="color:#aaa;font-size:0.85rem;margin-top:4px;">
                                                    <?= htmlspecialchars($srvDesc) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($srvCat) ?></td>
                                        <td><strong><?= moneyIDR($srvPrice) ?></strong></td>
                                        <td>
                                            <?php if (strtolower($srvStatus) === 'active' || $srvStatus == 1): ?>
                                                <span style="color:#28a745;font-weight:600;">Active</span>
                                            <?php else: ?>
                                                <span style="color:#dc3545;font-weight:600;">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- EDIT FORM (inline) -->
                                            <form method="POST" style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="id" value="<?= (int)$srv['id'] ?>">

                                                <input class="form-control" style="min-width:150px;flex:1;"
                                                       type="text" name="name" value="<?= htmlspecialchars($srvName) ?>" required>

                                                <input class="form-control" style="min-width:120px;"
                                                       type="text" name="category" value="<?= htmlspecialchars($srvCat) ?>">

                                                <input class="form-control" style="min-width:120px;"
                                                       type="number" step="0.01" name="price" value="<?= htmlspecialchars($srvPrice) ?>">

                                                <select class="form-select" name="status" style="min-width:120px;">
                                                    <option value="active" <?= (strtolower($srvStatus) === 'active') ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= (strtolower($srvStatus) === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                                </select>

                                                <input class="form-control" style="min-width:180px;"
                                                       type="text" name="description" value="<?= htmlspecialchars($srvDesc) ?>">

                                                <button class="btn btn-primary btn-sm" type="submit">
                                                    <i class="fas fa-save"></i> Update
                                                </button>
                                            </form>

                                            <!-- DELETE -->
                                            <form method="POST" style="margin-top:8px;"
                                                  onsubmit="return confirm('Delete this service?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$srv['id'] ?>">
                                                <button class="btn btn-danger btn-sm" type="submit">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>

                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php endif; ?>

        </div>
    </main>
</div>

<script>
document.getElementById('menuToggle').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('active');
});
</script>
</body>
</html>
