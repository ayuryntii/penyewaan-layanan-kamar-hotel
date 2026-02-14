<?php
// hotel/ajax/get_customer.php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'receptionist')) {
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit();
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo '<div class="alert alert-danger">Invalid customer ID</div>';
    exit();
}

// Get customer data with booking statistics
$sql = "SELECT u.*, 
        COUNT(b.id) as total_bookings,
        SUM(CASE WHEN b.payment_status = 'paid' THEN b.final_price ELSE 0 END) as total_spent,
        MAX(b.check_in) as last_booking,
        MIN(b.check_in) as first_booking
        FROM users u 
        LEFT JOIN bookings b ON u.id = b.user_id 
        WHERE u.id = ? AND u.role = 'customer'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<div class="alert alert-danger">Customer not found</div>';
    exit();
}

$customer = $result->fetch_assoc();
$stmt->close();

// Get customer's bookings history
$bookings_sql = "SELECT b.*, r.room_number, rc.name as room_type 
                 FROM bookings b
                 JOIN rooms r ON b.room_id = r.id
                 JOIN room_categories rc ON r.category_id = rc.id
                 WHERE b.user_id = ?
                 ORDER BY b.created_at DESC
                 LIMIT 10";
$bookings_stmt = $conn->prepare($bookings_sql);
$bookings_stmt->bind_param("i", $id);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();

// Format registration date
$reg_date = date('d M Y, H:i', strtotime($customer['created_at']));
$status_class = $customer['status'] == 'active' ? 'success' : ($customer['status'] == 'inactive' ? 'warning' : 'danger');
?>

<div class="customer-details">
    <!-- Customer Info Header -->
    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1);">
        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; color: white; font-weight: bold;">
            <?= strtoupper(substr($customer['full_name'], 0, 1)) ?>
        </div>
        <div>
            <h3 style="margin: 0 0 5px 0; color: white;"><?= htmlspecialchars($customer['full_name']) ?></h3>
            <p style="margin: 0; color: #aaa;">@<?= htmlspecialchars($customer['username']) ?></p>
            <div style="display: flex; gap: 10px; margin-top: 10px;">
                <span class="status-badge status-<?= $status_class ?>"><?= ucfirst($customer['status']) ?></span>
                <span class="badge">Customer ID: CUST<?= str_pad($customer['id'], 5, '0', STR_PAD_LEFT) ?></span>
            </div>
        </div>
    </div>

    <!-- Customer Stats -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <div style="background: rgba(76, 201, 240, 0.1); padding: 20px; border-radius: 10px; border: 1px solid rgba(76, 201, 240, 0.2);">
            <div style="font-size: 24px; font-weight: bold; color: var(--blue);"><?= $customer['total_bookings'] ?></div>
            <div style="color: #aaa; font-size: 14px;">Total Bookings</div>
        </div>
        <div style="background: rgba(46, 204, 113, 0.1); padding: 20px; border-radius: 10px; border: 1px solid rgba(46, 204, 113, 0.2);">
            <div style="font-size: 24px; font-weight: bold; color: #2ecc71;"><?= formatCurrency($customer['total_spent'] ?? 0) ?></div>
            <div style="color: #aaa; font-size: 14px;">Total Spent</div>
        </div>
        <div style="background: rgba(155, 89, 182, 0.1); padding: 20px; border-radius: 10px; border: 1px solid rgba(155, 89, 182, 0.2);">
            <div style="font-size: 24px; font-weight: bold; color: #9b59b6;"><?= $reg_date ?></div>
            <div style="color: #aaa; font-size: 14px;">Member Since</div>
        </div>
    </div>

    <!-- Customer Details -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
        <div>
            <h4 style="color: var(--blue); margin-bottom: 15px; font-size: 16px;">
                <i class="fas fa-user-circle"></i> Personal Information
            </h4>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 8px 0; color: #aaa; width: 120px;">Full Name:</td>
                    <td style="padding: 8px 0; color: white;"><?= htmlspecialchars($customer['full_name']) ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #aaa;">Username:</td>
                    <td style="padding: 8px 0; color: white;">@<?= htmlspecialchars($customer['username']) ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #aaa;">Email:</td>
                    <td style="padding: 8px 0; color: white;"><?= htmlspecialchars($customer['email']) ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #aaa;">Phone:</td>
                    <td style="padding: 8px 0; color: white;"><?= htmlspecialchars($customer['phone']) ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #aaa;">Address:</td>
                    <td style="padding: 8px 0; color: white;"><?= htmlspecialchars($customer['address'] ?? 'Not provided') ?></td>
                </tr>
            </table>
        </div>
        
        <div>
            <h4 style="color: var(--blue); margin-bottom: 15px; font-size: 16px;">
                <i class="fas fa-calendar-alt"></i> Booking Information
            </h4>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 8px 0; color: #aaa; width: 120px;">First Booking:</td>
                    <td style="padding: 8px 0; color: white;">
                        <?= $customer['first_booking'] ? date('d M Y', strtotime($customer['first_booking'])) : 'No bookings yet' ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #aaa;">Last Booking:</td>
                    <td style="padding: 8px 0; color: white;">
                        <?= $customer['last_booking'] ? date('d M Y', strtotime($customer['last_booking'])) : 'N/A' ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #aaa;">Status:</td>
                    <td style="padding: 8px 0;">
                        <span class="status-badge status-<?= $status_class ?>"><?= ucfirst($customer['status']) ?></span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #aaa;">Last Login:</td>
                    <td style="padding: 8px 0; color: white;">
                        <?= $customer['last_login'] ? date('d M Y H:i', strtotime($customer['last_login'])) : 'Never' ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Recent Bookings -->
    <div>
        <h4 style="color: var(--blue); margin-bottom: 15px; font-size: 16px;">
            <i class="fas fa-history"></i> Recent Bookings (Last 10)
        </h4>
        <?php if ($bookings_result->num_rows > 0): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--blue);">Booking ID</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--blue);">Room</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--blue);">Check In/Out</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--blue);">Status</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--blue);">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($booking = $bookings_result->fetch_assoc()): 
                        $booking_status = $booking['status'];
                        $status_colors = [
                            'confirmed' => 'success',
                            'pending' => 'warning',
                            'cancelled' => 'danger',
                            'checked_in' => 'info',
                            'checked_out' => 'primary'
                        ];
                        $status_color = $status_colors[$booking_status] ?? 'info';
                    ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);">#BK<?= str_pad($booking['id'], 6, '0', STR_PAD_LEFT) ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <div>
                                <strong style="color: white;">Room <?= $booking['room_number'] ?></strong><br>
                                <small style="color: #aaa;"><?= $booking['room_type'] ?></small>
                            </div>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <div>
                                <div style="color: white;"><?= date('d M Y', strtotime($booking['check_in'])) ?></div>
                                <small style="color: #aaa;">to <?= date('d M Y', strtotime($booking['check_out'])) ?></small>
                            </div>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span class="status-badge status-<?= $status_color ?>"><?= ucfirst($booking_status) ?></span>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); color: white;">
                            <?= formatCurrency($booking['final_price']) ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #aaa; background: rgba(255,255,255,0.05); border-radius: 10px;">
            <i class="fas fa-calendar-times fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i><br>
            No bookings found for this customer.
        </div>
        <?php endif; ?>
        <?php $bookings_stmt->close(); ?>
    </div>

    <!-- Action Buttons -->
    <div style="display: flex; gap: 10px; margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
        <button onclick="editCustomer(<?= $customer['id'] ?>)" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit Customer
        </button>
        <button onclick="sendEmail('<?= htmlspecialchars($customer['email']) ?>')" class="btn btn-success">
            <i class="fas fa-envelope"></i> Send Email
        </button>
        <?php if ($customer['status'] == 'active'): ?>
        <button onclick="updateStatus(<?= $customer['id'] ?>, 'inactive')" class="btn btn-warning">
            <i class="fas fa-user-slash"></i> Deactivate
        </button>
        <?php else: ?>
        <button onclick="updateStatus(<?= $customer['id'] ?>, 'active')" class="btn btn-success">
            <i class="fas fa-user-check"></i> Activate
        </button>
        <?php endif; ?>
        <button onclick="closeModal()" class="btn btn-secondary">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
</div>

<script>
// Update customer status
function updateStatus(customerId, status) {
    if (confirm('Are you sure you want to ' + (status == 'active' ? 'activate' : 'deactivate') + ' this customer?')) {
        showLoading();
        fetch('ajax/update_customer_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id=' + customerId + '&status=' + status
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                alert(data.message);
                closeModal();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            alert('Failed to update status');
        });
    }
}
</script>
<?php
// Close connection
if (isset($conn)) {
    $conn->close();
}
?>