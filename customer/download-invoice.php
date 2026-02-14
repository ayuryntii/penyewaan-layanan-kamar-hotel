<?php
// customer/download-invoice.php
session_start();
require_once '../includes/config.php';
requireCustomer();

if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    die('Invalid booking ID.');
}

$booking_id = (int)$_GET['booking_id'];
$user_id = $_SESSION['user_id'];

// Get booking data
$sql = "SELECT 
    b.booking_code,
    b.check_in,
    b.check_out,
    b.total_nights,
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
WHERE b.id = ? AND b.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    die('Invoice not found or access denied.');
}

$stmt->close();
$conn->close();

// Set headers for download
header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="Invoice_' . $booking['booking_code'] . '.html"');
header('Pragma: no-cache');
header('Expires: 0');

// HTML content for download
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= htmlspecialchars($booking['booking_code']) ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 20px;
            color: #000;
            background: white;
        }
        
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }
        
        .hotel-name {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        
        .invoice-title {
            font-size: 20px;
            margin: 20px 0 5px 0;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .invoice-table th, .invoice-table td {
            border: 1px solid #000;
            padding: 10px;
        }
        
        .total-row {
            font-weight: bold;
            background: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="invoice-header">
        <h1 class="hotel-name"><?= htmlspecialchars($hotel_name) ?></h1>
        <p><?= htmlspecialchars($config['address'] ?? 'Jl. Merdeka No. 123, Jakarta') ?></p>
        <p>Phone: <?= htmlspecialchars($config['phone'] ?? '+62 21 1234 5678') ?></p>
        
        <h2 class="invoice-title">INVOICE</h2>
        <p>Invoice #: <?= htmlspecialchars($booking['booking_code']) ?></p>
    </div>
    
    <div>
        <p><strong>Guest Name:</strong> <?= htmlspecialchars($booking['guest_name']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($booking['guest_email']) ?></p>
        <p><strong>Check-in:</strong> <?= date('d M Y', strtotime($booking['check_in'])) ?></p>
        <p><strong>Check-out:</strong> <?= date('d M Y', strtotime($booking['check_out'])) ?></p>
        <p><strong>Room:</strong> <?= htmlspecialchars($booking['room_type']) ?> (Room <?= htmlspecialchars($booking['room_number']) ?>)</p>
        <p><strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', $booking['booking_status'])) ?></p>
        <p><strong>Payment:</strong> <?= ucfirst($booking['payment_status']) ?></p>
    </div>
    
    <table class="invoice-table">
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
            <tr class="total-row">
                <td><strong>Total</strong></td>
                <td><strong><?= formatCurrency($booking['final_price']) ?></strong></td>
            </tr>
        </tbody>
    </table>
    
    <div style="margin-top: 40px;">
        <p>Thank you for staying with us!</p>
        <p><em>This is a computer-generated invoice. No signature required.</em></p>
        <p><small>Generated on: <?= date('d M Y H:i:s') ?></small></p>
    </div>
</body>
</html>