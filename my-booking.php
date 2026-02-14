<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = "My Bookings";
?>
<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2"><i class="fas fa-calendar-alt me-2"></i>My Bookings</h1>
            <p class="lead">View and manage your hotel bookings.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="booking.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>New Booking
            </a>
        </div>
    </div>
    
    <!-- Bookings List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Booking History</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Booking ID</th>
                            <th>Room</th>
                            <th>Dates</th>
                            <th>Guests</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $user_id = $_SESSION['user_id'];
                        $sql = "SELECT b.*, r.room_number, rc.name as room_type 
                                FROM bookings b 
                                JOIN rooms r ON b.room_id = r.id 
                                JOIN room_categories rc ON r.category_id = rc.id 
                                WHERE b.user_id = ? 
                                ORDER BY b.created_at DESC";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><strong><?php echo $row['booking_code']; ?></strong></td>
                            <td>
                                <?php echo $row['room_type']; ?><br>
                                <small class="text-muted">Room <?php echo $row['room_number']; ?></small>
                            </td>
                            <td>
                                <?php echo date('d M', strtotime($row['check_in'])); ?> - 
                                <?php echo date('d M Y', strtotime($row['check_out'])); ?><br>
                                <small class="text-muted"><?php echo $row['total_nights']; ?> nights</small>
                            </td>
                            <td>
                                <?php echo $row['adults']; ?> Adult<?php echo $row['adults'] > 1 ? 's' : ''; ?>
                                <?php if ($row['children'] > 0): ?>
                                    <br><small class="text-muted">+ <?php echo $row['children']; ?> Child<?php echo $row['children'] > 1 ? 'ren' : ''; ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatCurrency($row['final_price']); ?></td>
                            <td><?php echo getStatusBadge($row['booking_status'], 'booking'); ?></td>
                            <td><?php echo getStatusBadge($row['payment_status'], 'payment'); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="booking-details.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($row['booking_status'] == 'pending'): ?>
                                        <a href="cancel-booking.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-warning" title="Cancel Booking">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($row['payment_status'] == 'pending'): ?>
                                        <a href="payment.php?booking_id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-success" title="Make Payment">
                                            <i class="fas fa-credit-card"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>You haven't made any bookings yet.
                                    <a href="rooms.php" class="alert-link">Book a room now!</a>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Booking Status Legend -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Booking Status Legend</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <span class="badge bg-warning">Pending</span>
                    <small class="text-muted"> - Booking is being processed</small>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="badge bg-info">Confirmed</span>
                    <small class="text-muted"> - Booking is confirmed</small>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="badge bg-success">Checked In</span>
                    <small class="text-muted"> - Currently staying</small>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="badge bg-secondary">Checked Out</span>
                    <small class="text-muted"> - Stay completed</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>