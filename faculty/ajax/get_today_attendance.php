<?php
// ajax/get_live_attendance.php - Debug version
session_start();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Buffer to catch any output
ob_start();

try {
    require_once '../../config/db.php';
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not logged in');
    }
    
    if ($_SESSION['role'] !== 'faculty') {
        throw new Exception('Not faculty');
    }
    
    $faculty_id = $_SESSION['user_id'];
    $today = date('Y-m-d');
    
    // Test database connection
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Simple query to test
    $stmt = $pdo->query("SELECT 1");
    if (!$stmt) {
        throw new Exception('Database query failed');
    }
    
    // Get attendance records
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            s.name as student_name,
            sub.subject_name,
            a.status,
            DATE_FORMAT(a.created_at, '%h:%i %p') as marked_time,
            a.distance_from_faculty
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN subjects sub ON a.subjects_id = sub.id
        WHERE a.faculty_id = ? AND a.date = ?
        ORDER BY a.created_at DESC
        LIMIT 20
    ");
    
    $stmt->execute([$faculty_id, $today]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get present count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as present_count 
        FROM attendance 
        WHERE faculty_id = ? AND date = ? AND status = 'Present'
    ");
    $stmt->execute([$faculty_id, $today]);
    $present_count = $stmt->fetch(PDO::FETCH_ASSOC)['present_count'];
    
    // Clear buffer and return JSON
    ob_clean();
    echo json_encode([
        'success' => true,
        'attendance' => $attendance,
        'present_count' => (int)$present_count,
        'debug' => [
            'faculty_id' => $faculty_id,
            'date' => $today,
            'records_found' => count($attendance)
        ]
    ]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'attendance' => [],
        'present_count' => 0,
        'debug' => [
            'file' => __FILE__,
            'line' => $e->getLine()
        ]
    ]);
}
?>