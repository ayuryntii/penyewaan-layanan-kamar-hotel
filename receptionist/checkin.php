<?php
session_start();
require_once '../includes/config.php';
requireReceptionist();

$user_id = $_SESSION['user_id'];
$page_title = 'Guest Check-in';
$hotel_name = $config['hotel_name'] ?? 'Hotel System';

// FUNGSI formatCurrency() sudah ada di config.php, jadi HAPUS deklarasi ini:
// function formatCurrency($amount) {
//     return 'Rp ' . number_format($amount, 0, ',', '.');
// }

// Fungsi sanitize (jika belum ada di config.php)
if (!function_exists('sanitize')) {
    function sanitize($data, $conn) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

// Get receptionist data
$receptionist_sql = "SELECT * FROM users WHERE id = ?";
$receptionist_stmt = $conn->prepare($receptionist_sql);
$receptionist_stmt->bind_param("i", $user_id);
$receptionist_stmt->execute();
$receptionist_result = $receptionist_stmt->get_result();
$receptionist = $receptionist_result->fetch_assoc();
$receptionist_stmt->close();

// Initialize variables
$available_rooms = [];
$booking = null;
$guests = [];

// Get today's scheduled check-ins (bookings with status 'confirmed' for today)
$today = date('Y-m-d');
$todays_checkins_sql = "SELECT 
    b.*, 
    u.full_name, 
    u.username, 
    u.email, 
    u.phone,
    r.room_number,
    rc.name as room_type
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN rooms r ON b.room_id = r.id
JOIN room_categories rc ON r.category_id = rc.id
WHERE DATE(b.check_in) = ?
AND b.booking_status = 'confirmed'
ORDER BY b.check_in ASC";

$checkin_stmt = $conn->prepare($todays_checkins_sql);
$checkin_stmt->bind_param("s", $today);
$checkin_stmt->execute();
$checkin_result = $checkin_stmt->get_result();
$todays_checkins = [];
while ($row = $checkin_result->fetch_assoc()) {
    $todays_checkins[] = $row;
}
$checkin_stmt->close();

// Get active check-ins (already checked in today)
$active_checkins_sql = "SELECT 
    b.*, 
    u.full_name, 
    u.username, 
    u.email, 
    u.phone,
    r.room_number,
    rc.name as room_type
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN rooms r ON b.room_id = r.id
JOIN room_categories rc ON r.category_id = rc.id
WHERE b.booking_status = 'checked_in'
AND DATE(b.updated_at) = CURDATE()
ORDER BY b.updated_at DESC
LIMIT 10";

$active_stmt = $conn->prepare($active_checkins_sql);
$active_stmt->execute();
$active_result = $active_stmt->get_result();
$active_checkins = [];
while ($row = $active_result->fetch_assoc()) {
    $active_checkins[] = $row;
}
$active_stmt->close();

// Handle booking_id parameter
if (isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    
    // Get booking details
    $booking_sql = "SELECT b.*, u.full_name, u.username, u.email, u.phone, 
                           r.room_number, rc.name as room_type
                    FROM bookings b
                    JOIN users u ON b.user_id = u.id
                    JOIN rooms r ON b.room_id = r.id
                    JOIN room_categories rc ON r.category_id = rc.id
                    WHERE b.id = ? AND b.booking_status = 'confirmed'";
    
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("i", $booking_id);
    $booking_stmt->execute();
    $booking_result = $booking_stmt->get_result();
    $booking = $booking_result->fetch_assoc();
    $booking_stmt->close();
    
    if ($booking) {
        // HAPUS query ke booking_guests karena tabel tidak ada
        // Get available rooms for upgrade/downgrade
        // First, get current room's category_id
        $current_room_sql = "SELECT category_id FROM rooms WHERE id = ?";
        $current_room_stmt = $conn->prepare($current_room_sql);
        $current_room_stmt->bind_param("i", $booking['room_id']);
        $current_room_stmt->execute();
        $current_room_result = $current_room_stmt->get_result();
        $current_room = $current_room_result->fetch_assoc();
        $current_room_stmt->close();
        
        $room_category_id = $current_room['category_id'] ?? null;
        
        if ($room_category_id) {
            $available_rooms_sql = "SELECT r.*, rc.name as room_type 
                                   FROM rooms r
                                   JOIN room_categories rc ON r.category_id = rc.id
                                   WHERE r.status = 'available' 
                                   AND rc.id != ?
                                   ORDER BY rc.name ASC";
            $rooms_stmt = $conn->prepare($available_rooms_sql);
            $rooms_stmt->bind_param("i", $room_category_id);
            $rooms_stmt->execute();
            $rooms_result = $rooms_stmt->get_result();
            while ($row = $rooms_result->fetch_assoc()) {
                $available_rooms[] = $row;
            }
            $rooms_stmt->close();
        }
    }
}

// Handle check-in process
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['checkin'])) {
        $booking_id = intval($_POST['booking_id']);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get current booking
            $current_sql = "SELECT b.*, r.id as room_id, r.room_number 
                           FROM bookings b
                           JOIN rooms r ON b.room_id = r.id
                           WHERE b.id = ?";
            $current_stmt = $conn->prepare($current_sql);
            $current_stmt->bind_param("i", $booking_id);
            $current_stmt->execute();
            $current_result = $current_stmt->get_result();
            $current_booking = $current_result->fetch_assoc();
            $current_stmt->close();
            
            if (!$current_booking) {
                throw new Exception("Booking not found!");
            }
            
            // Handle room change if requested
            $new_room_id = $current_booking['room_id'];
            if (!empty($_POST['change_room']) && !empty($_POST['new_room_id'])) {
                $new_room_id = intval($_POST['new_room_id']);
                
                // Check if new room is available
                $room_check_sql = "SELECT status FROM rooms WHERE id = ?";
                $room_check_stmt = $conn->prepare($room_check_sql);
                $room_check_stmt->bind_param("i", $new_room_id);
                $room_check_stmt->execute();
                $room_check_result = $room_check_stmt->get_result();
                $room_status = $room_check_result->fetch_assoc();
                $room_check_stmt->close();
                
                if ($room_status['status'] !== 'available') {
                    throw new Exception("Selected room is not available!");
                }
                
                // Update old room status to available
                $update_old_room = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
                $update_old_room->bind_param("i", $current_booking['room_id']);
                $update_old_room->execute();
                $update_old_room->close();
                
                // Update new room status to occupied
                $update_new_room = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                $update_new_room->bind_param("i", $new_room_id);
                $update_new_room->execute();
                $update_new_room->close();
                
                // Update booking with new room
                $update_booking_room = $conn->prepare("UPDATE bookings SET room_id = ? WHERE id = ?");
                $update_booking_room->bind_param("ii", $new_room_id, $booking_id);
                $update_booking_room->execute();
                $update_booking_room->close();
            } else {
                // Update current room status to occupied
                $update_room = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                $update_room->bind_param("i", $current_booking['room_id']);
                $update_room->execute();
                $update_room->close();
            }
            
            // Update booking status to checked_in
            $update_booking = $conn->prepare("UPDATE bookings SET booking_status = 'checked_in', updated_at = NOW() WHERE id = ?");
            $update_booking->bind_param("i", $booking_id);
            $update_booking->execute();
            $update_booking->close();
            
            // Log the check-in
            $log_sql = "INSERT INTO activity_logs (user_id, action, details, created_at) 
                       VALUES (?, 'checkin', ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $room_number_sql = "SELECT room_number FROM rooms WHERE id = ?";
            $room_number_stmt = $conn->prepare($room_number_sql);
            $room_number_stmt->bind_param("i", $new_room_id);
            $room_number_stmt->execute();
            $room_number_result = $room_number_stmt->get_result();
            $room_data = $room_number_result->fetch_assoc();
            $room_number_stmt->close();
            
            $details = "Checked in booking #" . $current_booking['booking_code'] . " to room " . $room_data['room_number'];
            $log_stmt->bind_param("is", $user_id, $details);
            $log_stmt->execute();
            $log_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['flash_message'] = 'Guest checked in successfully!';
            $_SESSION['flash_type'] = 'success';
            
            header("Location: checkin.php?booking_id=" . $booking_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = 'Check-in failed: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
            header("Location: checkin.php");
            exit();
        }
    }
    
    // Handle confirm booking request (like admin does)
    if (isset($_POST['confirm_booking'])) {
        $booking_id = intval($_POST['booking_id']);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get booking details
            $booking_sql = "SELECT b.*, r.room_number FROM bookings b
                           JOIN rooms r ON b.room_id = r.id
                           WHERE b.id = ?";
            $booking_stmt = $conn->prepare($booking_sql);
            $booking_stmt->bind_param("i", $booking_id);
            $booking_stmt->execute();
            $booking_result = $booking_stmt->get_result();
            $booking_data = $booking_result->fetch_assoc();
            $booking_stmt->close();
            
            // Update booking status to confirmed
            $update_sql = "UPDATE bookings SET booking_status = 'confirmed', updated_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $booking_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Update room status to reserved
            $update_room_sql = "UPDATE rooms SET status = 'reserved' WHERE id = ?";
            $update_room_stmt = $conn->prepare($update_room_sql);
            $update_room_stmt->bind_param("i", $booking_data['room_id']);
            $update_room_stmt->execute();
            $update_room_stmt->close();
            
            // Log the confirmation
            $log_sql = "INSERT INTO activity_logs (user_id, action, details, created_at) 
                       VALUES (?, 'confirm_booking', ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $details = "Confirmed booking #" . $booking_data['booking_code'] . " for room " . $booking_data['room_number'];
            $log_stmt->bind_param("is", $user_id, $details);
            $log_stmt->execute();
            $log_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['flash_message'] = 'Booking confirmed successfully! Guest can now check-in.';
            $_SESSION['flash_type'] = 'success';
            
            header("Location: checkin.php");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = 'Booking confirmation failed: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
            header("Location: checkin.php");
            exit();
        }
    }
}

// Handle search
$search_results = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = "%" . sanitize($_GET['search'], $conn) . "%";
    
    $search_sql = "SELECT b.*, u.full_name, u.username, u.email, u.phone, 
                          r.room_number, rc.name as room_type
                  FROM bookings b
                  JOIN users u ON b.user_id = u.id
                  JOIN rooms r ON b.room_id = r.id
                  JOIN room_categories rc ON r.category_id = rc.id
                  WHERE (b.booking_code LIKE ? 
                         OR u.full_name LIKE ? 
                         OR u.username LIKE ? 
                         OR u.email LIKE ? 
                         OR r.room_number LIKE ?)
                  AND b.booking_status IN ('pending', 'confirmed')
                  AND DATE(b.check_in) >= CURDATE()
                  ORDER BY b.check_in ASC
                  LIMIT 20";
    
    $search_stmt = $conn->prepare($search_sql);
    if ($search_stmt) {
        $search_stmt->bind_param("sssss", $search_term, $search_term, $search_term, $search_term, $search_term);
        $search_stmt->execute();
        $search_result = $search_stmt->get_result();
        while ($row = $search_result->fetch_assoc()) {
            $search_results[] = $row;
        }
        $search_stmt->close();
    }
}

// Get pending bookings that need confirmation
$pending_bookings_sql = "SELECT 
    b.*, 
    u.full_name, 
    u.username, 
    u.email, 
    u.phone,
    r.room_number,
    rc.name as room_type
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN rooms r ON b.room_id = r.id
JOIN room_categories rc ON r.category_id = rc.id
WHERE b.booking_status = 'pending'
AND DATE(b.check_in) >= CURDATE()
ORDER BY b.created_at DESC
LIMIT 5";

$pending_stmt = $conn->prepare($pending_bookings_sql);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_bookings = [];
while ($row = $pending_result->fetch_assoc()) {
    $pending_bookings[] = $row;
}
$pending_stmt->close();

// JANGAN tutup koneksi di sini, biarkan terbuka untuk HTML
// $conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($hotel_name) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --navy: #0a192f;
    --blue: #4cc9f0;
    --blue-dark: #3a86ff;
    --light: #f8f9fa;
    --gray: #6c757d;
    --dark-bg: #0a192f;
    --card-bg: rgba(20, 30, 50, 0.85);
    --sidebar-width: 260px;
    --green: #28a745;
    --yellow: #ffc107;
    --red: #dc3545;
    --purple: #6f42c1;
    --orange: #fd7e14;
    --teal: #20c997;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    background: var(--dark-bg);
    color: var(--light);
    overflow-x: hidden;
}

.receptionist-wrapper {
    display: flex;
    min-height: 100vh;
}

/* === Sidebar === */
.sidebar {
    width: var(--sidebar-width);
    background: var(--navy);
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    z-index: 100;
    transition: all 0.3s ease;
    border-right: 1px solid rgba(76, 201, 240, 0.1);
}

.sidebar-header {
    padding: 25px 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-logo {
    width: 40px;
    height: 40px;
    background: var(--blue);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--navy);
    font-size: 18px;
}

.sidebar-title h3 {
    font-size: 1.2rem;
    font-weight: 600;
}

.sidebar-title p {
    font-size: 0.85rem;
    color: #aaa;
}

.sidebar-nav {
    padding: 20px 0;
    overflow-y: auto;
    height: calc(100vh - 180px);
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 12px 25px;
    color: #ccc;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
    position: relative;
}

.nav-item:hover,
.nav-item.active {
    background: rgba(76, 201, 240, 0.1);
    color: var(--blue);
}

.nav-item i {
    margin-right: 15px;
    width: 20px;
    text-align: center;
}

.nav-label {
    padding: 15px 25px 8px;
    color: #777;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.nav-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.05);
    margin: 15px 0;
}

.sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.user-menu {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: var(--blue);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: var(--navy);
}

.user-info .user-name {
    font-weight: 600;
    font-size: 0.95rem;
}

.user-info .user-role {
    font-size: 0.8rem;
    color: #aaa;
}

/* === Main Content === */
.main-content {
    flex: 1;
    margin-left: var(--sidebar-width);
    transition: all 0.3s ease;
}

.top-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    background: rgba(10, 25, 47, 0.95);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(76, 201, 240, 0.1);
}

.menu-toggle {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    display: none;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 25px;
}

.content-area {
    padding: 30px;
}

/* === Cards === */
.card {
    background: var(--card-bg);
    border-radius: 16px;
    margin-bottom: 25px;
    border: 1px solid rgba(76, 201, 240, 0.1);
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.card-title {
    font-size: 1.3rem;
    color: white;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-actions {
    display: flex;
    gap: 10px;
}

.card-body {
    padding: 25px;
}

/* === Badges === */
.badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-success {
    background: rgba(40, 167, 69, 0.2);
    color: #28a745;
    border: 1px solid rgba(40, 167, 69, 0.3);
}

.badge-warning {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.badge-danger {
    background: rgba(220, 53, 69, 0.2);
    color: #dc3545;
    border: 1px solid rgba(220, 53, 69, 0.3);
}

.badge-primary {
    background: rgba(76, 201, 240, 0.2);
    color: var(--blue);
    border: 1px solid rgba(76, 201, 240, 0.3);
}

.badge-secondary {
    background: rgba(108, 117, 125, 0.2);
    color: #6c757d;
    border: 1px solid rgba(108, 117, 125, 0.3);
}

.badge-teal {
    background: rgba(32, 201, 151, 0.2);
    color: var(--teal);
    border: 1px solid rgba(32, 201, 151, 0.3);
}

/* === Buttons === */
.btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.btn-primary {
    background: var(--blue);
    color: var(--navy);
}

.btn-success {
    background: var(--green);
    color: white;
}

.btn-warning {
    background: var(--yellow);
    color: var(--navy);
}

.btn-danger {
    background: var(--red);
    color: white;
}

.btn-secondary {
    background: var(--gray);
    color: white;
}

.btn-teal {
    background: var(--teal);
    color: white;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* === Time Display === */
.time-display {
    font-size: 1.5rem;
    color: var(--blue);
    font-weight: 600;
    margin-bottom: 5px;
}

.date-display {
    color: #aaa;
    font-size: 0.9rem;
}

/* === Logout Button === */
.logout-btn {
    background: rgba(231, 76, 60, 0.2);
    border: 1px solid rgba(231, 76, 60, 0.3);
    color: #e74c3c;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.logout-btn:hover {
    background: rgba(231, 76, 60, 0.3);
    transform: translateY(-1px);
}

/* === Empty State === */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #aaa;
}

.empty-state i {
    font-size: 36px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* === Alert Messages === */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    font-size: 0.95rem;
    border: 1px solid transparent;
}

.alert-success {
    background: rgba(40, 167, 69, 0.2);
    border-color: rgba(40, 167, 69, 0.3);
    color: #28a745;
}

.alert-error,
.alert-danger {
    background: rgba(220, 53, 69, 0.2);
    border-color: rgba(220, 53, 69, 0.3);
    color: #dc3545;
}

.alert-warning {
    background: rgba(255, 193, 7, 0.2);
    border-color: rgba(255, 193, 7, 0.3);
    color: #ffc107;
}

/* === Custom Styles for Check-in Page === */
.search-box {
    background: rgba(76, 201, 240, 0.1);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    border: 1px solid rgba(76, 201, 240, 0.2);
}

.search-form {
    display: flex;
    gap: 15px;
    align-items: end;
}

.form-group {
    flex: 1;
}

.form-label {
    display: block;
    color: #ccc;
    font-size: 14px;
    margin-bottom: 8px;
    font-weight: 500;
}

.input-group {
    display: flex;
    gap: 10px;
}

.input-group input {
    flex: 1;
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.2);
    color: white;
}

.input-group input:focus {
    outline: none;
    border-color: var(--blue);
}

.no-data {
    text-align: center;
    padding: 40px 20px;
    color: #aaa;
}

.no-data i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.booking-card {
    background: rgba(255, 255, 255, 0.03);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s;
}

.booking-card:hover {
    border-color: var(--blue);
    background: rgba(76, 201, 240, 0.05);
}

.booking-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 20px;
}

.guest-info {
    flex: 1;
}

.guest-name {
    font-size: 1.3rem;
    color: white;
    margin-bottom: 5px;
}

.guest-details {
    color: #aaa;
    font-size: 0.9rem;
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.room-info {
    text-align: right;
}

.room-type {
    font-size: 1.1rem;
    color: var(--blue);
    font-weight: 600;
}

.room-number {
    color: #aaa;
    font-size: 0.9rem;
}

.booking-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.detail-item {
    background: rgba(0, 0, 0, 0.2);
    padding: 12px 15px;
    border-radius: 8px;
}

.detail-label {
    color: #aaa;
    font-size: 0.8rem;
    margin-bottom: 5px;
}

.detail-value {
    color: white;
    font-weight: 500;
}

.booking-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.search-results {
    margin-top: 30px;
}

.search-results h3 {
    color: white;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.today-badge {
    background: var(--green);
    color: white;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 10px;
}

.active-checkin-badge {
    background: var(--orange);
    color: white;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 10px;
}

.pending-badge {
    background: var(--yellow);
    color: var(--navy);
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 10px;
}

/* === Active Check-ins Table === */
.table-container {
    overflow-x: auto;
    margin-top: 20px;
}

.active-checkins-table {
    width: 100%;
    border-collapse: collapse;
}

.active-checkins-table th {
    background: rgba(76, 201, 240, 0.1);
    padding: 15px;
    text-align: left;
    color: var(--blue);
    font-weight: 600;
    border-bottom: 1px solid rgba(76, 201, 240, 0.2);
}

.active-checkins-table td {
    padding: 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.active-checkins-table tr:hover {
    background: rgba(76, 201, 240, 0.05);
}

.status-checked-in {
    background: rgba(32, 201, 151, 0.1);
    color: var(--teal);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-confirmed {
    background: rgba(255, 193, 7, 0.1);
    color: var(--yellow);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-pending {
    background: rgba(108, 117, 125, 0.1);
    color: var(--gray);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* === Responsive === */
@media (max-width: 992px) {
    .sidebar {
        transform: translateX(-100%);
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .main-content {
        margin-left: 0;
    }

    .menu-toggle {
        display: block;
    }
}

@media (max-width: 768px) {
    .search-form {
        flex-direction: column;
    }

    .booking-header {
        flex-direction: column;
        gap: 15px;
    }

    .room-info {
        text-align: left;
        align-self: flex-start;
    }

    .booking-details {
        grid-template-columns: 1fr;
    }

    .booking-actions {
        flex-direction: column;
    }

    .booking-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
</head>
<body>

<div class="receptionist-wrapper">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-concierge-bell"></i>
            </div>
            <div class="sidebar-title">
                <h3><?= htmlspecialchars($hotel_name) ?></h3>
                <p>Receptionist Portal</p>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <div class="nav-divider"></div>
            <div class="nav-group">
                <p class="nav-label">BOOKINGS</p>
                <a href="checkin.php" class="nav-item active">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Check-in</span>
                </a>
                <a href="checkout.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'checkout.php') ? 'active' : '' ?>">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Check-out</span>
                </a>
                <a href="bookings.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'bookings.php') ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Manage Bookings</span>
                </a>
                <a href="new-booking.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'new-booking.php') ? 'active' : '' ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>New Booking</span>
                </a>
            </div>
            <div class="nav-divider"></div>
            <div class="nav-group">
                <p class="nav-label">GUESTS</p>
                <a href="guests.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'guests.php') ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    <span>Guests List</span>
                </a>
                <a href="active-guests.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'active-guests.php') ? 'active' : '' ?>">
                    <i class="fas fa-user-check"></i>
                    <span>Active Guests</span>
                </a>
            </div>
            <div class="nav-divider"></div>
            <div class="nav-group">
                <p class="nav-label">ROOMS</p>
                <a href="rooms.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'rooms.php') ? 'active' : '' ?>">
                    <i class="fas fa-bed"></i>
                    <span>Room Status</span>
                </a>
                <a href="room-management.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'room-management.php') ? 'active' : '' ?>">
                    <i class="fas fa-sliders-h"></i>
                    <span>Room Management</span>
                </a>
            </div>
            <div class="nav-divider"></div>
            <div class="nav-group">
                <p class="nav-label">SERVICES</p>
                <a href="service-requests.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'service-requests.php') ? 'active' : '' ?>">
                    <i class="fas fa-bell"></i>
                    <span>Service Requests</span>
                </a>
                <a href="services.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'services.php') ? 'active' : '' ?>">
                    <i class="fas fa-concierge-bell"></i>
                    <span>Hotel Services</span>
                </a>
            </div>
            <div class="nav-divider"></div>
            <div class="nav-group">
                <p class="nav-label">REPORTS</p>
                <a href="daily-report.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'daily-report.php') ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Daily Report</span>
                </a>
                <a href="reports.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'reports.php') ? 'active' : '' ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <div class="user-menu">
                <div class="user-avatar">
                    <?= strtoupper(substr($receptionist['full_name'] ?? $receptionist['username'], 0, 1)) ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($receptionist['full_name'] ?? $receptionist['username']) ?></div>
                    <div class="user-role"><?= ucfirst($receptionist['role']) ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
<main class="main-content">
    <header class="top-header">
        <div class="header-left">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1><?= htmlspecialchars($page_title) ?></h1>
        </div>
        <div class="header-right">
            <div style="text-align: right;">
                <div class="time-display" id="currentTime"><?= date('H:i:s') ?></div>
                <div class="date-display"><?= date('l, d F Y') ?></div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <div class="content-area">
        <?php
        if (isset($_SESSION['flash_message'])) {
            $alert_class = '';
            switch ($_SESSION['flash_type']) {
                case 'success':
                    $alert_class = 'alert-success';
                    break;
                case 'error':
                    $alert_class = 'alert-error';
                    break;
                case 'warning':
                    $alert_class = 'alert-warning';
                    break;
                default:
                    $alert_class = 'alert-success';
            }
            echo '<div class="alert ' . $alert_class . '">
                    <i class="fas fa-info-circle"></i> ' . htmlspecialchars($_SESSION['flash_message']) . '
                  </div>';
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
        }
        ?>

        <!-- Search Section -->
        <div class="search-box">
            <h3 style="color: white; margin-bottom: 20px;">
                <i class="fas fa-search"></i> Search Booking for Check-in
            </h3>
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label class="form-label">Search by Booking Code, Guest Name, Email, or Room Number</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Enter booking code, guest name, email, or room number..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 20px;">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Pending Bookings (Need Confirmation) -->
        <?php if (!empty($pending_bookings)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clock"></i>
                        Pending Bookings - Need Confirmation
                        <span class="pending-badge"><?= count($pending_bookings) ?> bookings</span>
                    </h3>
                </div>
                <div class="card-body">
                    <?php foreach ($pending_bookings as $pending): ?>
                        <div class="booking-card">
                            <div class="booking-header">
                                <div class="guest-info">
                                    <div class="guest-name"><?= htmlspecialchars($pending['full_name'] ?? $pending['username']) ?></div>
                                    <div class="guest-details">
                                        <span><i class="fas fa-tag"></i> <?= htmlspecialchars($pending['booking_code']) ?></span>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($pending['phone'] ?? 'N/A') ?></span>
                                        <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($pending['email']) ?></span>
                                    </div>
                                </div>
                                <div class="room-info">
                                    <div class="room-type"><?= htmlspecialchars($pending['room_type']) ?></div>
                                    <div class="room-number">Room <?= htmlspecialchars($pending['room_number']) ?></div>
                                </div>
                            </div>

                            <div class="booking-details">
                                <div class="detail-item">
                                    <div class="detail-label">Check-in Date</div>
                                    <div class="detail-value">
                                        <?= date('d M Y', strtotime($pending['check_in'])) ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Check-out Date</div>
                                    <div class="detail-value">
                                        <?= date('d M Y', strtotime($pending['check_out'])) ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Nights</div>
                                    <div class="detail-value">
                                        <?= $pending['total_nights'] ?> nights
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Total Amount</div>
                                    <div class="detail-value">
                                        <?= formatCurrency($pending['final_price']) ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Booking Status</div>
                                    <div class="detail-value">
                                        <span class="status-pending">Pending</span>
                                    </div>
                                </div>
                            </div>

                            <div class="booking-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="booking_id" value="<?= $pending['id'] ?>">
                                    <button type="submit" name="confirm_booking" class="btn btn-success" onclick="return confirm('Confirm booking for <?= addslashes(htmlspecialchars($pending['full_name'] ?? $pending['username'])) ?>?')">
                                        <i class="fas fa-check-circle"></i> Confirm Booking
                                    </button>
                                </form>
                                <a href="booking-details.php?id=<?= $pending['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Today's Scheduled Check-ins -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-day"></i>
                    Today's Scheduled Check-ins
                    <span class="today-badge"><?= count($todays_checkins) ?> guests</span>
                </h3>
                <div class="card-actions">
                    <span style="color: #aaa; font-size: 0.9rem;">
                        <i class="fas fa-calendar-alt"></i> <?= date('l, d F Y') ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($todays_checkins)): ?>
                    <div class="no-data">
                        <i class="fas fa-user-clock fa-3x"></i>
                        <h3 style="color: #aaa; margin: 15px 0 10px 0;">No Check-ins Today</h3>
                        <p style="color: #777; margin-bottom: 20px;">
                            No guests are scheduled to check-in today.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($todays_checkins as $checkin): ?>
                        <div class="booking-card">
                            <div class="booking-header">
                                <div class="guest-info">
                                    <div class="guest-name"><?= htmlspecialchars($checkin['full_name'] ?? $checkin['username']) ?></div>
                                    <div class="guest-details">
                                        <span><i class="fas fa-tag"></i> <?= htmlspecialchars($checkin['booking_code']) ?></span>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($checkin['phone'] ?? 'N/A') ?></span>
                                        <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($checkin['email']) ?></span>
                                    </div>
                                </div>
                                <div class="room-info">
                                    <div class="room-type"><?= htmlspecialchars($checkin['room_type']) ?></div>
                                    <div class="room-number">Room <?= htmlspecialchars($checkin['room_number']) ?></div>
                                </div>
                            </div>

                            <div class="booking-details">
                                <div class="detail-item">
                                    <div class="detail-label">Check-in Time</div>
                                    <div class="detail-value">
                                        <?= date('H:i', strtotime($checkin['check_in'])) ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Nights</div>
                                    <div class="detail-value">
                                        <?= $checkin['total_nights'] ?> nights
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Total Amount</div>
                                    <div class="detail-value">
                                        <?= formatCurrency($checkin['final_price']) ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Booking Status</div>
                                    <div class="detail-value">
                                        <span class="status-confirmed">Confirmed</span>
                                    </div>
                                </div>
                            </div>

                            <div class="booking-actions">
                                <a href="checkin.php?booking_id=<?= $checkin['id'] ?>" class="btn btn-teal">
                                    <i class="fas fa-sign-in-alt"></i> Process Check-in
                                </a>
                                <a href="?quick_checkin=<?= $checkin['id'] ?>" class="btn btn-warning" onclick="return confirm('Quick check-in <?= addslashes(htmlspecialchars($checkin['full_name'] ?? $checkin['username'])) ?>?')">
                                    <i class="fas fa-bolt"></i> Quick Check-in
                                </a>
                                <a href="booking-details.php?id=<?= $checkin['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active Check-ins Today -->
        <?php if (!empty($active_checkins)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user-check"></i>
                        Today's Completed Check-ins
                        <span class="active-checkin-badge"><?= count($active_checkins) ?> guests</span>
                    </h3>
                    <div class="card-actions">
                        <span style="color: #aaa; font-size: 0.9rem;">
                            <i class="fas fa-clock"></i> Last updated: <?= date('H:i') ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Table View for Active Check-ins -->
                    <div class="table-container">
                        <table class="active-checkins-table">
                            <thead>
                                <tr>
                                    <th>Guest Name</th>
                                    <th>Room</th>
                                    <th>Booking Code</th>
                                    <th>Check-in Time</th>
                                    <th>Nights</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_checkins as $checkin): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: white;"><?= htmlspecialchars($checkin['full_name'] ?? $checkin['username']) ?></div>
                                            <div style="font-size: 0.85rem; color: #aaa; margin-top: 5px;">
                                                <i class="fas fa-phone"></i> <?= htmlspecialchars($checkin['phone'] ?? 'N/A') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--blue);"><?= htmlspecialchars($checkin['room_number']) ?></div>
                                            <div style="font-size: 0.85rem; color: #aaa; margin-top: 5px;">
                                                <?= htmlspecialchars($checkin['room_type']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500; color: white;"><?= htmlspecialchars($checkin['booking_code']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500; color: white;"><?= date('H:i', strtotime($checkin['updated_at'])) ?></div>
                                            <div style="font-size: 0.85rem; color: #aaa; margin-top: 5px;">
                                                <?= date('d M Y', strtotime($checkin['updated_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500; color: white;"><?= $checkin['total_nights'] ?> nights</div>
                                        </td>
                                        <td>
                                            <span class="status-checked-in">Checked In</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>
</div>

<script>
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });

    function updateTime() {
        const now = new Date();
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('id-ID', {
            hour12: false
        });
    }
    setInterval(updateTime, 1000);
    updateTime();

    setTimeout(function() {
        location.reload();
    }, 30000);

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput && !searchInput.value) {
            searchInput.focus();
        }
    });

    // Handle quick check-in
    <?php if (isset($_GET['quick_checkin'])): ?>
        if (confirm('Quick check-in this guest?')) {
            const bookingId = <?= $_GET['quick_checkin'] ?>;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'checkin.php';
            
            const bookingIdInput = document.createElement('input');
            bookingIdInput.type = 'hidden';
            bookingIdInput.name = 'booking_id';
            bookingIdInput.value = bookingId;
            
            const checkinInput = document.createElement('input');
            checkinInput.type = 'hidden';
            checkinInput.name = 'checkin';
            checkinInput.value = '1';
            
            form.appendChild(bookingIdInput);
            form.appendChild(checkinInput);
            document.body.appendChild(form);
            form.submit();
        } else {
            window.location.href = 'checkin.php';
        }
    <?php endif; ?>
</script>

</body>
</html>
<?php
// Tutup koneksi setelah semua HTML selesai
if (isset($conn)) {
    $conn->close();
}
?>