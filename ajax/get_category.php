<?php
// hotel/ajax/get_category.php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'receptionist')) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['error' => 'Invalid category ID']);
    exit();
}

// Get category data
$sql = "SELECT * FROM room_categories WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['error' => 'Category not found']);
    exit();
}

$category = $result->fetch_assoc();
$stmt->close();

// Decode amenities
$category['amenities'] = $category['amenities'] ? json_decode($category['amenities'], true) : [];

// Return as JSON
echo json_encode(['success' => true, 'data' => $category]);
?>