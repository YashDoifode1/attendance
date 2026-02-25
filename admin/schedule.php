<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$alertType = "";

// Fetch data for dropdowns
$faculties = $pdo->query("SELECT id, name FROM students WHERE role = 'faculty' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$courses   = $pdo->query("SELECT id, course_name FROM courses ORDER BY course_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$subjects  = $pdo->query("SELECT id, subject_name, course_id FROM subjects ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$sessions  = $pdo->query("SELECT id, session_name FROM sessions ORDER BY session_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$years     = $pdo->query("SELECT id, year_name FROM years ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $faculty_id  = $_POST['faculty_id'] ?? '';
    $course_id   = $_POST['course_id'] ?? '';
    $subject_id  = $_POST['subject_id'] ?? '';
    $session_id  = $_POST['session_id'] ?? '';
    $year_id     = $_POST['year_id'] ?? '';
    $day         = $_POST['day'] ?? '';
    $start_time  = $_POST['start_time'] ?? '';
    $end_time    = $_POST['end_time'] ?? '';

    // Basic validation
    if (empty($faculty_id) || empty($course_id) || empty($subject_id) || empty($session_id) || empty($year_id) || empty($day) || empty($start_time) || empty($end_time)) {
        $message = "All fields are required.";
        $alertType = "error";
    } elseif ($start_time >= $end_time) {
        $message = "End time must be after start time.";
        $alertType = "error";
    } else {
        // Check for schedule conflict (same faculty, day, overlapping time)
        $conflictCheck = $pdo->prepare("
            SELECT COUNT(*) FROM schedule 
            WHERE faculty_id = ? AND day = ? 
            AND (
                (start_time <= ? AND end_time > ?) OR 
                (start_time < ? AND end_time >= ?)
            )
        ");
        $conflictCheck->execute([$faculty_id, $day, $end_time, $start_time, $end_time, $start_time]);
        
        if ($conflictCheck->fetchColumn() > 0) {
            $message = "This faculty already has a class scheduled at this time on $day.";
            $alertType = "error";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO schedule 
                    (faculty_id, course_id, subject_id, session_id, year_id, day, start_time, end_time) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$faculty_id, $course_id, $subject_id, $session_id, $year_id, $day, $start_time, $end_time]);
                $message = "Class schedule assigned successfully!";
                $alertType = "success";
            } catch (Exception $e) {
                $message = "Error assigning schedule.";
                $alertType = "error";
            }
        }
    }
}

// Include shared layout
include('includes/sidebar_header.php');
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Assign Class Schedule</h1>
        <p class="text-gray-600 mt-2">Assign faculty to teach specific subjects in a course, semester, and time slot.</p>
    </div>

    <!-- Success/Error Alert -->
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-lg <?= $alertType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <?php if ($alertType === 'success'): ?>
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    <?php else: ?>
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    <?php endif; ?>
                </svg>
                <?= htmlspecialchars($message) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Schedule Assignment Form Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Faculty -->
            <div>
                <label for="faculty_id" class="block text-sm font-medium text-gray-700 mb-2">Faculty Member</label>
                <select name="faculty_id" id="faculty_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- Select Faculty --</option>
                    <?php foreach ($faculties as $faculty): ?>
                        <option value="<?= $faculty['id'] ?>"><?= htmlspecialchars($faculty['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Course -->
            <div>
                <label for="course_id" class="block text-sm font-medium text-gray-700 mb-2">Course</label>
                <select name="course_id" id="course_id" required onchange="filterSubjects()" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Subject (Dynamic) -->
            <div>
                <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                <select name="subject_id" id="subject_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- Select Subject --</option>
                </select>
            </div>

            <!-- Session -->
            <div>
                <label for="session_id" class="block text-sm font-medium text-gray-700 mb-2">Session / Batch</label>
                <select name="session_id" id="session_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- Select Session --</option>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?= $session['id'] ?>"><?= htmlspecialchars($session['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Year / Semester -->
            <div>
                <label for="year_id" class="block text-sm font-medium text-gray-700 mb-2">Year / Semester</label>
                <select name="year_id" id="year_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- Select Semester --</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['year_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Day -->
            <div>
                <label for="day" class="block text-sm font-medium text-gray-700 mb-2">Day of Week</label>
                <select name="day" id="day" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- Select Day --</option>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                </select>
            </div>

            <!-- Start & End Time -->
            <div>
                <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">Start Time</label>
                <input type="time" name="start_time" id="start_time" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">End Time</label>
                <input type="time" name="end_time" id="end_time" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <!-- Submit Button (Full Width) -->
            <div class="md:col-span-2">
                <button type="submit" class="w-full inline-flex justify-center items-center px-6 py-3 bg-indigo-600 text-white font-medium text-lg rounded-lg hover:bg-indigo-700 transition shadow-md">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Assign Class Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Dynamic Subject Filtering Script -->
<script>
    const allSubjects = <?= json_encode($subjects, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function filterSubjects() {
        const courseId = document.getElementById('course_id').value;
        const subjectSelect = document.getElementById('subject_id');

        // Clear current options
        subjectSelect.innerHTML = '<option value="">-- Select Subject --</option>';

        if (!courseId) return;

        allSubjects.forEach(subject => {
            if (subject.course_id == courseId) {
                const option = document.createElement('option');
                option.value = subject.id;
                option.textContent = subject.subject_name;
                subjectSelect.appendChild(option);
            }
        });
    }

    // Initialize on page load (in case of validation error with pre-selected values)
    document.addEventListener('DOMContentLoaded', filterSubjects);
</script>

<?php include('includes/footer.php'); ?>