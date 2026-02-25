<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$alertType = "";

/* FETCH DROPDOWNS */
$courses  = $pdo->query("SELECT * FROM courses ORDER BY course_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$years    = $pdo->query("SELECT * FROM years ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$sessions = $pdo->query("SELECT * FROM sessions ORDER BY session_name ASC")->fetchAll(PDO::FETCH_ASSOC);

/* FILTERS */
$selected_course_id  = $_POST['course_id'] ?? '';
$selected_year_id    = $_POST['year_id'] ?? '';
$selected_session_id = $_POST['session_id'] ?? '';

/* PAGINATION */
$limit = 15;
$page  = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* SUBJECT QUERY */
$query = "
    SELECT s.id, s.subject_name, c.course_name, y.year_name, ses.session_name,
           s.course_id, s.year_id, s.session_id
    FROM subjects s
    JOIN courses c ON s.course_id = c.id
    JOIN years y ON s.year_id = y.id
    JOIN sessions ses ON s.session_id = ses.id
    WHERE 1=1
";
$params = [];

if ($selected_course_id) { 
    $query .= " AND s.course_id = ?"; 
    $params[] = $selected_course_id; 
}
if ($selected_year_id)   { 
    $query .= " AND s.year_id = ?";   
    $params[] = $selected_year_id; 
}
if ($selected_session_id){ 
    $query .= " AND s.session_id = ?";
    $params[] = $selected_session_id; 
}

/* COUNT */
$countQuery = str_replace(
    "s.id, s.subject_name, c.course_name, y.year_name, ses.session_name, s.course_id, s.year_id, s.session_id",
    "COUNT(*)",
    $query
);
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_records / $limit));

$query .= " ORDER BY c.course_name, y.year_name, s.subject_name LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ADD / UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject_name'])) {
    try {
        if (!empty($_POST['subject_id'])) {
            $pdo->prepare("UPDATE subjects SET subject_name=?, course_id=?, year_id=?, session_id=? WHERE id=?")
                ->execute([$_POST['subject_name'], $_POST['course_id'], $_POST['year_id'], $_POST['session_id'], $_POST['subject_id']]);
            $message = "Subject updated successfully.";
            $alertType = "success";
            
            // Refresh the page to show updated data
            echo "<meta http-equiv='refresh' content='1'>";
        } else {
            $pdo->prepare("INSERT INTO subjects (subject_name, course_id, year_id, session_id) VALUES (?,?,?,?)")
                ->execute([$_POST['subject_name'], $_POST['course_id'], $_POST['year_id'], $_POST['session_id']]);
            $message = "Subject added successfully.";
            $alertType = "success";
            
            // Refresh the page to show new data
            echo "<meta http-equiv='refresh' content='1'>";
        }
    } catch (Exception $e) {
        $message = "Error saving subject. Please check if subject already exists.";
        $alertType = "error";
    }
}

include('includes/sidebar_header.php');
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Subject Management</h4>
                <p class="mb-0" style="color: var(--text-muted);">Organize subjects by course, semester, and session</p>
            </div>
            <button class="btn btn-primary" onclick="resetForm()">
                <i class="bi bi-plus-circle me-2"></i>Add New Subject
            </button>
        </div>
    </div>
</div>

<!-- Alert Message -->
<?php if ($message): ?>
    <div class="alert alert-<?= $alertType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-<?= $alertType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" onclick="this.closest('.alert').remove()" style="filter: invert(1);"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-4 col-md-4">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-book-fill" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1" style="color: var(--text-muted);">Total Subjects</h6>
                        <h3 class="mb-0 fw-bold counter" data-target="<?= $total_records ?>"><?= $total_records ?></h3>
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
                            <i class="bi bi-mortarboard-fill" style="color: var(--success); font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1" style="color: var(--text-muted);">Courses</h6>
                        <h3 class="mb-0 fw-bold counter" data-target="<?= count($courses) ?>"><?= count($courses) ?></h3>
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
                            <i class="bi bi-calendar3-fill" style="color: var(--warning); font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1" style="color: var(--text-muted);">Semesters</h6>
                        <h3 class="mb-0 fw-bold counter" data-target="<?= count($years) ?>"><?= count($years) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 mb-4">
    <div class="card-body">
        <form method="POST" id="filterForm">
            <div class="row g-4">
                <div class="col-md-4">
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
                <div class="col-md-4">
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
                <div class="col-md-4">
                    <label class="form-label" style="color: var(--text-secondary);">Session Filter</label>
                    <select name="session_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Sessions</option>
                        <?php foreach ($sessions as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $selected_session_id == $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['session_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if ($selected_course_id || $selected_year_id || $selected_session_id): ?>
                <div class="mt-3">
                    <a href="manage_subjects.php" class="btn btn-sm" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);">
                        <i class="bi bi-x-circle me-1"></i>Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Main Content Grid -->
<div class="row g-4">
    <!-- Add/Edit Form Column -->
    <div class="col-xl-4 col-lg-5">
        <div class="card border-0 sticky-top" style="top: 90px; z-index: 100;">
            <div class="card-header">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);" id="formTitle">
                    <i class="bi bi-plus-circle me-2" style="color: var(--sidebar-active);"></i>Add New Subject
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="subjectForm" onsubmit="return validateSubjectForm()">
                    <input type="hidden" name="subject_id" id="edit_subject_id">
                    
                    <div class="mb-4">
                        <label class="form-label" style="color: var(--text-secondary);">Subject Name</label>
                        <input type="text" name="subject_name" id="edit_subject_name" required
                               class="form-control" placeholder="e.g., Mathematics 101">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" style="color: var(--text-secondary);">Course</label>
                        <select name="course_id" id="edit_course_id" required class="form-select">
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" style="color: var(--text-secondary);">Semester</label>
                        <select name="year_id" id="edit_year_id" required class="form-select">
                            <option value="">Select Semester</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y['id'] ?>"><?= htmlspecialchars($y['year_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" style="color: var(--text-secondary);">Session</label>
                        <select name="session_id" id="edit_session_id" required class="form-select">
                            <option value="">Select Session</option>
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['session_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" id="submit_btn" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-save me-2"></i>Save Subject
                        </button>
                        <button type="button" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" 
                                onclick="resetForm()" id="cancelBtn" style="display: none;">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Subjects Table Column -->
    <div class="col-xl-8 col-lg-7">
        <div class="card border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-table me-2" style="color: var(--sidebar-active);"></i>Subjects List
                </h5>
                <div class="d-flex gap-3">
                    <div class="search-box" style="width: 200px;">
                        <i class="bi bi-search"></i>
                        <input type="text" id="subjectSearch" placeholder="Search subjects..." onkeyup="filterSubjects()">
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-hover mb-0" style="color: var(--text-primary);" id="subjectsTable">
                        <thead style="background: var(--card-bg); border-bottom: 2px solid var(--border-color); position: sticky; top: 0; z-index: 10;">
                            <tr>
                                <th class="ps-4 py-3">Subject</th>
                                <th class="py-3">Course</th>
                                <th class="py-3">Semester</th>
                                <th class="py-3">Session</th>
                                <th class="pe-4 py-3 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subjectsTableBody">
                            <?php if ($subjects): ?>
                                <?php foreach ($subjects as $sub): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);" data-subject-id="<?= $sub['id'] ?>">
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 32px; height: 32px; background: rgba(59, 130, 246, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="bi bi-journal-text" style="color: var(--sidebar-active);"></i>
                                                </div>
                                                <span class="fw-medium"><?= htmlspecialchars($sub['subject_name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">
                                                <?= htmlspecialchars($sub['course_name']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3" style="color: var(--text-secondary);">
                                            <i class="bi bi-calendar3 me-1" style="color: var(--text-muted);"></i>
                                            <?= htmlspecialchars($sub['year_name']) ?>
                                        </td>
                                        <td class="py-3" style="color: var(--text-secondary);">
                                            <i class="bi bi-clock me-1" style="color: var(--text-muted);"></i>
                                            <?= htmlspecialchars($sub['session_name']) ?>
                                        </td>
                                        <td class="pe-4 py-3 text-end">
                                            <button class="btn btn-sm edit-btn" style="background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); margin-right: 5px;" 
                                                    data-id="<?= $sub['id'] ?>"
                                                    data-name="<?= htmlspecialchars($sub['subject_name']) ?>"
                                                    data-course="<?= $sub['course_id'] ?>"
                                                    data-year="<?= $sub['year_id'] ?>"
                                                    data-session="<?= $sub['session_id'] ?>"
                                                    title="Edit Subject">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm" style="background: transparent; color: #ef4444; border: 1px solid var(--border-color);" 
                                                    onclick="confirmDelete(<?= $sub['id'] ?>, '<?= htmlspecialchars($sub['subject_name'], ENT_QUOTES) ?>')"
                                                    title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5" style="color: var(--text-muted);">
                                        <i class="bi bi-inbox display-4 d-block mb-3" style="color: var(--border-color);"></i>
                                        <h6 style="color: var(--text-primary);">No subjects found</h6>
                                        <p class="mb-0">Try adjusting your filters or add a new subject.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <small style="color: var(--text-muted);">
                        Showing <?= $offset + 1 ?>-<?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> subjects
                    </small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-muted);">Previous</a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>" style="background: <?= $i == $page ? 'var(--sidebar-active)' : 'var(--card-bg)' ?>; border-color: var(--border-color); color: <?= $i == $page ? 'white' : 'var(--text-primary)' ?>;"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-muted);">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: var(--card-bg); border-radius: 20px; width: 90%; max-width: 400px; border: 1px solid var(--border-color);">
        <div class="p-4 border-bottom" style="border-color: var(--border-color) !important;">
            <h5 class="modal-title fw-bold" style="color: var(--text-primary);">
                <i class="bi bi-exclamation-triangle me-2" style="color: #ef4444;"></i>Confirm Delete
            </h5>
        </div>
        <div class="p-4">
            <p style="color: var(--text-secondary);">Are you sure you want to delete subject: <strong id="deleteSubjectName" style="color: var(--text-primary);"></strong>?</p>
            <p class="small text-muted">This action cannot be undone. This subject may be linked to schedules and attendance records.</p>
        </div>
        <div class="p-4 border-top" style="border-color: var(--border-color) !important; display: flex; gap: 10px; justify-content: flex-end;">
            <button class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 8px 20px;" onclick="hideDeleteModal()">Cancel</button>
            <a href="#" id="confirmDeleteBtn" class="btn" style="background: #ef4444; color: white; padding: 8px 20px; text-decoration: none;">Delete</a>
        </div>
    </div>
</div>

<!-- Self-Contained JavaScript -->
<script>
// ==================== EDIT FUNCTIONALITY ====================

// Add click handlers to all edit buttons
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        const course = this.dataset.course;
        const year = this.dataset.year;
        const session = this.dataset.session;
        
        document.getElementById('edit_subject_id').value = id;
        document.getElementById('edit_subject_name').value = name;
        document.getElementById('edit_course_id').value = course;
        document.getElementById('edit_year_id').value = year;
        document.getElementById('edit_session_id').value = session;
        
        document.getElementById('formTitle').innerHTML = '<i class="bi bi-pencil me-2" style="color: var(--sidebar-active);"></i>Edit Subject';
        document.getElementById('submit_btn').innerHTML = '<i class="bi bi-save me-2"></i>Update Subject';
        document.getElementById('cancelBtn').style.display = 'block';
        
        // Scroll to form
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});

// Reset form to add mode
function resetForm() {
    document.getElementById('edit_subject_id').value = '';
    document.getElementById('edit_subject_name').value = '';
    document.getElementById('edit_course_id').value = '';
    document.getElementById('edit_year_id').value = '';
    document.getElementById('edit_session_id').value = '';
    
    document.getElementById('formTitle').innerHTML = '<i class="bi bi-plus-circle me-2" style="color: var(--sidebar-active);"></i>Add New Subject';
    document.getElementById('submit_btn').innerHTML = '<i class="bi bi-save me-2"></i>Save Subject';
    document.getElementById('cancelBtn').style.display = 'none';
}

// ==================== FORM VALIDATION ====================

function validateSubjectForm() {
    const name = document.getElementById('edit_subject_name').value.trim();
    const course = document.getElementById('edit_course_id').value;
    const year = document.getElementById('edit_year_id').value;
    const session = document.getElementById('edit_session_id').value;
    
    if (!name) {
        alert('Please enter subject name');
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

// ==================== DELETE FUNCTIONALITY ====================

let deleteId = null;

function confirmDelete(id, name) {
    deleteId = id;
    document.getElementById('deleteSubjectName').textContent = name;
    document.getElementById('confirmDeleteBtn').href = 'delete_subject.php?id=' + id;
    document.getElementById('deleteModal').style.display = 'flex';
}

function hideDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// ==================== SEARCH FUNCTIONALITY ====================

function filterSubjects() {
    const searchTerm = document.getElementById('subjectSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#subjectsTableBody tr');
    
    rows.forEach(row => {
        if (row.querySelector('td[colspan]')) return; // Skip empty state row
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
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

// ==================== CLICK OUTSIDE TO CLOSE MODAL ====================

document.addEventListener('click', function(event) {
    const deleteModal = document.getElementById('deleteModal');
    
    if (deleteModal.style.display === 'flex' && event.target === deleteModal) {
        hideDeleteModal();
    }
});

// ==================== ESCAPE KEY TO CLOSE MODAL ====================

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideDeleteModal();
    }
});

// ==================== INITIALIZATION ====================

document.addEventListener('DOMContentLoaded', function() {
    animateCounters();
    setupAlerts();
    
    // Hide cancel button initially if no edit mode
    const cancelBtn = document.getElementById('cancelBtn');
    if (cancelBtn && !document.getElementById('edit_subject_id').value) {
        cancelBtn.style.display = 'none';
    }
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

/* Sticky form on desktop */
@media (min-width: 992px) {
    .sticky-top {
        position: sticky;
        top: 90px;
        z-index: 100;
    }
}

/* Table scrollbar */
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

/* Pagination styling */
.page-link {
    background: var(--card-bg);
    border-color: var(--border-color);
    color: var(--text-secondary);
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
}

.page-link:hover {
    background: var(--sidebar-hover);
    color: var(--text-primary);
    border-color: var(--border-color);
}

.page-item.active .page-link {
    background: var(--sidebar-active);
    border-color: var(--sidebar-active);
}

/* Button close styling */
.btn-close {
    background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
    border: 0;
    border-radius: 4px;
    width: 1em;
    height: 1em;
    cursor: pointer;
    opacity: 0.8;
}

.btn-close:hover {
    opacity: 1;
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
}
</style>

<?php include('includes/footer.php'); ?>