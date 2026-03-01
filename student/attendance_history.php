<?php
ob_start();
include('../config/db.php');
include('../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student details
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
$student_name = $student['name'] ?? 'Student';

// Get filter values
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$subject_filter = $_GET['subject'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Fetch subjects for filter dropdown
$subjStmt = $pdo->prepare("
    SELECT DISTINCT sub.id, sub.subject_name 
    FROM attendance a
    JOIN subjects sub ON a.subjects_id = sub.id
    WHERE a.student_id = ?
    ORDER BY sub.subject_name
");
$subjStmt->execute([$student_id]);
$subjects = $subjStmt->fetchAll();

// Get summary statistics
$statsSql = "
    SELECT 
        COUNT(*) as total_classes,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
        ROUND((SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as attendance_percentage
    FROM attendance a
    WHERE a.student_id = ? AND a.date BETWEEN ? AND ?
";

$statsParams = [$student_id, $from_date, $to_date];

if ($subject_filter) {
    $statsSql .= " AND a.subjects_id = ?";
    $statsParams[] = $subject_filter;
}

$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Default values if null
$stats['total_classes'] = $stats['total_classes'] ?? 0;
$stats['present_count'] = $stats['present_count'] ?? 0;
$stats['late_count'] = $stats['late_count'] ?? 0;
$stats['absent_count'] = $stats['absent_count'] ?? 0;
$stats['attendance_percentage'] = $stats['attendance_percentage'] ?? 0;

$attended = $stats['present_count'] + $stats['late_count'];

// Build attendance records query
$sql = "
    SELECT 
        a.date,
        a.status,
        a.created_at as marked_time,
        DATE_FORMAT(a.created_at, '%h:%i %p') as marked_time_formatted,
        sub.subject_name,
        sub.id as subject_id,
        f.name as faculty_name,
        sch.start_time,
        sch.end_time,
        sch.day,
        sess.session_name,
        c.course_name,
        y.year_name,
        a.distance_from_faculty,
        a.failure_reason,
        DATEDIFF(CURDATE(), a.date) as days_ago
    FROM attendance a
    JOIN subjects sub ON a.subjects_id = sub.id
    JOIN schedule sch ON a.schedule_id = sch.id
    LEFT JOIN students f ON a.faculty_id = f.id
    LEFT JOIN sessions sess ON sch.session_id = sess.id
    LEFT JOIN courses c ON a.course_id = c.id
    LEFT JOIN years y ON a.year_id = y.id
    WHERE a.student_id = ? AND a.date BETWEEN ? AND ?
";

$params = [$student_id, $from_date, $to_date];

if ($subject_filter) {
    $sql .= " AND a.subjects_id = ?";
    $params[] = $subject_filter;
}

if ($status_filter) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY a.date DESC, sch.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Get monthly trend for chart
$trendStmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) as attended
    FROM attendance
    WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month
");
$trendStmt->execute([$student_id]);
$trend_data = $trendStmt->fetchAll();

$trend_months = [];
$trend_percentages = [];
foreach ($trend_data as $trend) {
    $trend_months[] = date('M Y', strtotime($trend['month'] . '-01'));
    $trend_percentages[] = round(($trend['attended'] / $trend['total']) * 100, 1);
}
?>

<style>
/* Custom styles for attendance page */
.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
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

.filter-section {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.date-badge {
    background: #e9ecef;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    color: #495057;
    white-space: nowrap;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 12px;
    color: #6c757d;
}

.empty-state i {
    font-size: 5rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

.progress-sm {
    height: 8px;
    border-radius: 4px;
}

.attendance-rate {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}

.eligibility-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.eligibility-good {
    background: #d4edda;
    color: #155724;
}

.eligibility-risk {
    background: #fff3cd;
    color: #856404;
}

.eligibility-danger {
    background: #f8d7da;
    color: #721c24;
}

@media (max-width: 768px) {
    .page-header {
        padding: 15px;
    }
    
    .stat-card .display-6 {
        font-size: 1.5rem;
    }
}
</style>

<main class="main-content">
<div class="container-fluid px-4 py-4">

    <!-- Page Header with Student Info -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-2"><i class="bi bi-calendar-check me-2"></i>My Attendance History</h4>
                <p class="mb-0 opacity-75">
                    <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($student_name) ?>
                    <?php if ($student['course_name']): ?>
                        <span class="mx-2">|</span>
                        <i class="bi bi-mortarboard me-2"></i><?= htmlspecialchars($student['course_name']) ?> - <?= htmlspecialchars($student['year_name']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="scan_qr.php" class="btn btn-light">
                    <i class="bi bi-qr-code-scan me-2"></i>Scan QR Code
                </a>
                <a href="dashboard.php" class="btn btn-outline-light ms-2">
                    <i class="bi bi-grid"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-semibold">From Date</label>
                <input type="date" name="from_date" class="form-control" 
                       value="<?= htmlspecialchars($from_date) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">To Date</label>
                <input type="date" name="to_date" class="form-control" 
                       value="<?= htmlspecialchars($to_date) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Subject</label>
                <select name="subject" class="form-select">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= $subject_filter == $sub['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sub['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="Present" <?= $status_filter == 'Present' ? 'selected' : '' ?>>Present</option>
                    <option value="Late" <?= $status_filter == 'Late' ? 'selected' : '' ?>>Late</option>
                    <option value="Absent" <?= $status_filter == 'Absent' ? 'selected' : '' ?>>Absent</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
        
        <!-- Active Filters -->
        <?php if ($subject_filter || $status_filter): ?>
        <div class="mt-3">
            <small class="text-muted me-2">Active filters:</small>
            <?php if ($subject_filter): 
                $sub_name = array_filter($subjects, fn($s) => $s['id'] == $subject_filter);
                $sub_name = !empty($sub_name) ? reset($sub_name)['subject_name'] : '';
            ?>
                <span class="badge bg-primary me-1"><?= htmlspecialchars($sub_name) ?></span>
            <?php endif; ?>
            <?php if ($status_filter): ?>
                <span class="badge bg-info me-1"><?= $status_filter ?></span>
            <?php endif; ?>
            <a href="my_attendance.php" class="text-decoration-none ms-2 small">Clear all</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Total Classes</h6>
                            <h2 class="mt-2 mb-0"><?= $stats['total_classes'] ?></h2>
                            <small><?= date('d M Y', strtotime($from_date)) ?> - <?= date('d M Y', strtotime($to_date)) ?></small>
                        </div>
                        <i class="bi bi-calendar-check fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Present</h6>
                            <h2 class="mt-2 mb-0"><?= $stats['present_count'] ?></h2>
                            <small><?= $stats['total_classes'] > 0 ? round(($stats['present_count']/$stats['total_classes'])*100, 1) : 0 ?>% of total</small>
                        </div>
                        <i class="bi bi-check-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Late</h6>
                            <h2 class="mt-2 mb-0"><?= $stats['late_count'] ?></h2>
                            <small><?= $stats['total_classes'] > 0 ? round(($stats['late_count']/$stats['total_classes'])*100, 1) : 0 ?>% of total</small>
                        </div>
                        <i class="bi bi-clock fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Absent</h6>
                            <h2 class="mt-2 mb-0"><?= $stats['absent_count'] ?></h2>
                            <small><?= $stats['total_classes'] > 0 ? round(($stats['absent_count']/$stats['total_classes'])*100, 1) : 0 ?>% of total</small>
                        </div>
                        <i class="bi bi-x-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Rate Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center">
                            <div class="attendance-rate <?= $stats['attendance_percentage'] >= 75 ? 'text-success' : ($stats['attendance_percentage'] >= 60 ? 'text-warning' : 'text-danger') ?>">
                                <?= $stats['attendance_percentage'] ?>%
                            </div>
                            <div>Overall Attendance</div>
                            <div class="mt-2">
                                <?php if ($stats['attendance_percentage'] >= 75): ?>
                                    <span class="eligibility-badge eligibility-good">
                                        <i class="bi bi-check-circle-fill me-1"></i>Eligible
                                    </span>
                                <?php elseif ($stats['attendance_percentage'] >= 60): ?>
                                    <span class="eligibility-badge eligibility-risk">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>At Risk
                                    </span>
                                <?php else: ?>
                                    <span class="eligibility-badge eligibility-danger">
                                        <i class="bi bi-x-circle-fill me-1"></i>Shortage
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Attendance Progress</span>
                                <span><?= $attended ?>/<?= $stats['total_classes'] ?> classes attended</span>
                            </div>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar <?= $stats['attendance_percentage'] >= 75 ? 'bg-success' : ($stats['attendance_percentage'] >= 60 ? 'bg-warning' : 'bg-danger') ?>" 
                                     style="width: <?= $stats['attendance_percentage'] ?>%">
                                    <?= $stats['attendance_percentage'] ?>%
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted">Required: 75%</small>
                                <small class="text-muted">
                                    <?php 
                                    if ($stats['attendance_percentage'] < 75) {
                                        $needed = ceil((0.75 * $stats['total_classes'] - $attended) / 0.25);
                                        if ($needed > 0) {
                                            echo "Need {$needed} more class" . ($needed > 1 ? 'es' : '') . " to reach 75%";
                                        }
                                    } else {
                                        $extra = $attended - ceil(0.75 * $stats['total_classes']);
                                        echo "You have {$extra} extra class" . ($extra > 1 ? 'es' : '') . " above 75%";
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Trend Chart -->
    <?php if (!empty($trend_months)): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up me-2 text-primary"></i>Attendance Trend (Last 6 Months)</h6>
        </div>
        <div class="card-body">
            <canvas id="trendChart" height="80"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Attendance Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-table me-2 text-info"></i>Attendance Records
            </h5>
            <div>
                <span class="badge bg-light text-dark me-2">
                    <i class="bi bi-list me-1"></i><?= count($records) ?> Records
                </span>
                <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV()">
                    <i class="bi bi-download me-1"></i>Export
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (count($records) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="py-3">Date & Day</th>
                                <th class="py-3">Subject</th>
                                <th class="py-3">Course</th>
                                <th class="py-3">Faculty</th>
                                <th class="py-3">Time</th>
                                <th class="py-3">Status</th>
                                <th class="py-3">Marked At</th>
                                <?php if (isset($records[0]['distance_from_faculty'])): ?>
                                <th class="py-3">Distance</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sn = 1; ?>
                            <?php foreach ($records as $row): ?>
                                <?php 
                                $statusClass = strtolower($row['status']);
                                $date = new DateTime($row['date']);
                                $row_class = $row['days_ago'] == 0 ? 'table-success' : ($row['days_ago'] == 1 ? 'table-info' : '');
                                ?>
                                <tr class="<?= $row_class ?>">
                                    <td class="px-4"><?= $sn++ ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= $date->format('d M Y') ?></div>
                                        <div class="d-flex align-items-center">
                                            <small class="text-muted"><?= $row['day'] ?></small>
                                            <?php if ($row['days_ago'] == 0): ?>
                                                <span class="date-badge ms-2 bg-success text-white">Today</span>
                                            <?php elseif ($row['days_ago'] == 1): ?>
                                                <span class="date-badge ms-2 bg-info text-white">Yesterday</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['subject_name']) ?></div>
                                        <?php if (!empty($row['session_name'])): ?>
                                            <small class="text-muted"><?= $row['session_name'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($row['course_name'] ?? '-') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($row['year_name'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person-circle text-primary me-2"></i>
                                            <?= htmlspecialchars($row['faculty_name'] ?? 'N/A') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($row['start_time']): ?>
                                            <?= date('h:i A', strtotime($row['start_time'])) ?> - 
                                            <?= date('h:i A', strtotime($row['end_time'])) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $statusClass ?>">
                                            <i class="bi bi-<?= $row['status'] == 'Present' ? 'check-circle' : ($row['status'] == 'Late' ? 'clock' : 'x-circle') ?> me-1"></i>
                                            <?= $row['status'] ?>
                                        </span>
                                        <?php if ($row['failure_reason']): ?>
                                            <i class="bi bi-exclamation-triangle text-warning ms-1" 
                                               title="<?= htmlspecialchars($row['failure_reason']) ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>
                                            <?= $row['marked_time_formatted'] ?? 'N/A' ?>
                                        </small>
                                    </td>
                                    <?php if (isset($row['distance_from_faculty'])): ?>
                                    <td>
                                        <?php if ($row['distance_from_faculty']): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info">
                                                <i class="bi bi-geo-alt me-1"></i>
                                                <?= $row['distance_from_faculty'] ?>m
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h5 class="mt-3">No Attendance Records Found</h5>
                    <p class="text-muted mb-3">No records match your current filters</p>
                    <?php if ($subject_filter || $status_filter || $from_date != date('Y-m-01') || $to_date != date('Y-m-d')): ?>
                        <a href="my_attendance.php" class="btn btn-primary">
                            <i class="bi bi-arrow-repeat me-2"></i>Clear Filters
                        </a>
                    <?php else: ?>
                        <p class="text-muted">Faculty hasn't marked your attendance yet</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Table Footer -->
        <?php if (count($records) > 0): ?>
        <div class="card-footer bg-white py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small class="text-muted">
                        <i class="bi bi-calendar-range me-1"></i>
                        Showing records from <?= date('d M Y', strtotime($from_date)) ?> to <?= date('d M Y', strtotime($to_date)) ?>
                    </small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">
                        <i class="bi bi-check-circle-fill text-success me-1"></i> Present: <?= $stats['present_count'] ?> | 
                        <i class="bi bi-clock-fill text-warning me-1"></i> Late: <?= $stats['late_count'] ?> | 
                        <i class="bi bi-x-circle-fill text-danger me-1"></i> Absent: <?= $stats['absent_count'] ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
</main>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if (!empty($trend_months)): ?>
<script>
// Trend Chart
const ctx = document.getElementById('trendChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($trend_months) ?>,
        datasets: [{
            label: 'Attendance %',
            data: <?= json_encode($trend_percentages) ?>,
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
    const rows = [['Date', 'Day', 'Subject', 'Course', 'Year', 'Faculty', 'Start Time', 'End Time', 'Status', 'Marked At']];
    
    <?php foreach ($records as $row): ?>
    rows.push([
        '<?= $row['date'] ?>',
        '<?= $row['day'] ?>',
        '<?= addslashes($row['subject_name']) ?>',
        '<?= addslashes($row['course_name'] ?? '') ?>',
        '<?= addslashes($row['year_name'] ?? '') ?>',
        '<?= addslashes($row['faculty_name'] ?? 'N/A') ?>',
        '<?= $row['start_time'] ? date('h:i A', strtotime($row['start_time'])) : '' ?>',
        '<?= $row['end_time'] ? date('h:i A', strtotime($row['end_time'])) : '' ?>',
        '<?= $row['status'] ?>',
        '<?= $row['marked_time_formatted'] ?? 'N/A' ?>'
    ]);
    <?php endforeach; ?>
    
    const csv = rows.map(row => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'my_attendance_<?= date('Y-m-d') ?>.csv';
    a.click();
}
</script>

<?php include('../includes/footer.php'); ?>