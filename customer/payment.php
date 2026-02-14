<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();

$page_title = "Payment";

// ambil booking_id dari URL
$booking_id = intval($_GET['booking_id'] ?? 0);

if ($booking_id <= 0) {
    $_SESSION['error'] = "Invalid booking ID.";
    header("Location: dashboard.php");
    exit();
}

// ambil booking milik user yg sedang login
$sql = "SELECT 
            b.*,
            r.room_number,
            rc.name AS category_name,
            rc.base_price
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
    $_SESSION['error'] = "Booking not found or you don't have access.";
    header("Location: dashboard.php");
    exit();
}

// jumlah malam
$totalNights = intval($booking['total_nights'] ?? 1);
if ($totalNights <= 0) $totalNights = 1;

// total bayar (yang benar)
$amount = $booking['final_price'] ?? $booking['total_price'] ?? 0;

// handle submit pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // kalau sudah paid, jangan bayar ulang
    if (($booking['payment_status'] ?? '') === 'paid') {
        $_SESSION['info'] = "This booking has already been paid.";
        header("Location: bookings.php");
        exit();
    }

    $method = $_POST['payment_method'] ?? 'cash';

    // validasi metode pembayaran
    $allowed = ['cash', 'transfer', 'qris'];
    if (!in_array($method, $allowed)) {
        $_SESSION['error'] = "Invalid payment method.";
    } else {
        // update status payment di tabel bookings
        $update = $conn->prepare("
            UPDATE bookings 
            SET payment_status='paid', payment_method=? 
            WHERE id=? AND user_id=?
        ");
        $update->bind_param("sii", $method, $booking_id, $_SESSION['user_id']);

        if ($update->execute()) {
            $update->close();
            $_SESSION['success'] = "Payment successful! Thank you.";
            header("Location: bookings.php");
            exit();
        } else {
            $update->close();
            $_SESSION['error'] = "Payment failed. Please try again.";
        }
    }
}

// biar tampilan status enak dibaca
$paymentStatus = $booking['payment_status'] ?? 'pending';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($hotel_name ?? 'Hotel') ?></title>

    <style>
        body{
            margin:0;
            font-family: Arial, sans-serif;
            background:#0a192f;
            color:#fff;
            min-height:100vh;
            display:flex;
            justify-content:center;
            align-items:flex-start;
            padding:40px 16px;
        }
        .box{
            width:100%;
            max-width:560px;
            background:rgba(255,255,255,0.05);
            border:1px solid rgba(255,255,255,0.10);
            border-radius:18px;
            padding:22px;
            box-shadow:0 18px 45px rgba(0,0,0,0.35);
        }
        h2{
            margin:0 0 18px 0;
            font-size:28px;
            font-weight:800;
            letter-spacing:0.3px;
        }
        .meta{
            background:rgba(255,255,255,0.04);
            border:1px solid rgba(255,255,255,0.08);
            border-radius:14px;
            padding:14px;
            margin-bottom:18px;
        }
        .row{
            display:flex;
            justify-content:space-between;
            gap:14px;
            padding:10px 0;
            border-bottom:1px solid rgba(255,255,255,0.08);
            font-size:14px;
        }
        .row:last-child{
            border-bottom:none;
        }
        .label{
            color:rgba(255,255,255,0.65);
        }
        .value{
            font-weight:700;
            color:#fff;
            text-align:right;
        }

        .status{
            display:inline-block;
            padding:6px 12px;
            border-radius:999px;
            font-size:12px;
            font-weight:800;
        }
        .pending{ background:rgba(255,193,7,0.2); color:#ffc107; border:1px solid rgba(255,193,7,0.35); }
        .paid{ background:rgba(46,204,113,0.2); color:#2ecc71; border:1px solid rgba(46,204,113,0.35); }

        label{
            display:block;
            margin:14px 0 8px;
            font-weight:700;
            color:rgba(255,255,255,0.85);
            font-size:14px;
        }
        select, input{
            width:100%;
            padding:12px 12px;
            border-radius:12px;
            border:1px solid rgba(255,255,255,0.18);
            background:rgba(255,255,255,0.06);
            color:#fff;
            outline:none;
        }
        select:focus, input:focus{
            border-color: rgba(76,201,240,0.8);
            box-shadow:0 0 0 3px rgba(76,201,240,0.15);
        }

        .btn{
            width:100%;
            margin-top:18px;
            padding:12px 14px;
            border:none;
            border-radius:14px;
            cursor:pointer;
            font-weight:900;
            font-size:14px;
        }
        .btn-primary{
            background:#4cc9f0;
            color:#0a192f;
        }
        .btn-primary:disabled{
            opacity:0.5;
            cursor:not-allowed;
        }

        .links{
            display:flex;
            gap:10px;
            margin-top:14px;
            flex-wrap:wrap;
        }
        .link{
            color:#4cc9f0;
            text-decoration:none;
            font-weight:700;
            font-size:14px;
        }
        .alert{
            padding:12px 14px;
            border-radius:14px;
            margin-bottom:14px;
            font-size:14px;
            border:1px solid transparent;
        }
        .alert-success{ background:rgba(46,204,113,0.18); border-color:rgba(46,204,113,0.35); color:#2ecc71; }
        .alert-danger{ background:rgba(231,76,60,0.18); border-color:rgba(231,76,60,0.35); color:#ff6b6b; }
        .alert-info{ background:rgba(52,152,219,0.18); border-color:rgba(52,152,219,0.35); color:#74c0fc; }
    </style>
</head>
<body>

<div class="box">
    <h2>Payment</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['info'])): ?>
        <div class="alert alert-info"><?= $_SESSION['info']; unset($_SESSION['info']); ?></div>
    <?php endif; ?>

    <div class="meta">
        <div class="row">
            <div class="label">Booking Code</div>
            <div class="value"><?= htmlspecialchars($booking['booking_code'] ?? '-') ?></div>
        </div>

        <div class="row">
            <div class="label">Room</div>
            <div class="value"><?= htmlspecialchars(($booking['category_name'] ?? '-') . ' (Room ' . ($booking['room_number'] ?? '-') . ')') ?></div>
        </div>

        <div class="row">
            <div class="label">Check-in</div>
            <div class="value"><?= htmlspecialchars($booking['check_in'] ?? '-') ?></div>
        </div>

        <div class="row">
            <div class="label">Check-out</div>
            <div class="value"><?= htmlspecialchars($booking['check_out'] ?? '-') ?></div>
        </div>

        <div class="row">
            <div class="label">Nights</div>
            <div class="value"><?= $totalNights ?> night(s)</div>
        </div>

        <div class="row">
            <div class="label">Total</div>
            <div class="value"><?= formatCurrency($amount) ?></div>
        </div>

        <div class="row">
            <div class="label">Payment Status</div>
            <div class="value">
                <?php if ($paymentStatus === 'paid'): ?>
                    <span class="status paid">PAID</span>
                <?php else: ?>
                    <span class="status pending">PENDING</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <form method="POST">
        <label>Payment Method</label>
        <select name="payment_method" <?= ($paymentStatus === 'paid') ? 'disabled' : '' ?>>
            <option value="cash" <?= (($booking['payment_method'] ?? '') === 'cash') ? 'selected' : '' ?>>Cash</option>
            <option value="transfer" <?= (($booking['payment_method'] ?? '') === 'transfer') ? 'selected' : '' ?>>Bank Transfer</option>
            <option value="qris" <?= (($booking['payment_method'] ?? '') === 'qris') ? 'selected' : '' ?>>QRIS</option>
        </select>

        <button class="btn btn-primary" type="submit" <?= ($paymentStatus === 'paid') ? 'disabled' : '' ?>>
            <?= ($paymentStatus === 'paid') ? 'Already Paid' : 'Confirm Payment' ?>
        </button>
    </form>

    <div class="links">
        <a class="link" href="bookings.php">← Back to Bookings</a>
        <a class="link" href="dashboard.php">Go to Dashboard</a>
    </div>
</div>

</body>
</html>
