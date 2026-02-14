<?php
// ajax/save_room.php
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $room_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $room_number = trim($conn->real_escape_string($_POST['room_number']));
    $category_id = intval($_POST['category_id']);
    $floor = $conn->real_escape_string($_POST['floor']);
    $view_type = $conn->real_escape_string($_POST['view_type']);
    $bed_type = $conn->real_escape_string($_POST['bed_type']);
    $smoking = isset($_POST['smoking']) ? intval($_POST['smoking']) : 0;
    $status = $conn->real_escape_string($_POST['status']);
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    
    // Validate input
    $errors = [];
    
    if (empty($room_number)) {
        $errors[] = 'Room number is required';
    }
    
    if ($category_id <= 0) {
        $errors[] = 'Category is required';
    }
    
    if (empty($floor)) {
        $errors[] = 'Floor is required';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
        exit();
    }
    
    try {
        if ($room_id == 0) {
            // Check if room number already exists
            $check_sql = "SELECT id FROM rooms WHERE room_number = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("s", $room_number);
            $stmt->execute();
            $check_result = $stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Room number already exists!']);
                exit();
            }
            
            // Insert new room
            $sql = "INSERT INTO rooms (room_number, category_id, floor, view_type, bed_type, smoking, status, description, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sissiiss", $room_number, $category_id, $floor, $view_type, $bed_type, $smoking, $status, $description);
            $action = 'added';
        } else {
            // Update existing room
            $sql = "UPDATE rooms SET 
                    room_number = ?,
                    category_id = ?,
                    floor = ?,
                    view_type = ?,
                    bed_type = ?,
                    smoking = ?,
                    status = ?,
                    description = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sissiissi", $room_number, $category_id, $floor, $view_type, $bed_type, $smoking, $status, $description, $room_id);
            $action = 'updated';
        }
        
        if ($stmt->execute()) {
            // Get the room ID if it was an insert
            if ($room_id == 0) {
                $room_id = $stmt->insert_id;
            }
            
            // Get room details for response
            $room_sql = "SELECT r.*, rc.name as category_name, rc.base_price 
                        FROM rooms r 
                        JOIN room_categories rc ON r.category_id = rc.id 
                        WHERE r.id = ?";
            $room_stmt = $conn->prepare($room_sql);
            $room_stmt->bind_param("i", $room_id);
            $room_stmt->execute();
            $room_result = $room_stmt->get_result();
            $room_data = $room_result->fetch_assoc();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Room ' . $action . ' successfully!',
                'room' => $room_data
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method!']);
}
?>