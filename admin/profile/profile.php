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

// Handle profile photo upload
$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $upload_dir = '../../uploads/profiles/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $file = $_FILES['profile_photo'];
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (in_array($ext, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
        // Delete old photo if exists
        $stmt = $pdo->prepare("SELECT profile_photo FROM students WHERE id = ?");
        $stmt->execute([$user_id]);
        $old_photo = $stmt->fetchColumn();
        
        if ($old_photo && file_exists($upload_dir . $old_photo)) {
            unlink($upload_dir . $old_photo);
        }
        
        $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = $pdo->prepare("UPDATE students SET profile_photo = ? WHERE id = ?");
            $stmt->execute([$filename, $user_id]);
            $upload_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>Profile photo updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        } else {
            $upload_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>Upload failed. Please try again.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    } else {
        $upload_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Invalid file. Please upload JPG, PNG, or GIF (max 2MB).
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
}

// Fetch user data with additional statistics
$stmt = $pdo->prepare("
    SELECT 
        s.*,
        c.course_name,
        y.year_name,
        sess.session_name,
        (SELECT COUNT(*) FROM attendance WHERE student_id = s.id) as total_attendance,
        (SELECT COUNT(*) FROM attendance WHERE student_id = s.id AND status IN ('Present', 'Late')) as total_present,
        (SELECT COUNT(*) FROM attendance WHERE student_id = s.id AND date = CURDATE()) as today_attendance,
        (SELECT status FROM attendance WHERE student_id = s.id AND date = CURDATE() ORDER BY created_at DESC LIMIT 1) as today_status
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

// Calculate attendance percentage
$attendance_percentage = $user['total_attendance'] > 0 
    ? round(($user['total_present'] / $user['total_attendance']) * 100, 1) 
    : 0;

// Get attendance trend for last 7 days
$stmt = $pdo->prepare("
    SELECT 
        date,
        status
    FROM attendance 
    WHERE student_id = ? 
    AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY date DESC
");
$stmt->execute([$user_id]);
$recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly attendance summary
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(status IN ('Present', 'Late')) as present
    FROM attendance 
    WHERE student_id = ? 
    AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month DESC
");
$stmt->execute([$user_id]);
$monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's schedule if exists
$stmt = $pdo->prepare("
    SELECT s.*, sub.subject_name 
    FROM schedule s
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    WHERE s.course_id = ? AND s.year_id = ? AND s.session_id = ?
    AND s.day = DAYNAME(CURDATE())
    ORDER BY s.start_time
");
$stmt->execute([$user['course_id'], $user['year_id'], $user['session_id']]);
$today_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

$photo_path = $user['profile_photo']
    ? '../../uploads/profiles/' . htmlspecialchars($user['profile_photo'])
    : 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&size=180&background=3b82f6&color=fff';

// Determine attendance status color
$attendance_color = 'success';
if ($attendance_percentage < 75) {
    $attendance_color = 'danger';
} elseif ($attendance_percentage < 85) {
    $attendance_color = 'warning';
}

// INCLUDE SIDEBAR + HEADER based on role
if ($user_role === 'admin') {
    include('../../admin/includes/sidebar_header.php');
} else {
    include('../../includes/header.php');
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">My Profile</h4>
        <p class="mb-0" style="color: var(--text-muted);">Manage your personal information and view your academic progress.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="edit_profile.php" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);">
            <i class="bi bi-pencil-square me-2"></i>Edit Profile
        </a>
        <a href="change_password.php" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);">
            <i class="bi bi-key me-2"></i>Change Password
        </a>
        <button class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="window.print()">
            <i class="bi bi-printer me-2"></i>Print
        </button>
    </div>
</div>

<?= $upload_message ?>

<!-- Profile Overview Cards -->
<div class="row g-4 mb-4">
    <!-- Profile Card -->
    <div class="col-xl-4">
        <div class="card border-0 h-100">
            <div class="card-body text-center p-4">
                <!-- Profile Photo with Upload Overlay -->
                <div class="position-relative d-inline-block mb-4">
                    <img src="<?= $photo_path ?>?v=<?= time() ?>" 
                         alt="Profile Photo"
                         class="rounded-circle shadow" 
                         id="profileImage"
                         style="width: 160px; height: 160px; object-fit: cover; border: 4px solid var(--card-bg);">
                    
                    <!-- Upload Button -->
                    <label class="position-absolute bottom-0 end-0 rounded-circle p-2 shadow cursor-pointer"
                           style="background: var(--sidebar-active); transform: translate(10%, 10%); cursor: pointer;">
                        <i class="bi bi-camera-fill text-white"></i>
                        <form method="POST" enctype="multipart/form-data" class="d-inline" id="photoUploadForm">
                            <input type="file" name="profile_photo" id="photoInput" accept="image/*" 
                                   style="position: absolute; inset: 0; opacity: 0; cursor: pointer;">
                        </form>
                    </label>
                </div>

                <h4 class="fw-bold mb-1" style="color: var(--text-primary);"><?= htmlspecialchars($user['name']) ?></h4>
                <p class="mb-2">
                    <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active); padding: 6px 12px;">
                        <i class="bi bi-mortarboard-fill me-1"></i><?= ucfirst($user_role) ?>
                    </span>
                </p>

                <!-- Today's Status -->
                <?php if ($user['today_attendance'] > 0): ?>
                <div class="mt-3">
                    <span class="badge bg-<?= $user['today_status'] == 'Present' ? 'success' : ($user['today_status'] == 'Late' ? 'warning' : 'danger') ?> bg-opacity-10" 
                          style="color: <?= $user['today_status'] == 'Present' ? 'var(--success)' : ($user['today_status'] == 'Late' ? 'var(--warning)' : 'var(--danger)') ?>; padding: 8px 16px;">
                        <i class="bi bi-<?= $user['today_status'] == 'Present' ? 'check-circle' : ($user['today_status'] == 'Late' ? 'exclamation-triangle' : 'x-circle') ?> me-1"></i>
                        Today: <?= $user['today_status'] ?? 'Not Marked' ?>
                    </span>
                </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="d-flex justify-content-center gap-3 mt-4">
                    <div class="text-center">
                        <h5 class="fw-bold mb-0" style="color: var(--text-primary);"><?= $user['total_attendance'] ?? 0 ?></h5>
                        <small style="color: var(--text-muted);">Total Classes</small>
                    </div>
                    <div class="vr" style="background: var(--border-color);"></div>
                    <div class="text-center">
                        <h5 class="fw-bold mb-0" style="color: var(--text-primary);"><?= $user['total_present'] ?? 0 ?></h5>
                        <small style="color: var(--text-muted);">Present</small>
                    </div>
                    <div class="vr" style="background: var(--border-color);"></div>
                    <div class="text-center">
                        <h5 class="fw-bold mb-0" style="color: var(--<?= $attendance_color ?>);"><?= $attendance_percentage ?>%</h5>
                        <small style="color: var(--text-muted);">Attendance</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact & Academic Info -->
    <div class="col-xl-8">
        <div class="row g-4">
            <!-- Contact Information -->
            <div class="col-md-6">
                <div class="card border-0 h-100">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                            <i class="bi bi-info-circle-fill me-2" style="color: var(--sidebar-active);"></i>Contact Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div style="width: 40px; height: 40px; background: rgba(59, 130, 246, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-envelope-fill" style="color: var(--sidebar-active);"></i>
                            </div>
                            <div class="ms-3">
                                <small style="color: var(--text-muted);">Email Address</small>
                                <p class="mb-0 fw-medium" style="color: var(--text-primary);"><?= htmlspecialchars($user['email']) ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div style="width: 40px; height: 40px; background: rgba(16, 185, 129, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-person-badge-fill" style="color: var(--success);"></i>
                            </div>
                            <div class="ms-3">
                                <small style="color: var(--text-muted);">Student ID</small>
                                <p class="mb-0 fw-medium" style="color: var(--text-primary);">STU-<?= str_pad($user_id, 5, '0', STR_PAD_LEFT) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic Information -->
            <div class="col-md-6">
                <div class="card border-0 h-100">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                            <i class="bi bi-book-fill me-2" style="color: var(--sidebar-active);"></i>Academic Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small style="color: var(--text-muted);">Course</small>
                            <p class="mb-2 fw-medium" style="color: var(--text-primary);"><?= htmlspecialchars($user['course_name'] ?? 'Not Assigned') ?></p>
                        </div>
                        <div class="mb-3">
                            <small style="color: var(--text-muted);">Current Semester</small>
                            <p class="mb-2 fw-medium" style="color: var(--text-primary);"><?= htmlspecialchars($user['year_name'] ?? 'Not Assigned') ?></p>
                        </div>
                        <div>
                            <small style="color: var(--text-muted);">Batch Session</small>
                            <p class="mb-0 fw-medium" style="color: var(--text-primary);"><?= htmlspecialchars($user['session_name'] ?? 'Not Assigned') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Schedule -->
            <?php if (!empty($today_schedule)): ?>
            <div class="col-12">
                <div class="card border-0">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                            <i class="bi bi-calendar-day me-2" style="color: var(--sidebar-active);"></i>Today's Schedule (<?= date('l, F d, Y') ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead style="background: var(--card-bg);">
                                    <tr>
                                        <th style="color: var(--text-primary);">Subject</th>
                                        <th style="color: var(--text-primary);">Time</th>
                                        <th style="color: var(--text-primary);">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($today_schedule as $class): ?>
                                    <tr>
                                        <td style="color: var(--text-primary);"><?= htmlspecialchars($class['subject_name']) ?></td>
                                        <td style="color: var(--text-secondary);"><?= date('h:i A', strtotime($class['start_time'])) ?> - <?= date('h:i A', strtotime($class['end_time'])) ?></td>
                                        <td>
                                            <?php
                                            $current_time = date('H:i:s');
                                            if ($current_time < $class['start_time']) {
                                                echo '<span class="badge bg-info bg-opacity-10" style="color: #0dcaf0;">Upcoming</span>';
                                            } elseif ($current_time > $class['end_time']) {
                                                echo '<span class="badge bg-secondary bg-opacity-10" style="color: var(--text-muted);">Completed</span>';
                                            } else {
                                                echo '<span class="badge bg-success bg-opacity-10" style="color: var(--success);">Ongoing</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Attendance Summary -->
            <div class="col-12">
                <div class="card border-0">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                            <i class="bi bi-graph-up me-2" style="color: var(--sidebar-active);"></i>Attendance Overview
                        </h5>
                        <span class="badge bg-<?= $attendance_color ?> bg-opacity-10" style="color: var(--<?= $attendance_color ?>);">
                            Overall: <?= $attendance_percentage ?>%
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-3" style="height: 10px; background: var(--border-color);">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?= $attendance_percentage ?>%; background: <?= $attendance_percentage >= 75 ? 'linear-gradient(90deg, var(--success), #10b981)' : ($attendance_percentage >= 60 ? 'linear-gradient(90deg, var(--warning), #f59e0b)' : 'linear-gradient(90deg, var(--danger), #ef4444)') ?>;" 
                                 aria-valuenow="<?= $attendance_percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>
                                <small style="color: var(--text-muted);">Present: <?= $user['total_present'] ?? 0 ?></small>
                            </div>
                            <div>
                                <small style="color: var(--text-muted);">Total Classes: <?= $user['total_attendance'] ?? 0 ?></small>
                            </div>
                            <div>
                                <small style="color: var(--text-muted);">Absent: <?= ($user['total_attendance'] - $user['total_present']) ?? 0 ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Attendance & Monthly Stats -->
<div class="row g-4">
    <!-- Recent Attendance -->
    <div class="col-xl-6">
        <div class="card border-0 h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-clock-history me-2" style="color: var(--sidebar-active);"></i>Recent Attendance (Last 7 Days)
                </h5>
                <a href="view_attendance.php" class="text-decoration-none" style="color: var(--sidebar-active);">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background: var(--card-bg); border-bottom: 1px solid var(--border-color);">
                            <tr>
                                <th class="ps-4 py-3" style="color: var(--text-primary);">Date</th>
                                <th class="py-3" style="color: var(--text-primary);">Day</th>
                                <th class="py-3 text-center" style="color: var(--text-primary);">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_attendance)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4" style="color: var(--text-muted);">
                                        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                        No recent attendance records
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_attendance as $record): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td class="ps-4 py-3" style="color: var(--text-primary);">
                                        <?= date('M d, Y', strtotime($record['date'])) ?>
                                    </td>
                                    <td class="py-3" style="color: var(--text-secondary);">
                                        <?= date('l', strtotime($record['date'])) ?>
                                    </td>
                                    <td class="py-3 text-center">
                                        <?php
                                        $status = $record['status'];
                                        $badgeClass = '';
                                        $icon = '';
                                        if ($status == 'Present') {
                                            $badgeClass = 'success';
                                            $icon = 'bi-check-circle-fill';
                                        } elseif ($status == 'Late') {
                                            $badgeClass = 'warning';
                                            $icon = 'bi-exclamation-triangle-fill';
                                        } else {
                                            $badgeClass = 'danger';
                                            $icon = 'bi-x-circle-fill';
                                        }
                                        ?>
                                        <span class="badge bg-<?= $badgeClass ?> bg-opacity-10" style="color: var(--<?= $badgeClass ?>); padding: 6px 12px;">
                                            <i class="bi <?= $icon ?> me-1"></i><?= $status ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Statistics -->
    <div class="col-xl-6">
        <div class="card border-0 h-100">
            <div class="card-header">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-bar-chart-fill me-2" style="color: var(--sidebar-active);"></i>Monthly Attendance Summary
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($monthly_stats)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-bar-chart fs-1 d-block mb-3" style="color: var(--text-muted);"></i>
                        <p style="color: var(--text-muted);">No monthly data available</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($monthly_stats as $month): 
                        $month_percentage = $month['total'] > 0 ? round(($month['present'] / $month['total']) * 100, 1) : 0;
                    ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span style="color: var(--text-primary); font-weight: 500;"><?= date('F Y', strtotime($month['month'] . '-01')) ?></span>
                            <span class="fw-bold" style="color: <?= $month_percentage >= 75 ? 'var(--success)' : ($month_percentage >= 60 ? 'var(--warning)' : 'var(--danger)') ?>;">
                                <?= $month_percentage ?>%
                            </span>
                        </div>
                        <div class="progress" style="height: 8px; background: var(--border-color);">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?= $month_percentage ?>%; background: <?= $month_percentage >= 75 ? 'linear-gradient(90deg, var(--success), #10b981)' : ($month_percentage >= 60 ? 'linear-gradient(90deg, var(--warning), #f59e0b)' : 'linear-gradient(90deg, var(--danger), #ef4444)') ?>;" 
                                 aria-valuenow="<?= $month_percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="mt-1 d-flex justify-content-between">
                            <small style="color: var(--text-muted);">Present: <?= $month['present'] ?></small>
                            <small style="color: var(--text-muted);">Total: <?= $month['total'] ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0">
            <div class="card-header">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-lightning-charge me-2" style="color: var(--sidebar-active);"></i>Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3 col-6">
                        <a href="dashboard.php" class="text-decoration-none">
                            <div class="p-4 text-center rounded-3 action-card" 
                               style="background: rgba(59, 130, 246, 0.05); border: 1px solid var(--border-color); transition: all 0.3s;">
                                <div class="mb-2" style="color: var(--sidebar-active); font-size: 1.8rem;">📊</div>
                                <h6 class="mb-0" style="color: var(--text-primary);">Dashboard</h6>
                                <small style="color: var(--text-muted);">Overview</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="view_attendance.php" class="text-decoration-none">
                            <div class="p-4 text-center rounded-3 action-card"
                               style="background: rgba(16, 185, 129, 0.05); border: 1px solid var(--border-color); transition: all 0.3s;">
                                <div class="mb-2" style="color: var(--success); font-size: 1.8rem;">📅</div>
                                <h6 class="mb-0" style="color: var(--text-primary);">Attendance</h6>
                                <small style="color: var(--text-muted);">View records</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="timetable.php" class="text-decoration-none">
                            <div class="p-4 text-center rounded-3 action-card"
                               style="background: rgba(245, 158, 11, 0.05); border: 1px solid var(--border-color); transition: all 0.3s;">
                                <div class="mb-2" style="color: var(--warning); font-size: 1.8rem;">⏱️</div>
                                <h6 class="mb-0" style="color: var(--text-primary);">Timetable</h6>
                                <small style="color: var(--text-muted);">Class schedule</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="edit_profile.php" class="text-decoration-none">
                            <div class="p-4 text-center rounded-3 action-card"
                               style="background: rgba(139, 92, 246, 0.05); border: 1px solid var(--border-color); transition: all 0.3s;">
                                <div class="mb-2" style="color: #8b5cf6; font-size: 1.8rem;">✏️</div>
                                <h6 class="mb-0" style="color: var(--text-primary);">Edit Profile</h6>
                                <small style="color: var(--text-muted);">Update info</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
// Auto-submit photo upload
document.getElementById('photoInput')?.addEventListener('change', function(e) {
    if (e.target.files && e.target.files[0]) {
        // Show preview
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('profileImage').src = ev.target.result;
        };
        reader.readAsDataURL(e.target.files[0]);
        
        // Auto submit form
        document.getElementById('photoUploadForm').submit();
    }
});

// Card hover effects
document.querySelectorAll('.action-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
        this.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = 'none';
    });
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);
</script>

<!-- Print Styles -->
<style>
@media print {
    .sidebar, .top-header, .btn, .action-card, 
    label[for="photoInput"], .cursor-pointer, .card-header .btn-close {
        display: none !important;
    }
    
    .main-content {
        margin: 0 !important;
        padding: 20px !important;
        background: white !important;
    }
    
    body {
        background: white !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    
    .badge {
        border: 1px solid #ddd !important;
        color: #333 !important;
        background: #f5f5f5 !important;
    }
    
    * {
        color: #333 !important;
    }
    
    .progress {
        border: 1px solid #ddd !important;
    }
}

/* Custom styles */
.cursor-pointer {
    cursor: pointer;
}

.card {
    transition: all 0.3s ease;
}

.action-card {
    transition: all 0.3s ease;
}

.table {
    --bs-table-bg: transparent;
    --bs-table-color: var(--text-primary);
}

.table-hover tbody tr:hover {
    background: var(--sidebar-hover) !important;
}

.progress-bar {
    transition: width 1s ease;
}

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
    color: var(--danger);
    border-left: 4px solid var(--danger);
}

/* Responsive */
@media (max-width: 768px) {
    .card-body {
        padding: 1.25rem;
    }
    
    .table {
        font-size: 0.85rem;
    }
}
</style>

<?php
// CLOSE MAIN / BODY / HTML
include('../includes/footer.php');
?>