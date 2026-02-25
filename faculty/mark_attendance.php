<?php
include('../config/db.php');
include('../includes/faculty_header.php'); 

// Fetch faculty info
$stmt = $pdo->prepare("SELECT name FROM students WHERE id = ? AND role = 'faculty'");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$faculty) die("Faculty not found.");

// Update name in navbar
echo "<script>document.getElementById('facultyNameDisplay').textContent = '" . htmlspecialchars($faculty['name']) . "';</script>";

// Fetch faculty info
$stmt = $pdo->prepare("SELECT name FROM students WHERE id = ? AND role = 'faculty'");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$faculty) die("Faculty not found.");

// Default selected date
$selectedDate = $_POST['date'] ?? date('Y-m-d');
$dayOfWeek = date('l', strtotime($selectedDate));

// Fetch schedules **only for this faculty and the selected day**
$stmt = $pdo->prepare("
    SELECT s.id, s.day, s.start_time, s.end_time,
           sub.subject_name, c.course_name, y.year_name, ses.session_name
    FROM schedule s
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN courses c ON s.course_id = c.id
    JOIN years y ON s.year_id = y.id
    JOIN sessions ses ON s.session_id = ses.id
    WHERE s.faculty_id = ? AND s.day = ?
    ORDER BY s.start_time
");
$stmt->execute([$faculty_id, $dayOfWeek]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch attendance stats per schedule for selected date (for chart)
$attendanceStats = [];
foreach ($schedules as $sched) {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) AS absent,
            SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) AS late
        FROM attendance
        WHERE schedule_id = ? AND date = ?
    ");
    $stmt->execute([$sched['id'], $selectedDate]);
    $attendanceStats[$sched['id']] = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_id = $_POST['schedule_id'] ?? '';
    $date = $_POST['date'] ?? '';
    if (empty($schedule_id) || empty($date)) {
        $message = "Please select a class and date.";
        $messageType = "danger";
    } elseif (empty($_POST['attendance'] ?? [])) {
        $message = "No students found for the selected class.";
        $messageType = "warning";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM schedule WHERE id=? AND faculty_id=?");
        $stmt->execute([$schedule_id, $faculty_id]);
        $sched = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sched) {
            $message = "Invalid class selected.";
            $messageType = "danger";
        } else {
            foreach ($_POST['attendance'] as $student_id => $status) {
                if (!in_array($status, ['Present','Absent','Late'])) continue;
                $stmt = $pdo->prepare("
                    INSERT INTO attendance
                    (student_id, schedule_id, faculty_id, subjects_id, course_id, year_id, session_id, date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status=VALUES(status)
                ");
                $stmt->execute([
                    $student_id, $schedule_id, $sched['faculty_id'], $sched['subject_id'],
                    $sched['course_id'], $sched['year_id'], $sched['session_id'], $date, $status
                ]);
            }
            $message = "Attendance successfully marked for ".date('F j, Y', strtotime($date))."!";
            $messageType = "success";
        }
    }
}
?>

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card rounded-3 shadow-sm bg-gradient" style="background: linear-gradient(135deg, #1cc88a, #4e73df); color: #fff;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <h3 class="fw-bold">Mark Attendance</h3>
                    <small><?= date('l, F j, Y') ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if($message): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <strong><?= $messageType==='success'?'Success!':($messageType==='warning'?'Warning!':'Error!') ?></strong>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Attendance Form -->
        <div class="col-lg-6">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-primary text-white rounded-top">
                    <h6 class="mb-0 fw-bold">Select Class & Date</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="attendanceForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Class Schedule</label>
                            <select name="schedule_id" id="schedule" class="form-select form-select-lg" required>
                                <option value="">-- Choose a class --</option>
                                <?php foreach($schedules as $sched): ?>
                                    <option value="<?= $sched['id'] ?>">
                                        <?= htmlspecialchars($sched['subject_name']) ?> 
                                        (<?= htmlspecialchars($sched['course_name'].'-'.$sched['year_name']) ?>) 
                                        — <?= htmlspecialchars($sched['session_name']) ?> 
                                        [<?= htmlspecialchars($sched['day']) ?> | <?= date('g:i A', strtotime($sched['start_time'])) ?> - <?= date('g:i A', strtotime($sched['end_time'])) ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Attendance Date</label>
                            <input type="date" name="date" class="form-control form-control-lg" value="<?= $selectedDate ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div id="students-container" class="mb-3 text-center text-muted py-5">
                            <i class="bi bi-people fs-1 mb-3"></i>
                            <p>Select a class above to load students</p>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-success btn-lg px-5" id="submitBtn">
                                <span class="submit-text">Submit Attendance</span>
                                <span class="spinner-border spinner-border-sm d-none"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Attendance Charts -->
        <div class="col-lg-6">
            <div class="card shadow-sm rounded-3 mb-3">
                <div class="card-header bg-info text-white rounded-top">
                    <h6 class="mb-0 fw-bold">Attendance Overview</h6>
                </div>
                <div class="card-body">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>

            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-secondary text-white rounded-top">
                    <h6 class="mb-0 fw-bold">Weekly Attendance Trend</h6>
                </div>
                <div class="card-body">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// Load students dynamically when class is selected
const schedules = <?= json_encode($schedules) ?>;
const attendanceStats = <?= json_encode($attendanceStats) ?>;

document.getElementById('schedule').addEventListener('change', function(){
    const scheduleId = this.value;
    const container = document.getElementById('students-container');

    if(!scheduleId){
        container.innerHTML = `<div class="text-center text-muted py-5"><i class="bi bi-people fs-1 mb-3"></i><p>Select a class above to load students</p></div>`;
        updateCharts([0,0,0]);
        return;
    }

    container.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-3">Loading student list...</p></div>`;

    fetch(`fetch_students.php?schedule_id=${scheduleId}`)
        .then(res=>res.text())
        .then(data => { container.innerHTML = data || '<div class="alert alert-info">No students enrolled.</div>'; })
        .catch(err => { container.innerHTML = '<div class="alert alert-danger">Failed to load students.</div>'; });

    // Update doughnut chart
    const stat = attendanceStats[scheduleId] || {present:0, absent:0, late:0};
    updateCharts([stat.present||0, stat.absent||0, stat.late||0]);
});

// Submit button loading state
document.getElementById('attendanceForm').addEventListener('submit', function(){
    const btn = document.getElementById('submitBtn');
    btn.disabled=true;
    btn.querySelector('.submit-text').classList.add('d-none');
    btn.querySelector('.spinner-border').classList.remove('d-none');
});

// Doughnut chart
const ctx = document.getElementById('attendanceChart').getContext('2d');
let doughnutChart = new Chart(ctx, {
    type:'doughnut',
    data:{
        labels:['Present','Absent','Late'],
        datasets:[{
            data:[0,0,0],
            backgroundColor:['#1cc88a','#e74a3b','#f6c23e'],
            borderColor:'#fff',
            borderWidth:2
        }]
    },
    options:{
        responsive:true,
        plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Attendance Distribution', font:{size:16} } }
    }
});

function updateCharts(data){
    doughnutChart.data.datasets[0].data = data;
    doughnutChart.update();
}

// Weekly trend bar chart (dummy until data from backend)
const ctxWeekly = document.getElementById('weeklyChart').getContext('2d');
let weeklyChart = new Chart(ctxWeekly,{
    type:'bar',
    data:{
        labels:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
        datasets:[
            {label:'Present', data:[0,0,0,0,0,0,0], backgroundColor:'#1cc88a'},
            {label:'Absent', data:[0,0,0,0,0,0,0], backgroundColor:'#e74a3b'}
        ]
    },
    options:{
        responsive:true,
        plugins:{ legend:{ position:'top' }, title:{ display:true, text:'Weekly Attendance Trend' } },
        scales:{ y:{ beginAtZero:true } }
    }
});
</script>

<?php include('../includes/faculty_footer.php'); ?>
