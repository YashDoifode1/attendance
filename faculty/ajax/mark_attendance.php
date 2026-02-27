<?php
// api/mark_attendance.php
require_once '../config/db.php';
require_once '../config/location_config.php';
// require_once '../includes/security_headers.php';

// CORS headers for mobile apps
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Validate required fields
$required = ['session_id', 'token', 'lat', 'lng', 'accuracy'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing field: {$field}"]);
        exit;
    }
}

// Sanitize inputs
$session_id = filter_var($input['session_id'], FILTER_VALIDATE_INT);
$token = substr(preg_replace('/[^a-f0-9]/', '', $input['token']), 0, 64);
$student_lat = filter_var($input['lat'], FILTER_VALIDATE_FLOAT);
$student_lng = filter_var($input['lng'], FILTER_VALIDATE_FLOAT);
$accuracy = filter_var($input['accuracy'], FILTER_VALIDATE_FLOAT);
$device_id = isset($input['device_id']) ? substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $input['device_id']), 0, 100) : null;
$timestamp = isset($input['timestamp']) ? (int)$input['timestamp'] : time();

if (!$session_id || !$token || !$student_lat || !$student_lng || !$accuracy) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input format']);
    exit;
}

// Check if student is logged in
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}
$student_id = $_SESSION['user_id'];

// ============================================
// RATE LIMITING CHECK
// ============================================
function checkRateLimit($pdo, $student_id) {
    // Clean expired entries
    $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE expiry < NOW()");
    $stmt->execute();
    
    // Check attempts in last minute
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM rate_limits 
        WHERE identifier = ? AND action_type = 'MARK_ATTENDANCE' 
        AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $stmt->execute(["student_{$student_id}"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['attempt_count'] >= LocationConfig::RATE_LIMIT_ATTENDANCE) {
        return false;
    }
    
    // Log this attempt
    $stmt = $pdo->prepare("
        INSERT INTO rate_limits (identifier, action_type, expiry) 
        VALUES (?, 'MARK_ATTENDANCE', DATE_ADD(NOW(), INTERVAL 1 MINUTE))
    ");
    $stmt->execute(["student_{$student_id}"]);
    
    return true;
}

if (!checkRateLimit($pdo, $student_id)) {
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please wait a moment.']);
    exit;
}

// ============================================
// FETCH SESSION DETAILS
// ============================================
$stmt = $pdo->prepare("
    SELECT s.*, sch.subject_id, sch.faculty_id, sch.course_id, sch.year_id,
           sub.subject_name
    FROM attendance_sessions s
    JOIN schedule sch ON s.schedule_id = sch.id
    JOIN subjects sub ON sch.subject_id = sub.id
    WHERE s.id = ? AND s.token = ? AND s.expiry_timestamp > NOW()
");
$stmt->execute([$session_id, $token]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired session']);
    exit;
}

// ============================================
// CHECK IF ALREADY MARKED
// ============================================
$stmt = $pdo->prepare("
    SELECT id, status, failure_reason FROM attendance 
    WHERE student_id = ? AND session_id = ?
");
$stmt->execute([$student_id, $session_id]);
$existing = $stmt->fetch();

if ($existing) {
    if ($existing['status'] === 'Present') {
        echo json_encode(['success' => false, 'message' => 'Attendance already marked']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Previously failed: ' . $existing['failure_reason']]);
    }
    exit;
}

// ============================================
// LOCATION VERIFICATION
// ============================================

// Check GPS accuracy
if ($accuracy > LocationConfig::MAX_ALLOWED_GPS_ACCURACY) {
    $failure_reason = "GPS accuracy too low: {$accuracy}m (max: " . LocationConfig::MAX_ALLOWED_GPS_ACCURACY . "m)";
    
    // Log failed attempt
    logLocationVerification($pdo, null, $student_id, $session_id, 
                           $session['faculty_lat'], $session['faculty_lng'],
                           $student_lat, $student_lng, 0, 
                           $session['allowed_radius'], 'FAILED', $failure_reason);
    
    echo json_encode(['success' => false, 'message' => $failure_reason]);
    exit;
}

// Calculate distance using Haversine formula
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = LocationConfig::EARTH_RADIUS;
    
    $latFrom = deg2rad($lat1);
    $lonFrom = deg2rad($lon1);
    $latTo = deg2rad($lat2);
    $lonTo = deg2rad($lon2);
    
    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;
    
    $a = sin($latDelta / 2) * sin($latDelta / 2) +
         cos($latFrom) * cos($latTo) *
         sin($lonDelta / 2) * sin($lonDelta / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return round($earthRadius * $c);
}

$distance = haversineDistance(
    $session['faculty_lat'], $session['faculty_lng'],
    $student_lat, $student_lng
);

// ============================================
// ANTI-SPOOFING CHECKS
// ============================================

// Check if distance is within allowed radius
if ($distance > $session['allowed_radius']) {
    $failure_reason = "Outside classroom: {$distance}m (max: {$session['allowed_radius']}m)";
    
    // Log failed attempt
    logLocationVerification($pdo, null, $student_id, $session_id,
                           $session['faculty_lat'], $session['faculty_lng'],
                           $student_lat, $student_lng, $distance,
                           $session['allowed_radius'], 'FAILED', $failure_reason);
    
    // Save failed attendance
    saveAttendance($pdo, $student_id, $session, 'Absent', $student_lat, $student_lng, $distance, $failure_reason, $device_id);
    
    echo json_encode(['success' => false, 'message' => $failure_reason]);
    exit;
}

// Velocity check (if previous attendance exists)
if (LocationConfig::ENABLE_VELOCITY_CHECK) {
    $stmt = $pdo->prepare("
        SELECT student_lat, student_lng, created_at 
        FROM attendance 
        WHERE student_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $lastAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastAttendance && $lastAttendance['student_lat'] && $lastAttendance['student_lng']) {
        $timeDiff = time() - strtotime($lastAttendance['created_at']);
        if ($timeDiff < 300) { // Within 5 minutes
            $lastDistance = haversineDistance(
                $lastAttendance['student_lat'], $lastAttendance['student_lng'],
                $student_lat, $student_lng
            );
            
            $speed = $lastDistance / $timeDiff; // meters per second
            
            if ($speed > LocationConfig::MAX_POSSIBLE_SPEED) {
                $failure_reason = "Suspicious movement detected (impossible speed)";
                
                logLocationVerification($pdo, null, $student_id, $session_id,
                                       $session['faculty_lat'], $session['faculty_lng'],
                                       $student_lat, $student_lng, $distance,
                                       $session['allowed_radius'], 'FAILED', $failure_reason);
                
                saveAttendance($pdo, $student_id, $session, 'Absent', 
                              $student_lat, $student_lng, $distance, $failure_reason, $device_id);
                
                echo json_encode(['success' => false, 'message' => $failure_reason]);
                exit;
            }
        }
    }
}

// ============================================
// MARK ATTENDANCE - SUCCESS
// ============================================

// Determine status (on time or late)
$current_time = time();
$session_start = strtotime($session['start_time']);
$late_threshold = $session_start + 300; // 5 minutes grace period

$status = ($current_time > $late_threshold) ? 'Late' : 'Present';

// Save attendance
$attendance_id = saveAttendance($pdo, $student_id, $session, $status,
                               $student_lat, $student_lng, $distance, null, $device_id);

// Log successful verification
logLocationVerification($pdo, $attendance_id, $student_id, $session_id,
                       $session['faculty_lat'], $session['faculty_lng'],
                       $student_lat, $student_lng, $distance,
                       $session['allowed_radius'], 'PASSED', null);

// Prepare response
$response = [
    'success' => true,
    'message' => "Attendance marked as {$status} (Distance: {$distance}m)",
    'status' => $status,
    'distance' => $distance,
    'allowed_radius' => $session['allowed_radius']
];

// Add debug info in development mode
if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
    $response['debug'] = [
        'faculty_lat' => $session['faculty_lat'],
        'faculty_lng' => $session['faculty_lng'],
        'student_lat' => $student_lat,
        'student_lng' => $student_lng,
        'distance' => $distance,
        'allowed_radius' => $session['allowed_radius'],
        'accuracy' => $accuracy
    ];
}

echo json_encode($response);

// ============================================
// HELPER FUNCTIONS
// ============================================

function saveAttendance($pdo, $student_id, $session, $status, $lat, $lng, $distance, $failure_reason, $device_id) {
    $stmt = $pdo->prepare("
        INSERT INTO attendance 
        (student_id, schedule_id, date, status, faculty_id, subjects_id, 
         course_id, year_id, session_id, student_lat, student_lng, 
         distance_from_faculty, failure_reason, location_verified_at, 
         created_at, device_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
    ");
    
    $stmt->execute([
        $student_id,
        $session['schedule_id'],
        $session['date'],
        $status,
        $session['faculty_id'],
        $session['subject_id'],
        $session['course_id'],
        $session['year_id'],
        $session['id'],
        $lat,
        $lng,
        $distance,
        $failure_reason,
        $device_id
    ]);
    
    return $pdo->lastInsertId();
}

function logLocationVerification($pdo, $attendance_id, $student_id, $session_id,
                                 $faculty_lat, $faculty_lng,
                                 $student_lat, $student_lng,
                                 $distance, $radius, $result, $reason) {
    
    $stmt = $pdo->prepare("
        INSERT INTO location_verification_log 
        (attendance_id, student_id, session_id, faculty_lat, faculty_lng,
         student_lat, student_lng, calculated_distance, allowed_radius,
         verification_result, failure_reason, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $attendance_id,
        $student_id,
        $session_id,
        $faculty_lat,
        $faculty_lng,
        $student_lat,
        $student_lng,
        $distance,
        $radius,
        $result,
        $reason,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}