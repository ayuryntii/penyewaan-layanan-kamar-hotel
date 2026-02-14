<?php
// /hotel/ajax/update_booking_status.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// hanya admin / receptionist
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'receptionist'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');

$allowed = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'];
if ($id <= 0 || !in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking id or status']);
    exit;
}

// ambil booking dulu
$sql = "SELECT id, room_id, booking_status FROM bookings WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

$booking = $res->fetch_assoc();
$stmt->close();

$room_id = intval($booking['room_id']);
$current_status = $booking['booking_status'];

// aturan status (biar tidak loncat ngawur)
$valid_transition = [
    'pending'    => ['confirmed', 'cancelled'],
    'confirmed'  => ['checked_in', 'cancelled'],
    'checked_in' => ['checked_out'],
    'checked_out'=> [],
    'cancelled'  => []
];

if (!in_array($status, $valid_transition[$current_status] ?? [])) {
    echo json_encode([
        'success' => false,
        'message' => "Cannot change status from '$current_status' to '$status'"
    ]);
    exit;
}

// mulai transaksi biar aman
$conn->begin_transaction();

try {
    // update booking status
    $update = $conn->prepare("UPDATE bookings SET booking_status = ?, updated_at = NOW() WHERE id = ?");
    $update->bind_param("si", $status, $id);

    if (!$update->execute()) {
        throw new Exception("Failed update booking: " . $update->error);
    }
    $update->close();

    // update status kamar sesuai booking
    // confirmed => reserved
    // checked_in => occupied
    // checked_out => available
    // cancelled => available
    $room_status = null;

    if ($status === 'confirmed') $room_status = 'reserved';
    if ($status === 'checked_in') $room_status = 'occupied';
    if ($status === 'checked_out') $room_status = 'available';
    if ($status === 'cancelled') $room_status = 'available';

    if ($room_status !== null) {
        $roomUpdate = $conn->prepare("UPDATE rooms SET status = ? WHERE id = ?");
        $roomUpdate->bind_param("si", $room_status, $room_id);

        if (!$roomUpdate->execute()) {
            throw new Exception("Failed update room status: " . $roomUpdate->error);
        }
        $roomUpdate->close();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Booking status updated to $status"
    ]);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
