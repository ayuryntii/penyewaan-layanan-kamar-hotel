<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'booking.php';
    header("Location: login.php");
    exit();
}

$page_title = "Book a Room";
$error = '';
$success = '';

// Handle booking form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $room_id = intval($_POST['room_id']);
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $adults = intval($_POST['adults']);
    $children = intval($_POST['children']);
    $special_requests = trim($_POST['special_requests']);
    
    // Validate dates
    if (strtotime($check_out) <= strtotime($check_in)) {
        $error = "Check-out date must be after check-in date";
    } else {
        // Calculate nights
        $nights = (strtotime($check_out) - strtotime($check_in)) / (60 * 60 * 24);
        
        // Get room details
        $room_sql = "SELECT r.*, rc.base_price FROM rooms r 
                    JOIN room_categories rc ON r.category_id = rc.id 
                    WHERE r.id = ?";
        $room_stmt = $conn->prepare($room_sql);
        $room_stmt->bind_param("i", $room_id);
        $room_stmt->execute();
        $room_result = $room_stmt->get_result();
        
        if ($room_result->num_rows > 0) {
            $room = $room_result->fetch_assoc();
            
            // Check room availability
            $availability_sql = "SELECT id FROM bookings 
                                WHERE room_id = ? 
                                AND booking_status IN ('confirmed', 'checked_in')
                                AND (
                                    (check_in <= ? AND check_out > ?) OR
                                    (check_in < ? AND check_out >= ?) OR
                                    (check_in >= ? AND check_out <= ?)
                                )";
            $availability_stmt = $conn->prepare($availability_sql);
            $availability_stmt->bind_param("isssssss", $room_id, $check_out, $check_in, $check_out, $check_in, $check_in, $check_out);
            $availability_stmt->execute();
            $availability_result = $availability_stmt->get_result();
            
            if ($availability_result->num_rows > 0) {
                $error = "Room is not available for the selected dates";
            } else {
                // Calculate total price
                $base_price = $room['base_price'] * $nights;
                $extra_adults = max(0, $adults - 2) * 100000 * $nights;
                $extra_children = $children * 50000 * $nights;
                $subtotal = $base_price + $extra_adults + $extra_children;
                $tax = $subtotal * ($tax_percentage / 100);
                $final_price = $subtotal + $tax;
                
                // Generate booking code
                $booking_code = 'BOOK' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Create booking
                $booking_sql = "INSERT INTO bookings 
                                (booking_code, user_id, room_id, check_in, check_out, total_nights, 
                                 adults, children, total_price, final_price, special_requests) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $booking_stmt = $conn->prepare($booking_sql);
                $booking_stmt->bind_param("siissiiiidd", 
                    $booking_code, $_SESSION['user_id'], $room_id, $check_in, $check_out, 
                    $nights, $adults, $children, $subtotal, $final_price, $special_requests);
                
                if ($booking_stmt->execute()) {
                    $booking_id = $booking_stmt->insert_id;
                    
                    // Update room status
                    $update_room_sql = "UPDATE rooms SET status = 'reserved' WHERE id = ?";
                    $update_room_stmt = $conn->prepare($update_room_sql);
                    $update_room_stmt->bind_param("i", $room_id);
                    $update_room_stmt->execute();
                    
                    $booking_stmt->close();
                    $update_room_stmt->close();
                    
                    $_SESSION['success'] = "Booking successful! Your booking code is: " . $booking_code;
                    header("Location: my-bookings.php");
                    exit();
                } else {
                    $error = "Error creating booking: " . $conn->error;
                }
            }
        } else {
            $error = "Room not found";
        }
    }
}

// Get room details if room_id is provided in GET
$room_id = isset($_GET['room']) ? intval($_GET['room']) : 0;
$room = null;

if ($room_id > 0) {
    $room_sql = "SELECT r.*, rc.name as category_name, rc.base_price 
                FROM rooms r 
                JOIN room_categories rc ON r.category_id = rc.id 
                WHERE r.id = ? AND r.status = 'available'";
    $room_stmt = $conn->prepare($room_sql);
    $room_stmt->bind_param("i", $room_id);
    $room_stmt->execute();
    $room_result = $room_stmt->get_result();
    
    if ($room_result->num_rows > 0) {
        $room = $room_result->fetch_assoc();
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Book Your Stay</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($room): ?>
                        <!-- Room Selected View -->
                        <div class="alert alert-info">
                            <h5><i class="fas fa-bed me-2"></i>Selected Room</h5>
                            <div class="row mt-3">
                                <div class="col-md-3">
                                    <img src="https://images.unsplash.com/photo-1611892440504-42a792e24d32?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" 
                                         class="img-fluid rounded" alt="Room">
                                </div>
                                <div class="col-md-9">
                                    <h5><?php echo $room['category_name']; ?> - Room <?php echo $room['room_number']; ?></h5>
                                    <p class="mb-1">
                                        <i class="fas fa-bed"></i> <?php echo ucfirst($room['bed_type']); ?> Bed |
                                        <i class="fas fa-building"></i> Floor <?php echo $room['floor']; ?> |
                                        <i class="fas fa-eye"></i> <?php echo ucfirst($room['view_type']); ?> View
                                    </p>
                                    <p class="mb-1">
                                        <strong>Price:</strong> <?php echo formatCurrency($room['base_price']); ?> per night
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Room Selection -->
                        <div class="mb-4">
                            <h5><i class="fas fa-search me-2"></i>Select a Room</h5>
                            <div class="row">
                                <?php
                                $rooms_sql = "SELECT r.*, rc.name as category_name, rc.base_price 
                                            FROM rooms r 
                                            JOIN room_categories rc ON r.category_id = rc.id 
                                            WHERE r.status = 'available' 
                                            LIMIT 3";
                                $rooms_result = $conn->query($rooms_sql);
                                
                                if ($rooms_result->num_rows > 0):
                                    while ($room_item = $rooms_result->fetch_assoc()):
                                ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 room-card" data-room-id="<?php echo $room_item['id']; ?>">
                                        <img src="https://images.unsplash.com/photo-1611892440504-42a792e24d32?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" 
                                             class="card-img-top" alt="Room" style="height: 150px; object-fit: cover;">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo $room_item['category_name']; ?></h6>
                                            <p class="card-text small mb-2">
                                                Room <?php echo $room_item['room_number']; ?><br>
                                                <?php echo formatCurrency($room_item['base_price']); ?>/night
                                            </p>
                                            <button class="btn btn-sm btn-outline-primary select-room" 
                                                    data-room-id="<?php echo $room_item['id']; ?>">
                                                Select Room
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        No rooms available for booking at the moment.
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Booking Form -->
                    <form method="POST" action="" id="bookingForm" class="needs-validation" novalidate>
                        <input type="hidden" name="room_id" id="room_id" value="<?php echo $room ? $room['id'] : ''; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="check_in" class="form-label">Check-in Date *</label>
                                <input type="date" class="form-control" id="check_in" name="check_in" 
                                       value="<?php echo date('Y-m-d'); ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Please select check-in date.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="check_out" class="form-label">Check-out Date *</label>
                                <input type="date" class="form-control" id="check_out" name="check_out" 
                                       value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                <div class="invalid-feedback">Please select check-out date.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="adults" class="form-label">Adults *</label>
                                <select class="form-select" id="adults" name="adults" required>
                                    <option value="1">1 Adult</option>
                                    <option value="2" selected>2 Adults</option>
                                    <option value="3">3 Adults</option>
                                    <option value="4">4 Adults</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="children" class="form-label">Children</label>
                                <select class="form-select" id="children" name="children">
                                    <option value="0" selected>0 Child</option>
                                    <option value="1">1 Child</option>
                                    <option value="2">2 Children</option>
                                    <option value="3">3 Children</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="special_requests" class="form-label">Special Requests</label>
                            <textarea class="form-control" id="special_requests" name="special_requests" 
                                      rows="3" placeholder="Any special requests or requirements..."></textarea>
                        </div>
                        
                        <!-- Price Calculation -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Price Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-6">Room Price:</div>
                                    <div class="col-6 text-end" id="roomPrice">Rp 0</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6">Extra Adults:</div>
                                    <div class="col-6 text-end" id="extraAdults">Rp 0</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6">Extra Children:</div>
                                    <div class="col-6 text-end" id="extraChildren">Rp 0</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6">Tax (<?php echo $tax_percentage; ?>%):</div>
                                    <div class="col-6 text-end" id="taxAmount">Rp 0</div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-6"><strong>Total Price:</strong></div>
                                    <div class="col-6 text-end"><strong id="totalPrice">Rp 0</strong></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" 
                                    <?php echo !$room ? 'disabled' : ''; ?> id="submitBtn">
                                <i class="fas fa-check-circle me-2"></i>Confirm Booking
                            </button>
                            <a href="rooms.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Rooms
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Room selection
document.querySelectorAll('.select-room').forEach(button => {
    button.addEventListener('click', function() {
        const roomId = this.getAttribute('data-room-id');
        document.getElementById('room_id').value = roomId;
        
        // Update form
        document.getElementById('submitBtn').disabled = false;
        
        // Update selected room UI
        document.querySelectorAll('.room-card').forEach(card => {
            card.classList.remove('border-primary', 'border-2');
        });
        this.closest('.room-card').classList.add('border-primary', 'border-2');
        
        // Trigger price calculation
        calculatePrice();
    });
});

// Date validation and price calculation
document.getElementById('check_in').addEventListener('change', function() {
    const checkOut = document.getElementById('check_out');
    checkOut.min = this.value;
    if (checkOut.value < this.value) {
        checkOut.value = this.value;
    }
    calculatePrice();
});

document.getElementById('check_out').addEventListener('change', calculatePrice);
document.getElementById('adults').addEventListener('change', calculatePrice);
document.getElementById('children').addEventListener('change', calculatePrice);

function calculatePrice() {
    const checkIn = new Date(document.getElementById('check_in').value);
    const checkOut = new Date(document.getElementById('check_out').value);
    const adults = parseInt(document.getElementById('adults').value);
    const children = parseInt(document.getElementById('children').value);
    const roomId = document.getElementById('room_id').value;
    
    if (!roomId || isNaN(checkIn) || isNaN(checkOut)) {
        return;
    }
    
    // Calculate nights
    const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
    
    if (nights <= 0) {
        alert('Check-out date must be after check-in date');
        return;
    }
    
    // Get room price via AJAX
    fetch('ajax/get-room-price.php?room_id=' + roomId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const roomPrice = data.price;
                const basePrice = roomPrice * nights;
                const extraAdults = Math.max(0, adults - 2) * 100000 * nights;
                const extraChildren = children * 50000 * nights;
                const subtotal = basePrice + extraAdults + extraChildren;
                const tax = subtotal * (<?php echo $tax_percentage; ?> / 100);
                const totalPrice = subtotal + tax;
                
                // Update display
                document.getElementById('roomPrice').textContent = formatCurrency(basePrice);
                document.getElementById('extraAdults').textContent = formatCurrency(extraAdults);
                document.getElementById('extraChildren').textContent = formatCurrency(extraChildren);
                document.getElementById('taxAmount').textContent = formatCurrency(tax);
                document.getElementById('totalPrice').textContent = formatCurrency(totalPrice);
            }
        });
}

function formatCurrency(amount) {
    return 'Rp ' + amount.toLocaleString('id-ID');
}

// Form validation
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Auto-calculate if room is preselected
<?php if ($room): ?>
document.addEventListener('DOMContentLoaded', function() {
    calculatePrice();
});
<?php endif; ?>
</script>

<style>
.room-card {
    cursor: pointer;
    transition: all 0.3s;
}

.room-card:hover {
    transform: translateY(-5px);
}

.border-2 {
    border-width: 2px !important;
}
</style>

<?php include 'includes/footer.php'; ?>