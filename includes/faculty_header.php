<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../auth/login.php");
    exit();
}

$faculty_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Faculty Portal - <?= ucwords(str_replace(['_', '.php'], ' ', basename($_SERVER['PHP_SELF']))) ?></title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
body {
    background:#f8f9fa;
}

/* Sidebar */
.sidebar {
    position:fixed;
    top:0; left:0;
    width:250px;
    height:100vh;
    background:#212529;
    color:#fff;
    z-index:1000;
    transition:all .3s;
}
.sidebar .nav-link {
    color:#adb5bd;
    padding:12px 20px;
    border-radius:8px;
    margin:4px 12px;
}
.sidebar .nav-link.active,
.sidebar .nav-link:hover {
    background:#0d6efd;
    color:#fff;
}
.main-content {
    margin-left:250px;
    min-height:100vh;
    transition:margin .3s;
}

/* Top Navbar */
.top-navbar {
    background:#fff;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
    padding:12px 20px;
    position:sticky;
    top:0;
    z-index:900;
}

/* Search */
.search-box {
    max-width:350px;
}

/* Mobile */
@media (max-width:991px) {
    .sidebar { left:-250px; }
    .sidebar.show { left:0; }
    .main-content { margin-left:0; }
}
</style>
</head>

<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="px-4 py-3 d-flex align-items-center">
        <i class="bi bi-mortarboard-fill fs-3 me-2"></i>
        <span class="fs-5 fw-bold">Faculty Portal</span>
    </div>
    <hr class="border-secondary mx-3">

    <ul class="nav flex-column">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="generate_qr.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='generate_qr.php'?'active':'' ?>">
                <i class="bi bi-qr-code-scan me-2"></i> Generate QR
            </a>
        </li>
        <li class="nav-item">
            <a href="location.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='location.php'?'active':'' ?>">
                <i class="bi bi-qr-code-scan me-2"></i>Location Based Generate QR
            </a>
        </li>
        <li class="nav-item">
            <a href="mark_attendance.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='mark_attendance.php'?'active':'' ?>">
                <i class="bi bi-pencil-square me-2"></i> Mark Attendance
            </a>
        </li>
        <li class="nav-item">
            <a href="view_attendance.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='view_attendance.php'?'active':'' ?>">
                <i class="bi bi-table me-2"></i> View Attendance
            </a>
        </li>
    </ul>

    <div class="position-absolute bottom-0 w-100 px-3 pb-3">
        <hr class="border-secondary">
        <a href="../auth/logout.php" class="btn btn-outline-light w-100">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">

<!-- Top Navbar -->
<header class="top-navbar d-flex align-items-center justify-content-between">

    <!-- Left -->
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-outline-primary d-lg-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="mb-0 text-primary fw-semibold">
            <?= ucwords(str_replace('_',' ',basename($_SERVER['PHP_SELF'],'.php'))) ?>
        </h5>
    </div>

    <!-- Center Search -->
    <form class="search-box d-none d-md-block">
        <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" placeholder="Search subjects, classes...">
        </div>
    </form>

    <!-- Right Profile -->
    <div class="dropdown">
        <button class="btn btn-light d-flex align-items-center gap-2 dropdown-toggle"
                data-bs-toggle="dropdown">
            <i class="bi bi-person-circle fs-5"></i>
            <span id="facultyNameDisplay">Faculty</span>
        </button>

        <ul class="dropdown-menu dropdown-menu-end shadow">
            <li>
                <a class="dropdown-item" href="profile.php">
                    <i class="bi bi-person me-2"></i> Profile
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="#">
                    <i class="bi bi-gear me-2"></i> Settings
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item text-danger" href="../auth/logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>

</header>

<!-- Page Content -->
<div class="container-fluid py-4">
