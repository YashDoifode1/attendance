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

// Fetch current data with proper joins
$stmt = $pdo->prepare("
    SELECT s.*, 
           c.course_name, 
           y.year_name, 
           sess.session_name
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN years y ON s.year_id = y.id
    LEFT JOIN sessions sess ON s.session_id = sess.id
    WHERE s.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Fetch all options for dropdowns
$courses = $pdo->query("SELECT id, course_name FROM courses ORDER BY course_name")->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT id, year_name FROM years ORDER BY year_name")->fetchAll(PDO::FETCH_ASSOC);
$sessions = $pdo->query("SELECT id, session_name FROM sessions ORDER BY session_name")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course_id = $_POST['course_id'] ?? null;
    $year_id = $_POST['year_id'] ?? null;
    $session_id = $_POST['session_id'] ?? null;

    // Validation
    if (empty($name) || empty($email)) {
        $error_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Name and email are required.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Invalid email format.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    } else {
        // Check email uniqueness
        $check = $pdo->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
        $check->execute([$email, $user_id]);
        if ($check->fetch()) {
            $error_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>This email is already in use.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        } else {
            // Handle photo upload
            $photo_filename = $user['profile_photo'];
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/profiles/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $file = $_FILES['profile_photo'];
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (in_array($ext, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
                    // Delete old photo if exists
                    if ($user['profile_photo'] && file_exists($upload_dir . $user['profile_photo'])) {
                        unlink($upload_dir . $user['profile_photo']);
                    }
                    
                    $photo_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                    $filepath = $upload_dir . $photo_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Photo uploaded successfully
                    } else {
                        $error_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Failed to upload photo.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
                    }
                } else {
                    $error_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Invalid photo. Please upload JPG, PNG, or GIF (max 2MB).
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
                }
            }

            if (empty($error_message)) {
                $update = $pdo->prepare("UPDATE students SET name = ?, email = ?, profile_photo = ?, course_id = ?, year_id = ?, session_id = ? WHERE id = ?");
                if ($update->execute([$name, $email, $photo_filename, $course_id, $year_id, $session_id, $user_id])) {
                    $_SESSION['name'] = $name; // Update session name
                    $success_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>Profile updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
                    
                    // Refresh user data
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $user['profile_photo'] = $photo_filename;
                } else {
                    $error_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Failed to update profile.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
                }
            }
        }
    }
}

$photo_path = $user['profile_photo']
    ? '../../uploads/profiles/' . htmlspecialchars($user['profile_photo'])
    : 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&size=180&background=3b82f6&color=fff';

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
        <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Edit Profile</h4>
        <p class="mb-0" style="color: var(--text-muted);">Update your personal information and academic details.</p>
    </div>
    <a href="profile.php" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);">
        <i class="bi bi-arrow-left me-2"></i>Back to Profile
    </a>
</div>

<!-- Messages -->
<?= $success_message ?>
<?= $error_message ?>

<form method="POST" enctype="multipart/form-data">
    <div class="row g-4">
        <!-- Left Column - Profile Photo & Basic Info -->
        <div class="col-xl-4">
            <div class="card border-0">
                <div class="card-body text-center p-4">
                    <h5 class="fw-bold mb-4 text-start" style="color: var(--text-primary);">
                        <i class="bi bi-person-circle me-2" style="color: var(--sidebar-active);"></i>Profile Photo
                    </h5>
                    
                    <!-- Photo Upload Area -->
                    <div class="position-relative d-inline-block mb-4">
                        <img src="<?= $photo_path ?>?v=<?= time() ?>" 
                             alt="Profile Photo"
                             class="rounded-circle shadow" 
                             id="profilePreview"
                             style="width: 180px; height: 180px; object-fit: cover; border: 4px solid var(--card-bg);">
                        
                        <label class="position-absolute bottom-0 end-0 rounded-circle p-3 shadow cursor-pointer"
                               style="background: var(--sidebar-active); transform: translate(10%, 10%); cursor: pointer;">
                            <i class="bi bi-camera-fill text-white fs-5"></i>
                            <input type="file" name="profile_photo" id="photoInput" accept="image/*" 
                                   style="position: absolute; inset: 0; opacity: 0; cursor: pointer;">
                        </label>
                    </div>
                    
                    <p class="mb-0" style="color: var(--text-muted); font-size: 0.9rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        JPG, PNG or GIF (Max 2MB)
                    </p>
                    
                    <hr class="my-4" style="border-color: var(--border-color);">
                    
                    <!-- Basic Information -->
                    <h5 class="fw-bold mb-3 text-start" style="color: var(--text-primary);">
                        <i class="bi bi-info-circle me-2" style="color: var(--sidebar-active);"></i>Basic Information
                    </h5>
                    
                    <div class="text-start">
                        <div class="mb-3">
                            <label class="form-label fw-medium" style="color: var(--text-primary);">Full Name</label>
                            <input type="text" name="name" class="form-control form-control-lg" 
                                   value="<?= htmlspecialchars($user['name']) ?>" 
                                   placeholder="Enter your full name"
                                   style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" 
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-medium" style="color: var(--text-primary);">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-secondary);">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" name="email" class="form-control form-control-lg" 
                                       value="<?= htmlspecialchars($user['email']) ?>" 
                                       placeholder="Enter your email"
                                       style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" 
                                       required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Academic Details -->
        <div class="col-xl-8">
            <div class="card border-0">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                        <i class="bi bi-book-fill me-2" style="color: var(--sidebar-active);"></i>Academic Information
                    </h5>
                </div>
                <div class="card-body p-4">
                    <!-- Course Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-medium" style="color: var(--text-primary);">Course</label>
                        <select name="course_id" class="form-select form-select-lg" 
                                style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" 
                                required>
                            <option value="" disabled>Select your course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $user['course_id'] == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Semester Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-medium" style="color: var(--text-primary);">Current Semester</label>
                        <select name="year_id" class="form-select form-select-lg" 
                                style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" 
                                required>
                            <option value="" disabled>Select your semester</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y['id'] ?>" <?= $user['year_id'] == $y['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($y['year_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Session Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-medium" style="color: var(--text-primary);">Batch Session</label>
                        <select name="session_id" class="form-select form-select-lg" 
                                style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" 
                                required>
                            <option value="" disabled>Select your batch session</option>
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $user['session_id'] == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['session_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Current Values Display -->
                    <div class="p-3 rounded-3 mb-4" style="background: var(--sidebar-hover);">
                        <h6 class="fw-bold mb-3" style="color: var(--text-primary);">Current Academic Status</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <small style="color: var(--text-muted);">Course</small>
                                <p class="mb-0 fw-medium" style="color: var(--text-primary);"><?= htmlspecialchars($user['course_name'] ?? 'Not set') ?></p>
                            </div>
                            <div class="col-md-4">
                                <small style="color: var(--text-muted);">Semester</small>
                                <p class="mb-0 fw-medium" style="color: var(--text-primary);"><?= htmlspecialchars($user['year_name'] ?? 'Not set') ?></p>
                            </div>
                            <div class="col-md-4">
                                <small style="color: var(--text-muted);">Session</small>
                                <p class="mb-0 fw-medium" style="color: var(--text-primary);"><?= htmlspecialchars($user['session_name'] ?? 'Not set') ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="profile.php" class="btn btn-lg" 
                           style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 12px 30px;">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg" 
                                style="padding: 12px 30px; background: linear-gradient(90deg, var(--sidebar-active), #8b5cf6); border: none;">
                            <i class="bi bi-check-circle-fill me-2"></i>Save Changes
                        </button>
                    </div>
                </div>
            </div>

            <!-- Additional Information Card -->
            <div class="card border-0 mt-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-shield-check" style="color: var(--sidebar-active); font-size: 2rem;"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1" style="color: var(--text-primary);">Profile Privacy</h6>
                            <p class="mb-0" style="color: var(--text-muted);">Your information is securely stored and only visible to authorized faculty and administrators.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- JavaScript for Image Preview -->
<script>
document.getElementById('photoInput')?.addEventListener('change', function(e) {
    if (e.target.files && e.target.files[0]) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('profilePreview').src = ev.target.result;
        };
        reader.readAsDataURL(e.target.files[0]);
        
        // Show file name
        const fileName = e.target.files[0].name;
        const fileSize = (e.target.files[0].size / 1024).toFixed(1);
        
        // Create or update file info
        let infoDiv = document.getElementById('fileInfo');
        if (!infoDiv) {
            infoDiv = document.createElement('div');
            infoDiv.id = 'fileInfo';
            infoDiv.className = 'mt-2 text-start small';
            e.target.parentElement.parentElement.appendChild(infoDiv);
        }
        infoDiv.innerHTML = `<span style="color: var(--sidebar-active);">Selected: ${fileName} (${fileSize} KB)</span>`;
    }
});

// Form validation before submit
document.querySelector('form')?.addEventListener('submit', function(e) {
    const name = document.querySelector('input[name="name"]').value.trim();
    const email = document.querySelector('input[name="email"]').value.trim();
    
    if (!name || !email) {
        e.preventDefault();
        alert('Please fill in all required fields.');
    }
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);
</script>

<!-- Custom CSS for this page -->
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

/* Photo upload hover effect */
label[for="photoInput"] {
    transition: all 0.3s ease;
}

label[for="photoInput"]:hover {
    background: linear-gradient(90deg, var(--sidebar-active), #8b5cf6) !important;
    transform: translate(10%, 10%) scale(1.1) !important;
}

/* Button styles */
.btn {
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-outline-secondary:hover {
    background: var(--sidebar-hover);
    border-color: var(--border-color);
    color: var(--text-primary);
}

/* Alert styles */
.alert {
    border: none;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1.25rem;
    }
    
    .btn-lg {
        padding: 10px 20px !important;
        font-size: 1rem;
    }
}

/* Loading state */
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

/* Custom cursor */
.cursor-pointer {
    cursor: pointer;
}

/* File info animation */
#fileInfo {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<?php
// CLOSE MAIN / BODY / HTML
include('../includes/footer.php');
?>