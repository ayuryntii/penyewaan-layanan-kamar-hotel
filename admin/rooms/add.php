<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'receptionist')) {
    header("Location: ../../login.php");
    exit();
}

$page_title = "Add New Room";
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $room_number = trim($_POST['room_number']);
    $category_id = intval($_POST['category_id']);
    $floor = trim($_POST['floor']);
    $view_type = $_POST['view_type'];
    $bed_type = $_POST['bed_type'];
    $smoking = isset($_POST['smoking']) ? 1 : 0;
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    
    // Check if room number already exists
    $check_sql = "SELECT id FROM rooms WHERE room_number = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $room_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error = "Room number already exists!";
    } else {
        // Insert room
        $sql = "INSERT INTO rooms (room_number, category_id, floor, view_type, bed_type, smoking, description, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssiss", 
            $room_number, $category_id, $floor, $view_type, $bed_type, $smoking, $description, $status);
        
        if ($stmt->execute()) {
            $room_id = $stmt->insert_id;
            
            // Handle image upload
            if (!empty($_FILES['room_images']['name'][0])) {
                $upload_dir = '../../uploads/rooms/';
                
                // Create directory if not exists
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['room_images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['room_images']['error'][$key] === 0) {
                        $filename = time() . '_' . basename($_FILES['room_images']['name'][$key]);
                        $target_file = $upload_dir . $filename;
                        
                        // Move uploaded file
                        if (move_uploaded_file($tmp_name, $target_file)) {
                            // Insert image record
                            $image_sql = "INSERT INTO room_images (room_id, image_path) VALUES (?, ?)";
                            $image_stmt = $conn->prepare($image_sql);
                            $image_path = 'uploads/rooms/' . $filename;
                            $image_stmt->bind_param("is", $room_id, $image_path);
                            $image_stmt->execute();
                            $image_stmt->close();
                        }
                    }
                }
            }
            
            $stmt->close();
            $_SESSION['success'] = "Room added successfully!";
            header("Location: index.php");
            exit();
        } else {
            $error = "Error adding room: " . $conn->error;
        }
    }
}
?>
<?php include '../sidebar.php'; ?>

<div id="content">
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
        <div class="container-fluid">
            <button class="btn btn-primary" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="navbar-nav ms-auto">
                <div class="dropdown">
                    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?php echo $_SESSION['full_name']; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../../profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3"><i class="fas fa-plus-circle me-2"></i>Add New Room</h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Rooms
            </a>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Room Information</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="room_number" class="form-label">Room Number *</label>
                                    <input type="text" class="form-control" id="room_number" name="room_number" 
                                           placeholder="e.g., 101, 201A" required>
                                    <div class="invalid-feedback">Please enter room number.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">Room Category *</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php
                                        $categories = $conn->query("SELECT * FROM room_categories ORDER BY name");
                                        while ($cat = $categories->fetch_assoc()):
                                        ?>
                                        <option value="<?php echo $cat['id']; ?>">
                                            <?php echo $cat['name']; ?> - <?php echo formatCurrency($cat['base_price']); ?>/night
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select room category.</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="floor" class="form-label">Floor *</label>
                                    <input type="text" class="form-control" id="floor" name="floor" 
                                           placeholder="e.g., 1, 2, 3" required>
                                    <div class="invalid-feedback">Please enter floor number.</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="view_type" class="form-label">View Type</label>
                                    <select class="form-select" id="view_type" name="view_type">
                                        <option value="city">City View</option>
                                        <option value="garden">Garden View</option>
                                        <option value="pool">Pool View</option>
                                        <option value="sea">Sea View</option>
                                        <option value="mountain">Mountain View</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="bed_type" class="form-label">Bed Type</label>
                                    <select class="form-select" id="bed_type" name="bed_type">
                                        <option value="single">Single</option>
                                        <option value="double" selected>Double</option>
                                        <option value="queen">Queen</option>
                                        <option value="king">King</option>
                                        <option value="twin">Twin</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="available" selected>Available</option>
                                        <option value="occupied">Occupied</option>
                                        <option value="reserved">Reserved</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="cleaning">Cleaning</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Options</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="smoking" name="smoking">
                                        <label class="form-check-label" for="smoking">
                                            Smoking Room
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4" placeholder="Room description..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="room_images" class="form-label">Room Images</label>
                                <input type="file" class="form-control" id="room_images" name="room_images[]" 
                                       accept="image/*" multiple>
                                <small class="text-muted">You can select multiple images. Max 5MB per image.</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Room
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-redo me-2"></i>Reset
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Tips</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Room Number</h6>
                            <p class="mb-0">Use a unique room number. Format: Floor + Number (e.g., 101, 202B)</p>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Status</h6>
                            <p class="mb-0">Set status to 'Available' for rooms ready for booking.</p>
                        </div>
                        
                        <div class="alert alert-success">
                            <h6><i class="fas fa-image me-2"></i>Images</h6>
                            <p class="mb-0">Upload clear images of the room from different angles.</p>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Preview</h5>
                    </div>
                    <div class="card-body">
                        <div id="imagePreview" class="text-center">
                            <i class="fas fa-image fa-4x text-muted"></i>
                            <p class="mt-2 text-muted">No images selected</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Sidebar toggle
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
});

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

// Image preview
document.getElementById('room_images').addEventListener('change', function() {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    if (this.files.length > 0) {
        for (let i = 0; i < Math.min(this.files.length, 3); i++) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'img-thumbnail m-1';
                img.style.width = '100px';
                img.style.height = '100px';
                img.style.objectFit = 'cover';
                preview.appendChild(img);
            }
            reader.readAsDataURL(this.files[i]);
        }
        
        if (this.files.length > 3) {
            const more = document.createElement('p');
            more.className = 'text-muted mt-2';
            more.textContent = '+ ' + (this.files.length - 3) + ' more images';
            preview.appendChild(more);
        }
    } else {
        preview.innerHTML = '<i class="fas fa-image fa-4x text-muted"></i><p class="mt-2 text-muted">No images selected</p>';
    }
});
</script>

<style>
#sidebar {
    min-width: 250px;
    max-width: 250px;
    min-height: 100vh;
    background: #343a40;
    color: #fff;
    transition: all 0.3s;
    position: fixed;
    z-index: 1000;
}

#content {
    width: calc(100% - 250px);
    margin-left: 250px;
    transition: all 0.3s;
}

#sidebar.collapsed {
    margin-left: -250px;
}

#sidebar.collapsed + #content {
    width: 100%;
    margin-left: 0;
}

@media (max-width: 768px) {
    #sidebar {
        margin-left: -250px;
    }
    
    #sidebar.collapsed {
        margin-left: 0;
    }
    
    #content {
        width: 100%;
        margin-left: 0;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>