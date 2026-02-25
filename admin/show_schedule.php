<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$alertType = "";

// Fetch data for dropdowns with counts
$faculties = $pdo->query("
    SELECT s.id, s.name, s.email, s.avatar,
           COUNT(sch.id) as assigned_classes
    FROM students s
    LEFT JOIN schedule sch ON s.id = sch.faculty_id
    WHERE s.role = 'faculty'
    GROUP BY s.id
    ORDER BY s.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$courses = $pdo->query("
    SELECT c.id, c.course_name,
           COUNT(DISTINCT s.id) as subject_count,
           COUNT(DISTINCT sch.id) as schedule_count
    FROM courses c
    LEFT JOIN subjects s ON c.id = s.course_id
    LEFT JOIN schedule sch ON c.id = sch.course_id
    GROUP BY c.id
    ORDER BY c.course_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$subjects = $pdo->query("
    SELECT s.id, s.subject_name, s.course_id, c.course_name,
           COUNT(sch.id) as schedule_count
    FROM subjects s
    JOIN courses c ON s.course_id = c.id
    LEFT JOIN schedule sch ON s.id = sch.subject_id
    GROUP BY s.id
    ORDER BY s.subject_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$sessions = $pdo->query("
    SELECT s.id, s.session_name,
           COUNT(sch.id) as schedule_count
    FROM sessions s
    LEFT JOIN schedule sch ON s.id = sch.session_id
    GROUP BY s.id
    ORDER BY s.session_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$years = $pdo->query("
    SELECT y.id, y.year_name,
           COUNT(sch.id) as schedule_count
    FROM years y
    LEFT JOIN schedule sch ON y.id = sch.year_id
    GROUP BY y.id
    ORDER BY y.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $faculty_id  = $_POST['faculty_id'] ?? '';
    $course_id   = $_POST['course_id'] ?? '';
    $subject_id  = $_POST['subject_id'] ?? '';
    $session_id  = $_POST['session_id'] ?? '';
    $year_id     = $_POST['year_id'] ?? '';
    $day         = $_POST['day'] ?? '';
    $start_time  = $_POST['start_time'] ?? '';
    $end_time    = $_POST['end_time'] ?? '';

    // Basic validation
    if (empty($faculty_id) || empty($course_id) || empty($subject_id) || empty($session_id) || empty($year_id) || empty($day) || empty($start_time) || empty($end_time)) {
        $message = "All fields are required.";
        $alertType = "error";
    } elseif ($start_time >= $end_time) {
        $message = "End time must be after start time.";
        $alertType = "error";
    } else {
        // Check for schedule conflict (same faculty, day, overlapping time)
        $conflictCheck = $pdo->prepare("
            SELECT COUNT(*) FROM schedule 
            WHERE faculty_id = ? AND day = ? 
            AND (
                (start_time <= ? AND end_time > ?) OR 
                (start_time < ? AND end_time >= ?)
            )
        ");
        $conflictCheck->execute([$faculty_id, $day, $end_time, $start_time, $end_time, $start_time]);
        
        if ($conflictCheck->fetchColumn() > 0) {
            $message = "This faculty already has a class scheduled at this time on $day.";
            $alertType = "error";
        } else {
            // Check if subject is already assigned to another faculty at same time (optional)
            $subjectConflict = $pdo->prepare("
                SELECT COUNT(*) FROM schedule 
                WHERE subject_id = ? AND day = ? AND course_id = ? AND year_id = ? AND session_id = ?
                AND (
                    (start_time <= ? AND end_time > ?) OR 
                    (start_time < ? AND end_time >= ?)
                )
            ");
            $subjectConflict->execute([$subject_id, $day, $course_id, $year_id, $session_id, $end_time, $start_time, $end_time, $start_time]);
            
            if ($subjectConflict->fetchColumn() > 0) {
                $message = "This subject already has a class scheduled at this time.";
                $alertType = "error";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO schedule 
                        (faculty_id, course_id, subject_id, session_id, year_id, day, start_time, end_time) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$faculty_id, $course_id, $subject_id, $session_id, $year_id, $day, $start_time, $end_time]);
                    $message = "Class schedule assigned successfully!";
                    $alertType = "success";
                    
                    // Clear POST to prevent resubmission
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                    exit();
                } catch (Exception $e) {
                    $message = "Error assigning schedule: " . $e->getMessage();
                    $alertType = "error";
                }
            }
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Class schedule assigned successfully!";
    $alertType = "success";
}

// Get today's schedule for quick reference
$todaySchedule = $pdo->prepare("
    SELECT sch.*, f.name as faculty_name, c.course_name, sub.subject_name, y.year_name, sess.session_name
    FROM schedule sch
    JOIN students f ON sch.faculty_id = f.id
    JOIN courses c ON sch.course_id = c.id
    JOIN subjects sub ON sch.subject_id = sub.id
    JOIN years y ON sch.year_id = y.id
    JOIN sessions sess ON sch.session_id = sess.id
    WHERE sch.day = ?
    ORDER BY sch.start_time ASC
    LIMIT 5
");
$todaySchedule->execute([date('l')]);
$recentSchedules = $todaySchedule->fetchAll(PDO::FETCH_ASSOC);

// Include shared layout
include('includes/sidebar_header.php');
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Assign Class Schedule</h4>
        <p class="mb-0" style="color: var(--text-muted);">Assign faculty to teach specific subjects in a course, semester, and time slot.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="location.reload()">
            <i class="bi bi-arrow-repeat me-2"></i>Refresh
        </button>
        <a href="view_schedule.php" class="btn btn-primary">
            <i class="bi bi-calendar-week me-2"></i>View Full Schedule
        </a>
    </div>
</div>

<!-- Success/Error Alert -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $alertType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-<?= $alertType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-person-badge-fill" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= count($faculties) ?></h3>
                        <small style="color: var(--text-muted);">Total Faculty</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-book-fill" style="color: var(--success); font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= count($subjects) ?></h3>
                        <small style="color: var(--text-muted);">Subjects</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-calendar-range-fill" style="color: var(--warning); font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= count($courses) ?></h3>
                        <small style="color: var(--text-muted);">Courses</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 48px; height: 48px; background: rgba(139, 92, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-clock-history" style="color: #8b5cf6; font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= count($recentSchedules) ?></h3>
                        <small style="color: var(--text-muted);">Today's Classes</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Schedule Assignment Form Card -->
    <div class="col-xl-7">
        <div class="card border-0">
            <div class="card-header">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-plus-circle me-2" style="color: var(--sidebar-active);"></i>Create New Schedule
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="scheduleForm">
                    <div class="row g-4">
                        <!-- Faculty -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium" style="color: var(--text-secondary);">Faculty Member</label>
                            <select name="faculty_id" id="faculty_id" required class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                <option value="">-- Select Faculty --</option>
                                <?php foreach ($faculties as $faculty): ?>
                                    <option value="<?= $faculty['id'] ?>" data-classes="<?= $faculty['assigned_classes'] ?>">
                                        <?= htmlspecialchars($faculty['name']) ?> 
                                        (<?= $faculty['assigned_classes'] ?> classes)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Course -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium" style="color: var(--text-secondary);">Course</label>
                            <select name="course_id" id="course_id" required onchange="filterSubjects()" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                <option value="">-- Select Course --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>" data-subjects="<?= $course['subject_count'] ?>">
                                        <?= htmlspecialchars($course['course_name']) ?> 
                                        (<?= $course['subject_count'] ?> subjects)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Subject (Dynamic) -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium" style="color: var(--text-secondary);">Subject</label>
                            <select name="subject_id" id="subject_id" required class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                <option value="">-- Select Subject --</option>
                            </select>
                        </div>

                        <!-- Session -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium" style="color: var(--text-secondary);">Session / Batch</label>
                            <select name="session_id" id="session_id" required class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                <option value="">-- Select Session --</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?= $session['id'] ?>">
                                        <?= htmlspecialchars($session['session_name']) ?>
                                        <?php if ($session['schedule_count'] > 0): ?>
                                            (<?= $session['schedule_count'] ?> scheduled)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Year / Semester -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium" style="color: var(--text-secondary);">Year / Semester</label>
                            <select name="year_id" id="year_id" required class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                <option value="">-- Select Year --</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?= $year['id'] ?>">
                                        <?= htmlspecialchars($year['year_name']) ?>
                                        <?php if ($year['schedule_count'] > 0): ?>
                                            (<?= $year['schedule_count'] ?> scheduled)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Day -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium" style="color: var(--text-secondary);">Day of Week</label>
                            <select name="day" id="day" required class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                <option value="">-- Select Day --</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                        </div>

                        <!-- Time Range -->
                        <div class="col-md-3">
                            <label class="form-label fw-medium" style="color: var(--text-secondary);">Start Time</label>
                            <input type="time" name="start_time" id="start_time" required class="form-control" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-medium" style="color: var(--text-secondary);">End Time</label>
                            <input type="time" name="end_time" id="end_time" required class="form-control" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                        </div>

                        <!-- Submit Button -->
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100 py-3">
                                <i class="bi bi-plus-lg me-2"></i>Assign Class Schedule
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Quick Tips Card -->
        <div class="card border-0 mt-4">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 40px; height: 40px; background: rgba(59, 130, 246, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="bi bi-lightbulb" style="color: var(--warning);"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-2" style="color: var(--text-primary);">Scheduling Tips</h6>
                        <ul class="small mb-0" style="color: var(--text-muted); padding-left: 1rem;">
                            <li>Ensure no time conflicts for faculty members</li>
                            <li>Consider break times between classes</li>
                            <li>Verify subject belongs to selected course</li>
                            <li>Check faculty workload before assigning</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Today's Schedule Preview -->
    <div class="col-xl-5">
        <div class="card border-0 h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-calendar-day me-2" style="color: var(--sidebar-active);"></i>Today's Schedule
                </h5>
                <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">
                    <?= date('l, F j, Y') ?>
                </span>
            </div>
            <div class="card-body">
                <?php if (!empty($recentSchedules)): ?>
                    <div class="timeline">
                        <?php foreach ($recentSchedules as $schedule): ?>
                            <div class="timeline-item mb-4">
                                <div class="d-flex gap-3">
                                    <div class="timeline-time" style="min-width: 80px;">
                                        <span class="fw-bold" style="color: var(--sidebar-active);">
                                            <?= date('h:i A', strtotime($schedule['start_time'])) ?>
                                        </span>
                                    </div>
                                    <div class="timeline-content flex-grow-1">
                                        <div class="p-3 rounded-3" style="background: rgba(59, 130, 246, 0.05); border: 1px solid var(--border-color);">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="fw-bold mb-0" style="color: var(--text-primary);">
                                                    <?= htmlspecialchars($schedule['subject_name']) ?>
                                                </h6>
                                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                                                    <?= htmlspecialchars($schedule['day']) ?>
                                                </span>
                                            </div>
                                            <p class="small mb-1" style="color: var(--text-secondary);">
                                                <i class="bi bi-person-badge me-1"></i>
                                                Faculty: <?= htmlspecialchars($schedule['faculty_name']) ?>
                                            </p>
                                            <p class="small mb-0" style="color: var(--text-muted);">
                                                <i class="bi bi-book me-1"></i>
                                                <?= htmlspecialchars($schedule['course_name']) ?> - 
                                                <?= htmlspecialchars($schedule['year_name']) ?> - 
                                                <?= htmlspecialchars($schedule['session_name']) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="view_schedule.php" class="text-decoration-none" style="color: var(--sidebar-active);">
                                View Full Schedule <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <div style="width: 64px; height: 64px; background: rgba(59, 130, 246, 0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <i class="bi bi-calendar-x" style="color: var(--sidebar-active); font-size: 2rem;"></i>
                        </div>
                        <h6 style="color: var(--text-primary);">No classes scheduled today</h6>
                        <p style="color: var(--text-muted);" class="small mb-0">Use the form to create new schedules.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic Subject Filtering Script -->
<script>
    const allSubjects = <?= json_encode($subjects, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function filterSubjects() {
        const courseId = document.getElementById('course_id').value;
        const subjectSelect = document.getElementById('subject_id');

        // Clear current options
        subjectSelect.innerHTML = '<option value="">-- Select Subject --</option>';

        if (!courseId) return;

        allSubjects.forEach(subject => {
            if (subject.course_id == courseId) {
                const option = document.createElement('option');
                option.value = subject.id;
                option.textContent = subject.subject_name + 
                    (subject.schedule_count > 0 ? ` (${subject.schedule_count} scheduled)` : '');
                subjectSelect.appendChild(option);
            }
        });
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        filterSubjects();
        
        // Time validation
        const form = document.getElementById('scheduleForm');
        form.addEventListener('submit', function(e) {
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            
            if (startTime && endTime && startTime >= endTime) {
                e.preventDefault();
                alert('End time must be after start time.');
            }
        });
    });

    // Auto-dismiss alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
</script>

<style>
/* Timeline styling */
.timeline-item {
    position: relative;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 40px;
    top: 45px;
    bottom: -15px;
    width: 2px;
    background: var(--border-color);
}

/* Form control focus effect */
.form-select:focus, .form-control:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    border-color: var(--sidebar-active);
}

/* Card hover effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2) !important;
}

/* Alert styles */
.alert {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border-color: var(--success);
    color: var(--success);
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border-color: #ef4444;
    color: #ef4444;
}

/* Timeline content hover */
.timeline-content .p-3:hover {
    background: rgba(59, 130, 246, 0.1) !important;
    transform: translateX(5px);
    transition: all 0.3s ease;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .timeline-item .d-flex {
        flex-direction: column;
        gap: 0.5rem !important;
    }
    
    .timeline-item:not(:last-child)::before {
        left: 20px;
    }
    
    .timeline-time {
        margin-left: 10px;
    }
}
</style>

<?php include('includes/footer.php'); ?>