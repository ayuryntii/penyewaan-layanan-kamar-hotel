<?php
// hotel/ajax/delete_image.php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'receptionist')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$room_id = intval($_POST['room_id'] ?? 0);
$image_name = $_POST['image_name'] ?? '';

if ($room_id <= 0 || empty($image_name)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Get current images
$sql = "SELECT images FROM rooms WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Room not found']);
    exit();
}

$room = $result->fetch_assoc();
$images = $room['images'] ? json_decode($room['images'], true) : [];

// Remove image from array
$images = array_filter($images, function($img) use ($image_name) {
    return $img !== $image_name;
});

// Delete physical file
$file_path = '../assets/uploads/rooms/' . $image_name;
if (file_exists($file_path)) {
    unlink($file_path);
}

// Update database
$images_json = json_encode(array_values($images));
$update_sql = "UPDATE rooms SET images = ? WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("si", $images_json, $room_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete image']);
}

$update_stmt->close();
$stmt->close();
?>