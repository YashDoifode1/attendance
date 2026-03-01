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
        SELECT s.id, s.name, s.email, s.created_at,
               c.course_name, y.year_name, sess.session_name,
               (SELECT COUNT(*) FROM attendance WHERE student_id = s.id) as attendance_count,
               CASE WHEN (SELECT COUNT(*) FROM attendance WHERE student_id = s.id) > 0 THEN 'active' ELSE 'inactive' END as status
        FROM students s
        JOIN courses c ON s.course_id = c.id
        JOIN years y ON s.year_id = y.id
        JOIN sessions sess ON s.session_id = sess.id
        WHERE s.id = ? AND s.role = 'student'
    ");
    $stmt->execute([$id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        echo json_encode(['success' => true, 'student' => $student]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
}
?>