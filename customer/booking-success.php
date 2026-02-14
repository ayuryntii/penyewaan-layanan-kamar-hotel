<?php
// customer/booking-success.php - BOOKING SUCCESS PAGE
session_start();
require_once '../includes/config.php';
requireCustomer();

if (!isset($_SESSION['new_booking_id'])) {
    header('Location: new-booking.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_id = $_SESSION['new_booking_id'];
$page_title = 'Booking Success';

// Get booking details
$sql = "SELECT b.*, r.room_number, rc.name as room_type, rc.base_price 
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN room_categories rc ON r.category_id = rc.id
        WHERE b.id = ? AND b.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: bookings.php');
    exit();
}

$booking = $result->fetch_assoc();
$stmt->close();

// Clear the session variable
unset($_SESSION['new_booking_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($hotel_name) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #0a192f;
            --blue: #4cc9f0;
            --blue-dark: #3a86ff;
            --light: #f8f9fa;
            --dark-bg: #0a192f;
            --card-bg: rgba(20, 30, 50, 0.85);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark-bg);
            color: var(--light);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            width: 100%;
            max-width: 600px;
        }

        .success-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 40px;
            border: 1px solid rgba(76, 201, 240, 0.2);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 48px;
            color: white;
        }

        .success-title {
            font-size: 32px;
            margin-bottom: 15px;
            color: #2ecc71;
        }

        .success-subtitle {
            color: #aaa;
            font-size: 18px;
            margin-bottom: 30px;
        }

        .booking-details {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #aaa;
        }

        .detail-value {
            color: white;
            font-weight: 600;
        }

        .booking-id {
            font-size: 24px;
            font-weight: bold;
            color: var(--blue);
            margin: 10px 0;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--blue);
            color: var(--navy);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--blue);
            color: var(--blue);
        }

        .btn-primary:hover {
            background: #3abde0;
            transform: translateY(-2px);
        }

        .btn-secondary:hover {
            background: rgba(76, 201, 240, 0.1);
            transform: translateY(-2px);
        }

        .hotel-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #777;
            font-size: 14px;
        }

        @media (max-width: 480px) {
            .success-card {
                padding: 30px 20px;
            }
            
            .success-title {
                font-size: 24px;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1 class="success-title">Booking Successful!</h1>
            <p class="success-subtitle">Your reservation has been confirmed. We look forward to hosting you!</p>
            
            <div class="booking-details">
                <div class="detail-row">
                    <span class="detail-label">Booking ID:</span>
                    <span class="booking-id"><?= $booking['booking_code'] ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Room:</span>
                    <span class="detail-value"><?= $booking['room_type'] ?> (Room <?= $booking['room_number'] ?>)</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Check-in:</span>
                    <span class="detail-value"><?= date('F d, Y', strtotime($booking['check_in'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Check-out:</span>
                    <span class="detail-value"><?= date('F d, Y', strtotime($booking['check_out'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span class="detail-value"><?= $booking['total_nights'] ?> nights</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Guests:</span>
                    <span class="detail-value"><?= $booking['adults'] ?> Adult<?= $booking['adults'] > 1 ? 's' : '' ?><?= $booking['children'] > 0 ? ', ' . $booking['children'] . ' Child' . ($booking['children'] > 1 ? 'ren' : '') : '' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value" style="color: var(--blue); font-size: 18px;"><?= formatCurrency($booking['final_price']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value" style="background: rgba(243, 156, 18, 0.2); color: #f39c12; padding: 5px 10px; border-radius: 20px;">
                        Pending Payment
                    </span>
                </div>
            </div>
            
            <p style="color: #aaa; margin: 20px 0; font-size: 14px;">
                <i class="fas fa-info-circle"></i> Please complete your payment to confirm your booking. You will receive a confirmation email shortly.
            </p>
            
            <div class="actions">
                <a href="payment.php?booking_id=<?= $booking['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-credit-card"></i> Make Payment Now
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </a>
                <a href="bookings.php" class="btn btn-secondary">
                    <i class="fas fa-calendar-check"></i> View All Bookings
                </a>
            </div>
            
            <div class="hotel-info">
                <p><strong>Need Help?</strong></p>
                <p>Contact our reservations team:</p>
                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($hotel_phone) ?></p>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($hotel_email) ?></p>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>