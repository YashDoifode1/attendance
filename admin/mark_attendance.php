<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$alertType = "";

// Fetch dropdown data
$courses  = $pdo->query("SELECT id, course_name FROM courses ORDER BY course_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$years    = $pdo->query("SELECT id, year_name FROM years ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$sessions = $pdo->query("SELECT id, session_name FROM sessions ORDER BY session_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Check if subjects_id column exists
$columnCheckStmt = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'subjects_id'");
$subjectColumnExists = $columnCheckStmt->rowCount() > 0;

// Fetch subjects if exists
$subjects = [];
if ($subjectColumnExists) {
    $subjects = $pdo->query("SELECT id, subject_name FROM subjects ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Get filters from POST
$selectedCourse  = $_POST['course'] ?? '';
$selectedYear    = $_POST['year'] ?? '';
$selectedSession = $_POST['session'] ?? '';
$selectedSubject = $_POST['subject'] ?? '';
$selectedDate    = $_POST['date'] ?? date('Y-m-d');

// Fetch students for selected course/year
$students = [];
if ($selectedCourse && $selectedYear) {
    $stmt = $pdo->prepare("SELECT id, name FROM students WHERE course_id = ? AND year_id = ? ORDER BY name ASC");
    $stmt->execute([$selectedCourse, $selectedYear]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission for marking attendance
if (isset($_POST['mark_attendance'])) {
    $studentIds = $_POST['student_id'] ?? [];
    $statuses   = $_POST['status'] ?? [];

    $pdo->beginTransaction();
    try {
        foreach ($studentIds as $sid) {
            $status = $statuses[$sid] ?? 'Absent';

            // Check if attendance already exists for this student/date/session/course/year/subject
            $checkStmt = $pdo->prepare("
                SELECT id FROM attendance 
                WHERE student_id = ? AND course_id = ? AND year_id = ? AND session_id = ? AND date = ? " . 
                ($subjectColumnExists ? "AND subjects_id = ?" : "") . " LIMIT 1
            ");

            $paramsCheck = [$sid, $selectedCourse, $selectedYear, $selectedSession, $selectedDate];
            if ($subjectColumnExists) $paramsCheck[] = $selectedSubject ?: null;
            $checkStmt->execute($paramsCheck);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing record
                $updateStmt = $pdo->prepare("UPDATE attendance SET status = ? WHERE id = ?");
                $updateStmt->execute([$status, $existing['id']]);
            } else {
                // Insert new record
                $insertStmt = $pdo->prepare("
                    INSERT INTO attendance (student_id, course_id, year_id, session_id, subjects_id, date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([$sid, $selectedCourse, $selectedYear, $selectedSession, $selectedSubject ?: null, $selectedDate, $status]);
            }
        }
        $pdo->commit();
        $message = "Attendance saved successfully.";
        $alertType = "success";
        header("Location: ".$_SERVER['REQUEST_URI']); // refresh page after save
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error saving attendance: " . $e->getMessage();
        $alertType = "error";
    }
}

// Include layout
include('includes/sidebar_header.php');
?>

<div class="max-w-7xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Mark Attendance</h1>
        <p class="text-gray-600 mt-2">Select course, semester, session, subject, and date to mark attendance for students.</p>
    </div>

    <!-- Success/Error Message -->
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-lg <?= $alertType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Filter Form -->
    <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8 grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Course</label>
            <select name="course" onchange="this.form.submit()" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                <option value="">-- Select Course --</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= $course['id'] ?>" <?= $selectedCourse == $course['id'] ? 'selected' : '' ?>><?= htmlspecialchars($course['course_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Semester / Year</label>
            <select name="year" onchange="this.form.submit()" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                <option value="">-- Select Semester --</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?= $year['id'] ?>" <?= $selectedYear == $year['id'] ? 'selected' : '' ?>><?= htmlspecialchars($year['year_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Session</label>
            <select name="session" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                <option value="">-- Select Session --</option>
                <?php foreach ($sessions as $sess): ?>
                    <option value="<?= $sess['id'] ?>" <?= $selectedSession == $sess['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sess['session_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($subjectColumnExists): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
            <select name="subject" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">-- Select Subject --</option>
                <?php foreach ($subjects as $subj): ?>
                    <option value="<?= $subj['id'] ?>" <?= $selectedSubject == $subj['id'] ? 'selected' : '' ?>><?= htmlspecialchars($subj['subject_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
            <input type="date" name="date" value="<?= $selectedDate ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg" required>
        </div>
    </form>

    <?php if (!empty($students)): ?>
        <form method="POST">
            <input type="hidden" name="course" value="<?= $selectedCourse ?>">
            <input type="hidden" name="year" value="<?= $selectedYear ?>">
            <input type="hidden" name="session" value="<?= $selectedSession ?>">
            <input type="hidden" name="subject" value="<?= $selectedSubject ?>">
            <input type="hidden" name="date" value="<?= $selectedDate ?>">

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Mark Attendance</h3>
                    <button type="submit" name="mark_attendance" class="px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">Save Attendance</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($students as $student): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= $student['id'] ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($student['name']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <input type="hidden" name="student_id[]" value="<?= $student['id'] ?>">
                                        <select name="status[<?= $student['id'] ?>]" class="px-3 py-2 border border-gray-300 rounded-lg">
                                            <option value="Present">Present</option>
                                            <option value="Absent">Absent</option>
                                            <option value="Late">Late</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    <?php elseif($selectedCourse && $selectedYear): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <h3 class="text-lg font-medium text-gray-900 mb-2">No students found</h3>
            <p class="text-gray-600">No students are enrolled for the selected course and semester.</p>
        </div>
    <?php endif; ?>
</div>

<?php include('includes/footer.php'); ?>
