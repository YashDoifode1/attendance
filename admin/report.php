<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch dropdown data with counts
$courses = $pdo->query("
    SELECT c.*, COUNT(DISTINCT s.id) as student_count 
    FROM courses c 
    LEFT JOIN students s ON c.id = s.course_id 
    GROUP BY c.id 
    ORDER BY c.course_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$years = $pdo->query("
    SELECT y.*, COUNT(DISTINCT s.id) as student_count 
    FROM years y 
    LEFT JOIN students s ON y.id = s.year_id 
    GROUP BY y.id 
    ORDER BY y.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$sessions = $pdo->query("
    SELECT s.*, COUNT(DISTINCT st.id) as student_count 
    FROM sessions s 
    LEFT JOIN students st ON s.id = st.session_id 
    GROUP BY s.id 
    ORDER BY s.session_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get filters
$selectedCourse  = $_GET['course'] ?? '';
$selectedYear    = $_GET['year'] ?? '';
$selectedSession = $_GET['session'] ?? '';

$attendanceData = [];
$summary = [
    'total_lectures' => 0,
    'total_present' => 0,
    'total_late' => 0,
    'total_absent' => 0,
    'avg_attendance' => 0,
    'total_students' => 0
];

if ($selectedCourse && $selectedYear && $selectedSession) {
    // Get detailed attendance data
    $query = "
        SELECT 
            s.id AS student_id, 
            s.name AS student_name,
            s.email,
            s.avatar,
            COUNT(a.id) AS total_lectures, 
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
            ROUND(
                (SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(a.id), 0)), 2
            ) AS attendance_percentage
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id
        WHERE s.course_id = ? AND s.year_id = ? AND s.session_id = ?
        GROUP BY s.id, s.name, s.email, s.avatar
        ORDER BY attendance_percentage DESC, s.name ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$selectedCourse, $selectedYear, $selectedSession]);
    $attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate overall stats
    $summary['total_students'] = count($attendanceData);
    
    if ($summary['total_students'] > 0) {
        $totalLectures = array_sum(array_column($attendanceData, 'total_lectures'));
        $totalPresent = array_sum(array_column($attendanceData, 'present_count'));
        $totalLate = array_sum(array_column($attendanceData, 'late_count'));
        
        $summary['total_lectures'] = $totalLectures;
        $summary['total_present'] = $totalPresent;
        $summary['total_late'] = $totalLate;
        $summary['total_absent'] = $totalLectures - ($totalPresent + $totalLate);
        
        $summary['avg_attendance'] = $totalLectures > 0 
            ? round((($totalPresent + $totalLate) / $totalLectures) * 100, 2) 
            : 0;
    }
}

// Get course/year/session details for header
$courseName = '';
if ($selectedCourse) {
    $stmt = $pdo->prepare("SELECT course_name FROM courses WHERE id = ?");
    $stmt->execute([$selectedCourse]);
    $courseName = $stmt->fetchColumn();
}

$yearName = '';
if ($selectedYear) {
    $stmt = $pdo->prepare("SELECT year_name FROM years WHERE id = ?");
    $stmt->execute([$selectedYear]);
    $yearName = $stmt->fetchColumn();
}

$sessionName = '';
if ($selectedSession) {
    $stmt = $pdo->prepare("SELECT session_name FROM sessions WHERE id = ?");
    $stmt->execute([$selectedSession]);
    $sessionName = $stmt->fetchColumn();
}

// Include shared layout
include('includes/sidebar_header.php');
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Attendance Report</h4>
        <p class="mb-0" style="color: var(--text-muted);">Generate detailed attendance summary by course, semester, and session.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($attendanceData)): ?>
            <button class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="window.print()">
                <i class="bi bi-printer me-2"></i>Print
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Card -->
<div class="card border-0 mb-4">
    <div class="card-header">
        <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
            <i class="bi bi-funnel me-2" style="color: var(--sidebar-active);"></i>Filter Attendance Report
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-4">
            <div class="col-md-3">
                <label class="form-label fw-medium" style="color: var(--text-secondary);">Course</label>
                <select name="course" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" onchange="this.form.submit()">
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id'] ?>" <?= $selectedCourse == $course['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['course_name']) ?> (<?= $course['student_count'] ?> students)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-medium" style="color: var(--text-secondary);">Year / Semester</label>
                <select name="year" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" onchange="this.form.submit()">
                    <option value="">-- Select Year --</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= $year['id'] ?>" <?= $selectedYear == $year['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year['year_name']) ?> (<?= $year['student_count'] ?> students)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-medium" style="color: var(--text-secondary);">Session / Batch</label>
                <select name="session" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" onchange="this.form.submit()">
                    <option value="">-- Select Session --</option>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?= $session['id'] ?>" <?= $selectedSession == $session['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($session['session_name']) ?> (<?= $session['student_count'] ?> students)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-2"></i>Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedCourse && $selectedYear && $selectedSession): ?>
    <!-- Report Header -->
    <?php if (!empty($attendanceData)): ?>
    <div class="card border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div>
                    <h5 class="fw-bold mb-2" style="color: var(--text-primary);">
                        <i class="bi bi-file-text me-2" style="color: var(--sidebar-active);"></i>
                        Attendance Report: <?= htmlspecialchars($courseName) ?> - <?= htmlspecialchars($yearName) ?> - <?= htmlspecialchars($sessionName) ?>
                    </h5>
                    <p class="mb-0" style="color: var(--text-muted);">
                        <i class="bi bi-calendar me-2"></i>Generated on <?= date('F j, Y \a\t g:i A') ?>
                    </p>
                </div>
                <a href="generate_report.php?course=<?= $selectedCourse ?>&year=<?= $selectedYear ?>&session=<?= $selectedSession ?>" 
                   class="btn" style="background: var(--success); color: white; border: none;">
                    <i class="bi bi-file-pdf me-2"></i>Download PDF Report
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-people-fill" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= $summary['total_students'] ?></h3>
                            <small style="color: var(--text-muted);">Total Students</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-check-circle-fill" style="color: var(--success); font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= $summary['total_present'] ?></h3>
                            <small style="color: var(--text-muted);">Present</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-exclamation-triangle-fill" style="color: var(--warning); font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= $summary['total_late'] ?></h3>
                            <small style="color: var(--text-muted);">Late</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; background: rgba(239, 68, 68, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-x-circle-fill" style="color: #ef4444; font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= $summary['total_absent'] ?></h3>
                            <small style="color: var(--text-muted);">Absent</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; background: rgba(139, 92, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-graph-up" style="color: #8b5cf6; font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= $summary['avg_attendance'] ?>%</h3>
                            <small style="color: var(--text-muted);">Avg Attendance</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-book" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= $summary['total_lectures'] ?></h3>
                            <small style="color: var(--text-muted);">Total Lectures</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Table -->
    <div class="card border-0">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                <i class="bi bi-table me-2" style="color: var(--sidebar-active);"></i>Student Attendance Details
            </h5>
            <div class="d-flex gap-2">
                <input type="text" class="form-control form-control-sm" style="width: 250px; background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" 
                       placeholder="Search students..." id="tableSearch">
                <select class="form-select form-select-sm" style="width: 150px; background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="Good">Good (≥75%)</option>
                    <option value="Average">Average (50-74%)</option>
                    <option value="Poor"><50%</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="color: var(--text-primary);" id="attendanceTable">
                    <thead style="background: var(--card-bg); border-bottom: 1px solid var(--border-color);">
                        <tr>
                            <th class="ps-4 py-3">Student</th>
                            <th class="py-3">Email</th>
                            <th class="py-3 text-center">Total Lectures</th>
                            <th class="py-3 text-center">Present</th>
                            <th class="py-3 text-center">Late</th>
                            <th class="py-3 text-center">Absent</th>
                            <th class="py-3 text-center">Attendance %</th>
                            <th class="pe-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceData as $row): 
                            $percentage = $row['attendance_percentage'] ?: 0;
                            
                            if ($percentage >= 75) {
                                $statusClass = 'success';
                                $statusText = 'Good';
                                $progressClass = 'bg-success';
                            } elseif ($percentage >= 50) {
                                $statusClass = 'warning';
                                $statusText = 'Average';
                                $progressClass = 'bg-warning';
                            } else {
                                $statusClass = 'danger';
                                $statusText = 'Poor';
                                $progressClass = 'bg-danger';
                            }
                            
                            $initials = strtoupper(substr($row['student_name'], 0, 1));
                            if (strpos($row['student_name'], ' ') !== false) {
                                $nameParts = explode(' ', $row['student_name']);
                                $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                            }
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);" 
                            data-name="<?= strtolower($row['student_name']) ?>"
                            data-email="<?= strtolower($row['email']) ?>"
                            data-status="<?= $statusText ?>">
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <?php if (!empty($row['avatar'])): ?>
                                        <img src="<?= htmlspecialchars($row['avatar']) ?>" alt="Avatar" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 40px; height: 40px; background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active); font-weight: 600;">
                                            <?= $initials ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-medium" style="color: var(--text-primary);"><?= htmlspecialchars($row['student_name']) ?></div>
                                        <small style="color: var(--text-muted);">ID: <?= $row['student_id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3" style="color: var(--text-secondary);"><?= htmlspecialchars($row['email']) ?></td>
                            <td class="py-3 text-center fw-medium"><?= $row['total_lectures'] ?></td>
                            <td class="py-3 text-center" style="color: var(--success);"><?= $row['present_count'] ?></td>
                            <td class="py-3 text-center" style="color: var(--warning);"><?= $row['late_count'] ?></td>
                            <td class="py-3 text-center" style="color: #ef4444;"><?= $row['absent_count'] ?></td>
                            <td class="py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; background: var(--border-color);">
                                        <div class="progress-bar <?= $progressClass ?>" role="progressbar" 
                                             style="width: <?= $percentage ?>%;" 
                                             aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <span class="fw-bold" style="color: var(--text-primary); min-width: 45px;"><?= $percentage ?>%</span>
                                </div>
                            </td>
                            <td class="pe-4 py-3 text-center">
                                <span class="badge bg-<?= $statusClass ?> bg-opacity-10" style="color: var(--<?= $statusClass ?>); padding: 8px 12px;">
                                    <?= $statusText ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Table Footer with Summary -->
        <div class="card-footer" style="background: var(--card-bg); border-top: 1px solid var(--border-color);">
            <div class="d-flex justify-content-between align-items-center">
                <small style="color: var(--text-muted);">
                    Showing <?= count($attendanceData) ?> of <?= $summary['total_students'] ?> students
                </small>
                <div class="d-flex gap-3">
                    <small style="color: var(--text-muted);">
                        <span class="badge bg-success bg-opacity-10" style="color: var(--success);">●</span> Good (≥75%)
                    </small>
                    <small style="color: var(--text-muted);">
                        <span class="badge bg-warning bg-opacity-10" style="color: var(--warning);">●</span> Average (50-74%)
                    </small>
                    <small style="color: var(--text-muted);">
                        <span class="badge bg-danger bg-opacity-10" style="color: #ef4444;">●</span> Poor (<50%)
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
        <!-- No Data State -->
        <div class="card border-0">
            <div class="card-body text-center py-5">
                <div style="width: 80px; height: 80px; background: rgba(59, 130, 246, 0.1); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <i class="bi bi-bar-chart" style="color: var(--sidebar-active); font-size: 2.5rem;"></i>
                </div>
                <h5 style="color: var(--text-primary);">No attendance records found</h5>
                <p style="color: var(--text-muted);" class="mb-3">There are no attendance records for the selected filters.</p>
                <button class="btn btn-primary" onclick="document.querySelector('select[name=\"course\"]').focus()">
                    <i class="bi bi-arrow-left me-2"></i>Try Different Filters
                </button>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Initial Empty State -->
    <div class="card border-0">
        <div class="card-body text-center py-5">
            <div style="width: 80px; height: 80px; background: rgba(59, 130, 246, 0.1); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                <i class="bi bi-funnel" style="color: var(--sidebar-active); font-size: 2.5rem;"></i>
            </div>
            <h5 style="color: var(--text-primary);">Select filters to generate report</h5>
            <p style="color: var(--text-muted);" class="mb-0">Choose course, year, and session above to view attendance summary.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Search and Filter Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('tableSearch');
    const statusFilter = document.getElementById('statusFilter');
    const table = document.getElementById('attendanceTable');
    const rows = table ? table.getElementsByTagName('tbody')[0].getElementsByTagName('tr') : [];

    function filterTable() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const statusValue = statusFilter ? statusFilter.value.toLowerCase() : '';
        
        Array.from(rows).forEach(row => {
            const name = row.getAttribute('data-name') || '';
            const email = row.getAttribute('data-email') || '';
            const status = row.getAttribute('data-status') || '';
            
            const matchesSearch = searchTerm === '' || 
                                 name.includes(searchTerm) || 
                                 email.includes(searchTerm);
            
            const matchesStatus = statusValue === '' || 
                                 status.toLowerCase() === statusValue;
            
            row.style.display = matchesSearch && matchesStatus ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keyup', filterTable);
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', filterTable);
    }
});

// Auto-refresh data every 5 minutes (optional)
setInterval(function() {
    // Only refresh if filters are selected
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('course') && urlParams.has('year') && urlParams.has('session')) {
        location.reload();
    }
}, 300000);
</script>

<style>
/* Table row hover effect */
.table-hover tbody tr:hover {
    background: var(--sidebar-hover) !important;
}

/* Form control focus effect */
.form-select:focus, .form-control:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    border-color: var(--sidebar-active);
}

/* Badge styles */
.badge {
    font-weight: 500;
    padding: 6px 12px;
    font-size: 0.75rem;
}

/* Card hover effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2) !important;
}

/* Progress bar animation */
.progress-bar {
    transition: width 0.6s ease;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table {
        font-size: 0.85rem;
    }
    
    .badge {
        font-size: 0.7rem;
        padding: 4px 8px;
    }
}

/* Print styles */
@media print {
    .sidebar, .btn, .form-select, .form-control, .card-header .d-flex {
        display: none !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<?php include('includes/footer.php'); ?>