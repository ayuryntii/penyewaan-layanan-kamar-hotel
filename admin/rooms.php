<?php
// admin/rooms.php - ROOM MANAGEMENT FULL FUNCTIONALITY + IMAGE UPLOAD (RAPIH)
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireAdmin();

$page_title = 'Room Management';
$action = $_GET['action'] ?? '';

/**
 * ==================================================
 * HELPER: UPLOAD ROOM IMAGE
 * ==================================================
 */
function uploadRoomImage($file)
{
    if (!isset($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        return null;
    }

    if ($file['size'] > 2 * 1024 * 1024) { // 2MB
        return null;
    }

    $upload_dir = "../assets/uploads/rooms/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $new_name = "room_" . time() . "_" . rand(1000, 9999) . "." . $ext;
    $target = $upload_dir . $new_name;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        return $new_name;
    }

    return null;
}

/**
 * ==================================================
 * ACTION: DELETE ROOM
 * ==================================================
 */
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        $_SESSION['error'] = 'Invalid room ID.';
        header("Location: rooms.php");
        exit();
    }

    // Ambil foto lama dulu (kalau ada)
    $old = null;
    $get = $conn->prepare("SELECT image FROM rooms WHERE id = ?");
    $get->bind_param("i", $id);
    $get->execute();
    $old = $get->get_result()->fetch_assoc();
    $get->close();

   // cek apakah room dipakai di booking
$check = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE room_id = ?");
$check->bind_param("i", $id);
$check->execute();
$result = $check->get_result()->fetch_assoc();
$check->close();

if ($result['total'] > 0) {
    $_SESSION['error'] = "Room cannot be deleted because it has booking history.";
    header("Location: rooms.php");
    exit;
}

// aman dihapus
$stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Room deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete room.";
}
$stmt->close();

header("Location: rooms.php");
exit;

}

/**
 * ==================================================
 * ACTION: ADD ROOM (POST)
 * ==================================================
 */
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $room_number = trim($_POST['room_number'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $floor       = trim($_POST['floor'] ?? '');
    $view_type   = $_POST['view_type'] ?? 'city';
    $bed_type    = $_POST['bed_type'] ?? 'double';
    $smoking     = isset($_POST['smoking']) ? 1 : 0;
    $description = trim($_POST['description'] ?? '');
    $status      = $_POST['status'] ?? 'available';

    $errors = [];
    if ($room_number === '') $errors[] = 'Room number is required';
    if ($category_id <= 0)  $errors[] = 'Category is required';
    if ($floor === '')      $errors[] = 'Floor is required';

    // Check unique room number
    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM rooms WHERE room_number = ?");
        $check->bind_param("s", $room_number);
        $check->execute();
        $res = $check->get_result();
        $check->close();
        if ($res->num_rows > 0) {
            $errors[] = 'Room number already exists!';
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header("Location: rooms.php?action=add");
        exit();
    }

    // upload image
    $image_name = uploadRoomImage($_FILES['room_image'] ?? null);

    $stmt = $conn->prepare("INSERT INTO rooms 
        (room_number, category_id, floor, view_type, bed_type, smoking, description, image, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "sisssisss",
        $room_number,
        $category_id,
        $floor,
        $view_type,
        $bed_type,
        $smoking,
        $description,
        $image_name,
        $status
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = "Room added successfully!";
        $stmt->close();
        header("Location: rooms.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to add room: " . $stmt->error;
        $stmt->close();
        header("Location: rooms.php?action=add");
        exit();
    }
}

/**
 * ==================================================
 * ACTION: EDIT ROOM (POST)
 * ==================================================
 */
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $id          = intval($_GET['id'] ?? 0);
    $room_number = trim($_POST['room_number'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $floor       = trim($_POST['floor'] ?? '');
    $view_type   = $_POST['view_type'] ?? 'city';
    $bed_type    = $_POST['bed_type'] ?? 'double';
    $smoking     = isset($_POST['smoking']) ? 1 : 0;
    $description = trim($_POST['description'] ?? '');
    $status      = $_POST['status'] ?? 'available';

    if ($id <= 0) {
        $_SESSION['error'] = "Invalid room ID.";
        header("Location: rooms.php");
        exit();
    }

    $errors = [];
    if ($room_number === '') $errors[] = 'Room number is required';
    if ($category_id <= 0)  $errors[] = 'Category is required';
    if ($floor === '')      $errors[] = 'Floor is required';

    // Check unique except itself
    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM rooms WHERE room_number = ? AND id != ?");
        $check->bind_param("si", $room_number, $id);
        $check->execute();
        $res = $check->get_result();
        $check->close();
        if ($res->num_rows > 0) {
            $errors[] = 'Room number already exists!';
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header("Location: rooms.php?action=edit&id=" . $id);
        exit();
    }

    // ambil image lama
    $oldImage = null;
    $oldStmt = $conn->prepare("SELECT image FROM rooms WHERE id = ?");
    $oldStmt->bind_param("i", $id);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();
    $oldImage = $oldRow['images'] ?? null;

    // upload image baru kalau ada
    $newImage = uploadRoomImage($_FILES['room_image'] ?? null);

    if ($newImage) {
        // hapus image lama
        if (!empty($oldImage)) {
            $path = "../assets/uploads/rooms/" . $oldImage;
            if (file_exists($path)) @unlink($path);
        }

        $stmt = $conn->prepare("UPDATE rooms 
            SET room_number=?, category_id=?, floor=?, view_type=?, bed_type=?, smoking=?, description=?, image=?, status=?
            WHERE id=?
        ");

        $stmt->bind_param(
            "sisssisssi",
            $room_number,
            $category_id,
            $floor,
            $view_type,
            $bed_type,
            $smoking,
            $description,
            $newImage,
            $status,
            $id
        );
    } else {
        $stmt = $conn->prepare("UPDATE rooms 
            SET room_number=?, category_id=?, floor=?, view_type=?, bed_type=?, smoking=?, description=?, status=?
            WHERE id=?
        ");

        $stmt->bind_param(
            "sisssissi",
            $room_number,
            $category_id,
            $floor,
            $view_type,
            $bed_type,
            $smoking,
            $description,
            $status,
            $id
        );
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = "Room updated successfully!";
        $stmt->close();
        header("Location: rooms.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to update room: " . $stmt->error;
        $stmt->close();
        header("Location: rooms.php?action=edit&id=" . $id);
        exit();
    }
}

/**
 * ==================================================
 * ACTION: CATEGORIES
 * ==================================================
 */
if ($action === 'categories') {

    // Delete category
    if (isset($_GET['delete_category'])) {
        $category_id = intval($_GET['delete_category']);

        // Check category has rooms
        $check_sql = "SELECT COUNT(*) as count FROM rooms WHERE category_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $room_count = $check_result->fetch_assoc()['count'] ?? 0;
        $check_stmt->close();

        if ($room_count > 0) {
            $_SESSION['error'] = 'Cannot delete category. There are ' . $room_count . ' room(s) in this category.';
            header('Location: rooms.php?action=categories');
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM room_categories WHERE id = ?");
        $stmt->bind_param("i", $category_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Category deleted successfully!';
        } else {
            $_SESSION['error'] = 'Failed to delete category.';
        }
        $stmt->close();
        header('Location: rooms.php?action=categories');
        exit();
    }

    // Save/update category (AJAX)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'save_category') {
        header('Content-Type: application/json');

        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $base_price = floatval(preg_replace('/[^\d]/', '', $_POST['base_price'] ?? 0));
        $max_capacity = intval($_POST['max_capacity'] ?? 2);
        $amenities = trim($_POST['amenities'] ?? '');

        $errors = [];

        if (empty($name)) $errors[] = 'Category name is required';
        if ($base_price <= 0) $errors[] = 'Base price must be greater than 0';
        if ($max_capacity <= 0) $errors[] = 'Max capacity must be greater than 0';

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
            exit();
        }

        if ($id > 0) {
            $sql = "UPDATE room_categories 
                    SET name = ?, description = ?, base_price = ?, max_capacity = ?, amenities = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdisi", $name, $description, $base_price, $max_capacity, $amenities, $id);
            $message = 'Category updated successfully!';
        } else {
            $sql = "INSERT INTO room_categories (name, description, base_price, max_capacity, amenities, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdis", $name, $description, $base_price, $max_capacity, $amenities);
            $message = 'Category added successfully!';
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }

        $stmt->close();
        exit();
    }

    // Get category (AJAX)
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_category') {
        header('Content-Type: application/json');

        $category_id = intval($_GET['id'] ?? 0);

        if ($category_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
            exit();
        }

        $sql = "SELECT * FROM room_categories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Category not found']);
            exit();
        }

        $category = $result->fetch_assoc();
        $stmt->close();

        echo json_encode(['success' => true, 'data' => $category]);
        exit();
    }
}

/**
 * ==================================================
 * Helper: get room detail for view/edit
 * ==================================================
 */
$roomDetail = null;
if (($action === 'view' || $action === 'edit') && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT r.*, rc.name AS category_name, rc.base_price
                                FROM rooms r
                                JOIN room_categories rc ON r.category_id = rc.id
                                WHERE r.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $roomDetail = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$roomDetail) {
            $_SESSION['error'] = "Room not found.";
            header("Location: rooms.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($hotel_name ?? 'Hotel') ?></title>

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

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--navy);
            height: 100vh;
            position: fixed;
            left: 0; top: 0;
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

        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            margin-bottom: 25px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.18);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 18px 22px;
            flex-wrap: wrap;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body { padding: 22px; }

        /* Buttons */
        .btn {
            padding: 10px 14px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-sm { padding: 7px 10px; font-size: 12px; border-radius: 10px; }

        .btn-primary { background: var(--blue); color: var(--navy); }
        .btn-secondary { background: transparent; border: 1px solid var(--blue); color: var(--blue); }
        .btn-danger { background: #ef233c; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-info { background: #17a2b8; color: white; }

        .btn-primary:hover { background: #3abde0; transform: translateY(-1px); }
        .btn-secondary:hover { background: rgba(76,201,240,0.1); transform: translateY(-1px); }

        /* Alerts */
        .alert {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 18px;
            border: 1px solid transparent;
            font-size: 14px;
            display:flex;
            align-items:center;
            gap:10px;
        }
        .alert-success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border-color: rgba(46, 204, 113, 0.3); }
        .alert-danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border-color: rgba(231, 76, 60, 0.3); }

        /* Inputs */
        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            background: rgba(255,255,255,0.06);
            color: white;
            transition: all 0.2s ease;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.5); }
        .form-control:focus {
            outline:none;
            border-color: rgba(76,201,240,0.85);
            box-shadow: 0 0 0 3px rgba(76,201,240,0.15);
            background: rgba(255,255,255,0.08);
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z' fill='%23ccc'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            padding-right: 34px;
        }

        .form-label {
            display:block;
            font-weight:500;
            margin-bottom:8px;
            color: rgba(255,255,255,0.9);
        }

        /* Filters */
        .card-filters {
            display:grid;
            grid-template-columns: 1.2fr 1fr 1fr;
            gap: 14px;
            margin-bottom: 18px;
        }
        @media (max-width: 992px) {
            .card-filters { grid-template-columns: 1fr; }
        }

        /* Table */
        .table-responsive {
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
        }

        table {
            width:100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.03);
        }

        thead th {
            background: rgba(76, 201, 240, 0.08);
            padding: 14px 14px;
            text-align:left;
            color: var(--blue);
            font-size: 12px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        td {
            padding: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.85);
            vertical-align: middle;
            font-size: 14px;
        }

        tbody tr:hover td {
            background: rgba(76,201,240,0.06);
        }

        .status-badge {
            border: 1px solid rgba(255,255,255,0.12);
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            display:inline-block;
        }
        .status-success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .status-warning { background: rgba(243, 156, 18, 0.2); color: #f39c12; }
        .status-danger  { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .status-info    { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        .status-purple  { background: rgba(155, 89, 182, 0.2); color: #9b59b6; }

        /* Category Grid */
        .category-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 12px;
        }
        @media (max-width: 1200px) { .category-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 768px) { .category-grid { grid-template-columns: 1fr; } }

        .category-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 18px;
            transition: all 0.2s ease;
        }
        .category-card:hover {
            border-color: rgba(76, 201, 240, 0.35);
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(0,0,0,0.25);
        }

        .category-header {
            display:flex;
            justify-content: space-between;
            align-items:flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }
        .category-header h4 {
            margin:0 0 4px 0;
            color:#fff;
            font-weight:700;
            font-size:1.05rem;
        }
        .category-price {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--blue);
            white-space: nowrap;
        }

        .category-amenities {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 12px;
            padding: 12px;
            font-size: 0.9rem;
            color: rgba(255,255,255,0.75);
        }

        .category-actions {
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            margin-top: 14px;
        }

        /* Modal */
        .modal {
            display:none;
            position:fixed;
            inset:0;
            background: rgba(0,0,0,0.7);
            z-index:9999;
            justify-content:center;
            align-items:center;
            padding:20px;
        }
        .modal-content {
            width:100%;
            max-width: 620px;
            background: rgba(20, 30, 50, 0.95);
            border:1px solid rgba(76,201,240,0.18);
            border-radius: 18px;
            overflow:hidden;
            box-shadow: 0 18px 40px rgba(0,0,0,0.45);
        }
        .modal-header {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
        .modal-header h3 { margin:0; font-size:1.05rem; font-weight:700; }
        .modal-body { padding: 20px; }

        .close-modal {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            color:#fff;
            cursor:pointer;
        }

        /* Responsive sidebar */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
        }

        /* Image table */
        .img-thumb {
            width: 70px;
            height: 45px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .img-empty {
            width: 70px;
            height: 45px;
            border-radius: 10px;
            background: rgba(255,255,255,0.06);
            border: 1px dashed rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-size: 12px;
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
                <h3><?= htmlspecialchars($hotel_name ?? 'Hotel') ?></h3>
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

                <a href="rooms.php" class="nav-item <?= $action === '' ? 'active' : '' ?>">
                    <i class="fas fa-bed"></i>
                    <span>All Rooms</span>
                </a>

                <a href="rooms.php?action=add" class="nav-item <?= $action === 'add' ? 'active' : '' ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add New Room</span>
                </a>

                <a href="rooms.php?action=categories" class="nav-item <?= $action === 'categories' ? 'active' : '' ?>">
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
                <a href="services.php" class="nav-item">
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
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 style="font-size:1.2rem;"><?= htmlspecialchars($page_title) ?></h1>
            </div>
            <div>
                <a href="../logout.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <div class="content-area">

            <!-- Flash Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>


            <!-- ======================
                 ADD ROOM PAGE
                 ====================== -->
            <?php if ($action === 'add'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Add New Room</h3>
                        <a href="rooms.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>

                    <div class="card-body">
                        <form method="POST" action="rooms.php?action=add" enctype="multipart/form-data">

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                                <div>
                                    <label class="form-label">Room Number *</label>
                                    <input type="text" name="room_number" class="form-control" required placeholder="101 / 201A">
                                </div>

                                <div>
                                    <label class="form-label">Floor *</label>
                                    <input type="text" name="floor" class="form-control" required placeholder="1 / 2 / 3">
                                </div>
                            </div>

                            <div style="margin-top:18px;">
                                <label class="form-label">Category *</label>
                                <select name="category_id" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <?php
                                    $cats = $conn->query("SELECT * FROM room_categories ORDER BY name ASC");
                                    while ($cat = $cats->fetch_assoc()):
                                    ?>
                                        <option value="<?= $cat['id'] ?>">
                                            <?= htmlspecialchars($cat['name']) ?> - <?= formatCurrency($cat['base_price']) ?>/night
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px;">
                                <div>
                                    <label class="form-label">View Type</label>
                                    <select name="view_type" class="form-control">
                                        <option value="city">City</option>
                                        <option value="garden">Garden</option>
                                        <option value="pool">Pool</option>
                                        <option value="sea">Sea</option>
                                        <option value="mountain">Mountain</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Bed Type</label>
                                    <select name="bed_type" class="form-control">
                                        <option value="single">Single</option>
                                        <option value="double" selected>Double</option>
                                        <option value="queen">Queen</option>
                                        <option value="king">King</option>
                                        <option value="twin">Twin</option>
                                    </select>
                                </div>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px;">
                                <div>
                                    <label class="form-label">Status *</label>
                                    <select name="status" class="form-control" required>
                                        <option value="available" selected>Available</option>
                                        <option value="occupied">Occupied</option>
                                        <option value="reserved">Reserved</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="cleaning">Cleaning</option>
                                    </select>
                                </div>

                                <div style="display:flex;align-items:center;gap:10px;margin-top:30px;">
                                    <input type="checkbox" name="smoking" value="1" id="smoking">
                                    <label for="smoking" style="color:#ccc;">Smoking Room</label>
                                </div>
                            </div>

                            <div style="margin-top:18px;">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Room description..."></textarea>
                            </div>

                            <!-- Upload Photo -->
                            <div style="margin-top:18px;">
                                <label class="form-label">Room Photo</label>
                                <input type="file" name="room_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                                <small style="color:#aaa;">Max 2MB (JPG/PNG/WEBP)</small>
                            </div>

                            <div style="margin-top:22px;display:flex;gap:10px;flex-wrap:wrap;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Room
                                </button>
                                <a href="rooms.php" class="btn btn-secondary">Cancel</a>
                            </div>

                        </form>
                    </div>
                </div>


            <!-- ======================
                 EDIT ROOM PAGE
                 ====================== -->
            <?php elseif ($action === 'edit' && $roomDetail): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-edit"></i> Edit Room: <?= htmlspecialchars($roomDetail['room_number']) ?></h3>
                        <a href="rooms.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>

                    <div class="card-body">
                        <form method="POST" action="rooms.php?action=edit&id=<?= $roomDetail['id'] ?>" enctype="multipart/form-data">

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                                <div>
                                    <label class="form-label">Room Number *</label>
                                    <input type="text" name="room_number" class="form-control" required
                                           value="<?= htmlspecialchars($roomDetail['room_number']) ?>">
                                </div>

                                <div>
                                    <label class="form-label">Floor *</label>
                                    <input type="text" name="floor" class="form-control" required
                                           value="<?= htmlspecialchars($roomDetail['floor']) ?>">
                                </div>
                            </div>

                            <div style="margin-top:18px;">
                                <label class="form-label">Category *</label>
                                <select name="category_id" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <?php
                                    $cats = $conn->query("SELECT * FROM room_categories ORDER BY name ASC");
                                    while ($cat = $cats->fetch_assoc()):
                                        $selected = ($cat['id'] == $roomDetail['category_id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $cat['id'] ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($cat['name']) ?> - <?= formatCurrency($cat['base_price']) ?>/night
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px;">
                                <div>
                                    <label class="form-label">View Type</label>
                                    <select name="view_type" class="form-control">
                                        <?php
                                        $views = ['city','garden','pool','sea','mountain'];
                                        foreach($views as $v):
                                            $sel = ($roomDetail['view_type'] === $v) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $v ?>" <?= $sel ?>><?= ucfirst($v) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Bed Type</label>
                                    <select name="bed_type" class="form-control">
                                        <?php
                                        $beds = ['single','double','queen','king','twin'];
                                        foreach($beds as $b):
                                            $sel = ($roomDetail['bed_type'] === $b) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $b ?>" <?= $sel ?>><?= ucfirst($b) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px;">
                                <div>
                                    <label class="form-label">Status *</label>
                                    <select name="status" class="form-control" required>
                                        <?php
                                        $statuses = ['available','occupied','reserved','maintenance','cleaning'];
                                        foreach($statuses as $st):
                                            $sel = ($roomDetail['status'] === $st) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $st ?>" <?= $sel ?>><?= ucfirst($st) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div style="display:flex;align-items:center;gap:10px;margin-top:30px;">
                                    <input type="checkbox" name="smoking" value="1" id="smoking"
                                           <?= $roomDetail['smoking'] ? 'checked' : '' ?>>
                                    <label for="smoking" style="color:#ccc;">Smoking Room</label>
                                </div>
                            </div>

                            <div style="margin-top:18px;">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($roomDetail['description']) ?></textarea>
                            </div>

                            <!-- Photo Preview + Upload -->
                            <div style="margin-top:18px;">
                                <label class="form-label">Room Photo</label>

                                <?php if (!empty($roomDetail['image'])): ?>
                                    <div style="margin-bottom:10px;">
                                        <img src="../assets/uploads/rooms/<?= htmlspecialchars($roomDetail['image']) ?>"
                                             style="width:160px;height:95px;object-fit:cover;border-radius:12px;border:1px solid rgba(255,255,255,0.15);">
                                    </div>
                                <?php endif; ?>

                                <input type="file" name="room_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                                <small style="color:#aaa;">Upload new photo to replace (Max 2MB)</small>
                            </div>

                            <div style="margin-top:22px;display:flex;gap:10px;flex-wrap:wrap;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Room
                                </button>
                                <a href="rooms.php" class="btn btn-secondary">Cancel</a>
                            </div>

                        </form>
                    </div>
                </div>


            <!-- ======================
                 VIEW ROOM PAGE
                 ====================== -->
            <?php elseif ($action === 'view' && $roomDetail): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-eye"></i> Room Detail: <?= htmlspecialchars($roomDetail['room_number']) ?></h3>
                        <a href="rooms.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>

                    <div class="card-body">
                        <?php if (!empty($roomDetail['image'])): ?>
                            <div style="margin-bottom:18px;">
                                <img src="../assets/uploads/rooms/<?= htmlspecialchars($roomDetail['image']) ?>"
                                     style="width:320px;max-width:100%;height:200px;object-fit:cover;border-radius:16px;border:1px solid rgba(255,255,255,0.12);">
                            </div>
                        <?php endif; ?>

                        <table>
                            <tr><th style="padding:10px;color:#aaa;">Room Number</th><td style="padding:10px;"><?= htmlspecialchars($roomDetail['room_number']) ?></td></tr>
                            <tr><th style="padding:10px;color:#aaa;">Category</th><td style="padding:10px;"><?= htmlspecialchars($roomDetail['category_name']) ?></td></tr>
                            <tr><th style="padding:10px;color:#aaa;">Floor</th><td style="padding:10px;"><?= htmlspecialchars($roomDetail['floor']) ?></td></tr>
                            <tr><th style="padding:10px;color:#aaa;">View Type</th><td style="padding:10px;"><?= htmlspecialchars(ucfirst($roomDetail['view_type'])) ?></td></tr>
                            <tr><th style="padding:10px;color:#aaa;">Bed Type</th><td style="padding:10px;"><?= htmlspecialchars(ucfirst($roomDetail['bed_type'])) ?></td></tr>
                            <tr><th style="padding:10px;color:#aaa;">Smoking</th><td style="padding:10px;"><?= $roomDetail['smoking'] ? 'Yes' : 'No' ?></td></tr>
                            <tr><th style="padding:10px;color:#aaa;">Status</th><td style="padding:10px;"><?= htmlspecialchars(ucfirst($roomDetail['status'])) ?></td></tr>
                            <tr><th style="padding:10px;color:#aaa;">Price</th><td style="padding:10px;"><?= formatCurrency($roomDetail['base_price']) ?>/night</td></tr>
                            <tr><th style="padding:10px;color:#aaa;">Description</th><td style="padding:10px;"><?= nl2br(htmlspecialchars($roomDetail['description'])) ?></td></tr>
                        </table>
                    </div>
                </div>


            <!-- ======================
                 CATEGORIES PAGE
                 ====================== -->
            <?php elseif ($action === 'categories'): ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-tags"></i> Room Categories</h3>
                        <button onclick="showAddCategoryModal()" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Category
                        </button>
                    </div>

                    <div class="card-body">
                        <div class="category-grid">
                            <?php
                            $categories_query = "SELECT * FROM room_categories ORDER BY base_price ASC";
                            $categories_result = $conn->query($categories_query);

                            if ($categories_result && $categories_result->num_rows > 0):
                                while ($category = $categories_result->fetch_assoc()):
                            ?>
                                <div class="category-card">
                                    <div class="category-header">
                                        <div style="min-width:0;">
                                            <h4><?= htmlspecialchars($category['name']) ?></h4>
                                            <small style="color:rgba(255,255,255,0.6);">
                                                Max: <?= intval($category['max_capacity']) ?> persons
                                            </small>
                                        </div>
                                        <div class="category-price">
                                            <?= formatCurrency($category['base_price']) ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($category['description'])): ?>
                                        <p style="color: rgba(255,255,255,0.78); font-size: 0.92rem; line-height:1.5; margin:0 0 12px 0;">
                                            <?= htmlspecialchars($category['description']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($category['amenities'])): ?>
                                        <div class="category-amenities">
                                            <strong style="color: rgba(255,255,255,0.9);">Amenities:</strong><br>
                                            <?= htmlspecialchars($category['amenities']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="category-actions">
                                        <button onclick="editCategory(<?= $category['id'] ?>)" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button onclick="deleteCategory(<?= $category['id'] ?>, '<?= htmlspecialchars(addslashes($category['name'])) ?>')" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            <?php
                                endwhile;
                            else:
                            ?>
                                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #aaa;">
                                    <i class="fas fa-tags fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i><br>
                                    <h3 style="color: #aaa; margin: 15px 0 10px 0;">No Categories Found</h3>
                                    <p style="color: #777; margin-bottom: 20px;">No room categories have been added yet.</p>
                                    <button onclick="showAddCategoryModal()" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add First Category
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Add/Edit Category Modal -->
                <div class="modal" id="categoryModal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 id="modalTitle">Add Room Category</h3>
                            <button class="close-modal" onclick="closeModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="categoryForm">
                                <input type="hidden" name="id" id="categoryId" value="0">
                                <input type="hidden" name="ajax_action" value="save_category">

                                <div style="margin-bottom:14px;">
                                    <label class="form-label">Category Name *</label>
                                    <input type="text" name="name" id="categoryName" class="form-control" required
                                           placeholder="e.g., Deluxe Room, Executive Suite">
                                </div>

                                <div style="margin-bottom:14px;">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="categoryDescription" class="form-control" rows="3"
                                              placeholder="Describe this room category..."></textarea>
                                </div>

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                                    <div>
                                        <label class="form-label">Base Price *</label>
                                        <input type="text" name="base_price" id="categoryPrice" class="form-control" required
                                               placeholder="450000">
                                        <small style="color:#aaa;">Numbers only</small>
                                    </div>

                                    <div>
                                        <label class="form-label">Max Capacity *</label>
                                        <input type="number" name="max_capacity" id="categoryCapacity" class="form-control" required
                                               min="1" max="10" value="2">
                                    </div>
                                </div>

                                <div style="margin-bottom:10px;">
                                    <label class="form-label">Amenities</label>
                                    <textarea name="amenities" id="categoryAmenities" class="form-control" rows="3"
                                              placeholder="AC, TV, WiFi, Bathub, Mini Bar..."></textarea>
                                    <small style="color:#aaa;">Separate with commas</small>
                                </div>

                                <div style="display:flex; gap:10px; margin-top:18px; flex-wrap:wrap;">
                                    <button type="button" onclick="saveCategory()" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Category
                                    </button>
                                    <button type="button" onclick="closeModal()" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>


            <!-- ======================
                 ALL ROOMS DEFAULT
                 ====================== -->
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bed"></i> All Rooms</h3>
                        <a href="rooms.php?action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Room
                        </a>
                    </div>

                    <div class="card-body">
                        <div class="card-filters">
                            <input type="text" id="searchRoom" class="form-control" placeholder="Search room...">

                            <select id="filterCategory" class="form-control">
                                <option value="">All Categories</option>
                                <?php
                                $catRes = $conn->query("SELECT id, name FROM room_categories ORDER BY name ASC");
                                while ($c = $catRes->fetch_assoc()):
                                ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endwhile; ?>
                            </select>

                            <select id="filterStatus" class="form-control">
                                <option value="">All Status</option>
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="reserved">Reserved</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="cleaning">Cleaning</option>
                            </select>
                        </div>

                        <div class="table-responsive">
                            <table id="roomsTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Photo</th>
                                        <th>Room Number</th>
                                        <th>Category</th>
                                        <th>Floor</th>
                                        <th>View</th>
                                        <th>Bed</th>
                                        <th>Smoking</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th style="min-width:220px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $sql = "SELECT r.*, rc.name AS category_name, rc.base_price
                                        FROM rooms r
                                        JOIN room_categories rc ON r.category_id = rc.id
                                        ORDER BY r.room_number ASC";
                                $res = $conn->query($sql);

                                $no = 1;
                                if ($res && $res->num_rows > 0):
                                    while ($row = $res->fetch_assoc()):
                                        $statusClass = 'status-info';
                                        if ($row['status'] === 'available') $statusClass = 'status-success';
                                        if ($row['status'] === 'occupied') $statusClass = 'status-danger';
                                        if ($row['status'] === 'reserved') $statusClass = 'status-warning';
                                        if ($row['status'] === 'maintenance') $statusClass = 'status-purple';
                                ?>
                                    <tr data-category="<?= $row['category_id'] ?>" data-status="<?= $row['status'] ?>">
                                        <td><?= $no++ ?></td>

                                        <td>
                                            <?php if (!empty($row['image'])): ?>
                                                <img class="img-thumb" src="../assets/uploads/rooms/<?= htmlspecialchars($row['image']) ?>" alt="Room Photo">
                                            <?php else: ?>
                                                <div class="img-empty">No Img</div>
                                            <?php endif; ?>
                                        </td>

                                        <td><strong><?= htmlspecialchars($row['room_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['category_name']) ?></td>
                                        <td><?= htmlspecialchars($row['floor']) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($row['view_type'])) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($row['bed_type'])) ?></td>
                                        <td><?= $row['smoking'] ? 'Yes' : 'No' ?></td>
                                        <td><?= formatCurrency($row['base_price']) ?>/night</td>
                                        <td>
                                            <span class="status-badge <?= $statusClass ?>">
                                                <?= htmlspecialchars(ucfirst($row['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a class="btn btn-sm btn-info" href="rooms.php?action=view&id=<?= $row['id'] ?>">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a class="btn btn-sm btn-warning" href="rooms.php?action=edit&id=<?= $row['id'] ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a class="btn btn-sm btn-danger" onclick="return confirm('Delete this room?')"
                                               href="rooms.php?action=delete&id=<?= $row['id'] ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="11" style="text-align:center; padding: 25px; color:#aaa;">
                                            No rooms found. Add your first room.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

                <script>
                (function(){
                    const search = document.getElementById('searchRoom');
                    const filterCat = document.getElementById('filterCategory');
                    const filterStatus = document.getElementById('filterStatus');
                    const table = document.getElementById('roomsTable');

                    function applyFilter(){
                        const q = (search.value || '').toLowerCase().trim();
                        const cat = filterCat.value;
                        const status = filterStatus.value;

                        const rows = table.querySelectorAll('tbody tr');
                        rows.forEach(row => {
                            const text = row.innerText.toLowerCase();
                            const rowCat = row.getAttribute('data-category') || '';
                            const rowStatus = row.getAttribute('data-status') || '';

                            const matchText = !q || text.includes(q);
                            const matchCat = !cat || rowCat === cat;
                            const matchStatus = !status || rowStatus === status;

                            row.style.display = (matchText && matchCat && matchStatus) ? '' : 'none';
                        });
                    }

                    search.addEventListener('keyup', applyFilter);
                    filterCat.addEventListener('change', applyFilter);
                    filterStatus.addEventListener('change', applyFilter);
                })();
                </script>

            <?php endif; ?>

        </div>
    </main>
</div>

<script>
    // Menu toggle
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });

    // ===== Category Functions (AJAX) =====
    function showAddCategoryModal() {
        document.getElementById('categoryForm').reset();
        document.getElementById('categoryId').value = '0';
        document.getElementById('modalTitle').textContent = 'Add Room Category';
        document.getElementById('categoryModal').style.display = 'flex';
        document.getElementById('categoryCapacity').value = '2';
    }

    function editCategory(id) {
        fetch('rooms.php?action=categories&ajax=get_category&id=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    const category = data.data;
                    document.getElementById('categoryId').value = category.id;
                    document.getElementById('categoryName').value = category.name;
                    document.getElementById('categoryDescription').value = category.description || '';
                    document.getElementById('categoryPrice').value = (category.base_price + '').toString().replace(/[^\d]/g, '');
                    document.getElementById('categoryCapacity').value = category.max_capacity || '2';
                    document.getElementById('categoryAmenities').value = category.amenities || '';
                    document.getElementById('modalTitle').textContent = 'Edit Category: ' + category.name;
                    document.getElementById('categoryModal').style.display = 'flex';
                } else {
                    alert(data.message || 'Failed to load category');
                }
            })
            .catch(() => alert('Failed to load category. Check console.'));
    }

    function saveCategory() {
        const form = document.getElementById('categoryForm');
        const formData = new FormData(form);

        let price = (formData.get('base_price') || '').toString().replace(/[^\d]/g, '');
        formData.set('base_price', price);

        fetch('rooms.php?action=categories', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                closeModal();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => alert('Failed to save category. Check console.'));
    }

    function deleteCategory(id, name) {
        if (confirm(`Delete category "${name}"?\n\nThis action cannot be undone.`)) {
            window.location.href = 'rooms.php?action=categories&delete_category=' + id;
        }
    }

    function closeModal() {
        document.getElementById('categoryModal').style.display = 'none';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    const categoryModal = document.getElementById('categoryModal');
    if (categoryModal) {
        categoryModal.addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    }
</script>

</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
?>
