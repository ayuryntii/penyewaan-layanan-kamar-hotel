<?php
// hotel/ajax/save_customer.php
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
$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$status = trim($_POST['status'] ?? 'active');
$notes = trim($_POST['notes'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validation
$errors = [];

if (empty($full_name)) {
    $errors[] = 'Full name is required';
}

if (empty($username)) {
    $errors[] = 'Username is required';
}

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

if (empty($phone)) {
    $errors[] = 'Phone number is required';
}

if (!in_array($status, ['active', 'inactive', 'banned'])) {
    $status = 'active';
}

// Check if username already exists (for other users)
if ($id > 0) {
    $check_sql = "SELECT id FROM users WHERE username = ? AND id != ? AND role = 'customer'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $username, $id);
} else {
    $check_sql = "SELECT id FROM users WHERE username = ? AND role = 'customer'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $username);
}

$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $errors[] = 'Username already exists';
}
$check_stmt->close();

// Check if email already exists (for other users)
if ($id > 0) {
    $check_sql = "SELECT id FROM users WHERE email = ? AND id != ? AND role = 'customer'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $email, $id);
} else {
    $check_sql = "SELECT id FROM users WHERE email = ? AND role = 'customer'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
}

$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $errors[] = 'Email already exists';
}
$check_stmt->close();

// Password validation
if (!empty($password)) {
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
    exit();
}

// Prepare SQL statement
if ($id > 0) {
    // Update existing customer
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, address = ?, 
                status = ?, notes = ?, password = ? WHERE id = ? AND role = 'customer'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssi", $full_name, $username, $email, $phone, $address, $status, $notes, $hashed_password, $id);
    } else {
        $sql = "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, address = ?, 
                status = ?, notes = ? WHERE id = ? AND role = 'customer'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssi", $full_name, $username, $email, $phone, $address, $status, $notes, $id);
    }
} else {
    // Insert new customer
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Password is required for new customers']);
        exit();
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (full_name, username, email, phone, address, status, notes, password, role, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'customer', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $full_name, $username, $email, $phone, $address, $status, $notes, $hashed_password);
}

// Execute query
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => $id > 0 ? 'Customer updated successfully' : 'Customer added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

if (isset($stmt)) {
    $stmt->close();
}

$conn->close();
?>