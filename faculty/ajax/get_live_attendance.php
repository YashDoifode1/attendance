<?php
// ajax/get_live_attendance.php
session_start();
require_once '../config/db.php';

// Set JSON header first
header('Content-Type: application/json');

// Error handling to catch any PHP errors
try {
    // Check if user is logged in and is faculty
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
        throw new Exception('Unauthorized access');
    }

    $faculty_id = $_SESSION['user_id'];
    
    // Get today's date
    $today = date('Y-m-d');
    
    // First, get total students count for this faculty's courses
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id) as total_students
        FROM students s
        WHERE s.role = 'student'
        AND EXISTS (
            SELECT 1 FROM schedule sch 
            WHERE sch.faculty_id = ? 
            AND sch.course_id = s.course_id 
            AND sch.year_id = s.year_id
        )
    ");
    $stmt->execute([$faculty_id]);
    $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'] ?? 0;
    
    // Fetch live attendance records for today
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            s.name as student_name,
            sub.subject_name,
            a.status,
            DATE_FORMAT(a.created_at, '%h:%i %p') as marked_time,
            a.distance_from_faculty,
            a.failure_reason
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN subjects sub ON a.subjects_id = sub.id
        WHERE a.faculty_id = ? AND a.date = ?
        ORDER BY a.created_at DESC
        LIMIT 50
    ");
    
    $stmt->execute([$faculty_id, $today]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count present for today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as present_count 
        FROM attendance 
        WHERE faculty_id = ? AND date = ? AND status = 'Present'
    ");
    $stmt->execute([$faculty_id, $today]);
    $present_count = $stmt->fetch(PDO::FETCH_ASSOC)['present_count'];
    
    // Calculate attendance rate
    $attendance_rate = $total_students > 0 
        ? round(($present_count / $total_students) * 100, 1) 
        : 0;
    
    // Return success response
    echo json_encode([
        'success' => true,
        'attendance' => $attendance,
        'present_count' => (int)$present_count,
        'total_students' => (int)$total_students,
        'attendance_rate' => $attendance_rate,
        'timestamp' => time(),
        'date' => $today
    ]);
    
} catch (PDOException $e) {
    // Database error
    error_log("Database error in get_live_attendance.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'attendance' => [],
        'present_count' => 0
    ]);
} catch (Exception $e) {
    // General error
    error_log("Error in get_live_attendance.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'attendance' => [],
        'present_count' => 0
    ]);
}
?>