<?php
session_start();
require_once '../includes/config.php';
requireReceptionist();

if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    die('Invalid booking ID.');
}

$booking_id = (int)$_GET['booking_id'];

// Ambil data booking
$sql = "SELECT b.*, u.full_name, u.email, u.phone, r.room_number, rc.name as room_type
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN rooms r ON b.room_id = r.id
        JOIN room_categories rc ON r.category_id = rc.id
        WHERE b.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    die('Booking not found.');
}

$page_title = 'Print Check-in Form - #' . htmlspecialchars($booking['booking_code']);
$hotel_name = $config['hotel_name'] ?? 'Hotel System';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?></title>
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: white;
    color: #333;
    padding: 20px;
    max-width: 800px;
    margin: 0 auto;
}
.header {
    text-align: center;
    margin-bottom: 30px;
    border-bottom: 2px solid #333;
    padding-bottom: 20px;
}
.header h1 {
    margin: 0;
    color: #0a192f;
}
.header p {
    margin: 5px 0;
    color: #666;
}
.section {
    margin-bottom: 25px;
}
.section h3 {
    margin-bottom: 10px;
    color: #0a192f;
    border-bottom: 1px dashed #ccc;
    padding-bottom: 5px;
}
.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}
.info-label {
    font-weight: bold;
    width: 180px;
}
.info-value {
    flex: 1;
}
.table {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
}
.table th,
.table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
.table th {
    background-color: #f8f9fa;
    font-weight: bold;
}
.total-row {
    font-weight: bold;
    font-size: 1.1em;
}
.footer {
    text-align: center;
    margin-top: 50px;
    padding-top: 20px;
    border-top: 1px solid #ccc;
    font-size: 0.9rem;
    color: #777;
}
@media print {
    body {
        background: white;
        color: black;
    }
    .no-print {
        display: none;
    }
}
</style>
</head>
<body>

<div class="header">
<h1><?= htmlspecialchars($hotel_name) ?></h1>
<p><?= htmlspecialchars($config['address'] ?? 'Jl. Merdeka No. 123, Jakarta') ?></p>
<p>Phone: <?= htmlspecialchars($config['phone'] ?? '+62 21 1234 5678') ?></p>
<h2>Check-in Form</h2>
<p>Booking #: <?= htmlspecialchars($booking['booking_code']) ?></p>
</div>

<div class="section">
<h3>Guest Information</h3>
<div class="info-row">
<span class="info-label">Name:</span>
<span class="info-value"><?= htmlspecialchars($booking['full_name']) ?></span>
</div>
<div class="info-row">
<span class="info-label">Email:</span>
<span class="info-value"><?= htmlspecialchars($booking['email']) ?></span>
</div>
<div class="info-row">
<span class="info-label">Phone:</span>
<span class="info-value"><?= htmlspecialchars($booking['phone']) ?></span>
</div>
</div>

<div class="section">
<h3>Room Details</h3>
<div class="info-row">
<span class="info-label">Room Type:</span>
<span class="info-value"><?= htmlspecialchars($booking['room_type']) ?></span>
</div>
<div class="info-row">
<span class="info-label">Room Number:</span>
<span class="info-value">Room <?= htmlspecialchars($booking['room_number']) ?></span>
</div>
<div class="info-row">
<span class="info-label">Check-in Date:</span>
<span class="info-value"><?= date('d M Y H:i', strtotime($booking['check_in'])) ?></span>
</div>
<div class="info-row">
<span class="info-label">Check-out Date:</span>
<span class="info-value"><?= date('d M Y H:i', strtotime($booking['check_out'])) ?></span>
</div>
<div class="info-row">
<span class="info-label">Nights:</span>
<span class="info-value">
<?php
$nights = max(1, (strtotime($booking['check_out']) - strtotime($booking['check_in'])) / 86400);
echo $nights . ' night' . ($nights > 1 ? 's' : '');
?>
</span>
</div>
</div>

<div class="section">
<h3>Payment Information</h3>
<div class="info-row">
<span class="info-label">Total Amount:</span>
<span class="info-value">Rp <?= number_format($booking['final_price'], 0, ',', '.') ?></span>
</div>
<div class="info-row">
<span class="info-label">Payment Status:</span>
<span class="info-value">
<?php
$status = $booking['payment_status'];
echo ucfirst($status);
?>
</span>
</div>
</div>

<div class="section">
<h3>Special Requests</h3>
<div class="info-row">
<span class="info-label">Requests:</span>
<span class="info-value">
<?= $booking['special_requests'] ? htmlspecialchars($booking['special_requests']) : 'None' ?>
</span>
</div>
</div>

<div class="footer">
<p>This is a computer-generated form. Please sign below to confirm check-in.</p>
<div style="margin: 30px 0; display: flex; justify-content: space-between;">
<div style="text-align: center; width: 30%;">
    <div style="height: 80px; border-bottom: 1px solid #000; margin-bottom: 5px;"></div>
    <div>Guest Signature</div>
</div>
<div style="text-align: center; width: 30%;">
    <div style="height: 80px; border-bottom: 1px solid #000; margin-bottom: 5px;"></div>
    <div>Receptionist</div>
</div>
<div style="text-align: center; width: 30%;">
    <div style="height: 80px; border-bottom: 1px solid #000; margin-bottom: 5px;"></div>
    <div>Date & Time</div>
</div>
</div>
</div>

<script>
// Auto-print when page loads
window.onload = function() {
    window.print();
};
</script>

</body>
</html>