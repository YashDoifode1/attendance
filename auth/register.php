<?php
session_start();
include('../config/db.php');

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../student/dashboard.php");
    exit();
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $course_id  = $_POST['course'] ?? '';
    $session_id = $_POST['session'] ?? '';
    $year_id    = $_POST['year'] ?? '';

    if (empty($name) || empty($email) || empty($password) || 
        empty($course_id) || empty($session_id) || empty($year_id)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = "This email is already registered. Please login.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO students 
                (name, email, password, role, course_id, session_id, year_id, created_at) 
                VALUES (?, ?, ?, 'student', ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$name, $email, $hashedPassword, $course_id, $session_id, $year_id])) {
                $success = true;
            } else {
                $error = "Registration failed. Please try again later.";
            }
        }
    }
}

// Load dropdown options
$courses  = $pdo->query("SELECT id, course_name FROM courses ORDER BY course_name")->fetchAll();
$sessions = $pdo->query("SELECT id, session_name FROM sessions ORDER BY session_name")->fetchAll();
$years    = $pdo->query("SELECT id, year_name FROM years ORDER BY year_name DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Registration | Attendance Pro</title>

<!-- Favicon -->
<link rel="icon" href="../assets/favicon.png" type="image/png">

<!-- Fonts & Icons -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
:root {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --gray-50: #f9fafb;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-900: #111827;
    --danger: #dc2626;
    --success: #16a34a;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #f0f7ff, #e0f2fe);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    color: var(--gray-900);
}

.register-container {
    width: 100%;
    max-width: 500px;
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
}

.header {
    background: var(--primary);
    color: white;
    padding: 2.5rem 2rem 2rem;
    text-align: center;
}

.header h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; }
.header p { opacity: 0.9; font-size: 0.95rem; }

.form-content { padding: 2rem; }

.form-group { margin-bottom: 1.4rem; }
label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--gray-700); font-size: 0.9rem; }
input, select {
    width: 100%;
    padding: 0.9rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    font-size: 1rem;
    background: var(--gray-50);
    transition: all 0.2s;
}
input:focus, select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.12);
    background: white;
}

.alert {
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}
.alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

.submit-btn {
    width: 100%;
    padding: 1rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 1.05rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s ease;
    margin-top: 1.2rem;
}
.submit-btn:hover:not(:disabled) { background: var(--primary-dark); transform: translateY(-1px); }
.submit-btn:disabled { opacity: 0.7; cursor: not-allowed; }

.login-link {
    text-align: center;
    margin-top: 1.6rem;
    font-size: 0.92rem;
    color: var(--gray-600);
}
.login-link a { color: var(--primary); font-weight: 600; text-decoration: none; }
.login-link a:hover { text-decoration: underline; }

@media (max-width: 480px) {
    .form-content { padding: 1.6rem; }
    .header { padding: 2rem 1.5rem 1.8rem; }
}
</style>
</head>
<body>

<div class="register-container">
    <div class="header">
        <h1>Create Student Account</h1>
        <p>Attendance Management System</p>
    </div>

    <div class="form-content">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>Registration successful!</strong><br>
                Please <a href="login.php">login</a> to continue.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" id="registerForm" novalidate>
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" name="name" id="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="email">College Email</label>
                <input type="email" name="email" id="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required minlength="8">
            </div>

            <div class="form-group">
                <label for="course">Course</label>
                <select name="course" id="course" required>
                    <option value="">Select your course</option>
                    <?php foreach($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($_POST['course'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['course_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="session">Academic Session</label>
                <select name="session" id="session" required>
                    <option value="">Select session</option>
                    <?php foreach($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ($_POST['session'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['session_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="year">Current Year / Semester</label>
                <select name="year" id="year" required>
                    <option value="">Select year/semester</option>
                    <?php foreach($years as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= ($_POST['year'] ?? '') == $y['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($y['year_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">
                Create Account
            </button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('registerForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
    btn.disabled = true;
});
</script>
</body>
</html>
