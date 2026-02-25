<?php
include('../config/db.php');
include('../includes/faculty_header.php');

// Fetch faculty name
$stmt = $pdo->prepare("SELECT name FROM students WHERE id = ? AND role = 'faculty'");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<script>document.getElementById('facultyNameDisplay').textContent = '" . htmlspecialchars($faculty['name'] ?? 'Faculty') . "';</script>";

// Default date = today
$selected_date = $_POST['date'] ?? date('Y-m-d');
$selected_subject = $_POST['subject_id'] ?? '';

// Fetch ALL schedules for this faculty
$stmt = $pdo->prepare("
    SELECT s.id, s.day, s.start_time, s.end_time,
           sub.subject_name, sub.id AS subject_id,
           c.course_name, c.id AS course_id,
           y.year_name, y.id AS year_id,
           se.session_name, se.id AS session_id
    FROM schedule s
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN courses c ON s.course_id = c.id
    JOIN years y ON s.year_id = y.id
    JOIN sessions se ON s.session_id = se.id
    WHERE s.faculty_id = ?
    ORDER BY FIELD(s.day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.start_time
");
$stmt->execute([$faculty_id]);
$allSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch subjects for filter dropdown
$stmt = $pdo->prepare("
    SELECT DISTINCT sub.id, sub.subject_name 
    FROM subjects sub 
    JOIN schedule sch ON sub.id = sch.subject_id
    WHERE sch.faculty_id = ? 
    ORDER BY sub.subject_name
");
$stmt->execute([$faculty_id]);
$subjectsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch students who marked attendance for selected date (and optionally selected subject)
$query = "
    SELECT a.id, a.date, a.status, s.name AS student_name, TIME(a.created_at) AS marked_time, sb.subject_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN subjects sb ON a.subjects_id = sb.id
    JOIN schedule sch ON a.schedule_id = sch.id
    WHERE sch.faculty_id = ? AND a.date = ?
";
$params = [$faculty_id, $selected_date];

if (!empty($selected_subject)) {
    $query .= " AND sb.id = ?";
    $params[] = $selected_subject;
}

$query .= " ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendanceMarked = $stmt->fetchAll(PDO::FETCH_ASSOC);

$qrImage = '';
$expiryTime = '';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_id = $_POST['schedule_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $session_type = $_POST['session_type'] ?? '';
    $selected_subject = $_POST['subject_id'] ?? '';

    if (empty($schedule_id) || empty($date) || empty($session_type)) {
        $message = 'Please select a valid class, date, and session type.';
        $messageType = 'danger';
    } else {
        // Get selected schedule details
        $stmt = $pdo->prepare("SELECT * FROM schedule s
                               JOIN subjects sub ON s.subject_id = sub.id
                               JOIN courses c ON s.course_id = c.id
                               JOIN years y ON s.year_id = y.id
                               JOIN sessions se ON s.session_id = se.id
                               WHERE s.id = ? AND s.faculty_id = ?");
        $stmt->execute([$schedule_id, $faculty_id]);
        $selectedSchedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$selectedSchedule) {
            $message = 'Invalid class selected.';
            $messageType = 'danger';
        } else {
            $classDay = $selectedSchedule['day'];
            $selectedDayName = date('l', strtotime($date));
            if ($selectedDayName !== $classDay) {
                $message = "This class is only on {$classDay}s. Selected date is {$selectedDayName}.";
                $messageType = 'warning';
            } else {
                // Check if session already exists
                $stmt = $pdo->prepare("SELECT * FROM attendance_sessions WHERE schedule_id = ? AND date = ?");
                $stmt->execute([$schedule_id, $date]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $session_id = $existing['id'];
                    $token = $existing['token'];
                    $expiry = $existing['expiry_timestamp'];
                    $message = "QR already exists for this class on this date. Reusing it.";
                    $messageType = 'info';
                } else {
                    // Create new session
                    $duration = round((strtotime($selectedSchedule['end_time']) - strtotime($selectedSchedule['start_time'])) / 60);
                    $expiry = date('Y-m-d H:i:s', time() + (QR_EXPIRY_MINUTES * 60));

                    $stmt = $pdo->prepare("
                        INSERT INTO attendance_sessions 
                        (schedule_id, date, start_time, end_time, duration_minutes, session_type, expiry_timestamp, token)
                        VALUES (?, ?, ?, ?, ?, ?, ?, '')
                    ");
                    $stmt->execute([
                        $schedule_id, $date,
                        $selectedSchedule['start_time'], $selectedSchedule['end_time'],
                        $duration, $session_type, $expiry
                    ]);
                    $session_id = $pdo->lastInsertId();

                    $token = hash('sha256', $session_id . $date . SECRET_KEY);
                    $stmt = $pdo->prepare("UPDATE attendance_sessions SET token = ? WHERE id = ?");
                    $stmt->execute([$token, $session_id]);

                    $message = "QR Code generated successfully!";
                    $messageType = 'success';
                }

                // Build QR data
                $academicYear = str_replace('BATCH_', '', $selectedSchedule['session_name']);
                $department = $selectedSchedule['course_name'];

                $data = [
                    'institution_id' => INSTITUTION_ID,
                    'department' => $department,
                    'course_code' => 'COURSE-' . $selectedSchedule['course_id'],
                    'subject_code' => 'SUB-' . $selectedSchedule['subject_id'],
                    'subject_name' => $selectedSchedule['subject_name'],
                    'faculty_id' => $faculty_id,
                    'class_section' => $selectedSchedule['year_name'],
                    'academic_year' => $academicYear,
                    'semester' => explode(' ', $selectedSchedule['year_name'])[3] ?? 'N/A',
                    'session_type' => $session_type,
                    'date' => $date,
                    'start_time' => $selectedSchedule['start_time'],
                    'end_time' => $selectedSchedule['end_time'],
                    'session_duration' => $duration,
                    'unique_session_id' => $session_id,
                    'qr_expiry_timestamp' => $expiry,
                    'security_token' => $token
                ];

                $jsonData = json_encode($data);
                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=450x450&data=' . urlencode($jsonData);

                $qrImage = '<img src="' . $qrUrl . '" class="img-fluid rounded shadow border" alt="QR Code">';
                $expiryTime = strtotime($expiry);
            }
        }
    }
}
?>

<div class="row g-4">

    <!-- QR Generator Card -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Generate QR Code for Attendance</h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                        <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="qrForm">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Select Date</label>
                            <input type="date" name="date" id="dateInput" class="form-control form-control-lg" 
                                   value="<?= htmlspecialchars($selected_date) ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Subject Filter</label>
                            <select name="subject_id" id="subjectFilter" class="form-select form-select-lg">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjectsList as $sub): ?>
                                    <option value="<?= $sub['id'] ?>" <?= $selected_subject == $sub['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sub['subject_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label fw-bold">Available Classes</label>
                            <select name="schedule_id" id="scheduleSelect" class="form-select form-select-lg" required>
                                <option value="">-- Select date first --</option>
                            </select>
                            <div id="noClassMsg" class="text-muted small mt-2" style="display:none;">
                                No classes scheduled on this date.
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Session Type</label>
                            <select name="session_type" class="form-select form-select-lg" required>
                                <option value="">-- Type --</option>
                                <option value="Lecture">Lecture</option>
                                <option value="Lab">Lab</option>
                                <option value="Tutorial">Tutorial</option>
                            </select>
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary btn-lg px-5">Generate QR Code</button>
                    </div>
                </form>

                <?php if ($qrImage): ?>
                    <hr class="my-5">
                    <div class="text-center">
                        <h5 class="text-success mb-4">QR Code Ready – Show to Students</h5>
                        <?= $qrImage ?>
                        <div id="expiryCountdown" class="mt-4 fw-bold fs-5 text-muted"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Students Attendance Table -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Students Who Marked Attendance</h5>
            </div>
            <div class="card-body">
                <?php if (empty($attendanceMarked)): ?>
                    <p class="text-center text-muted py-5">No students have marked attendance for this date / subject.</p>
                <?php else: ?>
                    <div class="table-responsive" style="max-height:600px; overflow-y:auto;">
                        <table class="table table-hover align-middle">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Marked At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceMarked as $att): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($att['student_name']) ?></td>
                                        <td><?= htmlspecialchars($att['subject_name']) ?></td>
                                        <td>
                                            <?php if ($att['status'] === 'Present'): ?>
                                                <span class="badge bg-success">Present</span>
                                            <?php elseif ($att['status'] === 'Absent'): ?>
                                                <span class="badge bg-danger">Absent</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Late</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('g:i A', strtotime($att['marked_time'])) ?></td>
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

<script>
const allSchedules = <?= json_encode($allSchedules) ?>;
const dateInput = document.getElementById('dateInput');
const scheduleSelect = document.getElementById('scheduleSelect');
const noClassMsg = document.getElementById('noClassMsg');
const subjectFilter = document.getElementById('subjectFilter');

function updateClassList() {
    const selectedDate = dateInput.value;
    const selectedSubject = subjectFilter.value;
    if (!selectedDate) {
        scheduleSelect.innerHTML = '<option value="">-- Select date first --</option>';
        noClassMsg.style.display = 'none';
        return;
    }

    const selectedDayName = new Date(selectedDate).toLocaleString('en-us', { weekday: 'long' });

    let matching = allSchedules.filter(s => s.day === selectedDayName);
    if (selectedSubject) {
        matching = matching.filter(s => s.subject_id == selectedSubject);
    }

    scheduleSelect.innerHTML = '';
    if (matching.length === 0) {
        scheduleSelect.innerHTML = '<option value="">No classes on this date / subject</option>';
        noClassMsg.style.display = 'block';
        scheduleSelect.disabled = true;
    } else {
        scheduleSelect.disabled = false;
        noClassMsg.style.display = 'none';
        scheduleSelect.innerHTML = '<option value="">-- Select class --</option>';
        matching.forEach(sched => {
            const opt = document.createElement('option');
            opt.value = sched.id;
            opt.textContent = `${sched.subject_name} (${sched.course_name} - ${sched.year_name}) | ${sched.start_time} - ${sched.end_time}`;
            scheduleSelect.appendChild(opt);
        });

        if (matching.length === 1) {
            scheduleSelect.value = matching[0].id;
        }
    }
}
updateClassList();
dateInput.addEventListener('change', updateClassList);
subjectFilter.addEventListener('change', updateClassList);

<?php if ($expiryTime): ?>
const expiry = <?= $expiryTime ?> * 1000;
const countdownEl = document.getElementById('expiryCountdown');
function updateCountdown() {
    const remaining = expiry - Date.now();
    if (remaining <= 0) {
        countdownEl.innerHTML = '<span class="text-danger">QR Expired! Generate a new one.</span>';
        return;
    }
    const mins = Math.floor(remaining / 60000);
    const secs = Math.floor((remaining % 60000) / 1000);
    countdownEl.textContent = `Expires in ${mins}m ${secs}s`;
    setTimeout(updateCountdown, 1000);
}
updateCountdown();
<?php endif; ?>
</script>

<?php include('../includes/faculty_footer.php'); ?>
