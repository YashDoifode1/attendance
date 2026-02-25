<?php
session_start();
include('../config/db.php');

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Dashboard Statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE role = 'student'");
$totalStudents = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE role = 'faculty'");
$totalFaculty = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM courses");
$totalCourses = $stmt->fetchColumn();

$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(status IN ('Present','Late')) as present,
        SUM(status = 'Late') as late
    FROM attendance WHERE date = ?
");
$stmt->execute([$today]);
$todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

$todayPercentage = $todayAttendance['total'] > 0
    ? round(($todayAttendance['present'] / $todayAttendance['total']) * 100, 1)
    : 0;

// Get weekly attendance data
$weeklyData = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$weeklyPercentages = [];

foreach ($days as $day) {
    $date = date('Y-m-d', strtotime("last $day"));
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(status IN ('Present','Late')) as present
        FROM attendance WHERE date = ?
    ");
    $stmt->execute([$date]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $percentage = $data['total'] > 0 
        ? round(($data['present'] / $data['total']) * 100, 1) 
        : 0;
    $weeklyPercentages[] = $percentage;
}

// Get recent activities
$stmt = $pdo->prepare("
    SELECT a.*, s.name as student_name, c.course_name 
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN courses c ON a.course_id = c.id
    ORDER BY a.date DESC, a.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top performing courses
$stmt = $pdo->prepare("
    SELECT 
        c.course_name,
        COUNT(a.id) as total,
        SUM(a.status IN ('Present','Late')) as present,
        ROUND((SUM(a.status IN ('Present','Late')) / COUNT(a.id)) * 100, 1) as percentage
    FROM courses c
    LEFT JOIN attendance a ON c.id = a.course_id
    WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY c.id
    ORDER BY percentage DESC
    LIMIT 5
");
$stmt->execute();
$topCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// INCLUDE SIDEBAR + HEADER
include('includes/sidebar_header.php');
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">Welcome back, <?= htmlspecialchars($adminName) ?>!</h4>
        <p class="mb-0" style="color: var(--text-muted);">Here's what's happening with your attendance system today.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" onclick="exportReport()">
            <i class="bi bi-download me-2"></i>Export Report
        </button>
        <button class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);" onclick="refreshDashboard()">
            <i class="bi bi-arrow-repeat"></i>
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Total Students -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-people-fill" style="color: var(--sidebar-active); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">This Month</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= number_format($totalStudents) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Total Students</p>
                <div class="mt-3 d-flex align-items-center">
                    <span class="badge bg-success me-2">+12%</span>
                    <small style="color: var(--text-muted);">vs last month</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Faculty Members -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-person-badge-fill" style="color: var(--success); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">Active</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= number_format($totalFaculty) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Faculty Members</p>
                <div class="mt-3 d-flex align-items-center">
                    <span class="badge bg-success me-2">+2</span>
                    <small style="color: var(--text-muted);">this week</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Courses -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-book-fill" style="color: var(--warning); font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">Current</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= number_format($totalCourses) ?></h3>
                <p class="mb-0" style="color: var(--text-muted);">Active Courses</p>
                <div class="mt-3 d-flex align-items-center">
                    <span class="badge bg-warning me-2">8 new</span>
                    <small style="color: var(--text-muted);">this semester</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Attendance -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="width: 48px; height: 48px; background: rgba(139, 92, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-check-circle-fill" style="color: #8b5cf6; font-size: 1.5rem;"></i>
                    </div>
                    <span class="badge" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">Today</span>
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?= $todayPercentage ?>%</h3>
                <p class="mb-0" style="color: var(--text-muted);">Attendance Rate</p>
                <div class="progress mt-3" style="height: 6px; background: var(--border-color);">
                    <div class="progress-bar" role="progressbar" style="width: <?= $todayPercentage ?>%; background: linear-gradient(90deg, var(--sidebar-active), #8b5cf6);" aria-valuenow="<?= $todayPercentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="mt-2 d-flex justify-content-between">
                    <small style="color: var(--text-muted);">Present: <?= $todayAttendance['present'] ?? 0 ?></small>
                    <small style="color: var(--text-muted);">Late: <?= $todayAttendance['late'] ?? 0 ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts & Tables Row -->
<div class="row g-4 mb-4">
    <!-- Attendance Trend Chart -->
    <div class="col-xl-8">
        <div class="card border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-graph-up me-2" style="color: var(--sidebar-active);"></i>Attendance Trend
                </h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary); width: auto;" id="chartTimeRange">
                        <option value="week" selected>This Week</option>
                        <option value="month">This Month</option>
                        <option value="semester">This Semester</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-xl-4">
        <div class="card border-0 h-100">
            <div class="card-header">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-lightning-charge me-2" style="color: var(--sidebar-active);"></i>Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="add_student.php" class="text-decoration-none">
                            <div class="p-4 text-center rounded-3" style="background: rgba(59, 130, 246, 0.05); border: 1px solid var(--border-color); transition: all 0.3s;" 
                               onmouseover="this.style.background='rgba(59, 130, 246, 0.1)'; this.style.transform='translateY(-2px)'" 
                               onmouseout="this.style.background='rgba(59, 130, 246, 0.05)'; this.style.transform='translateY(0)'">
                                <div class="mb-2" style="color: var(--sidebar-active); font-size: 1.8rem;">➕</div>
                                <h6 class="mb-0" style="color: var(--text-primary);">Add Student</h6>
                                <small style="color: var(--text-muted);">New enrollment</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="create_schedule.php" class="text-decoration-none">
                            <div class="p-4 text-center rounded-3" style="background: rgba(16, 185, 129, 0.05); border: 1px solid var(--border-color); transition: all 0.3s;"
                               onmouseover="this.style.background='rgba(16, 185, 129, 0.1)'; this.style.transform='translateY(-2px)'"
                               onmouseout="this.style.background='rgba(16, 185, 129, 0.05)'; this.style.transform='translateY(0)'">
                                <div class="mb-2" style="color: var(--success); font-size: 1.8rem;">📅</div>
                                <h6 class="mb-0" style="color: var(--text-primary);">Schedule</h6>
                                <small style="color: var(--text-muted);">Create timetable</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="start_attendance.php" class="text-decoration-none">
                            <div class="p-4 text-center rounded-3" style="background: rgba(245, 158, 11, 0.05); border: 1px solid var(--border-color); transition: all 0.3s;"
                               onmouseover="this.style.background='rgba(245, 158, 11, 0.1)'; this.style.transform='translateY(-2px)'"
                               onmouseout="this.style.background='rgba(245, 158, 11, 0.05)'; this.style.transform='translateY(0)'">
                                <div class="mb-2" style="color: var(--warning); font-size: 1.8rem;">⏱️</div>
                                <h6 class="mb-0" style="color: var(--text-primary);">Attendance</h6>
                                <small style="color: var(--text-muted);">Start session</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="reports.php" class="text-decoration-none">
                            <div class="p-4 text-center rounded-3" style="background: rgba(139, 92, 246, 0.05); border: 1px solid var(--border-color); transition: all 0.3s;"
                               onmouseover="this.style.background='rgba(139, 92, 246, 0.1)'; this.style.transform='translateY(-2px)'"
                               onmouseout="this.style.background='rgba(139, 92, 246, 0.05)'; this.style.transform='translateY(0)'">
                                <div class="mb-2" style="color: #8b5cf6; font-size: 1.8rem;">📊</div>
                                <h6 class="mb-0" style="color: var(--text-primary);">Reports</h6>
                                <small style="color: var(--text-muted);">Analytics</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity & Top Courses -->
<div class="row g-4">
    <!-- Recent Attendance Activity -->
    <div class="col-xl-7">
        <div class="card border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-clock-history me-2" style="color: var(--sidebar-active);"></i>Recent Activity
                </h5>
                <a href="attendance.php" class="text-decoration-none" style="color: var(--sidebar-active);">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="color: var(--text-primary);">
                        <thead style="background: var(--card-bg); border-bottom: 1px solid var(--border-color);">
                            <tr>
                                <th class="ps-4 py-3">Student</th>
                                <th class="py-3">Course</th>
                                <th class="py-3">Date</th>
                                <th class="py-3">Status</th>
                                <th class="pe-4 py-3">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $activity): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle" style="width: 32px; height: 32px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                            <span style="color: var(--sidebar-active); font-weight: 600;"><?= strtoupper(substr($activity['student_name'], 0, 1)) ?></span>
                                        </div>
                                        <?= htmlspecialchars($activity['student_name']) ?>
                                    </div>
                                </td>
                                <td class="py-3" style="color: var(--text-secondary);"><?= htmlspecialchars($activity['course_name']) ?></td>
                                <td class="py-3" style="color: var(--text-secondary);"><?= date('M d, Y', strtotime($activity['date'])) ?></td>
                                <td class="py-3">
                                    <?php
                                    $status = $activity['status'];
                                    $badgeClass = '';
                                    $icon = '';
                                    if ($status == 'Present') {
                                        $badgeClass = 'success';
                                        $icon = 'bi-check-circle-fill';
                                    } elseif ($status == 'Late') {
                                        $badgeClass = 'warning';
                                        $icon = 'bi-exclamation-triangle-fill';
                                    } else {
                                        $badgeClass = 'danger';
                                        $icon = 'bi-x-circle-fill';
                                    }
                                    ?>
                                    <span class="badge bg-<?= $badgeClass ?> bg-opacity-10" style="color: var(--<?= $badgeClass ?>); padding: 8px 12px;">
                                        <i class="bi <?= $icon ?> me-1"></i><?= $status ?>
                                    </span>
                                </td>
                                <td class="pe-4 py-3" style="color: var(--text-muted);"><?= date('h:i A', strtotime($activity['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performing Courses -->
    <div class="col-xl-5">
        <div class="card border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--text-primary);">
                    <i class="bi bi-trophy me-2" style="color: var(--sidebar-active);"></i>Top Courses (30 Days)
                </h5>
                <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--sidebar-active);">Attendance Rate</span>
            </div>
            <div class="card-body">
                <?php foreach ($topCourses as $index => $course): ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold" style="color: var(--text-muted); width: 24px;">#<?= $index + 1 ?></span>
                            <span style="color: var(--text-primary); font-weight: 500;"><?= htmlspecialchars($course['course_name']) ?></span>
                        </div>
                        <span class="fw-bold" style="color: var(--text-primary);"><?= $course['percentage'] ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px; background: var(--border-color);">
                        <div class="progress-bar" role="progressbar" style="width: <?= $course['percentage'] ?>%; background: linear-gradient(90deg, <?= $index == 0 ? 'var(--warning)' : 'var(--sidebar-active)' ?>, <?= $index == 0 ? '#fbbf24' : '#8b5cf6' ?>);" 
                             aria-valuenow="<?= $course['percentage'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="mt-1 d-flex justify-content-between">
                        <small style="color: var(--text-muted);">Present: <?= $course['present'] ?>/<?= $course['total'] ?></small>
                        <small style="color: var(--text-muted);">Absent: <?= $course['total'] - $course['present'] ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Attendance Chart
const ctx = document.getElementById('attendanceChart').getContext('2d');

// Gradient fill
const gradient = ctx.createLinearGradient(0, 0, 0, 300);
gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

const attendanceChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            label: 'Attendance %',
            data: [<?= implode(',', $weeklyPercentages) ?>],
            borderColor: '#3b82f6',
            backgroundColor: gradient,
            borderWidth: 3,
            pointBackgroundColor: '#3b82f6',
            pointBorderColor: '#0f1217',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7,
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: '#1a1e26',
                titleColor: '#f0f3f8',
                bodyColor: '#cbd5e1',
                borderColor: '#2a2f3a',
                borderWidth: 1,
                padding: 10,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        return `Attendance: ${context.raw}%`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                grid: {
                    color: '#2a2f3a',
                    drawBorder: false
                },
                ticks: {
                    color: '#94a3b8',
                    callback: function(value) {
                        return value + '%';
                    }
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    color: '#94a3b8'
                }
            }
        }
    }
});

// Export Report Function
function exportReport() {
    // Create a modal for export options
    const modal = document.createElement('div');
    modal.className = 'modal fade' id="exportModal" tabindex="-1";
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold" style="color: var(--text-primary);">Export Report</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p style="color: var(--text-secondary);">Choose export format:</p>
                    <div class="d-flex gap-3">
                        <button class="btn flex-fill" style="background: var(--sidebar-hover); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="window.location.href='export.php?format=pdf'">
                            <i class="bi bi-file-pdf me-2" style="color: #ef4444;"></i>PDF
                        </button>
                        <button class="btn flex-fill" style="background: var(--sidebar-hover); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="window.location.href='export.php?format=excel'">
                            <i class="bi bi-file-excel me-2" style="color: #10b981;"></i>Excel
                        </button>
                        <button class="btn flex-fill" style="background: var(--sidebar-hover); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="window.location.href='export.php?format=csv'">
                            <i class="bi bi-filetype-csv me-2" style="color: var(--sidebar-active);"></i>CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
    
    modal.addEventListener('hidden.bs.modal', function() {
        modal.remove();
    });
}

// Refresh Dashboard
function refreshDashboard() {
    location.reload();
}

// Chart Time Range Change
document.getElementById('chartTimeRange')?.addEventListener('change', function(e) {
    const value = e.target.value;
    // Here you would fetch new data based on the selected range
    console.log('Changing chart range to:', value);
    
    // Simulate loading new data
    attendanceChart.data.datasets[0].data = value === 'week' 
        ? [<?= implode(',', $weeklyPercentages) ?>]
        : [88, 85, 90, 87, 92, 89, 91, 88, 86, 90, 93, 89, 87, 91, 88, 85, 89, 92, 90, 87, 88, 91, 89, 86, 90, 88, 87, 92, 89, 90];
    
    attendanceChart.update();
});

// Auto-refresh data every 5 minutes (optional)
setInterval(function() {
    // Fetch latest data silently
    console.log('Auto-refreshing dashboard data...');
    // Implement your refresh logic here
}, 300000);

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
});

// Animate counters
function animateCounter(element, start, end, duration) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= end) {
            current = end;
            clearInterval(timer);
        }
        element.textContent = Math.round(current).toLocaleString();
    }, 16);
}

// Start counter animations when page loads
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.counter');
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
        animateCounter(counter, 0, target, 2000);
    });
});
</script>

<!-- Custom CSS for this page -->
<style>
/* Card hover effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2) !important;
}

/* Table styles */
.table {
    --bs-table-bg: transparent;
    --bs-table-color: var(--text-primary);
}

.table-hover tbody tr:hover {
    background: var(--sidebar-hover) !important;
}

/* Progress bar animation */
.progress-bar {
    transition: width 1s ease;
}

/* Badge styles */
.badge {
    font-weight: 500;
    padding: 6px 10px;
}

/* Modal styles */
.modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
}

.modal-header {
    border-bottom: 1px solid var(--border-color);
}

.modal-footer {
    border-top: 1px solid var(--border-color);
}

.btn-close-white {
    filter: invert(1) grayscale(100%) brightness(200%);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1.25rem;
    }
    
    .table {
        font-size: 0.85rem;
    }
}

/* Loading skeleton animation */
@keyframes shimmer {
    0% {
        background-position: -1000px 0;
    }
    100% {
        background-position: 1000px 0;
    }
}

.loading-skeleton {
    background: linear-gradient(90deg, var(--card-bg) 25%, var(--sidebar-hover) 50%, var(--card-bg) 75%);
    background-size: 1000px 100%;
    animation: shimmer 2s infinite;
}
</style>

<?php
// CLOSE MAIN / BODY / HTML FROM sidebar_header.php
// This closes the main tag that was opened in sidebar_header.php
?>
</main>

<!-- Additional scripts can be placed here if needed -->

<?php
// The footer.php will handle closing the remaining tags
include('includes/footer.php');
?>