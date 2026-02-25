<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$alertType = "";

// Handle Add Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_session'])) {
    $session_name = trim($_POST['session_name']);

    if (empty($session_name)) {
        $message = "Session name cannot be empty.";
        $alertType = "error";
    } else {
        // Check for duplicate
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE session_name = ?");
        $stmt->execute([$session_name]);
        if ($stmt->fetchColumn() > 0) {
            $message = "Session '$session_name' already exists.";
            $alertType = "error";
        } else {
            try {
                $pdo->prepare("INSERT INTO sessions (session_name) VALUES (?)")->execute([$session_name]);
                $message = "Session '$session_name' added successfully.";
                $alertType = "success";
            } catch (Exception $e) {
                $message = "Error adding session.";
                $alertType = "error";
            }
        }
    }
}

// Handle Delete Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_session'])) {
    $session_id = $_POST['session_id'] ?? 0;
    
    // Check for related records before deletion
    $checkStudents = $pdo->prepare("SELECT COUNT(*) FROM students WHERE session_id = ?");
    $checkStudents->execute([$session_id]);
    $studentCount = $checkStudents->fetchColumn();
    
    $checkSchedules = $pdo->prepare("SELECT COUNT(*) FROM schedule WHERE session_id = ?");
    $checkSchedules->execute([$session_id]);
    $scheduleCount = $checkSchedules->fetchColumn();
    
    $checkSubjects = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE session_id = ?");
    $checkSubjects->execute([$session_id]);
    $subjectCount = $checkSubjects->fetchColumn();
    
    if ($studentCount > 0 || $scheduleCount > 0 || $subjectCount > 0) {
        $message = "Cannot delete: Session is used in $studentCount student(s), $subjectCount subject(s), and $scheduleCount schedule(s).";
        $alertType = "error";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE id = ?");
            if ($stmt->execute([$session_id])) {
                $message = "Session deleted successfully.";
                $alertType = "success";
            }
        } catch (Exception $e) {
            $message = "Error deleting session.";
            $alertType = "error";
        }
    }
}

// Fetch sessions with usage statistics
$sessions = $pdo->query("
    SELECT 
        s.*,
        (SELECT COUNT(*) FROM students WHERE session_id = s.id) as student_count,
        (SELECT COUNT(*) FROM subjects WHERE session_id = s.id) as subject_count,
        (SELECT COUNT(*) FROM schedule WHERE session_id = s.id) as schedule_count
    FROM sessions s 
    ORDER BY s.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get overall statistics
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE session_id IS NOT NULL")->fetchColumn();
$totalSchedules = $pdo->query("SELECT COUNT(*) FROM schedule WHERE session_id IS NOT NULL")->fetchColumn();
$totalSubjects = $pdo->query("SELECT COUNT(*) FROM subjects WHERE session_id IS NOT NULL")->fetchColumn();

// Include shared layout
include('includes/sidebar_header.php');
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Manage Sessions / Batches</h4>
        <p class="mb-0" style="color: var(--text-muted);">Create and manage academic sessions or batches (e.g., BATCH_2022_2025).</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="location.reload()">
            <i class="bi bi-arrow-repeat me-2"></i>Refresh
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Total Sessions -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-calendar-range-fill" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">Total</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= count($sessions) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Active Sessions</p>
            </div>
        </div>
    </div>

    <!-- Enrolled Students -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-people-fill" style="color: var(--success); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">Students</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= number_format($totalStudents) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Enrolled in Sessions</p>
            </div>
        </div>
    </div>

    <!-- Associated Subjects -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-book-fill" style="color: var(--warning); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">Subjects</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= number_format($totalSubjects) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Subjects by Session</p>
            </div>
        </div>
    </div>

    <!-- Scheduled Classes -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(139, 92, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-calendar-check-fill" style="color: #8b5cf6; font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">Schedules</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= number_format($totalSchedules) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Scheduled Classes</p>
            </div>
        </div>
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

<!-- Main Content Row -->
<div class="row g-4">
    <!-- Add Session Card -->
    <div class="col-xl-4">
        <div class="card border-0">
            <div class="card-header">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-plus-circle me-2" style="color: var(--sidebar-active);"></i>Add New Session / Batch
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-4">
                        <label for="session_name" class="form-label fw-medium" style="color: var(--text-secondary);">Session Name</label>
                        <input type="text" 
                               class="form-control" 
                               style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" 
                               id="session_name" 
                               name="session_name" 
                               required 
                               placeholder="e.g., BATCH_2022_2025">
                        <div class="form-text" style="color: var(--text-muted);">Use a unique identifier for the academic session</div>
                    </div>
                    
                    <!-- Quick Format Examples -->
                    <div class="mb-4">
                        <label class="form-label fw-medium" style="color: var(--text-secondary);">Common Formats:</label>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active); cursor: pointer;" onclick="document.getElementById('session_name').value = '2023-2024'">2023-2024</span>
                            <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active); cursor: pointer;" onclick="document.getElementById('session_name').value = 'BATCH_2022_2025'">BATCH_2022_2025</span>
                            <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active); cursor: pointer;" onclick="document.getElementById('session_name').value = 'SEM_1_2024'">SEM_1_2024</span>
                            <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active); cursor: pointer;" onclick="document.getElementById('session_name').value = 'ACAD_2024'">ACAD_2024</span>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="add_session" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2"></i>Add Session
                        </button>
                    </div>
                </form>

                <!-- Quick Tips -->
                <div class="mt-4 p-3 rounded-3" style="background: rgba(59, 130, 246, 0.05); border: 1px solid var(--border-color);">
                    <h6 class="fw-bold mb-2" style="color: var(--text-primary);">
                        <i class="bi bi-lightbulb me-2" style="color: var(--warning);"></i>Naming Conventions
                    </h6>
                    <ul class="small mb-0" style="color: var(--text-muted); padding-left: 1.2rem;">
                        <li><strong>Academic Year:</strong> 2023-2024, 2024-2025</li>
                        <li><strong>Batch:</strong> BATCH_2020_2024, BATCH_2021_2025</li>
                        <li><strong>Semester:</strong> SEM1_2024, FALL_2024</li>
                        <li>Avoid spaces - use underscores or hyphens</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Sessions List -->
    <div class="col-xl-8">
        <div class="card border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-list-ul me-2" style="color: var(--sidebar-active);"></i>Existing Sessions / Batches
                </h5>
                <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">
                    <?= count($sessions) ?> Total
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($sessions)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="color: var(--text-primary);">
                            <thead style="background: var(--card-bg); border-bottom: 1px solid var(--border-color);">
                                <tr>
                                    <th class="ps-4 py-3" style="width: 80px;">ID</th>
                                    <th class="py-3">Session Name</th>
                                    <th class="py-3">Usage Statistics</th>
                                    <th class="py-3">Created</th>
                                    <th class="pe-4 py-3 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td class="ps-4 py-3">
                                        <span class="fw-medium" style="color: var(--text-muted);">#<?= $session['id'] ?></span>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 32px; height: 32px; background: rgba(59, 130, 246, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                <i class="bi bi-calendar3" style="color: var(--sidebar-active); font-size: 0.9rem;"></i>
                                            </div>
                                            <span class="fw-medium" style="color: var(--text-primary);"><?= htmlspecialchars($session['session_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex gap-2 flex-wrap">
                                            <?php if ($session['student_count'] > 0): ?>
                                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success);" 
                                                      data-bs-toggle="tooltip" title="<?= $session['student_count'] ?> students">
                                                    <i class="bi bi-people me-1"></i><?= $session['student_count'] ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($session['subject_count'] > 0): ?>
                                                <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);"
                                                      data-bs-toggle="tooltip" title="<?= $session['subject_count'] ?> subjects">
                                                    <i class="bi bi-book me-1"></i><?= $session['subject_count'] ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($session['schedule_count'] > 0): ?>
                                                <span class="badge" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;"
                                                      data-bs-toggle="tooltip" title="<?= $session['schedule_count'] ?> scheduled classes">
                                                    <i class="bi bi-calendar-check me-1"></i><?= $session['schedule_count'] ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($session['student_count'] == 0 && $session['subject_count'] == 0 && $session['schedule_count'] == 0): ?>
                                                <span class="badge" style="background: rgba(148, 163, 184, 0.1); color: var(--text-muted);">Unused</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3" style="color: var(--text-muted);">
                                        <small><?= date('M d, Y', strtotime($session['created_at'])) ?></small>
                                    </td>
                                    <td class="pe-4 py-3 text-end">
                                        <form method="POST" class="d-inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this session?\n\nThis will affect:\n- <?= $session['student_count'] ?> student(s)\n- <?= $session['subject_count'] ?> subject(s)\n- <?= $session['schedule_count'] ?> schedule(s)\n\nThis action cannot be undone.');">
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <button type="submit" 
                                                    name="delete_session" 
                                                    class="btn btn-sm" 
                                                    style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none;"
                                                    data-bs-toggle="tooltip" 
                                                    title="Delete session"
                                                    <?= ($session['student_count'] > 0 || $session['subject_count'] > 0 || $session['schedule_count'] > 0) ? 'disabled' : '' ?>>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Usage Legend -->
                    <div class="p-3 border-top" style="border-color: var(--border-color) !important;">
                        <div class="d-flex flex-wrap gap-3 justify-content-end">
                            <small style="color: var(--text-muted);">
                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">●</span> Students
                            </small>
                            <small style="color: var(--text-muted);">
                                <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">●</span> Subjects
                            </small>
                            <small style="color: var(--text-muted);">
                                <span class="badge" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">●</span> Schedules
                            </small>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <div style="width: 64px; height: 64px; background: rgba(59, 130, 246, 0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <i class="bi bi-calendar-x" style="color: var(--sidebar-active); font-size: 2rem;"></i>
                        </div>
                        <h5 style="color: var(--text-primary);">No sessions yet</h5>
                        <p style="color: var(--text-muted);" class="mb-3">Add your first academic session or batch using the form.</p>
                        <button class="btn btn-primary" onclick="document.getElementById('session_name').focus()">
                            <i class="bi bi-plus-lg me-2"></i>Add Your First Session
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Important Information Card -->
        <?php if (!empty($sessions)): ?>
        <div class="card border-0 mt-4">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 40px; height: 40px; background: rgba(59, 130, 246, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="bi bi-info-circle" style="color: var(--sidebar-active);"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-2" style="color: var(--text-primary);">About Session Management</h6>
                        <p class="small mb-2" style="color: var(--text-muted);">
                            Sessions/Batches are used throughout the system to group students, subjects, and schedules. 
                            A session cannot be deleted if it has any associated records (students, subjects, or schedules).
                        </p>
                        <div class="d-flex gap-3">
                            <a href="students.php" class="text-decoration-none small" style="color: var(--sidebar-active);">
                                <i class="bi bi-arrow-right-circle me-1"></i>View Students
                            </a>
                            <a href="subjects.php" class="text-decoration-none small" style="color: var(--sidebar-active);">
                                <i class="bi bi-arrow-right-circle me-1"></i>View Subjects
                            </a>
                            <a href="schedule.php" class="text-decoration-none small" style="color: var(--sidebar-active);">
                                <i class="bi bi-arrow-right-circle me-1"></i>View Schedules
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Custom CSS -->
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
    transition: all 0.2s ease;
}

.badge:hover {
    transform: translateY(-1px);
}

/* Button hover effects */
.btn {
    transition: all 0.2s ease;
}

.btn:hover:not(:disabled) {
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

/* Disabled button styling */
.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Format badge clickable */
.badge[onclick] {
    cursor: pointer;
    transition: all 0.2s ease;
}

.badge[onclick]:hover {
    background: rgba(59, 130, 246, 0.2) !important;
    transform: translateY(-1px);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table {
        font-size: 0.85rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
    }
    
    .badge {
        font-size: 0.7rem;
        padding: 4px 8px;
    }
}
</style>

<!-- Initialize tooltips and auto-dismiss alerts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<?php include('includes/footer.php'); ?>