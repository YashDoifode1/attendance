<?php
// session_start();
include('../config/db.php');
include('../includes/header.php'); // Your professional sidebar + top header

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $old_password = trim($_POST['old_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Password strength validation
    $password_regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
    
    // Check if new password and confirm password match
    if ($new_password !== $confirm_password) {
        $message = "New passwords do not match.";
        $message_type = "danger";
    } elseif (!preg_match($password_regex, $new_password)) {
        $message = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
        $message_type = "warning";
    } else {
        // Fetch current hashed password from database
        $stmt = $pdo->prepare("SELECT password FROM students WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        // Verify old password
        if (password_verify($old_password, $user['password'])) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

            // Update password in database
            $update_stmt = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
            if ($update_stmt->execute([$hashed_password, $user_id])) {
                $message = "Password updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating password. Please try again.";
                $message_type = "danger";
            }
        } else {
            $message = "Incorrect old password.";
            $message_type = "danger";
        }
    }
}

// Fetch user data for display
$stmt = $pdo->prepare("SELECT name, email FROM students WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<main class="main-content">
    <div class="px-4 pt-4 pb-5">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                <i class="bi bi-shield-lock me-2"></i> Change Password
            </h2>
            <a href="profile.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> Back to Profile
            </a>
        </div>

        <!-- Alert Message -->
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-<?= $message_type == 'success' ? 'check-circle' : ($message_type == 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Password Change Card -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                <i class="bi bi-key text-primary fs-4"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Security Settings</h5>
                                <p class="text-muted small mb-0">Update your password to keep your account secure</p>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <!-- User Info Summary -->
                        <div class="bg-light rounded-3 p-3 mb-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-white rounded-circle p-2 shadow-sm me-3">
                                    <i class="bi bi-person-circle text-primary fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($user['name'] ?? 'Student') ?></h6>
                                    <p class="text-muted small mb-0"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <!-- Old Password -->
                            <div class="mb-3">
                                <label for="old_password" class="form-label fw-semibold">
                                    <i class="bi bi-lock me-2"></i>Current Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-key text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control border-start-0" 
                                           id="old_password" 
                                           name="old_password" 
                                           placeholder="Enter your current password"
                                           required>
                                </div>
                            </div>

                            <!-- New Password -->
                            <div class="mb-3">
                                <label for="new_password" class="form-label fw-semibold">
                                    <i class="bi bi-shield me-2"></i>New Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-shield-lock text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control border-start-0" 
                                           id="new_password" 
                                           name="new_password" 
                                           placeholder="Enter new password"
                                           required>
                                </div>
                                <!-- Password Strength Meter -->
                                <div class="mt-2">
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted" id="passwordHelp">
                                        Password must contain at least 8 characters, including uppercase, lowercase, number and special character.
                                    </small>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-semibold">
                                    <i class="bi bi-check-circle me-2"></i>Confirm New Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-check2-circle text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control border-start-0" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           placeholder="Re-enter new password"
                                           required>
                                </div>
                                <div class="invalid-feedback" id="passwordMatchFeedback">
                                    Passwords do not match
                                </div>
                            </div>

                            <!-- Security Tips -->
                            <div class="alert alert-info bg-opacity-10 border-0 d-flex align-items-start mb-4">
                                <i class="bi bi-info-circle-fill me-3 fs-5 flex-shrink-0"></i>
                                <div>
                                    <strong class="d-block mb-1">Password Security Tips:</strong>
                                    <ul class="mb-0 small">
                                        <li>Use a mix of uppercase and lowercase letters</li>
                                        <li>Include at least one number and one special character</li>
                                        <li>Avoid using easily guessable information</li>
                                        <li>Don't reuse passwords from other accounts</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-3">
                                <button type="submit" class="btn btn-primary px-5 py-2">
                                    <i class="bi bi-check-circle me-2"></i>Update Password
                                </button>
                                <a href="profile.php" class="btn btn-outline-secondary px-5 py-2">
                                    <i class="bi bi-x-circle me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Card Footer with Last Update Info -->
                    <div class="card-footer bg-white border-0 px-4 py-3">
                        <small class="text-muted">
                            <i class="bi bi-shield-check me-1"></i>
                            For your security, you'll be logged out after changing your password (coming soon)
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Custom Styles -->
<style>
    .main-content {
        background-color: #f8f9fa;
        min-height: 100vh;
    }
    
    .card {
        border-radius: 15px;
        overflow: hidden;
    }
    
    .input-group-text {
        border-right: none;
        background-color: transparent;
    }
    
    .form-control {
        border-left: none;
        padding-left: 0;
    }
    
    .form-control:focus {
        box-shadow: none;
        border-color: #dee2e6;
        border-left: none;
    }
    
    .form-control:focus + .input-group-text {
        border-color: #86b7fe;
    }
    
    .input-group:focus-within .input-group-text {
        border-color: #86b7fe;
    }
    
    .progress {
        border-radius: 10px;
        background-color: #e9ecef;
    }
    
    .btn {
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .bg-opacity-10 {
        background-color: rgba(13, 110, 253, 0.1) !important;
    }
    
    @media (max-width: 768px) {
        .d-flex.gap-3 {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
        }
    }
</style>

<!-- JavaScript for Password Validation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const strengthBar = document.getElementById('passwordStrength');
    const passwordHelp = document.getElementById('passwordHelp');
    const form = document.querySelector('form');
    
    // Password strength checker
    function checkPasswordStrength(password) {
        let strength = 0;
        let feedback = [];
        
        if (password.length >= 8) strength += 25;
        else feedback.push('at least 8 characters');
        
        if (/[a-z]/.test(password)) strength += 25;
        else feedback.push('lowercase letter');
        
        if (/[A-Z]/.test(password)) strength += 25;
        else feedback.push('uppercase letter');
        
        if (/\d/.test(password)) strength += 15;
        else feedback.push('number');
        
        if (/[@$!%*?&]/.test(password)) strength += 10;
        else feedback.push('special character');
        
        // Update progress bar
        strengthBar.style.width = strength + '%';
        strengthBar.setAttribute('aria-valuenow', strength);
        
        // Update color based on strength
        if (strength < 50) {
            strengthBar.className = 'progress-bar bg-danger';
        } else if (strength < 75) {
            strengthBar.className = 'progress-bar bg-warning';
        } else {
            strengthBar.className = 'progress-bar bg-success';
        }
        
        // Update help text
        if (feedback.length > 0) {
            passwordHelp.innerHTML = 'Missing: ' + feedback.join(', ');
            passwordHelp.className = 'text-danger';
        } else {
            passwordHelp.innerHTML = 'Strong password! ✓';
            passwordHelp.className = 'text-success';
        }
        
        return strength;
    }
    
    // Password match checker
    function checkPasswordMatch() {
        const matchFeedback = document.getElementById('passwordMatchFeedback');
        if (newPassword.value && confirmPassword.value) {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.classList.add('is-invalid');
                matchFeedback.style.display = 'block';
                return false;
            } else {
                confirmPassword.classList.remove('is-invalid');
                confirmPassword.classList.add('is-valid');
                matchFeedback.style.display = 'none';
                return true;
            }
        }
        return false;
    }
    
    // Event listeners
    newPassword.addEventListener('input', function() {
        checkPasswordStrength(this.value);
        if (confirmPassword.value) checkPasswordMatch();
    });
    
    confirmPassword.addEventListener('input', checkPasswordMatch);
    
    // Form validation
    form.addEventListener('submit', function(e) {
        const strength = checkPasswordStrength(newPassword.value);
        const passwordsMatch = checkPasswordMatch();
        
        if (strength < 100) {
            e.preventDefault();
            alert('Please ensure your password meets all security requirements.');
        } else if (!passwordsMatch) {
            e.preventDefault();
            alert('Passwords do not match.');
        }
    });
});
</script>