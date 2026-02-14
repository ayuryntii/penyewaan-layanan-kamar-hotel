<?php
// customer/print-invoice.php
session_start();
require_once '../includes/config.php';
requireCustomer();

if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    die('Invalid booking ID.');
}

$booking_id = (int)$_GET['booking_id'];
$user_id = $_SESSION['user_id'];

// Get booking data (ensure it belongs to the customer)
$sql = "SELECT 
    b.id,
    b.booking_code,
    b.check_in,
    b.check_out,
    b.total_nights,
    b.final_price,
    b.booking_status,
    b.payment_status,
    b.created_at,
    b.adults,
    b.children,
    b.special_requests,
    u.full_name AS guest_name,
    u.email AS guest_email,
    u.phone AS guest_phone,
    r.room_number,
    rc.name AS room_type,
    rc.base_price,
    rc.max_capacity,
    r.view_type,
    r.bed_type
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= htmlspecialchars($booking['booking_code']) ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 20mm;
            }
            body {
                font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
                color: #000;
                background: white;
                padding: 0;
                margin: 0;
            }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
        
        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            color: #333;
            background: white;
            max-width: 210mm;
            margin: 0 auto;
            padding: 20px;
        }
        
        .invoice-container {
            border: 2px solid #0a192f;
            border-radius: 10px;
            padding: 30px;
            position: relative;
            background: white;
        }
        
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0a192f;
            padding-bottom: 20px;
        }
        
        .hotel-name {
            color: #0a192f;
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 5px 0;
        }
        
        .hotel-address {
            color: #666;
            font-size: 14px;
            margin: 0 0 3px 0;
        }
        
        .invoice-title {
            color: #0a192f;
            font-size: 22px;
            font-weight: 600;
            margin: 20px 0 5px 0;
        }
        
        .invoice-number {
            color: #4cc9f0;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .info-section {
            margin: 25px 0;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .info-box {
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .info-label {
            font-weight: 600;
            color: #0a192f;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .info-value {
            color: #333;
            font-size: 15px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-checked-in { background: #cce5ff; color: #004085; }
        .status-checked-out { background: #d1ecf1; color: #0c5460; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        
        .invoice-table th {
            background: #0a192f;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .invoice-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .invoice-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .total-row td {
            background: #f8f9fa;
            font-weight: 700;
            font-size: 16px;
            color: #0a192f;
        }
        
        .total-amount {
            color: #28a745;
            font-size: 24px;
            font-weight: 800;
        }
        
        .invoice-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        
        .footer-note {
            font-style: italic;
            margin-top: 20px;
            color: #888;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            opacity: 0.05;
            font-size: 120px;
            font-weight: 900;
            color: #ccc;
            white-space: nowrap;
            pointer-events: none;
            z-index: -1;
        }
        
        .print-controls {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin: 0 5px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #4cc9f0;
            color: #0a192f;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .invoice-container {
                padding: 15px;
            }
            
            .info-section {
                grid-template-columns: 1fr;
            }
            
            .print-controls {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <h3>Invoice Ready to Print/Download</h3>
        <p>Use the buttons below to print or download this invoice as PDF.</p>
        <div>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Invoice
            </button>
            <button onclick="downloadPDF()" class="btn btn-primary">
                <i class="fas fa-download"></i> Download as PDF
            </button>
            <a href="invoice.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Invoices
            </a>
        </div>
    </div>
    
    <div class="invoice-container">
        <div class="watermark"><?= htmlspecialchars(strtoupper($hotel_name)) ?></div>
        
        <div class="invoice-header">
            <h1 class="hotel-name"><?= htmlspecialchars($hotel_name) ?></h1>
            <p class="hotel-address"><?= htmlspecialchars($config['address'] ?? 'Jl. Merdeka No. 123, Jakarta') ?></p>
            <p class="hotel-address">Phone: <?= htmlspecialchars($config['phone'] ?? '+62 21 1234 5678') ?></p>
            <p class="hotel-address">Email: <?= htmlspecialchars($config['email'] ?? 'info@hotel.com') ?></p>
            
            <h2 class="invoice-title">INVOICE</h2>
            <p class="invoice-number">Invoice #: <?= htmlspecialchars($booking['booking_code']) ?></p>
            <p>Invoice Date: <?= date('d M Y', strtotime($booking['created_at'])) ?></p>
        </div>
        
        <div class="info-section">
            <div class="info-box">
                <div class="info-label">BILL TO:</div>
                <div class="info-value"><?= htmlspecialchars($booking['guest_name']) ?></div>
                <div class="info-value">Email: <?= htmlspecialchars($booking['guest_email']) ?></div>
                <div class="info-value">Phone: <?= htmlspecialchars($booking['guest_phone'] ?? 'N/A') ?></div>
            </div>
            
            <div class="info-box">
                <div class="info-label">BOOKING DETAILS:</div>
                <div class="info-value">
                    <strong>Check-in:</strong> <?= date('d M Y H:i', strtotime($booking['check_in'])) ?>
                </div>
                <div class="info-value">
                    <strong>Check-out:</strong> <?= date('d M Y H:i', strtotime($booking['check_out'])) ?>
                </div>
                <div class="info-value">
                    <strong>Nights:</strong> <?= $booking['total_nights'] ?> night(s)
                </div>
                <div class="info-value">
                    <strong>Room:</strong> <?= htmlspecialchars($booking['room_type']) ?> (Room <?= $booking['room_number'] ?>)
                </div>
            </div>
        </div>
        
        <div class="info-section">
            <div class="info-box">
                <div class="info-label">BOOKING STATUS:</div>
                <div class="info-value">
                    <?php
                    $status = $booking['booking_status'];
                    $statusClass = '';
                    if ($status === 'confirmed') $statusClass = 'status-confirmed';
                    elseif ($status === 'checked_in') $statusClass = 'status-checked-in';
                    elseif ($status === 'checked_out') $statusClass = 'status-checked-out';
                    ?>
                    <span class="status-badge <?= $statusClass ?>">
                        <?= strtoupper(str_replace('_', ' ', $status)) ?>
                    </span>
                </div>
            </div>
            
            <div class="info-box">
                <div class="info-label">PAYMENT STATUS:</div>
                <div class="info-value">
                    <span class="status-badge <?= $booking['payment_status'] === 'paid' ? 'status-paid' : 'status-pending' ?>">
                        <?= strtoupper($booking['payment_status']) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($booking['room_type']) ?></strong><br>
                        Room <?= $booking['room_number'] ?> • <?= $booking['view_type'] ?> View • <?= $booking['bed_type'] ?> Bed<br>
                        Max Capacity: <?= $booking['max_capacity'] ?> Persons • <?= $booking['total_nights'] ?> Night(s)
                    </td>
                    <td><?= $booking['total_nights'] ?> night(s)</td>
                    <td><?= formatCurrency($booking['base_price']) ?></td>
                    <td><?= formatCurrency($booking['final_price']) ?></td>
                </tr>
                
                <?php if ($booking['special_requests']): ?>
                <tr>
                    <td colspan="4">
                        <strong>Special Requests:</strong> <?= htmlspecialchars($booking['special_requests']) ?>
                    </td>
                </tr>
                <?php endif; ?>
                
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;"><strong>TOTAL AMOUNT:</strong></td>
                    <td class="total-amount"><?= formatCurrency($booking['final_price']) ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="invoice-footer">
            <p><strong>Payment Terms:</strong> Payment is due upon check-out. All amounts are in Indonesian Rupiah (IDR).</p>
            <p><strong>Bank Transfer Information:</strong></p>
            <p>Bank: BCA • Account: 123-456-7890 • Name: <?= htmlspecialchars($hotel_name) ?></p>
            
            <div class="footer-note">
                <p>This is a computer-generated invoice. No signature required.</p>
                <p>Thank you for choosing <?= htmlspecialchars($hotel_name) ?>!</p>
            </div>
            
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
                <p><small>Invoice generated on: <?= date('d M Y H:i:s') ?></small></p>
                <p><small>Invoice ID: <?= $booking['id'] ?> • Customer ID: <?= $user_id ?></small></p>
            </div>
        </div>
    </div>
    
    <script>
        function downloadPDF() {
            // Simple PDF download using browser's print to PDF
            window.print();
        }
        
        // Auto trigger print dialog if print parameter exists
        window.addEventListener('DOMContentLoaded', (event) => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print') === 'true') {
                window.print();
            }
        });
    </script>
</body>
</html>
<?php
$stmt->close();
if (isset($conn)) {
    $conn->close();
}
?>