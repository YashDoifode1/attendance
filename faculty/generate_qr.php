<?php
session_start();
require_once '../config/db.php';
require_once '../config/constants.php';
require_once '../includes/functions/security.php';
require_once '../includes/functions/geo_location.php';
require_once '../includes/faculty_header.php';

// Verify faculty authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: ../login.php');
    exit();
}

$faculty_id = $_SESSION['user_id'];

// Fetch faculty name
$stmt = $pdo->prepare("SELECT name FROM students WHERE id = ? AND role = 'faculty'");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

// Default date = today
$selected_date = $_POST['date'] ?? date('Y-m-d');
$selected_subject = $_POST['subject_id'] ?? '';

// Fetch ALL schedules for this faculty
$stmt = $pdo->prepare("
    SELECT s.id, s.day, s.start_time, s.end_time,
           sub.subject_name, sub.id AS subject_id,
           c.course_name, c.id AS course_id,
           y.year_name, y.id AS year_id,
           se.session_name, se.id AS session_id
    FROM schedule s
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN courses c ON s.course_id = c.id
    JOIN years y ON s.year_id = y.id
    JOIN sessions se ON s.session_id = se.id
    WHERE s.faculty_id = ?
    ORDER BY FIELD(s.day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.start_time
");
$stmt->execute([$faculty_id]);
$allSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch subjects for filter dropdown
$stmt = $pdo->prepare("
    SELECT DISTINCT sub.id, sub.subject_name 
    FROM subjects sub 
    JOIN schedule sch ON sub.id = sch.subject_id
    WHERE sch.faculty_id = ? 
    ORDER BY sub.subject_name
");
$stmt->execute([$faculty_id]);
$subjectsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch attendance stats for today
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT s.id) as total_students,
        SUM(CASE WHEN a.status = 'Present' AND a.date = ? THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.failure_reason IS NOT NULL AND a.date = ? THEN 1 ELSE 0 END) as failed_count
    FROM students s
    LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
    WHERE s.role = 'student'
");
$stmt->execute([$selected_date, $selected_date, $selected_date]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$qrImage = '';
$expiryTime = '';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_qr'])) {
    $schedule_id = $_POST['schedule_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $session_type = $_POST['session_type'] ?? '';
    $selected_subject = $_POST['subject_id'] ?? '';
    $faculty_lat = $_POST['faculty_lat'] ?? '';
    $faculty_lng = $_POST['faculty_lng'] ?? '';
    $location_accuracy = $_POST['location_accuracy'] ?? '';

    if (empty($schedule_id) || empty($date) || empty($session_type)) {
        $message = 'Please select a valid class, date, and session type.';
        $messageType = 'danger';
    } elseif (empty($faculty_lat) || empty($faculty_lng)) {
        $message = 'Location could not be captured. Please enable GPS and try again.';
        $messageType = 'danger';
    } else {
        // Validate coordinates
        if (!validateCoordinates($faculty_lat, $faculty_lng)) {
            $message = 'Invalid location coordinates detected.';
            $messageType = 'danger';
        } else {
            // Check rate limit for QR generation
            $identifier = $_SERVER['REMOTE_ADDR'] . '_' . $faculty_id;
            if (!checkRateLimit($pdo, $identifier, 'QR_GENERATE', RATE_LIMIT_QR_GENERATE, 60)) {
                $message = 'Too many QR generation attempts. Please try again later.';
                $messageType = 'warning';
            } else {
                // Get selected schedule details
                $stmt = $pdo->prepare("SELECT * FROM schedule s
                                       JOIN subjects sub ON s.subject_id = sub.id
                                       JOIN courses c ON s.course_id = c.id
                                       JOIN years y ON s.year_id = y.id
                                       JOIN sessions se ON s.session_id = se.id
                                       WHERE s.id = ? AND s.faculty_id = ?");
                $stmt->execute([$schedule_id, $faculty_id]);
                $selectedSchedule = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$selectedSchedule) {
                    $message = 'Invalid class selected.';
                    $messageType = 'danger';
                } else {
                    $classDay = $selectedSchedule['day'];
                    $selectedDayName = date('l', strtotime($date));
                    
                    if ($selectedDayName !== $classDay) {
                        $message = "This class is only on {$classDay}s. Selected date is {$selectedDayName}.";
                        $messageType = 'warning';
                    } else {
                        // Set radius based on session type
                        $allowed_radius = match($session_type) {
                            'Lab' => DEFAULT_RADIUS_LAB,
                            'Exam' => DEFAULT_RADIUS_EXAM,
                            default => DEFAULT_RADIUS_LECTURE
                        };

                        // Check if session already exists
                        $stmt = $pdo->prepare("SELECT * FROM attendance_sessions WHERE schedule_id = ? AND date = ?");
                        $stmt->execute([$schedule_id, $date]);
                        $existing = $stmt->fetch();

                        if ($existing) {
                            $session_id = $existing['id'];
                            $token = $existing['token'];
                            $expiry = $existing['expiry_timestamp'];
                            
                            // Update location data if not set
                            if (is_null($existing['faculty_lat'])) {
                                $stmt = $pdo->prepare("
                                    UPDATE attendance_sessions 
                                    SET faculty_lat = ?, faculty_lng = ?, allowed_radius = ?,
                                        location_captured_at = NOW(), location_accuracy = ?
                                    WHERE id = ?
                                ");
                                $stmt->execute([$faculty_lat, $faculty_lng, $allowed_radius, $location_accuracy, $session_id]);
                            }
                            
                            $message = "QR already exists for this class. Reusing it.";
                            $messageType = 'info';
                        } else {
                            // Create new session with location data
                            $duration = round((strtotime($selectedSchedule['end_time']) - strtotime($selectedSchedule['start_time'])) / 60);
                            $expiry = date('Y-m-d H:i:s', time() + (QR_EXPIRY_MINUTES * 60));
                            $token = generateSecureToken();

                            $stmt = $pdo->prepare("
                                INSERT INTO attendance_sessions 
                                (schedule_id, date, start_time, end_time, duration_minutes, 
                                 session_type, expiry_timestamp, token, faculty_lat, faculty_lng, 
                                 allowed_radius, location_accuracy, location_captured_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            
                            $stmt->execute([
                                $schedule_id, $date,
                                $selectedSchedule['start_time'], $selectedSchedule['end_time'],
                                $duration, $session_type, $expiry, $token,
                                $faculty_lat, $faculty_lng, $allowed_radius, $location_accuracy
                            ]);
                            
                            $session_id = $pdo->lastInsertId();

                            $message = "QR Code generated successfully with location lock!";
                            $messageType = 'success';
                        }

                        // Build QR data (minimal, secure)
                        $qrData = json_encode([
                            'sid' => $session_id,
                            'tok' => substr($token, 0, 16), // First 16 chars of token
                            'exp' => strtotime($expiry),
                            'ins' => INSTITUTION_ID
                        ]);

                        // Encrypt QR data for additional security
                        $encrypted = openssl_encrypt(
                            $qrData,
                            QR_ENCRYPTION_ALGO,
                            SECRET_KEY,
                            0,
                            substr(hash('sha256', SECRET_KEY), 0, 16)
                        );
                        
                        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=450x450&data=' . urlencode($encrypted);
                        $qrImage = '<img src="' . $qrUrl . '" class="img-fluid rounded shadow border" alt="QR Code" id="qrCode">';
                        $expiryTime = strtotime($expiry);
                    }
                }
            }
        }
    }
}
?>

<style>
.gps-status-container {
    transition: all 0.3s ease;
}
.gps-progress {
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}
.gps-indicator-card {
    transition: transform 0.2s;
}
.gps-indicator-card:hover {
    transform: translateY(-2px);
}
.accuracy-excellent { color: #28a745; }
.accuracy-good { color: #17a2b8; }
.accuracy-fair { color: #ffc107; }
.accuracy-poor { color: #dc3545; }
.tip-item {
    padding: 8px;
    border-left: 3px solid #17a2b8;
    background: #f8f9fa;
    margin-bottom: 5px;
    border-radius: 0 4px 4px 0;
}
</style>

<!-- Enhanced GPS Status Section -->
<div class="container-fluid py-4">
    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Students</h6>
                    <h2 class="mb-0"><?= $stats['total_students'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Present Today</h6>
                    <h2 class="mb-0"><?= $stats['present_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">Failed Location</h6>
                    <h2 class="mb-0"><?= $stats['failed_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Attendance Rate</h6>
                    <h2 class="mb-0">
                        <?php 
                        $rate = ($stats['total_students'] ?? 0) > 0 
                            ? round(($stats['present_count'] ?? 0) / $stats['total_students'] * 100, 1)
                            : 0;
                        echo $rate . '%';
                        ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced GPS Status Section -->
    <div class="row mb-3">
        <div class="col-12">
            <div id="locationStatus" class="alert alert-info mb-3 gps-status-container">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span>📍 Initializing GPS... Please wait.</span>
                </div>
            </div>
            
            <div id="locationDetails" class="small text-muted mb-2"></div>
            <div id="accuracyTips" style="display: none;"></div>
            
            <!-- Progress bar for GPS acquisition -->
            <div class="progress gps-progress mb-3" style="height: 8px;">
                <div id="gpsProgress" class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                     style="width: 0%;"></div>
            </div>
            
            <!-- GPS status indicators -->
            <div class="row g-2 mb-3" id="gpsIndicators" style="display: none;">
                <div class="col-md-3 col-6">
                    <div class="card gps-indicator-card bg-light">
                        <div class="card-body p-2 text-center">
                            <small class="text-muted d-block">Signal Quality</small>
                            <span id="signalQuality" class="h4">⚪</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card gps-indicator-card bg-light">
                        <div class="card-body p-2 text-center">
                            <small class="text-muted d-block">Accuracy</small>
                            <span id="accuracyDisplay" class="h5">--</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card gps-indicator-card bg-light">
                        <div class="card-body p-2 text-center">
                            <small class="text-muted d-block">Status</small>
                            <span id="gpsMode" class="h5">⚪</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card gps-indicator-card bg-light">
                        <div class="card-body p-2 text-center">
                            <small class="text-muted d-block">Updates</small>
                            <span id="updateCount" class="h5">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- QR Generator Card -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">📍 Generate Location-Locked QR Code</h5>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                            <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="qrForm" onsubmit="return validateLocation()">
                        <input type="hidden" name="faculty_lat" id="faculty_lat">
                        <input type="hidden" name="faculty_lng" id="faculty_lng">
                        <input type="hidden" name="location_accuracy" id="location_accuracy">
                        
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Select Date</label>
                                <input type="date" name="date" id="dateInput" class="form-control form-control-lg" 
                                       value="<?= htmlspecialchars($selected_date) ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Subject Filter</label>
                                <select name="subject_id" id="subjectFilter" class="form-select form-select-lg">
                                    <option value="">All Subjects</option>
                                    <?php foreach ($subjectsList as $sub): ?>
                                        <option value="<?= $sub['id'] ?>" <?= $selected_subject == $sub['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sub['subject_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label fw-bold">Available Classes</label>
                                <select name="schedule_id" id="scheduleSelect" class="form-select form-select-lg" required>
                                    <option value="">-- Select date first --</option>
                                </select>
                                <div id="noClassMsg" class="text-muted small mt-2" style="display:none;">
                                    No classes scheduled on this date.
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold">Session Type</label>
                                <select name="session_type" id="sessionType" class="form-select form-select-lg" required>
                                    <option value="">-- Type --</option>
                                    <option value="Lecture">Lecture (30m radius)</option>
                                    <option value="Lab">Lab (40m radius)</option>
                                    <option value="Exam">Exam (60m radius)</option>
                                </select>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" name="generate_qr" id="generateBtn" 
                                    class="btn btn-primary btn-lg px-5" disabled>
                                Generate Location-Locked QR
                            </button>
                        </div>
                    </form>

                    <?php if ($qrImage): ?>
                        <hr class="my-5">
                        <div class="text-center">
                            <h5 class="text-success mb-4">
                                <i class="bi bi-geo-alt-fill"></i> 
                                QR Code Locked to Your Location
                            </h5>
                            <?= $qrImage ?>
                            <div id="expiryCountdown" class="mt-4 fw-bold fs-5 text-muted"></div>
                            <div class="mt-3 text-success">
                                <small>📍 Students must be within radius to mark attendance</small>
                            </div>
                            <button class="btn btn-outline-primary mt-3" onclick="window.print()">
                                Print QR Code
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Live Attendance Feed -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">📊 Live Attendance Feed</h5>
                    <span class="badge bg-light text-dark" id="liveCounter">0 Present</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height:600px; overflow-y:auto;">
                        <table class="table table-hover align-middle" id="liveAttendanceTable">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Distance</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceBody">
                                <!-- Updated via AJAX -->
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// ENHANCED GPS TRACKING SYSTEM
// ============================================

let locationWatchId = null;
let bestLocation = null;
let locationAttempts = 0;
let locationTimeout = null;
let gpsStartTime = Date.now();

// GPS Quality thresholds from PHP
const GPS_THRESHOLDS = {
    EXCELLENT: <?= GPS_ACCURACY_EXCELLENT ?>,
    GOOD: <?= GPS_ACCURACY_GOOD ?>,
    FAIR: <?= GPS_ACCURACY_FAIR ?>,
    POOR: <?= GPS_ACCURACY_POOR ?>
};

function startLocationTracking() {
    if (!navigator.geolocation) {
        showLocationError('Geolocation is not supported by your browser');
        return;
    }

    const options = {
        enableHighAccuracy: true,
        timeout: <?= GPS_TIMEOUT_MS ?>,
        maximumAge: <?= GPS_MAX_AGE_MS ?>
    };

    // Show tracking status
    updateLocationStatus('info', '📍 Acquiring GPS signal... This may take a few seconds.');
    
    // Show progress bar
    startProgressBar();
    
    // Use watchPosition to get continuous updates (accuracy improves over time)
    locationWatchId = navigator.geolocation.watchPosition(
        locationSuccess,
        locationError,
        options
    );
    
    // Set a timeout to stop trying if we can't get good accuracy
    locationTimeout = setTimeout(() => {
        if (locationWatchId) {
            // If we have any location at all, use the best we got
            if (bestLocation) {
                useBestAvailableLocation();
            } else {
                // Try fallback without high accuracy
                navigator.geolocation.getCurrentPosition(
                    fallbackLocationSuccess,
                    fallbackLocationError,
                    { enableHighAccuracy: false, timeout: 5000 }
                );
            }
        }
    }, 20000); // 20 seconds max wait
}

function startProgressBar() {
    const progressBar = document.getElementById('gpsProgress');
    let width = 0;
    const interval = setInterval(() => {
        if (width >= 100 || bestLocation?.accuracy <= GPS_THRESHOLDS.GOOD) {
            clearInterval(interval);
            return;
        }
        width += 1;
        progressBar.style.width = width + '%';
    }, 200);
}

function locationSuccess(position) {
    const accuracy = position.coords.accuracy;
    const timestamp = position.timestamp;
    
    // Show GPS indicators
    document.getElementById('gpsIndicators').style.display = 'flex';
    
    // Update update count
    locationAttempts++;
    document.getElementById('updateCount').textContent = locationAttempts;
    
    // Update signal quality indicator
    updateSignalQuality(accuracy);
    
    // Track the best location (highest accuracy = lowest number)
    if (!bestLocation || accuracy < bestLocation.accuracy) {
        bestLocation = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            accuracy: accuracy,
            timestamp: timestamp
        };
        
        updateLocationDisplay(bestLocation);
        
        // Update progress bar based on accuracy
        updateProgressFromAccuracy(accuracy);
        
        // If accuracy is good enough, we can proceed
        if (accuracy <= GPS_THRESHOLDS.GOOD) {
            useThisLocation(bestLocation, 'excellent');
        } else if (accuracy <= GPS_THRESHOLDS.FAIR && locationAttempts > 3) {
            // If we've tried multiple times and accuracy is fair, accept it
            useThisLocation(bestLocation, 'fair');
        } else if (accuracy <= GPS_THRESHOLDS.POOR && locationAttempts > 5) {
            // After many attempts, accept poor accuracy with warning
            useThisLocation(bestLocation, 'poor');
        }
    }
}

function updateSignalQuality(accuracy) {
    const signalEl = document.getElementById('signalQuality');
    const gpsModeEl = document.getElementById('gpsMode');
    
    if (accuracy <= GPS_THRESHOLDS.EXCELLENT) {
        signalEl.innerHTML = '🟢 Excellent';
        signalEl.className = 'h4 accuracy-excellent';
        gpsModeEl.innerHTML = '🟢 High';
    } else if (accuracy <= GPS_THRESHOLDS.GOOD) {
        signalEl.innerHTML = '🔵 Good';
        signalEl.className = 'h4 accuracy-good';
        gpsModeEl.innerHTML = '🔵 Good';
    } else if (accuracy <= GPS_THRESHOLDS.FAIR) {
        signalEl.innerHTML = '🟡 Fair';
        signalEl.className = 'h4 accuracy-fair';
        gpsModeEl.innerHTML = '🟡 Medium';
    } else {
        signalEl.innerHTML = '🔴 Poor';
        signalEl.className = 'h4 accuracy-poor';
        gpsModeEl.innerHTML = '🔴 Low';
    }
}

function updateProgressFromAccuracy(accuracy) {
    const progressBar = document.getElementById('gpsProgress');
    let progress = 0;
    
    if (accuracy <= GPS_THRESHOLDS.EXCELLENT) {
        progress = 100;
        progressBar.className = 'progress-bar bg-success';
    } else if (accuracy <= GPS_THRESHOLDS.GOOD) {
        progress = 80;
        progressBar.className = 'progress-bar bg-info';
    } else if (accuracy <= GPS_THRESHOLDS.FAIR) {
        progress = 60;
        progressBar.className = 'progress-bar bg-warning';
    } else if (accuracy <= GPS_THRESHOLDS.POOR) {
        progress = 40;
        progressBar.className = 'progress-bar bg-danger';
    } else {
        progress = 20;
    }
    
    progressBar.style.width = progress + '%';
}

function locationError(error) {
    console.warn('Location error:', error);
    
    let errorMessage = '';
    let tips = [];
    
    switch(error.code) {
        case error.PERMISSION_DENIED:
            errorMessage = 'Location access denied';
            tips = [
                'Click the location icon in your browser address bar',
                'Allow location access for this site',
                'Check system location settings'
            ];
            break;
        case error.POSITION_UNAVAILABLE:
            errorMessage = 'Location unavailable';
            tips = [
                'Move to an area with better GPS signal',
                'Go near a window',
                'Enable WiFi (helps GPS accuracy)'
            ];
            break;
        case error.TIMEOUT:
            errorMessage = 'Location request timed out';
            tips = [
                'Try again in a few seconds',
                'Restart your device\'s location services',
                'Toggle Airplane mode on/off'
            ];
            break;
    }
    
    // Don't show error immediately - try fallback
    if (locationAttempts > 3) {
        showLocationError(errorMessage, tips);
        
        // Try without high accuracy as fallback
        navigator.geolocation.getCurrentPosition(
            fallbackLocationSuccess,
            fallbackLocationError,
            { enableHighAccuracy: false, timeout: 5000 }
        );
    }
}

function fallbackLocationSuccess(position) {
    // Use less accurate but still useful location
    const accuracy = position.coords.accuracy;
    
    if (accuracy <= GPS_THRESHOLDS.POOR * 1.5) {
        useThisLocation({
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            accuracy: accuracy,
            timestamp: position.timestamp
        }, 'fallback');
    } else {
        showLocationError(
            'GPS signal is weak',
            [
                'Move near a window',
                'Step outside briefly',
                'Toggle WiFi/Bluetooth on (helps GPS)',
                'Wait 10 seconds and try again'
            ]
        );
    }
}

function fallbackLocationError(error) {
    showLocationError(
        'Unable to get your location',
        [
            'Location services are enabled',
            'You\'re not in a basement/underground',
            'No GPS blocking materials nearby',
            'Try again in a few minutes'
        ]
    );
}

function useThisLocation(location, mode) {
    // Clear timeout and watch
    if (locationTimeout) clearTimeout(locationTimeout);
    if (locationWatchId) navigator.geolocation.clearWatch(locationWatchId);
    
    // Update form fields
    document.getElementById('faculty_lat').value = location.lat;
    document.getElementById('faculty_lng').value = location.lng;
    document.getElementById('location_accuracy').value = location.accuracy;
    
    // Enable generate button
    document.getElementById('generateBtn').disabled = false;
    
    // Show success message with appropriate styling
    let statusClass = 'success';
    let qualityText = '';
    
    if (location.accuracy <= GPS_THRESHOLDS.EXCELLENT) {
        qualityText = 'Excellent signal';
        statusClass = 'success';
    } else if (location.accuracy <= GPS_THRESHOLDS.GOOD) {
        qualityText = 'Good signal';
        statusClass = 'success';
    } else if (location.accuracy <= GPS_THRESHOLDS.FAIR) {
        qualityText = 'Fair signal (acceptable)';
        statusClass = 'warning';
    } else {
        qualityText = 'Poor signal (may affect students)';
        statusClass = 'warning';
    }
    
    updateLocationStatus(statusClass, 
        `✅ Location locked! Accuracy: ${location.accuracy.toFixed(1)}m - ${qualityText}`
    );
    
    // Show additional tips if accuracy is borderline
    if (location.accuracy > GPS_THRESHOLDS.FAIR) {
        showAccuracyTips(location.accuracy);
    }
    
    // Complete progress bar
    document.getElementById('gpsProgress').style.width = '100%';
    document.getElementById('gpsProgress').className = 'progress-bar bg-success';
}

function useBestAvailableLocation() {
    if (bestLocation) {
        useThisLocation(bestLocation, 'best-available');
    }
}

function updateLocationDisplay(location) {
    const accuracyDisplay = document.getElementById('accuracyDisplay');
    if (accuracyDisplay) {
        accuracyDisplay.textContent = location.accuracy.toFixed(1) + 'm';
    }
}

function updateLocationStatus(type, message) {
    const statusDiv = document.getElementById('locationStatus');
    
    if (!statusDiv) return;
    
    let bgClass = 'alert-info';
    let icon = '📍';
    
    if (type === 'success') {
        bgClass = 'alert-success';
        icon = '✅';
    } else if (type === 'warning') {
        bgClass = 'alert-warning';
        icon = '⚠️';
    } else if (type === 'error') {
        bgClass = 'alert-danger';
        icon = '❌';
    } else {
        bgClass = 'alert-info';
        icon = '⏳';
    }
    
    statusDiv.className = `alert ${bgClass} mb-3 gps-status-container`;
    statusDiv.innerHTML = `<strong>${icon}</strong> ${message}`;
}

function showLocationError(message, bulletPoints = []) {
    const statusDiv = document.getElementById('locationStatus');
    const generateBtn = document.getElementById('generateBtn');
    
    if (statusDiv) {
        let html = `<strong>❌</strong> ${message}`;
        
        if (bulletPoints.length > 0) {
            html += '<div class="mt-3"><strong>Tips:</strong><ul class="mt-2 mb-0">';
            bulletPoints.forEach(point => {
                html += `<li class="tip-item">${point}</li>`;
            });
            html += '</ul></div>';
        }
        
        statusDiv.className = 'alert alert-danger mb-3 gps-status-container';
        statusDiv.innerHTML = html;
    }
    
    if (generateBtn) generateBtn.disabled = true;
}

function showAccuracyTips(accuracy) {
    const tipsDiv = document.getElementById('accuracyTips');
    if (!tipsDiv) return;
    
    let tips = [];
    
    if (accuracy > GPS_THRESHOLDS.FAIR) {
        tips = [
            '🪟 Move closer to a window for better signal',
            '📱 Hold phone away from your body',
            '📡 Disable any GPS spoofing apps',
            '🔄 Toggle Airplane mode on/off to reset GPS'
        ];
        
        tipsDiv.innerHTML = `
            <div class="alert alert-info mt-2">
                <strong>💡 Tips to improve GPS for students:</strong>
                <ul class="mb-0 small">
                    ${tips.map(tip => `<li>${tip}</li>`).join('')}
                </ul>
            </div>
        `;
        tipsDiv.style.display = 'block';
    } else {
        tipsDiv.style.display = 'none';
    }
}

function validateLocation() {
    const accuracy = parseFloat(document.getElementById('location_accuracy').value);
    const sessionType = document.getElementById('sessionType').value;
    
    if (!accuracy) {
        alert('Please wait for GPS to acquire your location');
        return false;
    }
    
    // Get required accuracy based on session type
    let requiredAccuracy;
    switch(sessionType) {
        case 'Exam':
            requiredAccuracy = <?= GPS_REQUIRED_ACCURACY_EXAM ?>;
            break;
        case 'Lab':
            requiredAccuracy = <?= GPS_REQUIRED_ACCURACY_LAB ?>;
            break;
        default:
            requiredAccuracy = <?= GPS_REQUIRED_ACCURACY_LECTURE ?>;
    }
    
    // More lenient for lectures, stricter for exams
    if (accuracy > requiredAccuracy) {
        const message = `GPS accuracy (${accuracy.toFixed(1)}m) is higher than recommended (${requiredAccuracy}m) for ${sessionType}. 
                        ${sessionType === 'Exam' ? 'This is required for exam security.' : 'You can still proceed, but students may have issues.'}
                        \n\nDo you want to continue?`;
        
        return confirm(message);
    }
    
    return true;
}

// Your existing schedule filtering code
const allSchedules = <?= json_encode($allSchedules) ?>;
const dateInput = document.getElementById('dateInput');
const scheduleSelect = document.getElementById('scheduleSelect');
const noClassMsg = document.getElementById('noClassMsg');
const subjectFilter = document.getElementById('subjectFilter');

function updateClassList() {
    const selectedDate = dateInput.value;
    const selectedSubject = subjectFilter.value;
    if (!selectedDate) {
        scheduleSelect.innerHTML = '<option value="">-- Select date first --</option>';
        noClassMsg.style.display = 'none';
        return;
    }

    const selectedDayName = new Date(selectedDate).toLocaleString('en-us', { weekday: 'long' });

    let matching = allSchedules.filter(s => s.day === selectedDayName);
    if (selectedSubject) {
        matching = matching.filter(s => s.subject_id == selectedSubject);
    }

    scheduleSelect.innerHTML = '';
    if (matching.length === 0) {
        scheduleSelect.innerHTML = '<option value="">No classes on this date / subject</option>';
        noClassMsg.style.display = 'block';
        scheduleSelect.disabled = true;
    } else {
        scheduleSelect.disabled = false;
        noClassMsg.style.display = 'none';
        scheduleSelect.innerHTML = '<option value="">-- Select class --</option>';
        matching.forEach(sched => {
            const opt = document.createElement('option');
            opt.value = sched.id;
            opt.textContent = `${sched.subject_name} (${sched.course_name} - ${sched.year_name}) | ${sched.start_time} - ${sched.end_time}`;
            scheduleSelect.appendChild(opt);
        });

        if (matching.length === 1) {
            scheduleSelect.value = matching[0].id;
        }
    }
}

// Live attendance refresh
function refreshLiveAttendance() {
    fetch('ajax/get_live_attendance.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('attendanceBody');
            const counter = document.getElementById('liveCounter');
            
            if (data.attendance && data.attendance.length > 0) {
                let html = '';
                data.attendance.forEach(record => {
                    let statusBadge = '';
                    let distanceDisplay = '';
                    
                    if (record.status === 'Present') {
                        statusBadge = '<span class="badge bg-success">Present</span>';
                    } else if (record.status === 'Absent' && record.failure_reason) {
                        statusBadge = '<span class="badge bg-danger" title="' + record.failure_reason + '">Failed</span>';
                    } else {
                        statusBadge = '<span class="badge bg-warning text-dark">Late</span>';
                    }
                    
                    if (record.distance_from_faculty) {
                        distanceDisplay = record.distance_from_faculty + 'm';
                        if (record.distance_from_faculty > record.allowed_radius) {
                            distanceDisplay += ' ⚠️';
                        }
                    } else {
                        distanceDisplay = 'N/A';
                    }
                    
                    html += `<tr>
                        <td>${escapeHtml(record.student_name)}</td>
                        <td>${escapeHtml(record.subject_name)}</td>
                        <td>${distanceDisplay}</td>
                        <td>${statusBadge}</td>
                        <td>${record.marked_time || 'N/A'}</td>
                    </tr>`;
                });
                tbody.innerHTML = html;
                counter.textContent = data.present_count + ' Present';
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No attendance records yet</td></tr>';
                counter.textContent = '0 Present';
            }
        })
        .catch(error => {
            console.error('Error refreshing attendance:', error);
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    startLocationTracking();
    updateClassList();
    refreshLiveAttendance();
    setInterval(refreshLiveAttendance, 5000);
});

dateInput.addEventListener('change', updateClassList);
subjectFilter.addEventListener('change', updateClassList);

// Cleanup
window.addEventListener('beforeunload', function() {
    if (locationWatchId) {
        navigator.geolocation.clearWatch(locationWatchId);
    }
    if (locationTimeout) {
        clearTimeout(locationTimeout);
    }
});

<?php if ($expiryTime): ?>
const expiry = <?= $expiryTime ?> * 1000;
const countdownEl = document.getElementById('expiryCountdown');
function updateCountdown() {
    const remaining = expiry - Date.now();
    if (remaining <= 0) {
        countdownEl.innerHTML = '<span class="text-danger">QR Expired! Generate a new one.</span>';
        document.getElementById('qrCode').style.opacity = '0.5';
        return;
    }
    const mins = Math.floor(remaining / 60000);
    const secs = Math.floor((remaining % 60000) / 1000);
    countdownEl.textContent = `Expires in ${mins}m ${secs}s`;
    setTimeout(updateCountdown, 1000);
}
updateCountdown();
<?php endif; ?>
</script>

<?php include('../includes/faculty_footer.php'); ?>