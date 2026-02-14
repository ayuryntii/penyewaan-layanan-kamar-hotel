<?php
session_start();
require_once '../includes/config.php';
requireReceptionist();

if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    $_SESSION['flash_message'] = "Invalid booking ID.";
    $_SESSION['flash_type'] = "danger";
    header("Location: checkin.php");
    exit();
}

$booking_id = (int)$_GET['booking_id'];

// Ambil data booking
$booking_sql = "SELECT * FROM bookings WHERE id = ?";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("i", $booking_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();
$booking = $booking_result->fetch_assoc();

if (!$booking) {
    $_SESSION['flash_message'] = "Booking not found.";
    $_SESSION['flash_type'] = "danger";
    header("Location: checkin.php");
    exit();
}

// Hanya bisa check-in jika status 'confirmed'
if ($booking['booking_status'] !== 'confirmed') {
    $_SESSION['flash_message'] = "Booking is not in confirmed state.";
    $_SESSION['flash_type'] = "warning";
    header("Location: checkin.php");
    exit();
}

// Update status booking ke 'checked_in'
$update_booking = $conn->prepare("UPDATE bookings SET booking_status = 'checked_in' WHERE id = ? AND booking_status = 'confirmed'");
$update_booking->bind_param("i", $booking_id);
$update_booking->execute();

if ($update_booking->affected_rows > 0) {
    // Update status kamar jadi 'occupied'
    $update_room = $conn->prepare("UPDATE rooms r 
        JOIN bookings b ON r.id = b.room_id 
        SET r.status = 'occupied' 
        WHERE b.id = ?");
    $update_room->bind_param("i", $booking_id);
    $update_room->execute();

    $_SESSION['flash_message'] = "Guest checked in successfully!";
    $_SESSION['flash_type'] = "success";

    // Log aktivitas
    $log_sql = "INSERT INTO activity_logs (user_id, action, details, created_at) 
               VALUES (?, 'checkin', ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    if ($log_stmt) {
        $details = "Checked in booking #" . $booking['booking_code'] . " for room " . $booking['room_number'];
        $log_stmt->bind_param("is", $_SESSION['user_id'], $details);
        $log_stmt->execute();
        $log_stmt->close();
    }
} else {
    $_SESSION['flash_message'] = "Check-in failed. Booking may already be processed.";
    $_SESSION['flash_type'] = "danger";
}

header("Location: checkin.php");
exit();
?>