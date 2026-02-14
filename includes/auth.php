<?php
// Include config
require_once 'config.php';

// Authentication functions
function loginUser($username, $password, $conn) {
    $sql = "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Update last login
            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_picture'] = $user['profile_picture'];
            
            $stmt->close();
            return ['success' => true, 'user' => $user];
        }
    }
    
    $stmt->close();
    return ['success' => false, 'message' => 'Invalid username or password'];
}

function registerUser($data, $conn) {
    // Check if username or email already exists
    $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $data['username'], $data['email']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $check_stmt->close();
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    $check_stmt->close();
    
    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT);
    
    // Insert user
    $sql = "INSERT INTO users (username, email, password, full_name, phone, role) 
            VALUES (?, ?, ?, ?, ?, 'customer')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", 
        $data['username'], 
        $data['email'], 
        $hashed_password,
        $data['full_name'],
        $data['phone']
    );
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        
        // Get the new user
        $user_sql = "SELECT * FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc();
        
        $user_stmt->close();
        $stmt->close();
        
        // Auto login after registration
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        
        return ['success' => true, 'user' => $user];
    }
    
    $stmt->close();
    return ['success' => false, 'message' => 'Registration failed'];
}

function logoutUser() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy session
    if (session_id() != "" || isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 2592000, '/');
    }
    
    session_destroy();
    
    // Redirect to login
    header("Location: " . BASE_URL . "login.php");
    exit();
}
?>