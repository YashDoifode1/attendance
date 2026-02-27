<?php
ob_start();
include('../config/db.php');
include('../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$student_id = $_SESSION['user_id'];

// Filters
$filter_date = $_GET['date'] ?? '';
$filter_subject = $_GET['subject'] ?? '';

// Fetch subjects for filter
$subjectsStmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.subject_name
    FROM attendance a
    JOIN subjects s ON a.subjects_id = s.id
    WHERE a.student_id = ?
    ORDER BY s.subject_name
");
$subjectsStmt->execute([$student_id]);
$subjects = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);

// Attendance query - Fixed by using students table for faculty info
$sql = "
    SELECT 
        a.date,
        a.status,
        a.distance_from_faculty,
        a.student_location_accuracy,
        a.failure_reason,
        sub.subject_name,
        sub.subject_code,
        f.name AS faculty_name,
        sch.start_time,
        sch.end_time,
        sch.session_type,
        s.allowed_radius
    FROM attendance a
    JOIN subjects sub ON a.subjects_id = sub.id
    JOIN schedule sch ON a.schedule_id = sch.id
    LEFT JOIN students f ON a.faculty_id = f.id AND f.role = 'faculty'
    LEFT JOIN attendance_sessions s ON a.session_id = s.id
    WHERE a.student_id = ?
";

$params = [$student_id];

if ($filter_date) {
    $sql .= " AND a.date = ?";
    $params[] = $filter_date;
}

if ($filter_subject) {
    $sql .= " AND sub.id = ?";
    $params[] = $filter_subject;
}

$sql .= " ORDER BY a.date DESC, sch.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendanceLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="main-content">
<div class="container px-3 px-md-4 pt-4 pb-5">

<!-- Page Header with Stats -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-2 mb-sm-0">
        <i class="bi bi-clock-history me-2"></i>Attendance History
    </h2>
    <div class="d-flex gap-2">
        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2">
            <i class="bi bi-check-circle me-2"></i>
            Present: <?= array_count_values(array_column($attendanceLogs, 'status'))['Present'] ?? 0 ?>
        </span>
        <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2">
            <i class="bi bi-x-circle me-2"></i>
            Absent: <?= array_count_values(array_column($attendanceLogs, 'status'))['Absent'] ?? 0 ?>
        </span>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">
                    <i class="bi bi-calendar3 me-2"></i>Date
                </label>
                <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" 
                       class="form-control form-control-lg">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">
                    <i class="bi bi-book me-2"></i>Subject
                </label>
                <select name="subject" class="form-select form-select-lg">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= ($filter_subject == $sub['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sub['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-funnel me-2"></i>Apply Filters
                </button>
                <?php if ($filter_date || $filter_subject): ?>
                    <a href="attendance_history.php" class="btn btn-outline-secondary btn-lg mt-2">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Table Card -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-table me-2"></i>Attendance Records
            </h5>
            <small class="text-muted">
                Total: <?= count($attendanceLogs) ?> records
            </small>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="py-3">Subject</th>
                        <th class="py-3">Faculty</th>
                        <th class="py-3">Time</th>
                        <th class="py-3">Location</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="py-3 text-center">Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($attendanceLogs): ?>
                    <?php foreach ($attendanceLogs as $row):
                        $statusClass = $row['status'] === 'Present' ? 'success' : 'danger';
                        $dateObj = new DateTime($row['date']);
                        $start_time = date('h:i A', strtotime($row['start_time']));
                        $end_time = date('h:i A', strtotime($row['end_time']));
                        
                        // Determine GPS quality emoji
                        $gpsEmoji = '📡';
                        if (isset($row['student_location_accuracy'])) {
                            if ($row['student_location_accuracy'] <= 15) $gpsEmoji = '🟢';
                            elseif ($row['student_location_accuracy'] <= 30) $gpsEmoji = '🔵';
                            elseif ($row['student_location_accuracy'] <= 50) $gpsEmoji = '🟡';
                            else $gpsEmoji = '🔴';
                        }
                    ?>
                        <tr>
                            <td class="px-4">
                                <div class="fw-semibold"><?= $dateObj->format('d M Y') ?></div>
                                <small class="text-muted"><?= $dateObj->format('l') ?></small>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($row['subject_name']) ?></div>
                                <?php if (!empty($row['subject_code'])): ?>
                                    <small class="text-muted"><?= $row['subject_code'] ?></small>
                                <?php endif; ?>
                                <?php if (!empty($row['session_type'])): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info ms-1">
                                        <?= $row['session_type'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($row['faculty_name'] ?? '—') ?></div>
                            </td>
                            <td>
                                <div><?= $start_time ?></div>
                                <small class="text-muted">to <?= $end_time ?></small>
                            </td>
                            <td>
                                <?php if ($row['distance_from_faculty']): ?>
                                    <div>
                                        <span title="Distance from faculty">
                                            📍 <?= round($row['distance_from_faculty']) ?>m
                                        </span>
                                        <?php if ($row['allowed_radius']): ?>
                                            <small class="text-muted">/<?= $row['allowed_radius'] ?>m</small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($row['student_location_accuracy']): ?>
                                        <small class="text-muted" title="GPS Accuracy">
                                            <?= $gpsEmoji ?> <?= round($row['student_location_accuracy']) ?>m
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $statusClass ?> bg-opacity-10 text-<?= $statusClass ?> px-3 py-2">
                                    <i class="bi bi-<?= $row['status'] === 'Present' ? 'check-circle' : 'x-circle' ?> me-1"></i>
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($row['failure_reason']): ?>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="showReason('<?= htmlspecialchars($row['failure_reason']) ?>')"
                                            title="View failure reason">
                                        <i class="bi bi-exclamation-triangle"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-success" disabled>
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="py-4">
                                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                <h6 class="text-muted mt-3">No attendance records found</h6>
                                <?php if ($filter_date || $filter_subject): ?>
                                    <p class="text-muted small">Try clearing your filters</p>
                                    <a href="attendance_history.php" class="btn btn-outline-primary btn-sm">
                                        Clear Filters
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted small">Your attendance history will appear here</p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Table Footer with Summary -->
    <?php if ($attendanceLogs): ?>
    <div class="card-footer bg-white py-3">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 p-2 rounded-circle me-2">
                        <i class="bi bi-check-circle text-success"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Attendance Rate</small>
                        <?php 
                        $present = array_count_values(array_column($attendanceLogs, 'status'))['Present'] ?? 0;
                        $total = count($attendanceLogs);
                        $rate = $total > 0 ? round(($present / $total) * 100) : 0;
                        ?>
                        <strong><?= $rate ?>%</strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center">
                    <div class="bg-info bg-opacity-10 p-2 rounded-circle me-2">
                        <i class="bi bi-geo-alt text-info"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Avg Distance</small>
                        <?php 
                        $distances = array_filter(array_column($attendanceLogs, 'distance_from_faculty'));
                        $avgDist = !empty($distances) ? round(array_sum($distances) / count($distances)) : 0;
                        ?>
                        <strong><?= $avgDist ?>m</strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center">
                    <div class="bg-warning bg-opacity-10 p-2 rounded-circle me-2">
                        <i class="bi bi-satellite text-warning"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">GPS Quality</small>
                        <?php 
                        $accuracies = array_filter(array_column($attendanceLogs, 'student_location_accuracy'));
                        $avgAcc = !empty($accuracies) ? round(array_sum($accuracies) / count($accuracies)) : 0;
                        $quality = $avgAcc <= 15 ? 'Excellent' : ($avgAcc <= 30 ? 'Good' : ($avgAcc <= 50 ? 'Fair' : 'Poor'));
                        ?>
                        <strong><?= $quality ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Export Options -->
<div class="mt-4 text-end">
    <button class="btn btn-outline-secondary btn-sm me-2" onclick="window.print()">
        <i class="bi bi-printer me-2"></i>Print
    </button>
    <button class="btn btn-outline-primary btn-sm" onclick="exportToCSV()">
        <i class="bi bi-download me-2"></i>Export CSV
    </button>
</div>

</div>
</main>

<!-- Failure Reason Modal -->
<div class="modal fade" id="reasonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-2"></i>Attendance Failure Reason
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="failureReasonText" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showReason(reason) {
    document.getElementById('failureReasonText').textContent = reason;
    new bootstrap.Modal(document.getElementById('reasonModal')).show();
}

function exportToCSV() {
    // Get table data
    const rows = document.querySelectorAll('table tbody tr');
    const csv = [];
    
    // Headers
    csv.push('Date,Subject,Faculty,Start Time,End Time,Status,Distance (m),GPS Accuracy (m)');
    
    // Data rows
    rows.forEach(row => {
        if (row.cells.length >= 5) {
            const data = [
                row.cells[0]?.innerText.trim() || '',
                row.cells[1]?.innerText.trim() || '',
                row.cells[2]?.innerText.trim() || '',
                row.cells[3]?.innerText.split('to')[0]?.trim() || '',
                row.cells[3]?.innerText.split('to')[1]?.trim() || '',
                row.cells[4]?.innerText.trim() || '',
                row.cells[5]?.innerText.trim() || ''
            ];
            csv.push(data.join(','));
        }
    });
    
    // Download
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'attendance_history_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
}
</script>

<style>
.table th {
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-weight: 500;
    padding: 0.5rem 1rem;
}

.card {
    border-radius: 15px;
}

.btn-group .btn {
    border-radius: 8px;
}

@media print {
    .btn, .filter-section, .card-header, .card-footer {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    
    th, td {
        border: 1px solid #ddd !important;
        padding: 8px !important;
    }
}
</style>

<?php include('../includes/footer.php'); ?>