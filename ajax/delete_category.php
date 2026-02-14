<?php
// hotel/ajax/delete_category.php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'receptionist')) {
    header('Location: ../login.php');
    exit();
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = 'Invalid category ID';
    header('Location: ../admin/rooms.php?action=categories');
    exit();
}

// Check if category is used by any rooms
$check_sql = "SELECT COUNT(*) as count FROM rooms WHERE category_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$room_count = $check_result->fetch_assoc()['count'];
$check_stmt->close();

if ($room_count > 0) {
    $_SESSION['error'] = 'Cannot delete category. There are ' . $room_count . ' rooms using this category.';
    header('Location: ../admin/rooms.php?action=categories');
    exit();
}

// Delete category
$sql = "DELETE FROM room_categories WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $_SESSION['success'] = 'Category deleted successfully!';
} else {
    $_SESSION['error'] = 'Failed to delete category: ' . $conn->error;
}

$stmt->close();
header('Location: ../admin/rooms.php?action=categories');
exit();
?>