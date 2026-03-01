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

    if (empty($schedule_id) || empty($date) || empty($session_type)) {
        $message = 'Please select a valid class, date, and session type.';
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
                // Build simple JSON
                $qrData = json_encode([
                    'subject_id' => (int)$selectedSchedule['subject_id'],
                    'year_id' => (int)$selectedSchedule['year_id'],
                    'course_id' => (int)$selectedSchedule['course_id'],
                    'faculty_id' => (int)$faculty_id,
                    'date' => $date,
                    'session_type' => $session_type
                ]);
                
                // Generate QR code
                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=450x450&data=' . urlencode($qrData);
                $qrImage = '<img src="' . $qrUrl . '" class="img-fluid rounded shadow border" alt="QR Code" id="qrCode">';
                
                $message = "Simple QR Code generated successfully!";
                $messageType = 'success';
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

.distance-badge {
    background-color: #e2e3e5;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 0.7rem;
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

/* QR Scanner fix for IndexedDB error */
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

#reader__dashboard_section {
    display: none !important;
}

/* Remove any IndexedDB access attempts */
#reader__status_span {
    display: none !important;
}

/* Fix for camera permissions */
.scanner-container {
    position: relative;
    width: 100%;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
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
                    <h5 class="mb-0"><i class="bi bi-qr-code me-2"></i>Generate Simple QR Code</h5>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                            <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="qrForm">
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
                            <button type="submit" name="generate_qr" id="generateBtn" 
                                    class="btn btn-primary btn-lg px-5">
                                <i class="bi bi-qr-code me-2"></i>Generate QR Code
                            </button>
                        </div>
                    </form>

                    <?php if ($qrImage): ?>
                        <hr class="my-5">
                        <div class="text-center">
                            <h5 class="text-success mb-4">
                                <i class="bi bi-check-circle-fill"></i> 
                                QR Code Generated
                            </h5>
                            <?= $qrImage ?>
                            <div class="mt-3">
                                <small class="text-muted d-block">Scan to mark attendance</small>
                                <small class="text-primary"><i class="bi bi-info-circle"></i> Contains: Subject, Year, Course, Date</small>
                            </div>
                            <button class="btn btn-outline-primary mt-3" onclick="window.print()">
                                <i class="bi bi-printer me-2"></i>Print QR Code
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
                                    <th>Distance</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceBody">
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="empty-state">
                                            <i class="bi bi-camera-reels"></i>
                                            <h6>No attendance records yet</h6>
                                            <p class="small">Generate a QR code and wait for students to scan</p>
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

// Live attendance refresh with better error handling
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
            return response.text(); // Get as text first to check
        })
        .then(text => {
            // Check if response is HTML (error page)
            if (text.trim().startsWith('<')) {
                console.warn('Received HTML instead of JSON:', text.substring(0, 100) + '...');
                throw new Error('Server returned HTML instead of JSON. Please check if ajax/get_live_attendance.php exists and returns JSON.');
            }
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', text.substring(0, 200));
                throw new Error('Invalid JSON response from server');
            }
        })
        .then(data => {
            retryCount = 0; // Reset retry count on success
            
            if (data.success && data.attendance) {
                updateAttendanceTable(data.attendance);
                updateLiveCounter(data.present_count || 0);
                updateLastUpdate('success', 'Updated ' + new Date().toLocaleTimeString());
            } else if (data.error) {
                console.error('Server error:', data.error);
                updateLastUpdate('error', 'Error: ' + data.error);
                
                // If table is empty, show error message
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
                // Will retry on next interval
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
        showEmptyState('No attendance records yet');
        return;
    }

    let html = '';
    attendance.forEach(record => {
        let statusBadge = '';
        let distanceDisplay = 'N/A';
        
        if (record.status === 'Present') {
            statusBadge = '<span class="status-badge status-present"><i class="bi bi-check-circle-fill me-1"></i>Present</span>';
        } else {
            statusBadge = '<span class="status-badge status-late"><i class="bi bi-clock-fill me-1"></i>Late</span>';
        }
        
        if (record.distance_from_faculty) {
            distanceDisplay = `<span class="distance-badge"><i class="bi bi-geo-alt-fill me-1"></i>${record.distance_from_faculty}m</span>`;
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
                <td>${distanceDisplay}</td>
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
                    <i class="bi bi-inbox"></i>
                    <h6>${message}</h6>
                    <p class="small">Waiting for students to scan QR code</p>
                </div>
            </td>
        </tr>
    `;
}

function updateLiveCounter(count) {
    liveCount.textContent = count;
    
    // Animate counter
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

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateClassList();
    
    // Initial load with delay to ensure DOM is ready
    setTimeout(() => {
        refreshLiveAttendance();
    }, 500);
    
    // Set up auto-refresh every 5 seconds
    refreshInterval = setInterval(refreshLiveAttendance, 5000);
    
    // Add event listeners
    dateInput.addEventListener('change', updateClassList);
    subjectFilter.addEventListener('change', updateClassList);
    
    // Clean up interval on page unload
    window.addEventListener('beforeunload', function() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });
});

// Manual refresh function (can be called from console if needed)
function manualRefresh() {
    refreshLiveAttendance();
}
</script>

<?php include('../includes/faculty_footer.php'); ?>