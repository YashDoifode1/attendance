<?php
/**
 * Adaptive Attendance API - Optimized for Low Accuracy
 */

session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../includes/functions/geo_location.php';

header('Content-Type: application/json');

// Get input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['qr_data'], $input['student_lat'], $input['student_lng'], 
                      $input['accuracy'], $input['student_id'], $input['timestamp'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

// Rate limiting
$identifier = $_SERVER['REMOTE_ADDR'] . '_' . $input['student_id'];
$stmt = $pdo->prepare("
    SELECT COUNT(*) as attempts 
    FROM rate_limits 
    WHERE identifier = ? AND action_type = 'ATTEMPT' 
    AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
");
$stmt->execute([$identifier]);
$result = $stmt->fetch();

if ($result['attempts'] >= RATE_LIMIT_ATTENDANCE) {
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait.']);
    exit();
}

// Log attempt
$stmt = $pdo->prepare("
    INSERT INTO rate_limits (identifier, action_type, expiry) 
    VALUES (?, 'ATTEMPT', DATE_ADD(NOW(), INTERVAL 1 MINUTE))
");
$stmt->execute([$identifier]);

try {
    // Decrypt QR data
    $decrypted = openssl_decrypt(
        $input['qr_data'],
        'AES-256-CBC',
        SECRET_KEY,
        0,
        substr(hash('sha256', SECRET_KEY), 0, 16)
    );
    
    if (!$decrypted) {
        echo json_encode(['success' => false, 'message' => 'Invalid QR code']);
        exit();
    }
    
    $qrData = json_decode($decrypted, true);
    
    if (!$qrData || !isset($qrData['sid'], $qrData['tok'], $qrData['exp'])) {
        echo json_encode(['success' => false, 'message' => 'Malformed QR data']);
        exit();
    }
    
    // Check expiry
    if ($qrData['exp'] < time()) {
        echo json_encode(['success' => false, 'message' => 'QR code expired']);
        exit();
    }
    
    // Check timestamp freshness
    if (abs($input['timestamp'] - time()) > ALLOWED_CLOCK_SKEW) {
        echo json_encode(['success' => false, 'message' => 'Request expired']);
        exit();
    }
    
    // Get session
    $stmt = $pdo->prepare("
        SELECT as.*, s.subject_name, s.id as subject_id,
               sch.course_id, sch.year_id, sch.id as schedule_id,
               sch.faculty_id
        FROM attendance_sessions as
        JOIN schedule sch ON as.schedule_id = sch.id
        JOIN subjects s ON sch.subject_id = s.id
        WHERE as.id = ? AND as.token LIKE ? AND as.expiry_timestamp > NOW()
    ");
    $stmt->execute([$qrData['sid'], $qrData['tok'] . '%']);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired session']);
        exit();
    }
    
    if (is_null($session['faculty_lat'])) {
        echo json_encode(['success' => false, 'message' => 'Session location not configured']);
        exit();
    }
    
    // Check for duplicate
    $stmt = $pdo->prepare("SELECT id, status FROM attendance WHERE student_id = ? AND session_id = ?");
    $stmt->execute([$input['student_id'], $session['id']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Attendance already marked']);
        exit();
    }
    
    // Validate location with accuracy compensation
    $validation = validateLocationWithAccuracy(
        $session['faculty_lat'],
        $session['faculty_lng'],
        $input['student_lat'],
        $input['student_lng'],
        $session['allowed_radius'],
        $input['accuracy'],
        $session['location_accuracy'] ?? 10
    );
    
    // Check for anomalies if enabled
    if (ENABLE_SPOOF_DETECTION) {
        $anomalies = detectGPSAnomalies(
            $input['student_lat'],
            $input['student_lng'],
            $input['accuracy']
        );
        
        if ($anomalies['should_block']) {
            $validation['valid'] = false;
            $validation['message'] = 'Suspicious location detected';
        }
    }
    
    // Determine status
    $status = $validation['valid'] ? 'Present' : 'Absent';
    $failure_reason = $validation['valid'] ? null : $validation['message'];
    
    // Insert attendance
    $stmt = $pdo->prepare("
        INSERT INTO attendance 
        (student_id, schedule_id, date, status, faculty_id, subjects_id,
         course_id, year_id, session_id, student_lat, student_lng,
         distance_from_faculty, failure_reason, student_location_accuracy,
         location_verified_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $input['student_id'],
        $session['schedule_id'],
        $session['date'],
        $status,
        $session['faculty_id'],
        $session['subject_id'],
        $session['course_id'],
        $session['year_id'],
        $session['id'],
        $input['student_lat'],
        $input['student_lng'],
        $validation['distance'],
        $failure_reason,
        $input['accuracy']
    ]);
    
    // Log if enabled
    if (LOG_ALL_ATTEMPTS) {
        $stmt = $pdo->prepare("
            INSERT INTO location_verification_log 
            (student_id, session_id, faculty_lat, faculty_lng, student_lat, student_lng,
             calculated_distance, allowed_radius, verification_result, failure_reason,
             ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $input['student_id'],
            $session['id'],
            $session['faculty_lat'],
            $session['faculty_lng'],
            $input['student_lat'],
            $input['student_lng'],
            $validation['distance'],
            $session['allowed_radius'],
            $validation['valid'] ? 'PASSED' : 'FAILED',
            $failure_reason,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    // Return response
    echo json_encode([
        'success' => $validation['valid'],
        'status' => $status,
        'message' => $validation['message'],
        'distance' => $validation['distance'],
        'effective_radius' => $validation['effective_radius'],
        'accuracy_tier' => $validation['accuracy_tier'],
        'compensation_applied' => $validation['compensation_applied'],
        'confidence_score' => $validation['confidence_score'] ?? 100,
        'timestamp' => date('H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Attendance error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}