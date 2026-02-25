<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$alertType = "";

// Handle Add Year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_year'])) {
    $year_name = trim($_POST['year_name']);

    if (empty($year_name)) {
        $message = "Year name cannot be empty.";
        $alertType = "error";
    } else {
        // Prevent duplicates
        $check = $pdo->prepare("SELECT COUNT(*) FROM years WHERE year_name = ?");
        $check->execute([$year_name]);
        if ($check->fetchColumn() > 0) {
            $message = "Year '$year_name' already exists.";
            $alertType = "error";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO years (year_name) VALUES (?)");
                $stmt->execute([$year_name]);
                $message = "Year '$year_name' added successfully.";
                $alertType = "success";
            } catch (Exception $e) {
                $message = "Error adding year.";
                $alertType = "error";
            }
        }
    }
}

// Handle Edit Year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_year'])) {
    $year_id   = $_POST['year_id'];
    $year_name = trim($_POST['year_name']);

    if (empty($year_name)) {
        $message = "Year name cannot be empty.";
        $alertType = "error";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE years SET year_name = ? WHERE id = ?");
            $stmt->execute([$year_name, $year_id]);
            $message = "Year updated successfully.";
            $alertType = "success";
        } catch (Exception $e) {
            $message = "Error updating year.";
            $alertType = "error";
        }
    }
}

// Handle Delete Year
if (isset($_GET['delete'])) {
    $year_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM years WHERE id = ?");
        $stmt->execute([$year_id]);
        $message = "Year deleted successfully.";
        $alertType = "success";
    } catch (Exception $e) {
        $message = "Cannot delete year (may have related subjects, students, or schedules).";
        $alertType = "error";
    }
}

// Fetch all years
$years = $pdo->query("SELECT * FROM years ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for the header
$stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE year_id IS NOT NULL");
$studentsWithYear = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM subjects WHERE year_id IS NOT NULL");
$subjectsWithYear = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM schedule WHERE year_id IS NOT NULL");
$schedulesWithYear = $stmt->fetchColumn();

// Include shared layout
include('includes/sidebar_header.php');
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Manage Years / Semesters</h4>
        <p class="mb-0" style="color: var(--text-muted);">Add, edit, and delete academic years or semesters (e.g., FIRST YEAR, 1ST SEMESTER).</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="location.reload()">
            <i class="bi bi-arrow-repeat me-2"></i>Refresh
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Total Years -->
    <div class="col-xl-4 col-md-4">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-calendar-range-fill" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">Total</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= count($years) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Years / Semesters</p>
            </div>
        </div>
    </div>

    <!-- Associated Students -->
    <div class="col-xl-4 col-md-4">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-people-fill" style="color: var(--success); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">Enrolled</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= number_format($studentsWithYear) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Students Assigned</p>
            </div>
        </div>
    </div>

    <!-- Active Courses/Subjects -->
    <div class="col-xl-4 col-md-4">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-book-fill" style="color: var(--warning); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">Active</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= number_format($subjectsWithYear) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Subjects Using Years</p>
                <div class="mt-2 small" style="color: var(--text-muted);">
                    <i class="bi bi-clock me-1"></i><?= $schedulesWithYear ?> scheduled classes
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Row -->
<div class="row g-4">
    <!-- Add Year Card -->
    <div class="col-xl-4">
        <div class="card border-0">
            <div class="card-header">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-plus-circle me-2" style="color: var(--sidebar-active);"></i>Add New Year / Semester
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $alertType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4">
                        <label for="year_name" class="form-label fw-medium" style="color: var(--text-secondary);">Year Name</label>
                        <input type="text" 
                               class="form-control" 
                               style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" 
                               id="year_name" 
                               name="year_name" 
                               required 
                               placeholder="e.g., FIRST YEAR, 1ST SEMESTER">
                        <div class="form-text" style="color: var(--text-muted);">Enter a unique name for the academic year or semester</div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="add_year" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2"></i>Add Year
                        </button>
                    </div>
                </form>

                <!-- Quick Tips -->
                <div class="mt-4 p-3 rounded-3" style="background: rgba(59, 130, 246, 0.05); border: 1px solid var(--border-color);">
                    <h6 class="fw-bold mb-2" style="color: var(--text-primary);">
                        <i class="bi bi-lightbulb me-2" style="color: var(--warning);"></i>Naming Tips
                    </h6>
                    <ul class="small mb-0" style="color: var(--text-muted); padding-left: 1.2rem;">
                        <li>Use consistent format like "FIRST YEAR - 1ST SEM"</li>
                        <li>Include year and semester for clarity</li>
                        <li>Avoid special characters</li>
                        <li>Keep names short but descriptive</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Years List -->
    <div class="col-xl-8">
        <div class="card border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-list-ul me-2" style="color: var(--sidebar-active);"></i>Existing Years / Semesters
                </h5>
                <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">
                    <?= count($years) ?> Total
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($years)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="color: var(--text-primary);">
                            <thead style="background: var(--card-bg); border-bottom: 1px solid var(--border-color);">
                                <tr>
                                    <th class="ps-4 py-3" style="width: 80px;">ID</th>
                                    <th class="py-3">Year / Semester Name</th>
                                    <th class="py-3" style="width: 120px;">Usage</th>
                                    <th class="pe-4 py-3 text-end" style="width: 200px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($years as $index => $year): 
                                    // Get usage count for this year
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE year_id = ?");
                                    $stmt->execute([$year['id']]);
                                    $studentCount = $stmt->fetchColumn();
                                    
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE year_id = ?");
                                    $stmt->execute([$year['id']]);
                                    $subjectCount = $stmt->fetchColumn();
                                ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td class="ps-4 py-3">
                                        <span class="fw-medium" style="color: var(--text-muted);">#<?= $year['id'] ?></span>
                                    </td>
                                    <td class="py-3">
                                        <form method="POST" class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="year_id" value="<?= $year['id'] ?>">
                                            <input type="text" 
                                                   name="year_name" 
                                                   value="<?= htmlspecialchars($year['year_name']) ?>" 
                                                   required
                                                   class="form-control form-control-sm" 
                                                   style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary); max-width: 300px;">
                                            <button type="submit" 
                                                    name="edit_year" 
                                                    class="btn btn-sm" 
                                                    style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active); border: none;"
                                                    data-bs-toggle="tooltip" 
                                                    title="Update year name">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex gap-2">
                                            <?php if ($studentCount > 0): ?>
                                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success);" 
                                                      data-bs-toggle="tooltip" title="<?= $studentCount ?> students">
                                                    <i class="bi bi-people me-1"></i><?= $studentCount ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($subjectCount > 0): ?>
                                                <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);"
                                                      data-bs-toggle="tooltip" title="<?= $subjectCount ?> subjects">
                                                    <i class="bi bi-book me-1"></i><?= $subjectCount ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($studentCount == 0 && $subjectCount == 0): ?>
                                                <span class="badge" style="background: rgba(148, 163, 184, 0.1); color: var(--text-muted);">Unused</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="pe-4 py-3 text-end">
                                        <a href="?delete=<?= $year['id'] ?>" 
                                           class="btn btn-sm" 
                                           style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none;"
                                           onclick="return confirm('Are you sure you want to delete this year/semester?\n\nThis will affect:\n- <?= $studentCount ?> student(s)\n- <?= $subjectCount ?> subject(s)\n- Any related schedules\n\nThis action cannot be undone.');"
                                           data-bs-toggle="tooltip" 
                                           title="Delete year">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div style="width: 64px; height: 64px; background: rgba(59, 130, 246, 0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <i class="bi bi-calendar-x" style="color: var(--sidebar-active); font-size: 2rem;"></i>
                        </div>
                        <h5 style="color: var(--text-primary);">No years/semesters yet</h5>
                        <p style="color: var(--text-muted);" class="mb-3">Add your first academic year or semester using the form.</p>
                        <button class="btn btn-primary" onclick="document.getElementById('year_name').focus()">
                            <i class="bi bi-plus-lg me-2"></i>Add Your First Year
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Usage Information Card -->
        <?php if (!empty($years)): ?>
        <div class="card border-0 mt-4">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 40px; height: 40px; background: rgba(59, 130, 246, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-info-circle" style="color: var(--sidebar-active);"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1" style="color: var(--text-primary);">About Year/Semester Management</h6>
                        <p class="small mb-0" style="color: var(--text-muted);">
                            Years/Semesters are used throughout the system for organizing students, subjects, and schedules. 
                            Changes here will affect all related data. Use the usage badges to see where each year is being used.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Custom CSS for additional styling -->
<style>
/* Table row hover effect */
.table-hover tbody tr:hover {
    background: var(--sidebar-hover) !important;
}

/* Form control focus effect */
.form-control:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    border-color: var(--sidebar-active);
}

/* Badge styles */
.badge {
    font-weight: 500;
    padding: 6px 10px;
    font-size: 0.75rem;
}

/* Button hover effects */
.btn {
    transition: all 0.2s ease;
}

.btn:hover {
    transform: translateY(-1px);
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .table {
        font-size: 0.85rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
    }
}

/* Loading animation */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.loading {
    animation: pulse 1.5s ease-in-out infinite;
}
</style>

<!-- Initialize tooltips -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<?php include('includes/footer.php'); ?>