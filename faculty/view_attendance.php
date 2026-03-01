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

// Get filter parameters
$selectedCourse = $_GET['course_id'] ?? '';
$selectedSubject = $_GET['subject_id'] ?? '';
$selectedYear = $_GET['year_id'] ?? '';
$selectedDate = $_GET['date'] ?? '';
$selectedStatus = $_GET['status'] ?? '';

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

// Get available dates
$stmt = $pdo->prepare("
    SELECT DISTINCT date 
    FROM attendance 
    WHERE faculty_id = ? 
    ORDER BY date DESC
");
$stmt->execute([$faculty_id]);
$availableDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Build attendance records query
$query = "
    SELECT 
        a.id, 
        a.date, 
        a.status,
        a.student_id,
        a.created_at,
        s.name AS student_name,
        s.email AS student_email,
        sub.subject_name,
        sub.id AS subject_id,
        c.course_name,
        c.id AS course_id,
        y.year_name,
        y.id AS year_id,
        ses.session_name,
        sch.start_time,
        sch.end_time,
        sch.day
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN subjects sub ON a.subjects_id = sub.id
    JOIN courses c ON a.course_id = c.id
    JOIN years y ON a.year_id = y.id
    JOIN sessions ses ON a.session_id = ses.id
    JOIN schedule sch ON a.schedule_id = sch.id
    WHERE a.faculty_id = ?
";
$params = [$faculty_id];

if ($selectedCourse) { 
    $query .= " AND a.course_id = ?"; 
    $params[] = $selectedCourse; 
}
if ($selectedSubject) { 
    $query .= " AND a.subjects_id = ?"; 
    $params[] = $selectedSubject; 
}
if ($selectedYear) { 
    $query .= " AND a.year_id = ?"; 
    $params[] = $selectedYear; 
}
if ($selectedDate) { 
    $query .= " AND a.date = ?"; 
    $params[] = $selectedDate; 
}
if ($selectedStatus) { 
    $query .= " AND a.status = ?"; 
    $params[] = $selectedStatus; 
}

$query .= " ORDER BY a.date DESC, sch.start_time ASC, s.name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$present = count(array_filter($records, fn($r) => $r['status'] == 'Present'));
$absent = count(array_filter($records, fn($r) => $r['status'] == 'Absent'));
$late = count(array_filter($records, fn($r) => $r['status'] == 'Late'));
$total = count($records);

// Group records by date
$recordsByDate = [];
foreach ($records as $record) {
    $date = $record['date'];
    if (!isset($recordsByDate[$date])) {
        $recordsByDate[$date] = [];
    }
    $recordsByDate[$date][] = $record;
}

// Handle status update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("UPDATE attendance SET status = ? WHERE id = ? AND faculty_id = ?");
        $success = $stmt->execute([$_POST['status'], $_POST['id'], $faculty_id]);
        echo json_encode(['success' => $success]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>

<style>
/* Reuse styles from mark_attendance.php */
.stat-card {
    border-radius: 10px;
    padding: 20px;
    color: white;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    border: 2px solid transparent;
}
.stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
.stat-card.present { background: linear-gradient(135deg, #28a745, #20c997); }
.stat-card.absent { background: linear-gradient(135deg, #dc3545, #c82333); }
.stat-card.late { background: linear-gradient(135deg, #ffc107, #fd7e14); }
.stat-card.total { background: linear-gradient(135deg, #17a2b8, #138496); }
.stat-card.active { border-color: #fff; box-shadow: 0 0 0 3px #007bff; }

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-block;
}
.status-present { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.status-absent { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.status-late { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

.filter-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
}

.date-group {
    margin-bottom: 2rem;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    overflow: hidden;
}

.date-group-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.date-group-header .date-badge {
    background: rgba(255,255,255,0.2);
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
}

.table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
}

.table td {
    vertical-align: middle;
}

.update-status {
    min-width: 100px;
    border-radius: 20px;
    padding: 5px 10px;
    font-size: 0.8rem;
    cursor: pointer;
}

.update-status:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.filter-badge {
    background: #e9ecef;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    margin-right: 5px;
    display: inline-block;
}

.filter-badge .remove-filter {
    color: #dc3545;
    margin-left: 5px;
    cursor: pointer;
    font-weight: bold;
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}
</style>

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card rounded-3 shadow-sm bg-gradient" style="background: linear-gradient(135deg, #1cc88a, #4e73df); color: #fff;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="fw-bold"><i class="bi bi-eye me-2"></i>View Attendance Records</h3>
                        <p class="mb-0 small opacity-75"><?= htmlspecialchars($faculty['name']) ?></p>
                    </div>
                    <div class="text-end">
                        <small class="d-block"><i class="bi bi-calendar me-1"></i><?= date('l, F j, Y') ?></small>
                        <small class="d-block mt-1"><i class="bi bi-funnel me-1"></i><?= $total ?> records found</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card present <?= $selectedStatus == 'Present' ? 'active' : '' ?>" onclick="filterByStatus('Present')">
                <h6>Present</h6>
                <h2><?= $present ?></h2>
                <small><?= $total ? round(($present/$total)*100, 1) : 0 ?>%</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card absent <?= $selectedStatus == 'Absent' ? 'active' : '' ?>" onclick="filterByStatus('Absent')">
                <h6>Absent</h6>
                <h2><?= $absent ?></h2>
                <small><?= $total ? round(($absent/$total)*100, 1) : 0 ?>%</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card late <?= $selectedStatus == 'Late' ? 'active' : '' ?>" onclick="filterByStatus('Late')">
                <h6>Late</h6>
                <h2><?= $late ?></h2>
                <small><?= $total ? round(($late/$total)*100, 1) : 0 ?>%</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card total">
                <h6>Total Records</h6>
                <h2><?= $total ?></h2>
                <small>&nbsp;</small>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filter-section">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
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
            <div class="col-md-3">
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
            <div class="col-md-2">
                <label class="form-label fw-bold">Year</label>
                <select name="year_id" class="form-select">
                    <option value="">All Years</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= $year['id'] ?>" <?= $selectedYear == $year['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year['year_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Date</label>
                <select name="date" class="form-select">
                    <option value="">All Dates</option>
                    <?php foreach ($availableDates as $date): ?>
                        <option value="<?= $date ?>" <?= $selectedDate == $date ? 'selected' : '' ?>>
                            <?= date('M j, Y', strtotime($date)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-2"></i>Apply Filters
                </button>
            </div>
        </form>

        <!-- Active Filters -->
        <?php if($selectedCourse || $selectedSubject || $selectedYear || $selectedDate || $selectedStatus): ?>
        <div class="mt-3">
            <small class="text-muted me-2">Active filters:</small>
            <?php if($selectedCourse): ?>
                <span class="filter-badge">
                    Course: <?= htmlspecialchars($selectedCourse) ?>
                    <span class="remove-filter" onclick="removeFilter('course_id')">&times;</span>
                </span>
            <?php endif; ?>
            <?php if($selectedSubject): ?>
                <span class="filter-badge">
                    Subject: <?= htmlspecialchars($selectedSubject) ?>
                    <span class="remove-filter" onclick="removeFilter('subject_id')">&times;</span>
                </span>
            <?php endif; ?>
            <?php if($selectedYear): ?>
                <span class="filter-badge">
                    Year: <?= htmlspecialchars($selectedYear) ?>
                    <span class="remove-filter" onclick="removeFilter('year_id')">&times;</span>
                </span>
            <?php endif; ?>
            <?php if($selectedDate): ?>
                <span class="filter-badge">
                    Date: <?= date('M j, Y', strtotime($selectedDate)) ?>
                    <span class="remove-filter" onclick="removeFilter('date')">&times;</span>
                </span>
            <?php endif; ?>
            <?php if($selectedStatus): ?>
                <span class="filter-badge">
                    Status: <?= $selectedStatus ?>
                    <span class="remove-filter" onclick="removeFilter('status')">&times;</span>
                </span>
            <?php endif; ?>
            <a href="view_attendance.php" class="btn btn-sm btn-outline-secondary ms-2">
                <i class="bi bi-x-circle me-1"></i>Clear All
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Attendance Records -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-table me-2"></i>Attendance Records</h6>
                    <div>
                        <button class="btn btn-sm btn-light me-2" onclick="exportToCSV()">
                            <i class="bi bi-download me-1"></i>Export CSV
                        </button>
                        <button class="btn btn-sm btn-light" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i>Print
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if(empty($records)): ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <h5>No attendance records found</h5>
                            <p class="text-muted">Try adjusting your filters or generate a QR code for students to scan</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Time</th>
                                        <th>Student</th>
                                        <th>Subject</th>
                                        <th>Course</th>
                                        <th>Year</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $record): ?>
                                    <tr id="row-<?= $record['id'] ?>">
                                        <td>
                                            <strong><?= date('M j, Y', strtotime($record['date'])) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $record['day'] ?></span>
                                        </td>
                                        <td>
                                            <?php if($record['start_time']): ?>
                                                <?= date('g:i A', strtotime($record['start_time'])) ?>
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-circle p-2 me-2">
                                                    <i class="bi bi-person-circle text-primary"></i>
                                                </div>
                                                <div>
                                                    <strong><?= htmlspecialchars($record['student_name']) ?></strong>
                                                    <small class="d-block text-muted"><?= htmlspecialchars($record['student_email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($record['subject_name']) ?></td>
                                        <td><?= htmlspecialchars($record['course_name']) ?></td>
                                        <td><?= htmlspecialchars($record['year_name']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower($record['status']) ?>" id="status-<?= $record['id'] ?>">
                                                <?= $record['status'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm update-status" 
                                                    data-id="<?= $record['id'] ?>" 
                                                    data-original="<?= $record['status'] ?>"
                                                    style="width: 100px;">
                                                <option value="Present" <?= $record['status']=='Present'?'selected':'' ?>>Present</option>
                                                <option value="Absent" <?= $record['status']=='Absent'?'selected':'' ?>>Absent</option>
                                                <option value="Late" <?= $record['status']=='Late'?'selected':'' ?>>Late</option>
                                            </select>
                                            <div class="spinner-border spinner-border-sm text-primary d-none" id="spinner-<?= $record['id'] ?>"></div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Summary Footer -->
                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Showing <?= count($records) ?> records
                                </small>
                                <div>
                                    <span class="badge bg-success me-2">Present: <?= $present ?></span>
                                    <span class="badge bg-danger me-2">Absent: <?= $absent ?></span>
                                    <span class="badge bg-warning">Late: <?= $late ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notification -->
<div id="notification" class="notification d-none"></div>

<script>
// Filter functions
function filterByStatus(status) {
    const url = new URL(window.location.href);
    url.searchParams.set('status', status);
    window.location.href = url.toString();
}

function removeFilter(param) {
    const url = new URL(window.location.href);
    url.searchParams.delete(param);
    window.location.href = url.toString();
}

// Handle status updates
document.querySelectorAll('.update-status').forEach(select => {
    select.addEventListener('change', function() {
        const id = this.dataset.id;
        const status = this.value;
        const original = this.dataset.original;
        const spinner = document.getElementById(`spinner-${id}`);
        const badge = document.getElementById(`status-${id}`);
        
        // Disable and show spinner
        this.disabled = true;
        spinner.classList.remove('d-none');
        
        // Send update
        fetch('view_attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `update_status=1&id=${id}&status=${status}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                // Update badge
                badge.className = `status-badge status-${status.toLowerCase()}`;
                badge.textContent = status;
                this.dataset.original = status;
                showNotification('Status updated successfully!', 'success');
                
                // Reload after 1 second to update stats
                setTimeout(() => location.reload(), 1000);
            } else {
                this.value = original;
                showNotification('Failed to update status!', 'danger');
            }
        })
        .catch(() => {
            this.value = original;
            showNotification('Error! Please try again.', 'danger');
        })
        .finally(() => {
            this.disabled = false;
            spinner.classList.add('d-none');
        });
    });
});

// Export to CSV
function exportToCSV() {
    const rows = [['Date','Day','Time','Student','Email','Subject','Course','Year','Status']];
    
    <?php foreach ($records as $record): ?>
    rows.push([
        '<?= $record['date'] ?>',
        '<?= $record['day'] ?>',
        '<?= $record['start_time'] ? date('g:i A', strtotime($record['start_time'])) : '--' ?>',
        '<?= addslashes($record['student_name']) ?>',
        '<?= addslashes($record['student_email']) ?>',
        '<?= addslashes($record['subject_name']) ?>',
        '<?= addslashes($record['course_name']) ?>',
        '<?= addslashes($record['year_name']) ?>',
        '<?= $record['status'] ?>'
    ]);
    <?php endforeach; ?>
    
    const csv = rows.map(row => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'attendance_<?= date('Y-m-d') ?>.csv';
    a.click();
}

// Show notification
function showNotification(msg, type) {
    const notif = document.getElementById('notification');
    notif.className = `notification alert alert-${type} alert-dismissible fade show`;
    notif.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-${type=='success'?'check-circle':'exclamation-triangle'} me-2"></i>
            <div class="flex-grow-1">${msg}</div>
            <button type="button" class="btn-close" onclick="this.parentElement.parentElement.classList.add('d-none')"></button>
        </div>
    `;
    notif.classList.remove('d-none');
    setTimeout(() => notif.classList.add('d-none'), 3000);
}

// Auto refresh every 30 seconds if no updates pending
setInterval(() => {
    const updating = document.querySelectorAll('.update-status:disabled');
    if (updating.length === 0) {
        location.reload();
    }
}, 30000);
</script>

<?php include('../includes/faculty_footer.php'); ?>