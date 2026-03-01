<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $stmt = $pdo->prepare("
        SELECT id, name, email, created_at,
               (SELECT COUNT(*) FROM schedule WHERE faculty_id = students.id) as course_count,
               CASE WHEN (SELECT COUNT(*) FROM schedule WHERE faculty_id = students.id) > 0 THEN 'active' ELSE 'inactive' END as status
        FROM students 
        WHERE id = ? AND role = 'faculty'
    ");
    $stmt->execute([$id]);
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($faculty) {
        echo json_encode(['success' => true, 'faculty' => $faculty]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Faculty not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
}
?>