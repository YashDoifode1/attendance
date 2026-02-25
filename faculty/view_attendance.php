<?php
include('../config/db.php');
include('../includes/faculty_header.php'); // Includes session check, sidebar, header

// Fetch faculty name safely
$stmt = $pdo->prepare("SELECT name FROM students WHERE id = ? AND role = 'faculty'");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$faculty) die("Faculty profile not found.");

// Update header name
echo "<script>document.getElementById('facultyNameDisplay').textContent = '" . htmlspecialchars($faculty['name']) . "';</script>";

// Fetch filter options (only those related to this faculty)
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.course_name 
    FROM courses c 
    JOIN schedule sch ON c.id = sch.course_id 
    WHERE sch.faculty_id = ? 
    ORDER BY c.course_name
");
$stmt->execute([$faculty_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT DISTINCT sub.id, sub.subject_name 
    FROM subjects sub 
    JOIN schedule sch ON sub.id = sch.subject_id 
    WHERE sch.faculty_id = ? 
    ORDER BY sub.subject_name
");
$stmt->execute([$faculty_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT DISTINCT y.id, y.year_name 
    FROM years y 
    JOIN schedule sch ON y.id = sch.year_id 
    WHERE sch.faculty_id = ? 
    ORDER BY y.year_name
");
$stmt->execute([$faculty_id]);
$years = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filters
$selectedCourse = $_GET['course_id'] ?? '';
$selectedSubject = $_GET['subject_id'] ?? '';
$selectedYear = $_GET['year_id'] ?? '';
$selectedDate = $_GET['date'] ?? '';

// Build attendance records query
$query = "
    SELECT 
        a.id, a.date, a.status,
        s.name AS student_name,
        sb.subject_name,
        c.course_name,
        y.year_name,
        se.session_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN subjects sb ON a.subjects_id = sb.id
    JOIN courses c ON a.course_id = c.id
    JOIN years y ON a.year_id = y.id
    JOIN sessions se ON a.session_id = se.id
    WHERE a.faculty_id = ?
";
$params = [$faculty_id];

if ($selectedCourse) { $query .= " AND a.course_id = ?"; $params[] = $selectedCourse; }
if ($selectedSubject) { $query .= " AND a.subjects_id = ?"; $params[] = $selectedSubject; }
if ($selectedYear) { $query .= " AND a.year_id = ?"; $params[] = $selectedYear; }
if ($selectedDate) { $query .= " AND a.date = ?"; $params[] = $selectedDate; }

$query .= " ORDER BY a.date DESC, s.name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary Stats
$totalRecords = count($attendanceRecords);
$present = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'Present'));
$absent = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'Absent'));
$late = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'Late'));
$attendanceRate = $totalRecords > 0 ? round(($present / $totalRecords) * 100, 1) : 0;

// Data for Chart (Attendance count per date)
$dateStats = [];
foreach ($attendanceRecords as $record) {
    $date = $record['date'];
    if (!isset($dateStats[$date])) {
        $dateStats[$date] = ['Present' => 0, 'Absent' => 0, 'Late' => 0];
    }
    $dateStats[$date][$record['status']]++;
}

$chartDates = array_keys($dateStats);
$chartPresent = array_column($dateStats, 'Present');
$chartAbsent = array_column($dateStats, 'Absent');
$chartLate = array_column($dateStats, 'Late');
?>

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm bg-gradient" style="background: linear-gradient(135deg, #1cc88a, #4e73df); color: #fff;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <h3 class="fw-bold">Attendance Records - <?= htmlspecialchars($faculty['name']) ?></h3>
                    <small><?= date('l, F j, Y') ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards + Filters -->
    <div class="row g-4">

        <!-- Stats Cards -->
        <div class="col-lg-8">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="card text-center bg-light shadow-sm border-0">
                        <div class="card-body py-4">
                            <h3 class="text-success mb-1"><?= $present ?></h3>
                            <p class="mb-0 fw-bold">Present</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center bg-light shadow-sm border-0">
                        <div class="card-body py-4">
                            <h3 class="text-danger mb-1"><?= $absent ?></h3>
                            <p class="mb-0 fw-bold">Absent</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center bg-light shadow-sm border-0">
                        <div class="card-body py-4">
                            <h3 class="text-warning mb-1"><?= $late ?></h3>
                            <p class="mb-0 fw-bold">Late</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center bg-light shadow-sm border-0">
                        <div class="card-body py-4">
                            <h3 class="text-primary mb-1"><?= $attendanceRate ?>%</h3>
                            <p class="mb-0 fw-bold">Attendance Rate</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0">Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Course</label>
                            <select name="course_id" class="form-select">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>" <?= $selectedCourse == $course['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($course['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Subject</label>
                            <select name="subject_id" class="form-select">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= $subject['id'] ?>" <?= $selectedSubject == $subject['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($subject['subject_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Year/Sem</label>
                            <select name="year_id" class="form-select">
                                <option value="">All Years</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?= $year['id'] ?>" <?= $selectedYear == $year['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year['year_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Specific Date</label>
                            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                            <a href="view_attendance.php" class="btn btn-outline-secondary w-100 mt-2">Clear</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- Attendance Trend Chart -->
    <?php if (!empty($chartDates)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">Attendance Trend Over Time</h6>
                </div>
                <div class="card-body">
                    <canvas id="attendanceTrendChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Attendance Records Table -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">Detailed Attendance Records</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($attendanceRecords)): ?>
                        <p class="text-center text-muted py-5">No attendance records found for the selected filters.</p>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 600px; overflow-y:auto;">
                            <table class="table table-hover align-middle">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>Date</th>
                                        <th>Student</th>
                                        <th>Subject</th>
                                        <th>Course</th>
                                        <th>Year/Sem</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendanceRecords as $record): ?>
                                        <tr>
                                            <td><strong><?= date('M j, Y', strtotime($record['date'])) ?></strong></td>
                                            <td><?= htmlspecialchars($record['student_name']) ?></td>
                                            <td><?= htmlspecialchars($record['subject_name']) ?></td>
                                            <td><?= htmlspecialchars($record['course_name']) ?></td>
                                            <td><?= htmlspecialchars($record['year_name']) ?></td>
                                            <td>
                                                <select class="form-select form-select-sm update-status" data-attendance-id="<?= $record['id'] ?>">
                                                    <option value="Present" <?= $record['status'] === 'Present' ? 'selected' : '' ?>>Present</option>
                                                    <option value="Absent" <?= $record['status'] === 'Absent' ? 'selected' : '' ?>>Absent</option>
                                                    <option value="Late" <?= $record['status'] === 'Late' ? 'selected' : '' ?>>Late</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php if (!empty($chartDates)): ?>
<script>
const ctx = document.getElementById('attendanceTrendChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartDates) ?>,
        datasets: [
            {label: 'Present', data: <?= json_encode($chartPresent) ?>, backgroundColor: 'rgba(40, 167, 69, 0.8)', borderColor:'#28a745', borderWidth:1},
            {label: 'Absent', data: <?= json_encode($chartAbsent) ?>, backgroundColor: 'rgba(220, 53, 69, 0.8)', borderColor:'#dc3545', borderWidth:1},
            {label: 'Late', data: <?= json_encode($chartLate) ?>, backgroundColor: 'rgba(255, 193, 7, 0.8)', borderColor:'#ffc107', borderWidth:1}
        ]
    },
    options: {
        responsive: true,
        scales: { x: { stacked:true }, y: { stacked:true, beginAtZero:true } },
        plugins: { legend: { position:'top' }, title: { display:true, text:'Daily Attendance Breakdown' } }
    }
});
</script>
<?php endif; ?>

<script>
// Inline AJAX update for status change
document.querySelectorAll('.update-status').forEach(select=>{
    select.addEventListener('change', function(){
        const status = this.value;
        const id = this.dataset.attendanceId;
        fetch('update_attendance.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({id,status})
        }).then(res=>res.json()).then(data=>{
            if(!data.success) alert('Failed to update status');
        });
    });
});
</script>

<?php include('../includes/faculty_footer.php'); ?>
