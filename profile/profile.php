<?php
// session_start();
include('../config/db.php');
include('../includes/header.php'); // Your professional sidebar + top header

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle profile photo upload
$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $upload_dir = '../uploads/profiles/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $file = $_FILES['profile_photo'];
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (in_array($ext, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
        $filename = 'profile_' . $user_id . '.' . $ext;
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = $pdo->prepare("UPDATE students SET profile_photo = ? WHERE id = ?");
            $stmt->execute([$filename, $user_id]);
            $upload_message = '<div class="alert alert-success alert-dismissible fade show">Photo updated! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } else {
            $upload_message = '<div class="alert alert-danger alert-dismissible fade show">Upload failed. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    } else {
        $upload_message = '<div class="alert alert-danger alert-dismissible fade show">Invalid file (JPG/PNG/GIF, max 2MB). <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// Fetch user data
$stmt = $pdo->prepare("SELECT s.name, s.email, s.profile_photo, c.course_name, y.year_name, sess.session_name 
                       FROM students s
                       LEFT JOIN courses c ON s.course_id = c.id
                       LEFT JOIN years y ON s.year_id = y.id
                       LEFT JOIN sessions sess ON s.session_id = sess.id
                       WHERE s.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$photo_path = $user['profile_photo']
    ? '../uploads/profiles/' . htmlspecialchars($user['profile_photo'])
    : 'https://via.placeholder.com/180?text=No+Photo'; // Or use a local default avatar
?>

<main class="main-content">
    <div class="px-4 pt-4 pb-5">
        <h2 class="page-title mb-4">👤 My Profile</h2>

        <?= $upload_message ?>

        <div class="row g-4">
            <!-- Profile Summary Card -->
            <div class="col-lg-4">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body text-center p-4">
                        <div class="position-relative d-inline-block mb-4">
                            <img src="<?= $photo_path ?>?v=<?= time() ?>" alt="Profile Photo"
                                 class="rounded-circle shadow" style="width: 180px; height: 180px; object-fit: cover; border: 5px solid white;">
                            <label class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-3 shadow cursor-pointer"
                                   style="transform: translate(20%, 20%); cursor: pointer;">
                                <i class="bi bi-camera-fill fs-5"></i>
                                <form method="POST" enctype="multipart/form-data" class="d-inline">
                                    <input type="file" name="profile_photo" accept="image/*" 
                                           onchange="this.form.submit()" 
                                           style="position: absolute; inset: 0; opacity: 0; cursor: pointer;">
                                </form>
                            </label>
                        </div>

                        <h4 class="mb-1"><?= htmlspecialchars($user['name']) ?></h4>
                        <p class="text-muted mb-3">Student</p>

                        <div class="text-start mt-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-envelope text-primary me-3"></i>
                                <span class="small"><?= htmlspecialchars($user['email']) ?></span>
                            </div>
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-mortarboard-fill text-primary me-3"></i>
                                <span class="small">ID: <?= str_pad($user_id, 4, '0', STR_PAD_LEFT) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resume-Style Details -->
            <div class="col-lg-8">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body p-5">
                        <h4 class="fw-bold text-primary border-bottom pb-3 mb-4">Education Details</h4>

                        <div class="row mb-4">
                            <div class="col-sm-4 fw-semibold">Course</div>
                            <div class="col-sm-8"><?= htmlspecialchars($user['course_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-sm-4 fw-semibold">Current Semester</div>
                            <div class="col-sm-8"><?= htmlspecialchars($user['year_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-sm-4 fw-semibold">Batch Session</div>
                            <div class="col-sm-8"><?= htmlspecialchars($user['session_name'] ?? 'N/A') ?></div>
                        </div>

                        <hr class="my-5">

                        <h4 class="fw-bold text-primary border-bottom pb-3 mb-4">Quick Actions</h4>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="../student/dashboard.php" class="btn btn-primary px-4 py-2">
                                <i class="bi bi-speedometer2 me-2"></i> Dashboard
                            </a>
                            <a href="../student/view_attendance.php" class="btn btn-outline-primary px-4 py-2">
                                <i class="bi bi-calendar-check me-2"></i> View Attendance
                            </a>
                            <a href="../auth/logout.php" class="btn btn-outline-danger px-4 py-2">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </div>

                        <div class="mt-4">
                            <button onclick="window.print()" class="btn btn-success px-4 py-2">
                                <i class="bi bi-printer me-2"></i> Print Profile
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    @media print {
        .top-header, .sidebar, .sidebar-overlay { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        body { background: white !important; }
        .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
</style>

<script>
    // Optional: Live preview of uploaded image
    document.querySelector('input[type="file"]')?.addEventListener('change', function(e) {
        if (e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = ev => {
                document.querySelector('.rounded-circle.shadow').src = ev.target.result;
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });
</script>