<?php
// session_start();
include('../config/db.php');
include('../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current data + options for dropdowns
$stmt = $pdo->prepare("SELECT s.name, s.email, s.profile_photo, s.course_id, s.year_id, s.session_id,
                              c.course_name, y.year_name, sess.session_name
                       FROM students s
                       LEFT JOIN courses c ON s.course_id = c.id
                       LEFT JOIN years y ON s.year_id = y.id
                       LEFT JOIN sessions sess ON s.session_id = sess.id
                       WHERE s.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Fetch all options for dropdowns
$courses = $pdo->query("SELECT id, course_name FROM courses ORDER BY course_name")->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT id, year_name FROM years ORDER BY year_name")->fetchAll(PDO::FETCH_ASSOC);
$sessions = $pdo->query("SELECT id, session_name FROM sessions ORDER BY session_name")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission (name, email, course, year, session, photo)
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
        $error_message = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        // Check email uniqueness
        $check = $pdo->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
        $check->execute([$email, $user_id]);
        if ($check->fetch()) {
            $error_message = "This email is already in use.";
        } else {
            // Handle photo upload
            $photo_filename = $user['profile_photo'];
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/profiles/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $file = $_FILES['profile_photo'];
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (in_array($ext, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
                    $photo_filename = 'profile_' . $user_id . '.' . $ext;
                    $filepath = $upload_dir . $photo_filename;
                    move_uploaded_file($file['tmp_name'], $filepath);
                } else {
                    $error_message = "Invalid photo (JPG/PNG/GIF, max 2MB).";
                }
            }

            if (empty($error_message)) {
                $update = $pdo->prepare("UPDATE students SET name = ?, email = ?, profile_photo = ?, course_id = ?, year_id = ?, session_id = ? WHERE id = ?");
                if ($update->execute([$name, $email, $photo_filename, $course_id, $year_id, $session_id, $user_id])) {
                    $success_message = "Profile updated successfully!";
                    // Refresh user data
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $user['profile_photo'] = $photo_filename;
                    $user['course_id'] = $course_id;
                    $user['year_id'] = $year_id;
                    $user['session_id'] = $session_id;
                    $_SESSION['name'] = $name; // Update session name
                } else {
                    $error_message = "Failed to update profile.";
                }
            }
        }
    }
}

$photo_path = $user['profile_photo']
    ? '../uploads/profiles/' . htmlspecialchars($user['profile_photo'])
    : 'https://via.placeholder.com/180?text=No+Photo';
?>

<main class="main-content">
    <div class="px-4 pt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title mb-0">✏️ Edit Profile</h2>
            <a href="profile.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> View Profile
            </a>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="row g-4">
                <!-- Left: Photo + Basic Info -->
                <div class="col-lg-4">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-body text-center p-4">
                            <div class="position-relative d-inline-block mb-4">
                                <img src="<?= $photo_path ?>?v=<?= time() ?>" alt="Profile Photo"
                                     class="rounded-circle shadow" style="width: 180px; height: 180px; object-fit: cover; border: 5px solid white;">
                                <label class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-3 shadow"
                                       style="transform: translate(20%, 20%); cursor: pointer;">
                                    <i class="bi bi-camera-fill fs-5"></i>
                                    <input type="file" name="profile_photo" accept="image/*" 
                                           onchange="previewImage(this)" style="position: absolute; inset: 0; opacity: 0; cursor: pointer;">
                                </label>
                            </div>

                            <div class="mt-4">
                                <label class="form-label fw-semibold">Full Name</label>
                                <input type="text" name="name" class="form-control form-control-lg mb-3" 
                                       value="<?= htmlspecialchars($user['name']) ?>" required>

                                <label class="form-label fw-semibold">Email Address</label>
                                <div class="input-group mb-3">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control form-control-lg" 
                                           value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Academic Details -->
                <div class="col-lg-8">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-body p-5">
                            <h4 class="fw-bold text-primary border-bottom pb-3 mb-4">Academic Information</h4>

                            <div class="row mb-4">
                                <div class="col-md-4 fw-semibold">Course</div>
                                <div class="col-md-8">
                                    <select name="course_id" class="form-select form-select-lg" required>
                                        <?php foreach ($courses as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $user['course_id'] == $c['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['course_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-4 fw-semibold">Current Semester</div>
                                <div class="col-md-8">
                                    <select name="year_id" class="form-select form-select-lg" required>
                                        <?php foreach ($years as $y): ?>
                                            <option value="<?= $y['id'] ?>" <?= $user['year_id'] == $y['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($y['year_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-5">
                                <div class="col-md-4 fw-semibold">Batch Session</div>
                                <div class="col-md-8">
                                    <select name="session_id" class="form-select form-select-lg" required>
                                        <?php foreach ($sessions as $s): ?>
                                            <option value="<?= $s['id'] ?>" <?= $user['session_id'] == $s['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($s['session_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="d-grid d-md-flex justify-content-md-end gap-3">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="bi bi-check-circle-fill me-2"></i> Save Changes
                                </button>
                                <a href="profile.php" class="btn btn-outline-secondary btn-lg px-5">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
// Live preview of new photo
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.querySelector('.rounded-circle.shadow').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>