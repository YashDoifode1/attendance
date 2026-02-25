<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$alertType = "";

/* ADD FACULTY */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faculty'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $avatar = 'default-avatar.png';

    if (!$name || !$email || !$password) {
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
                "INSERT INTO students (name, email, password, avatar, role, created_at) VALUES (?, ?, ?, ?, 'faculty', NOW())"
            );
            $stmt->execute([$name, $email, $hash, $avatar]);
            $message = "Faculty added successfully.";
            $alertType = "success";
        }
    }
}

/* DELETE FACULTY */
if (isset($_GET['delete'])) {
    try {
        $checkSchedule = $pdo->prepare("SELECT COUNT(*) FROM schedule WHERE faculty_id = ?");
        $checkSchedule->execute([intval($_GET['delete'])]);
        
        if ($checkSchedule->fetchColumn() > 0) {
            $message = "Faculty cannot be deleted as they are assigned to schedule entries.";
            $alertType = "error";
        } else {
            $stmt = $pdo->prepare("DELETE FROM students WHERE id = ? AND role = 'faculty'");
            $stmt->execute([intval($_GET['delete'])]);
            $message = "Faculty deleted successfully.";
            $alertType = "success";
        }
    } catch (Exception $e) {
        $message = "Error deleting faculty.";
        $alertType = "error";
    }
}

/* FETCH FACULTY with their assigned courses */
$stmt = $pdo->query("
    SELECT s.id, s.name, s.email, s.avatar, s.created_at,
           COUNT(DISTINCT sch.course_id) as course_count,
           GROUP_CONCAT(DISTINCT c.course_name SEPARATOR ', ') as courses,
           GROUP_CONCAT(DISTINCT CONCAT(c.course_name, ' (', sch.day, ' ', TIME_FORMAT(sch.start_time, '%H:%i'), '-', TIME_FORMAT(sch.end_time, '%H:%i'), ')') SEPARATOR '||') as detailed_schedule
    FROM students s
    LEFT JOIN schedule sch ON s.id = sch.faculty_id
    LEFT JOIN courses c ON sch.course_id = c.id
    WHERE s.role = 'faculty'
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$totalFaculty = count($faculty);
$stmt = $pdo->query("SELECT COUNT(DISTINCT faculty_id) FROM schedule");
$activeFaculty = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE role = 'faculty' AND MONTH(created_at) = MONTH(CURRENT_DATE())");
$newThisMonth = $stmt->fetchColumn();

include('includes/sidebar_header.php');
?>

<!-- Page Header with Stats -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Faculty Management</h4>
                <p class="mb-0" style="color: var(--text-muted);">Manage faculty accounts and course assignments</p>
            </div>
            <button class="btn btn-primary" onclick="showAddFacultyModal()">
                <i class="bi bi-plus-circle me-2"></i>Add New Faculty
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
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
                        <h6 class="mb-1" style="color: var(--text-muted);">Total Faculty</h6>
                        <h3 class="mb-0 fw-bold counter" data-target="<?= $totalFaculty ?>"><?= $totalFaculty ?></h3>
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
                            <i class="bi bi-book-fill" style="color: var(--success); font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1" style="color: var(--text-muted);">Active Teachers</h6>
                        <h3 class="mb-0 fw-bold counter" data-target="<?= $activeFaculty ?>"><?= $activeFaculty ?></h3>
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
                            <i class="bi bi-calendar-plus-fill" style="color: var(--warning); font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1" style="color: var(--text-muted);">New This Month</h6>
                        <h3 class="mb-0 fw-bold counter" data-target="<?= $newThisMonth ?>"><?= $newThisMonth ?></h3>
                    </div>
                </div>
            </div>
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

<!-- Faculty Table Card -->
<div class="card border-0">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
            <i class="bi bi-person-badge me-2" style="color: var(--sidebar-active);"></i>Faculty Directory
        </h5>
        <div class="d-flex gap-3">
            <div class="search-box" style="width: 250px;">
                <i class="bi bi-search"></i>
                <input type="text" id="facultySearch" placeholder="Search faculty..." onkeyup="filterFaculty()">
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="color: var(--text-primary);" id="facultyTable">
                <thead style="background: var(--card-bg); border-bottom: 2px solid var(--border-color);">
                    <tr>
                        <th class="ps-4 py-3">ID</th>
                        <th class="py-3">Faculty</th>
                        <th class="py-3">Email</th>
                        <th class="py-3">Courses Assigned</th>
                        <th class="py-3">Joined</th>
                        <th class="py-3">Status</th>
                        <th class="pe-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($faculty): ?>
                        <?php foreach ($faculty as $f): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);" data-faculty-id="<?= $f['id'] ?>">
                                <td class="ps-4 py-3">
                                    <span class="badge" style="background: var(--sidebar-bg); color: var(--text-secondary);">#<?= $f['id'] ?></span>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="user-avatar" style="width: 40px; height: 40px; font-size: 0.9rem;">
                                            <?= strtoupper(substr($f['name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold" style="color: var(--text-primary);">
                                                <?= htmlspecialchars($f['name']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3" style="color: var(--text-secondary);">
                                    <i class="bi bi-envelope me-1" style="color: var(--text-muted);"></i>
                                    <?= htmlspecialchars($f['email']) ?>
                                </td>
                                <td class="py-3">
                                    <?php if ($f['course_count'] > 0): ?>
                                        <span class="badge" style="background: var(--sidebar-active); color: white; padding: 6px 12px; cursor: pointer;" 
                                              onclick="showFacultyCourses(<?= $f['id'] ?>, '<?= htmlspecialchars($f['name']) ?>', '<?= htmlspecialchars($f['detailed_schedule']) ?>')">
                                            <?= $f['course_count'] ?> Course(s)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: var(--border-color); color: var(--text-muted);">No Courses</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3" style="color: var(--text-secondary);">
                                    <i class="bi bi-calendar3 me-1" style="color: var(--text-muted);"></i>
                                    <?= date('M d, Y', strtotime($f['created_at'])) ?>
                                </td>
                                <td class="py-3">
                                    <?php
                                    $status = $f['course_count'] > 0 ? 'active' : 'inactive';
                                    ?>
                                    <span class="badge bg-<?= $status === 'active' ? 'success' : 'secondary' ?> bg-opacity-10" 
                                          style="color: <?= $status === 'active' ? 'var(--success)' : 'var(--text-muted)' ?>; padding: 6px 12px;">
                                        <span class="user-status me-1" style="background: <?= $status === 'active' ? 'var(--success)' : 'var(--text-muted)' ?>;"></span>
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td class="pe-4 py-3 text-end">
                                    <button class="btn btn-sm" style="background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); margin-right: 5px;" 
                                            onclick="viewFaculty(<?= $f['id'] ?>)" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm" style="background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); margin-right: 5px;" 
                                            onclick="editFaculty(<?= $f['id'] ?>)" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="?delete=<?= $f['id'] ?>" 
                                       onclick="return confirmDelete(event, this)"
                                       class="btn btn-sm" style="background: transparent; color: #ef4444; border: 1px solid var(--border-color);"
                                       title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5" style="color: var(--text-muted);">
                                <i class="bi bi-inbox display-4 d-block mb-3" style="color: var(--border-color);"></i>
                                <h6 style="color: var(--text-primary);">No faculty members found</h6>
                                <p class="mb-0">Click the "Add New Faculty" button to get started.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer">
        <small style="color: var(--text-muted);">
            Showing <span id="visibleCount"><?= count($faculty) ?></span> of <?= $totalFaculty ?> faculty members • 
            <?= $activeFaculty ?> actively teaching
        </small>
    </div>
</div>

<!-- Add Faculty Modal (Hidden by default) -->
<div id="addFacultyModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--card-bg); border-radius: 20px; width: 90%; max-width: 500px; border: 1px solid var(--border-color);">
        <div class="p-4 border-bottom" style="border-color: var(--border-color) !important;">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-person-plus me-2" style="color: var(--sidebar-active);"></i>Add New Faculty
                </h5>
                <button class="btn-close btn-close-white" onclick="hideAddFacultyModal()"></button>
            </div>
        </div>
        <form method="POST" onsubmit="return validateFacultyForm()">
            <div class="p-4">
                <div class="mb-4">
                    <label class="form-label" style="color: var(--text-secondary);">Full Name</label>
                    <input type="text" name="name" id="facultyName" class="form-control" required placeholder="Enter full name">
                </div>
                <div class="mb-4">
                    <label class="form-label" style="color: var(--text-secondary);">Email Address</label>
                    <input type="email" name="email" id="facultyEmail" class="form-control" required placeholder="Enter email">
                </div>
                <div class="mb-4">
                    <label class="form-label" style="color: var(--text-secondary);">Password</label>
                    <input type="password" name="password" id="facultyPassword" class="form-control" required placeholder="Enter password">
                    <small style="color: var(--text-muted);">Minimum 6 characters</small>
                </div>
            </div>
            <div class="p-4 border-top" style="border-color: var(--border-color) !important; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 10px 24px;" onclick="hideAddFacultyModal()">Cancel</button>
                <button type="submit" name="add_faculty" class="btn btn-primary" style="padding: 10px 24px;">
                    <i class="bi bi-plus-circle me-2"></i>Add Faculty
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Faculty Modal (Hidden by default) -->
<div id="viewFacultyModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--card-bg); border-radius: 20px; width: 90%; max-width: 600px; border: 1px solid var(--border-color);">
        <div class="p-4 border-bottom" style="border-color: var(--border-color) !important;">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold" style="color: var(--text-primary);" id="viewModalTitle">Faculty Details</h5>
                <button class="btn-close btn-close-white" onclick="hideViewFacultyModal()"></button>
            </div>
        </div>
        <div class="p-4" id="facultyDetailsContent">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Courses Modal (Hidden by default) -->
<div id="coursesModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--card-bg); border-radius: 20px; width: 90%; max-width: 700px; border: 1px solid var(--border-color);">
        <div class="p-4 border-bottom" style="border-color: var(--border-color) !important;">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold" style="color: var(--text-primary);" id="coursesModalTitle">Faculty Courses</h5>
                <button class="btn-close btn-close-white" onclick="hideCoursesModal()"></button>
            </div>
        </div>
        <div class="p-4" id="coursesModalContent">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<!-- JavaScript (All self-contained) -->
<script>
// ==================== MODAL FUNCTIONS ====================

function showAddFacultyModal() {
    document.getElementById('addFacultyModal').style.display = 'flex';
}

function hideAddFacultyModal() {
    document.getElementById('addFacultyModal').style.display = 'none';
}

function showViewFacultyModal() {
    document.getElementById('viewFacultyModal').style.display = 'flex';
}

function hideViewFacultyModal() {
    document.getElementById('viewFacultyModal').style.display = 'none';
}

function showCoursesModal() {
    document.getElementById('coursesModal').style.display = 'flex';
}

function hideCoursesModal() {
    document.getElementById('coursesModal').style.display = 'none';
}

// ==================== FORM VALIDATION ====================

function validateFacultyForm() {
    const name = document.getElementById('facultyName').value.trim();
    const email = document.getElementById('facultyEmail').value.trim();
    const password = document.getElementById('facultyPassword').value;
    
    if (name === '') {
        alert('Please enter faculty name');
        return false;
    }
    
    if (email === '') {
        alert('Please enter email address');
        return false;
    }
    
    if (!email.includes('@') || !email.includes('.')) {
        alert('Please enter a valid email address');
        return false;
    }
    
    if (password === '') {
        alert('Please enter a password');
        return false;
    }
    
    if (password.length < 6) {
        alert('Password must be at least 6 characters long');
        return false;
    }
    
    return true;
}

// ==================== VIEW FACULTY DETAILS ====================

function viewFaculty(id) {
    // Get faculty data from the table row
    const row = document.querySelector(`tr[data-faculty-id="${id}"]`);
    if (!row) return;
    
    const name = row.querySelector('.fw-semibold').textContent;
    const email = row.querySelector('td:nth-child(3)').textContent.replace('📧', '').trim();
    const joined = row.querySelector('td:nth-child(5)').textContent.replace('📅', '').trim();
    const status = row.querySelector('td:nth-child(6) .badge').textContent.trim();
    const courseCount = row.querySelector('td:nth-child(4) .badge')?.textContent || '0 Course(s)';
    
    document.getElementById('viewModalTitle').innerHTML = `<i class="bi bi-person-badge me-2" style="color: var(--sidebar-active);"></i>${name}`;
    
    document.getElementById('facultyDetailsContent').innerHTML = `
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
                    <small class="text-muted d-block mb-2">Joined Date</small>
                    <span style="color: var(--text-primary);">${joined}</span>
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
            <div class="col-12">
                <div class="p-3 rounded-3" style="background: var(--sidebar-bg);">
                    <small class="text-muted d-block mb-2">Courses Assigned</small>
                    <span style="color: var(--text-primary);">${courseCount}</span>
                </div>
            </div>
        </div>
        
        <div class="mt-4 d-flex gap-2 justify-content-end">
            <button class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="hideViewFacultyModal()">Close</button>
            <button class="btn btn-primary" onclick="editFaculty(${id})">Edit Faculty</button>
        </div>
    `;
    
    showViewFacultyModal();
}

// ==================== SHOW FACULTY COURSES ====================

function showFacultyCourses(id, name, scheduleData) {
    document.getElementById('coursesModalTitle').innerHTML = `<i class="bi bi-book me-2" style="color: var(--sidebar-active);"></i>${name}'s Courses`;
    
    let coursesHtml = '';
    
    if (scheduleData) {
        const courses = scheduleData.split('||');
        coursesHtml = courses.map(course => {
            if (!course) return '';
            return `
                <tr>
                    <td class="py-2">${course}</td>
                </tr>
            `;
        }).join('');
    }
    
    document.getElementById('coursesModalContent').innerHTML = `
        <?php if ($f['course_count'] > 0): ?>
            <div class="table-responsive">
                <table class="table" style="color: var(--text-primary);">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <th class="ps-0">Course Schedule</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${coursesHtml || '<tr><td class="text-center py-3" style="color: var(--text-muted);">No detailed schedule available</td></tr>'}
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-book" style="color: var(--text-muted); font-size: 3rem;"></i>
                <p class="mt-2" style="color: var(--text-muted);">No courses assigned yet</p>
            </div>
        <?php endif; ?>
        
        <div class="mt-4 text-end">
            <button class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="hideCoursesModal()">Close</button>
        </div>
    `;
    
    showCoursesModal();
}

// ==================== EDIT FACULTY ====================

function editFaculty(id) {
    // Redirect to edit page or show edit form
    window.location.href = `edit_faculty.php?id=${id}`;
}

// ==================== DELETE CONFIRMATION ====================

function confirmDelete(event, element) {
    event.preventDefault();
    if (confirm('Delete this faculty member? This action cannot be undone and may affect schedule assignments.')) {
        window.location.href = element.href;
    }
    return false;
}

// ==================== SEARCH FUNCTIONALITY ====================

function filterFaculty() {
    const searchTerm = document.getElementById('facultySearch').value.toLowerCase();
    const rows = document.querySelectorAll('#facultyTable tbody tr');
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
    const addModal = document.getElementById('addFacultyModal');
    const viewModal = document.getElementById('viewFacultyModal');
    const coursesModal = document.getElementById('coursesModal');
    
    if (addModal.style.display === 'flex' && event.target === addModal) {
        hideAddFacultyModal();
    }
    
    if (viewModal.style.display === 'flex' && event.target === viewModal) {
        hideViewFacultyModal();
    }
    
    if (coursesModal.style.display === 'flex' && event.target === coursesModal) {
        hideCoursesModal();
    }
});

// ==================== ESCAPE KEY TO CLOSE MODALS ====================

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideAddFacultyModal();
        hideViewFacultyModal();
        hideCoursesModal();
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
.form-control {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 0.95rem;
    width: 100%;
}

.form-control:focus {
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

/* Button styles */
.btn {
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
}

.btn-primary {
    background: var(--sidebar-active);
    border: none;
    color: white;
}

.btn-primary:hover {
    background: var(--sidebar-active-light);
    transform: translateY(-2px);
}

/* Card styles */
.card {
    background: var(--card-bg);
    border-radius: 16px;
    overflow: hidden;
}

.card-header {
    background: transparent;
    border-bottom: 1px solid var(--border-color);
    padding: 1.25rem 1.5rem;
}

.card-footer {
    background: transparent;
    border-top: 1px solid var(--border-color);
    padding: 1rem 1.5rem;
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