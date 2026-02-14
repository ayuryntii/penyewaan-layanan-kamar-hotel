<?php
session_start();
require_once '../includes/config.php';
requireReceptionist();

if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    exit('Invalid booking ID.');
}

$booking_id = (int)$_GET['booking_id'];

// Ambil data booking
$sql = "SELECT 
    b.booking_code,
    b.check_in,
    b.check_out,
    b.final_price,
    b.booking_status,
    b.payment_status,
    b.created_at,
    u.full_name AS guest_name,
    u.email AS guest_email,
    r.room_number,
    rc.name AS room_type
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
    exit('Booking not found.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice - <?= htmlspecialchars($booking['booking_code']) ?></title>
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: white;
    color: #333;
    padding: 20px;
    max-width: 800px;
    margin: 0 auto;
}
.invoice-header {
    text-align: center;
    margin-bottom: 30px;
    border-bottom: 2px solid #333;
    padding-bottom: 20px;
}
.invoice-header h1 {
    margin: 0;
    color: #0a192f;
}
.invoice-header p {
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
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: bold;
    text-transform: uppercase;
}
.badge-success { background: #d4edda; color: #155724; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-primary { background: #cce5ff; color: #004085; }
.badge-secondary { background: #e2e3e5; color: #383d41; }

@media print {
    body { padding: 0; }
    .no-print { display: none; }
}
</style>
</head>
<body>

<div class="invoice-header">
    <h1><?= htmlspecialchars($config['hotel_name'] ?? 'Grand Hotel') ?></h1>
    <p><?= htmlspecialchars($config['address'] ?? 'Jl. Merdeka No. 123, Jakarta') ?></p>
    <p>Phone: <?= htmlspecialchars($config['phone'] ?? '+62 21 1234 5678') ?></p>
    <h2>INVOICE</h2>
    <p>Invoice #: <?= htmlspecialchars($booking['booking_code']) ?></p>
</div>

<div class="section">
    <div class="info-row">
        <span class="info-label">Guest Name:</span>
        <span class="info-value"><?= htmlspecialchars($booking['guest_name']) ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Email:</span>
        <span class="info-value"><?= htmlspecialchars($booking['guest_email']) ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Check-in:</span>
        <span class="info-value"><?= date('d M Y H:i', strtotime($booking['check_in'])) ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Check-out:</span>
        <span class="info-value"><?= date('d M Y H:i', strtotime($booking['check_out'])) ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Room:</span>
        <span class="info-value"><?= htmlspecialchars($booking['room_type']) ?> (Room <?= htmlspecialchars($booking['room_number']) ?>)</span>
    </div>
    <div class="info-row">
        <span class="info-label">Status:</span>
        <span class="info-value">
            <?php
            $status = $booking['booking_status'];
            $badgeClass = '';
            $label = '';
            if ($status === 'confirmed') {
                $badgeClass = 'badge-primary';
                $label = 'Confirmed';
            } elseif ($status === 'checked_in') {
                $badgeClass = 'badge-success';
                $label = 'Checked In';
            } elseif ($status === 'checked_out') {
                $badgeClass = 'badge-secondary';
                $label = 'Checked Out';
            } else {
                $badgeClass = 'badge-secondary';
                $label = 'Unknown';
            }
            ?>
            <span class="status-badge <?= $badgeClass ?>"><?= $label ?></span>
        </span>
    </div>
    <div class="info-row">
        <span class="info-label">Payment:</span>
        <span class="info-value">
            <span class="status-badge <?= $booking['payment_status'] === 'paid' ? 'badge-success' : 'badge-warning' ?>">
                <?= ucfirst($booking['payment_status']) ?>
            </span>
        </span>
    </div>
</div>

<div class="section">
    <table class="table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Room: <?= htmlspecialchars($booking['room_type']) ?> (<?= $booking['room_number'] ?>)</td>
                <td><?= formatCurrency($booking['final_price']) ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td><strong>Total</strong></td>
                <td><strong><?= formatCurrency($booking['final_price']) ?></strong></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="section" style="margin-top: 40px;">
    <p>Thank you for staying with us!</p>
    <p style="font-style: italic;">This is a computer-generated invoice. No signature required.</p>
</div>

<div class="no-print" style="text-align: center; margin-top: 30px;">
    <button onclick="window.print()" class="btn" style="padding:10px 20px;background:#0a192f;color:white;border:none;border-radius:5px;cursor:pointer;">
        <i class="fas fa-print"></i> Print Invoice
    </button>
    <a href="booking-details.php?id=<?= $booking_id ?>" style="margin-left:10px;padding:10px 20px;background:#4cc9f0;color:#0a192f;text-decoration:none;border-radius:5px;">
        Back to Details
    </a>
</div>

</body>
</html>