


<?php
session_start();
// if (!defined('APP_URL')) {
//     define('APP_URL', 'http://10.42.200.111/attendance');
// }
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['name'] ?? 'User';

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management System</title>

    <!-- ✅ FAVICONS (assets in ROOT) -->
    <link rel="icon" type="image/x-icon" href="/assets/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon/apple-touch-icon.png">
    <link rel="manifest" href="/assets/favicon/site.webmanifest">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-bg: #1e293b;
            --sidebar-brand-bg: #334155;
            --sidebar-hover: #334155;
            --sidebar-active: #4f46e5;
            --sidebar-active-light: #6366f1;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #334155;
        }

        body {
            background: #f8fafc;
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
        }

        /* SIDEBAR */
        nav.sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 240px;
            height: 100vh;
            background: var(--sidebar-bg);
            color: var(--text-primary);
            box-shadow: 2px 0 12px rgba(0,0,0,0.15);
            transition: transform 0.35s ease;
            z-index: 1030;
            overflow-y: auto;
        }

        nav.sidebar::-webkit-scrollbar {
            width: 5px;
        }
        nav.sidebar::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 5px;
        }

        /* Brand Header */
        .sidebar-brand {
            height: 64px;
            background: var(--sidebar-brand-bg);
            display: flex;
            align-items: center;
            padding: 0 20px;
            font-size: 1.35rem;
            font-weight: 700;
            color: white;
            letter-spacing: 0.4px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.18);
        }

        .sidebar-brand i {
            font-size: 1.6rem;
            margin-right: 12px;
        }

        /* Menu Items */
        nav.sidebar .nav-link {
            color: var(--text-secondary) !important;
            padding: 11px 20px !important;
            font-weight: 500;
            font-size: 0.94rem;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.25s ease;
            border-left: 3px solid transparent;
            margin: 1px 10px;
            border-radius: 6px;
        }

        nav.sidebar .nav-link i {
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }

        nav.sidebar .nav-link:hover {
            background: var(--sidebar-hover) !important;
            color: white !important;
            border-left-color: var(--sidebar-active-light);
            box-shadow: 0 2px 8px rgba(99,102,241,0.12);
        }

        nav.sidebar .nav-link.active {
            background: var(--sidebar-active) !important;
            color: white !important;
            border-left-color: #a5b4fc;
            font-weight: 600;
            box-shadow: 0 3px 10px rgba(99,102,241,0.22);
        }

        /* Divider */
        .sidebar-divider {
            height: 1px;
            background: #475569;
            margin: 14px 16px;
            opacity: 0.6;
        }

        /* TOP HEADER */
        header.top-header {
            position: fixed;
            top: 0;
            left: 240px;
            right: 0;
            height: 64px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            z-index: 1020;
            box-shadow: 0 1px 8px rgba(0,0,0,0.06);
            transition: left 0.35s ease;
        }

        .page-title {
            font-size: 1.35rem;
            font-weight: 600;
            color: #1e293b;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--sidebar-active);
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-btn {
            display: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: #475569;
        }

        /* MAIN CONTENT */
        main.main-content {
            margin-left: 240px;
            margin-top: 64px;
            min-height: calc(100vh - 64px);
            background: #f8fafc;
            transition: all 0.35s ease;
        }

        /* MOBILE */
        @media (max-width: 991.98px) {
            nav.sidebar {
                width: 280px;
                transform: translateX(-100%);
            }
            nav.sidebar.active {
                transform: translateX(0);
            }
            header.top-header, main.main-content {
                left: 0;
                margin-left: 0 !important;
            }
            .toggle-btn {
                display: block !important;
            }
        }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 1025;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>
<body>


<!-- Mobile Overlay -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<nav class="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-journal-check"></i> Attendance Pro
    </div>

    <ul class="nav flex-column mt-4 px-2">
        <?php if ($isLoggedIn): ?>
            <?php if ($userRole == 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($current_page, 'faculty') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/manage_faculty.php">
                        <i class="bi bi-person-badge"></i> Faculty
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($current_page, 'student') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/manage_student.php">
                        <i class="bi bi-people"></i> Students
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($current_page, 'attendance') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/manage_attendance.php">
                        <i class="bi bi-check-circle"></i> Attendance
                    </a>
                </li>
                <div class="sidebar-divider"></div>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($current_page, 'courses') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/manage_courses.php">
                        <i class="bi bi-book"></i> Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($current_page, 'year') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/manage_year.php">
                        <i class="bi bi-calendar3"></i> Semesters
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($current_page, 'sessions') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/manage_sessions.php">
                        <i class="bi bi-clock"></i> Sessions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'show_schedule.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/show_schedule.php">
                        <i class="bi bi-table"></i> Schedule
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($current_page, 'subjects') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/manage_subjects.php">
                        <i class="bi bi-journal-text"></i> Subjects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'report.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/report.php">
                        <i class="bi bi-graph-up"></i> Reports
                    </a>
                </li>

            <?php elseif ($userRole == 'faculty'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/faculty/dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'mark_attendance.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/faculty/mark_attendance.php">
                        <i class="bi bi-pencil-square"></i> Mark Attendance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'view_attendance.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/faculty/view_attendance.php">
                        <i class="bi bi-eye"></i> View Attendance
                    </a>
                </li>

            <?php elseif ($userRole == 'student'): ?>

    <li class="nav-item">
        <a class="nav-link <?= in_array($current_page, ['index.php','dashboard.php']) ? 'active' : '' ?>" 
           href="<?= APP_URL ?>/index.php">
            <i class="bi bi-house-fill"></i> Home
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= in_array($current_page, ['view_attendance.php']) ? 'active' : '' ?>" 
           href="<?= APP_URL ?>/student/view_attendance.php">
            <i class="bi bi-bar-chart-fill"></i> My Attendance
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_page == 'scan_qr.php' ? 'active' : '' ?>" 
           href="<?= APP_URL ?>/student/scan_qr.php">
            <i class="bi bi-qr-code-scan"></i> Scan QR
        </a>
    </li>
	 <li class="nav-item">
        <a class="nav-link <?= $current_page == 'location_scan.php' ? 'active' : '' ?>" 
           href="<?= APP_URL ?>/student/location_scan.php">
            <i class="bi bi-qr-code-scan"></i> Scan QR Location
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $current_page == 'attendance_history.php' ? 'active' : '' ?>" 
           href="<?= APP_URL ?>/student/attendance_history.php">
            <i class="bi bi-clock-history"></i> Attendance History
        </a>
    </li>

<?php endif; ?>

        <?php else: ?>
            <li class="nav-item">
                <a class="nav-link" href="<?= APP_URL ?>/auth/login.php">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>

<!-- Top Header -->
<header class="top-header">
    <div class="d-flex align-items-center">
        <div class="toggle-btn me-4" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </div>
        <h1 class="page-title mb-0">Dashboard</h1>
    </div>

    <div class="user-dropdown dropdown">
        <?php if ($isLoggedIn): ?>
            <a class="dropdown-toggle d-flex align-items-center gap-3 text-decoration-none" href="#" role="button" data-bs-toggle="dropdown">
                <div class="user-avatar"><?= strtoupper(substr($userName, 0, 2)) ?></div>
                <div class="text-start">
                    <div class="fw-semibold"><?= htmlspecialchars($userName) ?></div>
                    <small class="text-muted"><?= ucfirst($userRole) ?></small>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                <li><a class="dropdown-item" href="<?= APP_URL ?>/profile/profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/profile/edit-profile.php"><i class="bi bi-pencil-square me-2"></i> Edit Profile</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/profile/change-password.php"><i class="bi bi-lock me-2"></i> Change Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
        <?php else: ?>
            <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-primary">Login</a>
        <?php endif; ?>
    </div>
</header>

<!-- Main Content Area -->
<!-- Your page content goes here -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.querySelector('nav.sidebar').classList.toggle('active');
        document.querySelector('.sidebar-overlay').classList.toggle('active');
    }
</script>

</body>
</html>