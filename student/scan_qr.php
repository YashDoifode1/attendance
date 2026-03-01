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
            
            // Required fields validation
            $required = ['subject_id', 'year_id', 'course_id', 'faculty_id', 'date', 'session_type'];
            foreach ($required as $f) {
                if (empty($qr_data[$f])) throw new Exception("Invalid QR code: missing $f");
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
            
            // Get schedule details to verify if this subject belongs to student's course/year
            $stmt = $pdo->prepare("
                SELECT s.id 
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
            
            if (!$validSchedule && $isEnrolled) {
                // This means the QR code might be for a different subject in same course/year
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
            $details = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'already_marked' => !empty($already_marked),
                'is_enrolled' => $isEnrolled,
                'details' => [
                    'subject' => $details['subject_name'] ?? 'Unknown Subject',
                    'faculty' => $details['faculty_name'] ?? 'Unknown Faculty',
                    'course' => $details['course_name'] ?? 'Unknown Course',
                    'year' => $details['year_name'] ?? 'Unknown Year',
                    'date' => date('d M Y', strtotime($qr_data['date'])),
                    'session_type' => $qr_data['session_type'],
                    'subject_id' => $qr_data['subject_id'],
                    'faculty_id' => $qr_data['faculty_id'],
                    'course_id' => $qr_data['course_id'],
                    'year_id' => $qr_data['year_id']
                ]
            ]);
            exit;
        }
        
        if ($_POST['action'] === 'mark_attendance') {
            $qr_data = json_decode($_POST['qr_data'], true);
            
            if (!$qr_data || !is_array($qr_data)) {
                throw new Exception('Invalid QR code format');
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
                throw new Exception("You are not enrolled in this course. This QR code is for " . 
                                   $qr_data['course_id'] . "/" . $qr_data['year_id'] . 
                                   " but you are enrolled in " . $student['course_id'] . "/" . $student['year_id']);
            }
            
            // Verify the subject belongs to student's curriculum
            $stmt = $pdo->prepare("
                SELECT id FROM schedule 
                WHERE subject_id = ? AND course_id = ? AND year_id = ?
                LIMIT 1
            ");
            $stmt->execute([
                $qr_data['subject_id'],
                $qr_data['course_id'],
                $qr_data['year_id']
            ]);
            
            if (!$stmt->fetch()) {
                throw new Exception("This subject is not part of your curriculum");
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
            
            // Get schedule_id for this class
            $stmt = $pdo->prepare("
                SELECT id FROM schedule 
                WHERE subject_id = ? AND faculty_id = ? AND course_id = ? AND year_id = ?
                LIMIT 1
            ");
            $stmt->execute([
                $qr_data['subject_id'],
                $qr_data['faculty_id'],
                $qr_data['course_id'],
                $qr_data['year_id']
            ]);
            $schedule = $stmt->fetch();
            
            if (!$schedule) {
                throw new Exception("Invalid class schedule");
            }
            
            // Insert attendance
            $stmt = $pdo->prepare("
                INSERT INTO attendance (
                    student_id, schedule_id, subjects_id, date, status, 
                    faculty_id, course_id, year_id, created_at
                ) VALUES (?, ?, ?, ?, 'Present', ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $student_id,
                $schedule['id'],
                $qr_data['subject_id'],
                $qr_data['date'],
                $qr_data['faculty_id'],
                $qr_data['course_id'],
                $qr_data['year_id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Attendance marked successfully! ✅'
            ]);
            exit;
        }
        
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
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
                        <li>Review lecture details before marking attendance</li>
                        <li>You can only mark attendance for your enrolled courses</li>
                        <li>You can only mark attendance once per subject per day</li>
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

                    <!-- Status Area -->
                    <div class="status-area p-3 rounded-3 bg-light mb-4">
                        <div class="d-flex align-items-center">
                            <div class="spinner-grow spinner-grow-sm text-primary me-3" id="scanningSpinner"></div>
                            <div class="flex-grow-1">
                                <div id="statusBar" class="fw-medium">Ready to scan</div>
                                <small class="text-muted" id="statusDetail">Position QR code in frame</small>
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

                            <!-- Enrollment Status -->
                            <div id="enrollmentStatus" class="mt-3 p-3 rounded-3 d-none">
                                <i class="bi me-2"></i>
                                <span id="enrollmentMessage"></span>
                            </div>

                            <!-- Already Marked Message -->
                            <div id="alreadyMarkedMsg" class="alert alert-success mt-3 mb-0 d-none">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                You have already marked attendance for this lecture ✓
                            </div>

                            <!-- Mark Attendance Button -->
                            <div class="text-center mt-4" id="markBtnContainer">
                                <button class="btn btn-success btn-lg px-5" onclick="markAttendance()" id="markBtn">
                                    <i class="bi bi-check2-circle me-2"></i>
                                    Mark Attendance
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
                                    <small class="text-muted">You can now close this window</small>
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
                            <i class="bi bi-person-check me-1"></i>
                            Enrolled: <?= htmlspecialchars($student['course_name'] ?? 'N/A') ?>
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

#lectureDetailsCard, #notEnrolledCard {
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

// DOM Elements
const statusBar = document.getElementById('statusBar');
const statusDetail = document.getElementById('statusDetail');
const scanningSpinner = document.getElementById('scanningSpinner');
const scanCounter = document.getElementById('scanCounter');
const lectureDetailsCard = document.getElementById('lectureDetailsCard');
const notEnrolledCard = document.getElementById('notEnrolledCard');
const successPreview = document.getElementById('successPreview');
const markBtn = document.getElementById('markBtn');
const markedBtn = document.getElementById('markedBtn');
const alreadyMarkedMsg = document.getElementById('alreadyMarkedMsg');
const enrollmentStatus = document.getElementById('enrollmentStatus');
const enrollmentMessage = document.getElementById('enrollmentMessage');
const notEnrolledMessage = document.getElementById('notEnrolledMessage');

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
            
            // Update lecture details
            updateLectureDetails(result.details);
            
            // Check enrollment status
            if (!result.is_enrolled) {
                showNotEnrolled(result.details);
                return;
            }
            
            // Check if already marked
            if (result.already_marked) {
                showAlreadyMarked();
            } else {
                showMarkButton();
            }
            
            // Hide scanner view, show details
            document.getElementById('reader').style.display = 'none';
            lectureDetailsCard.classList.remove('d-none');
            notEnrolledCard.classList.add('d-none');
            successPreview.classList.add('d-none');
            
            statusBar.innerHTML = '✅ QR scanned';
            statusDetail.innerHTML = 'Review lecture details';
            
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

function showNotEnrolled(details) {
    notEnrolledMessage.innerHTML = `You are not enrolled in ${details.course} - ${details.year}.<br>Your enrollment: Course ID ${studentCourse}, Year ID ${studentYear}`;
    
    document.getElementById('reader').style.display = 'none';
    lectureDetailsCard.classList.add('d-none');
    notEnrolledCard.classList.remove('d-none');
    successPreview.classList.add('d-none');
    
    statusBar.innerHTML = '⚠️ Not eligible';
    statusDetail.innerHTML = 'You are not enrolled in this course';
}

function showAlreadyMarked() {
    markBtn.classList.add('d-none');
    markedBtn.classList.remove('d-none');
    alreadyMarkedMsg.classList.remove('d-none');
    enrollmentStatus.classList.add('d-none');
}

function showMarkButton() {
    markBtn.classList.remove('d-none');
    markedBtn.classList.add('d-none');
    alreadyMarkedMsg.classList.add('d-none');
    enrollmentStatus.classList.add('d-none');
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

        const response = await fetch(window.location.href, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData.toString()
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Hide lecture details, show success
            lectureDetailsCard.classList.add('d-none');
            notEnrolledCard.classList.add('d-none');
            successPreview.classList.remove('d-none');
            document.getElementById('successMessage').textContent = result.message;
            
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
        markBtn.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Mark Attendance';
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
    notEnrolledCard.classList.add('d-none');
    successPreview.classList.add('d-none');
    document.getElementById('reader').style.display = 'block';
    
    // Reset button states
    markBtn.disabled = false;
    markBtn.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Mark Attendance';
    showMarkButton();
    
    // Reset processing flag
    isProcessing = false;
    currentQRData = null;
    
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
};

// Save scan count
window.addEventListener('beforeunload', () => {
    const today = new Date().toDateString();
    localStorage.setItem('scan_count_' + today, scanCount);
    
    if (scanner) {
        scanner.stop().catch(() => {});
    }
});
</script>

<?php include('../includes/footer.php'); ?>