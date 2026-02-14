<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = "Rooms & Suites";
?>
<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2"><i class="fas fa-bed me-2"></i>Our Rooms & Suites</h1>
            <p class="lead">Discover our luxurious accommodations designed for your comfort.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="booking.php" class="btn btn-primary btn-lg">
                <i class="fas fa-calendar-check me-2"></i>Book Now
            </a>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Check-in Date</label>
                    <input type="date" class="form-control" name="check_in" 
                           value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Check-out Date</label>
                    <input type="date" class="form-control" name="check_out" 
                           value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Adults</label>
                    <select class="form-select" name="adults">
                        <option value="1">1 Adult</option>
                        <option value="2" selected>2 Adults</option>
                        <option value="3">3 Adults</option>
                        <option value="4">4 Adults</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Children</label>
                    <select class="form-select" name="children">
                        <option value="0" selected>0 Child</option>
                        <option value="1">1 Child</option>
                        <option value="2">2 Children</option>
                        <option value="3">3 Children</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Room Categories -->
    <div class="row">
        <?php
        $sql = "SELECT rc.*, 
                       (SELECT COUNT(*) FROM rooms r WHERE r.category_id = rc.id AND r.status = 'available') as available_count
                FROM room_categories rc 
                ORDER BY rc.base_price";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
        ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="position-relative">
                    <img src="https://images.unsplash.com/photo-1611892440504-42a792e24d32?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" 
                         class="card-img-top" alt="<?php echo $row['name']; ?>" style="height: 200px; object-fit: cover;">
                    <div class="position-absolute top-0 end-0 m-3">
                        <span class="badge bg-success"><?php echo $row['available_count']; ?> Available</span>
                    </div>
                </div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo $row['name']; ?></h5>
                    <p class="card-text text-muted">
                        <small><?php echo $row['description']; ?></small>
                    </p>
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-1">
                            <i class="fas fa-user me-2 text-primary"></i>
                            <small>Max <?php echo $row['max_capacity']; ?> persons</small>
                        </div>
                        <?php if ($row['amenities']): ?>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-star me-2 text-warning"></i>
                                <small>Amenities included</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="text-primary mb-0"><?php echo formatCurrency($row['base_price']); ?></h4>
                            <small class="text-muted">per night</small>
                        </div>
                        <a href="booking.php?category=<?php echo $row['id']; ?>" class="btn btn-primary">
                            Book Now <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php 
            endwhile;
        else:
        ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No room categories available at the moment.
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>