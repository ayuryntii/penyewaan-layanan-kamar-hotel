<?php
session_start();
require_once '../includes/config.php';
requireReceptionist();

if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    $_SESSION['flash_message'] = "Invalid booking ID.";
    $_SESSION['flash_type'] = "danger";
    header("Location: bookings.php");
    exit();
}

$booking_id = (int)$_GET['booking_id'];

// Mulai transaksi (opsional tapi direkomendasikan)
mysqli_autocommit($conn, false);

try {
    // 1. Update status booking menjadi 'checked_out'
    $update_booking = $conn->prepare("UPDATE bookings SET booking_status = 'checked_out' WHERE id = ? AND booking_status = 'checked_in'");
    $update_booking->bind_param("i", $booking_id);
    $update_booking->execute();
    $booking_updated = $update_booking->affected_rows;

    // 2. Update status kamar menjadi 'available'
    $update_room = $conn->prepare("UPDATE rooms r 
        JOIN bookings b ON r.id = b.room_id 
        SET r.status = 'available' 
        WHERE b.id = ?");
    $update_room->bind_param("i", $booking_id);
    $update_room->execute();

    if ($booking_updated > 0) {
        $_SESSION['flash_message'] = "Guest successfully checked out!";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Check-out failed. Guest may not be checked in or already checked out.";
        $_SESSION['flash_type'] = "warning";
    }

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['flash_message'] = "An error occurred during check-out. Please try again.";
    $_SESSION['flash_type'] = "danger";
}

header("Location: bookings.php");
exit();
?>