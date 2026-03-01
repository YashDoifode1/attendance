<?php
session_start();
require_once '../config/db.php';
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
        COUNT(CASE WHEN a.status = 'Present' AND a.date = ? THEN 1 END) as present_count
    FROM students s
    LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
    WHERE s.role = 'student'
");
$stmt->execute([$selected_date, $selected_date]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$qrImage = '';
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
        $message = 'Unable to get your location. Please enable location services and try again.';
        $messageType = 'warning';
    } elseif ($location_accuracy > 20) {
        // Check for high accuracy GPS (≤20m)
        $message = "⚠️ Low GPS accuracy detected: ±{$location_accuracy}m. High accuracy GPS (±5m – ±20m) is required for location-based attendance.<br>
                   <small>Please move to an open area, enable high accuracy mode, and try again.</small>";
        $messageType = 'danger';
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
                // Calculate expiry time (e.g., 10 minutes from now)
                $expiry_minutes = 10; // QR code valid for 10 minutes
                $expiry_timestamp = date('Y-m-d H:i:s', strtotime("+{$expiry_minutes} minutes"));
                
                // Generate unique token for this session
                $token = bin2hex(random_bytes(32));
                
                // Calculate end time based on schedule + session duration
                $start_time = $selectedSchedule['start_time'];
                $end_time = $selectedSchedule['end_time'];
                
                // Calculate duration in minutes
                $start = new DateTime($start_time);
                $end = new DateTime($end_time);
                $duration = $start->diff($end);
                $duration_minutes = ($duration->h * 60) + $duration->i;
                
                // Insert attendance session with location data
                $stmt = $pdo->prepare("
                    INSERT INTO attendance_sessions (
                        schedule_id, date, start_time, end_time, 
                        duration_minutes, session_type, expiry_timestamp, token,
                        faculty_lat, faculty_lng, allowed_radius, 
                        location_accuracy, location_captured_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $allowed_radius = 30; // Default 30 meters radius
                
                try {
                    $stmt->execute([
                        $schedule_id,
                        $date,
                        $start_time,
                        $end_time,
                        $duration_minutes,
                        $session_type,
                        $expiry_timestamp,
                        $token,
                        $faculty_lat,
                        $faculty_lng,
                        $allowed_radius,
                        $location_accuracy
                    ]);
                    
                    $session_id = $pdo->lastInsertId();
                    
                    // Build comprehensive QR data for location-based verification
                    $qrData = json_encode([
                        'session_id' => (int)$session_id,
                        'schedule_id' => (int)$schedule_id,
                        'subject_id' => (int)$selectedSchedule['subject_id'],
                        'subject_name' => $selectedSchedule['subject_name'],
                        'year_id' => (int)$selectedSchedule['year_id'],
                        'course_id' => (int)$selectedSchedule['course_id'],
                        'faculty_id' => (int)$faculty_id,
                        'faculty_name' => $faculty['name'],
                        'date' => $date,
                        'session_type' => $session_type,
                        'token' => $token,
                        'expiry' => $expiry_timestamp,
                        'faculty_lat' => (float)$faculty_lat,
                        'faculty_lng' => (float)$faculty_lng,
                        'allowed_radius' => $allowed_radius,
                        'verification_type' => 'location_based',
                        'faculty_accuracy' => (float)$location_accuracy
                    ]);
                    
                    // Generate QR code
                    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=450x450&data=' . urlencode($qrData);
                    $qrImage = '<img src="' . $qrUrl . '" class="img-fluid rounded shadow border" alt="QR Code" id="qrCode">';
                    
                    $message = "✅ High Accuracy GPS Location-based QR Code generated successfully!<br>
                               <strong>GPS Accuracy:</strong> ±{$location_accuracy}m ✓<br>
                               <strong>Allowed Radius:</strong> {$allowed_radius}m from your location<br>
                               <strong>Valid Until:</strong> " . date('h:i A', strtotime($expiry_timestamp));
                    $messageType = 'success';
                    
                } catch (PDOException $e) {
                    $message = 'Database error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    }
}
?>

<style>
/* Live feed animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.live-row {
    animation: fadeIn 0.3s ease;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-present {
    background-color: #d4edda;
    color: #155724;
}

.status-late {
    background-color: #fff3cd;
    color: #856404;
}

.status-failed {
    background-color: #f8d7da;
    color: #721c24;
}

.distance-badge {
    background-color: #e2e3e5;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 0.7rem;
}

.distance-outside {
    background-color: #f8d7da;
    color: #721c24;
}

.distance-within {
    background-color: #d4edda;
    color: #155724;
}

.live-counter-pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.empty-state {
    padding: 40px 20px;
    text-align: center;
    color: #6c757d;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

/* Location indicator */
.location-status {
    font-size: 0.9rem;
    padding: 15px;
    border-radius: 10px;
    background-color: #f8f9fa;
    border-left: 4px solid #6c757d;
    transition: all 0.3s ease;
}

.location-acquired {
    border-left-color: #28a745;
    background-color: #f0fff4;
}

.location-waiting {
    border-left-color: #ffc107;
    background-color: #fff9e6;
}

.location-error {
    border-left-color: #dc3545;
    background-color: #fff5f5;
}

/* GPS Accuracy Indicator */
.accuracy-meter {
    height: 8px;
    background: linear-gradient(to right, #dc3545, #ffc107, #28a745);
    border-radius: 4px;
    margin: 10px 0;
    position: relative;
}

.accuracy-marker {
    width: 4px;
    height: 12px;
    background-color: #000;
    position: absolute;
    top: -2px;
    border-radius: 2px;
    transition: left 0.3s ease;
}

.accuracy-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.85rem;
}

.accuracy-excellent {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.accuracy-good {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}

.accuracy-poor {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Auto-refresh indicator */
.last-update {
    font-size: 0.8rem;
    transition: color 0.3s ease;
}

.last-update.success {
    color: #28a745;
}

.last-update.error {
    color: #dc3545;
}

.location-badge {
    background-color: #e7f5ff;
    color: #0d6efd;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 0.8rem;
}

/* QR Code Container */
#qrCode {
    max-width: 100%;
    height: auto;
    border: 2px solid #dee2e6;
    padding: 10px;
    background: white;
}

/* Loading Spinner */
.gps-spinner {
    width: 1.2rem;
    height: 1.2rem;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="container-fluid py-4">
    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Students</h6>
                    <h2 class="mb-0"><?= $stats['total_students'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Present Today</h6>
                    <h2 class="mb-0 live-counter-pulse" id="presentCount"><?= $stats['present_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
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

    <div class="row g-4">
        <!-- QR Generator Card -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-qr-code me-2"></i>Generate High Accuracy GPS QR Code</h5>
                </div>
                <div class="card-body">
                    <!-- GPS Accuracy Status Card -->
                    <div class="location-status mb-4" id="locationStatus">
                        <div class="d-flex align-items-start">
                            <div class="me-3">
                                <i class="bi bi-satellite fs-2"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">GPS Accuracy Status</h6>
                                <div id="accuracyMessage" class="mb-2">
                                    <span id="locationMessage">Waiting for high accuracy GPS (±5m – ±20m)...</span>
                                    <span class="gps-spinner ms-2" id="locationSpinner"></span>
                                </div>
                                
                                <!-- Accuracy Meter -->
                                <div class="accuracy-meter" id="accuracyMeter">
                                    <div class="accuracy-marker" id="accuracyMarker" style="left: 0%;"></div>
                                </div>
                                
                                <div class="d-flex justify-content-between small text-muted mt-1">
                                    <span>❌ IP Based (>50m)</span>
                                    <span>⚠️ WiFi/Cellular (21-50m)</span>
                                    <span>✅ GPS (5-20m)</span>
                                </div>
                                
                                <!-- Accuracy Badge -->
                                <div class="mt-2 d-flex align-items-center">
                                    <span class="accuracy-badge" id="accuracyBadge">
                                        <i class="bi bi-question-circle me-1"></i>Acquiring GPS...
                                    </span>
                                    <span class="ms-2 small text-muted" id="accuracyValue"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Coordinates Display -->
                        <div class="mt-3 p-2 bg-light rounded" id="coordinatesBox" style="display: none;">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Latitude</small>
                                    <code id="displayLat">0.000000</code>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Longitude</small>
                                    <code id="displayLng">0.000000</code>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                            <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="qrForm" onsubmit="return validateLocation()">
                        <!-- Hidden inputs for location data -->
                        <input type="hidden" name="faculty_lat" id="faculty_lat" value="">
                        <input type="hidden" name="faculty_lng" id="faculty_lng" value="">
                        <input type="hidden" name="location_accuracy" id="location_accuracy" value="">

                        <div class="row g-3">
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
                                    <option value="Lecture">Lecture</option>
                                    <option value="Lab">Lab</option>
                                    <option value="Tutorial">Tutorial</option>
                                </select>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="refreshLocation()">
                                <i class="bi bi-arrow-repeat me-2"></i>Refresh GPS
                            </button>
                            <button type="submit" name="generate_qr" id="generateBtn" 
                                    class="btn btn-primary btn-lg px-5" disabled>
                                <i class="bi bi-qr-code me-2"></i>Generate QR Code
                            </button>
                        </div>
                    </form>

                    <?php if ($qrImage): ?>
                        <hr class="my-5">
                        <div class="text-center">
                            <h5 class="text-success mb-4">
                                <i class="bi bi-check-circle-fill"></i> 
                                High Accuracy GPS QR Code Generated
                            </h5>
                            <?= $qrImage ?>
                            <div class="mt-3">
                                <div class="alert alert-success">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-satellite me-3 fs-2"></i>
                                        <div class="text-start">
                                            <strong>GPS Verification Active:</strong><br>
                                            Students must be within 30 meters of your location<br>
                                            <small class="text-muted">Your GPS Accuracy: ±<?= htmlspecialchars($_POST['location_accuracy'] ?? '0') ?>m ✓</small>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted d-block">Scan to mark attendance with GPS verification</small>
                                <small class="text-primary"><i class="bi bi-geo-alt-fill"></i> Contains your GPS coordinates (High Accuracy)</small>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-outline-primary" onclick="window.print()">
                                    <i class="bi bi-printer me-2"></i>Print QR Code
                                </button>
                                <button class="btn btn-outline-success" onclick="copyQRInfo()">
                                    <i class="bi bi-clipboard me-2"></i>Copy Session Info
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Live Attendance Feed with Location Info -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-broadcast me-2"></i>Live Attendance Feed</h5>
                    <span class="badge bg-light text-dark" id="liveCounter">
                        <i class="bi bi-people-fill me-1"></i> <span id="liveCount">0</span> Present
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:600px; overflow-y:auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>GPS Location</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceBody">
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="empty-state">
                                            <i class="bi bi-satellite"></i>
                                            <h6>Waiting for GPS-based attendance</h6>
                                            <p class="small">Generate a high accuracy QR code and wait for students to scan with GPS enabled</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Auto-refresh indicator -->
                <div class="card-footer bg-white py-2 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="bi bi-arrow-repeat me-1"></i> Auto-refreshing every 5 seconds
                        </small>
                        <span class="badge bg-success bg-opacity-10 text-success last-update" id="lastUpdate">
                            <i class="bi bi-clock me-1"></i> Updating...
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// GPS tracking variables
let watchId = null;
let currentLocation = null;
let locationWatchStarted = false;
let accuracyCheckInterval = null;

// Get faculty location with high accuracy requirements
function startLocationWatch() {
    if (!navigator.geolocation) {
        updateLocationStatus('error', 'Geolocation is not supported by your browser');
        return;
    }

    const locationStatus = document.getElementById('locationStatus');
    const locationMessage = document.getElementById('locationMessage');
    const locationSpinner = document.getElementById('locationSpinner');
    const generateBtn = document.getElementById('generateBtn');
    const coordinatesBox = document.getElementById('coordinatesBox');

    // Update UI to show waiting
    locationStatus.classList.remove('location-acquired', 'location-error');
    locationStatus.classList.add('location-waiting');
    locationMessage.innerHTML = '📡 Requesting high accuracy GPS (target: ±5m – ±20m)...';
    locationSpinner.style.display = 'inline-block';
    generateBtn.disabled = true;
    coordinatesBox.style.display = 'none';

    const options = {
        enableHighAccuracy: true,        // Force GPS
        timeout: 15000,                  // 15 second timeout
        maximumAge: 0                     // Don't use cached positions
    };

    function success(pos) {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        const accuracy = pos.coords.accuracy;
        
        currentLocation = { lat, lng, accuracy };
        
        // Update hidden inputs
        document.getElementById('faculty_lat').value = lat;
        document.getElementById('faculty_lng').value = lng;
        document.getElementById('location_accuracy').value = accuracy;
        
        // Update display coordinates
        document.getElementById('displayLat').textContent = lat.toFixed(6);
        document.getElementById('displayLng').textContent = lng.toFixed(6);
        coordinatesBox.style.display = 'block';
        
        // Update accuracy meter and badge
        updateAccuracyDisplay(accuracy);
        
        // Check if accuracy meets GPS requirements
        if (accuracy <= 20) {
            // Excellent GPS accuracy
            locationStatus.classList.remove('location-waiting', 'location-error');
            locationStatus.classList.add('location-acquired');
            locationMessage.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i> High accuracy GPS acquired!';
            locationSpinner.style.display = 'none';
            generateBtn.disabled = false;
            
            // Update accuracy badge
            const accuracyBadge = document.getElementById('accuracyBadge');
            accuracyBadge.className = 'accuracy-badge accuracy-excellent';
            accuracyBadge.innerHTML = '<i class="bi bi-satellite me-1"></i> High Accuracy GPS ✓';
            
        } else if (accuracy <= 50) {
            // Medium accuracy (WiFi/Cellular)
            locationStatus.classList.remove('location-acquired', 'location-error');
            locationStatus.classList.add('location-waiting');
            locationMessage.innerHTML = '⚠️ Medium accuracy detected. Need better GPS signal...';
            locationSpinner.style.display = 'inline-block';
            generateBtn.disabled = true;
            
            // Update accuracy badge
            const accuracyBadge = document.getElementById('accuracyBadge');
            accuracyBadge.className = 'accuracy-badge accuracy-good';
            accuracyBadge.innerHTML = '<i class="bi bi-wifi me-1"></i> WiFi/Cellular (±' + Math.round(accuracy) + 'm)';
            
        } else {
            // Poor accuracy (IP based)
            locationStatus.classList.remove('location-acquired', 'location-waiting');
            locationStatus.classList.add('location-error');
            locationMessage.innerHTML = '❌ Poor accuracy. Please move to an open area for GPS signal.';
            locationSpinner.style.display = 'none';
            generateBtn.disabled = true;
            
            // Update accuracy badge
            const accuracyBadge = document.getElementById('accuracyBadge');
            accuracyBadge.className = 'accuracy-badge accuracy-poor';
            accuracyBadge.innerHTML = '<i class="bi bi-globe me-1"></i> IP Based (±' + Math.round(accuracy) + 'm)';
        }
        
        console.log('GPS location:', lat, lng, 'accuracy:', accuracy, 'meters');
    }

    function error(err) {
        console.warn('GPS error:', err);
        let message = getLocationErrorMessage(err);
        
        locationStatus.classList.remove('location-acquired', 'location-waiting');
        locationStatus.classList.add('location-error');
        locationMessage.innerHTML = '❌ ' + message;
        locationSpinner.style.display = 'none';
        generateBtn.disabled = true;
        coordinatesBox.style.display = 'none';
        
        // Update accuracy badge
        const accuracyBadge = document.getElementById('accuracyBadge');
        accuracyBadge.className = 'accuracy-badge accuracy-poor';
        accuracyBadge.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> ' + message;
        
        // Show retry button
        const retryBtn = document.createElement('button');
        retryBtn.className = 'btn btn-sm btn-outline-danger mt-2';
        retryBtn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Retry GPS';
        retryBtn.onclick = refreshLocation;
        
        const existingRetry = document.querySelector('.retry-gps-btn');
        if (existingRetry) existingRetry.remove();
        
        retryBtn.classList.add('retry-gps-btn');
        document.getElementById('coordinatesBox').appendChild(retryBtn);
    }

    function getLocationErrorMessage(error) {
        switch(error.code) {
            case error.PERMISSION_DENIED:
                return "GPS access denied. Please enable high accuracy location.";
            case error.POSITION_UNAVAILABLE:
                return "GPS signal unavailable. Move to open area.";
            case error.TIMEOUT:
                return "GPS timeout. Please try again.";
            default:
                return "Unknown GPS error occurred.";
        }
    }

    // Update accuracy display with meter
    function updateAccuracyDisplay(accuracy) {
        const meter = document.getElementById('accuracyMarker');
        const accuracyValue = document.getElementById('accuracyValue');
        
        // Calculate position on meter (0-100%)
        let position = 0;
        if (accuracy <= 20) {
            position = 20; // GPS range
        } else if (accuracy <= 50) {
            position = 50; // WiFi range
        } else if (accuracy <= 100) {
            position = 75; // Cellular range
        } else {
            position = 100; // IP range
        }
        
        meter.style.left = position + '%';
        accuracyValue.textContent = 'Current: ±' + Math.round(accuracy) + 'm';
    }

    // Clear existing watch
    if (watchId) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
    
    // Start watching position with high accuracy
    watchId = navigator.geolocation.watchPosition(success, error, options);
    locationWatchStarted = true;
    
    // Set up accuracy check interval
    if (accuracyCheckInterval) {
        clearInterval(accuracyCheckInterval);
    }
    accuracyCheckInterval = setInterval(() => {
        if (currentLocation) {
            updateAccuracyDisplay(currentLocation.accuracy);
        }
    }, 1000);
}

// Refresh GPS location
function refreshLocation() {
    // Remove retry button if exists
    const retryBtn = document.querySelector('.retry-gps-btn');
    if (retryBtn) retryBtn.remove();
    
    // Reset UI
    const locationMessage = document.getElementById('locationMessage');
    const locationSpinner = document.getElementById('locationSpinner');
    locationMessage.innerHTML = '📡 Refreshing GPS signal...';
    locationSpinner.style.display = 'inline-block';
    
    // Restart GPS watch
    if (watchId) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
    startLocationWatch();
}

// Validate location before form submission
function validateLocation() {
    if (!currentLocation) {
        alert('Please wait for GPS to acquire your location.');
        return false;
    }
    
    if (currentLocation.accuracy > 20) {
        alert('❌ GPS accuracy too low: ±' + Math.round(currentLocation.accuracy) + 'm.\n\nHigh accuracy GPS (±5m – ±20m) is required.\n\nPlease:\n• Move to an open area\n• Enable high accuracy mode\n• Wait for better GPS signal');
        return false;
    }
    
    return true;
}

// Update location status
function updateLocationStatus(status, message) {
    const locationStatus = document.getElementById('locationStatus');
    const locationMessage = document.getElementById('locationMessage');
    
    locationStatus.classList.remove('location-acquired', 'location-waiting', 'location-error');
    locationStatus.classList.add('location-' + status);
    locationMessage.textContent = message;
}

// Schedule filtering
const allSchedules = <?= json_encode($allSchedules) ?>;
const dateInput = document.getElementById('dateInput');
const scheduleSelect = document.getElementById('scheduleSelect');
const noClassMsg = document.getElementById('noClassMsg');
const subjectFilter = document.getElementById('subjectFilter');
const attendanceBody = document.getElementById('attendanceBody');
const liveCount = document.getElementById('liveCount');
const lastUpdate = document.getElementById('lastUpdate');

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

// Live attendance refresh with location info
let refreshInterval;
let isRefreshing = false;
let retryCount = 0;
const MAX_RETRIES = 3;

function refreshLiveAttendance() {
    if (isRefreshing) return;
    
    isRefreshing = true;
    
    // Add timestamp to prevent caching
    const url = 'get_live_attendance.php?t=' + Date.now();
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            if (text.trim().startsWith('<')) {
                console.warn('Received HTML instead of JSON:', text.substring(0, 100) + '...');
                throw new Error('Server returned HTML instead of JSON.');
            }
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', text.substring(0, 200));
                throw new Error('Invalid JSON response from server');
            }
        })
        .then(data => {
            retryCount = 0;
            
            if (data.success && data.attendance) {
                updateAttendanceTable(data.attendance);
                updateLiveCounter(data.present_count || 0);
                updateLastUpdate('success', 'Updated ' + new Date().toLocaleTimeString());
            } else if (data.error) {
                console.error('Server error:', data.error);
                updateLastUpdate('error', 'Error: ' + data.error);
                
                if (!data.attendance || data.attendance.length === 0) {
                    showEmptyState('Server error: ' + data.error);
                }
            }
        })
        .catch(error => {
            console.error('Error refreshing attendance:', error);
            updateLastUpdate('error', 'Connection error');
            
            retryCount++;
            if (retryCount <= MAX_RETRIES) {
                console.log(`Retry ${retryCount}/${MAX_RETRIES} in 2 seconds...`);
            } else {
                showEmptyState('Cannot connect to server. Please refresh the page.');
            }
        })
        .finally(() => {
            isRefreshing = false;
        });
}

function updateAttendanceTable(attendance) {
    if (!attendance || attendance.length === 0) {
        showEmptyState('No GPS-based attendance records yet');
        return;
    }

    let html = '';
    attendance.forEach(record => {
        let statusBadge = '';
        let locationDisplay = '';
        
        if (record.status === 'Present') {
            statusBadge = '<span class="status-badge status-present"><i class="bi bi-check-circle-fill me-1"></i>Present</span>';
        } else {
            statusBadge = '<span class="status-badge status-late"><i class="bi bi-clock-fill me-1"></i>Late</span>';
        }
        
        // Enhanced location display with distance and GPS accuracy
        if (record.distance_from_faculty) {
            const distanceClass = record.distance_from_faculty > 30 ? 'distance-outside' : 'distance-within';
            const distanceIcon = record.distance_from_faculty > 30 ? 'bi-exclamation-triangle-fill' : 'bi-geo-alt-fill';
            locationDisplay = `<span class="${distanceClass}"><i class="bi ${distanceIcon} me-1"></i>${record.distance_from_faculty}m</span>`;
            
            // Show GPS accuracy if available
            if (record.student_location_accuracy) {
                const accuracyClass = record.student_location_accuracy <= 20 ? 'text-success' : 'text-warning';
                locationDisplay += `<br><small class="${accuracyClass}"><i class="bi bi-satellite me-1"></i>GPS: ±${Math.round(record.student_location_accuracy)}m</small>`;
            }
        } else {
            locationDisplay = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> No GPS</span>';
        }
        
        html += `
            <tr class="live-row">
                <td>
                    <div class="d-flex align-items-center">
                        <div class="bg-light rounded-circle p-2 me-2">
                            <i class="bi bi-person-circle text-primary"></i>
                        </div>
                        <strong>${escapeHtml(record.student_name || 'Unknown')}</strong>
                    </div>
                </td>
                <td>${escapeHtml(record.subject_name || 'Unknown')}</td>
                <td>${locationDisplay}</td>
                <td>${statusBadge}</td>
                <td><small class="text-muted"><i class="bi bi-clock me-1"></i>${record.marked_time || 'N/A'}</small></td>
            </tr>
        `;
    });
    
    attendanceBody.innerHTML = html;
}

function showEmptyState(message) {
    attendanceBody.innerHTML = `
        <tr>
            <td colspan="5" class="text-center py-5">
                <div class="empty-state">
                    <i class="bi bi-satellite"></i>
                    <h6>${message}</h6>
                    <p class="small">Students need GPS enabled to mark attendance</p>
                </div>
            </td>
        </tr>
    `;
}

function updateLiveCounter(count) {
    liveCount.textContent = count;
    
    const counter = document.getElementById('liveCounter');
    counter.classList.add('live-counter-pulse');
    setTimeout(() => {
        counter.classList.remove('live-counter-pulse');
    }, 500);
}

function updateLastUpdate(status, message) {
    lastUpdate.innerHTML = `<i class="bi bi-${status === 'success' ? 'check-circle' : 'exclamation-triangle'} me-1"></i> ${message}`;
    lastUpdate.className = `badge bg-${status === 'success' ? 'success' : 'danger'} bg-opacity-10 text-${status === 'success' ? 'success' : 'danger'} last-update`;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function copyQRInfo() {
    if (!currentLocation) {
        alert('No GPS location available');
        return;
    }
    
    const qrInfo = `📍 High Accuracy GPS QR Code
═══════════════════════
📅 Generated: ${new Date().toLocaleString()}
📍 Your Location: ${currentLocation.lat.toFixed(6)}, ${currentLocation.lng.toFixed(6)}
📡 GPS Accuracy: ±${Math.round(currentLocation.accuracy)}m ✓
🎯 Required Radius: 30 meters
⏰ Valid for: 10 minutes

Note: Students must have GPS enabled and be within 30m of your location.`;
    
    navigator.clipboard.writeText(qrInfo).then(() => {
        alert('✅ GPS session info copied to clipboard!');
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateClassList();
    
    // Start high accuracy GPS tracking
    startLocationWatch();
    
    // Initial load with delay to ensure DOM is ready
    setTimeout(() => {
        refreshLiveAttendance();
    }, 500);
    
    // Set up auto-refresh every 5 seconds
    refreshInterval = setInterval(refreshLiveAttendance, 5000);
    
    // Add event listeners
    dateInput.addEventListener('change', updateClassList);
    subjectFilter.addEventListener('change', updateClassList);
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
        if (watchId) {
            navigator.geolocation.clearWatch(watchId);
        }
        if (accuracyCheckInterval) {
            clearInterval(accuracyCheckInterval);
        }
    });
});

// Manual refresh function
function manualRefresh() {
    refreshLiveAttendance();
}
</script>

<?php include('../includes/faculty_footer.php'); ?>