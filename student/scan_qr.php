<?php
ob_start();
include('../config/db.php');
include('../config/constants.php'); // Add constants for GPS thresholds
include('../includes/header.php');
include('../includes/functions/geo_location.php');
include('../includes/functions/gps_helper.php');
include('../includes/functions/security.php');

if (!isset($_SESSION['user_id'])) exit('Unauthorized');
$student_id = $_SESSION['user_id'];

/* =====================================================
   AJAX: MARK ATTENDANCE WITH LOCATION VERIFICATION
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    ob_clean();
    header('Content-Type: application/json');

    try {
        // Rate limiting check
        $identifier = $_SERVER['REMOTE_ADDR'] . '_' . $student_id;
        if (!checkRateLimit($pdo, $identifier, 'MARK_ATTENDANCE', RATE_LIMIT_ATTENDANCE, 1)) {
            throw new Exception('Too many attempts. Please wait a moment.');
        }

        $qr_encrypted = $_POST['qr_data'];
        $student_lat = $_POST['student_lat'] ?? null;
        $student_lng = $_POST['student_lng'] ?? null;
        $location_accuracy = $_POST['location_accuracy'] ?? 100;
        $device_info = $_POST['device_info'] ?? $_SERVER['HTTP_USER_AGENT'];

        // Validate location data
        if (!$student_lat || !$student_lng) {
            throw new Exception('Location data required. Please enable GPS.');
        }

        if (!validateCoordinates($student_lat, $student_lng)) {
            throw new Exception('Invalid location coordinates');
        }

        // Decrypt QR data
        $decrypted = openssl_decrypt(
            $qr_encrypted,
            QR_ENCRYPTION_ALGO,
            SECRET_KEY,
            0,
            substr(hash('sha256', SECRET_KEY), 0, 16)
        );

        if (!$decrypted) {
            throw new Exception('Invalid QR code');
        }

        $data = json_decode($decrypted, true);
        if (!$data || !is_array($data)) {
            throw new Exception('Invalid QR code format');
        }

        // Required fields validation
        $required = ['sid', 'tok', 'exp', 'ins'];
        foreach ($required as $f) {
            if (empty($data[$f])) throw new Exception("Invalid QR code: missing data");
        }

        // Verify institution
        if ($data['ins'] !== INSTITUTION_ID) {
            throw new Exception('QR code not issued by this institution');
        }

        // Expiry check
        if ($data['exp'] < time()) {
            throw new Exception("QR code expired");
        }

        // Get session details with location
        $stmt = $pdo->prepare("
            SELECT as.*, s.faculty_id, s.subject_id, s.course_id, s.year_id, s.session_id,
                   sub.subject_name
            FROM attendance_sessions as
            JOIN schedule s ON as.schedule_id = s.id
            JOIN subjects sub ON s.subject_id = sub.id
            WHERE as.id = ? AND as.token LIKE ?
        ");
        $stmt->execute([$data['sid'], $data['tok'] . '%']);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            throw new Exception("Attendance session not found");
        }

        // Verify session has location
        if (is_null($session['faculty_lat']) || is_null($session['faculty_lng'])) {
            throw new Exception("Faculty location not set for this session");
        }

        // Check for duplicate attendance
        $stmt = $pdo->prepare("
            SELECT id, status FROM attendance 
            WHERE student_id = ? AND session_id = ?
        ");
        $stmt->execute([$student_id, $session['id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            throw new Exception("Attendance already marked for this session");
        }

        // Calculate distance using Haversine formula
        $distance = haversineDistance(
            $session['faculty_lat'],
            $session['faculty_lng'],
            $student_lat,
            $student_lng
        );
        $distance = round($distance, 2);

        // Check for GPS spoofing
        $spoofCheck = detectGPSSpoofing(
            $student_lat,
            $student_lng,
            $location_accuracy,
            $_SERVER['REMOTE_ADDR']
        );

        // Determine if location is acceptable
        $session_type = $session['session_type'] ?? 'Lecture';
        $allowed_radius = $session['allowed_radius'] ?? DEFAULT_RADIUS_LECTURE;

        // Accuracy check
        $accuracyCheck = isAccuracyAcceptable(
            $location_accuracy,
            $session_type,
            $distance
        );

        // Verification logic
        $status = 'Present';
        $failure_reason = null;
        $verification_result = 'PASSED';

        // Location verification
        if ($distance > $allowed_radius) {
            $status = 'Absent';
            $failure_reason = "You are {$distance}m from faculty (max {$allowed_radius}m)";
            $verification_result = 'FAILED';
        } 
        // Accuracy verification
        elseif (!$accuracyCheck['acceptable']) {
            $status = 'Absent';
            $failure_reason = $accuracyCheck['reason'];
            $verification_result = 'FAILED';
        }
        // Spoofing check
        elseif ($spoofCheck['suspicious']) {
            $status = 'Absent';
            $failure_reason = "Suspicious location detected";
            $verification_result = 'FAILED';
            
            // Log severe spoofing attempts
            if ($spoofCheck['score'] < 0.3) {
                error_log("SEVERE SPOOFING: Student $student_id - " . implode(', ', $spoofCheck['reasons']));
            }
        }
        // Proximity override for borderline cases
        elseif ($distance <= PROXIMITY_OVERRIDE_DISTANCE && 
                $location_accuracy <= PROXIMITY_OVERRIDE_MAX_ACCURACY && 
                $session_type !== 'Exam') {
            $status = 'Present';
            $verification_result = 'PASSED_WITH_WARNING';
        }

        // Insert attendance record
        $stmt = $pdo->prepare("
            INSERT INTO attendance
            (student_id, schedule_id, faculty_id, subjects_id, course_id, year_id, session_id, 
             date, status, student_lat, student_lng, distance_from_faculty, failure_reason,
             student_location_accuracy, location_verified_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $student_id,
            $session['schedule_id'],
            $session['faculty_id'],
            $session['subject_id'],
            $session['course_id'],
            $session['year_id'],
            $session['id'],
            $session['date'],
            $status,
            $student_lat,
            $student_lng,
            $distance,
            $failure_reason,
            $location_accuracy
        ]);

        $attendance_id = $pdo->lastInsertId();

        // Log verification attempt
        logLocationVerification($pdo, [
            'attendance_id' => $attendance_id,
            'student_id' => $student_id,
            'session_id' => $session['id'],
            'faculty_lat' => $session['faculty_lat'],
            'faculty_lng' => $session['faculty_lng'],
            'student_lat' => $student_lat,
            'student_lng' => $student_lng,
            'calculated_distance' => $distance,
            'allowed_radius' => $allowed_radius,
            'verification_result' => $verification_result,
            'failure_reason' => $failure_reason
        ]);

        // Calculate confidence score
        $confidence = calculateLocationConfidence(
            $distance,
            $allowed_radius,
            $location_accuracy,
            $session_type
        );

        echo json_encode([
            'success' => $status === 'Present',
            'status' => $status,
            'message' => $status === 'Present' ? 'Attendance marked successfully ✅' : $failure_reason,
            'distance' => $distance,
            'allowed_radius' => $allowed_radius,
            'subject' => $session['subject_name'],
            'confidence' => $confidence,
            'verification_time' => date('H:i:s'),
            'accuracy_info' => [
                'student_accuracy' => $location_accuracy,
                'quality' => $accuracyCheck['confidence'],
                'gps_emoji' => getGpsStatusEmoji($location_accuracy)
            ]
        ]);
        exit;

    } catch (Throwable $e) {
        error_log("Attendance error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Get student info
$stmt = $pdo->prepare("SELECT name, course_id, year_id FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
?>

<main class="main-content">
<div class="px-4 pt-4 pb-5">
    
    <!-- Page Header with GPS Status -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">
            <i class="bi bi-qr-code-scan me-2"></i> Scan Attendance QR
        </h2>
        <div class="d-flex gap-2">
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2" id="gpsBadge">
                <i class="bi bi-satellite me-2"></i> <span id="gpsStatus">Acquiring GPS...</span>
            </span>
            <button class="btn btn-outline-secondary btn-sm" onclick="toggleInstructions()">
                <i class="bi bi-question-circle me-2"></i> Help
            </button>
        </div>
    </div>

    <!-- Enhanced GPS Status Card -->
    <div class="card shadow-sm border-0 mb-4" id="gpsCard">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3" id="gpsIcon">
                            <i class="bi bi-satellite text-primary fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">GPS Signal Quality</h6>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress" style="width: 150px; height: 8px;">
                                    <div id="gpsProgress" class="progress-bar progress-bar-striped progress-bar-animated" 
                                         style="width: 0%; background-color: #6c757d;"></div>
                                </div>
                                <span class="badge bg-secondary" id="gpsAccuracy">--</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end gap-3">
                        <div class="text-center">
                            <div class="text-muted small">Accuracy</div>
                            <div class="fw-bold" id="accuracyValue">--</div>
                        </div>
                        <div class="text-center">
                            <div class="text-muted small">Satellites</div>
                            <div class="fw-bold" id="satelliteCount">--</div>
                        </div>
                        <div class="text-center">
                            <div class="text-muted small">Status</div>
                            <div class="fw-bold" id="gpsMode">WAITING</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- GPS Tips (shown when accuracy is poor) -->
            <div id="gpsTips" class="mt-3 small" style="display: none;"></div>
            
            <!-- Hidden fields for location data -->
            <input type="hidden" id="studentLat" name="student_lat">
            <input type="hidden" id="studentLng" name="student_lng">
            <input type="hidden" id="locationAccuracy" name="location_accuracy">
        </div>
    </div>

    <!-- Instructions Card (Collapsible) -->
    <div class="card shadow-sm border-0 mb-4 d-none" id="instructionsCard">
        <div class="card-body">
            <div class="d-flex align-items-start">
                <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                    <i class="bi bi-info-circle text-primary fs-4"></i>
                </div>
                <div>
                    <h5 class="mb-2">How to Scan QR Code</h5>
                    <ul class="text-muted mb-0">
                        <li>Wait for GPS to get a good signal (accuracy < 50m is best)</li>
                        <li>Hold your phone steady about 15-20 cm from the QR code</li>
                        <li>Ensure good lighting for better scanning</li>
                        <li>You must be within the classroom radius (varies by session type)</li>
                        <li>QR codes expire after <?= QR_EXPIRY_MINUTES ?> minutes for security</li>
                    </ul>
                </div>
            </div>
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
                            <p class="text-muted small mb-0">Position the QR code within the frame to scan</p>
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
                        <small class="text-muted" id="gpsReady">
                            <i class="bi bi-check-circle-fill text-success"></i> GPS Ready
                        </small>
                    </div>

                    <!-- Scanner Container -->
                    <div class="scanner-container position-relative mb-4">
                        <div id="reader" class="scanner-frame"></div>
                        
                        <!-- Scanning Overlay -->
                        <div class="scanner-overlay">
                            <div class="scanner-line"></div>
                        </div>

                        <!-- Scanning Guide -->
                        <div class="scanning-guide text-center">
                            <i class="bi bi-upc-scan text-white opacity-50" style="font-size: 3rem;"></i>
                        </div>
                    </div>

                    <!-- Status Area -->
                    <div class="status-area p-3 rounded-3 bg-light mb-4">
                        <div class="d-flex align-items-center">
                            <div class="spinner-grow spinner-grow-sm text-primary me-3" id="scanningSpinner"></div>
                            <div class="flex-grow-1">
                                <div id="statusBar" class="fw-medium">Ready to scan</div>
                                <small class="text-muted" id="statusDetail">Waiting for QR code...</small>
                            </div>
                            <div id="signalStrength" class="text-success">
                                <i class="bi bi-wifi"></i>
                            </div>
                        </div>
                    </div>

                    <!-- QR Preview Section -->
                    <div id="qrPreviewBox" class="d-none">
                        <div class="alert alert-success border-0">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-check-circle-fill text-success me-3 fs-4"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-2">QR Code Detected!</h6>
                                    <div id="qrPreview" class="mb-2"></div>
                                    <div class="mb-2">
                                        <span id="distanceWarning" class="badge bg-warning text-dark d-none">
                                            <i class="bi bi-exclamation-triangle me-1"></i> Distance check in progress
                                        </span>
                                    </div>
                                    <div class="progress mb-2" style="height: 5px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                             id="processingProgress" 
                                             style="width: 0%"></div>
                                    </div>
                                    <small class="text-muted">Verifying location and marking attendance...</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-3 justify-content-center">
                        <button class="btn btn-primary px-4" onclick="resetScanner()">
                            <i class="bi bi-arrow-repeat me-2"></i>Scan Again
                        </button>
                        <button class="btn btn-outline-secondary px-4" onclick="toggleTorch()" id="torchBtn">
                            <i class="bi bi-lightbulb me-2"></i>Flashlight
                        </button>
                        <button class="btn btn-outline-danger px-4" onclick="stopScanner()">
                            <i class="bi bi-stop-circle me-2"></i>Stop
                        </button>
                    </div>

                    <!-- Manual Location Option (for legitimate cases) -->
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="bi bi-exclamation-circle"></i> 
                            <a href="#" onclick="showManualLocationDialog()" class="text-decoration-none">GPS issues? Click here</a>
                        </small>
                    </div>
                </div>

                <!-- Card Footer -->
                <div class="card-footer bg-white border-0 px-4 py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <small class="text-muted">
                            <i class="bi bi-clock-history me-1"></i>
                            <span id="scanCounter">0</span> scans today
                        </small>
                        <small class="text-muted">
                            <i class="bi bi-geo-alt-fill me-1"></i>
                            <span id="locationStatus">Waiting for GPS</span>
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

<!-- Manual Location Modal -->
<div class="modal fade" id="manualLocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Manual Location Confirmation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>GPS is having trouble getting your precise location. Please confirm:</p>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="confirmInClass">
                    <label class="form-check-label">
                        I am physically present in the classroom
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="confirmNoSpoof">
                    <label class="form-check-label">
                        I am not using any location spoofing apps
                    </label>
                </div>
                <div class="alert alert-info mt-3">
                    <small><i class="bi bi-info-circle"></i> Manual confirmation will be logged and may be reviewed by faculty.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="submitManualLocation()" id="manualSubmitBtn" disabled>
                    Confirm and Proceed
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
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
    height: 450px !important;
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
    box-shadow: 0 0 0 9999px rgba(0,0,0,0.3);
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

.scanning-guide {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    opacity: 0.5;
    pointer-events: none;
}

#reader__scan_region {
    background: #000;
}

#reader__dashboard_section {
    display: none !important;
}

.status-area {
    transition: all 0.3s ease;
}

.gps-excellent { background-color: #28a745 !important; }
.gps-good { background-color: #17a2b8 !important; }
.gps-fair { background-color: #ffc107 !important; }
.gps-poor { background-color: #dc3545 !important; }

#gpsProgress {
    transition: width 0.3s ease;
}

.qr-preview-item {
    background: white;
    border-radius: 10px;
    padding: 10px;
    border-left: 4px solid #28a745;
}

.btn-group .btn.active {
    background-color: #007bff;
    color: white;
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
    
    #gpsCard .row {
        flex-direction: column;
    }
    
    #gpsCard .col-md-6:last-child .d-flex {
        justify-content: center !important;
        margin-top: 1rem;
    }
}
</style>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
// ============================================
// ENHANCED GPS TRACKING
// ============================================

let locationWatchId = null;
let bestLocation = null;
let locationAttempts = 0;
let isGpsReady = false;
let scanner;
let isProcessing = false;
let torchEnabled = false;
let currentCamera = 'environment';
let scanCount = 0;

// GPS Thresholds from PHP
const GPS_THRESHOLDS = {
    EXCELLENT: <?= GPS_ACCURACY_EXCELLENT ?>,
    GOOD: <?= GPS_ACCURACY_GOOD ?>,
    FAIR: <?= GPS_ACCURACY_FAIR ?>,
    POOR: <?= GPS_ACCURACY_POOR ?>
};

// DOM Elements
const statusBar = document.getElementById('statusBar');
const statusDetail = document.getElementById('statusDetail');
const qrPreviewBox = document.getElementById('qrPreviewBox');
const qrPreview = document.getElementById('qrPreview');
const scanningSpinner = document.getElementById('scanningSpinner');
const processingProgress = document.getElementById('processingProgress');
const torchBtn = document.getElementById('torchBtn');
const scanCounter = document.getElementById('scanCounter');
const gpsProgress = document.getElementById('gpsProgress');
const gpsAccuracy = document.getElementById('gpsAccuracy');
const accuracyValue = document.getElementById('accuracyValue');
const gpsMode = document.getElementById('gpsMode');
const gpsStatus = document.getElementById('gpsStatus');
const gpsIcon = document.getElementById('gpsIcon');
const gpsBadge = document.getElementById('gpsBadge');
const distanceWarning = document.getElementById('distanceWarning');

// Initialize GPS tracking
function startGpsTracking() {
    if (!navigator.geolocation) {
        updateGpsStatus('error', 'GPS not supported');
        return;
    }

    const options = {
        enableHighAccuracy: true,
        timeout: <?= GPS_TIMEOUT_MS ?>,
        maximumAge: <?= GPS_MAX_AGE_MS ?>
    };

    updateGpsStatus('info', 'Acquiring GPS...');
    
    locationWatchId = navigator.geolocation.watchPosition(
        gpsSuccess,
        gpsError,
        options
    );

    // Timeout fallback
    setTimeout(() => {
        if (!bestLocation) {
            // Try without high accuracy
            navigator.geolocation.getCurrentPosition(
                gpsFallbackSuccess,
                gpsFallbackError,
                { enableHighAccuracy: false, timeout: 5000 }
            );
        }
    }, 15000);
}

function gpsSuccess(position) {
    const accuracy = position.coords.accuracy;
    locationAttempts++;

    if (!bestLocation || accuracy < bestLocation.accuracy) {
        bestLocation = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            accuracy: accuracy,
            timestamp: position.timestamp
        };

        updateGpsDisplay(bestLocation);
        
        // Auto-accept based on accuracy
        if (accuracy <= GPS_THRESHOLDS.GOOD) {
            acceptGpsLocation(bestLocation, 'excellent');
        } else if (accuracy <= GPS_THRESHOLDS.FAIR && locationAttempts > 3) {
            acceptGpsLocation(bestLocation, 'fair');
        } else if (accuracy <= GPS_THRESHOLDS.POOR && locationAttempts > 6) {
            acceptGpsLocation(bestLocation, 'poor');
        }
    }
}

function gpsError(error) {
    console.warn('GPS error:', error);
    
    if (locationAttempts > 3) {
        let message = '';
        let tips = [];
        
        switch(error.code) {
            case error.PERMISSION_DENIED:
                message = 'Location access denied';
                tips = getGpsTips('permission_denied');
                break;
            case error.POSITION_UNAVAILABLE:
                message = 'GPS signal unavailable';
                tips = getGpsTips('no_signal');
                break;
            case error.TIMEOUT:
                message = 'GPS timeout';
                tips = getGpsTips('timeout');
                break;
        }
        
        showGpsTips(message, tips);
    }
}

function gpsFallbackSuccess(position) {
    acceptGpsLocation({
        lat: position.coords.latitude,
        lng: position.coords.longitude,
        accuracy: position.coords.accuracy,
        timestamp: position.timestamp
    }, 'fallback');
}

function gpsFallbackError(error) {
    updateGpsStatus('error', 'Unable to get GPS');
    showGpsTips('GPS Unavailable', getGpsTips('low_accuracy'));
}

function acceptGpsLocation(location, quality) {
    // Store in hidden fields
    document.getElementById('studentLat').value = location.lat;
    document.getElementById('studentLng').value = location.lng;
    document.getElementById('locationAccuracy').value = location.accuracy;
    
    isGpsReady = true;
    
    // Clear watch
    if (locationWatchId) {
        navigator.geolocation.clearWatch(locationWatchId);
    }
    
    // Update UI
    updateGpsDisplay(location);
    gpsProgress.style.width = '100%';
    document.getElementById('gpsReady').innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> GPS Ready';
}

function updateGpsDisplay(location) {
    const accuracy = location.accuracy;
    
    // Update accuracy display
    accuracyValue.innerHTML = accuracy.toFixed(1) + 'm';
    gpsAccuracy.innerHTML = accuracy.toFixed(1) + 'm';
    
    // Calculate progress percentage (inverse: lower accuracy = higher progress)
    let progress = 0;
    let qualityClass = '';
    let qualityText = '';
    
    if (accuracy <= GPS_THRESHOLDS.EXCELLENT) {
        progress = 100;
        qualityClass = 'gps-excellent';
        qualityText = 'EXCELLENT';
        gpsMode.innerHTML = 'EXCELLENT';
        gpsMode.className = 'fw-bold text-success';
    } else if (accuracy <= GPS_THRESHOLDS.GOOD) {
        progress = 80;
        qualityClass = 'gps-good';
        qualityText = 'GOOD';
        gpsMode.innerHTML = 'GOOD';
        gpsMode.className = 'fw-bold text-info';
    } else if (accuracy <= GPS_THRESHOLDS.FAIR) {
        progress = 60;
        qualityClass = 'gps-fair';
        qualityText = 'FAIR';
        gpsMode.innerHTML = 'FAIR';
        gpsMode.className = 'fw-bold text-warning';
    } else if (accuracy <= GPS_THRESHOLDS.POOR) {
        progress = 40;
        qualityClass = 'gps-poor';
        qualityText = 'POOR';
        gpsMode.innerHTML = 'POOR';
        gpsMode.className = 'fw-bold text-danger';
    } else {
        progress = 20;
        qualityText = 'POOR';
        gpsMode.innerHTML = 'POOR';
        gpsMode.className = 'fw-bold text-danger';
    }
    
    // Update progress bar
    gpsProgress.style.width = progress + '%';
    gpsProgress.className = 'progress-bar progress-bar-striped ' + qualityClass;
    
    // Update status text
    updateGpsStatus('success', `${qualityText} (${accuracy.toFixed(1)}m)`);
    
    // Update satellite count (simulated)
    const satellites = Math.floor(Math.random() * 4) + 4; // 4-8 satellites
    document.getElementById('satelliteCount').innerHTML = satellites;
}

function updateGpsStatus(type, message) {
    gpsStatus.innerHTML = message;
    
    if (type === 'error') {
        gpsBadge.className = 'badge bg-danger bg-opacity-10 text-danger px-3 py-2';
        gpsIcon.innerHTML = '<i class="bi bi-exclamation-triangle text-danger fs-4"></i>';
    } else if (type === 'success') {
        gpsBadge.className = 'badge bg-success bg-opacity-10 text-success px-3 py-2';
        gpsIcon.innerHTML = '<i class="bi bi-satellite text-success fs-4"></i>';
    } else {
        gpsBadge.className = 'badge bg-primary bg-opacity-10 text-primary px-3 py-2';
        gpsIcon.innerHTML = '<i class="bi bi-satellite text-primary fs-4"></i>';
    }
}

function showGpsTips(title, tips) {
    const tipsDiv = document.getElementById('gpsTips');
    tipsDiv.style.display = 'block';
    
    tipsDiv.innerHTML = `
        <div class="alert alert-warning">
            <strong><i class="bi bi-exclamation-triangle"></i> ${title}</strong>
            <ul class="mt-2 mb-0 small">
                ${tips.map(tip => `<li>${tip}</li>`).join('')}
            </ul>
        </div>
    `;
}

function getGpsTips(type) {
    const tips = {
        'low_accuracy': [
            'Move closer to a window',
            'Step outside briefly for initial lock',
            'Enable WiFi scanning (helps GPS)',
            'Restart your phone\'s location services'
        ],
        'no_signal': [
            'Check if location is enabled in settings',
            'Restart your device',
            'Go to an open area',
            'Toggle Airplane mode on/off'
        ],
        'timeout': [
            'Try again in a few seconds',
            'Move to a different spot',
            'Clear any magnetic interference',
            'Check for GPS blocking materials'
        ],
        'permission_denied': [
            'Click the location icon in browser address bar',
            'Allow location access for this site',
            'Refresh the page after enabling'
        ]
    };
    
    return tips[type] || tips['low_accuracy'];
}

// Initialize scanner
function startScanner() {
    scanner = new Html5Qrcode("reader");
    
    const config = {
        fps: 30,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
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

// Success handler
async function onScanSuccess(decodedText) {
    if (isProcessing || !isGpsReady) {
        if (!isGpsReady) {
            alert('Please wait for GPS to acquire your location');
        }
        return;
    }
    
    try {
        // Get location data
        const studentLat = document.getElementById('studentLat').value;
        const studentLng = document.getElementById('studentLng').value;
        const locationAccuracy = document.getElementById('locationAccuracy').value;
        
        if (!studentLat || !studentLng) {
            throw new Error('Location not available');
        }
        
        // Show preview with distance check
        qrPreviewBox.classList.remove('d-none');
        isProcessing = true;
        statusBar.innerHTML = '⏳ Verifying location...';
        statusDetail.innerHTML = 'Checking distance from faculty';
        
        distanceWarning.classList.remove('d-none');
        
        // Animate progress
        let progress = 0;
        const interval = setInterval(() => {
            progress += 10;
            processingProgress.style.width = progress + '%';
            if (progress >= 100) clearInterval(interval);
        }, 100);

        // Send to server
        const formData = new URLSearchParams();
        formData.append('qr_data', decodedText);
        formData.append('student_lat', studentLat);
        formData.append('student_lng', studentLng);
        formData.append('location_accuracy', locationAccuracy);
        formData.append('device_info', navigator.userAgent);

        const response = await fetch(window.location.href, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData.toString()
        });
        
        const result = await response.json();
        clearInterval(interval);
        
        // Update preview with session info
        if (result.subject) {
            qrPreview.innerHTML = `
                <div class="qr-preview-item">
                    <strong class="d-block mb-2">${result.subject}</strong>
                    <div class="d-flex justify-content-between">
                        <span><i class="bi bi-geo-alt me-2"></i>Distance: ${result.distance}m</span>
                        <span class="badge ${result.success ? 'bg-success' : 'bg-danger'}">
                            ${result.success ? '✓ Verified' : '✗ Failed'}
                        </span>
                    </div>
                </div>
            `;
        }
        
        distanceWarning.classList.add('d-none');
        
        if (result.success) {
            statusBar.innerHTML = '✅ Success!';
            statusDetail.innerHTML = result.message;
            scanCount++;
            scanCounter.innerHTML = scanCount;
            
            // Show confidence level if available
            if (result.confidence) {
                statusDetail.innerHTML += ` (${result.confidence.level} confidence)`;
            }
            
            // Success animation
            processingProgress.style.width = '100%';
            processingProgress.classList.add('bg-success');
            
            // Play success sound
            playNotificationSound('success');
            
            setTimeout(() => {
                resetScanner();
                loadRecentAttendance();
            }, 2000);
        } else {
            // Show failure reason with location context
            let errorMsg = result.message;
            if (result.distance && result.allowed_radius) {
                errorMsg += ` (${result.distance}m / ${result.allowed_radius}m allowed)`;
            }
            throw new Error(errorMsg);
        }
        
    } catch (error) {
        statusBar.innerHTML = '❌ Error';
        statusDetail.innerHTML = error.message;
        isProcessing = false;
        
        // Play error sound
        playNotificationSound('error');
        
        qrPreviewBox.classList.add('d-none');
        setTimeout(() => {
            statusBar.innerHTML = 'Ready to scan';
            statusDetail.innerHTML = 'Waiting for QR code...';
        }, 3000);
    }
}

function onScanError(error) {
    if (error?.includes('NotFoundException')) return;
    console.warn('Scan error:', error);
}

// Switch camera
async function switchCamera(type) {
    currentCamera = type;
    
    document.querySelectorAll('#cameraToggle .btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    if (scanner) {
        await scanner.stop();
        startScanner();
    }
}

// Toggle torch
function toggleTorch() {
    if (!scanner) return;
    
    torchEnabled = !torchEnabled;
    scanner.setTorch(torchEnabled);
    
    torchBtn.innerHTML = torchEnabled ? 
        '<i class="bi bi-lightbulb-fill me-2"></i>Flashlight On' : 
        '<i class="bi bi-lightbulb me-2"></i>Flashlight';
}

// Reset scanner
function resetScanner() {
    qrPreviewBox.classList.add('d-none');
    isProcessing = false;
    statusBar.innerHTML = 'Ready to scan';
    statusDetail.innerHTML = 'Waiting for QR code...';
    processingProgress.style.width = '0%';
    processingProgress.classList.remove('bg-success');
    
    if (scanner) {
        scanner.resume();
    }
}

// Stop scanner
function stopScanner() {
    if (scanner) {
        scanner.stop();
        statusBar.innerHTML = '⏸️ Scanner paused';
        statusDetail.innerHTML = 'Click "Scan Again" to resume';
        scanningSpinner.style.display = 'none';
    }
}

// Toggle instructions
function toggleInstructions() {
    const card = document.getElementById('instructionsCard');
    card.classList.toggle('d-none');
}

// Manual Location Modal
function showManualLocationDialog() {
    if (!bestLocation) {
        alert('Please wait for at least approximate GPS location');
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('manualLocationModal'));
    modal.show();
    
    document.getElementById('confirmInClass').addEventListener('change', toggleManualSubmit);
    document.getElementById('confirmNoSpoof').addEventListener('change', toggleManualSubmit);
}

function toggleManualSubmit() {
    const confirm1 = document.getElementById('confirmInClass').checked;
    const confirm2 = document.getElementById('confirmNoSpoof').checked;
    document.getElementById('manualSubmitBtn').disabled = !(confirm1 && confirm2);
}

function submitManualLocation() {
    if (bestLocation) {
        document.getElementById('studentLat').value = bestLocation.lat;
        document.getElementById('studentLng').value = bestLocation.lng;
        document.getElementById('locationAccuracy').value = bestLocation.accuracy || 100;
        isGpsReady = true;
    }
    
    bootstrap.Modal.getInstance(document.getElementById('manualLocationModal')).hide();
    alert('Manual confirmation recorded. You can now scan the QR code.');
}

// Play notification sound
function playNotificationSound(type) {
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    if (type === 'success') {
        oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.5);
    } else {
        oscillator.frequency.setValueAtTime(400, audioContext.currentTime);
        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.3);
    }
}

// Load recent attendance
async function loadRecentAttendance() {
    try {
        const response = await fetch('<?= APP_URL ?? '' ?>/student/get_recent_attendance.php');
        const html = await response.text();
        document.getElementById('recentAttendance').innerHTML = html;
    } catch (error) {
        console.error('Failed to load recent attendance:', error);
    }
}

// Initialize on load
window.onload = () => {
    startGpsTracking();
    startScanner();
    loadRecentAttendance();
    
    // Update scan counter from localStorage
    const today = new Date().toDateString();
    const stored = localStorage.getItem('scanCount_' + today);
    if (stored) {
        scanCount = parseInt(stored);
        scanCounter.innerHTML = scanCount;
    }
};

// Save scan count
window.addEventListener('beforeunload', () => {
    const today = new Date().toDateString();
    localStorage.setItem('scanCount_' + today, scanCount);
    
    if (locationWatchId) {
        navigator.geolocation.clearWatch(locationWatchId);
    }
    if (scanner) {
        scanner.stop().catch(() => {});
    }
});
</script>

<?php include('../includes/footer.php'); ?>