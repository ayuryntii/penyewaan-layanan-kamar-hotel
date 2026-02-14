<?php
// ajax/save_category.php
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
    $category_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = trim($conn->real_escape_string($_POST['name']));
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $base_price = floatval($_POST['base_price']);
    $max_capacity = intval($_POST['max_capacity']);
    $amenities = $conn->real_escape_string($_POST['amenities'] ?? '');
    
    // Validate input
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Category name is required';
    }
    
    if ($base_price <= 0) {
        $errors[] = 'Base price must be greater than 0';
    }
    
    if ($max_capacity <= 0) {
        $errors[] = 'Max capacity must be greater than 0';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
        exit();
    }
    
    try {
        if ($category_id == 0) {
            // Check if category name already exists
            $check_sql = "SELECT id FROM room_categories WHERE name = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $check_result = $stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Category name already exists!']);
                exit();
            }
            
            // Insert new category
            $sql = "INSERT INTO room_categories (name, description, base_price, max_capacity, amenities, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdis", $name, $description, $base_price, $max_capacity, $amenities);
            $action = 'added';
        } else {
            // Update existing category
            $sql = "UPDATE room_categories SET 
                    name = ?,
                    description = ?,
                    base_price = ?,
                    max_capacity = ?,
                    amenities = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdisi", $name, $description, $base_price, $max_capacity, $amenities, $category_id);
            $action = 'updated';
        }
        
        if ($stmt->execute()) {
            // Get the category ID if it was an insert
            if ($category_id == 0) {
                $category_id = $stmt->insert_id;
            }
            
            // Get category details for response
            $category_sql = "SELECT * FROM room_categories WHERE id = ?";
            $category_stmt = $conn->prepare($category_sql);
            $category_stmt->bind_param("i", $category_id);
            $category_stmt->execute();
            $category_result = $category_stmt->get_result();
            $category_data = $category_result->fetch_assoc();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Category ' . $action . ' successfully!',
                'category' => $category_data
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