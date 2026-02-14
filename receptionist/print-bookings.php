<?php
session_start();
require_once '../includes/config.php';
requireReceptionist();

$bookings = $_SESSION['print_bookings'] ?? [];
if (empty($bookings)) {
    header("Location: bookings.php");
    exit();
}
unset($_SESSION['print_bookings']);
?>

<!DOCTYPE html>
<html>
<head>
<title>Print Bookings</title>
<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { border: 1px solid #000; padding: 8px; text-align: left; }
th { background: #f0f0f0; }
.no-print { margin-top: 20px; }
@media print {
    .no-print { display: none; }
}
</style>
</head>
<body>

<h2>Bookings Report - <?= date('d M Y') ?></h2>

<table>
<thead>
<tr>
<th>Code</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Total</th>
</tr>
</thead>
<tbody>
<?php foreach ($bookings as $b): ?>
<tr>
<td><?= htmlspecialchars($b['booking_code']) ?></td>
<td><?= htmlspecialchars($b['full_name'] ?? $b['username']) ?></td>
<td><?= htmlspecialchars($b['room_number']) ?></td>
<td><?= date('d/m/Y', strtotime($b['check_in'])) ?></td>
<td><?= date('d/m/Y', strtotime($b['check_out'])) ?></td>
<td><?= ucfirst(str_replace('_', ' ', $b['booking_status'])) ?></td>
<td>Rp <?= number_format($b['final_price'], 0, ',', '.') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="no-print">
<button onclick="window.print()">🖨️ Print / Save as PDF</button>
<a href="bookings.php">← Back to List</a>
</div>

</body>
</html>