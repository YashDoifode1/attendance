<?php
include('../config/db.php');
include('../includes/header.php'); // Professional sidebar/header included

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    die("Access Denied. Students only.");
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

// Get filter parameters
$filter_course = $_GET['course_id'] ?? $student['course_id'];
$filter_year = $_GET['year_id'] ?? $student['year_id'];
$filter_subject = $_GET['subject_id'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Fetch subjects for this student's course/year
$stmt = $pdo->prepare("
    SELECT DISTINCT sub.id, sub.subject_name
    FROM subjects sub
    JOIN schedule sch ON sub.id = sch.subject_id
    WHERE sch.course_id = ? AND sch.year_id = ?
    ORDER BY sub.subject_name
");
$stmt->execute([$student['course_id'], $student['year_id']]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination settings
$records_per_page = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Build WHERE clause for attendance
$where_conditions = ["a.student_id = ?"];
$params = [$student_id];

if ($filter_course) {
    $where_conditions[] = "a.course_id = ?";
    $params[] = $filter_course;
}
if ($filter_year) {
    $where_conditions[] = "a.year_id = ?";
    $params[] = $filter_year;
}
if ($filter_subject) {
    $where_conditions[] = "a.subjects_id = ?";
    $params[] = $filter_subject;
}
if ($filter_date_from) {
    $where_conditions[] = "a.date >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $where_conditions[] = "a.date <= ?";
    $params[] = $filter_date_to;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total records for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM attendance a
    WHERE $where_clause
";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Fetch attendance records with details
$query = "
    SELECT 
        a.id,
        a.date,
        a.status,
        a.created_at,
        DATE_FORMAT(a.created_at, '%h:%i %p') as marked_time,
        sub.subject_name,
        sub.id as subject_id,
        c.course_name,
        y.year_name,
        sch.start_time,
        sch.end_time,
        sch.day,
        f.name as faculty_name,
        DATEDIFF(CURDATE(), a.date) as days_ago
    FROM attendance a
    JOIN subjects sub ON a.subjects_id = sub.id
    JOIN courses c ON a.course_id = c.id
    JOIN years y ON a.year_id = y.id
    JOIN schedule sch ON a.schedule_id = sch.id
    JOIN students f ON a.faculty_id = f.id
    WHERE $where_clause
    ORDER BY a.date DESC, sch.start_time ASC
    LIMIT $records_per_page OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subject-wise summary
$summary_query = "
    SELECT 
        sub.subject_name,
        COUNT(*) as total_lectures,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
        ROUND((SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as percentage
    FROM attendance a
    JOIN subjects sub ON a.subjects_id = sub.id
    WHERE a.student_id = ?
    GROUP BY sub.subject_name
    ORDER BY percentage DESC
";

$stmt = $pdo->prepare($summary_query);
$stmt->execute([$student_id]);
$subject_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly summary
$monthly_query = "
    SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent
    FROM attendance
    WHERE student_id = ?
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";

$stmt = $pdo->prepare($monthly_query);
$stmt->execute([$student_id]);
$monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overall calculations
$overall_query = "
    SELECT 
        COUNT(*) as total_lectures,
        SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) as attended,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
        ROUND((SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as percentage
    FROM attendance
    WHERE student_id = ?
";

$stmt = $pdo->prepare($overall_query);
$stmt->execute([$student_id]);
$overall = $stmt->fetch(PDO::FETCH_ASSOC);

$total_lectures = $overall['total_lectures'] ?? 0;
$total_attended = $overall['attended'] ?? 0;
$total_absent = $overall['absent'] ?? 0;
$overall_percentage = $overall['percentage'] ?? 0;

// Get today's schedule
$today = date('Y-m-d');
$dayOfWeek = date('l');

$today_query = "
    SELECT 
        sch.start_time,
        sch.end_time,
        sub.subject_name,
        f.name as faculty_name
    FROM schedule sch
    JOIN subjects sub ON sch.subject_id = sub.id
    JOIN students f ON sch.faculty_id = f.id
    WHERE sch.course_id = ? 
      AND sch.year_id = ? 
      AND sch.day = ?
    ORDER BY sch.start_time
";

$stmt = $pdo->prepare($today_query);
$stmt->execute([$student['course_id'], $student['year_id'], $dayOfWeek]);
$today_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance trend for chart
$trend_query = "
    SELECT 
        date,
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) as attended
    FROM attendance
    WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY date
    ORDER BY date
";

$stmt = $pdo->prepare($trend_query);
$stmt->execute([$student_id]);
$trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_dates = [];
$chart_attended = [];
$chart_total = [];

foreach ($trend_data as $day) {
    $chart_dates[] = date('M d', strtotime($day['date']));
    $chart_attended[] = $day['attended'];
    $chart_total[] = $day['total'];
}
?>

<style>
/* Custom styles for student dashboard */
.welcome-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
}

.stat-card {
    transition: transform 0.2s ease;
    border: none;
    border-radius: 12px;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.stat-card .stat-icon {
    font-size: 2.5rem;
    opacity: 0.2;
    position: absolute;
    right: 10px;
    top: 10px;
}

.progress-sm {
    height: 8px;
    border-radius: 4px;
}

.attendance-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-present {
    background: #d4edda;
    color: #155724;
}

.badge-absent {
    background: #f8d7da;
    color: #721c24;
}

.badge-late {
    background: #fff3cd;
    color: #856404;
}

.subject-progress {
    margin-bottom: 15px;
}

.subject-progress .progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.filter-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
}

.schedule-item {
    background: white;
    border-left: 4px solid #667eea;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.schedule-item .time {
    color: #667eea;
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

@media (max-width: 768px) {
    .welcome-section {
        padding: 20px;
    }
    
    .stat-card .stat-icon {
        font-size: 2rem;
    }
}
</style>

<main class="main-content">
    <div class="px-4 pt-4 pb-5">

        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="fw-bold mb-2">Welcome back, <?= htmlspecialchars($student['name']) ?>! 👋</h2>
                    <p class="mb-0 opacity-75">
                        <i class="bi bi-mortarboard me-2"></i><?= htmlspecialchars($student['course_name']) ?> - <?= htmlspecialchars($student['year_name']) ?>
                        <span class="mx-2">|</span>
                        <i class="bi bi-calendar me-2"></i><?= htmlspecialchars($student['session_name']) ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <span class="badge bg-white text-dark p-3">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        Overall Attendance: <?= $overall_percentage ?>%
                    </span>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-6">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <i class="bi bi-book stat-icon"></i>
                        <h6 class="text-white-50 mb-2">Total Lectures</h6>
                        <h2 class="mb-0"><?= $total_lectures ?></h2>
                        <small>All time</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <i class="bi bi-check-circle stat-icon"></i>
                        <h6 class="text-white-50 mb-2">Present</h6>
                        <h2 class="mb-0"><?= $total_attended ?></h2>
                        <small><?= $total_lectures > 0 ? round(($total_attended/$total_lectures)*100, 1) : 0 ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <i class="bi bi-x-circle stat-icon"></i>
                        <h6 class="text-white-50 mb-2">Absent</h6>
                        <h2 class="mb-0"><?= $total_absent ?></h2>
                        <small><?= $total_lectures > 0 ? round(($total_absent/$total_lectures)*100, 1) : 0 ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card <?= $overall_percentage >= 75 ? 'bg-info' : 'bg-warning' ?> text-white">
                    <div class="card-body">
                        <i class="bi bi-pie-chart stat-icon"></i>
                        <h6 class="text-white-50 mb-2">Attendance Rate</h6>
                        <h2 class="mb-0"><?= $overall_percentage ?>%</h2>
                        <small><?= $overall_percentage >= 75 ? '✅ Eligible' : '⚠️ At Risk' ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Schedule -->
        <?php if (!empty($today_schedule)): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-day me-2 text-primary"></i>Today's Schedule (<?= $dayOfWeek ?>)</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($today_schedule as $class): ?>
                    <div class="col-md-4">
                        <div class="schedule-item">
                            <div class="time mb-2"><?= date('g:i A', strtotime($class['start_time'])) ?> - <?= date('g:i A', strtotime($class['end_time'])) ?></div>
                            <h6 class="mb-1"><?= htmlspecialchars($class['subject_name']) ?></h6>
                            <small class="text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($class['faculty_name']) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Subject</label>
                    <select name="subject_id" class="form-select">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['id'] ?>" <?= $filter_subject == $subject['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subject['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $filter_date_from ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $filter_date_to ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 me-2">
                        <i class="bi bi-funnel me-2"></i>Apply Filters
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Subject-wise Summary -->
        <?php if (!empty($subject_summary)): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart me-2 text-success"></i>Subject-wise Performance</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($subject_summary as $subject): ?>
                        <div class="subject-progress">
                            <div class="progress-label">
                                <span><?= htmlspecialchars($subject['subject_name']) ?></span>
                                <span class="fw-bold <?= $subject['percentage'] >= 75 ? 'text-success' : 'text-danger' ?>">
                                    <?= $subject['percentage'] ?>%
                                </span>
                            </div>
                            <div class="progress progress-sm">
                                <div class="progress-bar <?= $subject['percentage'] >= 75 ? 'bg-success' : 'bg-danger' ?>" 
                                     style="width: <?= $subject['percentage'] ?>%"></div>
                            </div>
                            <small class="text-muted">
                                P: <?= $subject['present_count'] ?> | 
                                L: <?= $subject['late_count'] ?> | 
                                A: <?= $subject['absent_count'] ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up me-2 text-primary"></i>Monthly Trend (Last 6 Months)</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attendance Records Table -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-table me-2 text-info"></i>Attendance History</h6>
                <div>
                    <span class="badge bg-light text-dark me-2">
                        <i class="bi bi-calendar me-1"></i>Total: <?= $total_records ?> records
                    </span>
                    <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV()">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($attendance_records)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h5>No attendance records found</h5>
                        <p class="text-muted">Try adjusting your filters or wait for faculty to mark attendance</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4">Date</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Faculty</th>
                                    <th>Status</th>
                                    <th>Marked At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_records as $record): 
                                    $statusClass = strtolower($record['status']);
                                ?>
                                    <tr>
                                        <td class="px-4">
                                            <strong><?= date('M j, Y', strtotime($record['date'])) ?></strong>
                                            <?php if ($record['days_ago'] == 0): ?>
                                                <span class="badge bg-success ms-2">Today</span>
                                            <?php elseif ($record['days_ago'] == 1): ?>
                                                <span class="badge bg-info ms-2">Yesterday</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $record['day'] ?></td>
                                        <td>
                                            <?php if ($record['start_time']): ?>
                                                <?= date('g:i A', strtotime($record['start_time'])) ?> - 
                                                <?= date('g:i A', strtotime($record['end_time'])) ?>
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($record['subject_name']) ?></strong>
                                                <small class="d-block text-muted"><?= htmlspecialchars($record['course_name']) ?> - <?= htmlspecialchars($record['year_name']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-person-circle text-primary me-2"></i>
                                                <?= htmlspecialchars($record['faculty_name']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="attendance-badge badge-<?= $statusClass ?>">
                                                <i class="bi bi-<?= $statusClass == 'present' ? 'check-circle' : ($statusClass == 'late' ? 'clock' : 'x-circle') ?> me-1"></i>
                                                <?= $record['status'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>
                                                <?= $record['marked_time'] ?? 'N/A' ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
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
                                
                                for ($i = $start; $i <= $end; $i++):
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Doughnut Chart
new Chart(document.getElementById('overallChart'), {
    type:'doughnut',
    data:{labels:['Present','Absent'], datasets:[{data:[<?= $total_attended ?>,<?= $total_absent ?>], backgroundColor:['#16a34a','#dc2626']}]},
    options:{responsive:true, maintainAspectRatio:false, cutout:'75%', plugins:{legend:{position:'bottom'}, tooltip:{callbacks:{label:ctx=>ctx.label+': '+ctx.parsed+' lectures'}}}, plugins:[{id:'centerText', beforeDraw:chart=>{const ctx=chart.ctx,width=chart.width,height=chart.height;ctx.textAlign='center';ctx.textBaseline='middle';ctx.font='bold 36px sans-serif';ctx.fillStyle='#1f2937';ctx.fillText('<?= $overall_percentage ?>%',width/2,height/2);}}]}
});

// Bar Chart
new Chart(document.getElementById('subjectChart'), {
    type:'bar',
    data:{labels:<?= json_encode($subject_labels) ?>, datasets:[{label:'Attendance %', data:<?= json_encode($subject_percentages) ?>, backgroundColor:<?= json_encode($subject_colors) ?>, borderRadius:6}]},
    options:{indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{x:{max:100,ticks:{stepSize:20}}}}
});
</script>
<script>
// Monthly trend chart
<?php if (!empty($monthly_stats)): ?>
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(function($m) { 
            return date('M Y', strtotime($m['month'] . '-01')); 
        }, $monthly_stats)) ?>,
        datasets: [{
            label: 'Attendance %',
            data: <?= json_encode(array_map(function($m) {
                return $m['total'] > 0 ? round(($m['present'] + $m['late']) / $m['total'] * 100, 1) : 0;
            }, $monthly_stats)) ?>,
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
<?php endif; ?>

// Export to CSV function
function exportToCSV() {
    const rows = [['Date', 'Day', 'Time', 'Subject', 'Course', 'Year', 'Faculty', 'Status', 'Marked At']];
    
    <?php foreach ($attendance_records as $record): ?>
    rows.push([
        '<?= $record['date'] ?>',
        '<?= $record['day'] ?>',
        '<?= $record['start_time'] ? date('g:i A', strtotime($record['start_time'])) . " - " . date('g:i A', strtotime($record['end_time'])) : "--" ?>',
        '<?= addslashes($record['subject_name']) ?>',
        '<?= addslashes($record['course_name']) ?>',
        '<?= addslashes($record['year_name']) ?>',
        '<?= addslashes($record['faculty_name']) ?>',
        '<?= $record['status'] ?>',
        '<?= $record['marked_time'] ?? "N/A" ?>'
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

// Auto-refresh every 30 seconds for latest data
setTimeout(function() {
    location.reload();
}, 30000); // Refresh after 30 seconds if page is idle
</script>

<?php include('../includes/footer.php'); ?>