<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/constants.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$faculty_id = $_SESSION['user_id'];
$today = date('Y-m-d');

try {
    // Get today's attendance with location data
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            s.name as student_name,
            sub.subject_name,
            a.status,
            TIME(a.created_at) as marked_time,
            a.distance_from_faculty,
            a.failure_reason,
            a.student_lat,
            a.student_lng,
            asess.allowed_radius
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN subjects sub ON a.subjects_id = sub.id
        JOIN schedule sch ON a.schedule_id = sch.id
        LEFT JOIN attendance_sessions asess ON a.session_id = asess.id
        WHERE sch.faculty_id = ? AND a.date = ?
        ORDER BY a.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$faculty_id, $today]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get counts
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT s.id) as total_students,
            SUM(CASE WHEN a.status = 'Present' AND a.date = ? THEN 1 ELSE 0 END) as present_count
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
        WHERE s.role = 'student'
    ");
    $stmt->execute([$today, $today]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'attendance' => $attendance,
        'total_students' => $stats['total_students'] ?? 0,
        'present_count' => $stats['present_count'] ?? 0,
        'timestamp' => time()
    ]);
    
} catch (PDOException $e) {
    error_log("Live attendance error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}