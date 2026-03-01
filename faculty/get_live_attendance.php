<?php
// ajax/get_live_attendance.php
// Disable any output buffering and clean buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Start fresh output buffer
ob_start();

session_start();
require_once '../config/db.php';

// Clear any previous output
ob_clean();

// Set JSON header
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Initialize response array
$response = [
    'success' => false,
    'attendance' => [],
    'present_count' => 0,
    'error' => null,
    'debug' => []
];

try {
    // Verify faculty authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
        $response['error'] = 'Unauthorized access';
        echo json_encode($response);
        exit();
    }

    $faculty_id = $_SESSION['user_id'];
    $today = date('Y-m-d');
    
    $response['debug']['faculty_id'] = $faculty_id;
    $response['debug']['date'] = $today;

    // First, check if we can connect to database
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Simple query to test if we can fetch data
    $testQuery = $pdo->prepare("SELECT 1 as test");
    $testQuery->execute();
    $testResult = $testQuery->fetch(PDO::FETCH_ASSOC);
    $response['debug']['db_test'] = $testResult ? 'success' : 'failed';

    // Get today's attendance records
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.student_id,
            s.name as student_name,
            a.status,
            DATE_FORMAT(a.created_at, '%h:%i %p') as marked_time,
            a.distance_from_faculty,
            sub.subject_name,
            c.course_name,
            y.year_name
        FROM attendance a
        INNER JOIN students s ON a.student_id = s.id
        INNER JOIN schedule sch ON a.schedule_id = sch.id
        LEFT JOIN subjects sub ON sch.subject_id = sub.id
        LEFT JOIN courses c ON sch.course_id = c.id
        LEFT JOIN years y ON sch.year_id = y.id
        WHERE sch.faculty_id = :faculty_id 
        AND DATE(a.created_at) = :today
        ORDER BY a.created_at DESC
        LIMIT 50
    ");
    
    $stmt->execute([
        ':faculty_id' => $faculty_id,
        ':today' => $today
    ]);
    
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['debug']['records_found'] = count($attendance);
    
    // Count present students
    $present_count = 0;
    foreach ($attendance as $record) {
        if ($record['status'] !== 'Absent') {
            $present_count++;
        }
    }
    
    // Format the data
    foreach ($attendance as &$record) {
        // Ensure all fields exist
        $record['student_name'] = $record['student_name'] ?? 'Unknown Student';
        $record['subject_name'] = $record['subject_name'] ?? 'Unknown Subject';
        $record['course_name'] = $record['course_name'] ?? '';
        $record['year_name'] = $record['year_name'] ?? '';
        $record['marked_time'] = $record['marked_time'] ?? 'N/A';
        $record['distance_from_faculty'] = $record['distance_from_faculty'] ? round($record['distance_from_faculty']) . 'm' : 'N/A';
        $record['status'] = $record['status'] ?? 'Absent';
        
        // Add status badge class
        $record['status_class'] = ($record['status'] === 'Present') ? 'success' : 'warning';
    }
    
    $response['success'] = true;
    $response['attendance'] = $attendance;
    $response['present_count'] = $present_count;
    
} catch (PDOException $e) {
    $response['error'] = 'Database error: ' . $e->getMessage();
    $response['debug']['pdo_error'] = $e->getMessage();
} catch (Exception $e) {
    $response['error'] = 'Server error: ' . $e->getMessage();
    $response['debug']['error'] = $e->getMessage();
}

// Clear output buffer again before sending JSON
ob_clean();

// Send JSON response
echo json_encode($response);
exit();
?>