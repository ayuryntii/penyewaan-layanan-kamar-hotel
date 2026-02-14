<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = "My Profile";
$error = '';
$success = '';

// Get user data
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Update basic info
    $update_sql = "UPDATE users SET full_name = ?, phone = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssi", $full_name, $phone, $_SESSION['user_id']);
    
    if ($update_stmt->execute()) {
        $_SESSION['full_name'] = $full_name;
        $success = "Profile updated successfully!";
    } else {
        $error = "Error updating profile: " . $conn->error;
    }
    
    // Update password if provided
    if (!empty($current_password) && !empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $error = "New passwords do not match!";
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = "Current password is incorrect!";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $password_sql = "UPDATE users SET password = ? WHERE id = ?";
            $password_stmt = $conn->prepare($password_sql);
            $password_stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
            
            if ($password_stmt->execute()) {
                $success = "Profile and password updated successfully!";
            } else {
                $error = "Error updating password.";
            }
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture'])) {
    if ($_FILES['profile_picture']['error'] === 0) {
        $upload_result = uploadFile($_FILES['profile_picture'], 'uploads/users');
        
        if ($upload_result['success']) {
            $picture_sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
            $picture_stmt = $conn->prepare($picture_sql);
            $picture_path = $upload_result['path'];
            $picture_stmt->bind_param("si", $picture_path, $_SESSION['user_id']);
            
            if ($picture_stmt->execute()) {
                $_SESSION['profile_picture'] = $picture_path;
                $success = "Profile picture updated successfully!";
            }
        } else {
            $error = $upload_result['error'];
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-user-circle me-2"></i>My Profile</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <!-- Profile Picture -->
                        <div class="col-md-4 text-center mb-4">
                            <div class="profile-picture-container">
                                <?php if ($user['profile_picture']): ?>
                                    <img src="<?php echo BASE_URL . $user['profile_picture']; ?>" 
                                         alt="Profile Picture" class="img-fluid rounded-circle mb-3" 
                                         style="width: 200px; height: 200px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mb-3" 
                                         style="width: 200px; height: 200px; margin: 0 auto;">
                                        <i class="fas fa-user fa-5x text-light"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <div class="input-group mb-2">
                                        <input type="file" class="form-control" name="profile_picture" 
                                               accept="image/*" id="profilePicture">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-upload"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Max 5MB. JPG, PNG, GIF only.</small>
                                </form>
                            </div>
                            
                            <!-- Account Info -->
                            <div class="mt-4">
                                <h6>Account Information</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Username:</strong> <?php echo $user['username']; ?></li>
                                    <li><strong>Email:</strong> <?php echo $user['email']; ?></li>
                                    <li><strong>Role:</strong> <?php echo ucfirst($user['role']); ?></li>
                                    <li><strong>Member Since:</strong> <?php echo date('d M Y', strtotime($user['created_at'])); ?></li>
                                    <?php if ($user['last_login']): ?>
                                        <li><strong>Last Login:</strong> <?php echo date('d M Y H:i', strtotime($user['last_login'])); ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Profile Form -->
                        <div class="col-md-8">
                            <form method="POST" action="" class="needs-validation" novalidate>
                                <h5 class="mb-3">Personal Information</h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="full_name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                        <div class="invalid-feedback">Please enter your full name.</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone']); ?>">
                                    </div>
                                </div>
                                
                                <hr class="my-4">
                                
                                <h5 class="mb-3">Change Password</h5>
                                <p class="text-muted mb-3">Leave blank if you don't want to change your password.</p>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                    <a href="my-bookings.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Bookings
                                    </a>
                                </div>
                            </form>
                            
                            <!-- Danger Zone -->
                            <hr class="my-4">
                            <div class="alert alert-danger">
                                <h5><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                                <p class="mb-2">Once you delete your account, there is no going back. Please be certain.</p>
                                <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                    <i class="fas fa-trash me-2"></i>Delete My Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete your account?</p>
                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. All your data will be permanently deleted.</p>
                <div class="mb-3">
                    <label class="form-label">Type "DELETE" to confirm:</label>
                    <input type="text" class="form-control" id="deleteConfirm" placeholder="DELETE">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="deleteAccountBtn" disabled>
                    Delete Account
                </button>
            </div>
        </div>
    </div>
</div>

<script>
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

// Password validation
document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (this.value && confirmPassword.value) {
        if (this.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
});

document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password');
    if (this.value && newPassword.value) {
        if (this.value !== newPassword.value) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    }
});

// Delete account confirmation
document.getElementById('deleteConfirm').addEventListener('input', function() {
    document.getElementById('deleteAccountBtn').disabled = this.value !== 'DELETE';
});

document.getElementById('deleteAccountBtn').addEventListener('click', function() {
    if (confirm('Are you absolutely sure? This cannot be undone!')) {
        window.location.href = 'delete-account.php';
    }
});

// Profile picture preview
document.getElementById('profilePicture').addEventListener('change', function() {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.querySelector('.profile-picture-container img');
            if (img) {
                img.src = e.target.result;
            }
        };
        reader.readAsDataURL(this.files[0]);
    }
});
</script>

<style>
.profile-picture-container img {
    border: 5px solid #f8f9fa;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
</style>

<?php include 'includes/footer.php'; ?>