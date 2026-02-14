<?php
// hotel/ajax/update_customer_status.php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'receptionist')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$id = intval($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
    exit();
}

if (!in_array($status, ['active', 'inactive', 'banned'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Update status
$sql = "UPDATE users SET status = ? WHERE id = ? AND role = 'customer'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Customer status updated to ' . $status]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>