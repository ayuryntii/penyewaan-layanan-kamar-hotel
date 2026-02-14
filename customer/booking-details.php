<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireLogin();

$booking_id = intval($_GET['id'] ?? 0);

if ($booking_id <= 0) {
    http_response_code(400);
    echo "<div style='padding:16px;color:white;'>Invalid booking id</div>";
    exit();
}

$sql = "SELECT 
            b.*, 
            r.room_number,
            rc.name AS category_name
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN room_categories rc ON r.category_id = rc.id
        WHERE b.id = ? AND b.user_id = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    http_response_code(404);
    echo "<div style='padding:16px;color:white;'>Booking not found</div>";
    exit();
}

$total = $booking['final_price'] ?? $booking['total_price'] ?? 0;
?>

<div style="padding: 18px; color: white;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:14px;">
        <div>
            <h3 style="margin:0;font-size:18px;font-weight:800;">Booking Details</h3>
            <p style="margin:6px 0 0;color:rgba(255,255,255,0.6);font-size:13px;">
                <?= htmlspecialchars($booking['booking_code']) ?>
            </p>
        </div>
    </div>

    <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:14px;">
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.08);">
            <span style="color:rgba(255,255,255,0.65);">Room</span>
            <strong><?= htmlspecialchars($booking['category_name']) ?> (Room <?= htmlspecialchars($booking['room_number']) ?>)</strong>
        </div>

        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.08);">
            <span style="color:rgba(255,255,255,0.65);">Check-in</span>
            <strong><?= htmlspecialchars($booking['check_in']) ?></strong>
        </div>

        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.08);">
            <span style="color:rgba(255,255,255,0.65);">Check-out</span>
            <strong><?= htmlspecialchars($booking['check_out']) ?></strong>
        </div>

        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.08);">
            <span style="color:rgba(255,255,255,0.65);">Nights</span>
            <strong><?= intval($booking['total_nights']) ?> night(s)</strong>
        </div>

        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.08);">
            <span style="color:rgba(255,255,255,0.65);">Guests</span>
            <strong><?= intval($booking['adults']) ?> Adults, <?= intval($booking['children']) ?> Children</strong>
        </div>

        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:none;">
            <span style="color:rgba(255,255,255,0.65);">Total</span>
            <strong><?= formatCurrency($total) ?></strong>
        </div>
    </div>
</div>
