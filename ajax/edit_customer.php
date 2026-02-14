<?php
// hotel/ajax/edit_customer.php
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
    
    <form id="editCustomerForm" onsubmit="saveCustomer(event)">
        <input type="hidden" name="id" value="<?= $customer['id'] ?>">
        
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
                    <option value="banned" <?= $customer['status'] == 'banned' ? 'selected' : '' ?>>Banned</option>
                </select>
            </div>
        </div>
        
        <div class="form-group" style="margin-bottom: 20px;">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="3"
                      placeholder="Enter customer's address..."><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Notes (Internal)</label>
            <textarea name="notes" class="form-control" rows="3"
                      placeholder="Any internal notes about this customer..."><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
            <small style="color: #aaa;">These notes are only visible to staff</small>
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
            <button type="submit" class="btn btn-primary">
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

// Save customer
function saveCustomer(event) {
    event.preventDefault();
    
    if (!validatePassword()) {
        alert('Passwords do not match!');
        return;
    }
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Show loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;
    
    fetch('ajax/save_customer.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Customer updated successfully!');
            closeModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save customer');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}
</script>
<?php
// Close connection
if (isset($conn)) {
    $conn->close();
}
?>