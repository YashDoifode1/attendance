<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Get course ID from URL
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$course_id) {
    header("Location: courses.php");
    exit();
}

// Fetch course details
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM students WHERE course_id = c.id) as total_students,
           (SELECT COUNT(*) FROM subjects WHERE course_id = c.id) as total_subjects,
           (SELECT COUNT(*) FROM schedule WHERE course_id = c.id) as total_schedules
    FROM courses c
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header("Location: courses.php");
    exit();
}

// Fetch students in this course
$stmt = $pdo->prepare("
    SELECT s.*, y.year_name, sess.session_name
    FROM students s
    LEFT JOIN years y ON s.year_id = y.id
    LEFT JOIN sessions sess ON s.session_id = sess.id
    WHERE s.course_id = ? AND s.role = 'student'
    ORDER BY s.name ASC
");
$stmt->execute([$course_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch subjects for this course
$stmt = $pdo->prepare("
    SELECT s.*, y.year_name, sess.session_name,
           (SELECT COUNT(*) FROM schedule WHERE subject_id = s.id) as schedule_count
    FROM subjects s
    LEFT JOIN years y ON s.year_id = y.id
    LEFT JOIN sessions sess ON s.session_id = sess.id
    WHERE s.course_id = ?
    ORDER BY y.year_name, s.subject_name
");
$stmt->execute([$course_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch faculty teaching in this course
$stmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.name, s.email, s.profile_photo,
           COUNT(DISTINCT sub.id) as subjects_taught,
           COUNT(DISTINCT sch.id) as schedule_count
    FROM students s
    JOIN schedule sch ON s.id = sch.faculty_id
    JOIN subjects sub ON sch.subject_id = sub.id
    WHERE sub.course_id = ? AND s.role = 'faculty'
    GROUP BY s.id
    ORDER BY s.name ASC
");
$stmt->execute([$course_id]);
$faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch schedule for this course
$stmt = $pdo->prepare("
    SELECT sch.*, 
           sub.subject_name,
           f.name as faculty_name,
           y.year_name,
           sess.session_name
    FROM schedule sch
    JOIN subjects sub ON sch.subject_id = sub.id
    JOIN students f ON sch.faculty_id = f.id
    JOIN years y ON sch.year_id = y.id
    JOIN sessions sess ON sch.session_id = sess.id
    WHERE sch.course_id = ?
    ORDER BY FIELD(sch.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), sch.start_time
");
$stmt->execute([$course_id]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT a.student_id) as active_students,
        COUNT(a.id) as total_records,
        SUM(a.status IN ('Present', 'Late')) as present_count,
        ROUND(AVG(CASE WHEN a.status IN ('Present', 'Late') THEN 100 ELSE 0 END), 1) as avg_attendance
    FROM attendance a
    WHERE a.course_id = ?
");
$stmt->execute([$course_id]);
$attendance_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get monthly attendance trend
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(a.date, '%Y-%m') as month,
        COUNT(DISTINCT a.student_id) as students,
        ROUND(AVG(CASE WHEN a.status IN ('Present', 'Late') THEN 100 ELSE 0 END), 1) as attendance_rate
    FROM attendance a
    WHERE a.course_id = ? AND a.date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(a.date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$stmt->execute([$course_id]);
$monthly_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

include('includes/sidebar_header.php');
?>

<!-- Page Header with Breadcrumb -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="dashboard.php" style="color: var(--text-muted); text-decoration: none;">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="courses.php" style="color: var(--text-muted); text-decoration: none;">Courses</a></li>
                <li class="breadcrumb-item active" style="color: var(--text-primary);" aria-current="page">Course Details</li>
            </ol>
        </nav>
        <h4 class="mb-0 fw-bold" style="color: var(--text-primary);"><?= htmlspecialchars($course['course_name']) ?></h4>
        <p class="mb-0" style="color: var(--text-muted);">Comprehensive overview and management</p>
    </div>
    <div class="d-flex gap-2">
        <a href="edit_course.php?id=<?= $course_id ?>" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);">
            <i class="bi bi-pencil me-2"></i>Edit Course
        </a>
        <button class="btn btn-primary" onclick="exportCourseData()">
            <i class="bi bi-download me-2"></i>Export Data
        </button>
    </div>
</div>

<!-- Course Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Total Students -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-people-fill" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">Enrolled</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= number_format($course['total_students']) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Total Students</p>
                <div class="mt-3">
                    <small style="color: var(--text-muted);">Active: <?= $attendance_stats['active_students'] ?? 0 ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Faculty Members -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-person-badge-fill" style="color: var(--success); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">Teaching</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= count($faculty) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Faculty Members</p>
            </div>
        </div>
    </div>

    <!-- Subjects -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-journal-bookmark-fill" style="color: var(--warning); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">Subjects</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= $course['total_subjects'] ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Active Subjects</p>
            </div>
        </div>
    </div>

    <!-- Attendance Rate -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(139, 92, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-check-circle-fill" style="color: #8b5cf6; font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">Overall</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= $attendance_stats['avg_attendance'] ?? 0 ?>%</h3>
                <p class="mb-0" style="color: var(--text-muted);">Attendance Rate</p>
                <div class="progress mt-3" style="height: 6px; background: var(--border-color);">
                    <div class="progress-bar" role="progressbar" style="width: <?= $attendance_stats['avg_attendance'] ?? 0 ?>%; background: linear-gradient(90deg, var(--sidebar-active), #8b5cf6);"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" id="courseTabs" role="tablist" style="border-bottom-color: var(--border-color);">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" style="color: var(--text-secondary); background: transparent; border: none;" onmouseover="this.style.color='var(--sidebar-active)'" onmouseout="this.style.color='var(--text-secondary)'">
            <i class="bi bi-info-circle me-2"></i>Overview
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab" style="color: var(--text-secondary); background: transparent; border: none;" onmouseover="this.style.color='var(--sidebar-active)'" onmouseout="this.style.color='var(--text-secondary)'">
            <i class="bi bi-people me-2"></i>Students (<?= count($students) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjects" type="button" role="tab" style="color: var(--text-secondary); background: transparent; border: none;" onmouseover="this.style.color='var(--sidebar-active)'" onmouseout="this.style.color='var(--text-secondary)'">
            <i class="bi bi-book me-2"></i>Subjects (<?= count($subjects) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="faculty-tab" data-bs-toggle="tab" data-bs-target="#faculty" type="button" role="tab" style="color: var(--text-secondary); background: transparent; border: none;" onmouseover="this.style.color='var(--sidebar-active)'" onmouseout="this.style.color='var(--text-secondary)'">
            <i class="bi bi-person-badge me-2"></i>Faculty (<?= count($faculty) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab" style="color: var(--text-secondary); background: transparent; border: none;" onmouseover="this.style.color='var(--sidebar-active)'" onmouseout="this.style.color='var(--text-secondary)'">
            <i class="bi bi-calendar-week me-2"></i>Schedule (<?= count($schedules) ?>)
        </button>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="courseTabsContent">
    <!-- Overview Tab -->
    <div class="tab-pane fade show active" id="overview" role="tabpanel">
        <div class="row g-4">
            <!-- Course Info Card -->
            <div class="col-lg-4">
                <div class="card border-0">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4" style="color: var(--text-primary);">
                            <i class="bi bi-info-circle me-2" style="color: var(--sidebar-active);"></i>Course Information
                        </h5>
                        
                        <div class="mb-4">
                            <label class="text-muted mb-1">Course ID</label>
                            <div class="p-3 rounded" style="background: var(--sidebar-hover); color: var(--text-primary);">
                                #<?= str_pad($course['id'], 4, '0', STR_PAD_LEFT) ?>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="text-muted mb-1">Course Name</label>
                            <div class="p-3 rounded" style="background: var(--sidebar-hover); color: var(--text-primary); font-weight: 500;">
                                <?= htmlspecialchars($course['course_name']) ?>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="text-muted mb-1">Created At</label>
                            <div class="p-3 rounded" style="background: var(--sidebar-hover); color: var(--text-primary);">
                                <i class="bi bi-calendar me-2"></i><?= date('F d, Y', strtotime($course['created_at'])) ?>
                            </div>
                        </div>
                        
                        <div>
                            <label class="text-muted mb-1">Status</label>
                            <div class="p-3 rounded" style="background: var(--sidebar-hover);">
                                <span class="badge bg-success">Active</span>
                                <span class="badge bg-info ms-2">Current Semester</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats & Actions -->
            <div class="col-lg-8">
                <div class="row g-4">
                    <!-- Attendance Trend -->
                    <div class="col-12">
                        <div class="card border-0">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                                    <i class="bi bi-graph-up me-2" style="color: var(--sidebar-active);"></i>Attendance Trend (Last 6 Months)
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="attendanceTrendChart" style="height: 200px;"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
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
                                        <a href="add_student.php?course_id=<?= $course_id ?>" class="text-decoration-none">
                                            <div class="p-3 text-center rounded-3" style="background: rgba(59, 130, 246, 0.05); border: 1px solid var(--border-color);">
                                                <div class="mb-2" style="color: var(--sidebar-active); font-size: 1.5rem;">➕</div>
                                                <h6 class="mb-0 small" style="color: var(--text-primary);">Add Student</h6>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <a href="add_subject.php?course_id=<?= $course_id ?>" class="text-decoration-none">
                                            <div class="p-3 text-center rounded-3" style="background: rgba(16, 185, 129, 0.05); border: 1px solid var(--border-color);">
                                                <div class="mb-2" style="color: var(--success); font-size: 1.5rem;">📚</div>
                                                <h6 class="mb-0 small" style="color: var(--text-primary);">Add Subject</h6>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <a href="create_schedule.php?course_id=<?= $course_id ?>" class="text-decoration-none">
                                            <div class="p-3 text-center rounded-3" style="background: rgba(245, 158, 11, 0.05); border: 1px solid var(--border-color);">
                                                <div class="mb-2" style="color: var(--warning); font-size: 1.5rem;">📅</div>
                                                <h6 class="mb-0 small" style="color: var(--text-primary);">Schedule</h6>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <a href="attendance.php?course_id=<?= $course_id ?>" class="text-decoration-none">
                                            <div class="p-3 text-center rounded-3" style="background: rgba(139, 92, 246, 0.05); border: 1px solid var(--border-color);">
                                                <div class="mb-2" style="color: #8b5cf6; font-size: 1.5rem;">⏱️</div>
                                                <h6 class="mb-0 small" style="color: var(--text-primary);">Take Attendance</h6>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Students Tab -->
    <div class="tab-pane fade" id="students" role="tabpanel">
        <div class="card border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-people me-2" style="color: var(--sidebar-active);"></i>Enrolled Students
                </h5>
                <div class="d-flex gap-2">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3" style="color: var(--text-muted);"></i>
                        <input type="text" id="studentSearch" class="form-control form-control-sm ps-5" placeholder="Search students..." style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary); width: 250px;">
                    </div>
                    <a href="add_student.php?course_id=<?= $course_id ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Add Student
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="color: var(--text-primary);">
                        <thead style="background: var(--card-bg); border-bottom: 1px solid var(--border-color);">
                            <tr>
                                <th class="ps-4 py-3">Student</th>
                                <th class="py-3">Email</th>
                                <th class="py-3">Year</th>
                                <th class="py-3">Session</th>
                                <th class="pe-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentTable">
                            <?php if ($students): ?>
                                <?php foreach ($students as $student): ?>
                                <tr class="student-row">
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($student['profile_photo']): ?>
                                                <img src="<?= htmlspecialchars($student['profile_photo']) ?>" alt="" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle" style="width: 32px; height: 32px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                                    <span style="color: var(--sidebar-active); font-weight: 600;"><?= strtoupper(substr($student['name'], 0, 1)) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($student['name']) ?>
                                        </div>
                                    </td>
                                    <td class="py-3" style="color: var(--text-secondary);"><?= htmlspecialchars($student['email']) ?></td>
                                    <td class="py-3" style="color: var(--text-secondary);"><?= htmlspecialchars($student['year_name'] ?? 'N/A') ?></td>
                                    <td class="py-3" style="color: var(--text-secondary);"><?= htmlspecialchars($student['session_name'] ?? 'N/A') ?></td>
                                    <td class="pe-4 py-3">
                                        <div class="dropdown">
                                            <button class="btn btn-sm" style="background: var(--sidebar-hover); border: 1px solid var(--border-color); color: var(--text-secondary);" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="view_student.php?id=<?= $student['id'] ?>"><i class="bi bi-eye me-2"></i>View</a></li>
                                                <li><a class="dropdown-item" href="edit_student.php?id=<?= $student['id'] ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="confirmDeleteStudent(<?= $student['id'] ?>)"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4" style="color: var(--text-muted);">
                                        No students enrolled in this course yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Subjects Tab -->
    <div class="tab-pane fade" id="subjects" role="tabpanel">
        <div class="card border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-book me-2" style="color: var(--sidebar-active);"></i>Course Subjects
                </h5>
                <div class="d-flex gap-2">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3" style="color: var(--text-muted);"></i>
                        <input type="text" id="subjectSearch" class="form-control form-control-sm ps-5" placeholder="Search subjects..." style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary); width: 250px;">
                    </div>
                    <a href="add_subject.php?course_id=<?= $course_id ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Add Subject
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="color: var(--text-primary);">
                        <thead style="background: var(--card-bg); border-bottom: 1px solid var(--border-color);">
                            <tr>
                                <th class="ps-4 py-3">Subject Name</th>
                                <th class="py-3">Year</th>
                                <th class="py-3">Session</th>
                                <th class="py-3">Schedules</th>
                                <th class="pe-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subjectTable">
                            <?php if ($subjects): ?>
                                <?php foreach ($subjects as $subject): ?>
                                <tr class="subject-row">
                                    <td class="ps-4 py-3 fw-medium"><?= htmlspecialchars($subject['subject_name']) ?></td>
                                    <td class="py-3" style="color: var(--text-secondary);"><?= htmlspecialchars($subject['year_name'] ?? 'N/A') ?></td>
                                    <td class="py-3" style="color: var(--text-secondary);"><?= htmlspecialchars($subject['session_name'] ?? 'N/A') ?></td>
                                    <td class="py-3">
                                        <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">
                                            <?= $subject['schedule_count'] ?> schedules
                                        </span>
                                    </td>
                                    <td class="pe-4 py-3">
                                        <div class="dropdown">
                                            <button class="btn btn-sm" style="background: var(--sidebar-hover); border: 1px solid var(--border-color); color: var(--text-secondary);" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="view_subject.php?id=<?= $subject['id'] ?>"><i class="bi bi-eye me-2"></i>View</a></li>
                                                <li><a class="dropdown-item" href="edit_subject.php?id=<?= $subject['id'] ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                                <li><a class="dropdown-item" href="subject_schedule.php?id=<?= $subject['id'] ?>"><i class="bi bi-calendar me-2"></i>Schedule</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="confirmDeleteSubject(<?= $subject['id'] ?>)"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4" style="color: var(--text-muted);">
                                        No subjects added to this course yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Faculty Tab -->
    <div class="tab-pane fade" id="faculty" role="tabpanel">
        <div class="row g-4">
            <?php if ($faculty): ?>
                <?php foreach ($faculty as $member): ?>
                <div class="col-xl-4 col-md-6">
                    <div class="card border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <?php if ($member['profile_photo']): ?>
                                    <img src="<?= htmlspecialchars($member['profile_photo']) ?>" alt="" class="rounded-circle" style="width: 64px; height: 64px; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 64px; height: 64px; background: linear-gradient(135deg, var(--sidebar-active), #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <span class="fw-bold text-white" style="font-size: 1.5rem;"><?= strtoupper(substr($member['name'], 0, 1)) ?></span>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h5 class="fw-bold mb-1" style="color: var(--text-primary);"><?= htmlspecialchars($member['name']) ?></h5>
                                    <p class="mb-0" style="color: var(--text-muted);"><?= htmlspecialchars($member['email']) ?></p>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-3 mb-3">
                                <div class="text-center p-2 rounded" style="background: rgba(59, 130, 246, 0.05); flex: 1;">
                                    <div class="fw-bold" style="color: var(--sidebar-active);"><?= $member['subjects_taught'] ?></div>
                                    <small style="color: var(--text-muted);">Subjects</small>
                                </div>
                                <div class="text-center p-2 rounded" style="background: rgba(16, 185, 129, 0.05); flex: 1;">
                                    <div class="fw-bold" style="color: var(--success);"><?= $member['schedule_count'] ?></div>
                                    <small style="color: var(--text-muted);">Classes</small>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="view_faculty.php?id=<?= $member['id'] ?>" class="btn btn-sm" style="background: var(--sidebar-hover); color: var(--text-primary); border: 1px solid var(--border-color);">
                                    View Profile
                                </a>
                                <a href="faculty_schedule.php?id=<?= $member['id'] ?>" class="text-decoration-none" style="color: var(--sidebar-active);">
                                    Schedule <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="card border-0">
                        <div class="card-body text-center py-5">
                            <div style="width: 80px; height: 80px; background: rgba(59, 130, 246, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                <i class="bi bi-person-badge" style="color: var(--sidebar-active); font-size: 2rem;"></i>
                            </div>
                            <h5 style="color: var(--text-primary);">No Faculty Assigned</h5>
                            <p style="color: var(--text-muted);" class="mb-4">Assign faculty members to teach in this course</p>
                            <a href="assign_faculty.php?course_id=<?= $course_id ?>" class="btn btn-primary">
                                <i class="bi bi-plus-lg me-2"></i>Assign Faculty
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Schedule Tab -->
    <div class="tab-pane fade" id="schedule" role="tabpanel">
        <div class="card border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-calendar-week me-2" style="color: var(--sidebar-active);"></i>Weekly Schedule
                </h5>
                <a href="create_schedule.php?course_id=<?= $course_id ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Add Schedule
                </a>
            </div>
            <div class="card-body">
                <?php if ($schedules): ?>
                    <?php
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    foreach ($days as $day):
                        $day_schedules = array_filter($schedules, function($s) use ($day) {
                            return $s['day'] === $day;
                        });
                        if (empty($day_schedules)) continue;
                    ?>
                    <div class="mb-4">
                        <h6 class="fw-bold mb-3" style="color: var(--text-primary);">
                            <span class="badge p-2" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);"><?= $day ?></span>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm" style="color: var(--text-primary);">
                                <thead style="background: var(--sidebar-hover);">
                                    <tr>
                                        <th class="py-2">Time</th>
                                        <th class="py-2">Subject</th>
                                        <th class="py-2">Faculty</th>
                                        <th class="py-2">Year</th>
                                        <th class="py-2">Session</th>
                                        <th class="py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($day_schedules as $schedule): ?>
                                    <tr>
                                        <td class="py-2"><?= date('h:i A', strtotime($schedule['start_time'])) ?> - <?= date('h:i A', strtotime($schedule['end_time'])) ?></td>
                                        <td class="py-2 fw-medium"><?= htmlspecialchars($schedule['subject_name']) ?></td>
                                        <td class="py-2"><?= htmlspecialchars($schedule['faculty_name']) ?></td>
                                        <td class="py-2"><?= htmlspecialchars($schedule['year_name']) ?></td>
                                        <td class="py-2"><?= htmlspecialchars($schedule['session_name']) ?></td>
                                        <td class="py-2">
                                            <a href="edit_schedule.php?id=<?= $schedule['id'] ?>" class="text-decoration-none me-2" style="color: var(--sidebar-active);">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="#" onclick="confirmDeleteSchedule(<?= $schedule['id'] ?>)" class="text-decoration-none text-danger">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div style="width: 80px; height: 80px; background: rgba(59, 130, 246, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                            <i class="bi bi-calendar-x" style="color: var(--sidebar-active); font-size: 2rem;"></i>
                        </div>
                        <h5 style="color: var(--text-primary);">No Schedule Found</h5>
                        <p style="color: var(--text-muted);" class="mb-4">Create a schedule for this course</p>
                        <a href="create_schedule.php?course_id=<?= $course_id ?>" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2"></i>Create Schedule
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modals -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-danger" style="color: #ef4444;">
                    <i class="bi bi-exclamation-triangle me-2"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p style="color: var(--text-primary);">Are you sure you want to delete this student? This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn" style="background: var(--sidebar-hover); color: var(--text-primary); border: 1px solid var(--border-color);" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteStudentBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Attendance Trend Chart
const ctx = document.getElementById('attendanceTrendChart').getContext('2d');
const gradient = ctx.createLinearGradient(0, 0, 0, 200);
gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: [<?php foreach (array_reverse($monthly_trend) as $trend): ?>'<?= date('M Y', strtotime($trend['month'].'-01')) ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'Attendance Rate',
            data: [<?php foreach (array_reverse($monthly_trend) as $trend): ?><?= $trend['attendance_rate'] ?>,<?php endforeach; ?>],
            borderColor: '#3b82f6',
            backgroundColor: gradient,
            borderWidth: 2,
            pointBackgroundColor: '#3b82f6',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1a1e26',
                titleColor: '#f0f3f8',
                bodyColor: '#cbd5e1'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                grid: { color: '#2a2f3a' },
                ticks: { color: '#94a3b8', callback: value => value + '%' }
            },
            x: {
                grid: { display: false },
                ticks: { color: '#94a3b8' }
            }
        }
    }
});

// Student Search
document.getElementById('studentSearch')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('.student-row').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Subject Search
document.getElementById('subjectSearch')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('.subject-row').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Delete confirmation functions
function confirmDeleteStudent(id) {
    document.getElementById('confirmDeleteStudentBtn').href = `delete_student.php?id=${id}`;
    new bootstrap.Modal(document.getElementById('deleteStudentModal')).show();
}

function confirmDeleteSubject(id) {
    if (confirm('Are you sure you want to delete this subject? This will affect all related schedules and attendance records.')) {
        window.location.href = `delete_subject.php?id=<?= $course_id ?>&subject_id=${id}`;
    }
}

function confirmDeleteSchedule(id) {
    if (confirm('Are you sure you want to delete this schedule?')) {
        window.location.href = `delete_schedule.php?id=<?= $course_id ?>&schedule_id=${id}`;
    }
}

// Export function
function exportCourseData() {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold" style="color: var(--text-primary);">
                        <i class="bi bi-download me-2" style="color: var(--sidebar-active);"></i>
                        Export Course Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p style="color: var(--text-secondary); mb-3">Choose data to export:</p>
                    <div class="list-group mb-3">
                        <label class="list-group-item" style="background: var(--sidebar-hover); border-color: var(--border-color); color: var(--text-primary);">
                            <input class="form-check-input me-2" type="checkbox" value="students" checked> Students List
                        </label>
                        <label class="list-group-item" style="background: var(--sidebar-hover); border-color: var(--border-color); color: var(--text-primary);">
                            <input class="form-check-input me-2" type="checkbox" value="subjects" checked> Subjects
                        </label>
                        <label class="list-group-item" style="background: var(--sidebar-hover); border-color: var(--border-color); color: var(--text-primary);">
                            <input class="form-check-input me-2" type="checkbox" value="schedule" checked> Schedule
                        </label>
                        <label class="list-group-item" style="background: var(--sidebar-hover); border-color: var(--border-color); color: var(--text-primary);">
                            <input class="form-check-input me-2" type="checkbox" value="attendance"> Attendance Records
                        </label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn flex-fill" style="background: var(--sidebar-hover); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="window.location.href='export_course.php?id=<?= $course_id ?>&format=pdf'">
                            <i class="bi bi-file-pdf me-2" style="color: #ef4444;"></i>PDF
                        </button>
                        <button class="btn flex-fill" style="background: var(--sidebar-hover); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="window.location.href='export_course.php?id=<?= $course_id ?>&format=excel'">
                            <i class="bi bi-file-excel me-2" style="color: #10b981;"></i>Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
    
    modal.addEventListener('hidden.bs.modal', () => modal.remove());
}

// Tab persistence
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash) {
        const tab = document.querySelector(`[data-bs-target="${hash}"]`);
        if (tab) {
            tab.click();
        }
    }
    
    // Update hash when tab changes
    const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            window.location.hash = e.target.getAttribute('data-bs-target');
        });
    });
});
</script>

<style>
/* Breadcrumb styles */
.breadcrumb-item + .breadcrumb-item::before {
    color: var(--text-muted);
}

/* Tab styles */
.nav-tabs .nav-link {
    transition: all 0.2s;
    position: relative;
}

.nav-tabs .nav-link:hover {
    color: var(--sidebar-active) !important;
}

.nav-tabs .nav-link.active {
    color: var(--sidebar-active) !important;
    border-bottom: 2px solid var(--sidebar-active) !important;
}

/* Table styles */
.table {
    --bs-table-bg: transparent;
    --bs-table-color: var(--text-primary);
}

.table-hover tbody tr:hover {
    background: var(--sidebar-hover) !important;
}

/* List group styles */
.list-group-item {
    background: var(--sidebar-hover);
    border-color: var(--border-color);
    color: var(--text-primary);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .nav-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
    }
    
    .nav-tabs .nav-link {
        white-space: nowrap;
    }
}
</style>

<?php include('includes/footer.php'); ?>