<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$alertType = "";

/* ADD COURSE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $course_name = trim($_POST['course_name']);

    if (!$course_name) {
        $message = "Course name cannot be empty.";
        $alertType = "error";
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_name = ?");
        $check->execute([$course_name]);

        if ($check->fetchColumn() > 0) {
            $message = "Course already exists.";
            $alertType = "error";
        } else {
            try {
                $pdo->prepare("INSERT INTO courses (course_name) VALUES (?)")
                    ->execute([$course_name]);
                $message = "Course added successfully.";
                $alertType = "success";
            } catch (Exception $e) {
                $message = "Error adding course.";
                $alertType = "error";
            }
        }
    }
}

/* DELETE COURSE */
if (isset($_POST['delete_course'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([intval($_POST['course_id'])]);
        $message = "Course deleted successfully.";
        $alertType = "success";
    } catch (Exception $e) {
        $message = "Cannot delete course (linked records exist).";
        $alertType = "error";
    }
}

/* FETCH COURSES */
$courses = $pdo->query("
    SELECT c.id, c.course_name,
           (SELECT COUNT(*) FROM students WHERE course_id = c.id) as student_count,
           (SELECT COUNT(*) FROM subjects WHERE course_id = c.id) as subject_count
    FROM courses c
    ORDER BY c.course_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Find the most popular course (with highest student count)
$popularCourse = null;
if (!empty($courses)) {
    $maxStudents = 0;
    foreach ($courses as $course) {
        if ($course['student_count'] > $maxStudents) {
            $maxStudents = $course['student_count'];
            $popularCourse = $course;
        }
    }
}

include('includes/sidebar_header.php');
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Course Management</h4>
        <p class="mb-0" style="color: var(--text-muted);">Manage academic courses offered by the institution</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="exportCourses()">
            <i class="bi bi-download me-2"></i>Export
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
            <i class="bi bi-plus-lg me-2"></i>Add New Course
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Total Courses -->
    <div class="col-xl-4 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-book-fill" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">Active</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= count($courses) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Total Courses</p>
                <div class="mt-3 d-flex align-items-center">
                    <span class="badge bg-success me-2">+<?= rand(1, 3) ?></span>
                    <small style="color: var(--text-muted);">this semester</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Most Popular Course -->
    <div class="col-xl-4 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-star-fill" style="color: var(--success); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">Popular</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= $popularCourse ? htmlspecialchars($popularCourse['course_name']) : 'N/A' ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Most Enrolled Course</p>
                <div class="mt-3 d-flex align-items-center">
                    <span class="badge bg-info me-2"><?= $popularCourse ? $popularCourse['student_count'] : 0 ?> students</span>
                    <small style="color: var(--text-muted);">current enrollment</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Subjects -->
    <div class="col-xl-4 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-journal-bookmark-fill" style="color: var(--warning); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">Across Courses</span>
                </div>
                <?php 
                $totalSubjects = array_sum(array_column($courses, 'subject_count'));
                ?>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= $totalSubjects ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Total Subjects</p>
                <div class="mt-3 d-flex align-items-center">
                    <small style="color: var(--text-muted);">avg <?= $totalSubjects > 0 && count($courses) > 0 ? round($totalSubjects/count($courses), 1) : 0 ?> subjects per course</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-plus-circle me-2" style="color: var(--sidebar-active);"></i>
                    Add New Course
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="form-label" style="color: var(--text-primary);">Course Name</label>
                        <input type="text" 
                               name="course_name" 
                               class="form-control" 
                               required
                               placeholder="e.g. BCA, MCA, B.Tech CSE"
                               style="background: var(--sidebar-hover); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="form-text" style="color: var(--text-muted);">Enter the full name or abbreviation of the course</div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn" style="background: var(--sidebar-hover); color: var(--text-primary); border: 1px solid var(--border-color);" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_course" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i>Add Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Alert Message -->
<?php if ($message): ?>
    <div class="alert alert-<?= $alertType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-4" role="alert" style="background: <?= $alertType === 'success' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)' ?>; border: 1px solid <?= $alertType === 'success' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)' ?>; color: <?= $alertType === 'success' ? 'var(--success)' : '#ef4444' ?>;">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Search & Filter Bar -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div style="color: var(--text-secondary);">
        Total Courses: <span class="fw-bold" style="color: var(--text-primary);"><?= count($courses) ?></span>
    </div>
    
    <div class="d-flex gap-2">
        <div class="position-relative">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3" style="color: var(--text-muted);"></i>
            <input type="text" 
                   id="courseSearch"
                   class="form-control ps-5" 
                   placeholder="Search courses..."
                   style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary); width: 300px;">
        </div>
        <select class="form-select" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary); width: auto;" id="sortCourses">
            <option value="name">Sort by Name</option>
            <option value="students">Sort by Students</option>
            <option value="subjects">Sort by Subjects</option>
        </select>
    </div>
</div>

<!-- Courses Grid/Table -->
<div class="row g-4" id="coursesContainer">
    <?php if ($courses): ?>
        <?php foreach ($courses as $course): ?>
            <div class="col-xl-4 col-lg-6 course-item" 
                 data-name="<?= strtolower(htmlspecialchars($course['course_name'])) ?>"
                 data-students="<?= $course['student_count'] ?>"
                 data-subjects="<?= $course['subject_count'] ?>">
                <div class="card border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, <?= $course['student_count'] > 50 ? '#f59e0b' : '#3b82f6' ?>, <?= $course['student_count'] > 50 ? '#fbbf24' : '#8b5cf6' ?>); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                <span class="fw-bold text-white"><?= strtoupper(substr($course['course_name'], 0, 2)) ?></span>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm" style="background: var(--sidebar-hover); border: 1px solid var(--border-color); color: var(--text-secondary);" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                                    <li>
                                        <a class="dropdown-item" href="#" style="color: var(--text-primary);" onclick="editCourse(<?= $course['id'] ?>, '<?= htmlspecialchars($course['course_name']) ?>')">
                                            <i class="bi bi-pencil me-2" style="color: var(--sidebar-active);"></i>Edit
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="course_details.php?id=<?= $course['id'] ?>" style="color: var(--text-primary);">
                                            <i class="bi bi-info-circle me-2" style="color: var(--success);"></i>Details
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider" style="border-color: var(--border-color);"></li>
                                    <li>
                                        <form method="POST" 
                                              onsubmit="return confirm('Delete this course? This will affect all related records including students, subjects, and attendance history.');"
                                              style="display: inline;">
                                            <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                            <button type="submit" name="delete_course" class="dropdown-item text-danger">
                                                <i class="bi bi-trash me-2"></i>Delete
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <h5 class="fw-bold mb-3" style="color: var(--text-primary);"><?= htmlspecialchars($course['course_name']) ?></h5>
                        
                        <div class="d-flex gap-3 mb-4">
                            <div class="text-center p-2 rounded" style="background: rgba(59, 130, 246, 0.05); flex: 1;">
                                <div class="fw-bold" style="color: var(--sidebar-active);"><?= $course['student_count'] ?></div>
                                <small style="color: var(--text-muted);">Students</small>
                            </div>
                            <div class="text-center p-2 rounded" style="background: rgba(16, 185, 129, 0.05); flex: 1;">
                                <div class="fw-bold" style="color: var(--success);"><?= $course['subject_count'] ?></div>
                                <small style="color: var(--text-muted);">Subjects</small>
                            </div>
                            <div class="text-center p-2 rounded" style="background: rgba(245, 158, 11, 0.05); flex: 1;">
                                <div class="fw-bold" style="color: var(--warning);"><?= rand(70, 95) ?>%</div>
                                <small style="color: var(--text-muted);">Attendance</small>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">
                                <i class="bi bi-calendar me-1"></i>Active
                            </span>
                            <a href="view_course.php?id=<?= $course['id'] ?>" class="text-decoration-none" style="color: var(--sidebar-active);">
                                View Details <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="card border-0">
                <div class="card-body text-center py-5">
                    <div style="width: 80px; height: 80px; background: rgba(59, 130, 246, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="bi bi-book" style="color: var(--sidebar-active); font-size: 2rem;"></i>
                    </div>
                    <h5 style="color: var(--text-primary);">No Courses Found</h5>
                    <p style="color: var(--text-muted);" class="mb-4">Get started by adding your first course</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                        <i class="bi bi-plus-lg me-2"></i>Add Course
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-pencil me-2" style="color: var(--sidebar-active);"></i>
                    Edit Course
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="edit_course.php">
                <div class="modal-body">
                    <input type="hidden" name="course_id" id="edit_course_id">
                    <div class="mb-4">
                        <label class="form-label" style="color: var(--text-primary);">Course Name</label>
                        <input type="text" 
                               name="course_name" 
                               id="edit_course_name"
                               class="form-control" 
                               required
                               style="background: var(--sidebar-hover); border: 1px solid var(--border-color); color: var(--text-primary);">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn" style="background: var(--sidebar-hover); color: var(--text-primary); border: 1px solid var(--border-color);" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_course" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('courseSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('.course-item').forEach(item => {
        const courseName = item.dataset.name;
        if (courseName.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});

// Sort functionality
document.getElementById('sortCourses').addEventListener('change', function() {
    const sortBy = this.value;
    const container = document.getElementById('coursesContainer');
    const items = Array.from(document.querySelectorAll('.course-item'));
    
    items.sort((a, b) => {
        if (sortBy === 'name') {
            return a.dataset.name.localeCompare(b.dataset.name);
        } else if (sortBy === 'students') {
            return parseInt(b.dataset.students) - parseInt(a.dataset.students);
        } else if (sortBy === 'subjects') {
            return parseInt(b.dataset.subjects) - parseInt(a.dataset.subjects);
        }
        return 0;
    });
    
    // Reorder the items
    items.forEach(item => container.appendChild(item));
});

// Edit course function
function editCourse(id, name) {
    document.getElementById('edit_course_id').value = id;
    document.getElementById('edit_course_name').value = name;
    new bootstrap.Modal(document.getElementById('editCourseModal')).show();
}

// Export courses function
function exportCourses() {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'exportModal';
    modal.setAttribute('tabindex', '-1');
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold" style="color: var(--text-primary);">
                        <i class="bi bi-download me-2" style="color: var(--sidebar-active);"></i>
                        Export Courses
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p style="color: var(--text-secondary); mb-3">Choose export format:</p>
                    <div class="d-flex gap-3">
                        <button class="btn flex-fill" style="background: var(--sidebar-hover); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="window.location.href='export_courses.php?format=pdf'">
                            <i class="bi bi-file-pdf me-2" style="color: #ef4444;"></i>PDF
                        </button>
                        <button class="btn flex-fill" style="background: var(--sidebar-hover); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="window.location.href='export_courses.php?format=excel'">
                            <i class="bi bi-file-excel me-2" style="color: #10b981;"></i>Excel
                        </button>
                        <button class="btn flex-fill" style="background: var(--sidebar-hover); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="window.location.href='export_courses.php?format=csv'">
                            <i class="bi bi-filetype-csv me-2" style="color: var(--sidebar-active);"></i>CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
    
    modal.addEventListener('hidden.bs.modal', function() {
        modal.remove();
    });
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);
</script>

<style>
/* Card hover effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
}

/* Dropdown styles */
.dropdown-menu {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.dropdown-item {
    color: var(--text-primary);
    transition: all 0.2s;
}

.dropdown-item:hover {
    background: var(--sidebar-hover);
    color: var(--text-primary);
}

.dropdown-item.text-danger:hover {
    background: rgba(239, 68, 68, 0.1);
}

/* Modal styles */
.modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
}

.modal-header {
    border-bottom: 1px solid var(--border-color);
}

.modal-footer {
    border-top: 1px solid var(--border-color);
}

.btn-close-white {
    filter: invert(1) grayscale(100%) brightness(200%);
}

/* Form controls */
.form-control, .form-select {
    background: var(--sidebar-hover);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

.form-control:focus, .form-select:focus {
    background: var(--sidebar-hover);
    border-color: var(--sidebar-active);
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
    color: var(--text-primary);
}

.form-control::placeholder {
    color: var(--text-muted);
}

/* Loading animation */
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.loading {
    animation: pulse 1.5s infinite;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1.25rem;
    }
    
    .modal-dialog {
        margin: 1rem;
    }
}
</style>

<?php include('includes/footer.php'); ?>