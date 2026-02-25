<?php
session_start();
require '../config/db.php';

// Security: Only allow admins to generate reports
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Include TCPDF (make sure TCPDF is in libs/tcpdf/tcpdf.php)
require_once('libs/tcpdf/tcpdf.php');

// Get filters
$selectedCourse   = $_GET['course'] ?? '';
$selectedYear     = $_GET['year'] ?? '';
$selectedSession  = $_GET['session'] ?? '';

// Validate filters
if (empty($selectedCourse) || empty($selectedYear) || empty($selectedSession)) {
    die("Invalid or missing filters. Please select course, semester, and session.");
}

// Sanitize inputs (prevent SQL injection)
$selectedCourse   = (int)$selectedCourse;
$selectedYear     = (int)$selectedYear;
$selectedSession  = (int)$selectedSession;

// Fetch header details safely
$stmt = $pdo->prepare("SELECT course_name FROM courses WHERE id = ?");
$stmt->execute([$selectedCourse]);
$courseName = $stmt->fetchColumn() ?: 'Unknown Course';

$stmt = $pdo->prepare("SELECT year_name FROM years WHERE id = ?");
$stmt->execute([$selectedYear]);
$yearName = $stmt->fetchColumn() ?: 'Unknown Semester';

$stmt = $pdo->prepare("SELECT session_name FROM sessions WHERE id = ?");
$stmt->execute([$selectedSession]);
$sessionName = $stmt->fetchColumn() ?: 'Unknown Session';

// Fetch attendance data correctly
// We calculate average attendance per student across all their classes
$query = "
    SELECT 
        s.id AS student_id,
        s.name AS student_name,
        COUNT(a.id) AS total_lectures,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_lectures,
        ROUND(
            (SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(a.id), 0)), 2
        ) AS avg_attendance
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE s.course_id = ? 
      AND s.year_id = ? 
      AND s.session_id = ?
    GROUP BY s.id, s.name
    ORDER BY avg_attendance DESC, s.name ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$selectedCourse, $selectedYear, $selectedSession]);
$attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall stats
$totalStudents = count($attendanceData);
$overallAttendance = 0;
if ($totalStudents > 0) {
    $sum = array_sum(array_column($attendanceData, 'avg_attendance'));
    $overallAttendance = round($sum / $totalStudents, 2);
}

// Create PDF
class CustomTCPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 15, 'Attendance Report', 0, 1, 'C');
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Generated on ' . date('d M Y') . ' | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new CustomTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Attendance System');
$pdf->SetTitle('Attendance Report');
$pdf->SetMargins(15, 30, 15);
$pdf->SetAutoPageBreak(TRUE, 25);

$pdf->AddPage();

// Report Title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, "Attendance Report", 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 8, "Course: $courseName", 0, 1, 'L');
$pdf->Cell(0, 8, "Semester: $yearName", 0, 1, 'L');
$pdf->Cell(0, 8, "Session: $sessionName", 0, 1, 'L');
$pdf->Cell(0, 8, "Total Students: $totalStudents", 0, 1, 'L');
$pdf->Cell(0, 8, "Overall Average Attendance: $overallAttendance%", 0, 1, 'L');
$pdf->Cell(0, 8, "Generated on: " . date('d F Y'), 0, 1, 'L');
$pdf->Ln(10);

// Table Header
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(20, 10, 'ID', 1, 0, 'C', true);
$pdf->Cell(80, 10, 'Student Name', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Total Lectures', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Present', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Attendance (%)', 1, 1, 'C', true);

// Table Rows
$pdf->SetFont('helvetica', '', 10);
foreach ($attendanceData as $row) {
    $fill = ($pdf->GetY() % 20 < 10) ? true : false; // Alternate row color
    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

    $pdf->Cell(20, 10, $row['student_id'], 1, 0, 'C', $fill);
    $pdf->Cell(80, 10, $row['student_name'], 1, 0, 'L', $fill);
    $pdf->Cell(30, 10, $row['total_lectures'], 1, 0, 'C', $fill);
    $pdf->Cell(30, 10, $row['present_lectures'], 1, 0, 'C', $fill);

    // Color attendance percentage
    $percent = $row['avg_attendance'];
    if ($percent >= 75) {
        $pdf->SetTextColor(0, 128, 0); // Green
    } elseif ($percent >= 50) {
        $pdf->SetTextColor(255, 140, 0); // Orange
    } else {
        $pdf->SetTextColor(220, 20, 60); // Red
    }
    $pdf->Cell(30, 10, $percent . '%', 1, 1, 'C', $fill);
    $pdf->SetTextColor(0, 0, 0); // Reset color
}

// Output PDF
$filename = "Attendance_Report_{$courseName}_{$yearName}_{$sessionName}_" . date('Y-m-d') . ".pdf";
$pdf->Output($filename, 'D'); // 'D' = Force download

exit();
?>