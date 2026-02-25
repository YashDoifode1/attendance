<?php
session_start();
include('../config/db.php');

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'student';
    header("Location: ../$role/dashboard.php");
    exit();
}

$error = '';
$email_error = '';
$password_error = '';
$role_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? '';

    if (empty($email)) $email_error = "Email is required.";
    if (empty($password)) $password_error = "Password is required.";
    if (!in_array($role, ['admin', 'faculty', 'student'])) {
        $role_error = "Please select a valid role.";
    }

    if (empty($email_error) && empty($password_error) && empty($role_error)) {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ? AND role = ?");
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == 'faculty') {
                $_SESSION['faculty_id'] = $user['id'];
            }

            header("Location: ../" . $user['role'] . "/dashboard.php");
            exit();
        } else {
            $error = "Invalid email, password, or role.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Attendance Management System</title>

<!-- Favicon -->
<link rel="icon" href="../assets/favicon.png" type="image/png">

<!-- Bootstrap 5 & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --primary: #6366f1;
    --primary-hover: #4f46e5;
    --text: #1f2937;
    --bg: #f8fafc;
}

body {
    font-family: 'Poppins', sans-serif;
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.container {
    display: flex;
    flex-wrap: wrap;
    max-width: 900px;
    width: 100%;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.illustration {
    flex: 1 1 40%;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.illustration img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
}

.login-card {
    flex: 1 1 60%;
    padding: 3rem 2rem;
    min-width: 300px;
}

.header {
    text-align: center;
    margin-bottom: 2rem;
}

.header i {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

h1 {
    font-size: 1.8rem;
    color: var(--text);
    margin-bottom: 0.5rem;
}

p.subtitle {
    color: #6b7280;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.25rem;
}

label {
    font-weight: 600;
    display: block;
    margin-bottom: 0.5rem;
}

input[type="email"],
input[type="password"] {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 1rem;
}

input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
}

.error-text {
    font-size: 0.875rem;
    color: #ef4444;
    margin-top: 0.25rem;
}

.role-selection {
    display: flex;
    gap: 1rem;
    margin: 1.5rem 0;
}

.role-option {
    flex: 1;
    text-align: center;
}

.role-option input { display: none; }

.role-option label {
    display: block;
    padding: 1rem;
    border: 2px solid #d1d5db;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.role-option input:checked + label {
    border-color: var(--primary);
    background: #eff6ff;
    color: var(--primary);
    font-weight: 600;
}

.forgot {
    text-align: right;
    margin-bottom: 1.5rem;
}

.forgot a {
    color: var(--primary);
    font-size: 0.875rem;
    text-decoration: none;
}

.forgot a:hover {
    text-decoration: underline;
}

button {
    width: 100%;
    padding: 0.875rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}

button:hover { background: var(--primary-hover); }

.general-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    font-size: 0.875rem;
}

.register-link {
    text-align: center;
    margin-top: 1.5rem;
    font-size: 0.875rem;
    color: #6b7280;
}

.register-link a {
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
}

.register-link a:hover {
    text-decoration: underline;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .container {
        flex-direction: column;
        margin: 1rem;
    }
    .illustration { display: none; }
    .role-selection { flex-direction: column; }
}
</style>
</head>
<body>

<div class="container">
    <div class="illustration">
        <img src="https://static.vecteezy.com/system/resources/thumbnails/001/879/641/small/landing-page-design-of-good-education-system-that-make-student-better-in-learning-and-increase-a-creativity-and-enjoy-studying-developing-knowledge-intelligence-designed-for-website-mobile-apps-free-vector.jpg" alt="Attendance Illustration">
    </div>

    <div class="login-card">
        <div class="header">
            <i class="fas fa-shield-alt"></i>
            <h1>Welcome Back</h1>
            <p class="subtitle">Attendance Management System</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="general-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                <?php if (!empty($email_error)): ?><div class="error-text"><?= $email_error ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
                <?php if (!empty($password_error)): ?><div class="error-text"><?= $password_error ?></div><?php endif; ?>
            </div>

            <div class="forgot">
                <a href="#">Forgot Password?</a>
            </div>

            <div class="role-selection">
                <div class="role-option">
                    <input type="radio" name="role" value="admin" id="admin" <?= (($_POST['role'] ?? '') == 'admin') ? 'checked' : '' ?> required>
                    <label for="admin"><i class="fas fa-user-shield"></i><br>Admin</label>
                </div>
                <div class="role-option">
                    <input type="radio" name="role" value="faculty" id="faculty" <?= (($_POST['role'] ?? '') == 'faculty') ? 'checked' : '' ?>>
                    <label for="faculty"><i class="fas fa-chalkboard-teacher"></i><br>Faculty</label>
                </div>
                <div class="role-option">
                    <input type="radio" name="role" value="student" id="student" <?= (($_POST['role'] ?? '') == 'student') ? 'checked' : '' ?>>
                    <label for="student"><i class="fas fa-user-graduate"></i><br>Student</label>
                </div>
            </div>

            <?php if (!empty($role_error)): ?><div class="error-text"><?= $role_error ?></div><?php endif; ?>

            <button type="submit" id="submitBtn">Sign In</button>
        </form>

        <div class="register-link">
            New student? <a href="register.php">Register here</a>
        </div>
    </div>
</div>

<script>
const form = document.getElementById('loginForm');
const btn = document.getElementById('submitBtn');

form.addEventListener('submit', function() {
    btn.textContent = 'Signing in...';
    btn.disabled = true;
});
</script>

</body>
</html>
