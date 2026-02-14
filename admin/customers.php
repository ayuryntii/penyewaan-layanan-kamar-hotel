<?php
// admin/customers.php - CUSTOMER MANAGEMENT FULL FUNCTIONALITY
require_once '../includes/config.php';
requireAdmin();

$page_title = 'Customer Management';

// Handle delete customer
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Check if customer has bookings
    $check_sql = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $booking_count = $check_result->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($booking_count > 0) {
        $_SESSION['error'] = 'Cannot delete customer. There are ' . $booking_count . ' booking(s) associated with this customer.';
        header('Location: customers.php');
        exit();
    }
    
    // Delete customer
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Customer deleted successfully!';
    } else {
        $_SESSION['error'] = 'Failed to delete customer.';
    }
    $stmt->close();
    header('Location: customers.php');
    exit();
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_ids = $_POST['selected_customers'] ?? [];
    
    if (empty($selected_ids)) {
        $_SESSION['error'] = 'No customers selected.';
        header('Location: customers.php');
        exit();
    }
    
    $ids = implode(',', array_map('intval', $selected_ids));
    
    switch ($action) {
        case 'activate':
            $sql = "UPDATE users SET status = 'active' WHERE id IN ($ids) AND role = 'customer'";
            $message = 'Selected customers activated successfully!';
            break;
            
        case 'deactivate':
            $sql = "UPDATE users SET status = 'inactive' WHERE id IN ($ids) AND role = 'customer'";
            $message = 'Selected customers deactivated successfully!';
            break;
            
        case 'delete':
            // Check if any selected customers have bookings
            $check_sql = "SELECT COUNT(*) as count FROM bookings WHERE user_id IN ($ids)";
            $check_result = $conn->query($check_sql);
            $booking_count = $check_result->fetch_assoc()['count'];
            
            if ($booking_count > 0) {
                $_SESSION['error'] = 'Cannot delete customers. Some have bookings.';
                header('Location: customers.php');
                exit();
            }
            
            $sql = "DELETE FROM users WHERE id IN ($ids) AND role = 'customer'";
            $message = 'Selected customers deleted successfully!';
            break;
            
        default:
            $_SESSION['error'] = 'Invalid action.';
            header('Location: customers.php');
            exit();
    }
    
    if ($conn->query($sql)) {
        $_SESSION['success'] = $message;
    } else {
        $_SESSION['error'] = 'Failed to perform bulk action: ' . $conn->error;
    }
    
    header('Location: customers.php');
    exit();
}

// Handle export requests
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    
    // Get all customers data
    $query = "SELECT u.id, u.full_name, u.username, u.email, u.phone, u.status, u.created_at,
                     COUNT(b.id) as total_bookings,
                     SUM(CASE WHEN b.payment_status = 'paid' THEN b.final_price ELSE 0 END) as total_spent
              FROM users u 
              LEFT JOIN bookings b ON u.id = b.user_id 
              WHERE u.role = 'customer' 
              GROUP BY u.id 
              ORDER BY u.created_at DESC";
    $result = $conn->query($query);
    
    if ($type == 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Customer ID', 'Name', 'Username', 'Email', 'Phone', 'Status', 'Bookings', 'Total Spent', 'Registered']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                'CUST' . str_pad($row['id'], 5, '0', STR_PAD_LEFT),
                $row['full_name'],
                $row['username'],
                $row['email'],
                $row['phone'],
                ucfirst($row['status']),
                $row['total_bookings'],
                formatCurrency($row['total_spent'] ?? 0),
                date('d M Y', strtotime($row['created_at']))
            ]);
        }
        fclose($output);
        exit();
    } elseif ($type == 'pdf') {
        // Simple HTML-to-PDF fallback
        echo "<html><head><title>Customer Report</title></head><body>";
        echo "<h2>Customer Report - " . date('d M Y') . "</h2>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Customer ID</th><th>Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Status</th><th>Bookings</th><th>Total Spent</th><th>Registered</th></tr>";
        
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>CUST" . str_pad($row['id'], 5, '0', STR_PAD_LEFT) . "</td>";
            echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
            echo "<td>" . ucfirst($row['status']) . "</td>";
            echo "<td>" . $row['total_bookings'] . "</td>";
            echo "<td>" . formatCurrency($row['total_spent'] ?? 0) . "</td>";
            echo "<td>" . date('d M Y', strtotime($row['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<script>window.print();</script>";
        echo "</body></html>";
        exit();
    }
}

// Function to handle AJAX requests directly (SEMUA DALAM SATU FILE)
if (isset($_GET['ajax'])) {
    $ajax_action = $_GET['ajax'];
    
    switch ($ajax_action) {
        case 'get_customer':
            getCustomer();
            break;
        case 'edit_customer':
            editCustomerForm();
            break;
        case 'save_customer':
            saveCustomer();
            break;
        case 'update_status':
            updateCustomerStatus();
            break;
    }
    exit();
}

// Function to get customer details
function getCustomer() {
    global $conn;
    
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
    $status_class = $customer['status'] == 'active' ? 'success' : ($customer['status'] == 'inactive' ? 'warning' : ($customer['status'] == 'suspended' ? 'danger' : 'info'));
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
                            // DIPERBAIKI: Gunakan booking_status bukan status
                            $booking_status = $booking['booking_status'] ?? 'pending';
                            $status_colors = [
                                'confirmed' => 'success',
                                'pending' => 'warning',
                                'cancelled' => 'danger',
                                'checked_in' => 'info',
                                'checked_out' => 'primary',
                                'no_show' => 'danger'
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
                                <span class="status-badge status-<?= $status_color ?>"><?= ucfirst(str_replace('_', ' ', $booking_status)) ?></span>
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
    <?php
}

// Function to show edit customer form
function editCustomerForm() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        echo '<div class="alert alert-danger">Invalid customer ID</div>';
        exit();
    }
    
    // Get customer data
    $sql = "SELECT * FROM users WHERE id = ? AND role = 'customer'";
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
    ?>
    
    <div class="edit-customer-form">
        <h4 style="color: var(--blue); margin-bottom: 20px; font-size: 18px;">
            <i class="fas fa-edit"></i> Edit Customer: <?= htmlspecialchars($customer['full_name']) ?>
        </h4>
        
        <form id="editCustomerForm">
            <input type="hidden" name="id" value="<?= $customer['id'] ?>">
            <input type="hidden" name="ajax_action" value="save_customer">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required 
                           value="<?= htmlspecialchars($customer['full_name']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" required 
                           value="<?= htmlspecialchars($customer['username']) ?>">
                    <small style="color: #aaa;">Unique username for login</small>
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" required 
                       value="<?= htmlspecialchars($customer['email']) ?>">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label class="form-label">Phone Number *</label>
                    <input type="text" name="phone" class="form-control" required 
                           value="<?= htmlspecialchars($customer['phone']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-control" required>
                        <option value="active" <?= $customer['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $customer['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $customer['status'] == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
            </div>
            
            <!-- Password Reset Section -->
            <div style="background: rgba(76, 201, 240, 0.1); padding: 20px; border-radius: 10px; margin: 20px 0;">
                <h5 style="color: var(--blue); margin-bottom: 15px; font-size: 16px;">
                    <i class="fas fa-key"></i> Password Management
                </h5>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" id="password" class="form-control" 
                           placeholder="Leave empty to keep current password">
                    <small style="color: #aaa;">Minimum 6 characters</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                           placeholder="Confirm new password">
                    <div id="passwordMatch" style="display: none; color: #ef233c; font-size: 12px; margin-top: 5px;">
                        <i class="fas fa-exclamation-circle"></i> Passwords do not match
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div style="display: flex; gap: 10px; margin-top: 30px;">
                <button type="button" onclick="saveCustomerForm()" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Customer
                </button>
                <button type="button" onclick="viewCustomer(<?= $customer['id'] ?>)" class="btn btn-secondary">
                    <i class="fas fa-eye"></i> View Details
                </button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
    
    <script>
    // Password validation
    document.getElementById('password').addEventListener('input', validatePassword);
    document.getElementById('confirm_password').addEventListener('input', validatePassword);
    
    function validatePassword() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const matchElement = document.getElementById('passwordMatch');
        
        if (password && confirmPassword && password !== confirmPassword) {
            matchElement.style.display = 'block';
            return false;
        } else {
            matchElement.style.display = 'none';
            return true;
        }
    }
    </script>
    <?php
}

// Function to save customer - SUDAH DIPERBAIKI
function saveCustomer() {
    global $conn;
    
    header('Content-Type: application/json');
    
    $id = intval($_POST['id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    }
    
    // SESUAIKAN DENGAN DATABASE (active, inactive, suspended)
    if (!in_array($status, ['active', 'inactive', 'suspended'])) {
        $status = 'active';
    }
    
    // Check if username already exists (for other users)
    if ($id > 0) {
        $check_sql = "SELECT id FROM users WHERE username = ? AND id != ? AND role = 'customer'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $username, $id);
    } else {
        $check_sql = "SELECT id FROM users WHERE username = ? AND role = 'customer'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $username);
    }
    
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = 'Username already exists';
    }
    $check_stmt->close();
    
    // Check if email already exists (for other users)
    if ($id > 0) {
        $check_sql = "SELECT id FROM users WHERE email = ? AND id != ? AND role = 'customer'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $id);
    } else {
        $check_sql = "SELECT id FROM users WHERE email = ? AND role = 'customer'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
    }
    
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = 'Email already exists';
    }
    $check_stmt->close();
    
    // Password validation
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
        exit();
    }
    
    // Prepare SQL statement - SESUAIKAN DENGAN DATABASE
    if ($id > 0) {
        // Update existing customer
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, status = ?, password = ? WHERE id = ? AND role = 'customer'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $full_name, $username, $email, $phone, $status, $hashed_password, $id);
        } else {
            $sql = "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, status = ? WHERE id = ? AND role = 'customer'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $full_name, $username, $email, $phone, $status, $id);
        }
    } else {
        // Insert new customer
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Password is required for new customers']);
            exit();
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (full_name, username, email, phone, status, password, role, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'customer', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $full_name, $username, $email, $phone, $status, $hashed_password);
    }
    
    // Execute query
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => $id > 0 ? 'Customer updated successfully' : 'Customer added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
}

// Function to update customer status
function updateCustomerStatus() {
    global $conn;
    
    header('Content-Type: application/json');
    
    $id = intval($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
        exit();
    }
    
    if (!in_array($status, ['active', 'inactive', 'suspended'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    // Update status
    $sql = "UPDATE users SET status = ? WHERE id = ? AND role = 'customer'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Customer status updated to ' . $status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $conn->error]);
    }
    
    $stmt->close();
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark-bg);
            color: var(--light);
            overflow-x: hidden;
        }

        .admin-wrapper {
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

        .card-filters {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .form-control {
            width: 250px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(76, 201, 240, 0.15);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z' fill='%23ccc'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px;
        }

        .card-body {
            padding: 25px;
        }

        /* === Stats === */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(76, 201, 240, 0.1);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .stat-icon.primary { background: rgba(76, 201, 240, 0.2); color: var(--blue); }
        .stat-icon.success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .stat-icon.warning { background: rgba(243, 156, 18, 0.2); color: #f39c12; }
        .stat-icon.danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .stat-icon.purple { background: rgba(155, 89, 182, 0.2); color: #9b59b6; }

        .stat-info h3 {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: white;
        }

        .stat-info p {
            color: #aaa;
            font-size: 0.9rem;
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

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--blue);
            color: var(--blue);
        }

        .btn-danger {
            background: #ef233c;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-primary:hover {
            background: #3abde0;
            transform: translateY(-1px);
        }

        .btn-secondary:hover {
            background: rgba(76, 201, 240, 0.1);
            transform: translateY(-1px);
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: rgba(76, 201, 240, 0.2);
            color: var(--blue);
            display: inline-block;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .status-warning { background: rgba(243, 156, 18, 0.2); color: #f39c12; }
        .status-danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .status-info { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        .status-purple { background: rgba(155, 89, 182, 0.2); color: #9b59b6; }

        /* === Modal === */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 25px;
        }

        /* === User Menu in Header === */
        .user-menu-header {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(10, 25, 47, 0.8);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            cursor: pointer;
            position: relative;
        }

        .user-menu-header:hover {
            background: rgba(76, 201, 240, 0.1);
        }

        .user-menu-header .user-avatar {
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

        .user-menu-header .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-menu-header .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-menu-header .user-role {
            font-size: 0.8rem;
            color: #aaa;
        }

        .logout-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--card-bg);
            border: 1px solid rgba(76, 201, 240, 0.1);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
        }

        .logout-menu.show {
            display: block;
        }

        .logout-menu a {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            font-weight: 500;
        }

        .logout-menu a:hover {
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
        }

        /* === Bulk Actions === */
        .bulk-actions {
            display: none;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            background: rgba(76, 201, 240, 0.1);
            border-bottom: 1px solid rgba(76, 201, 240, 0.2);
        }

        .bulk-actions.show {
            display: flex;
        }

        /* === Checkbox === */
        .select-all-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .customer-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* === Loading Overlay === */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(76, 201, 240, 0.3);
            border-top-color: var(--blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* === Alert Messages === */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
            border-color: rgba(46, 204, 113, 0.3);
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border-color: rgba(231, 76, 60, 0.3);
        }

        .alert-info {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            border-color: rgba(52, 152, 219, 0.3);
        }

        .alert-warning {
            background: rgba(243, 156, 18, 0.2);
            color: #f39c12;
            border-color: rgba(243, 156, 18, 0.3);
        }

        /* === Table Styles === */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            color: var(--blue);
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: #ccc;
            font-size: 14px;
        }

        tr:hover td {
            background: rgba(76, 201, 240, 0.05);
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
            .card-filters {
                flex-direction: column;
                align-items: stretch;
            }
            .form-control {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 12px;
            }
            
            .btn {
                padding: 8px 15px;
                font-size: 12px;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }

        /* === Form Styles === */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: white;
            font-weight: 500;
        }

        /* === Add Customer Button === */
        .add-customer-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: var(--blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy);
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(76, 201, 240, 0.3);
            cursor: pointer;
            transition: all 0.3s;
            z-index: 100;
        }

        .add-customer-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(76, 201, 240, 0.4);
        }

        /* === Empty State === */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* === Pagination === */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .page-link {
            padding: 8px 16px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 6px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .page-link:hover {
            background: rgba(76, 201, 240, 0.1);
            border-color: var(--blue);
        }

        .page-link.active {
            background: var(--blue);
            color: var(--navy);
            border-color: var(--blue);
        }
    </style>
</head>
<body>

    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-hotel"></i>
                </div>
                <div class="sidebar-title">
                    <h3><?= htmlspecialchars($hotel_name) ?></h3>
                    <p>Admin Dashboard</p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">ROOM MANAGEMENT</p>
                    <a href="rooms.php" class="nav-item">
                        <i class="fas fa-bed"></i>
                        <span>All Rooms</span>
                    </a>
                    <a href="rooms.php?action=add" class="nav-item">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add New Room</span>
                    </a>
                    <a href="rooms.php?action=categories" class="nav-item">
                        <i class="fas fa-tags"></i>
                        <span>Room Categories</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">BOOKINGS</p>
                    <a href="bookings.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>All Bookings</span>
                    </a>
                    <a href="bookings.php?action=add" class="nav-item">
                        <i class="fas fa-plus"></i>
                        <span>New Booking</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">CUSTOMERS</p>
                    <a href="customers.php" class="nav-item active">
                        <i class="fas fa-users"></i>
                        <span>All Customers</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">FINANCE</p>
                    <a href="payments.php" class="nav-item">
                        <i class="fas fa-credit-card"></i>
                        <span>Payments</span>
                    </a>
                    <a href="reports.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">SERVICES</p>
                    <a href="services.php" class="nav-item">
                        <i class="fas fa-concierge-bell"></i>
                        <span>Hotel Services</span>
                    </a>
                    <a href="staff.php" class="nav-item">
                        <i class="fas fa-user-tie"></i>
                        <span>Staff Management</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">SETTINGS</p>
                    <a href="settings.php" class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>System Settings</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
                        <div class="user-role"><?= ucfirst($_SESSION['role'] ?? 'admin') ?></div>
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
                        Last Login: <?= date('d M Y H:i', strtotime($_SESSION['last_login'] ?? date('Y-m-d H:i:s'))) ?>
                    </div>
                    
                    <!-- User Menu with Logout -->
                    <div class="user-menu-header" id="userMenuHeader">
                        <div class="user-avatar">
                            <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
                            <div class="user-role"><?= ucfirst($_SESSION['role'] ?? 'admin') ?></div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: #aaa;"></i>
                    </div>
                    
                    <!-- Logout Menu -->
                    <div class="logout-menu" id="logoutMenu">
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </header>

            <div class="content-area">
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Loading Overlay -->
                <div class="loading-overlay" id="loadingOverlay">
                    <div class="spinner"></div>
                </div>

                <!-- Page Header -->
                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <h2 style="font-size: 24px; font-weight: 600; margin: 0;">Customer Management</h2>
                        <p style="color: #aaa; margin-top: 5px;">Manage all hotel customers</p>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <div class="export-buttons" style="display: flex; gap: 10px;">
                            <a href="?export=csv" class="btn btn-secondary">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </a>
                            <a href="?export=pdf" class="btn btn-secondary">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Customer Statistics -->
                <?php
                // Get statistics
                $total_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch_assoc()['count'];
                $active_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND status = 'active'")->fetch_assoc()['count'];
                $inactive_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND status = 'inactive'")->fetch_assoc()['count'];
                $new_customers_month = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'];
                $total_revenue = $conn->query("SELECT SUM(final_price) as total FROM bookings WHERE payment_status = 'paid'")->fetch_assoc()['total'] ?? 0;
                ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $total_customers ?></h3>
                            <p>Total Customers</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $active_customers ?></h3>
                            <p>Active Customers</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $new_customers_month ?></h3>
                            <p>New This Month</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= formatCurrency($total_revenue) ?></h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>
                </div>

                <!-- Bulk Actions Bar -->
                <div class="bulk-actions" id="bulkActions">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="selectAll" class="select-all-checkbox">
                        <span id="selectedCount">0 customers selected</span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <select id="bulkActionSelect" class="form-control" style="width: 150px;">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button onclick="applyBulkAction()" class="btn btn-primary">Apply</button>
                        <button onclick="hideBulkActions()" class="btn btn-secondary">Cancel</button>
                    </div>
                </div>

                <!-- Customers Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Customers</h3>
                        <div class="card-filters">
                            <input type="text" id="searchCustomer" class="form-control" placeholder="Search customers...">
                            <select id="filterStatus" class="form-control" style="width: 150px;">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            <button onclick="showBulkActions()" class="btn btn-secondary">
                                <i class="fas fa-tasks"></i> Bulk Actions
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <form id="bulkForm" method="POST" style="display: none;">
                                <input type="hidden" name="bulk_action" id="bulkActionInput" value="">
                                <input type="hidden" name="selected_customers[]" id="selectedCustomersInput" value="">
                            </form>
                            
                            <table style="width: 100%; border-collapse: collapse;" id="customersTable">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;"></th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Customer ID</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Name</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Email</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Phone</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Status</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Bookings</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Total Spent</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Registered</th>
                                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get all customers with booking statistics
                                    $query = "SELECT u.*, 
                                             COUNT(b.id) as total_bookings,
                                             SUM(CASE WHEN b.payment_status = 'paid' THEN b.final_price ELSE 0 END) as total_spent
                                             FROM users u 
                                             LEFT JOIN bookings b ON u.id = b.user_id 
                                             WHERE u.role = 'customer' 
                                             GROUP BY u.id 
                                             ORDER BY u.created_at DESC";
                                    $result = $conn->query($query);
                                    
                                    if ($result->num_rows > 0):
                                        while ($customer = $result->fetch_assoc()) {
                                            $status_class = $customer['status'] == 'active' ? 'success' : ($customer['status'] == 'inactive' ? 'warning' : ($customer['status'] == 'suspended' ? 'danger' : 'info'));
                                    ?>
                                    <tr data-status="<?= $customer['status'] ?>" data-search="<?= strtolower(htmlspecialchars($customer['full_name'] . ' ' . $customer['email'] . ' ' . $customer['phone'])) ?>">
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <input type="checkbox" class="customer-checkbox" name="selected_customers[]" value="<?= $customer['id'] ?>" style="display: none;">
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <strong style="color: var(--blue);">CUST<?= str_pad($customer['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                                    <?= strtoupper(substr($customer['full_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <strong style="color: white;"><?= htmlspecialchars($customer['full_name']) ?></strong><br>
                                                    <small style="color: #aaa;">@<?= htmlspecialchars($customer['username']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <a href="mailto:<?= htmlspecialchars($customer['email']) ?>" style="color: #4cc9f0; text-decoration: none;">
                                                <?= htmlspecialchars($customer['email']) ?>
                                            </a>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <?= htmlspecialchars($customer['phone']) ?>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <span class="status-badge status-<?= $status_class ?>"><?= ucfirst($customer['status']) ?></span>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <span class="badge"><?= $customer['total_bookings'] ?> bookings</span>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <strong style="color: white;"><?= formatCurrency($customer['total_spent'] ?? 0) ?></strong>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <?= date('d M Y', strtotime($customer['created_at'])) ?>
                                        </td>
                                        <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <div style="display: flex; gap: 5px;">
                                                <button onclick="viewCustomer(<?= $customer['id'] ?>)" class="btn btn-sm btn-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="editCustomer(<?= $customer['id'] ?>)" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="sendEmail('<?= htmlspecialchars($customer['email']) ?>')" class="btn btn-sm btn-success" title="Send Email">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                                <?php if ($customer['status'] == 'active'): ?>
                                                <button onclick="updateStatus(<?= $customer['id'] ?>, 'inactive')" class="btn btn-sm btn-warning" title="Deactivate">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                                <?php else: ?>
                                                <button onclick="updateStatus(<?= $customer['id'] ?>, 'active')" class="btn btn-sm btn-success" title="Activate">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button onclick="deleteCustomer(<?= $customer['id'] ?>, '<?= htmlspecialchars(addslashes($customer['full_name'])) ?>')" 
                                                        class="btn btn-sm btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php 
                                        }
                                    else:
                                    ?>
                                    <tr>
                                        <td colspan="10" style="text-align: center; padding: 40px; color: #aaa;">
                                            <div class="empty-state">
                                                <i class="fas fa-users fa-3x"></i>
                                                <h3 style="color: #aaa; margin: 15px 0 10px 0;">No Customers Found</h3>
                                                <p style="color: #777; margin-bottom: 20px;">There are no customers registered yet.</p>
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
        </main>
    </div>

    <!-- Add Customer Floating Button -->
    <div class="add-customer-btn" onclick="addCustomer()" title="Add New Customer">
        <i class="fas fa-plus"></i>
    </div>

    <!-- Customer Modal -->
    <div class="modal" id="customerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Customer Details</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="customerModalBody">
                <!-- Customer details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Toggle Logout Menu
        const userMenuHeader = document.getElementById('userMenuHeader');
        const logoutMenu = document.getElementById('logoutMenu');
        
        if (userMenuHeader && logoutMenu) {
            userMenuHeader.addEventListener('click', function(e) {
                e.stopPropagation();
                logoutMenu.classList.toggle('show');
            });

            // Close menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!userMenuHeader.contains(e.target) && !logoutMenu.contains(e.target)) {
                    logoutMenu.classList.remove('show');
                }
            });
        }
        
        // Show loading overlay
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }
        
        // Hide loading overlay
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }
        
        // Search functionality
        document.getElementById('searchCustomer').addEventListener('keyup', function() {
            filterTable();
        });
        
        // Filter by status
        document.getElementById('filterStatus').addEventListener('change', function() {
            filterTable();
        });
        
        function filterTable() {
            const searchValue = document.getElementById('searchCustomer').value.toLowerCase();
            const statusValue = document.getElementById('filterStatus').value;
            const rows = document.querySelectorAll('#customersTable tbody tr');
            
            rows.forEach(row => {
                const searchText = row.getAttribute('data-search') || '';
                const rowStatus = row.getAttribute('data-status');
                const matchesSearch = searchText.includes(searchValue);
                const matchesStatus = !statusValue || rowStatus === statusValue;
                
                row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });
        }
        
        // Bulk Actions
        function showBulkActions() {
            const checkboxes = document.querySelectorAll('.customer-checkbox');
            checkboxes.forEach(cb => {
                cb.style.display = 'inline-block';
            });
            document.getElementById('bulkActions').classList.add('show');
            updateSelectedCount();
        }
        
        function hideBulkActions() {
            const checkboxes = document.querySelectorAll('.customer-checkbox');
            checkboxes.forEach(cb => {
                cb.style.display = 'none';
                cb.checked = false;
            });
            document.getElementById('selectAll').checked = false;
            document.getElementById('bulkActions').classList.remove('show');
            updateSelectedCount();
        }
        
        // Select all checkboxes
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.customer-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateSelectedCount();
        });
        
        // Update selected count
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.customer-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = selected + ' customer(s) selected';
        }
        
        // Apply bulk action
        function applyBulkAction() {
            const action = document.getElementById('bulkActionSelect').value;
            if (!action) {
                alert('Please select an action.');
                return;
            }
            
            const selected = document.querySelectorAll('.customer-checkbox:checked');
            if (selected.length === 0) {
                alert('Please select at least one customer.');
                return;
            }
            
            const customerIds = Array.from(selected).map(cb => cb.value);
            const customerNames = Array.from(selected).map(cb => {
                const row = cb.closest('tr');
                return row.querySelector('td:nth-child(3) strong').textContent;
            }).join(', ');
            
            if (action === 'delete') {
                if (!confirm(`Are you sure you want to delete ${selected.length} customer(s)?\n\n${customerNames}`)) {
                    return;
                }
            } else {
                const actionText = action === 'activate' ? 'activate' : 'deactivate';
                if (!confirm(`Are you sure you want to ${actionText} ${selected.length} customer(s)?\n\n${customerNames}`)) {
                    return;
                }
            }
            
            // Submit form
            document.getElementById('bulkActionInput').value = action;
            document.getElementById('selectedCustomersInput').value = customerIds.join(',');
            document.getElementById('bulkForm').submit();
        }
        
        // Add event listeners to checkboxes
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('customer-checkbox')) {
                updateSelectedCount();
            }
        });
        
        // View Customer
        function viewCustomer(id) {
            showLoading();
            fetch('?ajax=get_customer&id=' + id)
                .then(response => response.text())
                .then(html => {
                    hideLoading();
                    document.getElementById('customerModalBody').innerHTML = html;
                    document.getElementById('modalTitle').textContent = 'Customer Details';
                    document.getElementById('customerModal').style.display = 'flex';
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    document.getElementById('customerModalBody').innerHTML = 
                        '<div style="padding: 20px; color: #ff6b6b;">Failed to load customer details.</div>';
                    document.getElementById('modalTitle').textContent = 'Error';
                    document.getElementById('customerModal').style.display = 'flex';
                });
        }
        
        // Edit Customer
        function editCustomer(id) {
            showLoading();
            fetch('?ajax=edit_customer&id=' + id)
                .then(response => response.text())
                .then(html => {
                    hideLoading();
                    document.getElementById('customerModalBody').innerHTML = html;
                    document.getElementById('modalTitle').textContent = 'Edit Customer';
                    document.getElementById('customerModal').style.display = 'flex';
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    document.getElementById('customerModalBody').innerHTML = 
                        '<div style="padding: 20px; color: #ff6b6b;">Failed to load edit form.</div>';
                    document.getElementById('modalTitle').textContent = 'Error';
                    document.getElementById('customerModal').style.display = 'flex';
                });
        }
        
        // Add Customer
        function addCustomer() {
            const html = `
                <div class="add-customer-form">
                    <h4 style="color: var(--blue); margin-bottom: 20px; font-size: 18px;">
                        <i class="fas fa-user-plus"></i> Add New Customer
                    </h4>
                    <form id="addCustomerForm" onsubmit="saveNewCustomer(event)">
                        <input type="hidden" name="id" value="0">
                        <input type="hidden" name="ajax_action" value="save_customer">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required 
                                       placeholder="Enter full name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control" required 
                                       placeholder="customer@example.com">
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label class="form-label">Phone Number *</label>
                                <input type="text" name="phone" class="form-control" required 
                                       placeholder="081234567890">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" class="form-control" required 
                                       placeholder="Choose username">
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label class="form-label">Password *</label>
                                <input type="password" name="password" class="form-control" required 
                                       placeholder="Minimum 6 characters">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm Password *</label>
                                <input type="password" name="confirm_password" class="form-control" required 
                                       placeholder="Confirm password">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Initial Status</label>
                            <select name="status" class="form-control">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div style="display: flex; gap: 10px; margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Customer
                            </button>
                            <button type="button" onclick="closeModal()" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            `;
            
            document.getElementById('customerModalBody').innerHTML = html;
            document.getElementById('modalTitle').textContent = 'Add New Customer';
            document.getElementById('customerModal').style.display = 'flex';
        }
        
        // Save customer form
        function saveCustomerForm() {
            const form = document.getElementById('editCustomerForm');
            const formData = new FormData(form);
            
            // Basic validation
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            
            if (password && password.length < 6) {
                alert('Password must be at least 6 characters.');
                return;
            }
            
            if (password && password !== confirmPassword) {
                alert('Passwords do not match.');
                return;
            }
            
            showLoading();
            
            fetch('?ajax=save_customer', {
                method: 'POST',
                body: formData
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
                alert('Failed to save customer.');
            });
        }
        
        // Save new customer
        function saveNewCustomer(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            
            // Basic validation
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters.');
                return;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                return;
            }
            
            showLoading();
            
            fetch('?ajax=save_customer', {
                method: 'POST',
                body: formData
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
                alert('Failed to add customer.');
            });
        }
        
        // Send Email
        function sendEmail(email) {
            const subject = prompt('Enter email subject:', 'Message from <?= htmlspecialchars($hotel_name) ?>');
            if (subject === null) return;
            
            const body = prompt('Enter email message:', 'Dear valued customer,\n\n');
            if (body === null) return;
            
            // Open default email client
            window.location.href = 'mailto:' + email + 
                                   '?subject=' + encodeURIComponent(subject) + 
                                   '&body=' + encodeURIComponent(body);
        }
        
        // Update Status
        function updateStatus(id, status) {
            const action = status === 'active' ? 'activate' : 'deactivate';
            if (confirm(`Are you sure you want to ${action} this customer?`)) {
                showLoading();
                fetch('?ajax=update_status', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'id=' + id + '&status=' + status
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        alert(data.message);
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
        
        // Delete Customer
        function deleteCustomer(id, name) {
            if (confirm(`Delete customer "${name}"?\n\nThis action cannot be undone.`)) {
                showLoading();
                window.location.href = '?delete=' + id;
            }
        }
        
        // Close Modal
        function closeModal() {
            document.getElementById('customerModal').style.display = 'none';
        }
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Close modal when clicking outside
        document.getElementById('customerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>