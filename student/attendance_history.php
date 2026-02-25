<?php
ob_clean();
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

// Attendance query
$sql = "
    SELECT 
        a.date,
        a.status,
        sub.subject_name,
        f.name AS faculty_name,
        sch.start_time,
        sch.end_time
    FROM attendance a
    JOIN subjects sub ON a.subjects_id = sub.id
    JOIN schedule sch ON a.schedule_id = sch.id
    LEFT JOIN faculty f ON a.faculty_id = f.id
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

<h2 class="fw-bold mb-4">📘 Attendance History</h2>

<!-- Filters -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Date</label>
                <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">Subject</label>
                <select name="subject" class="form-select">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= ($filter_subject == $sub['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sub['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 d-grid">
                <button class="btn btn-primary w-100">🔍 Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Table -->
<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-hover table-bordered align-middle text-center mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Date</th>
                    <th>Subject</th>
                    <th>Faculty</th>
                    <th>Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($attendanceLogs): ?>
                <?php foreach ($attendanceLogs as $row):
                    $statusClass = $row['status'] === 'Present' ? 'bg-success' : 'bg-danger';
                    $dateObj = new DateTime($row['date']);
                    $start_time = date('h:i A', strtotime($row['start_time']));
                    $end_time = date('h:i A', strtotime($row['end_time']));
                ?>
                    <tr>
                        <td><?= $dateObj->format('d M Y') ?></td>
                        <td><?= htmlspecialchars($row['subject_name']) ?></td>
                        <td><?= htmlspecialchars($row['faculty_name'] ?? '—') ?></td>
                        <td><?= $start_time ?> - <?= $end_time ?></td>
                        <td><span class="badge <?= $statusClass ?> px-3 py-2"><?= $row['status'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">No attendance records found</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</main>

<?php include('../includes/footer.php'); ?>
