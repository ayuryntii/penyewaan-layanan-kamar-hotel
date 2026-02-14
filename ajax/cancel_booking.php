<?php
// /hotel/ajax/cancel_booking.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'receptionist'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking id']);
    exit;
}

// ambil booking dulu
$stmt = $conn->prepare("SELECT id, room_id, booking_status FROM bookings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

$booking = $res->fetch_assoc();
$stmt->close();

if (in_array($booking['booking_status'], ['checked_out', 'cancelled'])) {
    echo json_encode(['success' => false, 'message' => 'Booking cannot be cancelled']);
    exit;
}

$conn->begin_transaction();

try {
    // set booking cancelled
    $up = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $up->bind_param("i", $id);

    if (!$up->execute()) {
        throw new Exception("Failed to cancel booking");
    }
    $up->close();

    // set room available lagi
    $room_id = intval($booking['room_id']);
    $ru = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
    $ru->bind_param("i", $room_id);

    if (!$ru->execute()) {
        throw new Exception("Failed to update room status");
    }
    $ru->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
