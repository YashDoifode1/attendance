<?php
// admin/includes/sidebar_header.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Get admin details from database
$stmt = $pdo->prepare("SELECT name, email, profile_pic FROM students WHERE id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$adminName = $admin['name'] ?? 'Admin';
$adminEmail = $admin['email'] ?? 'admin@example.com';
$adminInitials = strtoupper(substr($adminName, 0, 2));
$current_page = basename($_SERVER['PHP_SELF']);

// Define APP_URL if not defined
if (!defined('APP_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    define('APP_URL', $protocol . $_SERVER['HTTP_HOST'] . '/attendance');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Attendance Management System</title>

    <!-- ✅ FAVICONS -->
    <link rel="icon" type="image/x-icon" href="/assets/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon/apple-touch-icon.png">
    <link rel="manifest" href="/assets/favicon/site.webmanifest">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --dark-bg: #0a0c10;
            --sidebar-bg: #0f1217;
            --sidebar-secondary: #1a1e26;
            --sidebar-hover: #232832;
            --sidebar-active: #3b82f6;
            --sidebar-active-light: #60a5fa;
            --text-primary: #f0f3f8;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #2a2f3a;
            --card-bg: #151a22;
            --header-bg: #0f1217;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--dark-bg);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.5;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--sidebar-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--sidebar-active);
        }

        /* SIDEBAR */
        nav.sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            color: var(--text-primary);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1030;
            overflow-y: auto;
            border-right: 1px solid var(--border-color);
        }

        nav.sidebar::-webkit-scrollbar {
            width: 4px;
        }

        /* Brand Header */
        .sidebar-brand {
            height: 70px;
            background: linear-gradient(145deg, var(--sidebar-secondary), var(--sidebar-bg));
            display: flex;
            align-items: center;
            padding: 0 20px;
            font-size: 1.4rem;
            font-weight: 700;
            color: white;
            letter-spacing: 0.3px;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1;
            backdrop-filter: blur(10px);
        }

        .sidebar-brand i {
            font-size: 1.8rem;
            margin-right: 12px;
            color: var(--sidebar-active);
            filter: drop-shadow(0 2px 4px rgba(59, 130, 246, 0.3));
        }

        .sidebar-brand span {
            background: linear-gradient(135deg, #fff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* User Profile in Sidebar */
        .sidebar-user {
            padding: 20px 16px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(145deg, rgba(26, 30, 38, 0.5), transparent);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar-large {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--sidebar-active), var(--sidebar-active-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .user-details {
            flex: 1;
        }

        .user-details h6 {
            margin: 0;
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-primary);
        }

        .user-details p {
            margin: 4px 0 0;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .user-status {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: var(--success);
            border-radius: 50%;
            margin-right: 6px;
            box-shadow: 0 0 8px var(--success);
        }

        /* Menu Sections */
        .nav-section {
            padding: 20px 16px 8px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        /* Menu Items */
        nav.sidebar .nav {
            padding: 8px 12px;
        }

        nav.sidebar .nav-link {
            color: var(--text-secondary) !important;
            padding: 12px 16px !important;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
            border-radius: 10px;
            margin: 2px 0;
            position: relative;
            overflow: hidden;
        }

        nav.sidebar .nav-link i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
            color: var(--text-muted);
            transition: all 0.2s ease;
        }

        nav.sidebar .nav-link:hover {
            background: var(--sidebar-hover) !important;
            color: white !important;
            transform: translateX(4px);
        }

        nav.sidebar .nav-link:hover i {
            color: var(--sidebar-active-light);
        }

        nav.sidebar .nav-link.active {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.15), transparent) !important;
            color: white !important;
            border-left: 3px solid var(--sidebar-active);
        }

        nav.sidebar .nav-link.active i {
            color: var(--sidebar-active);
        }

        nav.sidebar .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--sidebar-active);
            border-radius: 0 2px 2px 0;
        }

        /* Divider */
        .sidebar-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border-color), transparent);
            margin: 16px 16px;
        }

        /* Badge */
        .nav-badge {
            background: var(--sidebar-active);
            color: white;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 20px;
            margin-left: auto;
        }

        /* TOP HEADER */
        header.top-header {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            height: 70px;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            z-index: 1020;
            backdrop-filter: blur(10px);
            transition: left 0.3s ease;
        }

        .page-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title i {
            color: var(--sidebar-active);
            font-size: 1.5rem;
        }

        .page-title span {
            background: linear-gradient(135deg, var(--sidebar-active), var(--sidebar-active-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            margin-left: 4px;
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Search Box */
        .search-box {
            background: var(--sidebar-secondary);
            border-radius: 30px;
            padding: 8px 18px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            width: 320px;
            transition: all 0.3s ease;
        }

        .search-box:focus-within {
            border-color: var(--sidebar-active);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            background: var(--sidebar-hover);
        }

        .search-box input {
            border: none;
            background: transparent;
            padding: 0 10px;
            width: 100%;
            outline: none;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .search-box input::placeholder {
            color: var(--text-muted);
        }

        .search-box i {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .search-shortcut {
            font-size: 0.7rem;
            background: var(--sidebar-bg);
            padding: 4px 8px;
            border-radius: 6px;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }

        /* Notification Bell */
        .notification-wrapper {
            position: relative;
        }

        .notification-badge {
            position: relative;
            font-size: 1.3rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
            background: var(--sidebar-secondary);
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-badge:hover {
            background: var(--sidebar-hover);
            color: var(--sidebar-active);
        }

        .notification-badge .badge-count {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--danger);
            color: white;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 3px 6px;
            border-radius: 20px;
            border: 2px solid var(--header-bg);
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* User Dropdown */
        .user-dropdown .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 12px;
            border-radius: 40px;
            background: var(--sidebar-secondary);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .user-dropdown .dropdown-toggle:hover {
            background: var(--sidebar-hover);
            border-color: var(--sidebar-active);
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 40px;
            background: linear-gradient(135deg, var(--sidebar-active), var(--sidebar-active-light));
            color: white;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .user-info-text {
            text-align: left;
        }

        .user-info-text .name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .user-info-text .role {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        /* Dropdown Menu */
        .dropdown-menu {
            background: var(--sidebar-secondary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 8px;
            margin-top: 10px;
            box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.5);
        }

        .dropdown-item {
            padding: 10px 16px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .dropdown-item i {
            font-size: 1rem;
            color: var(--text-muted);
        }

        .dropdown-item:hover {
            background: var(--sidebar-hover);
            color: white;
            transform: translateX(4px);
        }

        .dropdown-item:hover i {
            color: var(--sidebar-active);
        }

        .dropdown-item.text-danger:hover {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger) !important;
        }

        .dropdown-divider {
            border-top: 1px solid var(--border-color);
            margin: 8px 0;
        }

        /* Toggle Button */
        .toggle-btn {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 8px 12px;
            border-radius: 10px;
            transition: all 0.3s ease;
            background: var(--sidebar-secondary);
        }

        .toggle-btn:hover {
            background: var(--sidebar-hover);
            color: var(--sidebar-active);
        }

        /* MAIN CONTENT */
        main.main-content {
            margin-left: 260px;
            margin-top: 70px;
            min-height: calc(100vh - 70px);
            background: var(--dark-bg);
            transition: all 0.3s ease;
            padding: 24px;
        }

        /* Overlay */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 1025;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-body {
            color: var(--text-secondary);
        }

        /* MOBILE RESPONSIVE */
        @media (max-width: 991.98px) {
            nav.sidebar {
                width: 280px;
                transform: translateX(-100%);
            }
            
            nav.sidebar.active {
                transform: translateX(0);
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.6);
            }
            
            header.top-header {
                left: 0 !important;
            }
            
            main.main-content {
                margin-left: 0 !important;
            }
            
            .toggle-btn {
                display: flex !important;
                align-items: center;
                justify-content: center;
            }
            
            .search-box {
                width: 200px;
            }
            
            .user-info-text {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .search-box {
                display: none !important;
            }
            
            header.top-header {
                padding: 0 16px;
            }
            
            .page-title {
                font-size: 1.1rem;
            }
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .main-content {
            animation: slideIn 0.4s ease;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .notification-badge.has-new {
            animation: pulse 2s infinite;
        }

        /* Quick Stats Widget (optional) */
        .quick-stats {
            display: flex;
            gap: 12px;
            margin-left: 20px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--sidebar-secondary);
            padding: 6px 12px;
            border-radius: 30px;
            border: 1px solid var(--border-color);
            font-size: 0.8rem;
        }

        .stat-item i {
            color: var(--sidebar-active);
        }

        .stat-value {
            font-weight: 600;
            color: var(--text-primary);
        }
    </style>
</head>
<body>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<nav class="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-journal-check"></i>
        <span>Attendance Pro</span>
    </div>

    <!-- User Profile in Sidebar (Mobile friendly) -->
    <div class="sidebar-user d-lg-none">
        <div class="user-info">
            <div class="user-avatar-large"><?= $adminInitials ?></div>
            <div class="user-details">
                <h6><?= htmlspecialchars($adminName) ?></h6>
                <p>
                    <span class="user-status"></span>
                    Administrator
                </p>
            </div>
        </div>
    </div>

    <!-- Main Navigation -->
    <div class="nav-section">MAIN</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/dashboard.php">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
                <?php if ($current_page == 'dashboard.php'): ?>
                    <span class="nav-badge">NEW</span>
                <?php endif; ?>
            </a>
        </li>
    </ul>

    <div class="nav-section">USER MANAGEMENT</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'faculty') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/faculty.php">
                <i class="bi bi-person-badge"></i>
                <span>Faculty</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'student') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/student.php">
                <i class="bi bi-people"></i>
                <span>Students</span>
            </a>
        </li>
    </ul>

    <div class="nav-section">ACADEMIC</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'courses') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/courses.php">
                <i class="bi bi-book"></i>
                <span>Courses</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'subjects') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/subjects.php">
                <i class="bi bi-journal-text"></i>
                <span>Subjects</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'year') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/year.php">
                <i class="bi bi-calendar3"></i>
                <span>Semesters</span>
            </a>
        </li>
    </ul>

    <div class="nav-section">ATTENDANCE</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'attendance') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/attendance.php">
                <i class="bi bi-check-circle"></i>
                <span>Attendance</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'sessions') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/sessions.php">
                <i class="bi bi-clock"></i>
                <span>Sessions</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'show_schedule.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/show_schedule.php">
                <i class="bi bi-table"></i>
                <span>Schedule</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-divider"></div>

    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'report.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/report.php">
                <i class="bi bi-graph-up"></i>
                <span>Reports & Analytics</span>
            </a>
        </li>
    </ul>

    <!-- Sidebar Footer -->
    <div class="sidebar-divider"></div>
    <div class="p-3 text-center" style="color: var(--text-muted); font-size: 0.75rem;">
        <i class="bi bi-shield-check me-1"></i> Admin v2.0.0
    </div>
</nav>

<!-- Top Header -->
<header class="top-header">
    <div class="d-flex align-items-center">
        <div class="toggle-btn me-3" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </div>
        <h1 class="page-title mb-0">
            <i class="bi bi-grid-fill"></i>
            <?php
            // Dynamic page title
            $pageTitle = str_replace(['.php', '_', 'manage', 'show'], ['', ' ', '', ''], $current_page);
            $pageTitle = ucwords(trim($pageTitle));
            if (empty($pageTitle) || $pageTitle == 'Dashboard') {
                echo 'Dashboard';
            } else {
                echo $pageTitle;
            }
            ?>
            <span>• Admin</span>
        </h1>

        <!-- Quick Stats (optional) -->
        <div class="quick-stats d-none d-xl-flex">
            <div class="stat-item">
                <i class="bi bi-people"></i>
                <span class="stat-value">156</span>
                <span class="text-muted">Students</span>
            </div>
            <div class="stat-item">
                <i class="bi bi-person-badge"></i>
                <span class="stat-value">24</span>
                <span class="text-muted">Faculty</span>
            </div>
        </div>
    </div>

    <div class="header-actions">
        <!-- Search Box -->
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Search..." id="globalSearch">
            <span class="search-shortcut">⌘K</span>
        </div>

        <!-- Notifications -->
        <div class="notification-wrapper">
            <div class="notification-badge" onclick="toggleNotifications()" id="notificationBtn">
                <i class="bi bi-bell"></i>
                <span class="badge-count">3</span>
            </div>
        </div>

        <!-- User Dropdown -->
        <div class="user-dropdown dropdown">
            <a class="dropdown-toggle text-decoration-none" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar"><?= $adminInitials ?></div>
                <div class="user-info-text">
                    <div class="name"><?= htmlspecialchars($adminName) ?></div>
                    <div class="role">Administrator</div>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/profile/profile.php"><i class="bi bi-person"></i> Profile</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/profile/edit-profile.php"><i class="bi bi-pencil-square"></i> Edit Profile</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/profile/change-password.php"><i class="bi bi-lock"></i> Change Password</a></li>
                <!-- <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/profile/settings.php"><i class="bi bi-gear"></i> Settings</a></li> -->
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</header>

<!-- Main Content Area -->
<main class="main-content">