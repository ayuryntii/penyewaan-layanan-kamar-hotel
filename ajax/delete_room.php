<?php
// ajax/delete_room.php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access!']);
    exit();
}

// Check if user is admin
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Access denied!']);
    exit();
}

if (isset($_GET['id'])) {
    $room_id = intval($_GET['id']);
    
    if ($room_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid room ID!']);
        exit();
    }
    
    try {
        // Check if room has active bookings
        $check_sql = "SELECT COUNT(*) as booking_count FROM bookings 
                     WHERE room_id = ? AND booking_status IN ('confirmed', 'checked_in')";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        if ($data['booking_count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete room with active bookings!']);
            exit();
        }
        
        // Delete the room
        $delete_sql = "DELETE FROM rooms WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $room_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Room deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete room!']);
        }
        
        $delete_stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Room ID is required!']);
}
?>