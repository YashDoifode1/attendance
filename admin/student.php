<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$alertType = "";

// Fetch filters
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$years   = $pdo->query("SELECT * FROM years ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$selected_course_id = $_POST['course_id'] ?? '';
$selected_year_id   = $_POST['year_id'] ?? '';

// Fetch students
$query = "
    SELECT s.*, c.course_name, y.year_name, sess.session_name
    FROM students s
    JOIN courses c ON s.course_id = c.id
    JOIN years y ON s.year_id = y.id
    JOIN sessions sess ON s.session_id = sess.id
    WHERE s.role = 'student'
";

$params = [];

if ($selected_course_id) {
    $query .= " AND s.course_id = ?";
    $params[] = $selected_course_id;
}
if ($selected_year_id) {
    $query .= " AND s.year_id = ?";
    $params[] = $selected_year_id;
}

$query .= " ORDER BY s.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Delete student
if (isset($_POST['delete_student'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$_POST['student_id']]);
        $message = "Student deleted successfully";
        $alertType = "success";
        
        // Refresh the page to update the list
        echo "<meta http-equiv='refresh' content='1'>";
    } catch (Exception $e) {
        $message = "Unable to delete student (linked data exists)";
        $alertType = "error";
    }
}

// Calculate statistics
$totalStudents = count($students);
$recentCount = 0;
$courseDistribution = [];
$semesterDistribution = [];

foreach ($students as $s) {
    // Recent students (last 30 days)
    if (strtotime($s['created_at']) > strtotime('-30 days')) {
        $recentCount++;
    }
    
    // Course distribution
    $courseDistribution[$s['course_name']] = ($courseDistribution[$s['course_name']] ?? 0) + 1;
    
    // Semester distribution
    $semesterDistribution[$s['year_name']] = ($semesterDistribution[$s['year_name']] ?? 0) + 1;
}

include('includes/sidebar_header.php');
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Student Management</h4>
                <p class="mb-0" style="color: var(--text-muted);">
                    <?= $selected_course_id || $selected_year_id ? 'Filtered student list' : 'Showing all registered students' ?>
                </p>
            </div>
            <button class="btn btn-primary" onclick="showAddStudentModal()">
                <i class="bi bi-plus-circle me-2"></i>Add New Student
            </button>
        </div>
    </div>
</div>

<!-- Alert Message -->
<?php if ($message): ?>
    <div class="alert alert-<?= $alertType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-<?= $alertType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close btn-close-white" onclick="this.closest('.alert').remove()"></button>
    </div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-4 col-md-4">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-people-fill" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1" style="color: var(--text-muted);">Total Students</h6>
                        <h3 class="mb-0 fw-bold counter" data-target="<?= $totalStudents ?>"><?= $totalStudents ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-4">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-calendar-check-fill" style="color: var(--success); font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1" style="color: var(--text-muted);">New (30 Days)</h6>
                        <h3 class="mb-0 fw-bold counter" data-target="<?= $recentCount ?>"><?= $recentCount ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-4">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-funnel-fill" style="color: var(--warning); font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1" style="color: var(--text-muted);">Active Filters</h6>
                        <h3 class="mb-0 fw-bold">
                            <span class="badge" style="background: <?= $selected_course_id || $selected_year_id ? 'var(--sidebar-active)' : 'var(--border-color)' ?>; color: white;">
                                <?= $selected_course_id || $selected_year_id ? 'Applied' : 'None' ?>
                            </span>
                        </h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 mb-4">
    <div class="card-body">
        <form method="POST" id="filterForm">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label" style="color: var(--text-secondary);">Course Filter</label>
                    <select name="course_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $selected_course_id == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" style="color: var(--text-secondary);">Semester Filter</label>
                    <select name="year_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Semesters</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y['id'] ?>" <?= $selected_year_id == $y['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($y['year_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if ($selected_course_id || $selected_year_id): ?>
                <div class="mt-3">
                    <a href="manage_student.php" class="btn btn-sm" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);">
                        <i class="bi bi-x-circle me-1"></i>Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="card border-0">
            <div class="card-header">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-bar-chart-fill me-2" style="color: var(--sidebar-active);"></i>Students by Semester
                </h5>
            </div>
            <div class="card-body">
                <canvas id="barChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card border-0">
            <div class="card-header">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-pie-chart-fill me-2" style="color: var(--sidebar-active);"></i>Distribution
                </h5>
            </div>
            <div class="card-body">
                <canvas id="donutChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Student Table Card -->
<div class="card border-0">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
            <i class="bi bi-table me-2" style="color: var(--sidebar-active);"></i>Student Records
        </h5>
        <div class="d-flex gap-3">
            <div class="search-box" style="width: 250px;">
                <i class="bi bi-search"></i>
                <input type="text" id="studentSearch" placeholder="Search students..." onkeyup="filterStudents()">
            </div>
            <button class="btn btn-sm" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="exportTable()">
                <i class="bi bi-download me-1"></i>Export
            </button>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
            <table class="table table-hover mb-0" style="color: var(--text-primary);" id="studentTable">
                <thead style="background: var(--card-bg); border-bottom: 2px solid var(--border-color); position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th class="ps-4 py-3">Student</th>
                        <th class="py-3">Email</th>
                        <th class="py-3">Course</th>
                        <th class="py-3">Semester</th>
                        <th class="py-3">Session</th>
                        <th class="py-3">Status</th>
                        <th class="pe-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="studentTableBody">
                    <?php if ($students): ?>
                        <?php foreach ($students as $s): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);" data-student-id="<?= $s['id'] ?>">
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="user-avatar" style="width: 40px; height: 40px; font-size: 0.9rem;">
                                            <?= strtoupper(substr($s['name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold" style="color: var(--text-primary);">
                                                <?= htmlspecialchars($s['name']) ?>
                                            </div>
                                            <small style="color: var(--text-muted);">ID: #<?= $s['id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3" style="color: var(--text-secondary);">
                                    <i class="bi bi-envelope me-1" style="color: var(--text-muted);"></i>
                                    <?= htmlspecialchars($s['email']) ?>
                                </td>
                                <td class="py-3">
                                    <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">
                                        <?= htmlspecialchars($s['course_name']) ?>
                                    </span>
                                </td>
                                <td class="py-3" style="color: var(--text-secondary);">
                                    <i class="bi bi-calendar3 me-1" style="color: var(--text-muted);"></i>
                                    <?= htmlspecialchars($s['year_name']) ?>
                                </td>
                                <td class="py-3" style="color: var(--text-secondary);">
                                    <?= htmlspecialchars($s['session_name']) ?>
                                </td>
                                <td class="py-3">
                                    <?php
                                    // Determine status based on recent attendance or activity
                                    $status = 'active'; // This could be dynamic based on your logic
                                    ?>
                                    <span class="badge bg-<?= $status === 'active' ? 'success' : 'secondary' ?> bg-opacity-10" 
                                          style="color: <?= $status === 'active' ? 'var(--success)' : 'var(--text-muted)' ?>; padding: 6px 12px;">
                                        <span class="user-status me-1" style="background: <?= $status === 'active' ? 'var(--success)' : 'var(--text-muted)' ?>;"></span>
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td class="pe-4 py-3 text-end">
                                    <button class="btn btn-sm" style="background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); margin-right: 5px;" 
                                            onclick="viewStudent(<?= $s['id'] ?>)" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <a href="edit_student.php?id=<?= $s['id'] ?>" 
                                       class="btn btn-sm" style="background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); margin-right: 5px;"
                                       title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmDelete(event, this)">
                                        <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                                        <button type="submit" name="delete_student" 
                                                class="btn btn-sm" style="background: transparent; color: #ef4444; border: 1px solid var(--border-color);"
                                                title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5" style="color: var(--text-muted);">
                                <i class="bi bi-inbox display-4 d-block mb-3" style="color: var(--border-color);"></i>
                                <h6 style="color: var(--text-primary);">No students found</h6>
                                <p class="mb-0">Try adjusting your filters or add a new student.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-between align-items-center">
        <small style="color: var(--text-muted);">
            Showing <span id="visibleCount"><?= count($students) ?></span> of <?= $totalStudents ?> students
        </small>
        <div>
            <span class="badge me-2" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">
                <i class="bi bi-sort-down me-1"></i>Name (A-Z)
            </span>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div id="addStudentModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--card-bg); border-radius: 20px; width: 90%; max-width: 600px; border: 1px solid var(--border-color); max-height: 90vh; overflow-y: auto;">
        <div class="p-4 border-bottom" style="border-color: var(--border-color) !important;">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-person-plus me-2" style="color: var(--sidebar-active);"></i>Add New Student
                </h5>
                <button class="btn-close btn-close-white" onclick="hideAddStudentModal()"></button>
            </div>
        </div>
        <form action="add_student.php" method="POST" onsubmit="return validateStudentForm()">
            <div class="p-4">
                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label" style="color: var(--text-secondary);">Full Name</label>
                        <input type="text" name="name" id="studentName" class="form-control" required placeholder="Enter full name">
                    </div>
                    <div class="col-12">
                        <label class="form-label" style="color: var(--text-secondary);">Email Address</label>
                        <input type="email" name="email" id="studentEmail" class="form-control" required placeholder="Enter email">
                    </div>
                    <div class="col-12">
                        <label class="form-label" style="color: var(--text-secondary);">Password</label>
                        <input type="password" name="password" id="studentPassword" class="form-control" required placeholder="Enter password">
                        <small style="color: var(--text-muted);">Minimum 6 characters</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" style="color: var(--text-secondary);">Course</label>
                        <select name="course_id" id="studentCourse" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" style="color: var(--text-secondary);">Semester</label>
                        <select name="year_id" id="studentYear" class="form-select" required>
                            <option value="">Select Semester</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y['id'] ?>"><?= htmlspecialchars($y['year_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" style="color: var(--text-secondary);">Session</label>
                        <select name="session_id" id="studentSession" class="form-select" required>
                            <option value="">Select Session</option>
                            <?php
                            $sessions = $pdo->query("SELECT * FROM sessions ORDER BY session_name DESC")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($sessions as $sess): ?>
                                <option value="<?= $sess['id'] ?>"><?= htmlspecialchars($sess['session_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="p-4 border-top" style="border-color: var(--border-color) !important; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 10px 24px;" onclick="hideAddStudentModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                    <i class="bi bi-plus-circle me-2"></i>Add Student
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Student Modal -->
<div id="viewStudentModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--card-bg); border-radius: 20px; width: 90%; max-width: 600px; border: 1px solid var(--border-color);">
        <div class="p-4 border-bottom" style="border-color: var(--border-color) !important;">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold" style="color: var(--text-primary);" id="viewModalTitle">Student Details</h5>
                <button class="btn-close btn-close-white" onclick="hideViewStudentModal()"></button>
            </div>
        </div>
        <div class="p-4" id="studentDetailsContent">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Self-Contained JavaScript -->
<script>
// ==================== MODAL FUNCTIONS ====================

function showAddStudentModal() {
    document.getElementById('addStudentModal').style.display = 'flex';
}

function hideAddStudentModal() {
    document.getElementById('addStudentModal').style.display = 'none';
}

function showViewStudentModal() {
    document.getElementById('viewStudentModal').style.display = 'flex';
}

function hideViewStudentModal() {
    document.getElementById('viewStudentModal').style.display = 'none';
}

// ==================== FORM VALIDATION ====================

function validateStudentForm() {
    const name = document.getElementById('studentName')?.value.trim();
    const email = document.getElementById('studentEmail')?.value.trim();
    const password = document.getElementById('studentPassword')?.value;
    const course = document.getElementById('studentCourse')?.value;
    const year = document.getElementById('studentYear')?.value;
    const session = document.getElementById('studentSession')?.value;
    
    if (!name) {
        alert('Please enter student name');
        return false;
    }
    
    if (!email || !email.includes('@') || !email.includes('.')) {
        alert('Please enter a valid email address');
        return false;
    }
    
    if (!password || password.length < 6) {
        alert('Password must be at least 6 characters long');
        return false;
    }
    
    if (!course) {
        alert('Please select a course');
        return false;
    }
    
    if (!year) {
        alert('Please select a semester');
        return false;
    }
    
    if (!session) {
        alert('Please select a session');
        return false;
    }
    
    return true;
}

// ==================== VIEW STUDENT DETAILS ====================

function viewStudent(id) {
    const row = document.querySelector(`tr[data-student-id="${id}"]`);
    if (!row) return;
    
    const name = row.querySelector('.fw-semibold').textContent;
    const email = row.querySelector('td:nth-child(2').textContent.replace('📧', '').trim();
    const course = row.querySelector('td:nth-child(3) .badge').textContent;
    const semester = row.querySelector('td:nth-child(4)').textContent.replace('📅', '').trim();
    const session = row.querySelector('td:nth-child(5)').textContent.trim();
    const status = row.querySelector('td:nth-child(6) .badge').textContent.trim();
    
    document.getElementById('viewModalTitle').innerHTML = `<i class="bi bi-person-badge me-2" style="color: var(--sidebar-active);"></i>${name}`;
    
    document.getElementById('studentDetailsContent').innerHTML = `
        <div class="text-center mb-4">
            <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 1.8rem;">
                ${name.charAt(0).toUpperCase()}
            </div>
            <h5 style="color: var(--text-primary);">${name}</h5>
            <p style="color: var(--sidebar-active);">${email}</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-6">
                <div class="p-3 rounded-3" style="background: var(--sidebar-bg);">
                    <small class="text-muted d-block mb-2">Course</small>
                    <span style="color: var(--text-primary);">${course}</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 rounded-3" style="background: var(--sidebar-bg);">
                    <small class="text-muted d-block mb-2">Semester</small>
                    <span style="color: var(--text-primary);">${semester}</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 rounded-3" style="background: var(--sidebar-bg);">
                    <small class="text-muted d-block mb-2">Session</small>
                    <span style="color: var(--text-primary);">${session}</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 rounded-3" style="background: var(--sidebar-bg);">
                    <small class="text-muted d-block mb-2">Status</small>
                    <span class="badge bg-${status.toLowerCase() === 'active' ? 'success' : 'secondary'}" 
                          style="color: ${status.toLowerCase() === 'active' ? 'var(--success)' : 'var(--text-muted)'}">
                        ${status}
                    </span>
                </div>
            </div>
        </div>
        
        <div class="mt-4 d-flex gap-2 justify-content-end">
            <button class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="hideViewStudentModal()">Close</button>
            <a href="edit_student.php?id=${id}" class="btn btn-primary">Edit Student</a>
        </div>
    `;
    
    showViewStudentModal();
}

// ==================== SEARCH FUNCTIONALITY ====================

function filterStudents() {
    const searchTerm = document.getElementById('studentSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#studentTableBody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    document.getElementById('visibleCount').textContent = visibleCount;
}

// ==================== DELETE CONFIRMATION ====================

function confirmDelete(event, form) {
    event.preventDefault();
    if (confirm('Delete this student? This action cannot be undone.')) {
        form.submit();
    }
    return false;
}

// ==================== EXPORT TABLE ====================

function exportTable() {
    const rows = [];
    const headers = ['Name', 'Email', 'Course', 'Semester', 'Session', 'Status'];
    
    document.querySelectorAll('#studentTableBody tr').forEach(row => {
        if (row.style.display !== 'none') {
            const rowData = [
                row.querySelector('.fw-semibold').textContent,
                row.querySelector('td:nth-child(2)').textContent.replace('📧', '').trim(),
                row.querySelector('td:nth-child(3) .badge').textContent,
                row.querySelector('td:nth-child(4)').textContent.replace('📅', '').trim(),
                row.querySelector('td:nth-child(5)').textContent.trim(),
                row.querySelector('td:nth-child(6) .badge').textContent.trim()
            ];
            rows.push(rowData);
        }
    });
    
    let csv = headers.join(',') + '\n';
    rows.forEach(row => {
        csv += row.map(cell => `"${cell}"`).join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'students_export.csv';
    a.click();
}

// ==================== CHARTS ====================

// Bar Chart
new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($semesterDistribution)) ?>,
        datasets: [{
            label: 'Number of Students',
            data: <?= json_encode(array_values($semesterDistribution)) ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#94a3b8' }
            },
            x: {
                grid: { display: false },
                ticks: { color: '#94a3b8' }
            }
        }
    }
});

// Donut Chart
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($courseDistribution)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($courseDistribution)) ?>,
            backgroundColor: [
                'rgba(59, 130, 246, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(139, 92, 246, 0.8)',
                'rgba(236, 72, 153, 0.8)',
                'rgba(239, 68, 68, 0.8)'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: '#94a3b8' }
            }
        }
    }
});

// ==================== COUNTER ANIMATION ====================

function animateCounters() {
    const counters = document.querySelectorAll('.counter');
    
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
        const current = parseInt(counter.textContent);
        
        if (current < target) {
            let start = current;
            const increment = Math.ceil((target - start) / 30);
            
            const timer = setInterval(() => {
                start += increment;
                if (start >= target) {
                    counter.textContent = target;
                    clearInterval(timer);
                } else {
                    counter.textContent = start;
                }
            }, 30);
        }
    });
}

// ==================== AUTO-HIDE ALERTS ====================

function setupAlerts() {
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
}

// ==================== CLICK OUTSIDE TO CLOSE MODALS ====================

document.addEventListener('click', function(event) {
    const addModal = document.getElementById('addStudentModal');
    const viewModal = document.getElementById('viewStudentModal');
    
    if (addModal.style.display === 'flex' && event.target === addModal) {
        hideAddStudentModal();
    }
    
    if (viewModal.style.display === 'flex' && event.target === viewModal) {
        hideViewStudentModal();
    }
});

// ==================== ESCAPE KEY TO CLOSE MODALS ====================

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideAddStudentModal();
        hideViewStudentModal();
    }
});

// ==================== INITIALIZATION ====================

document.addEventListener('DOMContentLoaded', function() {
    animateCounters();
    setupAlerts();
});
</script>

<!-- Custom CSS for this page -->
<style>
/* Form controls styling */
.form-control, .form-select {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 0.95rem;
    width: 100%;
}

.form-control:focus, .form-select:focus {
    background: var(--sidebar-hover);
    border-color: var(--sidebar-active);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    color: var(--text-primary);
    outline: none;
}

.form-control::placeholder {
    color: var(--text-muted);
    opacity: 0.7;
}

.form-label {
    font-weight: 500;
    margin-bottom: 8px;
    font-size: 0.9rem;
    display: block;
}

/* Modal styling */
.btn-close-white {
    background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
    border: 0;
    border-radius: 4px;
    width: 1em;
    height: 1em;
    cursor: pointer;
}

/* Table hover effect */
.table-hover tbody tr:hover {
    background: var(--sidebar-hover) !important;
}

/* Badge styling */
.badge {
    font-weight: 500;
    padding: 6px 12px;
    border-radius: 30px;
}

/* User avatar in table */
.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--sidebar-active), var(--sidebar-active-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    overflow: hidden;
}

/* Status indicator */
.user-status {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* Alert styling */
.alert {
    border: none;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

/* Search box */
.search-box {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 30px;
    padding: 8px 16px;
    display: flex;
    align-items: center;
}

.search-box i {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.search-box input {
    border: none;
    background: transparent;
    padding: 0 8px;
    width: 100%;
    outline: none;
    color: var(--text-primary);
}

.search-box input::placeholder {
    color: var(--text-muted);
}

/* Table sticky header */
.table-responsive::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.table-responsive::-webkit-scrollbar-track {
    background: var(--sidebar-bg);
}

.table-responsive::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: var(--sidebar-active);
}

/* Responsive */
@media (max-width: 768px) {
    .card-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch !important;
    }
    
    .search-box {
        width: 100% !important;
    }
    
    .table {
        font-size: 0.85rem;
    }
}
</style>

<?php include('includes/footer.php'); ?>