<?php
// ajax/set_primary_image.php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $room_id = intval($_POST['room_id']);
    $image_name = $conn->real_escape_string($_POST['image_name']);
    
    // Get current images
    $sql = "SELECT images FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $room = $result->fetch_assoc();
        $images = json_decode($room['images'], true);
        
        // Find the image and move it to first position
        $key = array_search($image_name, $images);
        if ($key !== false) {
            unset($images[$key]);
            array_unshift($images, $image_name);
            
            // Update database
            $images_json = json_encode(array_values($images));
            $update_sql = "UPDATE rooms SET images = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $images_json, $room_id);
            
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Primary image updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Update failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Image not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Room not found']);
    }
}
?>