<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$alertType = "";

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

// Check if subjects_id column exists
$columnCheckStmt = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'subjects_id'");
$subjectColumnExists = $columnCheckStmt->rowCount() > 0;

// Fetch subjects if exists with course filtering
$subjects = [];
if ($subjectColumnExists) {
    $subjects = $pdo->query("
        SELECT s.*, c.course_name 
        FROM subjects s
        JOIN courses c ON s.course_id = c.id
        ORDER BY s.subject_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Get filters from POST/GET
$selectedCourse  = $_POST['course'] ?? $_GET['course'] ?? '';
$selectedYear    = $_POST['year'] ?? $_GET['year'] ?? '';
$selectedSession = $_POST['session'] ?? $_GET['session'] ?? '';
$selectedSubject = $_POST['subject'] ?? $_GET['subject'] ?? '';
$selectedDate    = $_POST['date'] ?? $_GET['date'] ?? date('Y-m-d');

// Fetch students for selected filters with existing attendance status
$students = [];
$existingAttendance = [];

if ($selectedCourse && $selectedYear && $selectedSession) {
    $query = "
        SELECT s.id, s.name, s.email, s.avatar,
               a.status as existing_status,
               a.id as attendance_id
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id 
            AND a.course_id = ? 
            AND a.year_id = ? 
            AND a.session_id = ? 
            AND a.date = ?
            " . ($subjectColumnExists && $selectedSubject ? " AND a.subjects_id = ?" : "") . "
        WHERE s.course_id = ? AND s.year_id = ? AND s.session_id = ?
        ORDER BY s.name ASC
    ";

    $params = [$selectedCourse, $selectedYear, $selectedSession, $selectedDate];
    if ($subjectColumnExists && $selectedSubject) {
        $params[] = $selectedSubject;
    }
    $params[] = $selectedCourse;
    $params[] = $selectedYear;
    $params[] = $selectedSession;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a map of existing attendance status
    foreach ($students as $student) {
        if ($student['existing_status']) {
            $existingAttendance[$student['id']] = $student['existing_status'];
        }
    }
}

// Handle form submission for marking attendance
if (isset($_POST['mark_attendance'])) {
    $studentIds = $_POST['student_id'] ?? [];
    $statuses   = $_POST['status'] ?? [];

    $pdo->beginTransaction();
    try {
        foreach ($studentIds as $sid) {
            $status = $statuses[$sid] ?? 'Absent';

            // Check if attendance already exists
            $checkStmt = $pdo->prepare("
                SELECT id FROM attendance 
                WHERE student_id = ? AND course_id = ? AND year_id = ? AND session_id = ? AND date = ? " . 
                ($subjectColumnExists && $selectedSubject ? "AND subjects_id = ?" : "") . " LIMIT 1
            ");

            $paramsCheck = [$sid, $selectedCourse, $selectedYear, $selectedSession, $selectedDate];
            if ($subjectColumnExists && $selectedSubject) {
                $paramsCheck[] = $selectedSubject;
            }
            $checkStmt->execute($paramsCheck);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing record
                $updateStmt = $pdo->prepare("UPDATE attendance SET status = ? WHERE id = ?");
                $updateStmt->execute([$status, $existing['id']]);
            } else {
                // Insert new record
                $insertStmt = $pdo->prepare("
                    INSERT INTO attendance (student_id, course_id, year_id, session_id, subjects_id, date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([
                    $sid, 
                    $selectedCourse, 
                    $selectedYear, 
                    $selectedSession, 
                    ($subjectColumnExists && $selectedSubject) ? $selectedSubject : null, 
                    $selectedDate, 
                    $status
                ]);
            }
        }
        $pdo->commit();
        $message = "Attendance saved successfully for " . count($studentIds) . " students.";
        $alertType = "success";
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . "?course=" . $selectedCourse . "&year=" . $selectedYear . "&session=" . $selectedSession . "&subject=" . $selectedSubject . "&date=" . $selectedDate . "&success=1");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error saving attendance: " . $e->getMessage();
        $alertType = "error";
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Attendance saved successfully.";
    $alertType = "success";
}

// Get course/year/session names for display
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

// Include layout
include('includes/sidebar_header.php');
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Mark Attendance</h4>
        <p class="mb-0" style="color: var(--text-muted);">Select course, semester, session, and date to mark attendance for students.</p>
    </div>
    <?php if (!empty($students)): ?>
    <div class="d-flex gap-2">
        <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active); padding: 8px 12px;">
            <i class="bi bi-calendar me-2"></i><?= date('F j, Y', strtotime($selectedDate)) ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<!-- Filter Card -->
<div class="card border-0 mb-4">
    <div class="card-header">
        <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
            <i class="bi bi-funnel me-2" style="color: var(--sidebar-active);"></i>Select Attendance Parameters
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-4">
            <div class="col-md-3">
                <label class="form-label fw-medium" style="color: var(--text-secondary);">Course</label>
                <select name="course" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" onchange="this.form.submit()" required>
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
                <select name="year" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" onchange="this.form.submit()" required>
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
                <select name="session" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" onchange="this.form.submit()" required>
                    <option value="">-- Select Session --</option>
                    <?php foreach ($sessions as $sess): ?>
                        <option value="<?= $sess['id'] ?>" <?= $selectedSession == $sess['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sess['session_name']) ?> (<?= $sess['student_count'] ?> students)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($subjectColumnExists): ?>
            <div class="col-md-3">
                <label class="form-label fw-medium" style="color: var(--text-secondary);">Subject</label>
                <select name="subject" class="form-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" onchange="this.form.submit()">
                    <option value="">-- Select Subject (Optional) --</option>
                    <?php foreach ($subjects as $subj): ?>
                        <option value="<?= $subj['id'] ?>" <?= $selectedSubject == $subj['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subj['subject_name']) ?> (<?= htmlspecialchars($subj['course_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-<?= $subjectColumnExists ? '3' : '3' ?>">
                <label class="form-label fw-medium" style="color: var(--text-secondary);">Date</label>
                <input type="date" name="date" value="<?= $selectedDate ?>" class="form-control" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" required onchange="this.form.submit()">
            </div>

            <?php if (!$subjectColumnExists): ?>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-2"></i>Load Students
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Success/Error Alert -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $alertType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-<?= $alertType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Attendance Marking Section -->
<?php if (!empty($students)): ?>
    <!-- Quick Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-people-fill" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0" style="color: var(--text-primary);"><?= count($students) ?></h3>
                            <small style="color: var(--text-muted);">Total Students</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-check-circle-fill" style="color: var(--success); font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0" style="color: var(--text-primary);" id="presentCount">0</h3>
                            <small style="color: var(--text-muted);">Present</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-exclamation-triangle-fill" style="color: var(--warning); font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0" style="color: var(--text-primary);" id="lateCount">0</h3>
                            <small style="color: var(--text-muted);">Late</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; background: rgba(239, 68, 68, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-x-circle-fill" style="color: #ef4444; font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0" style="color: var(--text-primary);" id="absentCount">0</h3>
                            <small style="color: var(--text-muted);">Absent</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Form -->
    <form method="POST" id="attendanceForm">
        <input type="hidden" name="course" value="<?= $selectedCourse ?>">
        <input type="hidden" name="year" value="<?= $selectedYear ?>">
        <input type="hidden" name="session" value="<?= $selectedSession ?>">
        <input type="hidden" name="subject" value="<?= $selectedSubject ?>">
        <input type="hidden" name="date" value="<?= $selectedDate ?>">

        <div class="card border-0">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                        <i class="bi bi-pencil-square me-2" style="color: var(--sidebar-active);"></i>
                        Mark Attendance: <?= htmlspecialchars($courseName) ?> - <?= htmlspecialchars($yearName) ?> - <?= htmlspecialchars($sessionName) ?>
                    </h5>
                    <?php if ($selectedSubject): ?>
                        <small style="color: var(--text-muted);">Subject: <?= htmlspecialchars($selectedSubject) ?></small>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="setAllStatus('Present')">
                        <i class="bi bi-check-all me-2"></i>All Present
                    </button>
                    <button type="button" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="setAllStatus('Absent')">
                        <i class="bi bi-x-octagon me-2"></i>All Absent
                    </button>
                    <button type="submit" name="mark_attendance" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Attendance
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="color: var(--text-primary);">
                        <thead style="background: var(--card-bg); border-bottom: 1px solid var(--border-color);">
                            <tr>
                                <th class="ps-4 py-3" style="width: 50px;">
                                    <input type="checkbox" class="form-check-input" id="selectAll" style="border-color: var(--border-color);">
                                </th>
                                <th class="py-3">Student</th>
                                <th class="py-3">Email</th>
                                <th class="py-3" style="width: 200px;">Attendance Status</th>
                                <th class="pe-4 py-3" style="width: 100px;">Previous</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): 
                                $initials = strtoupper(substr($student['name'], 0, 1));
                                if (strpos($student['name'], ' ') !== false) {
                                    $nameParts = explode(' ', $student['name']);
                                    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                                }
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td class="ps-4 py-3">
                                    <input type="checkbox" class="form-check-input student-select" name="student_id[]" value="<?= $student['id'] ?>" checked style="border-color: var(--border-color);">
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if (!empty($student['avatar'])): ?>
                                            <img src="<?= htmlspecialchars($student['avatar']) ?>" alt="Avatar" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px; background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active); font-weight: 600;">
                                                <?= $initials ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-medium" style="color: var(--text-primary);"><?= htmlspecialchars($student['name']) ?></div>
                                            <small style="color: var(--text-muted);">ID: <?= $student['id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3" style="color: var(--text-secondary);"><?= htmlspecialchars($student['email']) ?></td>
                                <td class="py-3">
                                    <select name="status[<?= $student['id'] ?>]" class="form-select status-select" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary); width: auto;" data-student-id="<?= $student['id'] ?>">
                                        <option value="Present" <?= (isset($existingAttendance[$student['id']]) && $existingAttendance[$student['id']] == 'Present') ? 'selected' : '' ?>>Present</option>
                                        <option value="Late" <?= (isset($existingAttendance[$student['id']]) && $existingAttendance[$student['id']] == 'Late') ? 'selected' : '' ?>>Late</option>
                                        <option value="Absent" <?= (isset($existingAttendance[$student['id']]) && $existingAttendance[$student['id']] == 'Absent') ? 'selected' : '' ?>>Absent</option>
                                    </select>
                                </td>
                                <td class="pe-4 py-3">
                                    <?php if (isset($existingAttendance[$student['id']])): ?>
                                        <span class="badge bg-<?= 
                                            $existingAttendance[$student['id']] == 'Present' ? 'success' : 
                                            ($existingAttendance[$student['id']] == 'Late' ? 'warning' : 'danger') 
                                        ?> bg-opacity-10" style="color: var(--<?= 
                                            $existingAttendance[$student['id']] == 'Present' ? 'success' : 
                                            ($existingAttendance[$student['id']] == 'Late' ? 'warning' : 'danger') 
                                        ?>);">
                                            <i class="bi bi-<?= 
                                                $existingAttendance[$student['id']] == 'Present' ? 'check-circle' : 
                                                ($existingAttendance[$student['id']] == 'Late' ? 'exclamation-triangle' : 'x-circle') 
                                            ?> me-1"></i>
                                            <?= $existingAttendance[$student['id']] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(148, 163, 184, 0.1); color: var(--text-muted);">New</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small style="color: var(--text-muted);">
                    <i class="bi bi-info-circle me-1"></i>
                    Select/deselect students using checkboxes. Only selected students will be saved.
                </small>
                <button type="submit" name="mark_attendance" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Save Attendance
                </button>
            </div>
        </div>
    </form>

<?php elseif($selectedCourse && $selectedYear && $selectedSession): ?>
    <!-- No Students Found -->
    <div class="card border-0">
        <div class="card-body text-center py-5">
            <div style="width: 80px; height: 80px; background: rgba(59, 130, 246, 0.1); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                <i class="bi bi-people" style="color: var(--sidebar-active); font-size: 2.5rem;"></i>
            </div>
            <h5 style="color: var(--text-primary);">No students found</h5>
            <p style="color: var(--text-muted);" class="mb-3">No students are enrolled for the selected filters.</p>
            <a href="add_student.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-2"></i>Add New Student
            </a>
        </div>
    </div>

<?php elseif($selectedCourse || $selectedYear || $selectedSession): ?>
    <!-- Incomplete Selection -->
    <div class="card border-0">
        <div class="card-body text-center py-5">
            <div style="width: 80px; height: 80px; background: rgba(245, 158, 11, 0.1); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                <i class="bi bi-exclamation-triangle" style="color: var(--warning); font-size: 2.5rem;"></i>
            </div>
            <h5 style="color: var(--text-primary);">Incomplete Selection</h5>
            <p style="color: var(--text-muted);" class="mb-0">Please select all required filters to view students.</p>
        </div>
    </div>
<?php endif; ?>

<!-- JavaScript for interactive features -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox functionality
    const selectAll = document.getElementById('selectAll');
    const studentCheckboxes = document.querySelectorAll('.student-select');
    const statusSelects = document.querySelectorAll('.status-select');
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            studentCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateStats();
        });
    }
    
    // Update statistics when status changes
    function updateStats() {
        let present = 0, late = 0, absent = 0;
        
        studentCheckboxes.forEach((checkbox, index) => {
            if (checkbox.checked) {
                const status = statusSelects[index]?.value;
                if (status === 'Present') present++;
                else if (status === 'Late') late++;
                else if (status === 'Absent') absent++;
            }
        });
        
        document.getElementById('presentCount').textContent = present;
        document.getElementById('lateCount').textContent = late;
        document.getElementById('absentCount').textContent = absent;
    }
    
    // Add event listeners to status selects
    statusSelects.forEach(select => {
        select.addEventListener('change', updateStats);
    });
    
    // Add event listeners to checkboxes
    studentCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateStats);
    });
    
    // Initial stats update
    updateStats();
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});

// Set all selected students to a specific status
function setAllStatus(status) {
    const statusSelects = document.querySelectorAll('.status-select');
    const studentCheckboxes = document.querySelectorAll('.student-select');
    
    statusSelects.forEach((select, index) => {
        if (studentCheckboxes[index] && studentCheckboxes[index].checked) {
            select.value = status;
        }
    });
    
    // Trigger stats update
    const event = new Event('change');
    document.querySelectorAll('.status-select')[0]?.dispatchEvent(event);
}

// Form validation
document.getElementById('attendanceForm')?.addEventListener('submit', function(e) {
    const selectedStudents = document.querySelectorAll('.student-select:checked');
    if (selectedStudents.length === 0) {
        e.preventDefault();
        alert('Please select at least one student to mark attendance.');
    }
});
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

/* Checkbox styling */
.form-check-input:checked {
    background-color: var(--sidebar-active);
    border-color: var(--sidebar-active);
}

/* Card hover effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2) !important;
}

/* Status badge animations */
.badge {
    transition: all 0.2s ease;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table {
        font-size: 0.85rem;
    }
    
    .btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }
    
    .form-select {
        min-width: 120px;
    }
}

/* Alert styles */
.alert {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border-color: var(--success);
    color: var(--success);
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border-color: #ef4444;
    color: #ef4444;
}

/* Quick action buttons */
.btn-outline-secondary {
    border-color: var(--border-color);
    color: var(--text-secondary);
}

.btn-outline-secondary:hover {
    background: var(--sidebar-hover);
    border-color: var(--border-color);
    color: var(--text-primary);
}
</style>

<?php include('includes/footer.php'); ?>