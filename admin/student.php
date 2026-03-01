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
$sessions = $pdo->query("SELECT * FROM sessions ORDER BY session_name DESC")->fetchAll(PDO::FETCH_ASSOC);

$selected_course_id = $_GET['course_id'] ?? $_POST['course_id'] ?? '';
$selected_year_id   = $_GET['year_id'] ?? $_POST['year_id'] ?? '';
$selected_session_id = $_GET['session_id'] ?? $_POST['session_id'] ?? '';

/* ADD STUDENT */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $course_id = intval($_POST['course_id']);
    $year_id = intval($_POST['year_id']);
    $session_id = intval($_POST['session_id']);
    $avatar = 'default-avatar.png';

    if (!$name || !$email || !$password || !$course_id || !$year_id || !$session_id) {
        $message = "All fields are required.";
        $alertType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $alertType = "error";
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetchColumn() > 0) {
            $message = "Email already exists.";
            $alertType = "error";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO students (name, email, password, avatar, course_id, year_id, session_id, role, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'student', NOW())"
            );
            $stmt->execute([$name, $email, $hash, $avatar, $course_id, $year_id, $session_id]);
            $message = "Student added successfully.";
            $alertType = "success";
            
            // Refresh filters to show new student
            $selected_course_id = $course_id;
            $selected_year_id = $year_id;
            $selected_session_id = $session_id;
        }
    }
}

/* EDIT STUDENT */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $id = intval($_POST['student_id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $course_id = intval($_POST['course_id']);
    $year_id = intval($_POST['year_id']);
    $session_id = intval($_POST['session_id']);
    
    if (!$name || !$email || !$course_id || !$year_id || !$session_id) {
        $message = "All fields are required.";
        $alertType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $alertType = "error";
    } else {
        // Check if email exists for other users
        $check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ? AND id != ?");
        $check->execute([$email, $id]);
        
        if ($check->fetchColumn() > 0) {
            $message = "Email already exists for another student.";
            $alertType = "error";
        } else {
            if (!empty($password)) {
                // Update with new password
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE students SET name = ?, email = ?, password = ?, course_id = ?, year_id = ?, session_id = ? WHERE id = ? AND role = 'student'");
                $stmt->execute([$name, $email, $hash, $course_id, $year_id, $session_id, $id]);
            } else {
                // Update without password
                $stmt = $pdo->prepare("UPDATE students SET name = ?, email = ?, course_id = ?, year_id = ?, session_id = ? WHERE id = ? AND role = 'student'");
                $stmt->execute([$name, $email, $course_id, $year_id, $session_id, $id]);
            }
            $message = "Student updated successfully.";
            $alertType = "success";
        }
    }
}

/* DELETE STUDENT */
if (isset($_POST['delete_student'])) {
    try {
        // Check if student has attendance records
        $checkAttendance = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ?");
        $checkAttendance->execute([intval($_POST['student_id'])]);
        
        if ($checkAttendance->fetchColumn() > 0) {
            $message = "Student cannot be deleted as they have attendance records.";
            $alertType = "error";
        } else {
            $stmt = $pdo->prepare("DELETE FROM students WHERE id = ? AND role = 'student'");
            $stmt->execute([intval($_POST['student_id'])]);
            $message = "Student deleted successfully.";
            $alertType = "success";
            
            // Refresh the page to update the list
            echo "<meta http-equiv='refresh' content='1'>";
        }
    } catch (Exception $e) {
        $message = "Error deleting student.";
        $alertType = "error";
    }
}

/* FETCH SINGLE STUDENT FOR EDIT */
$editStudent = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT id, name, email, course_id, year_id, session_id FROM students WHERE id = ? AND role = 'student'");
    $stmt->execute([intval($_GET['edit'])]);
    $editStudent = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* FETCH STUDENTS with filters */
$query = "
    SELECT s.*, c.course_name, y.year_name, sess.session_name,
           (SELECT COUNT(*) FROM attendance WHERE student_id = s.id) as attendance_count,
           (SELECT COUNT(*) FROM attendance WHERE student_id = s.id AND DATE(date) = CURDATE()) as today_attendance
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
if ($selected_session_id) {
    $query .= " AND s.session_id = ?";
    $params[] = $selected_session_id;
}

$query .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalStudents = count($students);
$recentCount = 0;
$activeToday = 0;
$courseDistribution = [];
$semesterDistribution = [];

foreach ($students as $s) {
    // Recent students (last 30 days)
    if (strtotime($s['created_at']) > strtotime('-30 days')) {
        $recentCount++;
    }
    
    // Active today
    if ($s['today_attendance'] > 0) {
        $activeToday++;
    }
    
    // Course distribution
    $courseDistribution[$s['course_name']] = ($courseDistribution[$s['course_name']] ?? 0) + 1;
    
    // Semester distribution
    $semesterDistribution[$s['year_name']] = ($semesterDistribution[$s['year_name']] ?? 0) + 1;
}

include('includes/sidebar_header.php');
?>

<!-- Custom Fonts and Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Page Header with Gradient -->
<div class="page-header">
    <div class="header-content">
        <div class="header-left">
            <div class="header-icon">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <h1 class="header-title">Student Management</h1>
                <p class="header-subtitle">
                    <?= $selected_course_id || $selected_year_id || $selected_session_id ? 'Filtered student list' : 'Showing all registered students' ?>
                </p>
            </div>
        </div>
        <button class="btn-primary-glow" onclick="showAddStudentModal()">
            <i class="fas fa-plus-circle"></i>
            <span>Add New Student</span>
        </button>
    </div>
</div>

<!-- Alert Message -->
<?php if ($message): ?>
    <div class="alert-modern alert-<?= $alertType === 'success' ? 'success' : 'danger' ?>">
        <div class="alert-icon">
            <i class="fas fa-<?= $alertType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
        </div>
        <div class="alert-content">
            <h6><?= $alertType === 'success' ? 'Success' : 'Error' ?></h6>
            <p><?= htmlspecialchars($message) ?></p>
        </div>
        <button class="alert-close" onclick="this.closest('.alert-modern').remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
<?php endif; ?>

<!-- KPI Cards with Animation -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="kpi-content">
            <span class="kpi-label">Total Students</span>
            <div class="kpi-value-wrapper">
                <h3 class="kpi-value counter" data-target="<?= $totalStudents ?>"><?= $totalStudents ?></h3>
                <span class="kpi-trend positive">
                    <i class="fas fa-arrow-up"></i> 12%
                </span>
            </div>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="kpi-content">
            <span class="kpi-label">Active Today</span>
            <div class="kpi-value-wrapper">
                <h3 class="kpi-value counter" data-target="<?= $activeToday ?>"><?= $activeToday ?></h3>
                <span class="kpi-badge">present</span>
            </div>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fas fa-user-plus"></i>
        </div>
        <div class="kpi-content">
            <span class="kpi-label">New (30 Days)</span>
            <div class="kpi-value-wrapper">
                <h3 class="kpi-value counter" data-target="<?= $recentCount ?>"><?= $recentCount ?></h3>
                <span class="kpi-period">this month</span>
            </div>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
            <i class="fas fa-filter"></i>
        </div>
        <div class="kpi-content">
            <span class="kpi-label">Active Filters</span>
            <div class="kpi-value-wrapper">
                <h3 class="kpi-value"><?= ($selected_course_id ? 1 : 0) + ($selected_year_id ? 1 : 0) + ($selected_session_id ? 1 : 0) ?></h3>
                <span class="kpi-badge filter-badge">applied</span>
            </div>
        </div>
    </div>
</div>

<!-- Filters Section with Modern Design -->
<div class="filters-section">
    <div class="filters-header">
        <div class="filters-title">
            <i class="fas fa-sliders-h"></i>
            <h4>Filter Students</h4>
        </div>
        <?php if ($selected_course_id || $selected_year_id || $selected_session_id): ?>
            <a href="student.php" class="clear-filters">
                <i class="fas fa-times"></i> Clear All Filters
            </a>
        <?php endif; ?>
    </div>
    
    <form method="GET" id="filterForm" class="filters-grid">
        <div class="filter-group">
            <label class="filter-label">
                <i class="fas fa-book"></i> Course
            </label>
            <div class="filter-select-wrapper">
                <select name="course_id" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selected_course_id == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i class="fas fa-chevron-down select-arrow"></i>
            </div>
        </div>

        <div class="filter-group">
            <label class="filter-label">
                <i class="fas fa-layer-group"></i> Semester
            </label>
            <div class="filter-select-wrapper">
                <select name="year_id" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Semesters</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y['id'] ?>" <?= $selected_year_id == $y['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($y['year_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i class="fas fa-chevron-down select-arrow"></i>
            </div>
        </div>

        <div class="filter-group">
            <label class="filter-label">
                <i class="fas fa-calendar-alt"></i> Session
            </label>
            <div class="filter-select-wrapper">
                <select name="session_id" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Sessions</option>
                    <?php foreach ($sessions as $sess): ?>
                        <option value="<?= $sess['id'] ?>" <?= $selected_session_id == $sess['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sess['session_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i class="fas fa-chevron-down select-arrow"></i>
            </div>
        </div>
    </form>
</div>

<!-- Analytics Dashboard -->
<div class="analytics-grid">
    <div class="chart-card">
        <div class="chart-header">
            <div class="chart-title">
                <i class="fas fa-chart-bar"></i>
                <h5>Students by Semester</h5>
            </div>
            <div class="chart-actions">
                <button class="chart-action" onclick="refreshChart('bar')">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        <div class="chart-body">
            <canvas id="barChart" style="height: 300px;"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-header">
            <div class="chart-title">
                <i class="fas fa-chart-pie"></i>
                <h5>Course Distribution</h5>
            </div>
            <div class="chart-actions">
                <button class="chart-action" onclick="refreshChart('pie')">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        <div class="chart-body">
            <canvas id="donutChart" style="height: 300px;"></canvas>
        </div>
    </div>
</div>

<!-- Students Table -->
<div class="table-container">
    <div class="table-header">
        <div class="table-header-left">
            <div class="table-title">
                <i class="fas fa-list"></i>
                <h5>Student Records</h5>
            </div>
            <div class="table-stats">
                <span class="stat-item">
                    <i class="fas fa-users"></i> <?= count($students) ?> students
                </span>
                <span class="stat-divider"></span>
                <span class="stat-item">
                    <i class="fas fa-clock"></i> Updated just now
                </span>
            </div>
        </div>
        <div class="table-header-right">
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="studentSearch" placeholder="Search by name, email..." onkeyup="filterStudents()">
                <button class="search-clear" onclick="clearSearch()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <button class="btn-export" onclick="exportTable()">
                <i class="fas fa-download"></i>
                <span>Export</span>
            </button>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th class="ps-4">Student</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Semester</th>
                    <th>Session</th>
                    <th>Attendance</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody id="studentTableBody">
                <?php if ($students): ?>
                    <?php foreach ($students as $index => $s): ?>
                        <tr class="student-row" data-student-id="<?= $s['id'] ?>" data-student-name="<?= htmlspecialchars($s['name']) ?>" data-student-email="<?= htmlspecialchars($s['email']) ?>" data-student-course="<?= htmlspecialchars($s['course_name']) ?>" data-student-year="<?= htmlspecialchars($s['year_name']) ?>" data-student-session="<?= htmlspecialchars($s['session_name']) ?>" data-attendance-count="<?= $s['attendance_count'] ?>" style="animation-delay: <?= $index * 0.05 ?>s">
                            <td class="ps-4">
                                <div class="student-info">
                                    <div class="student-avatar">
                                        <?= strtoupper(substr($s['name'], 0, 2)) ?>
                                        <?php if ($s['today_attendance'] > 0): ?>
                                            <span class="online-indicator"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="student-details">
                                        <div class="student-name"><?= htmlspecialchars($s['name']) ?></div>
                                        <div class="student-id">ID: STU-<?= str_pad($s['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="email-cell">
                                    <i class="fas fa-envelope"></i>
                                    <?= htmlspecialchars($s['email']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge-course"><?= htmlspecialchars($s['course_name']) ?></span>
                            </td>
                            <td>
                                <span class="text-semester">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?= htmlspecialchars($s['year_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-session">
                                    <i class="fas fa-clock"></i>
                                    <?= htmlspecialchars($s['session_name']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="attendance-stats">
                                    <span class="attendance-count"><?= $s['attendance_count'] ?></span>
                                    <span class="attendance-label">records</span>
                                </div>
                            </td>
                            <td>
                                <?php
                                $status = $s['attendance_count'] > 0 ? 'active' : 'inactive';
                                $statusColor = $status === 'active' ? '#10b981' : '#6b7280';
                                ?>
                                <span class="status-badge status-<?= $status ?>">
                                    <span class="status-dot" style="background: <?= $statusColor ?>;"></span>
                                    <?= ucfirst($status) ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="action-buttons">
                                    <button class="action-btn view-btn" onclick="viewStudent(<?= $s['id'] ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit-btn" onclick="editStudent(<?= $s['id'] ?>)" title="Edit Student">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete-btn" onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')" title="Delete Student">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            <div class="empty-state-content">
                                <i class="fas fa-inbox"></i>
                                <h6>No students found</h6>
                                <p>Try adjusting your filters or add a new student.</p>
                                <button class="btn-primary-sm" onclick="showAddStudentModal()">
                                    <i class="fas fa-plus"></i> Add Student
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <div class="footer-left">
            <span class="showing-info">
                Showing <span id="visibleCount"><?= count($students) ?></span> of <?= $totalStudents ?> students
            </span>
        </div>
        <div class="footer-right">
            <span class="sort-info">
                <i class="fas fa-sort-alpha-down"></i> Sorted by Name (A-Z)
            </span>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div id="addStudentModal" class="modal-overlay">
    <div class="modal-modern">
        <div class="modal-header-modern">
            <div class="modal-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="modal-title-wrapper">
                <h3 class="modal-title">Add New Student</h3>
                <p class="modal-subtitle">Fill in the student details below</p>
            </div>
            <button class="modal-close" onclick="hideAddStudentModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" onsubmit="return validateStudentForm()" class="modal-form">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label-modern">
                        <i class="fas fa-user"></i> Full Name
                    </label>
                    <div class="input-wrapper">
                        <input type="text" name="name" id="addStudentName" class="form-input-modern" 
                               required placeholder="e.g., John Doe" autocomplete="off">
                        <div class="input-focus-border"></div>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label-modern">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <div class="input-wrapper">
                        <input type="email" name="email" id="addStudentEmail" class="form-input-modern" 
                               required placeholder="e.g., john@example.com" autocomplete="off">
                        <div class="input-focus-border"></div>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label-modern">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="addStudentPassword" class="form-input-modern" 
                               required placeholder="Minimum 6 characters">
                        <div class="input-focus-border"></div>
                    </div>
                    <div class="input-hint">Minimum 6 characters</div>
                </div>

                <div class="form-group">
                    <label class="form-label-modern">
                        <i class="fas fa-book"></i> Course
                    </label>
                    <div class="select-wrapper-modern">
                        <select name="course_id" id="addStudentCourse" class="select-modern" required>
                            <option value="" disabled selected>Select Course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow-modern"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label-modern">
                        <i class="fas fa-layer-group"></i> Semester
                    </label>
                    <div class="select-wrapper-modern">
                        <select name="year_id" id="addStudentYear" class="select-modern" required>
                            <option value="" disabled selected>Select Semester</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y['id'] ?>"><?= htmlspecialchars($y['year_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow-modern"></i>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label-modern">
                        <i class="fas fa-calendar-alt"></i> Session
                    </label>
                    <div class="select-wrapper-modern">
                        <select name="session_id" id="addStudentSession" class="select-modern" required>
                            <option value="" disabled selected>Select Session</option>
                            <?php foreach ($sessions as $sess): ?>
                                <option value="<?= $sess['id'] ?>"><?= htmlspecialchars($sess['session_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow-modern"></i>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary-modern" onclick="hideAddStudentModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="add_student" class="btn-primary-modern">
                    <i class="fas fa-plus-circle"></i> Add Student
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Student Modal -->
<div id="viewStudentModal" class="modal-overlay">
    <div class="modal-modern" style="max-width: 600px;">
        <div class="modal-header-modern">
            <div class="modal-icon">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="modal-title-wrapper">
                <h3 class="modal-title" id="viewModalTitle">Student Profile</h3>
                <p class="modal-subtitle">Detailed student information</p>
            </div>
            <button class="modal-close" onclick="hideViewStudentModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body" id="studentDetailsContent">
            <!-- Content will be populated by JavaScript -->
        </div>

        <div class="modal-actions">
            <button class="btn-secondary-modern" onclick="hideViewStudentModal()">
                <i class="fas fa-times"></i> Close
            </button>
            <button class="btn-primary-modern" id="editFromViewBtn" onclick="">
                <i class="fas fa-edit"></i> Edit Student
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="modal-overlay">
    <div class="modal-modern" style="max-width: 450px;">
        <div class="modal-header-modern warning">
            <div class="modal-icon warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="modal-title-wrapper">
                <h3 class="modal-title">Confirm Delete</h3>
                <p class="modal-subtitle">This action cannot be undone</p>
            </div>
            <button class="modal-close" onclick="hideDeleteModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body text-center">
            <div class="delete-warning-content">
                <i class="fas fa-user-times delete-icon"></i>
                <h5 class="delete-name" id="deleteStudentName"></h5>
                <p class="delete-message">
                    Are you sure you want to delete this student?<br>
                    All associated data will be permanently removed.
                </p>
            </div>
        </div>

        <div class="modal-actions">
            <button class="btn-secondary-modern" onclick="hideDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <form method="POST" id="deleteForm" style="display: inline;">
                <input type="hidden" name="student_id" id="deleteStudentId" value="">
                <button type="submit" name="delete_student" class="btn-danger-modern">
                    <i class="fas fa-trash-alt"></i> Delete Student
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<?php if ($editStudent): ?>
<div id="editStudentModal" class="modal-overlay" style="display: flex;">
    <div class="modal-modern">
        <div class="modal-header-modern">
            <div class="modal-icon">
                <i class="fas fa-user-edit"></i>
            </div>
            <div class="modal-title-wrapper">
                <h3 class="modal-title">Edit Student</h3>
                <p class="modal-subtitle">Update student information</p>
            </div>
            <a href="student.php<?= $selected_course_id || $selected_year_id || $selected_session_id ? '?' . http_build_query(array_filter(['course_id' => $selected_course_id, 'year_id' => $selected_year_id, 'session_id' => $selected_session_id])) : '' ?>" class="modal-close">
                <i class="fas fa-times"></i>
            </a>
        </div>
        
        <form method="POST" class="modal-form">
            <input type="hidden" name="student_id" value="<?= $editStudent['id'] ?>">
            
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label-modern">
                        <i class="fas fa-user"></i> Full Name
                    </label>
                    <div class="input-wrapper">
                        <input type="text" name="name" id="editStudentName" class="form-input-modern" 
                               required value="<?= htmlspecialchars($editStudent['name']) ?>" placeholder="Enter full name">
                        <div class="input-focus-border"></div>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label-modern">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <div class="input-wrapper">
                        <input type="email" name="email" id="editStudentEmail" class="form-input-modern" 
                               required value="<?= htmlspecialchars($editStudent['email']) ?>" placeholder="Enter email">
                        <div class="input-focus-border"></div>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label-modern">
                        <i class="fas fa-lock"></i> New Password
                    </label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="editStudentPassword" class="form-input-modern" 
                               placeholder="Leave blank to keep current">
                        <div class="input-focus-border"></div>
                    </div>
                    <div class="input-hint">Minimum 6 characters if changing</div>
                </div>

                <div class="form-group">
                    <label class="form-label-modern">
                        <i class="fas fa-book"></i> Course
                    </label>
                    <div class="select-wrapper-modern">
                        <select name="course_id" id="editStudentCourse" class="select-modern" required>
                            <option value="" disabled>Select Course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $editStudent['course_id'] == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow-modern"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label-modern">
                        <i class="fas fa-layer-group"></i> Semester
                    </label>
                    <div class="select-wrapper-modern">
                        <select name="year_id" id="editStudentYear" class="select-modern" required>
                            <option value="" disabled>Select Semester</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y['id'] ?>" <?= $editStudent['year_id'] == $y['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($y['year_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow-modern"></i>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label-modern">
                        <i class="fas fa-calendar-alt"></i> Session
                    </label>
                    <div class="select-wrapper-modern">
                        <select name="session_id" id="editStudentSession" class="select-modern" required>
                            <option value="" disabled>Select Session</option>
                            <?php foreach ($sessions as $sess): ?>
                                <option value="<?= $sess['id'] ?>" <?= $editStudent['session_id'] == $sess['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sess['session_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow-modern"></i>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <a href="student.php<?= $selected_course_id || $selected_year_id || $selected_session_id ? '?' . http_build_query(array_filter(['course_id' => $selected_course_id, 'year_id' => $selected_year_id, 'session_id' => $selected_session_id])) : '' ?>" class="btn-secondary-modern">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" name="edit_student" class="btn-primary-modern">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- JavaScript -->
<script>
// ==================== ANIMATIONS ====================
document.addEventListener('DOMContentLoaded', function() {
    animateCounters();
    setupAlerts();
    initCharts();
    animateRows();
});

function animateRows() {
    const rows = document.querySelectorAll('.student-row');
    rows.forEach((row, index) => {
        row.style.animation = `slideIn 0.3s ease forwards ${index * 0.05}s`;
        row.style.opacity = '0';
    });
}

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

// ==================== CHARTS ====================

let barChart, donutChart;

function initCharts() {
    // Bar Chart
    const barCtx = document.getElementById('barChart');
    if (barCtx) {
        barChart = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($semesterDistribution)) ?>,
                datasets: [{
                    label: 'Number of Students',
                    data: <?= json_encode(array_values($semesterDistribution)) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderRadius: 8,
                    barPercentage: 0.6,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#e2e8f0',
                        bodyColor: '#94a3b8',
                        borderColor: '#334155',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { 
                            color: 'rgba(255,255,255,0.05)',
                            drawBorder: false 
                        },
                        ticks: { 
                            color: '#94a3b8',
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { 
                            color: '#94a3b8',
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }

    // Donut Chart
    const donutCtx = document.getElementById('donutChart');
    if (donutCtx) {
        donutChart = new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($courseDistribution)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($courseDistribution)) ?>,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.9)',
                        'rgba(16, 185, 129, 0.9)',
                        'rgba(245, 158, 11, 0.9)',
                        'rgba(139, 92, 246, 0.9)',
                        'rgba(236, 72, 153, 0.9)',
                        'rgba(239, 68, 68, 0.9)'
                    ],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { 
                            color: '#94a3b8',
                            padding: 16,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#e2e8f0',
                        bodyColor: '#94a3b8',
                        borderColor: '#334155',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 2000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }
}

function refreshChart(type) {
    if (type === 'bar' && barChart) {
        barChart.update();
    } else if (type === 'pie' && donutChart) {
        donutChart.update();
    }
}

// ==================== MODAL FUNCTIONS ====================

function showAddStudentModal() {
    document.getElementById('addStudentModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function hideAddStudentModal() {
    document.getElementById('addStudentModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function showViewStudentModal() {
    document.getElementById('viewStudentModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function hideViewStudentModal() {
    document.getElementById('viewStudentModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function showDeleteModal() {
    document.getElementById('deleteConfirmModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function hideDeleteModal() {
    document.getElementById('deleteConfirmModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// ==================== FORM VALIDATION ====================

function validateStudentForm() {
    const name = document.getElementById('addStudentName')?.value.trim();
    const email = document.getElementById('addStudentEmail')?.value.trim();
    const password = document.getElementById('addStudentPassword')?.value;
    const course = document.getElementById('addStudentCourse')?.value;
    const year = document.getElementById('addStudentYear')?.value;
    const session = document.getElementById('addStudentSession')?.value;
    
    if (!name) {
        showNotification('Please enter student name', 'error');
        return false;
    }
    
    if (!email || !isValidEmail(email)) {
        showNotification('Please enter a valid email address', 'error');
        return false;
    }
    
    if (!password || password.length < 6) {
        showNotification('Password must be at least 6 characters long', 'error');
        return false;
    }
    
    if (!course) {
        showNotification('Please select a course', 'error');
        return false;
    }
    
    if (!year) {
        showNotification('Please select a semester', 'error');
        return false;
    }
    
    if (!session) {
        showNotification('Please select a session', 'error');
        return false;
    }
    
    return true;
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification-modern notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// ==================== VIEW STUDENT DETAILS ====================

function viewStudent(id) {
    // Get student data from the row's data attributes
    const row = document.querySelector(`tr[data-student-id="${id}"]`);
    if (!row) return;
    
    const student = {
        id: id,
        name: row.dataset.studentName,
        email: row.dataset.studentEmail,
        course_name: row.dataset.studentCourse,
        year_name: row.dataset.studentYear,
        session_name: row.dataset.studentSession,
        attendance_count: parseInt(row.dataset.attendanceCount),
        status: parseInt(row.dataset.attendanceCount) > 0 ? 'active' : 'inactive'
    };
    
    displayStudentDetails(student);
}

function displayStudentDetails(student) {
    document.getElementById('viewModalTitle').textContent = student.name;
    
    document.getElementById('studentDetailsContent').innerHTML = `
        <div class="profile-header">
            <div class="profile-avatar-large">
                ${student.name.charAt(0).toUpperCase()}
            </div>
            <div class="profile-info">
                <h4>${student.name}</h4>
                <p>${student.email}</p>
            </div>
        </div>
        
        <div class="profile-stats-grid">
            <div class="profile-stat-card">
                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1);">
                    <i class="fas fa-book" style="color: #3b82f6;"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Course</span>
                    <span class="stat-value">${student.course_name}</span>
                </div>
            </div>
            
            <div class="profile-stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1);">
                    <i class="fas fa-layer-group" style="color: #10b981;"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Semester</span>
                    <span class="stat-value">${student.year_name}</span>
                </div>
            </div>
            
            <div class="profile-stat-card">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1);">
                    <i class="fas fa-calendar-alt" style="color: #f59e0b;"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Session</span>
                    <span class="stat-value">${student.session_name}</span>
                </div>
            </div>
            
            <div class="profile-stat-card">
                <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1);">
                    <i class="fas fa-clock" style="color: #8b5cf6;"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Attendance</span>
                    <span class="stat-value">${student.attendance_count || 0} records</span>
                </div>
            </div>
        </div>
        
        <div class="profile-status">
            <span class="status-label">Status</span>
            <span class="status-badge-large status-${student.status}">
                <span class="status-dot"></span>
                ${student.status}
            </span>
        </div>
    `;
    
    document.getElementById('editFromViewBtn').onclick = function() {
        hideViewStudentModal();
        editStudent(student.id);
    };
    
    showViewStudentModal();
}

// ==================== EDIT STUDENT ====================

function editStudent(id) {
    const urlParams = new URLSearchParams(window.location.search);
    window.location.href = `student.php?edit=${id}&${urlParams.toString()}`;
}

// ==================== DELETE STUDENT ====================

function confirmDelete(id, name) {
    document.getElementById('deleteStudentName').textContent = name;
    document.getElementById('deleteStudentId').value = id;
    showDeleteModal();
}

// ==================== SEARCH FUNCTIONALITY ====================

function filterStudents() {
    const searchTerm = document.getElementById('studentSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#studentTableBody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        if (!row.classList.contains('empty-state-row') && !row.querySelector('.empty-state')) {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        }
    });
    
    document.getElementById('visibleCount').textContent = visibleCount;
    
    // Show/hide clear button
    const clearBtn = document.querySelector('.search-clear');
    if (searchTerm.length > 0) {
        clearBtn.style.display = 'flex';
    } else {
        clearBtn.style.display = 'none';
    }
}

function clearSearch() {
    document.getElementById('studentSearch').value = '';
    filterStudents();
}

// ==================== EXPORT TABLE ====================

function exportTable() {
    const rows = [];
    const headers = ['Name', 'Email', 'Course', 'Semester', 'Session', 'Attendance', 'Status'];
    
    document.querySelectorAll('#studentTableBody tr').forEach(row => {
        if (!row.querySelector('.empty-state') && row.style.display !== 'none') {
            const nameEl = row.querySelector('.student-name');
            const emailEl = row.querySelector('.email-cell');
            const courseEl = row.querySelector('.badge-course');
            const semesterEl = row.querySelector('.text-semester');
            const sessionEl = row.querySelector('.text-session');
            const attendanceEl = row.querySelector('.attendance-count');
            const statusEl = row.querySelector('.status-badge');
            
            if (nameEl && emailEl && courseEl && semesterEl && sessionEl && attendanceEl && statusEl) {
                const rowData = [
                    nameEl.textContent,
                    emailEl.textContent.replace('📧', '').trim(),
                    courseEl.textContent,
                    semesterEl.textContent.replace('📅', '').trim(),
                    sessionEl.textContent.replace('🕐', '').trim(),
                    attendanceEl.textContent,
                    statusEl.textContent.trim()
                ];
                rows.push(rowData);
            }
        }
    });
    
    if (rows.length === 0) {
        showNotification('No data to export', 'error');
        return;
    }
    
    let csv = headers.join(',') + '\n';
    rows.forEach(row => {
        csv += row.map(cell => `"${cell}"`).join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `students_export_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

// ==================== AUTO-HIDE ALERTS ====================

function setupAlerts() {
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-modern');
        alerts.forEach(alert => {
            alert.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
}

// ==================== CLICK OUTSIDE TO CLOSE MODALS ====================

document.addEventListener('click', function(event) {
    const modals = ['addStudentModal', 'viewStudentModal', 'deleteConfirmModal'];
    
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal && modal.style.display === 'flex' && event.target === modal) {
            if (modalId === 'deleteConfirmModal') {
                hideDeleteModal();
            } else if (modalId === 'addStudentModal') {
                hideAddStudentModal();
            } else if (modalId === 'viewStudentModal') {
                hideViewStudentModal();
            }
        }
    });
});

// ==================== ESCAPE KEY TO CLOSE MODALS ====================

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideAddStudentModal();
        hideViewStudentModal();
        hideDeleteModal();
    }
});

// Add keyframe animation for rows
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
`;
document.head.appendChild(style);
</script>

<!-- Enhanced CSS -->
<style>
/* Modern Reset and Base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #e2e8f0;
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
    border-radius: 24px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.header-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
}

.header-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    background: linear-gradient(135deg, #fff, #94a3b8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.header-subtitle {
    color: #94a3b8;
    margin: 0.25rem 0 0 0;
    font-size: 0.95rem;
}

.btn-primary-glow {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border: none;
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
}

.btn-primary-glow:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
}

/* Alert Modern */
.alert-modern {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    animation: slideIn 0.3s ease;
    position: relative;
    overflow: hidden;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.alert-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.alert-success .alert-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.alert-danger .alert-icon {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.alert-content {
    flex: 1;
}

.alert-content h6 {
    font-size: 1rem;
    margin: 0 0 0.25rem 0;
    color: #e2e8f0;
}

.alert-content p {
    font-size: 0.9rem;
    margin: 0;
    color: #94a3b8;
}

.alert-close {
    background: transparent;
    border: none;
    color: #64748b;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.2s;
}

.alert-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

/* KPI Grid */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.kpi-card {
    background: rgba(30, 41, 59, 0.5);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: all 0.3s;
}

.kpi-card:hover {
    transform: translateY(-5px);
    border-color: rgba(59, 130, 246, 0.3);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.kpi-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}

.kpi-content {
    flex: 1;
}

.kpi-label {
    font-size: 0.9rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
    margin-bottom: 0.5rem;
}

.kpi-value-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.kpi-value {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    color: #e2e8f0;
}

.kpi-trend {
    font-size: 0.85rem;
    padding: 4px 8px;
    border-radius: 30px;
}

.kpi-trend.positive {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.kpi-badge {
    font-size: 0.8rem;
    padding: 4px 8px;
    border-radius: 30px;
    background: rgba(255, 255, 255, 0.1);
    color: #94a3b8;
}

.kpi-badge.filter-badge {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.kpi-period {
    font-size: 0.8rem;
    color: #64748b;
}

/* Filters Section */
.filters-section {
    background: rgba(30, 41, 59, 0.5);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.filters-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filters-title i {
    color: #3b82f6;
    font-size: 1.2rem;
}

.filters-title h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.clear-filters {
    color: #ef4444;
    text-decoration: none;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 30px;
    background: rgba(239, 68, 68, 0.1);
    transition: all 0.2s;
}

.clear-filters:hover {
    background: rgba(239, 68, 68, 0.2);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-size: 0.9rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-label i {
    font-size: 0.9rem;
    color: #3b82f6;
}

.filter-select-wrapper {
    position: relative;
}

.filter-select {
    width: 100%;
    padding: 12px 16px;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: #e2e8f0;
    font-size: 0.95rem;
    appearance: none;
    cursor: pointer;
    transition: all 0.2s;
    padding-right: 40px;
}

.filter-select:hover {
    border-color: rgba(59, 130, 246, 0.5);
    background: rgba(15, 23, 42, 0.95);
}

.filter-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.select-arrow {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    pointer-events: none;
    font-size: 0.8rem;
}

/* Analytics Grid */
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.chart-card {
    background: rgba(30, 41, 59, 0.5);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    overflow: hidden;
}

.chart-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chart-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-title i {
    color: #3b82f6;
    font-size: 1.2rem;
}

.chart-title h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #e2e8f0;
}

.chart-actions {
    display: flex;
    gap: 8px;
}

.chart-action {
    width: 32px;
    height: 32px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: #94a3b8;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chart-action:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: #3b82f6;
    color: #3b82f6;
    transform: rotate(180deg);
}

.chart-body {
    padding: 1.5rem;
    height: 300px;
}

/* Table Container */
.table-container {
    background: rgba(30, 41, 59, 0.5);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    overflow: hidden;
}

.table-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.table-header-left {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.table-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-title i {
    color: #3b82f6;
    font-size: 1.2rem;
}

.table-title h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #e2e8f0;
}

.table-stats {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-item {
    font-size: 0.9rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 6px;
}

.stat-item i {
    font-size: 0.8rem;
    color: #3b82f6;
}

.stat-divider {
    width: 1px;
    height: 20px;
    background: rgba(255, 255, 255, 0.1);
}

.table-header-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.search-wrapper {
    position: relative;
    width: 300px;
}

.search-wrapper i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 0.9rem;
}

.search-wrapper input {
    width: 100%;
    padding: 10px 16px 10px 42px;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: #e2e8f0;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.search-wrapper input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.search-wrapper input::placeholder {
    color: #64748b;
}

.search-clear {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #94a3b8;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.search-clear:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.btn-export {
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #94a3b8;
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-export:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
}

/* Modern Table */
.table-wrapper {
    overflow-x: auto;
    max-height: 600px;
    overflow-y: auto;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
}

.modern-table thead {
    position: sticky;
    top: 0;
    z-index: 10;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(10px);
}

.modern-table th {
    padding: 1rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #94a3b8;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    white-space: nowrap;
}

.modern-table td {
    padding: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    color: #e2e8f0;
}

.student-row {
    transition: all 0.3s;
}

.student-row:hover {
    background: rgba(59, 130, 246, 0.1);
}

.student-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.student-avatar {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
    color: white;
    position: relative;
}

.online-indicator {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 12px;
    height: 12px;
    background: #10b981;
    border: 2px solid #1e293b;
    border-radius: 50%;
}

.student-details {
    display: flex;
    flex-direction: column;
}

.student-name {
    font-weight: 600;
    color: #e2e8f0;
}

.student-id {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 2px;
}

.email-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #94a3b8;
    font-size: 0.9rem;
}

.email-cell i {
    color: #3b82f6;
    font-size: 0.85rem;
}

.badge-course {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 500;
    white-space: nowrap;
}

.text-semester, .text-session {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #94a3b8;
    font-size: 0.9rem;
}

.text-semester i, .text-session i {
    color: #f59e0b;
    font-size: 0.85rem;
}

.attendance-stats {
    display: flex;
    align-items: baseline;
    gap: 5px;
}

.attendance-count {
    font-weight: 700;
    color: #3b82f6;
    font-size: 1.1rem;
}

.attendance-label {
    font-size: 0.8rem;
    color: #64748b;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 500;
}

.status-active {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.status-inactive {
    background: rgba(100, 116, 139, 0.2);
    color: #94a3b8;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(15, 23, 42, 0.8);
    color: #94a3b8;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.view-btn:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
    transform: translateY(-2px);
}

.edit-btn:hover {
    border-color: #f59e0b;
    color: #f59e0b;
    background: rgba(245, 158, 11, 0.1);
    transform: translateY(-2px);
}

.delete-btn:hover {
    border-color: #ef4444;
    color: #ef4444;
    background: rgba(239, 68, 68, 0.1);
    transform: translateY(-2px);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem !important;
}

.empty-state-content {
    max-width: 300px;
    margin: 0 auto;
}

.empty-state-content i {
    font-size: 4rem;
    color: #334155;
    margin-bottom: 1rem;
}

.empty-state-content h6 {
    font-size: 1.25rem;
    color: #e2e8f0;
    margin-bottom: 0.5rem;
}

.empty-state-content p {
    color: #64748b;
    margin-bottom: 1.5rem;
}

.btn-primary-sm {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary-sm:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
}

/* Table Footer */
.table-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(15, 23, 42, 0.5);
}

.footer-left {
    color: #64748b;
    font-size: 0.9rem;
}

.footer-right {
    color: #94a3b8;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sort-info i {
    color: #3b82f6;
}

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(8px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

.modal-modern {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    border-radius: 28px;
    width: 90%;
    max-width: 600px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    animation: slideUp 0.4s ease;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header-modern {
    padding: 2rem 2rem 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.modal-header-modern.warning {
    border-bottom-color: rgba(239, 68, 68, 0.2);
}

.modal-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    flex-shrink: 0;
}

.modal-icon.warning-icon {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.modal-title-wrapper {
    flex: 1;
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.25rem 0;
    color: #e2e8f0;
}

.modal-subtitle {
    font-size: 0.9rem;
    color: #64748b;
    margin: 0;
}

.modal-close {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: #94a3b8;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    text-decoration: none;
}

.modal-close:hover {
    background: rgba(239, 68, 68, 0.1);
    border-color: #ef4444;
    color: #ef4444;
    transform: rotate(90deg);
}

.modal-body {
    padding: 1.5rem 2rem;
}

.modal-form {
    padding: 1.5rem 2rem 2rem;
}

/* Form Styles */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group.full-width {
    grid-column: span 2;
}

.form-label-modern {
    font-size: 0.9rem;
    font-weight: 500;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-label-modern i {
    color: #3b82f6;
    font-size: 0.9rem;
}

.input-wrapper {
    position: relative;
}

.form-input-modern {
    width: 100%;
    padding: 12px 16px;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: #e2e8f0;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.form-input-modern:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.form-input-modern::placeholder {
    color: #475569;
}

.input-focus-border {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, #3b82f6, #2563eb);
    transition: width 0.3s;
    border-radius: 2px;
}

.form-input-modern:focus ~ .input-focus-border {
    width: 100%;
}

.input-hint {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 4px;
}

.select-wrapper-modern {
    position: relative;
}

.select-modern {
    width: 100%;
    padding: 12px 16px;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: #e2e8f0;
    font-size: 0.95rem;
    appearance: none;
    cursor: pointer;
    transition: all 0.2s;
}

.select-modern:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.select-arrow-modern {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    pointer-events: none;
    font-size: 0.8rem;
}

.modal-actions {
    padding: 1.5rem 2rem 2rem;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.btn-primary-modern, .btn-secondary-modern, .btn-danger-modern {
    padding: 12px 28px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
}

.btn-primary-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
}

.btn-secondary-modern {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #94a3b8;
}

.btn-secondary-modern:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    color: #e2e8f0;
    transform: translateY(-2px);
}

.btn-danger-modern {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
}

.btn-danger-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5);
}

/* Profile View Styles */
.profile-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.profile-avatar-large {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 600;
    color: white;
}

.profile-info h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1.25rem;
    color: #e2e8f0;
}

.profile-info p {
    margin: 0;
    color: #64748b;
    font-size: 0.95rem;
}

.profile-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.profile-stat-card {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.stat-content {
    flex: 1;
}

.stat-label {
    display: block;
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #e2e8f0;
}

.profile-status {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.status-label {
    color: #64748b;
    font-size: 0.95rem;
}

.status-badge-large {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 30px;
    font-weight: 600;
}

.status-badge-large.status-active {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.status-badge-large.status-inactive {
    background: rgba(100, 116, 139, 0.2);
    color: #94a3b8;
}

/* Delete Confirmation */
.delete-warning-content {
    text-align: center;
    padding: 1rem;
}

.delete-icon {
    font-size: 4rem;
    color: #ef4444;
    opacity: 0.5;
    margin-bottom: 1rem;
}

.delete-name {
    font-size: 1.25rem;
    color: #e2e8f0;
    margin-bottom: 0.5rem;
}

.delete-message {
    color: #64748b;
    font-size: 0.95rem;
    line-height: 1.6;
}

/* Notification */
.notification-modern {
    position: fixed;
    top: 24px;
    right: 24px;
    background: linear-gradient(135deg, #1e293b, #0f172a);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    z-index: 11000;
    transform: translateX(400px);
    transition: transform 0.3s ease;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.notification-modern.show {
    transform: translateX(0);
}

.notification-success {
    border-left: 4px solid #10b981;
}

.notification-error {
    border-left: 4px solid #ef4444;
}

.notification-modern i {
    font-size: 1.25rem;
}

.notification-success i {
    color: #10b981;
}

.notification-error i {
    color: #ef4444;
}

.notification-modern span {
    color: #e2e8f0;
    font-size: 0.95rem;
}

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.5);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* Responsive Design */
@media (max-width: 1024px) {
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .analytics-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .kpi-grid {
        grid-template-columns: 1fr;
    }
    
    .table-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .table-header-left {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .table-header-right {
        flex-direction: column;
    }
    
    .search-wrapper {
        width: 100%;
    }
    
    .btn-export {
        width: 100%;
        justify-content: center;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-group.full-width {
        grid-column: span 1;
    }
    
    .modal-actions {
        flex-direction: column;
    }
    
    .profile-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .header-icon {
        width: 48px;
        height: 48px;
        font-size: 1.5rem;
    }
    
    .header-title {
        font-size: 1.5rem;
    }
    
    .modal-modern {
        width: 95%;
        margin: 1rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
    }
}
</style>

<?php include('includes/footer.php'); ?>