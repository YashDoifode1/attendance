<?php
include('../config/db.php');
include('../includes/header.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: " . APP_URL . "/auth/login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Filters
$courses = $pdo->query("SELECT id, course_name FROM courses ORDER BY course_name")->fetchAll(PDO::FETCH_ASSOC);
$years   = $pdo->query("SELECT id, year_name FROM years ORDER BY year_name")->fetchAll(PDO::FETCH_ASSOC);

$course_id  = $_GET['course_id']  ?? '';
$year_id    = $_GET['year_id']    ?? '';
$subject_id = $_GET['subject_id'] ?? '';

// Load subjects only when course + year selected
$subjects = [];
if ($course_id && $year_id) {
    $stmt = $pdo->prepare("SELECT id, subject_name FROM subjects WHERE course_id=? AND year_id=? ORDER BY subject_name");
    $stmt->execute([$course_id, $year_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Pagination & Attendance
$attendance_records = [];
$summary = ['total' => 0, 'attended' => 0, 'percentage' => 0];
$total_pages = 1;
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

if ($subject_id) {
    $summary_stmt = $pdo->prepare("
        SELECT COUNT(a.id) AS total,
               SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS attended
        FROM attendance a
        INNER JOIN schedule s ON a.schedule_id=s.id
        WHERE a.student_id=? AND s.subject_id=?
    ");
    $summary_stmt->execute([$student_id, $subject_id]);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: $summary;
    $summary['percentage'] = $summary['total'] > 0 ? round(($summary['attended']/$summary['total'])*100,1) : 0;

    $detail_stmt = $pdo->prepare("
        SELECT a.date AS lecture_date, a.status, s.day,
               TIME_FORMAT(s.start_time,'%h:%i %p') AS start_time,
               TIME_FORMAT(s.end_time,'%h:%i %p') AS end_time
        FROM attendance a
        INNER JOIN schedule s ON a.schedule_id=s.id
        WHERE a.student_id=? AND s.subject_id=?
        ORDER BY a.date DESC, s.start_time DESC
        LIMIT ? OFFSET ?
    ");
    $detail_stmt->execute([$student_id, $subject_id, $per_page, $offset]);
    $attendance_records = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);

    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance a INNER JOIN schedule s ON a.schedule_id=s.id WHERE a.student_id=? AND s.subject_id=?");
    $count_stmt->execute([$student_id, $subject_id]);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_records / $per_page));
}
?>

<main class="main-content">
<div class="container px-3 px-md-4 pt-4 pb-5">

<h2 class="fw-bold mb-3">📊 Detailed Attendance</h2>

<!-- FILTERS -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form id="attendanceFilter" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Course</label>
                <select name="course_id" class="form-select form-select-lg" onchange="loadSubjects(this.value)">
                    <option value="">Select Course</option>
                    <?php foreach($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $course_id==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['course_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Semester</label>
                <select name="year_id" class="form-select form-select-lg" onchange="this.form.submit()" <?= !$course_id?'disabled':'' ?>>
                    <option value="">Select Semester</option>
                    <?php foreach($years as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= $year_id==$y['id']?'selected':'' ?>><?= htmlspecialchars($y['year_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Subject</label>
                <select name="subject_id" class="form-select form-select-lg" <?= !$course_id||!$year_id?'disabled':'' ?>>
                    <option value="">Select Subject</option>
                    <?php foreach($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $subject_id==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary" <?= !$subject_id?'disabled':'' ?>>View</button>
            </div>
        </form>
    </div>
</div>

<?php if($subject_id): ?>
    <?php if($summary['total']>0): ?>
    <!-- SUMMARY CARDS -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted">Attendance Percentage</h6>
                    <h3 class="<?= $summary['percentage']>=75?'text-success':'text-danger' ?>"><?= $summary['percentage'] ?>%</h3>
                    <div class="progress mt-2" style="height:10px">
                        <div class="progress-bar <?= $summary['percentage']>=75?'bg-success':'bg-danger' ?>" role="progressbar" style="width:<?= $summary['percentage'] ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted">Attended / Total</h6>
                    <h4><span class="text-success"><?= $summary['attended'] ?></span> / <span class="text-danger"><?= $summary['total'] ?></span></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted">Subject</h6>
                    <h5><?= htmlspecialchars($subjects[array_search($subject_id, array_column($subjects,'id'))]['subject_name']??'—') ?></h5>
                </div>
            </div>
        </div>
    </div>

    <!-- ATTENDANCE TABLE -->
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($attendance_records as $r):
                    $statusClass = $r['status']=='Present'?'bg-success':'bg-danger';
                    $dateObj = new DateTime($r['lecture_date']);
                ?>
                    <tr>
                        <td><?= $dateObj->format('d M Y') ?></td>
                        <td><?= $r['day'] ?></td>
                        <td><?= $r['start_time'] ?> - <?= $r['end_time'] ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= $r['status'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PAGINATION -->
    <?php if($total_pages>1): ?>
    <nav aria-label="Page navigation" class="mt-3">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">« Prev</a></li>
            <?php for($i=1;$i<=$total_pages;$i++): ?>
                <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">Next »</a></li>
        </ul>
    </nav>
    <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-journal-x display-1 text-muted"></i>
            <h4 class="text-muted mt-3">No attendance records found</h4>
            <p class="text-muted">Faculty hasn't marked attendance for this subject yet.</p>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="text-center py-5 bg-light rounded-3 border">
        <i class="bi bi-funnel display-4 text-primary mb-3"></i>
        <h4 class="text-muted">Select filters to view attendance</h4>
        <p class="text-muted">Choose <strong>Course → Semester → Subject</strong> to see detailed attendance.</p>
    </div>
<?php endif; ?>

</div>
</main>

<script>
function loadSubjects(courseId) {
    if(!courseId) { document.querySelector('select[name="year_id"]').disabled=true; document.querySelector('select[name="subject_id"]').disabled=true; return; }
    document.querySelector('#attendanceFilter').submit();
}
</script>

<?php include('../includes/footer.php'); ?>
