<?php
// includes/config.php - FIXED & COMPLETE VERSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_hotel_complete');
define('BASE_URL', 'http://localhost/hotel/');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Create Connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        die("Koneksi database gagal: " . $conn->connect_error);
    }
    
    // Create database if not exists
    $create_db = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
    if ($conn->query($create_db) === TRUE) {
        $conn->select_db(DB_NAME);
    } else {
        die("Error creating database: " . $conn->error);
    }
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
    // Create tables if not exist
    createTables($conn);
    
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to create ALL tables
function createTables($conn) {
    // Users table
    $users_table = "CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `username` varchar(50) NOT NULL UNIQUE,
        `password` varchar(255) NOT NULL,
        `email` varchar(100) NOT NULL UNIQUE,
        `full_name` varchar(100) DEFAULT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `address` text DEFAULT NULL,
        `profile_picture` varchar(255) DEFAULT NULL,
        `role` enum('admin','receptionist','customer','staff') DEFAULT 'customer',
        `status` enum('active','inactive','suspended') DEFAULT 'active',
        `last_login` timestamp NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Room categories table
    $room_categories_table = "CREATE TABLE IF NOT EXISTS `room_categories` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `name` varchar(100) NOT NULL,
        `description` text,
        `base_price` decimal(10,2) NOT NULL,
        `max_capacity` int(11) DEFAULT 2,
        `amenities` text,
        `image` varchar(255) DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Rooms table
    $rooms_table = "CREATE TABLE IF NOT EXISTS `rooms` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `room_number` varchar(10) NOT NULL UNIQUE,
        `category_id` int(11) NOT NULL,
        `floor` varchar(10) DEFAULT NULL,
        `view_type` enum('city','garden','pool','mountain','sea') DEFAULT 'city',
        `bed_type` enum('single','double','queen','king','twin') DEFAULT 'double',
        `smoking` tinyint(1) DEFAULT 0,
        `description` text,
        `status` enum('available','occupied','maintenance','cleaning','reserved') DEFAULT 'available',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`category_id`) REFERENCES `room_categories`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Bookings table
    $bookings_table = "CREATE TABLE IF NOT EXISTS `bookings` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `booking_code` varchar(20) NOT NULL UNIQUE,
        `user_id` int(11) NOT NULL,
        `room_id` int(11) NOT NULL,
        `check_in` date NOT NULL,
        `check_out` date NOT NULL,
        `total_nights` int(11) NOT NULL,
        `adults` int(11) DEFAULT 1,
        `children` int(11) DEFAULT 0,
        `total_price` decimal(10,2) NOT NULL,
        `discount_amount` decimal(10,2) DEFAULT 0,
        `final_price` decimal(10,2) NOT NULL,
        `special_requests` text,
        `booking_status` enum('pending','confirmed','checked_in','checked_out','cancelled','no_show') DEFAULT 'pending',
        `payment_status` enum('pending','partial','paid','refunded','failed') DEFAULT 'pending',
        `payment_method` varchar(50) DEFAULT NULL,
        `payment_proof` varchar(255) DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
        FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Payments table
    $payments_table = "CREATE TABLE IF NOT EXISTS `payments` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `booking_id` int(11) NOT NULL,
        `payment_code` varchar(20) NOT NULL UNIQUE,
        `amount` decimal(10,2) NOT NULL,
        `payment_method` enum('cash','credit_card','debit_card','bank_transfer','e-wallet') NOT NULL,
        `payment_date` date NOT NULL,
        `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
        `transaction_id` varchar(100) DEFAULT NULL,
        `notes` text,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Services table
    $services_table = "CREATE TABLE IF NOT EXISTS `services` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `name` varchar(100) NOT NULL,
        `description` text,
        `price` decimal(10,2) NOT NULL,
        `unit` varchar(20) DEFAULT 'per service',
        `category` varchar(50) DEFAULT 'general',
        `status` enum('active','inactive') DEFAULT 'active',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Booking services table
    $booking_services_table = "CREATE TABLE IF NOT EXISTS `booking_services` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `booking_id` int(11) NOT NULL,
        `service_id` int(11) NOT NULL,
        `quantity` int(11) DEFAULT 1,
        `unit_price` decimal(10,2) NOT NULL,
        `total_price` decimal(10,2) NOT NULL,
        `notes` text,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Service requests table
    $service_requests_table = "CREATE TABLE IF NOT EXISTS `service_requests` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` int(11) NOT NULL,
        `booking_id` int(11) DEFAULT NULL,
        `service_type` varchar(100) NOT NULL,
        `description` text NOT NULL,
        `priority` enum('low','medium','high') DEFAULT 'medium',
        `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
        `room_number` varchar(10) DEFAULT NULL,
        `completed_by` int(11) DEFAULT NULL,
        `completed_at` timestamp NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Activity logs table
    $activity_logs_table = "CREATE TABLE IF NOT EXISTS `activity_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` int(11) DEFAULT NULL,
        `action` varchar(100) NOT NULL,
        `details` text,
        `ip_address` varchar(45) DEFAULT NULL,
        `user_agent` text,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Settings table
    $settings_table = "CREATE TABLE IF NOT EXISTS `settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `setting_key` varchar(100) NOT NULL UNIQUE,
        `setting_value` text,
        `setting_group` varchar(50) DEFAULT 'general',
        `description` text,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Execute table creations
    $tables = [
        $users_table,
        $room_categories_table,
        $rooms_table,
        $bookings_table,
        $payments_table,
        $services_table,
        $booking_services_table,
        $service_requests_table,
        $activity_logs_table,
        $settings_table
    ];
    
    foreach ($tables as $table_sql) {
        if (!$conn->query($table_sql)) {
            // Table already exists or error
        }
    }
    
    
    // Insert default settings if empty
    $check_settings = $conn->query("SELECT COUNT(*) as count FROM settings");
    if ($check_settings && $check_settings->fetch_assoc()['count'] == 0) {
        $default_settings = "INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
            ('hotel_name', 'Grand Luxury Hotel', 'general', 'Hotel name'),
            ('hotel_address', 'Jl. Sudirman No. 123, Jakarta', 'general', 'Hotel address'),
            ('hotel_phone', '(021) 1234-5678', 'general', 'Hotel phone number'),
            ('hotel_email', 'info@grandhotel.com', 'general', 'Hotel email'),
            ('checkin_time', '14:00', 'booking', 'Check-in time'),
            ('checkout_time', '12:00', 'booking', 'Check-out time'),
            ('currency', 'IDR', 'finance', 'Currency'),
            ('tax_rate', '10', 'finance', 'Tax percentage'),
            ('booking_advance_payment', '50', 'finance', 'Advance payment percentage'),
            ('cancellation_policy', 'Free cancellation up to 24 hours before check-in', 'booking', 'Cancellation policy')";
        $conn->query($default_settings);
    }
    
    // Insert demo users if empty
    $check_users = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($check_users && $check_users->fetch_assoc()['count'] == 0) {
        // Hash password '123'
        $hashed_password = password_hash('123', PASSWORD_DEFAULT);
        
        $demo_users = "INSERT INTO `users` (`username`, `password`, `email`, `full_name`, `role`, `status`) VALUES 
            ('admin', '$hashed_password', 'admin@hotel.com', 'Administrator', 'admin', 'active'),
            ('receptionist', '$hashed_password', 'receptionist@hotel.com', 'Resepsionis', 'receptionist', 'active'),
            ('customer', '$hashed_password', 'customer@hotel.com', 'John Doe', 'customer', 'active')";
        $conn->query($demo_users);
    }
    
    // Insert demo room categories if empty
    $check_categories = $conn->query("SELECT COUNT(*) as count FROM room_categories");
    if ($check_categories && $check_categories->fetch_assoc()['count'] == 0) {
        $demo_categories = "INSERT INTO `room_categories` (`name`, `description`, `base_price`, `max_capacity`, `amenities`) VALUES
            ('Standard Room', 'Comfortable room with basic amenities', 500000, 2, '[\"WiFi\", \"TV\", \"AC\", \"Private Bathroom\"]'),
            ('Deluxe Room', 'Spacious room with better view and amenities', 750000, 3, '[\"WiFi\", \"TV\", \"AC\", \"Mini Bar\", \"Private Bathroom\", \"Coffee Maker\"]'),
            ('Executive Suite', 'Luxurious suite with separate living area', 1200000, 4, '[\"WiFi\", \"TV\", \"AC\", \"Mini Bar\", \"Private Bathroom\", \"Coffee Maker\", \"Jacuzzi\", \"Balcony\"]'),
            ('Family Room', 'Large room suitable for families', 900000, 5, '[\"WiFi\", \"TV\", \"AC\", \"Mini Bar\", \"Private Bathroom\", \"Coffee Maker\", \"Extra Beds\"]')";
        $conn->query($demo_categories);
    }
    
    // Insert demo rooms if empty
    $check_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms");
    if ($check_rooms && $check_rooms->fetch_assoc()['count'] == 0) {
        $demo_rooms = "INSERT INTO `rooms` (`room_number`, `category_id`, `floor`, `view_type`, `bed_type`, `smoking`, `description`) VALUES
            ('101', 1, '1', 'city', 'double', 0, 'Standard room with city view'),
            ('102', 1, '1', 'garden', 'double', 0, 'Standard room with garden view'),
            ('201', 2, '2', 'city', 'queen', 0, 'Deluxe room with city view'),
            ('202', 2, '2', 'pool', 'queen', 0, 'Deluxe room with pool view'),
            ('301', 3, '3', 'city', 'king', 0, 'Executive suite with panoramic city view'),
            ('302', 3, '3', 'sea', 'king', 0, 'Executive suite with sea view'),
            ('401', 4, '4', 'garden', 'twin', 0, 'Family room with two double beds')";
        $conn->query($demo_rooms);
    }
    
    // Insert demo services if empty
    $check_services = $conn->query("SELECT COUNT(*) as count FROM services");
    if ($check_services && $check_services->fetch_assoc()['count'] == 0) {
        $demo_services = "INSERT INTO `services` (`name`, `description`, `price`, `unit`, `category`) VALUES
            ('Breakfast Buffet', 'International breakfast buffet', 150000, 'per person', 'food'),
            ('Airport Transfer', 'Pickup and dropoff service', 300000, 'per trip', 'transportation'),
            ('Laundry Service', 'Express laundry service', 80000, 'per kg', 'laundry'),
            ('Spa Treatment', 'Relaxing massage therapy', 350000, 'per session', 'wellness'),
            ('Room Service', '24/7 food delivery to room', 50000, 'per order', 'food')";
        $conn->query($demo_services);
    }
}

// ========== HELPER FUNCTIONS ==========

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

// Check if user is receptionist
function isReceptionist() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'receptionist';
}

// Check if user is admin or receptionist
function isAdminOrReceptionist() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'receptionist']);
}

// Check if user is customer
function isCustomer() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'customer';
}

// Check if user is staff
function isStaff() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'staff';
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

// Redirect if not receptionist
function requireReceptionist() {
    requireLogin();
    if (!isReceptionist()) {
        // Redirect ke login jika bukan receptionist
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

// Redirect if not admin or receptionist
function requireAdminOrReceptionist() {
    requireLogin();
    if (!isAdminOrReceptionist()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

// Redirect if not customer
function requireCustomer() {
    requireLogin();
    if (!isCustomer()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

// Sanitize input
function sanitize($input, $conn) {
    if (is_array($input)) {
        $sanitized = [];
        foreach ($input as $key => $value) {
            $sanitized[$key] = sanitize($value, $conn);
        }
        return $sanitized;
    }
    return htmlspecialchars(strip_tags(trim($input)));
}

// Generate booking code
function generateBookingCode() {
    return 'BOOK' . date('YmdHis') . rand(100, 999);
}

// Format currency (IDR)
function formatCurrency($amount) {
    if ($amount == 0 || $amount == null) {
        return 'Rp 0';
    }
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Calculate nights between dates
function calculateNights($check_in, $check_out) {
    $start = new DateTime($check_in);
    $end = new DateTime($check_out);
    $interval = $start->diff($end);
    return $interval->days;
}

// Get setting value
function getSetting($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    if ($stmt) {
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['setting_value'];
        }
        $stmt->close();
    }
    return '';
}

// Get user data
function getUserData($conn, $user_id) {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }
    return null;
}

// Check room availability
function checkRoomAvailability($conn, $room_id, $check_in, $check_out) {
    $sql = "SELECT COUNT(*) as count FROM bookings 
            WHERE room_id = ? 
            AND booking_status NOT IN ('cancelled', 'checked_out', 'no_show')
            AND (
                (check_in < ? AND check_out > ?) OR
                (check_in < ? AND check_out > ?) OR
                (check_in >= ? AND check_out <= ?)
            )";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("issssss", $room_id, $check_out, $check_in, $check_in, $check_out, $check_in, $check_out);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['count'] == 0;
    }
    return false;
}

// Get all room categories
function getAllRoomCategories($conn) {
    $sql = "SELECT * FROM room_categories ORDER BY base_price ASC";
    $result = $conn->query($sql);
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    return $categories;
}

// Get all rooms
function getAllRooms($conn) {
    $sql = "SELECT r.*, rc.name as category_name, rc.base_price 
            FROM rooms r 
            JOIN room_categories rc ON r.category_id = rc.id 
            ORDER BY r.room_number ASC";
    $result = $conn->query($sql);
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
    return $rooms;
}

// Get available rooms for dates
function getAvailableRooms($conn, $check_in, $check_out) {
    $sql = "SELECT r.*, rc.name as category_name, rc.base_price 
            FROM rooms r 
            JOIN room_categories rc ON r.category_id = rc.id 
            WHERE r.status = 'available' 
            AND r.id NOT IN (
                SELECT room_id FROM bookings 
                WHERE booking_status NOT IN ('cancelled', 'checked_out', 'no_show')
                AND (
                    (check_in < ? AND check_out > ?) OR
                    (check_in < ? AND check_out > ?) OR
                    (check_in >= ? AND check_out <= ?)
                )
            )
            ORDER BY r.room_number ASC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ssssss", $check_out, $check_in, $check_in, $check_out, $check_in, $check_out);
        $stmt->execute();
        $result = $stmt->get_result();
        $rooms = [];
        while ($row = $result->fetch_assoc()) {
            $rooms[] = $row;
        }
        $stmt->close();
        return $rooms;
    }
    return [];
}

// Get booking by ID
function getBookingById($conn, $booking_id) {
    $sql = "SELECT b.*, u.full_name, u.username, u.email, u.phone, 
                   r.room_number, rc.name as room_type, rc.base_price
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN rooms r ON b.room_id = r.id
            JOIN room_categories rc ON r.category_id = rc.id
            WHERE b.id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        $stmt->close();
        return $booking;
    }
    return null;
}

// Get all bookings
function getAllBookings($conn, $limit = null) {
    $sql = "SELECT b.*, u.full_name, u.username, u.email, u.phone, 
                   r.room_number, rc.name as room_type, rc.base_price
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN rooms r ON b.room_id = r.id
            JOIN room_categories rc ON r.category_id = rc.id
            ORDER BY b.created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT $limit";
    }
    
    $result = $conn->query($sql);
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    return $bookings;
}

// Update booking status
function updateBookingStatus($conn, $booking_id, $status) {
    $sql = "UPDATE bookings SET booking_status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("si", $status, $booking_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

// Update room status
function updateRoomStatus($conn, $room_id, $status) {
    $sql = "UPDATE rooms SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("si", $status, $room_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

// Get todays checkins
function getTodaysCheckins($conn) {
    $today = date('Y-m-d');
    $sql = "SELECT b.*, u.full_name, u.username, u.email, u.phone, 
                   r.room_number, rc.name as room_type
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN rooms r ON b.room_id = r.id
            JOIN room_categories rc ON r.category_id = rc.id
            WHERE DATE(b.check_in) = ? 
            AND b.booking_status = 'confirmed'
            ORDER BY b.check_in ASC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $checkins = [];
        while ($row = $result->fetch_assoc()) {
            $checkins[] = $row;
        }
        $stmt->close();
        return $checkins;
    }
    return [];
}

// Get todays checkouts
function getTodaysCheckouts($conn) {
    $today = date('Y-m-d');
    $sql = "SELECT b.*, u.full_name, u.username, u.email, u.phone, 
                   r.room_number, rc.name as room_type
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN rooms r ON b.room_id = r.id
            JOIN room_categories rc ON r.category_id = rc.id
            WHERE DATE(b.check_out) = ? 
            AND b.booking_status = 'checked_in'
            ORDER BY b.check_out ASC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $checkouts = [];
        while ($row = $result->fetch_assoc()) {
            $checkouts[] = $row;
        }
        $stmt->close();
        return $checkouts;
    }
    return [];
}

// Get active guests
function getActiveGuests($conn) {
    $sql = "SELECT b.*, u.full_name, u.username, u.email, u.phone, 
                   r.room_number, rc.name as room_type
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN rooms r ON b.room_id = r.id
            JOIN room_categories rc ON r.category_id = rc.id
            WHERE b.booking_status = 'checked_in'
            ORDER BY b.check_in DESC";
    
    $result = $conn->query($sql);
    $guests = [];
    while ($row = $result->fetch_assoc()) {
        $guests[] = $row;
    }
    return $guests;
}

// Count rooms by status
function countRoomsByStatus($conn) {
    $sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
        SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
        SUM(CASE WHEN status = 'cleaning' THEN 1 ELSE 0 END) as cleaning
        FROM rooms";
    
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

// Get receptionist dashboard stats
function getReceptionistDashboardStats($conn) {
    $today = date('Y-m-d');
    $stats = [];
    
    // Today's checkins
    $checkin_sql = "SELECT COUNT(*) as count FROM bookings 
                   WHERE DATE(check_in) = ? AND booking_status = 'confirmed'";
    $stmt = $conn->prepare($checkin_sql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['today_checkins'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Today's checkouts
    $checkout_sql = "SELECT COUNT(*) as count FROM bookings 
                    WHERE DATE(check_out) = ? AND booking_status = 'checked_in'";
    $stmt = $conn->prepare($checkout_sql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['today_checkouts'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Available rooms
    $rooms_sql = "SELECT COUNT(*) as count FROM rooms WHERE status = 'available'";
    $result = $conn->query($rooms_sql);
    $stats['available_rooms'] = $result->fetch_assoc()['count'];
    
    return $stats;
}

// Get booking services
function getBookingServices($conn, $booking_id) {
    $sql = "SELECT bs.*, s.name FROM booking_services bs
            LEFT JOIN services s ON bs.service_id = s.id
            WHERE bs.booking_id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $services = [];
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        $stmt->close();
        return $services;
    }
    return [];
}

// Get booking status badge
function getBookingStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-primary',
        'checked_in' => 'badge-success',
        'checked_out' => 'badge-secondary',
        'cancelled' => 'badge-danger',
        'no_show' => 'badge-danger'
    ];
    
    $texts = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'checked_in' => 'Checked-in',
        'checked_out' => 'Checked-out',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show'
    ];
    
    $badge_class = $badges[$status] ?? 'badge-secondary';
    $badge_text = $texts[$status] ?? ucfirst($status);
    
    return "<span class='badge $badge_class'>$badge_text</span>";
}

// Get payment status badge
function getPaymentStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'partial' => 'badge-info',
        'paid' => 'badge-success',
        'refunded' => 'badge-secondary',
        'failed' => 'badge-danger'
    ];
    
    $texts = [
        'pending' => 'Pending',
        'partial' => 'Partial',
        'paid' => 'Paid',
        'refunded' => 'Refunded',
        'failed' => 'Failed'
    ];
    
    $badge_class = $badges[$status] ?? 'badge-secondary';
    $badge_text = $texts[$status] ?? ucfirst($status);
    
    return "<span class='badge $badge_class'>$badge_text</span>";
}

// Get flash message
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $alert_class = '';
        switch ($_SESSION['flash_type']) {
            case 'success': $alert_class = 'alert-success'; break;
            case 'error': $alert_class = 'alert-danger'; break;
            case 'warning': $alert_class = 'alert-warning'; break;
            default: $alert_class = 'alert-success';
        }
        
        $message = '<div class="alert ' . $alert_class . '">
                    <i class="fas fa-info-circle"></i> ' . $_SESSION['flash_message'] . '
                   </div>';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return $message;
    }
    return '';
}

// Redirect user based on role after login
function redirectByRole() {
    if (!isset($_SESSION['role'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
    
    $role = $_SESSION['role'];
    
    if ($role == 'admin') {
        header('Location: ' . BASE_URL . 'admin/index.php');
    } elseif ($role == 'receptionist') {
        header('Location: ' . BASE_URL . 'receptionist/dashboard.php');
    } elseif ($role == 'staff') {
        header('Location: ' . BASE_URL . 'staff/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'customer/dashboard.php');
    }
    exit();
}

// ========== LOAD HOTEL SETTINGS ==========

// Get hotel settings
$hotel_name = getSetting($conn, 'hotel_name') ?: 'Grand Luxury Hotel';
$hotel_address = getSetting($conn, 'hotel_address') ?: 'Jl. Sudirman No. 123, Jakarta';
$hotel_phone = getSetting($conn, 'hotel_phone') ?: '(021) 1234-5678';
$hotel_email = getSetting($conn, 'hotel_email') ?: 'info@grandhotel.com';
$checkin_time = getSetting($conn, 'checkin_time') ?: '14:00';
$checkout_time = getSetting($conn, 'checkout_time') ?: '12:00';
$currency = getSetting($conn, 'currency') ?: 'IDR';
$tax_rate = getSetting($conn, 'tax_rate') ?: 10;

// Error handling (turn on for debugging, off for production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>