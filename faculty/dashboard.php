<?php
include('../config/db.php');
include('../includes/faculty_header.php');

// Fetch faculty name from 'students' table where role = 'faculty'
$stmt = $pdo->prepare("SELECT name FROM students WHERE id = ? AND role = 'faculty'");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$faculty) {
    die("Faculty not found.");
}

// Update name in header
echo "<script>document.getElementById('facultyNameDisplay').textContent = '" . htmlspecialchars($faculty['name']) . "';</script>";

// Fetch today's schedule for this faculty
$today = date('l'); // e.g., Monday
$stmt = $pdo->prepare("
    SELECT sub.subject_name AS subject, c.course_name AS course, y.year_name AS year,
           s.start_time, s.end_time, se.session_name AS session
    FROM schedule s
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN courses c ON s.course_id = c.id
    JOIN years y ON s.year_id = y.id
    JOIN sessions se ON s.session_id = se.id
    WHERE s.faculty_id = ? AND s.day = ?
    ORDER BY s.start_time
");
$stmt->execute([$faculty_id, $today]);
$todaySchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch overall stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_classes,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count
    FROM attendance a
    JOIN schedule s ON a.schedule_id = s.id
    WHERE s.faculty_id = ?
");
$stmt->execute([$faculty_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$present = $stats['present_count'] ?? 0;
$absent = $stats['absent_count'] ?? 0;
$late = $stats['late_count'] ?? 0;
$total = $present + $absent + $late;
$presentPercent = $total > 0 ? round(($present / $total) * 100) : 0;
?>

<div class="row g-4">
    <!-- Welcome Card -->
    <div class="col-12">
        <div class="card shadow border-0">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Welcome back, <?= htmlspecialchars($faculty['name']) ?></h5>
            </div>
            <div class="card-body">
                <p class="lead">Today is <strong><?= date('l, F j, Y') ?></strong></p>
                <p>You have <strong><?= count($todaySchedule) ?></strong> class<?= count($todaySchedule) != 1 ? 'es' : '' ?> scheduled today.</p>
            </div>
        </div>
    </div>

    <!-- Attendance Summary Chart -->
    <div class="col-lg-6">
        <div class="card shadow border-0 h-100">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0">Attendance Overview</h6>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="col-lg-6">
        <div class="row g-3">
            <div class="col-6">
                <div class="card text-center shadow border-0 bg-light">
                    <div class="card-body">
                        <h3 class="text-success"><?= $present ?></h3>
                        <p class="mb-0">Present</p>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card text-center shadow border-0 bg-light">
                    <div class="card-body">
                        <h3 class="text-danger"><?= $absent ?></h3>
                        <p class="mb-0">Absent</p>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card text-center shadow border-0 bg-light">
                    <div class="card-body">
                        <h3 class="text-warning"><?= $late ?></h3>
                        <p class="mb-0">Late</p>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card text-center shadow border-0 bg-light">
                    <div class="card-body">
                        <h3 class="text-primary"><?= $presentPercent ?>%</h3>
                        <p class="mb-0">Attendance Rate</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Schedule -->
    <div class="col-12">
        <div class="card shadow border-0">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">Today's Schedule (<?= $today ?>)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($todaySchedule)): ?>
                    <p class="text-muted text-center py-4">No classes scheduled for today.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Course</th>
                                    <th>Year/Sem</th>
                                    <th>Session</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todaySchedule as $class): ?>
                                    <tr>
                                        <td><strong><?= date('h:i A', strtotime($class['start_time'])) ?> - <?= date('h:i A', strtotime($class['end_time'])) ?></strong></td>
                                        <td><?= htmlspecialchars($class['subject']) ?></td>
                                        <td><?= htmlspecialchars($class['course']) ?></td>
                                        <td><?= htmlspecialchars($class['year']) ?></td>
                                        <td><?= htmlspecialchars($class['session']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Late'],
            datasets: [{
                data: [<?= $present ?>, <?= $absent ?>, <?= $late ?>],
                backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                title: {
                    display: true,
                    text: 'Overall Attendance Distribution'
                }
            }
        }
    });
</script>

<?php include('../includes/faculty_footer.php'); ?>