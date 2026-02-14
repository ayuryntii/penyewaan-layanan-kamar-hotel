<?php
// admin/ajax/save_service.php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $service_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = trim($conn->real_escape_string($_POST['name']));
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $price = floatval($_POST['price']);
    $unit = $conn->real_escape_string($_POST['unit'] ?? 'per item');
    $category = $conn->real_escape_string($_POST['category'] ?? 'other');
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    try {
        if ($service_id == 0) {
            $sql = "INSERT INTO services (name, description, price, unit, category, is_available) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdssi", $name, $description, $price, $unit, $category, $is_available);
        } else {
            $sql = "UPDATE services SET 
                    name = ?, description = ?, price = ?, unit = ?, 
                    category = ?, is_available = ?, updated_at = NOW()
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdssii", $name, $description, $price, $unit, $category, $is_available, $service_id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Service saved successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>