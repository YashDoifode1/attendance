<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch filter data
$courses = $pdo->query("SELECT id, course_name FROM courses ORDER BY course_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT id, year_name FROM years ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$sessions = $pdo->query("SELECT id, session_name FROM sessions ORDER BY session_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$faculties = $pdo->query("SELECT id, name FROM students WHERE role = 'faculty' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get filter values
$selectedCourse = $_GET['course'] ?? '';
$selectedYear = $_GET['year'] ?? '';
$selectedSession = $_GET['session'] ?? '';
$selectedFaculty = $_GET['faculty'] ?? '';
$selectedDay = $_GET['day'] ?? '';

// Build query based on filters
$query = "
    SELECT 
        sch.*,
        f.name as faculty_name,
        f.email as faculty_email,
        c.course_name,
        sub.subject_name,
        y.year_name,
        sess.session_name
    FROM schedule sch
    JOIN students f ON sch.faculty_id = f.id
    JOIN courses c ON sch.course_id = c.id
    JOIN subjects sub ON sch.subject_id = sub.id
    JOIN years y ON sch.year_id = y.id
    JOIN sessions sess ON sch.session_id = sess.id
    WHERE 1=1
";
$params = [];

if (!empty($selectedCourse)) {
    $query .= " AND sch.course_id = ?";
    $params[] = $selectedCourse;
}
if (!empty($selectedYear)) {
    $query .= " AND sch.year_id = ?";
    $params[] = $selectedYear;
}
if (!empty($selectedSession)) {
    $query .= " AND sch.session_id = ?";
    $params[] = $selectedSession;
}
if (!empty($selectedFaculty)) {
    $query .= " AND sch.faculty_id = ?";
    $params[] = $selectedFaculty;
}
if (!empty($selectedDay)) {
    $query .= " AND sch.day = ?";
    $params[] = $selectedDay;
}

$query .= " ORDER BY 
    FIELD(sch.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
    sch.start_time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group schedules by day
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$groupedSchedules = [];
foreach ($days as $day) {
    $groupedSchedules[$day] = [];
}
foreach ($schedules as $schedule) {
    $groupedSchedules[$schedule['day']][] = $schedule;
}

// Get statistics
$totalClasses = count($schedules);
$uniqueFaculty = $pdo->query("SELECT COUNT(DISTINCT faculty_id) FROM schedule")->fetchColumn();
$uniqueSubjects = $pdo->query("SELECT COUNT(DISTINCT subject_id) FROM schedule")->fetchColumn();

// Include shared layout
include('includes/sidebar_header.php');
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Class Schedule</h4>
        <p class="mb-0" style="color: var(--text-muted);">View and manage all scheduled classes across courses, years, and sessions.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="window.print()">
            <i class="bi bi-printer me-2"></i>Print
        </button>
        <a href="create_schedule.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Add New Schedule
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-calendar-week" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= $totalClasses ?></h3>
                        <small style="color: var(--text-muted);">Total Classes</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-person-badge-fill" style="color: var(--success); font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= $uniqueFaculty ?></h3>
                        <small style="color: var(--text-muted);">Faculty Assigned</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-book-fill" style="color: var(--warning); font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= $uniqueSubjects ?></h3>
                        <small style="color: var(--text-muted);">Subjects Scheduled</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 48px; height: 48px; background: rgba(139, 92, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-clock" style="color: #8b5cf6; font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0" style="color: var(--text-primary);">
                            <?php 
                            $busiestDay = '';
                            $maxClasses = 0;
                            foreach ($groupedSchedules as $day => $classes) {
                                if (count($classes) > $maxClasses) {
                                    $maxClasses = count($classes);
                                    $busiestDay = $day;
                                }
                            }
                            echo $busiestDay ? substr($busiestDay, 0, 3) : 'N/A';
                            ?>
                        </h3>
                        <small style="color: var(--text-muted);">Busiest Day</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card border-0 mb-4">
    <div class="card-header">
        <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
            <i class="bi bi-funnel me-2" style="color: var(--sidebar-active);"></i>Filter Schedule
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-4">
            <div class="col-md-2">
                <label class="form-label fw-medium" style="color: var(--text-secondary);">Course</label>
                <select name="course" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id'] ?>" <?= $selectedCourse == $course['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label fw-medium" style="color: var(--text-secondary);">Year</label>
                <select name="year" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                    <option value="">All Years</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= $year['id'] ?>" <?= $selectedYear == $year['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year['year_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label fw-medium" style="color: var(--text-secondary);">Session</label>
                <select name="session" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                    <option value="">All Sessions</option>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?= $session['id'] ?>" <?= $selectedSession == $session['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($session['session_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label fw-medium" style="color: var(--text-secondary);">Faculty</label>
                <select name="faculty" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                    <option value="">All Faculty</option>
                    <?php foreach ($faculties as $faculty): ?>
                        <option value="<?= $faculty['id'] ?>" <?= $selectedFaculty == $faculty['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($faculty['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label fw-medium" style="color: var(--text-secondary);">Day</label>
                <select name="day" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                    <option value="">All Days</option>
                    <?php foreach ($days as $day): ?>
                        <option value="<?= $day ?>" <?= $selectedDay == $day ? 'selected' : '' ?>>
                            <?= $day ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-2"></i>Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Schedule Display -->
<?php if (!empty($schedules)): ?>
    <!-- View Toggle -->
    <div class="d-flex justify-content-end mb-3">
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-sm active" style="background: var(--sidebar-active); color: white;" id="gridViewBtn">
                <i class="bi bi-grid-3x3-gap-fill"></i> Grid
            </button>
            <button type="button" class="btn btn-sm" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" id="listViewBtn">
                <i class="bi bi-list-ul"></i> List
            </button>
        </div>
    </div>

    <!-- Grid View (by day) -->
    <div id="gridView">
        <div class="row g-4">
            <?php foreach ($groupedSchedules as $day => $daySchedules): ?>
                <?php if (!empty($daySchedules)): ?>
                    <div class="col-xl-6">
                        <div class="card border-0 h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                                    <i class="bi bi-calendar-day me-2" style="color: var(--sidebar-active);"></i><?= $day ?>
                                </h5>
                                <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">
                                    <?= count($daySchedules) ?> classes
                                </span>
                            </div>
                            <div class="card-body">
                                <?php foreach ($daySchedules as $schedule): ?>
                                    <div class="schedule-item mb-3 p-3 rounded-3" style="background: rgba(59, 130, 246, 0.05); border: 1px solid var(--border-color);">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="fw-bold mb-1" style="color: var(--text-primary);">
                                                    <?= htmlspecialchars($schedule['subject_name']) ?>
                                                </h6>
                                                <p class="small mb-0" style="color: var(--text-secondary);">
                                                    <i class="bi bi-person-badge me-1"></i>
                                                    <?= htmlspecialchars($schedule['faculty_name']) ?>
                                                </p>
                                            </div>
                                            <span class="badge" style="background: var(--sidebar-active); color: white;">
                                                <?= date('h:i A', strtotime($schedule['start_time'])) ?> - 
                                                <?= date('h:i A', strtotime($schedule['end_time'])) ?>
                                            </span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <small style="color: var(--text-muted);">
                                                <i class="bi bi-book me-1"></i><?= htmlspecialchars($schedule['course_name']) ?>
                                            </small>
                                            <small style="color: var(--text-muted);">
                                                <i class="bi bi-layers me-1"></i><?= htmlspecialchars($schedule['year_name']) ?>
                                            </small>
                                            <small style="color: var(--text-muted);">
                                                <i class="bi bi-people me-1"></i><?= htmlspecialchars($schedule['session_name']) ?>
                                            </small>
                                        </div>
                                        <div class="mt-2 d-flex justify-content-end gap-2">
                                            <a href="edit_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-sm" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="delete_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;" onclick="return confirm('Are you sure you want to delete this schedule?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- List View (compact table) -->
    <div id="listView" style="display: none;">
        <div class="card border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="color: var(--text-primary);">
                        <thead style="background: var(--card-bg); border-bottom: 1px solid var(--border-color);">
                            <tr>
                                <th class="ps-4 py-3">Day</th>
                                <th class="py-3">Time</th>
                                <th class="py-3">Subject</th>
                                <th class="py-3">Faculty</th>
                                <th class="py-3">Course</th>
                                <th class="py-3">Year</th>
                                <th class="py-3">Session</th>
                                <th class="pe-4 py-3 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td class="ps-4 py-3">
                                        <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">
                                            <?= substr($schedule['day'], 0, 3) ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <span style="color: var(--text-primary);">
                                            <?= date('h:i A', strtotime($schedule['start_time'])) ?>
                                        </span><br>
                                        <small style="color: var(--text-muted);">to <?= date('h:i A', strtotime($schedule['end_time'])) ?></small>
                                    </td>
                                    <td class="py-3">
                                        <div class="fw-medium" style="color: var(--text-primary);"><?= htmlspecialchars($schedule['subject_name']) ?></div>
                                    </td>
                                    <td class="py-3">
                                        <div style="color: var(--text-secondary);"><?= htmlspecialchars($schedule['faculty_name']) ?></div>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($schedule['faculty_email']) ?></small>
                                    </td>
                                    <td class="py-3" style="color: var(--text-secondary);"><?= htmlspecialchars($schedule['course_name']) ?></td>
                                    <td class="py-3" style="color: var(--text-secondary);"><?= htmlspecialchars($schedule['year_name']) ?></td>
                                    <td class="py-3" style="color: var(--text-secondary);"><?= htmlspecialchars($schedule['session_name']) ?></td>
                                    <td class="pe-4 py-3 text-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <a href="edit_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-sm" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="delete_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;" onclick="return confirm('Are you sure you want to delete this schedule?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- No Data State -->
    <div class="card border-0">
        <div class="card-body text-center py-5">
            <div style="width: 80px; height: 80px; background: rgba(59, 130, 246, 0.1); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                <i class="bi bi-calendar-x" style="color: var(--sidebar-active); font-size: 2.5rem;"></i>
            </div>
            <h5 style="color: var(--text-primary);">No schedules found</h5>
            <p style="color: var(--text-muted);" class="mb-3">No class schedules match your filter criteria.</p>
            <?php if (!empty($selectedCourse) || !empty($selectedYear) || !empty($selectedSession) || !empty($selectedFaculty) || !empty($selectedDay)): ?>
                <a href="view_schedule.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-2"></i>Clear Filters
                </a>
            <?php else: ?>
                <a href="create_schedule.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Create First Schedule
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- View Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');
    
    // Check localStorage for preferred view
    const savedView = localStorage.getItem('scheduleView') || 'grid';
    if (savedView === 'list') {
        gridView.style.display = 'none';
        listView.style.display = 'block';
        gridBtn.classList.remove('active');
        gridBtn.style.background = 'var(--card-bg)';
        gridBtn.style.color = 'var(--text-secondary)';
        listBtn.classList.add('active');
        listBtn.style.background = 'var(--sidebar-active)';
        listBtn.style.color = 'white';
    }
    
    gridBtn.addEventListener('click', function() {
        gridView.style.display = 'block';
        listView.style.display = 'none';
        gridBtn.classList.add('active');
        gridBtn.style.background = 'var(--sidebar-active)';
        gridBtn.style.color = 'white';
        listBtn.classList.remove('active');
        listBtn.style.background = 'var(--card-bg)';
        listBtn.style.color = 'var(--text-secondary)';
        localStorage.setItem('scheduleView', 'grid');
    });
    
    listBtn.addEventListener('click', function() {
        gridView.style.display = 'none';
        listView.style.display = 'block';
        listBtn.classList.add('active');
        listBtn.style.background = 'var(--sidebar-active)';
        listBtn.style.color = 'white';
        gridBtn.classList.remove('active');
        gridBtn.style.background = 'var(--card-bg)';
        gridBtn.style.color = 'var(--text-secondary)';
        localStorage.setItem('scheduleView', 'list');
    });
});
</script>

<style>
/* Schedule item hover effect */
.schedule-item {
    transition: all 0.3s ease;
}

.schedule-item:hover {
    transform: translateX(5px);
    background: rgba(59, 130, 246, 0.1) !important;
}

/* Table row hover */
.table-hover tbody tr:hover {
    background: var(--sidebar-hover) !important;
}

/* View toggle buttons */
.btn-group .btn {
    transition: all 0.3s ease;
}

.btn-group .btn:hover {
    transform: translateY(-1px);
}

/* Card hover */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2) !important;
}

/* Badge styles */
.badge {
    font-weight: 500;
    padding: 6px 10px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table {
        font-size: 0.85rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
    }
    
    .schedule-item .d-flex {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .schedule-item .badge {
        align-self: flex-start;
    }
}

/* Print styles */
@media print {
    .sidebar, .btn, .form-select, .btn-group, .card-header .d-flex {
        display: none !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        break-inside: avoid;
    }
    
    .schedule-item {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>

<?php include('includes/footer.php'); ?>