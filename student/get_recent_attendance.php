<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';

header('Content-Type: text/html; charset=utf-8');

// Verify student authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo '<div class="text-center text-danger">Unauthorized access</div>';
    exit();
}

$student_id = $_SESSION['user_id'];
$today = date('Y-m-d');

try {
    // Get today's attendance records with location data
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.status,
            a.created_at,
            a.distance_from_faculty,
            a.student_location_accuracy,
            a.failure_reason,
            sub.subject_name,
            sub.subject_code,
            s.start_time,
            s.end_time,
            s.session_type,
            asess.allowed_radius,
            asess.faculty_lat,
            asess.faculty_lng,
            f.name as faculty_name,
            CASE 
                WHEN a.student_location_accuracy <= ? THEN 'excellent'
                WHEN a.student_location_accuracy <= ? THEN 'good'
                WHEN a.student_location_accuracy <= ? THEN 'fair'
                ELSE 'poor'
            END as gps_quality,
            CASE
                WHEN a.distance_from_faculty <= asess.allowed_radius * 0.5 THEN 'very_close'
                WHEN a.distance_from_faculty <= asess.allowed_radius THEN 'within_range'
                ELSE 'outside_range'
            END as proximity
        FROM attendance a
        JOIN schedule s ON a.schedule_id = s.id
        JOIN subjects sub ON a.subjects_id = sub.id
        JOIN students f ON a.faculty_id = f.id
        LEFT JOIN attendance_sessions asess ON a.session_id = asess.id
        WHERE a.student_id = ? AND a.date = ?
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([
        GPS_ACCURACY_EXCELLENT,
        GPS_ACCURACY_GOOD,
        GPS_ACCURACY_FAIR,
        $student_id, 
        $today
    ]);
    
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_classes,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'Absent' AND failure_reason IS NOT NULL THEN 1 ELSE 0 END) as failed_count,
            AVG(distance_from_faculty) as avg_distance,
            AVG(student_location_accuracy) as avg_accuracy
        FROM attendance a
        JOIN schedule s ON a.schedule_id = s.id
        WHERE a.student_id = ? AND a.date = ?
    ");
    
    $stmt->execute([$student_id, $today]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (count($records) > 0): ?>
        
        <!-- Summary Stats -->
        <div class="row g-2 mb-3">
            <div class="col-3">
                <div class="bg-light rounded-3 p-2 text-center">
                    <small class="text-muted d-block">Total</small>
                    <strong><?= $summary['total_classes'] ?? 0 ?></strong>
                </div>
            </div>
            <div class="col-3">
                <div class="bg-light rounded-3 p-2 text-center">
                    <small class="text-muted d-block">Present</small>
                    <strong class="text-success"><?= $summary['present_count'] ?? 0 ?></strong>
                </div>
            </div>
            <div class="col-3">
                <div class="bg-light rounded-3 p-2 text-center">
                    <small class="text-muted d-block">Failed</small>
                    <strong class="text-danger"><?= $summary['failed_count'] ?? 0 ?></strong>
                </div>
            </div>
            <div class="col-3">
                <div class="bg-light rounded-3 p-2 text-center">
                    <small class="text-muted d-block">Avg Dist</small>
                    <strong><?= $summary['avg_distance'] ? round($summary['avg_distance']) . 'm' : '--' ?></strong>
                </div>
            </div>
        </div>

        <!-- Records List -->
        <div class="list-group">
            <?php foreach ($records as $record): 
                $time = date('h:i A', strtotime($record['created_at']));
                $statusClass = $record['status'] === 'Present' ? 'success' : 'danger';
                $gpsIcon = match($record['gps_quality']) {
                    'excellent' => '🟢',
                    'good' => '🔵',
                    'fair' => '🟡',
                    default => '🔴'
                };
                
                // Distance indicator
                $distanceIcon = match($record['proximity']) {
                    'very_close' => '📍',
                    'within_range' => '📌',
                    default => '⚠️'
                };
            ?>
                <div class="list-group-item list-group-item-action border-0 mb-2 rounded-3">
                    <div class="d-flex w-100 justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-<?= $statusClass ?> bg-opacity-10 text-<?= $statusClass ?> px-2 py-1">
                                    <?= $record['status'] ?>
                                </span>
                                <span class="fw-semibold"><?= htmlspecialchars($record['subject_name']) ?></span>
                                <?php if ($record['subject_code']): ?>
                                    <small class="text-muted">(<?= $record['subject_code'] ?>)</small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex flex-wrap gap-3 small">
                                <span class="text-muted">
                                    <i class="bi bi-clock me-1"></i><?= $time ?>
                                </span>
                                <span class="text-muted">
                                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($record['faculty_name']) ?>
                                </span>
                                <?php if ($record['distance_from_faculty']): ?>
                                    <span class="<?= $record['status'] === 'Present' ? 'text-success' : 'text-danger' ?>">
                                        <?= $distanceIcon ?> <?= $record['distance_from_faculty'] ?>m 
                                        <small class="text-muted">/<?= $record['allowed_radius'] ?>m</small>
                                    </span>
                                <?php endif; ?>
                                <?php if ($record['student_location_accuracy']): ?>
                                    <span class="text-muted">
                                        <?= $gpsIcon ?> <?= $record['student_location_accuracy'] ?>m
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($record['failure_reason']): ?>
                                <div class="small text-danger mt-1">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    <?= htmlspecialchars($record['failure_reason']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Session Type Badge -->
                        <span class="badge bg-info bg-opacity-10 text-info">
                            <?= $record['session_type'] ?? 'Lecture' ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- View All Link -->
        <div class="text-center mt-3">
            <a href="attendance_history.php" class="text-decoration-none small">
                View Full History <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        
    <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-4">
            <div class="mb-3">
                <i class="bi bi-calendar-check text-muted" style="font-size: 3rem;"></i>
            </div>
            <h6 class="text-muted mb-2">No attendance marked today</h6>
            <p class="small text-muted mb-3">
                Scan a QR code to mark your attendance for today's classes
            </p>
            <div class="d-flex justify-content-center gap-2">
                <span class="badge bg-light text-dark p-2">
                    <i class="bi bi-clock me-1"></i> Monitor: <?= date('d M Y') ?>
                </span>
            </div>
        </div>
        
    <?php endif;
    
} catch (PDOException $e) {
    error_log("Recent attendance error: " . $e->getMessage());
    echo '<div class="alert alert-danger">Unable to load recent attendance</div>';
}
?>