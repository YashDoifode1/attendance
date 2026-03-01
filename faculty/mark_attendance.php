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

// Default selected date
$selectedDate = $_POST['date'] ?? date('Y-m-d');
$dayOfWeek = date('l', strtotime($selectedDate));

// Fetch schedules ONLY for this faculty and the selected day
$stmt = $pdo->prepare("
    SELECT s.id, s.day, s.start_time, s.end_time,
           sub.subject_name, sub.id AS subject_id,
           c.course_name, c.id AS course_id,
           y.year_name, y.id AS year_id,
           ses.session_name, ses.id AS session_id,
           s.faculty_id
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

// Fetch attendance stats per schedule for selected date
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

// Fetch weekly attendance data
$weeklyData = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
foreach ($days as $day) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent
        FROM attendance a
        JOIN schedule s ON a.schedule_id = s.id
        WHERE s.faculty_id = ? AND DAYNAME(a.date) = ?
        AND a.date BETWEEN DATE_SUB(?, INTERVAL 7 DAY) AND ?
    ");
    $stmt->execute([$faculty_id, $day, $selectedDate, $selectedDate]);
    $weeklyData[$day] = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
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
            $successCount = 0;
            foreach ($_POST['attendance'] as $student_id => $status) {
                if (!in_array($status, ['Present','Absent','Late'])) continue;
                
                // Check if attendance already exists
                $stmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND schedule_id = ? AND date = ?");
                $stmt->execute([$student_id, $schedule_id, $date]);
                
                if ($stmt->fetch()) {
                    // Update existing
                    $stmt = $pdo->prepare("
                        UPDATE attendance 
                        SET status = ?, 
                            faculty_id = ?,
                            subjects_id = ?,
                            course_id = ?,
                            year_id = ?,
                            session_id = ?,
                            updated_at = NOW()
                        WHERE student_id = ? AND schedule_id = ? AND date = ?
                    ");
                    $stmt->execute([
                        $status,
                        $sched['faculty_id'],
                        $sched['subject_id'],
                        $sched['course_id'],
                        $sched['year_id'],
                        $sched['session_id'],
                        $student_id,
                        $schedule_id,
                        $date
                    ]);
                } else {
                    // Insert new
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance
                        (student_id, schedule_id, faculty_id, subjects_id, course_id, year_id, session_id, date, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $student_id,
                        $schedule_id,
                        $sched['faculty_id'],
                        $sched['subject_id'],
                        $sched['course_id'],
                        $sched['year_id'],
                        $sched['session_id'],
                        $date,
                        $status
                    ]);
                }
                $successCount++;
            }
            
            $message = "Attendance successfully marked for {$successCount} students on " . date('F j, Y', strtotime($date)) . "!";
            $messageType = "success";
            
            // Refresh stats after update
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) AS absent,
                    SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) AS late
                FROM attendance
                WHERE schedule_id = ? AND date = ?
            ");
            $stmt->execute([$schedule_id, $date]);
            $attendanceStats[$schedule_id] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}
?>

<style>
/* Custom styles for mark attendance */
.student-list-container {
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 10px;
}

.student-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
    border-left: 4px solid transparent;
}

.student-item:hover {
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.student-item.present {
    border-left-color: #28a745;
    background: #f0fff4;
}

.student-item.absent {
    border-left-color: #dc3545;
    background: #fff5f5;
}

.student-item.late {
    border-left-color: #ffc107;
    background: #fff9e6;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.status-badge.present {
    background: #28a745;
    color: white;
}

.status-badge.absent {
    background: #dc3545;
    color: white;
}

.status-badge.late {
    background: #ffc107;
    color: #212529;
}

.status-badge:hover {
    transform: scale(1.05);
    filter: brightness(110%);
}

.status-selector {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.status-option {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid #dee2e6;
    transition: all 0.2s ease;
}

.status-option.present {
    color: #28a745;
    border-color: #28a745;
}

.status-option.present.active {
    background: #28a745;
    color: white;
}

.status-option.absent {
    color: #dc3545;
    border-color: #dc3545;
}

.status-option.absent.active {
    background: #dc3545;
    color: white;
}

.status-option.late {
    color: #ffc107;
    border-color: #ffc107;
}

.status-option.late.active {
    background: #ffc107;
    color: #212529;
}

.status-option:hover {
    transform: scale(1.05);
}

.quick-actions {
    background: #e9ecef;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 15px;
}

.quick-actions .btn-sm {
    font-size: 0.8rem;
    padding: 4px 8px;
}

.stats-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.stats-summary .stat-item {
    text-align: center;
}

.stats-summary .stat-value {
    font-size: 1.5rem;
    font-weight: bold;
}

.stats-summary .stat-label {
    font-size: 0.85rem;
    opacity: 0.9;
}

/* Animation for updates */
@keyframes highlight {
    0% { background-color: #fff3cd; }
    100% { background-color: transparent; }
}

.highlight {
    animation: highlight 1s ease;
}

.date-info {
    background: #e8f4fd;
    border-left: 4px solid #0d6efd;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}

/* Chart container fix */
.chart-container {
    position: relative;
    width: 100%;
    height: 200px;
    margin: 0 auto;
}

/* Fix for pie chart spacing */
canvas {
    display: block;
    max-width: 100%;
    height: auto !important;
}
</style>

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card rounded-3 shadow-sm bg-gradient" style="background: linear-gradient(135deg, #1cc88a, #4e73df); color: #fff;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="fw-bold"><i class="bi bi-pencil-square me-2"></i>Mark Attendance</h3>
                        <p class="mb-0 small opacity-75">Manually mark or update student attendance</p>
                    </div>
                    <div class="text-end">
                        <small class="d-block"><i class="bi bi-calendar me-1"></i><?= date('l, F j, Y') ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if($message): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?= $messageType==='success'?'check-circle':($messageType==='warning'?'exclamation-triangle':'x-circle') ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Attendance Form -->
        <div class="col-lg-7">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-primary text-white rounded-top d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-card-checklist me-2"></i>Mark Attendance</h6>
                    <span class="badge bg-light text-primary" id="selectedClassDisplay">No class selected</span>
                </div>
                <div class="card-body">
                    <!-- Date Info -->
                    <div class="date-info">
                        <i class="bi bi-info-circle text-primary me-2"></i>
                        <strong>Selected Date:</strong> <?= date('l, F j, Y', strtotime($selectedDate)) ?>
                        <span class="badge bg-primary ms-2"><?= $dayOfWeek ?></span>
                        <?php if(count($schedules) > 0): ?>
                            <span class="badge bg-success ms-2"><?= count($schedules) ?> class(es) scheduled</span>
                        <?php else: ?>
                            <span class="badge bg-warning ms-2">No classes scheduled</span>
                        <?php endif; ?>
                    </div>

                    <form method="POST" id="attendanceForm">
                        <div class="row g-3 mb-4">
                            <div class="col-md-7">
                                <label class="form-label fw-bold">Class Schedule <small class="text-muted">(for <?= $dayOfWeek ?>)</small></label>
                                <select name="schedule_id" id="schedule" class="form-select form-select-lg" required>
                                    <option value="">-- Choose a class --</option>
                                    <?php foreach($schedules as $sched): ?>
                                        <option value="<?= $sched['id'] ?>" data-subject="<?= htmlspecialchars($sched['subject_name']) ?>">
                                            <?= htmlspecialchars($sched['subject_name']) ?> 
                                            (<?= htmlspecialchars($sched['course_name'].' - '.$sched['year_name']) ?>) 
                                            — <?= date('g:i A', strtotime($sched['start_time'])) ?> - <?= date('g:i A', strtotime($sched['end_time'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if(empty($schedules)): ?>
                                        <option value="" disabled>No classes scheduled on <?= $dayOfWeek ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold">Attendance Date</label>
                                <input type="date" name="date" id="attendanceDate" class="form-control form-control-lg" value="<?= $selectedDate ?>" max="<?= date('Y-m-d') ?>" required onchange="this.form.submit()">
                                <small class="text-muted">Changing date will reload available classes</small>
                            </div>
                        </div>

                        <!-- Quick Actions - Only show if schedules exist -->
                        <?php if(!empty($schedules)): ?>
                        <div class="quick-actions d-flex gap-2 mb-3" id="quickActions" style="display: none;">
                            <span class="text-muted me-2"><i class="bi bi-lightning-charge"></i> Quick mark all:</span>
                            <button type="button" class="btn btn-success btn-sm" onclick="markAll('Present')">
                                <i class="bi bi-check-circle me-1"></i>Present
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="markAll('Absent')">
                                <i class="bi bi-x-circle me-1"></i>Absent
                            </button>
                            <button type="button" class="btn btn-warning btn-sm" onclick="markAll('Late')">
                                <i class="bi bi-clock me-1"></i>Late
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="resetAll()">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- Students List Container -->
                        <div id="students-container" class="mb-4">
                            <?php if(empty($schedules)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-calendar-x fs-1 mb-3 d-block"></i>
                                    <h5>No classes scheduled on <?= $dayOfWeek ?></h5>
                                    <p>Please select a different date</p>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-people fs-1 mb-3 d-block"></i>
                                    <p>Select a class above to load students</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Submit Button - Only show if schedules exist -->
                        <?php if(!empty($schedules)): ?>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small" id="selectedCount">
                                <i class="bi bi-person-check me-1"></i>
                                <span>0</span> students selected
                            </div>
                            <button type="submit" name="submit_attendance" class="btn btn-success btn-lg px-5" id="submitBtn">
                                <span class="submit-text"><i class="bi bi-check2-all me-2"></i>Submit Attendance</span>
                                <span class="spinner-border spinner-border-sm d-none"></span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics & Charts -->
        <div class="col-lg-5">
            <!-- Stats Summary Card -->
            <div class="card shadow-sm rounded-3 mb-3">
                <div class="card-header bg-info text-white rounded-top">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-pie-chart me-2"></i>Today's Summary</h6>
                </div>
                <div class="card-body">
                    <div class="stats-summary mb-3" id="statsSummary" style="display: none;">
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="stat-item">
                                    <div class="stat-value" id="statPresent">0</div>
                                    <div class="stat-label">Present</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-item">
                                    <div class="stat-value" id="statAbsent">0</div>
                                    <div class="stat-label">Absent</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-item">
                                    <div class="stat-value" id="statLate">0</div>
                                    <div class="stat-label">Late</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Weekly Trend Card -->
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-secondary text-white rounded-top">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up me-2"></i>Weekly Attendance Trend</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Updates -->
            <div class="card shadow-sm rounded-3 mt-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Recent Updates</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="recentUpdates">
                        <div class="list-group-item text-muted text-center py-3">
                            <small>No recent updates</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden field to store selected student counts -->
<input type="hidden" id="selectedStudentCount" value="0">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Store schedules and stats from PHP
const schedules = <?= json_encode($schedules) ?>;
const attendanceStats = <?= json_encode($attendanceStats) ?>;
const weeklyData = <?= json_encode($weeklyData) ?>;

// DOM Elements
const scheduleSelect = document.getElementById('schedule');
const dateInput = document.getElementById('attendanceDate');
const container = document.getElementById('students-container');
const quickActions = document.getElementById('quickActions');
const selectedClassDisplay = document.getElementById('selectedClassDisplay');
const submitBtn = document.getElementById('submitBtn');
const selectedCountSpan = document.getElementById('selectedCount')?.querySelector('span');
const statsSummary = document.getElementById('statsSummary');
const recentUpdates = document.getElementById('recentUpdates');

// Chart instances
let doughnutChart = null;
let weeklyChart = null;

// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    initCharts();
    
    // Add event listeners
    if (scheduleSelect) {
        scheduleSelect.addEventListener('change', loadStudents);
    }
    
    // Check if schedule is pre-selected
    if (scheduleSelect && scheduleSelect.value) {
        loadStudents();
    }
});

function initCharts() {
    // Destroy existing charts if they exist
    if (doughnutChart) {
        doughnutChart.destroy();
    }
    if (weeklyChart) {
        weeklyChart.destroy();
    }

    // Doughnut Chart
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    doughnutChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Late'],
            datasets: [{
                data: [0, 0, 0],
                backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: { boxWidth: 12 }
                }
            }
        }
    });

    // Weekly Chart
    const ctxWeekly = document.getElementById('weeklyChart').getContext('2d');
    weeklyChart = new Chart(ctxWeekly, {
        type: 'bar',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            datasets: [
                {
                    label: 'Present',
                    data: [
                        <?= ($weeklyData['Monday']['present'] ?? 0) ?>,
                        <?= ($weeklyData['Tuesday']['present'] ?? 0) ?>,
                        <?= ($weeklyData['Wednesday']['present'] ?? 0) ?>,
                        <?= ($weeklyData['Thursday']['present'] ?? 0) ?>,
                        <?= ($weeklyData['Friday']['present'] ?? 0) ?>,
                        <?= ($weeklyData['Saturday']['present'] ?? 0) ?>
                    ],
                    backgroundColor: '#28a745'
                },
                {
                    label: 'Absent',
                    data: [
                        <?= ($weeklyData['Monday']['absent'] ?? 0) ?>,
                        <?= ($weeklyData['Tuesday']['absent'] ?? 0) ?>,
                        <?= ($weeklyData['Wednesday']['absent'] ?? 0) ?>,
                        <?= ($weeklyData['Thursday']['absent'] ?? 0) ?>,
                        <?= ($weeklyData['Friday']['absent'] ?? 0) ?>,
                        <?= ($weeklyData['Saturday']['absent'] ?? 0) ?>
                    ],
                    backgroundColor: '#dc3545'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: { 
                y: { 
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                } 
            }
        }
    });
}

// Load students when schedule changes
function loadStudents() {
    const scheduleId = scheduleSelect.value;
    const date = dateInput.value;

    if (!scheduleId || !date) {
        container.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-people fs-1 mb-3 d-block"></i>
                <p>Select a class to load students</p>
            </div>
        `;
        if (quickActions) quickActions.style.display = 'none';
        if (selectedClassDisplay) selectedClassDisplay.textContent = 'No class selected';
        
        // Update charts to show 0
        if (doughnutChart) {
            doughnutChart.data.datasets[0].data = [0, 0, 0];
            doughnutChart.update();
        }
        
        if (statsSummary) statsSummary.style.display = 'none';
        return;
    }

    // Update selected class display
    const selectedOption = scheduleSelect.options[scheduleSelect.selectedIndex];
    if (selectedClassDisplay) {
        selectedClassDisplay.textContent = selectedOption.text.split('(')[0].trim();
    }

    container.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p class="mt-3">Loading student list...</p>
        </div>
    `;

    // Fetch students with their attendance status
    fetch(`fetch_students.php?schedule_id=${scheduleId}&date=${date}`)
        .then(res => res.text())
        .then(data => {
            container.innerHTML = data;
            if (quickActions) quickActions.style.display = 'flex';
            updateSelectedCount();
            
            // Update stats summary
            const stats = attendanceStats[scheduleId] || {present: 0, absent: 0, late: 0};
            updateStatsSummary(stats);
            
            // Update chart without recreating it
            if (doughnutChart) {
                doughnutChart.data.datasets[0].data = [stats.present || 0, stats.absent || 0, stats.late || 0];
                doughnutChart.update();
            }
            
            if (statsSummary) statsSummary.style.display = 'block';
            
            // Add event listeners to status options
            document.querySelectorAll('.status-option').forEach(opt => {
                opt.addEventListener('click', function() {
                    const studentId = this.dataset.studentId;
                    const status = this.dataset.status;
                    updateStudentStatus(studentId, status, this);
                });
            });
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<div class="alert alert-danger">Failed to load students. Please try again.</div>';
        });
}

// Update student status
function updateStudentStatus(studentId, status, element) {
    // Update UI
    const studentItem = element.closest('.student-item');
    const statusOptions = studentItem.querySelectorAll('.status-option');
    
    statusOptions.forEach(opt => {
        opt.classList.remove('active');
    });
    element.classList.add('active');
    
    // Update student item class
    studentItem.classList.remove('present', 'absent', 'late');
    studentItem.classList.add(status.toLowerCase());
    
    // Update hidden input
    let hiddenInput = document.querySelector(`input[name="attendance[${studentId}]"]`);
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = `attendance[${studentId}]`;
        studentItem.appendChild(hiddenInput);
    }
    hiddenInput.value = status;
    
    // Update counts
    updateSelectedCount();
    addToRecentUpdates(studentId, status);
}

// Mark all students with same status
function markAll(status) {
    const studentItems = document.querySelectorAll('.student-item');
    studentItems.forEach(item => {
        const statusOption = item.querySelector(`.status-option.${status.toLowerCase()}`);
        if (statusOption) {
            const studentId = statusOption.dataset.studentId;
            updateStudentStatus(studentId, status, statusOption);
        }
    });
}

// Reset all selections
function resetAll() {
    const studentItems = document.querySelectorAll('.student-item');
    studentItems.forEach(item => {
        const hiddenInput = item.querySelector('input[type="hidden"]');
        if (hiddenInput) hiddenInput.remove();
        
        item.classList.remove('present', 'absent', 'late');
        
        const statusOptions = item.querySelectorAll('.status-option');
        statusOptions.forEach(opt => opt.classList.remove('active'));
    });
    updateSelectedCount();
}

// Update selected students count
function updateSelectedCount() {
    const selectedInputs = document.querySelectorAll('input[name^="attendance["]');
    const count = selectedInputs.length;
    if (selectedCountSpan) selectedCountSpan.textContent = count;
    document.getElementById('selectedStudentCount').value = count;
}

// Update stats summary
function updateStatsSummary(stats) {
    document.getElementById('statPresent').textContent = stats.present || 0;
    document.getElementById('statAbsent').textContent = stats.absent || 0;
    document.getElementById('statLate').textContent = stats.late || 0;
}

// Add to recent updates
function addToRecentUpdates(studentId, status) {
    const studentName = document.querySelector(`.status-option[data-student-id="${studentId}"]`)
        ?.closest('.student-item')
        ?.querySelector('.fw-bold')
        ?.textContent || 'Student';
    
    const time = new Date().toLocaleTimeString();
    const statusIcon = status === 'Present' ? '✅' : (status === 'Absent' ? '❌' : '⏰');
    
    const updateHtml = `
        <div class="list-group-item py-2 highlight">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="fw-bold">${studentName}</small>
                    <small class="text-muted d-block">${statusIcon} Marked as ${status}</small>
                </div>
                <small class="text-muted">${time}</small>
            </div>
        </div>
    `;
    
    recentUpdates.innerHTML = updateHtml + recentUpdates.innerHTML;
    if (recentUpdates.children.length > 5) {
        recentUpdates.removeChild(recentUpdates.lastChild);
    }
}

// Form submit handling
document.getElementById('attendanceForm')?.addEventListener('submit', function(e) {
    const selectedCount = parseInt(document.getElementById('selectedStudentCount').value);
    
    if (selectedCount === 0) {
        e.preventDefault();
        alert('Please mark attendance for at least one student.');
        return;
    }
    
    // Show loading state
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.querySelector('.submit-text').classList.add('d-none');
        submitBtn.querySelector('.spinner-border').classList.remove('d-none');
    }
});

// Handle window resize to prevent chart distortion
window.addEventListener('resize', function() {
    if (doughnutChart) {
        doughnutChart.resize();
    }
    if (weeklyChart) {
        weeklyChart.resize();
    }
});
</script>

<?php include('../includes/faculty_footer.php'); ?>