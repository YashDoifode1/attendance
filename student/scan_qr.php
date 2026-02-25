<?php
ob_start();
include('../config/db.php');
include('../includes/header.php');

if (!isset($_SESSION['user_id'])) exit('Unauthorized');
$student_id = $_SESSION['user_id'];

/* =====================================================
   AJAX: MARK ATTENDANCE
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    ob_clean();
    header('Content-Type: application/json');

    try {
        $data = json_decode($_POST['qr_data'], true);
        if (!$data || !is_array($data)) 
            throw new Exception('Invalid QR code');

        // Required fields
        $required = [
            'unique_session_id','faculty_id','qr_expiry_timestamp','security_token',
            'date','subject_name','start_time','end_time','session_type'
        ];
        foreach ($required as $f) {
            if (empty($data[$f])) throw new Exception("QR missing: $f");
        }

        // Expiry check
        if (strtotime($data['qr_expiry_timestamp']) < time()) throw new Exception("QR code expired");

        // Security token check
        $expected = hash('sha256', $data['unique_session_id'].$data['date'].SECRET_KEY);
        if (!hash_equals($expected, $data['security_token'])) throw new Exception("Invalid QR token");

        // Get schedule from session
        $stmt = $pdo->prepare("SELECT schedule_id FROM attendance_sessions WHERE id=?");
        $stmt->execute([$data['unique_session_id']]);
        $session = $stmt->fetch();
        if (!$session) throw new Exception("Attendance session not found");

        // Get schedule details
        $stmt = $pdo->prepare("
            SELECT subject_id AS subjects_id, course_id, year_id, session_id
            FROM schedule
            WHERE id=?
        ");
        $stmt->execute([$session['schedule_id']]);
        $ids = $stmt->fetch();
        if (!$ids) throw new Exception("Schedule details missing");

        // Insert or update attendance
        $stmt = $pdo->prepare("
            INSERT INTO attendance
            (student_id, schedule_id, faculty_id, subjects_id, course_id, year_id, session_id, date, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Present', NOW())
            ON DUPLICATE KEY UPDATE status='Present', created_at=NOW()
        ");
        $stmt->execute([
            $student_id,
            $session['schedule_id'],
            $data['faculty_id'],
            $ids['subjects_id'],
            $ids['course_id'],
            $ids['year_id'],
            $ids['session_id'],
            $data['date']
        ]);

        echo json_encode([
            'success'=>true,
            'message'=>'Attendance marked successfully ✅'
        ]);
        exit;

    } catch (Throwable $e) {
        error_log($e->getMessage());
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        exit;
    }
}
?>

<main class="main-content">
<div class="px-4 pt-4 pb-5">
    
    <!-- Page Header with Status -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">
            <i class="bi bi-qr-code-scan me-2"></i> Scan Attendance QR
        </h2>
        <div class="d-flex gap-2">
            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2">
                <i class="bi bi-camera-video me-2"></i> Camera Active
            </span>
            <button class="btn btn-outline-secondary btn-sm" onclick="toggleInstructions()">
                <i class="bi bi-question-circle me-2"></i> Help
            </button>
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
                        <li>Hold your phone steady about 15-20 cm from the QR code</li>
                        <li>Ensure good lighting for better scanning</li>
                        <li>Center the QR code within the scanning box</li>
                        <li>QR codes expire after a certain time for security</li>
                        <li>You'll receive confirmation once attendance is marked</li>
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
                    <!-- Camera Selection (if multiple cameras) -->
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
                            <i class="bi bi-arrow-repeat me-1"></i> Auto-detecting
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

                    <!-- QR Preview Section (Shown after scan) -->
                    <div id="qrPreviewBox" class="d-none">
                        <div class="alert alert-success border-0">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-check-circle-fill text-success me-3 fs-4"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-2">QR Code Detected!</h6>
                                    <div id="qrPreview" class="mb-2"></div>
                                    <div class="progress mb-2" style="height: 5px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                             id="processingProgress" 
                                             style="width: 0%"></div>
                                    </div>
                                    <small class="text-muted">Processing attendance...</small>
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
                </div>

                <!-- Card Footer with Recent Scans -->
                <div class="card-footer bg-white border-0 px-4 py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <small class="text-muted">
                            <i class="bi bi-clock-history me-1"></i>
                            <span id="scanCounter">0</span> scans today
                        </small>
                        <small class="text-muted">
                            <i class="bi bi-shield-check me-1"></i>
                            Secure & Encrypted
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
                        <!-- Will be populated via AJAX -->
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

.status-area.scanning {
    background: linear-gradient(45deg, #f8f9fa, #e9ecef);
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
}
</style>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
// State variables
let scanner;
let isProcessing = false;
let torchEnabled = false;
let currentCamera = 'environment';
let scanCount = 0;

// DOM Elements
const statusBar = document.getElementById('statusBar');
const statusDetail = document.getElementById('statusDetail');
const qrPreviewBox = document.getElementById('qrPreviewBox');
const qrPreview = document.getElementById('qrPreview');
const scanningSpinner = document.getElementById('scanningSpinner');
const processingProgress = document.getElementById('processingProgress');
const torchBtn = document.getElementById('torchBtn');
const scanCounter = document.getElementById('scanCounter');

// Initialize scanner
function startScanner() {
    scanner = new Html5Qrcode("reader");
    
    const config = {
        fps: 30,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0,
        showTorchButtonIfSupported: true
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
    if (isProcessing) return;
    
    try {
        const decodedQR = JSON.parse(decodedText);
        
        // Show preview
        qrPreview.innerHTML = `
            <div class="qr-preview-item">
                <strong class="d-block mb-2">${decodedQR.subject_name || 'Subject'}</strong>
                <div class="d-flex justify-content-between mb-1">
                    <span><i class="bi bi-clock me-2"></i>${decodedQR.start_time || 'N/A'} - ${decodedQR.end_time || 'N/A'}</span>
                    <span class="badge bg-info">${decodedQR.session_type || 'Lecture'}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span><i class="bi bi-calendar me-2"></i>${decodedQR.date || 'N/A'}</span>
                    <span><i class="bi bi-person me-2"></i>Faculty: ${decodedQR.faculty_id || 'N/A'}</span>
                </div>
            </div>
        `;
        
        qrPreviewBox.classList.remove('d-none');
        isProcessing = true;
        statusBar.innerHTML = '⏳ Processing...';
        statusDetail.innerHTML = 'Marking attendance';
        
        // Animate progress
        let progress = 0;
        const interval = setInterval(() => {
            progress += 10;
            processingProgress.style.width = progress + '%';
            if (progress >= 100) clearInterval(interval);
        }, 100);

        // Send to server
        const response = await fetch("<?= APP_URL ?>/student/scan_attendance.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "qr_data=" + encodeURIComponent(JSON.stringify(decodedQR))
        });
        
        const result = await response.json();
        clearInterval(interval);
        
        if (result.success) {
            statusBar.innerHTML = '✅ Success!';
            statusDetail.innerHTML = result.message;
            scanCount++;
            scanCounter.innerHTML = scanCount;
            
            // Show success animation
            processingProgress.style.width = '100%';
            processingProgress.classList.add('bg-success');
            
            setTimeout(() => {
                resetScanner();
                loadRecentAttendance();
            }, 2000);
        } else {
            throw new Error(result.message);
        }
        
    } catch (error) {
        statusBar.innerHTML = '❌ Error';
        statusDetail.innerHTML = error.message;
        isProcessing = false;
        
        // Flash error
        qrPreviewBox.classList.add('d-none');
        setTimeout(() => {
            statusBar.innerHTML = 'Ready to scan';
            statusDetail.innerHTML = 'Waiting for QR code...';
        }, 3000);
    }
}

// Error handler
function onScanError(error) {
    // Only log serious errors
    if (error?.includes('NotFoundException')) return;
    console.warn('Scan error:', error);
}

// Switch camera
async function switchCamera(type) {
    currentCamera = type;
    
    // Update UI
    document.querySelectorAll('#cameraToggle .btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Restart scanner
    if (scanner) {
        await scanner.stop();
        startScanner();
    }
}

// Toggle torch/flashlight
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

// Load recent attendance
async function loadRecentAttendance() {
    try {
        const response = await fetch('<?= APP_URL ?>/student/get_recent_attendance.php');
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
    
    // Update scan counter from localStorage
    const today = new Date().toDateString();
    const stored = localStorage.getItem('scanCount_' + today);
    if (stored) {
        scanCount = parseInt(stored);
        scanCounter.innerHTML = scanCount;
    }
};

// Save scan count to localStorage
window.addEventListener('beforeunload', () => {
    const today = new Date().toDateString();
    localStorage.setItem('scanCount_' + today, scanCount);
});
</script>

<?php include('../includes/footer.php'); ?>