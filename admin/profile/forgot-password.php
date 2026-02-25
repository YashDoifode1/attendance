<?php
session_start();
include('../../config/db.php');
include('email-template.php'); // Make sure this file exists with email functions

$message = "";
$message_type = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $message = "Please enter your email address.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } else {
        // Check if email exists in students table
        $stmt = $pdo->prepare("SELECT id, name FROM students WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate a unique token
            $token = bin2hex(random_bytes(32));
            $expiry = date("Y-m-d H:i:s", strtotime("+1 hour")); // Token valid for 1 hour

            // Store token in the database (you'll need to add these columns to your students table)
            // First, check if columns exist and add them if not
            try {
                $check_columns = $pdo->query("SHOW COLUMNS FROM students LIKE 'reset_token'");
                if ($check_columns->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE students ADD COLUMN reset_token VARCHAR(255) NULL AFTER password");
                    $pdo->exec("ALTER TABLE students ADD COLUMN reset_expires DATETIME NULL AFTER reset_token");
                }
            } catch (PDOException $e) {
                // Columns might already exist
            }

            $update_stmt = $pdo->prepare("UPDATE students SET reset_token = ?, reset_expires = ? WHERE email = ?");
            $update_stmt->execute([$token, $expiry, $email]);

            // Send reset email using your email template function
            if (function_exists('sendPasswordResetEmail')) {
                $email_sent = sendPasswordResetEmail($email, $token, $user['name']);
            } else {
                // Fallback email sending
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/attendance_system/auth/reset-password.php?token=" . urlencode($token);
                $subject = "Password Reset Request - Attendance System";
                
                $email_message = "
                <html>
                <head>
                    <title>Password Reset Request</title>
                </head>
                <body>
                    <h2>Hello " . htmlspecialchars($user['name']) . ",</h2>
                    <p>We received a request to reset your password for the Attendance System.</p>
                    <p>Click the button below to reset your password. This link will expire in 1 hour.</p>
                    <p><a href='$reset_link' style='display: inline-block; padding: 10px 20px; background-color: #3b82f6; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <p>Thanks,<br>Attendance System Team</p>
                </body>
                </html>
                ";
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n";
                
                $email_sent = mail($email, $subject, $email_message, $headers);
            }

            if ($email_sent) {
                $message = "A password reset link has been sent to your email address. Please check your inbox.";
                $message_type = "success";
            } else {
                $message = "Failed to send email. Please try again later.";
                $message_type = "error";
            }
        } else {
            // Don't reveal if email exists for security
            $message = "If your email is registered, you will receive a reset link shortly.";
            $message_type = "info";
        }
    }
}

// Include header without authentication check
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bg-primary: #0f1217;
            --bg-secondary: #1a1e26;
            --card-bg: #1e232b;
            --sidebar-hover: #2a2f3a;
            --border-color: #2a2f3a;
            --text-primary: #f0f3f8;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --sidebar-active: #3b82f6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .forgot-password-container {
            width: 100%;
            max-width: 450px;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }

        .card-header {
            background: linear-gradient(135deg, var(--sidebar-active), #8b5cf6);
            padding: 30px 30px 20px;
            border-bottom: none;
        }

        .card-body {
            padding: 30px;
        }

        .brand-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .brand-icon i {
            font-size: 2rem;
            color: white;
        }

        h2 {
            color: white;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.8rem;
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .input-group {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .input-group:focus-within {
            border-color: var(--sidebar-active);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .input-group-text {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 12px 15px;
        }

        .form-control {
            background: transparent;
            border: none;
            padding: 12px 15px 12px 0;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .form-control:focus {
            background: transparent;
            box-shadow: none;
            color: var(--text-primary);
        }

        .form-control::placeholder {
            color: var(--text-muted);
            opacity: 0.7;
        }

        .btn-reset {
            background: linear-gradient(90deg, var(--sidebar-active), #8b5cf6);
            border: none;
            color: white;
            font-weight: 600;
            padding: 14px;
            border-radius: 12px;
            width: 100%;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
            color: white;
        }

        .btn-reset:active {
            transform: translateY(0);
        }

        .btn-back {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-weight: 500;
            padding: 12px;
            border-radius: 12px;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: var(--sidebar-hover);
            color: var(--text-primary);
            border-color: var(--sidebar-active);
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--sidebar-active);
            border-left: 4px solid var(--sidebar-active);
        }

        .alert i {
            font-size: 1.2rem;
            margin-right: 12px;
        }

        .security-note {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 15px;
            margin-top: 25px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .security-note i {
            color: var(--warning);
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .security-note p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin: 0;
            line-height: 1.5;
        }

        .footer-links {
            margin-top: 20px;
            text-align: center;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--sidebar-active);
        }

        .divider {
            color: var(--border-color);
            margin: 0 10px;
        }

        /* Animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            animation: slideIn 0.5s ease;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .card-header {
                padding: 25px 20px;
            }
            
            .card-body {
                padding: 25px 20px;
            }
            
            h2 {
                font-size: 1.5rem;
            }
        }

        /* Loading state */
        .btn-reset.loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-reset.loading::after {
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
</head>
<body>

<div class="forgot-password-container">
    <div class="card">
        <div class="card-header text-center">
            <div class="brand-icon mx-auto">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h2>Forgot Password?</h2>
            <p class="subtitle">No worries, we'll send you reset instructions.</p>
        </div>
        
        <div class="card-body">
            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?= $message_type === 'success' ? 'check-circle-fill' : ($message_type === 'info' ? 'info-circle-fill' : 'exclamation-triangle-fill') ?>"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" id="forgotPasswordForm">
                <div class="mb-4">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-envelope-fill"></i>
                        </span>
                        <input type="email" 
                               name="email" 
                               class="form-control" 
                               placeholder="Enter your registered email"
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                               required 
                               autofocus>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        <i class="bi bi-info-circle me-1"></i>
                        We'll send a reset link to this email address
                    </small>
                </div>

                <button type="submit" class="btn-reset" id="submitBtn">
                    <i class="bi bi-send-fill me-2"></i>Send Reset Link
                </button>
            </form>

            <!-- Security Note -->
            <div class="security-note">
                <i class="bi bi-shield-check"></i>
                <p>
                    For your security, password reset links expire after 1 hour. 
                    If you don't receive an email, check your spam folder or try again.
                </p>
            </div>

            <!-- Back to Login -->
            <div class="footer-links">
                <a href="login.php">
                    <i class="bi bi-arrow-left me-1"></i>Back to Login
                </a>
                <span class="divider">|</span>
                <a href="register.php">Create Account</a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center mt-4">
        <small style="color: var(--text-muted);">© <?= date('Y') ?> Attendance System. All rights reserved.</small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Form validation and loading state
document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
    const email = document.querySelector('input[name="email"]').value.trim();
    const submitBtn = document.getElementById('submitBtn');
    
    if (!email) {
        e.preventDefault();
        showNotification('Please enter your email address.', 'error');
    } else if (!isValidEmail(email)) {
        e.preventDefault();
        showNotification('Please enter a valid email address.', 'error');
    } else {
        // Show loading state
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
    }
});

// Email validation function
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Show notification function (if no Bootstrap alert)
function showNotification(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
        <i class="bi bi-${type === 'error' ? 'exclamation-triangle-fill' : 'info-circle-fill'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const cardBody = document.querySelector('.card-body');
    cardBody.insertBefore(alertDiv, cardBody.firstChild);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);

// Add smooth fade out effect
document.querySelectorAll('.btn-close').forEach(btn => {
    btn.addEventListener('click', function() {
        const alert = this.closest('.alert');
        alert.classList.remove('show');
    });
});
</script>

<!-- Add this if you want to use the email template function -->
<?php
// email-template.php content (create this file if it doesn't exist)
if (!function_exists('sendPasswordResetEmail')) {
    function sendPasswordResetEmail($email, $token, $name) {
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/attendance_system/auth/reset-password.php?token=" . urlencode($token);
        
        $subject = "Reset Your Password - Attendance System";
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Password Reset Request</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #3b82f6, #8b5cf6); padding: 30px; text-align: center; color: white; border-radius: 10px 10px 0 0; }
                .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 12px 30px; background: linear-gradient(90deg, #3b82f6, #8b5cf6); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Password Reset Request</h2>
                </div>
                <div class='content'>
                    <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    <p>We received a request to reset your password for the Attendance System. Click the button below to create a new password:</p>
                    
                    <div style='text-align: center;'>
                        <a href='$reset_link' class='button'>Reset Password</a>
                    </div>
                    
                    <p><strong>This link will expire in 1 hour.</strong></p>
                    
                    <p>If you didn't request this, please ignore this email and your password will remain unchanged.</p>
                    
                    <p>For security reasons, never share this link with anyone.</p>
                    
                    <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
                    
                    <p style='color: #666; font-size: 0.9em;'>
                        If the button doesn't work, copy and paste this link into your browser:<br>
                        <span style='color: #3b82f6;'>$reset_link</span>
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Attendance System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Attendance System <no-reply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";
        
        return mail($email, $subject, $message, $headers);
    }
}
?>
</body>
</html>