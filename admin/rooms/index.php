<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'receptionist')) {
    header("Location: ../../login.php");
    exit();
}

$page_title = "Room Management";
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
            <h1 class="h3"><i class="fas fa-bed me-2"></i>Room Management</h1>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add New Room
            </a>
        </div>
        
        <!-- Filter and Search -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <select class="form-select" id="filterStatus">
                            <option value="">All Status</option>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="reserved">Reserved</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select class="form-select" id="filterCategory">
                            <option value="">All Categories</option>
                            <?php
                            $categories = $conn->query("SELECT * FROM room_categories");
                            while ($cat = $categories->fetch_assoc()):
                            ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search rooms..." id="searchInput">
                            <button class="btn btn-outline-secondary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rooms Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Rooms</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Room Number</th>
                                <th>Category</th>
                                <th>Floor</th>
                                <th>View</th>
                                <th>Bed Type</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT r.*, rc.name as category_name, rc.base_price 
                                    FROM rooms r 
                                    JOIN room_categories rc ON r.category_id = rc.id 
                                    ORDER BY r.room_number";
                            $result = $conn->query($sql);
                            $counter = 1;
                            
                            if ($result->num_rows > 0):
                                while ($row = $result->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><strong><?php echo $row['room_number']; ?></strong></td>
                                <td><?php echo $row['category_name']; ?></td>
                                <td><?php echo $row['floor']; ?></td>
                                <td><?php echo ucfirst($row['view_type']); ?></td>
                                <td><?php echo ucfirst($row['bed_type']); ?></td>
                                <td><?php echo formatCurrency($row['base_price']); ?>/night</td>
                                <td><?php echo getStatusBadge($row['status'], 'room'); ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-sm btn-danger delete-room" 
                                                data-id="<?php echo $row['id']; ?>"
                                                data-name="<?php echo $row['room_number']; ?>"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="alert alert-info">
                                        No rooms found. <a href="add.php">Add your first room</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete room <strong id="roomName"></strong>?</p>
                <p class="text-danger"><small>This action cannot be undone!</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirm" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
// Sidebar toggle
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
});

// Delete room
document.querySelectorAll('.delete-room').forEach(button => {
    button.addEventListener('click', function() {
        const roomId = this.getAttribute('data-id');
        const roomName = this.getAttribute('data-name');
        
        document.getElementById('roomName').textContent = roomName;
        document.getElementById('deleteConfirm').href = 'delete.php?id=' + roomId;
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    });
});

// Filter rooms
document.getElementById('filterStatus').addEventListener('change', function() {
    filterRooms();
});

document.getElementById('filterCategory').addEventListener('change', function() {
    filterRooms();
});

document.getElementById('searchInput').addEventListener('keyup', function() {
    filterRooms();
});

function filterRooms() {
    const status = document.getElementById('filterStatus').value;
    const category = document.getElementById('filterCategory').value;
    const search = document.getElementById('searchInput').value.toLowerCase();
    
    document.querySelectorAll('tbody tr').forEach(row => {
        const rowStatus = row.cells[7].textContent.toLowerCase();
        const rowCategory = row.cells[2].textContent.toLowerCase();
        const rowText = row.textContent.toLowerCase();
        
        const statusMatch = !status || rowStatus.includes(status);
        const categoryMatch = !category || rowCategory.includes(category.toLowerCase());
        const searchMatch = !search || rowText.includes(search);
        
        row.style.display = statusMatch && categoryMatch && searchMatch ? '' : 'none';
    });
}
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