<?php
include('../config/db.php');
include('../includes/header.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: " . APP_URL . "/auth/login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student's enrolled course and year
$stmt = $pdo->prepare("
    SELECT s.name, s.course_id, s.year_id, s.session_id,
           c.course_name, y.year_name, se.session_name
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN years y ON s.year_id = y.id
    LEFT JOIN sessions se ON s.session_id = se.id
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student profile not found.");
}

// Get filter parameters - default to student's course/year
$course_id = $_GET['course_id'] ?? $student['course_id'];
$year_id = $_GET['year_id'] ?? $student['year_id'];
$subject_id = $_GET['subject_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Load subjects for selected course/year
$subjects = [];
if ($course_id && $year_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT sub.id, sub.subject_name 
        FROM subjects sub
        JOIN schedule sch ON sub.id = sch.subject_id
        WHERE sub.course_id = ? AND sub.year_id = ?
        ORDER BY sub.subject_name
    ");
    $stmt->execute([$course_id, $year_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Pagination
$per_page = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Initialize variables
$attendance_records = [];
$summary = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'percentage' => 0];
$total_pages = 1;
$subject_name = '';

if ($subject_id) {
    // Get subject name
    $subject_key = array_search($subject_id, array_column($subjects, 'id'));
    $subject_name = $subject_key !== false ? $subjects[$subject_key]['subject_name'] : '';
    
    // Build WHERE clause for date filters
    $date_conditions = [];
    $params = [$student_id, $subject_id];
    
    if ($date_from) {
        $date_conditions[] = "a.date >= ?";
        $params[] = $date_from;
    }
    if ($date_to) {
        $date_conditions[] = "a.date <= ?";
        $params[] = $date_to;
    }
    
    $date_clause = !empty($date_conditions) ? " AND " . implode(" AND ", $date_conditions) : "";
    
    // Get summary statistics
    $summary_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent
        FROM attendance a
        INNER JOIN schedule s ON a.schedule_id = s.id
        WHERE a.student_id = ? AND s.subject_id = ? $date_clause
    ");
    $summary_stmt->execute($params);
    $summary_data = $summary_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($summary_data) {
        $summary = $summary_data;
        $summary['attended'] = $summary['present'] + $summary['late'];
        $summary['percentage'] = $summary['total'] > 0 
            ? round(($summary['attended'] / $summary['total']) * 100, 1) 
            : 0;
    }
    
    // Get total records for pagination
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM attendance a
        INNER JOIN schedule s ON a.schedule_id = s.id
        WHERE a.student_id = ? AND s.subject_id = ? $date_clause
    ");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_records / $per_page));
    
    // Get paginated attendance records
    $detail_stmt = $pdo->prepare("
        SELECT 
            a.date AS lecture_date,
            a.status,
            a.created_at,
            DATE_FORMAT(a.created_at, '%h:%i %p') as marked_time,
            s.day,
            TIME_FORMAT(s.start_time, '%h:%i %p') AS start_time,
            TIME_FORMAT(s.end_time, '%h:%i %p') AS end_time,
            f.name as faculty_name,
            DATEDIFF(CURDATE(), a.date) as days_ago
        FROM attendance a
        INNER JOIN schedule s ON a.schedule_id = s.id
        INNER JOIN students f ON a.faculty_id = f.id
        WHERE a.student_id = ? AND s.subject_id = ? $date_clause
        ORDER BY a.date DESC, s.start_time DESC
        LIMIT ? OFFSET ?
    ");
    
    $detail_params = array_merge($params, [$per_page, $offset]);
    $detail_stmt->execute($detail_params);
    $attendance_records = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get monthly trend for this subject
$monthly_trend = [];
if ($subject_id && $summary['total'] > 0) {
    $trend_stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(a.date, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) as attended
        FROM attendance a
        INNER JOIN schedule s ON a.schedule_id = s.id
        WHERE a.student_id = ? AND s.subject_id = ?
        GROUP BY DATE_FORMAT(a.date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ");
    $trend_stmt->execute([$student_id, $subject_id]);
    $monthly_trend = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
/* Custom styles for attendance details */
.filter-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    border: 1px solid #dee2e6;
}

.stat-card {
    transition: transform 0.2s ease;
    border: none;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.05);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-present {
    background: #d4edda;
    color: #155724;
}

.status-late {
    background: #fff3cd;
    color: #856404;
}

.status-absent {
    background: #f8d7da;
    color: #721c24;
}

.progress-sm {
    height: 8px;
    border-radius: 4px;
}

.student-info-bar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
}

.trend-container {
    background: white;
    border-radius: 12px;
    padding: 15px;
    border: 1px solid #dee2e6;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 15px;
    color: #6c757d;
}

.empty-state i {
    font-size: 5rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.date-badge {
    background: #e9ecef;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    color: #495057;
}

@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 10px;
    }
}
</style>

<main class="main-content">
<div class="container-fluid px-3 px-md-4 pt-4 pb-5">

    <!-- Student Info Bar -->
    <div class="student-info-bar d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h4 class="fw-bold mb-1">📊 Detailed Attendance Analysis</h4>
            <p class="mb-0 opacity-75">
                <i class="bi bi-mortarboard me-2"></i><?= htmlspecialchars($student['course_name']) ?> - <?= htmlspecialchars($student['year_name']) ?>
                <span class="mx-2">|</span>
                <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($student['name']) ?>
            </p>
        </div>
        <div class="mt-2 mt-md-0">
            <span class="badge bg-white text-dark p-3">
                <i class="bi bi-calendar-check me-2"></i>
                Overall: <?= $summary['percentage'] ?>%
            </span>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form id="attendanceFilter" method="GET" class="row g-3">
            <input type="hidden" name="course_id" value="<?= $course_id ?>">
            <input type="hidden" name="year_id" value="<?= $year_id ?>">
            
            <div class="col-md-4">
                <label class="form-label fw-bold">Subject</label>
                <select name="subject_id" class="form-select form-select-lg" required>
                    <option value="">-- Select Subject --</option>
                    <?php foreach($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $subject_id == $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['subject_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 me-2">
                    <i class="bi bi-search me-2"></i>View
                </button>
                <a href="attendance_details.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
        
        <?php if($subject_id): ?>
        <div class="mt-3">
            <span class="badge bg-primary me-2">Subject: <?= htmlspecialchars($subject_name) ?></span>
            <?php if($date_from): ?><span class="badge bg-info me-2">From: <?= date('d M Y', strtotime($date_from)) ?></span><?php endif; ?>
            <?php if($date_to): ?><span class="badge bg-info me-2">To: <?= date('d M Y', strtotime($date_to)) ?></span><?php endif; ?>
            <span class="badge bg-secondary">Total Records: <?= $summary['total'] ?></span>
        </div>
        <?php endif; ?>
    </div>

    <?php if($subject_id): ?>
        <?php if($summary['total'] > 0): ?>
        
        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <i class="bi bi-calendar-check stat-icon" style="font-size: 2.5rem; opacity: 0.2; position: absolute; right: 10px; top: 10px;"></i>
                        <h6 class="text-white-50 mb-2">Total Lectures</h6>
                        <h2 class="mb-0"><?= $summary['total'] ?></h2>
                        <small>All time</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <i class="bi bi-check-circle stat-icon" style="font-size: 2.5rem; opacity: 0.2; position: absolute; right: 10px; top: 10px;"></i>
                        <h6 class="text-white-50 mb-2">Present</h6>
                        <h2 class="mb-0"><?= $summary['present'] ?></h2>
                        <small><?= $summary['total'] > 0 ? round(($summary['present']/$summary['total'])*100, 1) : 0 ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <i class="bi bi-clock stat-icon" style="font-size: 2.5rem; opacity: 0.2; position: absolute; right: 10px; top: 10px;"></i>
                        <h6 class="text-white-50 mb-2">Late</h6>
                        <h2 class="mb-0"><?= $summary['late'] ?></h2>
                        <small><?= $summary['total'] > 0 ? round(($summary['late']/$summary['total'])*100, 1) : 0 ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <i class="bi bi-x-circle stat-icon" style="font-size: 2.5rem; opacity: 0.2; position: absolute; right: 10px; top: 10px;"></i>
                        <h6 class="text-white-50 mb-2">Absent</h6>
                        <h2 class="mb-0"><?= $summary['absent'] ?></h2>
                        <small><?= $summary['total'] > 0 ? round(($summary['absent']/$summary['total'])*100, 1) : 0 ?>%</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Rate Progress -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 fw-bold">Attendance Rate</h6>
                    <span class="h5 mb-0 <?= $summary['percentage'] >= 75 ? 'text-success' : 'text-danger' ?>">
                        <?= $summary['percentage'] ?>%
                    </span>
                </div>
                <div class="progress" style="height: 15px;">
                    <div class="progress-bar <?= $summary['percentage'] >= 75 ? 'bg-success' : ($summary['percentage'] >= 60 ? 'bg-warning' : 'bg-danger') ?>" 
                         role="progressbar" 
                         style="width: <?= $summary['percentage'] ?>%"
                         aria-valuenow="<?= $summary['percentage'] ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <small class="text-muted">Required: 75%</small>
                    <small class="text-muted">
                        <?= $summary['attended'] ?> attended out of <?= $summary['total'] ?> lectures
                    </small>
                </div>
            </div>
        </div>

        <!-- Monthly Trend (if available) -->
        <?php if (!empty($monthly_trend) && count($monthly_trend) > 1): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up me-2 text-primary"></i>Monthly Attendance Trend</h6>
            </div>
            <div class="card-body">
                <canvas id="monthlyTrendChart" height="100"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attendance Records Table -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <i class="bi bi-table me-2 text-info"></i>
                    Attendance History - <?= htmlspecialchars($subject_name) ?>
                </h6>
                <div>
                    <span class="badge bg-light text-dark me-2">
                        Page <?= $page ?> of <?= $total_pages ?>
                    </span>
                    <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV()">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Faculty</th>
                            <th>Status</th>
                            <th>Marked At</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($attendance_records as $r):
                        $statusClass = strtolower($r['status']);
                        $dateObj = new DateTime($r['lecture_date']);
                    ?>
                        <tr>
                            <td>
                                <strong><?= $dateObj->format('d M Y') ?></strong>
                                <?php if($r['days_ago'] == 0): ?>
                                    <span class="date-badge ms-2">Today</span>
                                <?php elseif($r['days_ago'] == 1): ?>
                                    <span class="date-badge ms-2">Yesterday</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $r['day'] ?></td>
                            <td><?= $r['start_time'] ?> - <?= $r['end_time'] ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-person-circle text-primary me-2"></i>
                                    <?= htmlspecialchars($r['faculty_name']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $statusClass ?>">
                                    <i class="bi bi-<?= $statusClass == 'present' ? 'check-circle' : ($statusClass == 'late' ? 'clock' : 'x-circle') ?> me-1"></i>
                                    <?= $r['status'] ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= $r['marked_time'] ?? 'N/A' ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="card-footer bg-white">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        </li>
                        
                        <?php
                        $start = max(1, $page-2);
                        $end = min($total_pages, $page+2);
                        
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?'.http_build_query(array_merge($_GET, ['page' => 1])).'">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor;
                        
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?'.http_build_query(array_merge($_GET, ['page' => $total_pages])).'">'.$total_pages.'</a></li>';
                        }
                        ?>
                        
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- No Records Found -->
        <div class="empty-state">
            <i class="bi bi-journal-x"></i>
            <h4 class="mb-2">No Attendance Records Found</h4>
            <p class="text-muted mb-3">Faculty hasn't marked attendance for <?= htmlspecialchars($subject_name) ?> yet.</p>
            <?php if($date_from || $date_to): ?>
                <p class="text-muted">Try adjusting your date filters or <a href="attendance_details.php?course_id=<?= $course_id ?>&year_id=<?= $year_id ?>&subject_id=<?= $subject_id ?>">clear filters</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
    <!-- No Subject Selected -->
    <div class="empty-state">
        <i class="bi bi-funnel"></i>
        <h4 class="mb-2">Select a Subject</h4>
        <p class="text-muted">Choose a subject from the dropdown above to view detailed attendance records.</p>
        <div class="mt-4">
            <i class="bi bi-arrow-up text-primary" style="font-size: 2rem;"></i>
        </div>
    </div>
    <?php endif; ?>

</div>
</main>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if(!empty($monthly_trend) && count($monthly_trend) > 1): ?>
<script>
// Monthly trend chart
const ctx = document.getElementById('monthlyTrendChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(function($m) { 
            return date('M Y', strtotime($m['month'] . '-01')); 
        }, array_reverse($monthly_trend))) ?>,
        datasets: [{
            label: 'Attendance %',
            data: <?= json_encode(array_map(function($m) {
                return round(($m['attended'] / $m['total']) * 100, 1);
            }, array_reverse($monthly_trend))) ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: function(context) {
                const value = context.raw;
                return value >= 75 ? '#28a745' : (value >= 60 ? '#ffc107' : '#dc3545');
            },
            pointRadius: 6,
            pointHoverRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Attendance: ${context.raw}%`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                title: { display: true, text: 'Percentage (%)' }
            }
        }
    }
});
</script>
<?php endif; ?>

<script>
// Export to CSV function
function exportToCSV() {
    const rows = [['Date', 'Day', 'Time', 'Faculty', 'Status', 'Marked At']];
    
    <?php foreach($attendance_records as $r): ?>
    rows.push([
        '<?= $r['lecture_date'] ?>',
        '<?= $r['day'] ?>',
        '<?= $r['start_time'] ?> - <?= $r['end_time'] ?>',
        '<?= addslashes($r['faculty_name']) ?>',
        '<?= $r['status'] ?>',
        '<?= $r['marked_time'] ?? "N/A" ?>'
    ]);
    <?php endforeach; ?>
    
    const csv = rows.map(row => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'attendance_<?= $subject_name ?>_<?= date('Y-m-d') ?>.csv';
    a.click();
}

// Auto-submit when subject changes
document.querySelector('select[name="subject_id"]').addEventListener('change', function() {
    if(this.value) {
        document.getElementById('attendanceFilter').submit();
    }
});
</script>

<?php include('../includes/footer.php'); ?>