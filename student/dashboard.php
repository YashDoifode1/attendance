<?php
include('../config/db.php');
include('../includes/header.php'); // Professional sidebar/header included

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    die("Access Denied. Students only.");
}

$student_id = $_SESSION['user_id'];

// Fetch courses and years
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT * FROM years ORDER BY year_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$course_id = $_GET['course_id'] ?? null;
$year_id = $_GET['year_id'] ?? null;

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Build WHERE clause
$where_clause = "";
$params = [$student_id];
$count_params = [$student_id];

if ($course_id && $year_id) {
    $where_clause = " AND sub.course_id = ? AND sub.year_id = ?";
    $params[] = $course_id;
    $params[] = $year_id;
    $count_params[] = $course_id;
    $count_params[] = $year_id;
}

// Total records for pagination
$count_query = "SELECT COUNT(DISTINCT sub.id) as total
                FROM subjects sub
                LEFT JOIN schedule s ON sub.id = s.subject_id
                LEFT JOIN attendance a ON s.id = a.schedule_id AND a.student_id = ?
                WHERE 1=1 $where_clause";

$stmt = $pdo->prepare($count_query);
$stmt->execute($count_params);
$total_records = $stmt->fetchColumn();
$total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;

// Paginated attendance data
$query = "SELECT 
            sub.id AS subject_id,
            sub.subject_name,
            c.course_name,
            y.year_name,
            COUNT(a.id) AS total_lectures,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS attended_lectures
          FROM subjects sub
          JOIN courses c ON sub.course_id = c.id
          JOIN years y ON sub.year_id = y.id
          LEFT JOIN schedule s ON sub.id = s.subject_id
          LEFT JOIN attendance a ON s.id = a.schedule_id AND a.student_id = ?
          WHERE 1=1 $where_clause
          GROUP BY sub.id, sub.subject_name, c.course_name, y.year_name
          ORDER BY c.course_name, y.year_name, sub.subject_name
          LIMIT $records_per_page OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overall calculations
$overall_query = "SELECT 
                    COUNT(a.id) AS total_lectures,
                    SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS attended_lectures
                  FROM subjects sub
                  LEFT JOIN schedule s ON sub.id = s.subject_id
                  LEFT JOIN attendance a ON s.id = a.schedule_id AND a.student_id = ?
                  WHERE 1=1 $where_clause";

$stmt = $pdo->prepare($overall_query);
$stmt->execute($count_params);
$overall = $stmt->fetch(PDO::FETCH_ASSOC);

$total_lectures = $overall['total_lectures'] ?? 0;
$total_attended = $overall['attended_lectures'] ?? 0;
$total_absent = $total_lectures - $total_attended;
$overall_percentage = $total_lectures > 0 ? round(($total_attended / $total_lectures) * 100, 2) : 0;

// Chart data
$subject_labels = [];
$subject_percentages = [];
$subject_colors = [];
foreach ($attendance_records as $record) {
    $label = $record['subject_name'] . " (" . $record['course_name'] . " - " . $record['year_name'] . ")";
    $subject_labels[] = $label;
    $perc = $record['total_lectures'] > 0 
        ? round(($record['attended_lectures'] / $record['total_lectures']) * 100, 1) 
        : 0;
    $subject_percentages[] = $perc;
    $color = $perc >= 85 ? '#16a34a' : ($perc >= 75 ? '#eab308' : '#dc2626');
    $subject_colors[] = $color;
}
?>

<main class="main-content">
    <div class="px-4 pt-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <h2 class="page-title mb-0">📊 My Attendance Dashboard</h2>
            <small class="text-muted mt-2 mt-md-0">
                <?= ($course_id && $year_id) ? "Filtered View" : "<strong>Overall Analytics (All Time)</strong>" ?>
            </small>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-6">
                <div class="card shadow-sm h-100 border-0 text-center p-4">
                    <h6 class="text-muted">Total Lectures</h6>
                    <h3 class="mb-0"><?= $total_lectures ?></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card shadow-sm h-100 border-0 text-center p-4 <?= $overall_percentage >= 75 ? 'text-success' : 'text-danger' ?>">
                    <h6 class="text-muted">Overall Attendance</h6>
                    <h3 class="mb-0"><?= $overall_percentage ?>%</h3>
                    <small><?= $overall_percentage >= 75 ? '✅ Eligible' : '⚠️ Risk' ?></small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card shadow-sm h-100 border-0 text-center p-4 text-success">
                    <h6 class="text-muted">Attended</h6>
                    <h3 class="mb-0"><?= $total_attended ?></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card shadow-sm h-100 border-0 text-center p-4 text-danger">
                    <h6 class="text-muted">Absent</h6>
                    <h3 class="mb-0"><?= $total_absent ?></h3>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Course</label>
                        <select name="course_id" class="form-select">
                            <option value="">-- All Courses --</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>" <?= $course_id==$course['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($course['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Semester</label>
                        <select name="year_id" class="form-select">
                            <option value="">-- All Semesters --</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?= $year['id'] ?>" <?= $year_id==$year['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($year['year_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                    </div>
                </form>
                <?php if ($course_id || $year_id): ?>
                    <div class="mt-3">
                        <a href="?" class="btn btn-outline-secondary btn-sm">Reset Filters</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($attendance_records)): ?>
            <!-- Charts -->
            <div class="row g-4 mb-4">
                <div class="col-lg-5">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0 px-3 pt-3">Attendance Overview</h6>
                        </div>
                        <div class="card-body p-4 d-flex justify-content-center align-items-center">
                            <canvas id="overallChart" width="300" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card shadow-sm h-100">
                        <div class="card-header <?= $overall_percentage>=75?'bg-success':'bg-warning' ?> text-white">
                            <h6 class="mb-0 px-3 pt-3">Subject-wise Attendance (Page <?= $page ?>)</h6>
                        </div>
                        <div class="card-body p-4">
                            <canvas id="subjectChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center px-4 py-3">
                    <h6 class="mb-0">Detailed Records</h6>
                    <small class="text-muted">Page <?= $page ?> of <?= $total_pages ?> (<?= $total_records ?> subjects)</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4">Subject</th>
                                    <th class="px-4">Course - Semester</th>
                                    <th class="px-4">Total</th>
                                    <th class="px-4">Attended</th>
                                    <th class="px-4">Absent</th>
                                    <th class="px-4">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_records as $record):
                                    $perc = $record['total_lectures']>0 ? round(($record['attended_lectures']/$record['total_lectures'])*100,2) : 0;
                                    $absent = $record['total_lectures'] - $record['attended_lectures'];
                                ?>
                                    <tr>
                                        <td class="px-4"><strong><?= htmlspecialchars($record['subject_name']) ?></strong></td>
                                        <td class="px-4 text-muted"><?= htmlspecialchars($record['course_name']) ?> - <?= htmlspecialchars($record['year_name']) ?></td>
                                        <td class="px-4"><?= $record['total_lectures'] ?></td>
                                        <td class="px-4"><span class="badge bg-success"><?= $record['attended_lectures'] ?></span></td>
                                        <td class="px-4"><span class="badge bg-danger"><?= $absent ?></span></td>
                                        <td class="px-4">
                                            <div class="progress" style="height:26px;">
                                                <div class="progress-bar <?= $perc>=75?'bg-success':'bg-danger' ?>" style="width:<?= $perc ?>%">
                                                    <?= $perc ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if($total_pages>1): ?>
                        <div class="card-footer bg-light px-4 py-3">
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mb-0">
                                    <li class="page-item <?= $page<=1?'disabled':'' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">Previous</a>
                                    </li>
                                    <?php
                                    $start = max(1,$page-2);
                                    $end = min($total_pages,$page+2);
                                    if($start>1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    for($i=$start;$i<=$end;$i++): ?>
                                        <li class="page-item <?= $i==$page?'active':'' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor;
                                    if($end<$total_pages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    ?>
                                    <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center py-5 mx-4">
                <h4>No attendance records found.</h4>
                <p>Records will appear once faculty start marking attendance.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Keep Chart.js scripts intact -->
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
