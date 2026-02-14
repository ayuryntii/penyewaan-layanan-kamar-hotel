<?php
// customer/invoice.php
session_start();
require_once '../includes/config.php';
requireCustomer();

$user_id = $_SESSION['user_id'];
$page_title = 'My Invoices';

// Get customer data
$customer_sql = "SELECT * FROM users WHERE id = ?";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("i", $user_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();
$customer_stmt->close();

// Get customer's bookings with invoices
$bookings_sql = "SELECT 
    b.id,
    b.booking_code,
    b.check_in,
    b.check_out,
    b.total_nights,
    b.final_price,
    b.booking_status,
    b.payment_status,
    b.created_at,
    r.room_number,
    rc.name as room_type,
    rc.base_price
FROM bookings b
JOIN rooms r ON b.room_id = r.id
JOIN room_categories rc ON r.category_id = rc.id
WHERE b.user_id = ?
ORDER BY b.created_at DESC";

$bookings_stmt = $conn->prepare($bookings_sql);
$bookings_stmt->bind_param("i", $user_id);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Verify booking belongs to this customer
    $verify_sql = "SELECT id FROM bookings WHERE id = ? AND user_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $booking_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        // Update payment status
        $update_sql = "UPDATE bookings SET payment_status = 'paid', updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $booking_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success'] = 'Payment confirmed successfully!';
        } else {
            $_SESSION['error'] = 'Failed to confirm payment. Please try again.';
        }
        $update_stmt->close();
    } else {
        $_SESSION['error'] = 'Booking not found or access denied.';
    }
    $verify_stmt->close();
    
    header('Location: invoice.php');
    exit();
}
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
            --gray: #6c757d;
            --dark-bg: #0a192f;
            --card-bg: rgba(20, 30, 50, 0.85);
            --sidebar-width: 260px;
            --green: #28a745;
            --yellow: #ffc107;
            --red: #dc3545;
            --purple: #6f42c1;
            --orange: #fd7e14;
            --teal: #20c997;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark-bg);
            color: var(--light);
            overflow-x: hidden;
        }

        .customer-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* === Sidebar === */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--navy);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
            transition: all 0.3s ease;
            border-right: 1px solid rgba(76, 201, 240, 0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            background: var(--blue);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy);
            font-size: 18px;
        }

        .sidebar-title h3 {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .sidebar-title p {
            font-size: 0.85rem;
            color: #aaa;
        }

        .sidebar-nav {
            padding: 20px 0;
            overflow-y: auto;
            height: calc(100vh - 180px);
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #ccc;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
        }

        .nav-item i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }

        .nav-label {
            padding: 15px 25px 8px;
            color: #777;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-divider {
            height: 1px;
            background: rgba(255,255,255,0.05);
            margin: 15px 0;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--navy);
        }

        .user-info .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-info .user-role {
            font-size: 0.8rem;
            color: #aaa;
        }

        /* === Main Content === */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: rgba(10, 25, 47, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(76, 201, 240, 0.1);
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            display: none;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .content-area {
            padding: 30px;
        }

        /* === Cards === */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            margin-bottom: 25px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            overflow: hidden;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .card-title {
            font-size: 1.3rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-actions {
            display: flex;
            gap: 10px;
        }

        .card-body {
            padding: 25px;
        }

        /* === Badges === */
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .badge-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .badge-primary {
            background: rgba(76, 201, 240, 0.2);
            color: var(--blue);
            border: 1px solid rgba(76, 201, 240, 0.3);
        }

        .badge-secondary {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }

        .badge-teal {
            background: rgba(32, 201, 151, 0.2);
            color: var(--teal);
            border: 1px solid rgba(32, 201, 151, 0.3);
        }

        /* === Buttons === */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-primary {
            background: var(--blue);
            color: var(--navy);
        }

        .btn-success {
            background: var(--green);
            color: white;
        }

        .btn-warning {
            background: var(--yellow);
            color: var(--navy);
        }

        .btn-danger {
            background: var(--red);
            color: white;
        }

        .btn-secondary {
            background: var(--gray);
            color: white;
        }

        .btn-teal {
            background: var(--teal);
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* === Last Login === */
        .last-login {
            color: #aaa;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* === Logout Button === */
        .logout-btn {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(231, 76, 60, 0.3);
            transform: translateY(-1px);
        }

        /* === Alert Messages === */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border-color: rgba(40, 167, 69, 0.3);
            color: #28a745;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border-color: rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.2);
            border-color: rgba(255, 193, 7, 0.3);
            color: #ffc107;
        }

        /* === Invoice Cards === */
        .invoice-card {
            background: rgba(255,255,255,0.03);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
        }

        .invoice-card:hover {
            border-color: var(--blue);
            background: rgba(76, 201, 240, 0.05);
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .invoice-info {
            flex: 1;
        }

        .invoice-code {
            font-size: 1.5rem;
            color: var(--blue);
            font-weight: 700;
            margin-bottom: 5px;
        }

        .invoice-date {
            color: #aaa;
            font-size: 0.9rem;
        }

        .invoice-status {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-end;
        }

        .invoice-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item {
            background: rgba(0, 0, 0, 0.2);
            padding: 12px 15px;
            border-radius: 8px;
        }

        .detail-label {
            color: #aaa;
            font-size: 0.8rem;
            margin-bottom: 5px;
        }

        .detail-value {
            color: white;
            font-weight: 500;
        }

        .invoice-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        /* === Empty State === */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* === Responsive === */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .invoice-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .invoice-status {
                align-items: flex-start;
            }
            
            .invoice-actions {
                justify-content: flex-start;
            }
            
            .invoice-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <div class="customer-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-hotel"></i>
                </div>
                <div class="sidebar-title">
                    <h3><?= htmlspecialchars($hotel_name) ?></h3>
                    <p>Customer Portal</p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">BOOKINGS</p>
                    <a href="bookings.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>My Bookings</span>
                    </a>
                    <a href="new-booking.php" class="nav-item">
                        <i class="fas fa-plus-circle"></i>
                        <span>New Booking</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">INVOICES</p>
                    <a href="invoice.php" class="nav-item active">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>My Invoices</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">PROFILE</p>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="settings.php" class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">HOTEL</p>
                    <a href="rooms.php" class="nav-item">
                        <i class="fas fa-bed"></i>
                        <span>View Rooms</span>
                    </a>
                    <a href="services.php" class="nav-item">
                        <i class="fas fa-concierge-bell"></i>
                        <span>Services</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($customer['full_name'], 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($customer['full_name']) ?></div>
                        <div class="user-role"><?= ucfirst($customer['role']) ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1><?= htmlspecialchars($page_title) ?></h1>
                </div>
                
                <div class="header-right">
                    <div class="last-login">
                        <i class="fas fa-clock"></i>
                        Last Login: <?= $customer['last_login'] ? date('d M Y H:i', strtotime($customer['last_login'])) : 'First login' ?>
                    </div>
                    
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <div class="content-area">
                <?php
                if (isset($_SESSION['success'])) {
                    echo '<div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success']) . '
                          </div>';
                    unset($_SESSION['success']);
                }
                
                if (isset($_SESSION['error'])) {
                    echo '<div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_SESSION['error']) . '
                          </div>';
                    unset($_SESSION['error']);
                }
                ?>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-file-invoice"></i>
                            My Invoices
                        </div>
                        <div class="card-actions">
                            <span class="badge badge-secondary">
                                <?= $bookings_result->num_rows ?> Invoice(s)
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($bookings_result->num_rows > 0): ?>
                            <?php while ($booking = $bookings_result->fetch_assoc()): ?>
                                <div class="invoice-card">
                                    <div class="invoice-header">
                                        <div class="invoice-info">
                                            <div class="invoice-code">#<?= htmlspecialchars($booking['booking_code']) ?></div>
                                            <div class="invoice-date">
                                                Booked on <?= date('d M Y', strtotime($booking['created_at'])) ?>
                                            </div>
                                        </div>
                                        <div class="invoice-status">
                                            <div>
                                                <span class="badge <?= $booking['booking_status'] === 'checked_out' ? 'badge-success' : ($booking['booking_status'] === 'checked_in' ? 'badge-primary' : 'badge-warning') ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $booking['booking_status'])) ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span class="badge <?= $booking['payment_status'] === 'paid' ? 'badge-success' : 'badge-warning' ?>">
                                                    <?= ucfirst($booking['payment_status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="invoice-details">
                                        <div class="detail-item">
                                            <div class="detail-label">Check-in Date</div>
                                            <div class="detail-value">
                                                <?= date('d M Y', strtotime($booking['check_in'])) ?>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Check-out Date</div>
                                            <div class="detail-value">
                                                <?= date('d M Y', strtotime($booking['check_out'])) ?>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Room</div>
                                            <div class="detail-value">
                                                <?= htmlspecialchars($booking['room_type']) ?> (Room <?= htmlspecialchars($booking['room_number']) ?>)
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Nights</div>
                                            <div class="detail-value">
                                                <?= $booking['total_nights'] ?> nights
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Total Amount</div>
                                            <div class="detail-value">
                                                <?= formatCurrency($booking['final_price']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="invoice-actions">
                                        <?php if ($booking['payment_status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Confirm payment for invoice #<?= $booking['booking_code'] ?>?');">
                                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                <button type="submit" name="confirm_payment" class="btn btn-success">
                                                    <i class="fas fa-check-circle"></i> Confirm Payment
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="download-invoice.php?booking_id=<?= $booking['id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-download"></i> Download Invoice
                                        </a>
                                        
                                        <a href="print-invoice.php?booking_id=<?= $booking['id'] ?>" target="_blank" class="btn btn-teal">
                                            <i class="fas fa-print"></i> Print Invoice
                                        </a>
                                        
                                        <a href="booking-details.php?id=<?= $booking['id'] ?>" class="btn btn-secondary">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-file-invoice-dollar fa-3x"></i>
                                <h3>No Invoices Found</h3>
                                <p>You don't have any booking invoices yet.</p>
                                <a href="new-booking.php" class="btn btn-primary" style="margin-top: 20px;">
                                    <i class="fas fa-plus-circle"></i> Make a Booking
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>

</body>
</html>
<?php
$bookings_stmt->close();
if (isset($conn)) {
    $conn->close();
}
?>