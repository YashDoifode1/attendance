<?php
session_start();
include('../../config/db.php');

// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';
$message = "";
$message_type = "";

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $old_password = trim($_POST['old_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validation
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $message = "All fields are required.";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match.";
        $message_type = "error";
    } elseif (strlen($new_password) < 8) {
        $message = "New password must be at least 8 characters long.";
        $message_type = "error";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $message = "Password must contain at least one uppercase letter.";
        $message_type = "error";
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $message = "Password must contain at least one lowercase letter.";
        $message_type = "error";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $message = "Password must contain at least one number.";
        $message_type = "error";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $message = "Password must contain at least one special character.";
        $message_type = "error";
    } else {
        // Fetch current hashed password from database
        $stmt = $pdo->prepare("SELECT password FROM students WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        // Verify old password
        if (password_verify($old_password, $user['password'])) {
            // Check if new password is same as old
            if (password_verify($new_password, $user['password'])) {
                $message = "New password cannot be the same as old password.";
                $message_type = "error";
            } else {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

                // Update password in database
                $update_stmt = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
                if ($update_stmt->execute([$hashed_password, $user_id])) {
                    $message = "Password updated successfully!";
                    $message_type = "success";
                    
                    // Log the password change (optional)
                    $log_stmt = $pdo->prepare("INSERT INTO password_changes (user_id, changed_at) VALUES (?, NOW())");
                    $log_stmt->execute([$user_id]);
                } else {
                    $message = "Error updating password. Please try again.";
                    $message_type = "error";
                }
            }
        } else {
            $message = "Incorrect old password.";
            $message_type = "error";
        }
    }
}

// Get user name for welcome message
$stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
$stmt->execute([$user_id]);
$user_name = $stmt->fetchColumn();

// INCLUDE SIDEBAR + HEADER based on role
if ($user_role === 'admin') {
    include('../../admin/includes/sidebar_header.php');
} else {
    include('../../includes/header.php'); // Adjust path as needed
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Change Password</h4>
        <p class="mb-0" style="color: var(--text-muted);">Update your password to keep your account secure.</p>
    </div>
    <a href="profile.php" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);">
        <i class="bi bi-arrow-left me-2"></i>Back to Profile
    </a>
</div>

<!-- Password Change Form -->
<div class="row justify-content-center">
    <div class="col-xl-6 col-lg-8">
        <!-- Security Notice Card -->
        <div class="card border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-shield-lock-fill" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="fw-bold mb-1" style="color: var(--text-primary);">Account Security</h6>
                        <p class="mb-0" style="color: var(--text-muted); font-size: 0.9rem;">
                            Choose a strong password that you don't use for other accounts.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Form Card -->
        <div class="card border-0">
            <div class="card-header">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-key-fill me-2" style="color: var(--sidebar-active);"></i>Update Your Password
                </h5>
            </div>
            <div class="card-body p-4">
                
                <!-- Welcome Message -->
                <div class="mb-4 p-3 rounded-3" style="background: var(--sidebar-hover);">
                    <p class="mb-0" style="color: var(--text-secondary);">
                        <i class="bi bi-person-circle me-2" style="color: var(--sidebar-active);"></i>
                        Changing password for <strong style="color: var(--text-primary);"><?= htmlspecialchars($user_name) ?></strong>
                    </p>
                </div>

                <!-- Message Display -->
                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-<?= $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="passwordForm">
                    <!-- Old Password -->
                    <div class="mb-4">
                        <label class="form-label fw-medium" style="color: var(--text-primary);">
                            <i class="bi bi-lock me-2" style="color: var(--sidebar-active);"></i>Current Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-secondary);">
                                <i class="bi bi-key"></i>
                            </span>
                            <input type="password" name="old_password" id="old_password" 
                                   class="form-control form-control-lg" 
                                   placeholder="Enter your current password"
                                   style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" 
                                   required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="old_password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- New Password -->
                    <div class="mb-4">
                        <label class="form-label fw-medium" style="color: var(--text-primary);">
                            <i class="bi bi-shield-lock me-2" style="color: var(--sidebar-active);"></i>New Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-secondary);">
                                <i class="bi bi-key-fill"></i>
                            </span>
                            <input type="password" name="new_password" id="new_password" 
                                   class="form-control form-control-lg" 
                                   placeholder="Enter new password"
                                   style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" 
                                   required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        
                        <!-- Password Strength Meter -->
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small style="color: var(--text-muted);">Password strength:</small>
                                <small id="passwordStrength" style="color: var(--text-muted);">Too weak</small>
                            </div>
                            <div class="progress" style="height: 6px; background: var(--border-color);">
                                <div id="strengthBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <!-- Password Requirements -->
                        <div class="mt-3 p-3 rounded-3" style="background: var(--sidebar-hover);">
                            <small class="d-block fw-medium mb-2" style="color: var(--text-primary);">Password must contain:</small>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="requirement-item" id="req-length">
                                        <i class="bi bi-x-circle-fill text-danger me-2"></i>
                                        <small style="color: var(--text-muted);">At least 8 characters</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="requirement-item" id="req-uppercase">
                                        <i class="bi bi-x-circle-fill text-danger me-2"></i>
                                        <small style="color: var(--text-muted);">One uppercase letter</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="requirement-item" id="req-lowercase">
                                        <i class="bi bi-x-circle-fill text-danger me-2"></i>
                                        <small style="color: var(--text-muted);">One lowercase letter</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="requirement-item" id="req-number">
                                        <i class="bi bi-x-circle-fill text-danger me-2"></i>
                                        <small style="color: var(--text-muted);">One number</small>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="requirement-item" id="req-special">
                                        <i class="bi bi-x-circle-fill text-danger me-2"></i>
                                        <small style="color: var(--text-muted);">One special character (!@#$%^&*)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-4">
                        <label class="form-label fw-medium" style="color: var(--text-primary);">
                            <i class="bi bi-check-circle me-2" style="color: var(--sidebar-active);"></i>Confirm New Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-secondary);">
                                <i class="bi bi-check2-circle"></i>
                            </span>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                   class="form-control form-control-lg" 
                                   placeholder="Confirm your new password"
                                   style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" 
                                   required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div id="passwordMatch" class="mt-2" style="display: none;">
                            <small><i class="bi bi-check-circle-fill text-success me-1"></i>Passwords match</small>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-lg" onclick="resetForm()"
                                style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 12px 30px;">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg" 
                                style="padding: 12px 30px; background: linear-gradient(90deg, var(--sidebar-active), #8b5cf6); border: none;">
                            <i class="bi bi-shield-check me-2"></i>Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Tips Card -->
        <div class="card border-0 mt-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3" style="color: var(--text-primary);">
                    <i class="bi bi-lightbulb me-2" style="color: var(--warning);"></i>Password Security Tips
                </h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2 d-flex align-items-center">
                        <i class="bi bi-check-circle-fill text-success me-2" style="font-size: 0.8rem;"></i>
                        <span style="color: var(--text-secondary);">Use a unique password for this account</span>
                    </li>
                    <li class="mb-2 d-flex align-items-center">
                        <i class="bi bi-check-circle-fill text-success me-2" style="font-size: 0.8rem;"></i>
                        <span style="color: var(--text-secondary);">Avoid using personal information</span>
                    </li>
                    <li class="mb-2 d-flex align-items-center">
                        <i class="bi bi-check-circle-fill text-success me-2" style="font-size: 0.8rem;"></i>
                        <span style="color: var(--text-secondary);">Change your password regularly</span>
                    </li>
                    <li class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill text-success me-2" style="font-size: 0.8rem;"></i>
                        <span style="color: var(--text-secondary);">Never share your password with anyone</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Password Features -->
<script>
// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
});

// Password strength checker
const newPassword = document.getElementById('new_password');
const strengthBar = document.getElementById('strengthBar');
const strengthText = document.getElementById('passwordStrength');

// Requirement elements
const reqLength = document.getElementById('req-length');
const reqUppercase = document.getElementById('req-uppercase');
const reqLowercase = document.getElementById('req-lowercase');
const reqNumber = document.getElementById('req-number');
const reqSpecial = document.getElementById('req-special');

function checkPasswordStrength(password) {
    let strength = 0;
    
    // Length check
    if (password.length >= 8) {
        strength += 20;
        updateRequirement(reqLength, true);
    } else {
        updateRequirement(reqLength, false);
    }
    
    // Uppercase check
    if (/[A-Z]/.test(password)) {
        strength += 20;
        updateRequirement(reqUppercase, true);
    } else {
        updateRequirement(reqUppercase, false);
    }
    
    // Lowercase check
    if (/[a-z]/.test(password)) {
        strength += 20;
        updateRequirement(reqLowercase, true);
    } else {
        updateRequirement(reqLowercase, false);
    }
    
    // Number check
    if (/[0-9]/.test(password)) {
        strength += 20;
        updateRequirement(reqNumber, true);
    } else {
        updateRequirement(reqNumber, false);
    }
    
    // Special character check
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
        strength += 20;
        updateRequirement(reqSpecial, true);
    } else {
        updateRequirement(reqSpecial, false);
    }
    
    // Update strength bar
    strengthBar.style.width = strength + '%';
    
    if (strength <= 20) {
        strengthBar.style.background = '#ef4444';
        strengthText.textContent = 'Too weak';
        strengthText.style.color = '#ef4444';
    } else if (strength <= 40) {
        strengthBar.style.background = '#f59e0b';
        strengthText.textContent = 'Weak';
        strengthText.style.color = '#f59e0b';
    } else if (strength <= 60) {
        strengthBar.style.background = '#3b82f6';
        strengthText.textContent = 'Fair';
        strengthText.style.color = '#3b82f6';
    } else if (strength <= 80) {
        strengthBar.style.background = '#10b981';
        strengthText.textContent = 'Good';
        strengthText.style.color = '#10b981';
    } else {
        strengthBar.style.background = 'linear-gradient(90deg, #10b981, #3b82f6)';
        strengthText.textContent = 'Strong';
        strengthText.style.color = '#10b981';
    }
}

function updateRequirement(element, isValid) {
    const icon = element.querySelector('i');
    if (isValid) {
        icon.classList.remove('bi-x-circle-fill', 'text-danger');
        icon.classList.add('bi-check-circle-fill', 'text-success');
        element.querySelector('small').style.color = 'var(--text-primary)';
    } else {
        icon.classList.remove('bi-check-circle-fill', 'text-success');
        icon.classList.add('bi-x-circle-fill', 'text-danger');
        element.querySelector('small').style.color = 'var(--text-muted)';
    }
}

newPassword.addEventListener('input', function() {
    checkPasswordStrength(this.value);
});

// Password match checker
const confirmPassword = document.getElementById('confirm_password');
const passwordMatch = document.getElementById('passwordMatch');

function checkPasswordMatch() {
    if (confirmPassword.value.length > 0) {
        if (newPassword.value === confirmPassword.value) {
            passwordMatch.style.display = 'block';
            confirmPassword.style.borderColor = '#10b981';
        } else {
            passwordMatch.style.display = 'none';
            confirmPassword.style.borderColor = '#ef4444';
        }
    } else {
        passwordMatch.style.display = 'none';
        confirmPassword.style.borderColor = 'var(--border-color)';
    }
}

newPassword.addEventListener('input', checkPasswordMatch);
confirmPassword.addEventListener('input', checkPasswordMatch);

// Form validation before submit
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPass = newPassword.value;
    const confirmPass = confirmPassword.value;
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('Passwords do not match!');
    }
});

// Reset form function
function resetForm() {
    document.getElementById('passwordForm').reset();
    
    // Reset password strength
    strengthBar.style.width = '0%';
    strengthText.textContent = 'Too weak';
    
    // Reset requirements
    const requirements = [reqLength, reqUppercase, reqLowercase, reqNumber, reqSpecial];
    requirements.forEach(req => {
        const icon = req.querySelector('i');
        icon.classList.remove('bi-check-circle-fill', 'text-success');
        icon.classList.add('bi-x-circle-fill', 'text-danger');
        req.querySelector('small').style.color = 'var(--text-muted)';
    });
    
    // Hide match message
    passwordMatch.style.display = 'none';
    
    // Reset border colors
    confirmPassword.style.borderColor = 'var(--border-color)';
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);
</script>

<!-- Custom CSS -->
<style>
/* Form styles */
.form-control, .form-select {
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--sidebar-active);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Card hover effect */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2) !important;
}

/* Input group styles */
.input-group-text {
    border-right: none;
}

.input-group .form-control {
    border-left: none;
}

.input-group .form-control:focus {
    border-left: none;
}

/* Toggle password button */
.toggle-password {
    border-color: var(--border-color);
    background: var(--card-bg);
    color: var(--text-secondary);
}

.toggle-password:hover {
    background: var(--sidebar-hover);
    color: var(--text-primary);
}

/* Password strength bar */
.progress-bar {
    transition: width 0.3s ease, background-color 0.3s ease;
}

/* Requirement items */
.requirement-item {
    transition: all 0.3s ease;
    padding: 2px 0;
}

.requirement-item i {
    font-size: 0.8rem;
}

/* Alert styles */
.alert {
    border: none;
    border-radius: 12px;
    padding: 1rem 1.25rem;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border-left: 4px solid var(--success);
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-left: 4px solid #ef4444;
}

/* Button styles */
.btn {
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1.25rem;
    }
    
    .btn-lg {
        padding: 10px 20px !important;
        font-size: 0.9rem;
    }
}

/* Loading state for submit button */
.btn-primary.loading {
    position: relative;
    pointer-events: none;
    opacity: 0.7;
}

.btn-primary.loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 2px solid transparent;
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<?php
// CLOSE MAIN / BODY / HTML
include('../includes/footer.php');
?>