<?php
// includes/config.php - FINAL VERSION

// Start session safely once
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
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Koneksi database gagal: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'receptionist']);
}

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

// Load settings
$hotel_name = getSetting($conn, 'hotel_name') ?: 'Grand Luxury Hotel';
$hotel_address = getSetting($conn, 'hotel_address') ?: 'Jl. Sudirman No. 123, Jakarta';
$hotel_phone = getSetting($conn, 'hotel_phone') ?: '(021) 1234-5678';
$hotel_email = getSetting($conn, 'hotel_email') ?: 'info@grandhotel.com';

// Error handling (production-safe)
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);