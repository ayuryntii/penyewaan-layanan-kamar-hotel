<?php

// Format Currency
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

// Get Status Badge
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status, $type = 'booking') {
        $badges = [
            'booking' => [
                'pending' => '<span class="badge bg-warning">Pending</span>',
                'confirmed' => '<span class="badge bg-info">Confirmed</span>',
                'checked_in' => '<span class="badge bg-success">Checked In</span>',
                'checked_out' => '<span class="badge bg-secondary">Checked Out</span>',
                'cancelled' => '<span class="badge bg-danger">Cancelled</span>',
                'no_show' => '<span class="badge bg-dark">No Show</span>'
            ],
            'payment' => [
                'pending' => '<span class="badge bg-warning">Pending</span>',
                'partial' => '<span class="badge bg-primary">Partial</span>',
                'paid' => '<span class="badge bg-success">Paid</span>',
                'refunded' => '<span class="badge bg-info">Refunded</span>',
                'failed' => '<span class="badge bg-danger">Failed</span>'
            ],
            'room' => [
                'available' => '<span class="badge bg-success">Available</span>',
                'occupied' => '<span class="badge bg-danger">Occupied</span>',
                'maintenance' => '<span class="badge bg-warning">Maintenance</span>',
                'cleaning' => '<span class="badge bg-info">Cleaning</span>',
                'reserved' => '<span class="badge bg-primary">Reserved</span>'
            ]
        ];

        return $badges[$type][$status] ?? '<span class="badge bg-secondary">Unknown</span>';
    }
}

// Check if user is logged in
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

// Check if user is admin
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
    }
}

// Check if user is receptionist
if (!function_exists('isReceptionist')) {
    function isReceptionist() {
        return isset($_SESSION['role']) && $_SESSION['role'] == 'receptionist';
    }
}

// Check if user is customer
if (!function_exists('isCustomer')) {
    function isCustomer() {
        return isset($_SESSION['role']) && $_SESSION['role'] == 'customer';
    }
}

// Require login
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            header("Location: " . BASE_URL . "login.php");
            exit();
        }
    }
}

// Require admin
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        requireLogin();
        if (!isAdmin() && !isReceptionist()) {
            header("Location: " . BASE_URL . "index.php");
            exit();
        }
    }
}

// Hash password
if (!function_exists('hashPassword')) {
    function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}

// Verify password
if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

// Upload file
if (!function_exists('uploadFile')) {
    function uploadFile($file, $folder = 'uploads') {
        $target_dir = $folder . "/";

        // Create directory if not exists
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $filename = time() . "_" . basename($file["name"]);
        $target_file = $target_dir . $filename;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if file is an actual image
        $check = getimagesize($file["tmp_name"]);
        if ($check === false) {
            return ['success' => false, 'error' => 'File is not an image.'];
        }

        // Check file size (5MB)
        if ($file["size"] > 5000000) {
            return ['success' => false, 'error' => 'File is too large.'];
        }

        // Allow certain file formats
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowed_types)) {
            return ['success' => false, 'error' => 'Only JPG, JPEG, PNG & GIF files are allowed.'];
        }

        // Upload file
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return ['success' => true, 'filename' => $filename, 'path' => $target_file];
        } else {
            return ['success' => false, 'error' => 'Error uploading file.'];
        }
    }
}

// Sanitize input
if (!function_exists('sanitize')) {
    function sanitize($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
}

// Get user by ID
if (!function_exists('getUserById')) {
    function getUserById($id, $conn) {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}
