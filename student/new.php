<?php
ob_start();
include('../config/db.php');
include('../includes/header.php');

if (!isset($_SESSION['user_id'])) exit('Unauthorized');
$student_id = $_SESSION['user_id'];

// Handle AJAX request to get QR data info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'get_info') {
            $qr_data = json_decode($_POST['qr_data'], true);
            
            if (!$qr_data || !is_array($qr_data)) {
                throw new Exception('Invalid QR code format');
            }
            
            // Check if this is a location-based QR code
            $is_location_based = isset($qr_data['verification_type']) && $qr_data['verification_type'] === 'location_based';
            
            // Required fields validation based on QR type
            if ($is_location_based) {
                $required = ['session_id', 'schedule_id', 'subject_id', 'subject_name', 'year_id', 'course_id', 'faculty_id', 'faculty_name', 'date', 'session_type', 'token', 'expiry', 'faculty_lat', 'faculty_lng', 'allowed_radius'];
            } else {
                $required = ['subject_id', 'year_id', 'course_id', 'faculty_id', 'date', 'session_type'];
            }
            
            foreach ($required as $f) {
                if (!isset($qr_data[$f]) || empty($qr_data[$f])) {
                    throw new Exception("Invalid QR code: missing $f");
                }
            }
            
            // Check if session has expired (for location-based QR)
            if ($is_location_based && isset($qr_data['expiry'])) {
                $expiry_time = strtotime($qr_data['expiry']);
                if ($expiry_time < time()) {
                    throw new Exception("This QR code has expired. Please request a new one from your faculty.");
                }
            }
            
            // Get student's enrolled course and year
            $stmt = $pdo->prepare("SELECT course_id, year_id, name FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();
            
            if (!$student) {
                throw new Exception("Student record not found");
            }
            
            // Check if student is enrolled in this course/year
            $isEnrolled = ($student['course_id'] == $qr_data['course_id'] && 
                          $student['year_id'] == $qr_data['year_id']);
            
            if (!$isEnrolled) {
                throw new Exception("You are not enrolled in this course/year");
            }
            
            // Get schedule details
            $stmt = $pdo->prepare("
                SELECT s.id, s.subject_id 
                FROM schedule s
                WHERE s.subject_id = ? 
                  AND s.course_id = ? 
                  AND s.year_id = ?
                  AND s.faculty_id = ?
                LIMIT 1
            ");
            $stmt->execute([
                $qr_data['subject_id'],
                $qr_data['course_id'],
                $qr_data['year_id'],
                $qr_data['faculty_id']
            ]);
            $validSchedule = $stmt->fetch();
            
            if (!$validSchedule) {
                throw new Exception("This subject is not part of your curriculum");
            }
            
            // Check if already marked
            $stmt = $pdo->prepare("
                SELECT id FROM attendance 
                WHERE student_id = ? AND subjects_id = ? AND date = ?
            ");
            $stmt->execute([$student_id, $qr_data['subject_id'], $qr_data['date']]);
            $already_marked = $stmt->fetch();
            
            // Get subject and faculty details
            if ($is_location_based) {
                $details = [
                    'subject' => $qr_data['subject_name'],
                    'faculty' => $qr_data['faculty_name'],
                    'course' => 'Course ' . $qr_data['course_id'],
                    'year' => 'Year ' . $qr_data['year_id'],
                    'date' => date('d M Y', strtotime($qr_data['date'])),
                    'session_type' => $qr_data['session_type'],
                    'subject_id' => $qr_data['subject_id'],
                    'faculty_id' => $qr_data['faculty_id'],
                    'course_id' => $qr_data['course_id'],
                    'year_id' => $qr_data['year_id'],
                    'schedule_id' => $qr_data['schedule_id'],
                    'session_id' => $qr_data['session_id'],
                    'faculty_lat' => $qr_data['faculty_lat'],
                    'faculty_lng' => $qr_data['faculty_lng'],
                    'allowed_radius' => $qr_data['allowed_radius'],
                    'verification_type' => 'location_based',
                    'token' => $qr_data['token'],
                    'faculty_accuracy' => $qr_data['faculty_accuracy'] ?? null
                ];
            } else {
                // For backward compatibility with old QR codes
                $stmt = $pdo->prepare("
                    SELECT s.subject_name, f.name as faculty_name,
                           c.course_name, y.year_name
                    FROM subjects s
                    JOIN students f ON f.id = ?
                    JOIN courses c ON c.id = ?
                    JOIN years y ON y.id = ?
                    WHERE s.id = ?
                ");
                $stmt->execute([
                    $qr_data['faculty_id'],
                    $qr_data['course_id'],
                    $qr_data['year_id'],
                    $qr_data['subject_id']
                ]);
                $db_details = $stmt->fetch();
                
                $details = [
                    'subject' => $db_details['subject_name'] ?? 'Unknown Subject',
                    'faculty' => $db_details['faculty_name'] ?? 'Unknown Faculty',
                    'course' => $db_details['course_name'] ?? 'Unknown Course',
                    'year' => $db_details['year_name'] ?? 'Unknown Year',
                    'date' => date('d M Y', strtotime($qr_data['date'])),
                    'session_type' => $qr_data['session_type'],
                    'subject_id' => $qr_data['subject_id'],
                    'faculty_id' => $qr_data['faculty_id'],
                    'course_id' => $qr_data['course_id'],
                    'year_id' => $qr_data['year_id'],
                    'schedule_id' => $validSchedule['id'],
                    'verification_type' => 'standard'
                ];
            }
            
            echo json_encode([
                'success' => true,
                'already_marked' => !empty($already_marked),
                'is_enrolled' => $isEnrolled,
                'requires_location' => $is_location_based,
                'details' => $details
            ]);
            exit;
        }
        
        if ($_POST['action'] === 'verify_location') {
            $qr_data = json_decode($_POST['qr_data'], true);
            $student_lat = $_POST['student_lat'] ?? null;
            $student_lng = $_POST['student_lng'] ?? null;
            $student_accuracy = $_POST['student_accuracy'] ?? null;
            
            if (!$student_lat || !$student_lng) {
                throw new Exception("Unable to get your location. Please enable location services.");
            }
            
            // Check if accuracy meets GPS requirements (≤20m)
            if ($student_accuracy > 20) {
                throw new Exception("Location accuracy too low: ±{$student_accuracy}m. GPS accuracy of ±5-20m required. Please move to an open area and try again.");
            }
            
            // Calculate distance using Haversine formula
            $earth_radius = 6371000; // meters
            
            $lat1 = deg2rad($qr_data['faculty_lat']);
            $lon1 = deg2rad($qr_data['faculty_lng']);
            $lat2 = deg2rad($student_lat);
            $lon2 = deg2rad($student_lng);
            
            $dlat = $lat2 - $lat1;
            $dlon = $lon2 - $lon1;
            
            $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $distance = $earth_radius * $c;
            
            $allowed_radius = $qr_data['allowed_radius'];
            $distance_rounded = round($distance);
            
            // Log the verification attempt
            $stmt = $pdo->prepare("
                INSERT INTO location_verification_log (
                    student_id, session_id, faculty_lat, faculty_lng,
                    student_lat, student_lng, calculated_distance,
                    allowed_radius, verification_result, failure_reason,
                    ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $verification_result = ($distance <= $allowed_radius) ? 'PASSED' : 'FAILED';
            $failure_reason = ($distance <= $allowed_radius) ? null : "Distance {$distance_rounded}m exceeds allowed {$allowed_radius}m";
            
            $stmt->execute([
                $student_id,
                $qr_data['session_id'] ?? null,
                $qr_data['faculty_lat'],
                $qr_data['faculty_lng'],
                $student_lat,
                $student_lng,
                $distance_rounded,
                $allowed_radius,
                $verification_result,
                $failure_reason,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            echo json_encode([
                'success' => true,
                'verified' => ($distance <= $allowed_radius),
                'distance' => $distance_rounded,
                'allowed_radius' => $allowed_radius,
                'accuracy' => round($student_accuracy),
                'message' => ($distance <= $allowed_radius) 
                    ? "Location verified! You are within {$allowed_radius}m of faculty."
                    : "You are {$distance_rounded}m away from faculty (max allowed: {$allowed_radius}m). Please move closer.",
                'accuracy_message' => ($student_accuracy <= 20) 
                    ? "GPS accuracy: ±{$student_accuracy}m ✓" 
                    : "Low accuracy: ±{$student_accuracy}m"
            ]);
            exit;
        }
        
        if ($_POST['action'] === 'mark_attendance') {
            $qr_data = json_decode($_POST['qr_data'], true);
            $student_lat = $_POST['student_lat'] ?? null;
            $student_lng = $_POST['student_lng'] ?? null;
            $student_accuracy = $_POST['student_accuracy'] ?? null;
            
            if (!$qr_data || !is_array($qr_data)) {
                throw new Exception('Invalid QR code format');
            }
            
            // For location-based QR, verify location again with accuracy check
            if (isset($qr_data['verification_type']) && $qr_data['verification_type'] === 'location_based') {
                if (!$student_lat || !$student_lng) {
                    throw new Exception("Location is required for this QR code. Please enable GPS.");
                }
                
                // Check GPS accuracy
                if ($student_accuracy > 20) {
                    throw new Exception("GPS accuracy too low: ±{$student_accuracy}m. Please move to an open area for better signal.");
                }
                
                // Recalculate distance to ensure it wasn't spoofed
                $earth_radius = 6371000;
                $lat1 = deg2rad($qr_data['faculty_lat']);
                $lon1 = deg2rad($qr_data['faculty_lng']);
                $lat2 = deg2rad($student_lat);
                $lon2 = deg2rad($student_lng);
                
                $dlat = $lat2 - $lat1;
                $dlon = $lon2 - $lon1;
                
                $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                $distance = $earth_radius * $c;
                
                if ($distance > $qr_data['allowed_radius']) {
                    throw new Exception("You are too far from faculty. Distance: " . round($distance) . "m, Allowed: " . $qr_data['allowed_radius'] . "m");
                }
                
                // Check if session is still valid
                if (isset($qr_data['expiry'])) {
                    $expiry_time = strtotime($qr_data['expiry']);
                    if ($expiry_time < time()) {
                        throw new Exception("This QR code has expired. Please request a new one.");
                    }
                }
            }
            
            // Get student's enrolled course and year
            $stmt = $pdo->prepare("SELECT course_id, year_id FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();
            
            if (!$student) {
                throw new Exception("Student record not found");
            }
            
            // Verify student belongs to the course/year
            if ($student['course_id'] != $qr_data['course_id'] || $student['year_id'] != $qr_data['year_id']) {
                throw new Exception("You are not enrolled in this course/year");
            }
            
            // Check for duplicate
            $stmt = $pdo->prepare("
                SELECT id FROM attendance 
                WHERE student_id = ? AND subjects_id = ? AND date = ?
            ");
            $stmt->execute([$student_id, $qr_data['subject_id'], $qr_data['date']]);
            
            if ($stmt->fetch()) {
                throw new Exception("Attendance already marked for this subject today");
            }
            
            // Calculate distance if location data is available
            $distance = null;
            if (isset($student_lat) && isset($student_lng) && isset($qr_data['faculty_lat']) && isset($qr_data['faculty_lng'])) {
                $distance = calculateDistance($qr_data['faculty_lat'], $qr_data['faculty_lng'], $student_lat, $student_lng);
            }
            
            // Insert attendance with location data - MATCHING YOUR DB STRUCTURE
            $stmt = $pdo->prepare("
                INSERT INTO attendance (
                    student_id, schedule_id, subjects_id, date, status,
                    faculty_id, course_id, year_id, session_id,
                    student_lat, student_lng, distance_from_faculty,
                    student_location_accuracy, location_verified_at, created_at
                ) VALUES (?, ?, ?, ?, 'Present', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $student_id,
                $qr_data['schedule_id'],
                $qr_data['subject_id'],
                $qr_data['date'],
                $qr_data['faculty_id'],
                $qr_data['course_id'],
                $qr_data['year_id'],
                $qr_data['session_id'] ?? null,
                $student_lat ? floatval($student_lat) : null,
                $student_lng ? floatval($student_lng) : null,
                $distance ? round($distance) : null,
                $student_accuracy ? floatval($student_accuracy) : null
            ]);
            
            $attendance_id = $pdo->lastInsertId();
            
            // Update location verification log with attendance_id if we have one
            if (isset($qr_data['session_id'])) {
                $stmt = $pdo->prepare("
                    UPDATE location_verification_log 
                    SET attendance_id = ? 
                    WHERE student_id = ? AND session_id = ? 
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$attendance_id, $student_id, $qr_data['session_id']]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Attendance marked successfully! ✅',
                'distance' => $distance ? round($distance) . 'm from faculty' : null,
                'accuracy' => $student_accuracy ? "GPS accuracy: ±{$student_accuracy}m" : null
            ]);
            exit;
        }
        
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Helper function to calculate distance
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return null;
    
    $earth_radius = 6371000;
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return round($earth_radius * $c);
}

// Get student info
$stmt = $pdo->prepare("SELECT name, course_id, year_id, course_name, year_name 
                       FROM students s
                       LEFT JOIN courses c ON s.course_id = c.id
                       LEFT JOIN years y ON s.year_id = y.id
                       WHERE s.id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
?>

<main class="main-content">
<div class="px-4 pt-4 pb-5">
    
    <!-- Page Header with Student Info -->
    <div class="bg-primary bg-gradient text-white rounded-3 p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1"><i class="bi bi-qr-code-scan me-2"></i>Scan Attendance QR</h4>
                <p class="mb-0 opacity-75">
                    <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($student['name']) ?>
                    <span class="mx-2">|</span>
                    <i class="bi bi-mortarboard me-2"></i><?= htmlspecialchars($student['course_name'] ?? 'N/A') ?> - <?= htmlspecialchars($student['year_name'] ?? 'N/A') ?>
                </p>
            </div>
            <button class="btn btn-light" onclick="toggleInstructions()">
                <i class="bi bi-question-circle me-2"></i>Help
            </button>
        </div>
    </div>

    <!-- Instructions Card -->
    <div class="card shadow-sm border-0 mb-4 d-none" id="instructionsCard">
        <div class="card-body">
            <div class="d-flex align-items-start">
                <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                    <i class="bi bi-info-circle text-primary fs-4"></i>
                </div>
                <div>
                    <h5 class="mb-2">How to Scan QR Code</h5>
                    <ul class="text-muted mb-0">
                        <li>Hold your phone steady about 15-20 cm from the QR code</li>
                        <li>Ensure good lighting for better scanning</li>
                        <li><strong class="text-primary">Location-based attendance:</strong> You must be within 30 meters of faculty</li>
                        <li><strong class="text-success">GPS Required:</strong> Accuracy must be ±5m – ±20m (IP/WiFi based location will be rejected)</li>
                        <li>Enable high accuracy GPS mode for best results</li>
                        <li>You can only mark attendance once per subject per day</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Location Status Banner -->
    <div class="alert alert-info alert-dismissible fade show mb-4" id="locationBanner" style="display: none;">
        <div class="d-flex align-items-center">
            <div class="spinner-grow spinner-grow-sm text-info me-3" id="locationSpinner"></div>
            <div class="flex-grow-1">
                <strong id="locationStatus">Getting your GPS location...</strong>
                <small class="d-block text-muted" id="locationAccuracy"></small>
            </div>
            <button type="button" class="btn-close" onclick="document.getElementById('locationBanner').style.display='none'"></button>
        </div>
    </div>

    <!-- Main Scanner Card -->
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0 overflow-hidden">
                <!-- Scanner Header -->
                <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                            <i class="bi bi-camera text-primary fs-4"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">QR Code Scanner</h5>
                            <p class="text-muted small mb-0">Scan QR code to view lecture details</p>
                        </div>
                    </div>
                </div>

                <!-- Scanner Body -->
                <div class="card-body p-4">
                    <!-- Camera Selection -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="btn-group" id="cameraToggle">
                            <button class="btn btn-sm btn-outline-primary active" onclick="switchCamera('environment')">
                                <i class="bi bi-phone me-2"></i>Back Camera
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="switchCamera('user')">
                                <i class="bi bi-camera me-2"></i>Front Camera
                            </button>
                        </div>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Your enrolled: <?= htmlspecialchars($student['course_name'] ?? 'N/A') ?>
                        </small>
                    </div>

                    <!-- Scanner Container -->
                    <div class="scanner-container position-relative mb-4">
                        <div id="reader" class="scanner-frame"></div>
                        
                        <!-- Scanning Overlay -->
                        <div class="scanner-overlay">
                            <div class="scanner-line"></div>
                        </div>
                    </div>

                    <!-- Status Area with GPS Indicator -->
                    <div class="status-area p-3 rounded-3 bg-light mb-4">
                        <div class="d-flex align-items-center">
                            <div class="spinner-grow spinner-grow-sm text-primary me-3" id="scanningSpinner"></div>
                            <div class="flex-grow-1">
                                <div id="statusBar" class="fw-medium">Ready to scan</div>
                                <small class="text-muted" id="statusDetail">Position QR code in frame</small>
                            </div>
                        </div>
                        <div id="gpsStatus" class="mt-2 small"></div>
                    </div>

                    <!-- Location Verification Card (shown after scan for location-based QR) -->
                    <div id="locationVerificationCard" class="card border-0 bg-light mb-4 d-none">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="bi bi-geo-alt-fill text-primary me-2"></i>
                                Location Verification Required
                            </h6>
                            
                            <div class="text-center mb-3">
                                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" id="locationVerifySpinner"></div>
                                <div id="locationVerifyStatus">Verifying your location...</div>
                                <div class="mt-2" id="locationVerifyDistance"></div>
                                <div class="mt-2" id="locationVerifyAccuracy"></div>
                            </div>

                            <div class="progress mb-3" style="height: 10px;" id="distanceProgressContainer">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 0%;" id="distanceProgress"></div>
                            </div>

                            <div class="text-center">
                                <button class="btn btn-primary" onclick="retryLocationVerification()" id="retryLocationBtn" style="display: none;">
                                    <i class="bi bi-arrow-repeat me-2"></i>Retry Verification
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Lecture Details Card (shown after scan) -->
                    <div id="lectureDetailsCard" class="card border-0 bg-light mb-4 d-none">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="bi bi-info-circle-fill text-primary me-2"></i>
                                Lecture Details
                            </h6>
                            
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="d-flex">
                                        <i class="bi bi-book text-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Subject</small>
                                            <span class="fw-bold" id="detailSubject">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="d-flex">
                                        <i class="bi bi-person-badge text-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Faculty</small>
                                            <span class="fw-bold" id="detailFaculty">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="d-flex">
                                        <i class="bi bi-mortarboard text-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Course</small>
                                            <span class="fw-bold" id="detailCourse">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="d-flex">
                                        <i class="bi bi-calendar text-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Date</small>
                                            <span class="fw-bold" id="detailDate">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="d-flex">
                                        <i class="bi bi-clock text-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Session Type</small>
                                            <span class="fw-bold" id="detailSession">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="d-flex">
                                        <i class="bi bi-diagram-3 text-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Year</small>
                                            <span class="fw-bold" id="detailYear">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Location Requirement Badge -->
                            <div id="locationRequirementBadge" class="mt-3 p-2 bg-info bg-opacity-10 rounded-3 d-none">
                                <i class="bi bi-geo-alt-fill text-info me-2"></i>
                                <span>This lecture requires location verification (GPS accuracy: ±5m – ±20m required)</span>
                            </div>

                            <!-- Already Marked Message -->
                            <div id="alreadyMarkedMsg" class="alert alert-success mt-3 mb-0 d-none">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                You have already marked attendance for this lecture ✓
                            </div>

                            <!-- Mark Attendance Button -->
                            <div class="text-center mt-4" id="markBtnContainer">
                                <button class="btn btn-success btn-lg px-5" onclick="startAttendanceProcess()" id="markBtn">
                                    <i class="bi bi-check2-circle me-2"></i>
                                    Proceed to Mark Attendance
                                </button>
                                <button class="btn btn-outline-secondary btn-lg px-5 d-none" id="markedBtn" disabled>
                                    <i class="bi bi-check2-circle me-2"></i>
                                    Already Marked ✓
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Not Enrolled Card -->
                    <div id="notEnrolledCard" class="card border-0 bg-danger bg-opacity-10 mb-4 d-none">
                        <div class="card-body text-center">
                            <i class="bi bi-exclamation-triangle-fill text-danger fs-1 mb-3"></i>
                            <h5 class="text-danger">Not Eligible</h5>
                            <p class="mb-2" id="notEnrolledMessage">You are not enrolled in this course/year.</p>
                            <p class="text-muted small">You can only mark attendance for your enrolled courses.</p>
                            <button class="btn btn-outline-danger mt-2" onclick="resetScanner()">
                                <i class="bi bi-arrow-repeat me-2"></i>Scan Another QR
                            </button>
                        </div>
                    </div>

                    <!-- Success Preview (shown after marking) -->
                    <div id="successPreview" class="d-none">
                        <div class="alert alert-success border-0">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-check-circle-fill text-success me-3 fs-4"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-2" id="successMessage">Attendance Marked Successfully!</h6>
                                    <div id="successDetails" class="mb-2"></div>
                                    <div id="successAccuracy" class="small text-muted"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-3 justify-content-center mt-3">
                        <button class="btn btn-primary px-4" onclick="resetScanner()">
                            <i class="bi bi-arrow-repeat me-2"></i>Scan New QR
                        </button>
                        <button class="btn btn-outline-danger px-4" onclick="stopScanner()">
                            <i class="bi bi-stop-circle me-2"></i>Stop
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer bg-white border-0 px-4 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="bi bi-clock-history me-1"></i>
                            <span id="scanCounter">0</span> scans today
                        </small>
                        <small class="text-muted">
                            <i class="bi bi-satellite me-1"></i>
                            <span id="footerGpsStatus">GPS: Waiting...</span>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance Card -->
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Today's Attendance</h6>
                </div>
                <div class="card-body p-4">
                    <div id="recentAttendance">
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                            No attendance marked today
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<!-- Hidden fields for storing QR data -->
<input type="hidden" id="currentQRData" value="">
<input type="hidden" id="currentDetails" value="">
<input type="hidden" id="studentCourse" value="<?= $student['course_id'] ?>">
<input type="hidden" id="studentYear" value="<?= $student['year_id'] ?>">
<input type="hidden" id="studentLat" value="">
<input type="hidden" id="studentLng" value="">
<input type="hidden" id="studentAccuracy" value="">

<style>
.main-content {
    background-color: #f8f9fa;
    min-height: 100vh;
}

.scanner-container {
    position: relative;
    width: 100%;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

#reader {
    width: 100% !important;
    height: 400px !important;
    background: #000;
}

#reader video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.scanner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
    border: 2px solid rgba(255,255,255,0.5);
    border-radius: 15px;
}

.scanner-line {
    position: absolute;
    left: 10%;
    right: 10%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #007bff, transparent);
    animation: scan 2s linear infinite;
    box-shadow: 0 0 20px #007bff;
}

@keyframes scan {
    0% { top: 20%; opacity: 0; }
    50% { top: 80%; opacity: 1; }
    100% { top: 20%; opacity: 0; }
}

#reader__dashboard_section {
    display: none !important;
}

.status-area {
    transition: all 0.3s ease;
}

.btn-group .btn.active {
    background-color: #007bff;
    color: white;
}

#lectureDetailsCard, #notEnrolledCard, #locationVerificationCard {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.progress {
    border-radius: 10px;
    background-color: #e9ecef;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.5s ease;
    position: relative;
}

.progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

#gpsStatus {
    padding: 5px 10px;
    background: rgba(0,0,0,0.05);
    border-radius: 20px;
    display: inline-block;
    font-size: 0.85rem;
}

#accuracyWarning {
    animation: slideIn 0.3s ease;
    font-size: 0.9rem;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.bg-warning {
    background-color: #ffc107 !important;
}

.text-warning {
    color: #ffc107 !important;
}

.text-success {
    color: #28a745 !important;
}

.text-danger {
    color: #dc3545 !important;
}

.badge {
    padding: 5px 10px;
    font-weight: 500;
}

@media (max-width: 768px) {
    #reader {
        height: 350px !important;
    }
    
    .d-flex.gap-3 {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}
</style>

<!-- QR Scanner Library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
let scanner;
let isProcessing = false;
let currentCamera = 'environment';
let scanCount = 0;
let currentQRData = null;
let currentDetails = null;
let locationWatchId = null;

// DOM Elements
const statusBar = document.getElementById('statusBar');
const statusDetail = document.getElementById('statusDetail');
const scanningSpinner = document.getElementById('scanningSpinner');
const scanCounter = document.getElementById('scanCounter');
const lectureDetailsCard = document.getElementById('lectureDetailsCard');
const locationVerificationCard = document.getElementById('locationVerificationCard');
const notEnrolledCard = document.getElementById('notEnrolledCard');
const successPreview = document.getElementById('successPreview');
const markBtn = document.getElementById('markBtn');
const markedBtn = document.getElementById('markedBtn');
const alreadyMarkedMsg = document.getElementById('alreadyMarkedMsg');
const locationRequirementBadge = document.getElementById('locationRequirementBadge');
const locationBanner = document.getElementById('locationBanner');
const locationStatus = document.getElementById('locationStatus');
const locationAccuracy = document.getElementById('locationAccuracy');
const locationSpinner = document.getElementById('locationSpinner');
const locationVerifySpinner = document.getElementById('locationVerifySpinner');
const locationVerifyStatus = document.getElementById('locationVerifyStatus');
const locationVerifyDistance = document.getElementById('locationVerifyDistance');
const locationVerifyAccuracy = document.getElementById('locationVerifyAccuracy');
const distanceProgress = document.getElementById('distanceProgress');
const retryLocationBtn = document.getElementById('retryLocationBtn');
const footerGpsStatus = document.getElementById('footerGpsStatus');
const gpsStatus = document.getElementById('gpsStatus');

// Detail fields
const detailSubject = document.getElementById('detailSubject');
const detailFaculty = document.getElementById('detailFaculty');
const detailCourse = document.getElementById('detailCourse');
const detailYear = document.getElementById('detailYear');
const detailDate = document.getElementById('detailDate');
const detailSession = document.getElementById('detailSession');

// Student info
const studentCourse = document.getElementById('studentCourse').value;
const studentYear = document.getElementById('studentYear').value;

// Hidden fields for location
const studentLat = document.getElementById('studentLat');
const studentLng = document.getElementById('studentLng');
const studentAccuracy = document.getElementById('studentAccuracy');

// Initialize scanner
function startScanner() {
    scanner = new Html5Qrcode("reader");
    
    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 }
    };

    scanner.start(
        { facingMode: currentCamera },
        config,
        onScanSuccess,
        onScanError
    ).then(() => {
        statusBar.innerHTML = '✅ Scanner ready';
        statusDetail.innerHTML = 'Position QR code in frame';
        scanningSpinner.style.display = 'inline-block';
    }).catch(err => {
        console.error(err);
        statusBar.innerHTML = '❌ Camera error';
        statusDetail.innerHTML = 'Please check camera permissions';
        scanningSpinner.style.display = 'none';
    });
}

// Start location tracking with GPS accuracy requirements
function startLocationTracking() {
    if (!navigator.geolocation) {
        showLocationError('Geolocation is not supported by your browser');
        return;
    }

    locationBanner.style.display = 'block';
    locationStatus.innerHTML = '📍 Getting your GPS location... (accuracy target: ±5m – ±20m)';
    locationSpinner.style.display = 'inline-block';

    const options = {
        enableHighAccuracy: true,        // Force GPS
        timeout: 15000,                  // 15 second timeout
        maximumAge: 0                     // Don't use cached positions
    };

    function success(pos) {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        const accuracy = pos.coords.accuracy;
        
        // Store location data
        studentLat.value = lat;
        studentLng.value = lng;
        studentAccuracy.value = accuracy;
        
        // Update GPS status in footer
        updateGPSStatus(accuracy);
        
        // Check if accuracy meets GPS requirements (±5m – ±20m)
        if (accuracy <= 20) {
            // Good GPS accuracy
            locationStatus.innerHTML = '✅ GPS acquired';
            locationAccuracy.innerHTML = `Accuracy: ±${Math.round(accuracy)}m (✓ GPS quality)`;
            locationSpinner.style.display = 'none';
            
            // Update verification card if visible
            if (!locationVerificationCard.classList.contains('d-none')) {
                performLocationVerification();
            }
            
            // Auto-hide banner after 3 seconds
            setTimeout(() => {
                locationBanner.style.display = 'none';
            }, 3000);
            
            console.log('GPS location:', lat, lng, 'accuracy:', accuracy, 'meters');
        } else {
            // Accuracy is too low (IP/Wi-Fi based)
            locationStatus.innerHTML = '⚠️ Poor location accuracy';
            locationAccuracy.innerHTML = `Accuracy: ±${Math.round(accuracy)}m (❌ Need GPS: ±5m – ±20m)`;
            locationSpinner.style.display = 'none';
            
            // Show warning
            const warningDiv = document.createElement('div');
            warningDiv.className = 'alert alert-warning mt-2';
            warningDiv.id = 'accuracyWarning';
            warningDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Low accuracy detected!</strong><br>
                Current accuracy: ±${Math.round(accuracy)}m. 
                GPS accuracy (5-20m) is required for location verification.<br>
                <small>Please move to an open area, enable high accuracy mode, and try again.</small>
            `;
            
            // Remove existing warning if any
            const existingWarning = document.getElementById('accuracyWarning');
            if (existingWarning) existingWarning.remove();
            
            locationBanner.appendChild(warningDiv);
            
            // If verification is in progress, show retry button
            if (!locationVerificationCard.classList.contains('d-none')) {
                locationVerifyStatus.innerHTML = '❌ Poor GPS accuracy';
                locationVerifyDistance.innerHTML = `<span class="badge bg-warning">Distance verification pending...</span>`;
                locationVerifyAccuracy.innerHTML = `<span class="badge bg-warning">Accuracy: ±${Math.round(accuracy)}m (need 5-20m)</span>`;
                locationVerifySpinner.style.display = 'none';
                retryLocationBtn.style.display = 'inline-block';
            }
            
            // Try to get better accuracy
            setTimeout(() => {
                if (locationWatchId) {
                    navigator.geolocation.clearWatch(locationWatchId);
                    startLocationTracking();
                }
            }, 5000);
        }
    }

    function error(err) {
        console.warn('GPS error:', err);
        let message = getLocationErrorMessage(err);
        showLocationError(message);
        
        // Show specific instructions for GPS
        if (err.code === 1) { // PERMISSION_DENIED
            message = '❌ GPS permission denied. Please enable precise location access.';
        } else if (err.code === 3) { // TIMEOUT
            message = '❌ GPS timeout. Please ensure you are in an open area.';
        }
        
        locationStatus.innerHTML = message;
        locationAccuracy.innerHTML = 'Enable high accuracy GPS mode';
        locationSpinner.style.display = 'none';
        
        // Update footer
        footerGpsStatus.innerHTML = 'GPS: Error';
        
        // Add retry button
        if (!locationVerificationCard.classList.contains('d-none')) {
            locationVerifyStatus.innerHTML = message;
            locationVerifySpinner.style.display = 'none';
            retryLocationBtn.style.display = 'inline-block';
        }
    }

    function getLocationErrorMessage(error) {
        switch(error.code) {
            case error.PERMISSION_DENIED:
                return "GPS access denied. Please enable precise location.";
            case error.POSITION_UNAVAILABLE:
                return "GPS signal unavailable. Move to open area.";
            case error.TIMEOUT:
                return "GPS timeout. Please try again.";
            default:
                return "Unknown GPS error occurred.";
        }
    }

    function showLocationError(message) {
        locationStatus.innerHTML = message;
        locationAccuracy.innerHTML = '';
        locationSpinner.style.display = 'none';
        footerGpsStatus.innerHTML = 'GPS: Error';
    }

    // Clear existing watch
    if (locationWatchId) {
        navigator.geolocation.clearWatch(locationWatchId);
    }
    
    // Start watching position with high accuracy
    locationWatchId = navigator.geolocation.watchPosition(success, error, options);
    locationWatchStarted = true;
}

// Update GPS status display
function updateGPSStatus(accuracy) {
    let status = '';
    let color = '';
    let icon = '';
    
    if (accuracy <= 20) {
        status = 'GPS Active';
        color = 'text-success';
        icon = '✅';
    } else if (accuracy <= 50) {
        status = 'WiFi/Cellular';
        color = 'text-warning';
        icon = '⚠️';
    } else {
        status = 'IP Based';
        color = 'text-danger';
        icon = '❌';
    }
    
    footerGpsStatus.innerHTML = `${icon} ${status} (${Math.round(accuracy)}m)`;
    
    // Update status area GPS indicator
    gpsStatus.innerHTML = `<span class="${color}"><i class="bi bi-satellite"></i> ${status} Accuracy: ±${Math.round(accuracy)}m</span>`;
}

// Success handler - Get QR info first
async function onScanSuccess(decodedText) {
    if (isProcessing) return;
    
    try {
        isProcessing = true;
        statusBar.innerHTML = '⏳ Reading QR code...';
        
        // Pause scanner
        if (scanner) {
            await scanner.pause();
        }
        
        // Get QR info from server
        const formData = new URLSearchParams();
        formData.append('action', 'get_info');
        formData.append('qr_data', decodedText);

        const response = await fetch(window.location.href, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData.toString()
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Store QR data for later use
            currentQRData = decodedText;
            currentDetails = result.details;
            
            // Update lecture details
            updateLectureDetails(result.details);
            
            // Check if already marked
            if (result.already_marked) {
                showAlreadyMarked();
            } else {
                showMarkButton();
            }
            
            // Show location requirement if needed
            if (result.requires_location) {
                locationRequirementBadge.classList.remove('d-none');
            } else {
                locationRequirementBadge.classList.add('d-none');
            }
            
            // Hide scanner view, show details
            document.getElementById('reader').style.display = 'none';
            lectureDetailsCard.classList.remove('d-none');
            notEnrolledCard.classList.add('d-none');
            locationVerificationCard.classList.add('d-none');
            successPreview.classList.add('d-none');
            
            statusBar.innerHTML = '✅ QR scanned';
            statusDetail.innerHTML = 'Review lecture details';
            
            // Start location tracking if needed
            if (result.requires_location) {
                startLocationTracking();
            }
            
        } else {
            throw new Error(result.message);
        }
        
    } catch (error) {
        statusBar.innerHTML = '❌ Error';
        statusDetail.innerHTML = error.message;
        isProcessing = false;
        
        // Resume scanner on error
        if (scanner) {
            setTimeout(() => {
                scanner.resume().catch(() => {});
            }, 2000);
        }
    }
}

function updateLectureDetails(details) {
    detailSubject.textContent = details.subject;
    detailFaculty.textContent = details.faculty;
    detailCourse.textContent = details.course;
    detailYear.textContent = details.year;
    detailDate.textContent = details.date;
    detailSession.textContent = details.session_type;
    
    // Store details for marking
    document.getElementById('currentDetails').value = JSON.stringify(details);
}

function showAlreadyMarked() {
    markBtn.classList.add('d-none');
    markedBtn.classList.remove('d-none');
    alreadyMarkedMsg.classList.remove('d-none');
}

function showMarkButton() {
    markBtn.classList.remove('d-none');
    markedBtn.classList.add('d-none');
    alreadyMarkedMsg.classList.add('d-none');
}

// Start attendance process (verify location if needed)
async function startAttendanceProcess() {
    if (currentDetails && currentDetails.verification_type === 'location_based') {
        // Show location verification card
        lectureDetailsCard.classList.add('d-none');
        locationVerificationCard.classList.remove('d-none');
        
        // Start location verification
        if (studentLat.value && studentLng.value && studentAccuracy.value <= 20) {
            performLocationVerification();
        } else {
            locationVerifyStatus.innerHTML = '📍 Acquiring GPS signal...';
            locationVerifyDistance.innerHTML = '<span class="badge bg-warning">Waiting for GPS (5-20m accuracy)...</span>';
            startLocationTracking();
        }
    } else {
        // Standard attendance without location
        markAttendance();
    }
}

// Perform actual location verification with server
async function performLocationVerification() {
    try {
        locationVerifySpinner.style.display = 'inline-block';
        locationVerifyStatus.innerHTML = '🔍 Verifying your location...';
        retryLocationBtn.style.display = 'none';
        
        const accuracy = parseFloat(studentAccuracy.value);
        
        // Double-check accuracy before sending
        if (accuracy > 20) {
            throw new Error(`GPS accuracy too low: ±${Math.round(accuracy)}m. Please move to an open area.`);
        }
        
        const formData = new URLSearchParams();
        formData.append('action', 'verify_location');
        formData.append('qr_data', currentQRData);
        formData.append('student_lat', studentLat.value);
        formData.append('student_lng', studentLng.value);
        formData.append('student_accuracy', accuracy);

        const response = await fetch(window.location.href, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData.toString()
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update distance display
            const distancePercent = Math.min(100, (result.distance / result.allowed_radius) * 100);
            const progressColor = result.verified ? 'bg-success' : 'bg-danger';
            
            distanceProgress.style.width = distancePercent + '%';
            distanceProgress.className = `progress-bar ${progressColor}`;
            
            // Show accuracy info
            const accuracyBadge = result.accuracy <= 20 ? 
                `<span class="badge bg-success">GPS Accuracy: ±${result.accuracy}m ✓</span>` :
                `<span class="badge bg-warning">Low Accuracy: ±${result.accuracy}m</span>`;
            
            locationVerifyAccuracy.innerHTML = accuracyBadge;
            
            if (result.verified) {
                locationVerifyStatus.innerHTML = '✅ ' + result.message;
                locationVerifyDistance.innerHTML = `<span class="badge bg-success">Distance: ${result.distance}m (within ${result.allowed_radius}m)</span>`;
                locationVerifySpinner.style.display = 'none';
                
                // Automatically mark attendance after 2 seconds
                setTimeout(() => {
                    markAttendance();
                }, 2000);
            } else {
                locationVerifyStatus.innerHTML = '❌ ' + result.message;
                locationVerifyDistance.innerHTML = `<span class="badge bg-danger">Distance: ${result.distance}m (max: ${result.allowed_radius}m)</span>`;
                locationVerifySpinner.style.display = 'none';
                retryLocationBtn.style.display = 'inline-block';
            }
        } else {
            throw new Error(result.message);
        }
        
    } catch (error) {
        locationVerifyStatus.innerHTML = '❌ Error: ' + error.message;
        locationVerifySpinner.style.display = 'none';
        retryLocationBtn.style.display = 'inline-block';
    }
}

// Retry location verification with fresh GPS
function retryLocationVerification() {
    // Clear old location
    studentLat.value = '';
    studentLng.value = '';
    studentAccuracy.value = '';
    
    // Remove accuracy warning
    const warning = document.getElementById('accuracyWarning');
    if (warning) warning.remove();
    
    // Reset UI
    locationVerifyStatus.innerHTML = '📍 Acquiring GPS signal...';
    locationVerifyDistance.innerHTML = '<span class="badge bg-warning">Waiting for GPS (5-20m accuracy)...</span>';
    locationVerifyAccuracy.innerHTML = '';
    locationVerifySpinner.style.display = 'inline-block';
    retryLocationBtn.style.display = 'none';
    distanceProgress.style.width = '0%';
    
    // Force fresh GPS acquisition
    if (locationWatchId) {
        navigator.geolocation.clearWatch(locationWatchId);
    }
    
    // Show banner with GPS instructions
    locationBanner.style.display = 'block';
    locationStatus.innerHTML = '📍 Getting your GPS location... (accuracy target: ±5m – ±20m)';
    locationAccuracy.innerHTML = 'Ensure you have clear sky view';
    locationSpinner.style.display = 'inline-block';
    
    // Start fresh GPS tracking
    startLocationTracking();
}

// Mark attendance
async function markAttendance() {
    try {
        statusBar.innerHTML = '⏳ Marking attendance...';
        markBtn.disabled = true;
        markBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        
        const formData = new URLSearchParams();
        formData.append('action', 'mark_attendance');
        formData.append('qr_data', currentQRData);
        formData.append('student_lat', studentLat.value || '');
        formData.append('student_lng', studentLng.value || '');
        formData.append('student_accuracy', studentAccuracy.value || '');

        const response = await fetch(window.location.href, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData.toString()
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Hide all cards, show success
            lectureDetailsCard.classList.add('d-none');
            locationVerificationCard.classList.add('d-none');
            notEnrolledCard.classList.add('d-none');
            successPreview.classList.remove('d-none');
            
            let successHtml = result.message;
            if (result.distance) {
                successHtml += `<br><small class="text-muted">${result.distance}</small>`;
            }
            if (result.accuracy) {
                document.getElementById('successAccuracy').innerHTML = result.accuracy;
            }
            document.getElementById('successMessage').innerHTML = successHtml;
            
            statusBar.innerHTML = '✅ Success!';
            statusDetail.innerHTML = 'Attendance marked';
            
            // Update scan count
            scanCount++;
            scanCounter.innerHTML = scanCount;
            localStorage.setItem('scan_count_' + new Date().toDateString(), scanCount);
            
            // Refresh recent attendance
            setTimeout(() => {
                loadRecentAttendance();
            }, 500);
            
        } else {
            throw new Error(result.message);
        }
        
    } catch (error) {
        statusBar.innerHTML = '❌ Error';
        statusDetail.innerHTML = error.message;
        markBtn.disabled = false;
        markBtn.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Proceed to Mark Attendance';
    }
}

function onScanError(error) {
    if (error?.includes('NotFoundException')) return;
    console.warn('Scan error:', error);
}

// Switch camera
function switchCamera(type) {
    currentCamera = type;
    
    document.querySelectorAll('#cameraToggle .btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    resetScanner();
}

// Reset scanner for new scan
function resetScanner() {
    // Hide all cards
    lectureDetailsCard.classList.add('d-none');
    locationVerificationCard.classList.add('d-none');
    notEnrolledCard.classList.add('d-none');
    successPreview.classList.add('d-none');
    document.getElementById('reader').style.display = 'block';
    
    // Reset button states
    markBtn.disabled = false;
    markBtn.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Proceed to Mark Attendance';
    showMarkButton();
    
    // Reset location
    studentLat.value = '';
    studentLng.value = '';
    studentAccuracy.value = '';
    
    // Reset GPS status
    footerGpsStatus.innerHTML = 'GPS: Waiting...';
    gpsStatus.innerHTML = '';
    
    // Reset processing flag
    isProcessing = false;
    currentQRData = null;
    currentDetails = null;
    
    // Clear location watch
    if (locationWatchId) {
        navigator.geolocation.clearWatch(locationWatchId);
        locationWatchId = null;
    }
    
    // Remove accuracy warning
    const warning = document.getElementById('accuracyWarning');
    if (warning) warning.remove();
    
    // Resume scanner
    if (scanner) {
        statusBar.innerHTML = 'Ready to scan';
        statusDetail.innerHTML = 'Position QR code in frame';
        scanner.resume().catch(() => {
            // If resume fails, restart
            scanner.stop().then(() => {
                startScanner();
            });
        });
    }
}

// Stop scanner
function stopScanner() {
    if (scanner) {
        scanner.stop();
        statusBar.innerHTML = '⏸️ Scanner paused';
        statusDetail.innerHTML = 'Click "Scan New QR" to resume';
        scanningSpinner.style.display = 'none';
    }
}

// Toggle instructions
function toggleInstructions() {
    const card = document.getElementById('instructionsCard');
    card.classList.toggle('d-none');
}

// Load recent attendance
async function loadRecentAttendance() {
    try {
        const response = await fetch('get_recent_attendance.php');
        const html = await response.text();
        document.getElementById('recentAttendance').innerHTML = html;
    } catch (error) {
        console.error('Failed to load recent attendance:', error);
    }
}

// Initialize on load
window.onload = () => {
    startScanner();
    loadRecentAttendance();
    
    // Load scan count from localStorage
    const today = new Date().toDateString();
    const stored = localStorage.getItem('scan_count_' + today);
    if (stored) {
        scanCount = parseInt(stored);
        scanCounter.innerHTML = scanCount;
    }
    
    // Start periodic GPS status check
    setInterval(() => {
        if (studentLat.value && studentAccuracy.value) {
            updateGPSStatus(parseFloat(studentAccuracy.value));
        }
    }, 2000);
};

// Save scan count and cleanup
window.addEventListener('beforeunload', () => {
    const today = new Date().toDateString();
    localStorage.setItem('scan_count_' + today, scanCount);
    
    if (locationWatchId) {
        navigator.geolocation.clearWatch(locationWatchId);
    }
    
    if (scanner) {
        scanner.stop().catch(() => {});
    }
});
</script>

<?php include('../includes/footer.php'); ?>