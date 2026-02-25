<?php
include('../config/db.php');
include('../includes/faculty_header.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ===============================
   FETCH USER DATA
================================ */
$stmt = $pdo->prepare("
    SELECT s.name, s.email, s.profile_photo, s.course_id, s.year_id, s.session_id,
           c.course_name, y.year_name, sess.session_name
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN years y ON s.year_id = y.id
    LEFT JOIN sessions sess ON s.session_id = sess.id
    WHERE s.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die("User not found");

/* ===============================
   DROPDOWN DATA
================================ */
$courses  = $pdo->query("SELECT id, course_name FROM courses ORDER BY course_name")->fetchAll(PDO::FETCH_ASSOC);
$years    = $pdo->query("SELECT id, year_name FROM years ORDER BY year_name")->fetchAll(PDO::FETCH_ASSOC);
$sessions = $pdo->query("SELECT id, session_name FROM sessions ORDER BY session_name")->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   UPDATE PROFILE
================================ */
$success_message = $error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name       = trim($_POST['name']);
    $email      = trim($_POST['email']);
    $course_id  = $_POST['course_id'];
    $year_id    = $_POST['year_id'];
    $session_id = $_POST['session_id'];

    if (!$name || !$email) {
        $error_message = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email address.";
    } else {

        $check = $pdo->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
        $check->execute([$email, $user_id]);

        if ($check->fetch()) {
            $error_message = "Email already in use.";
        } else {

            /* PHOTO UPLOAD */
            $photo = $user['profile_photo'];
            if (!empty($_FILES['profile_photo']['name'])) {

                $allowed = ['jpg','jpeg','png'];
                $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed) || $_FILES['profile_photo']['size'] > 2*1024*1024) {
                    $error_message = "Photo must be JPG/PNG and under 2MB.";
                } else {
                    $dir = "../uploads/profiles/";
                    if (!is_dir($dir)) mkdir($dir, 0755, true);

                    $photo = "user_{$user_id}.{$ext}";
                    move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir.$photo);
                }
            }

            if (!$error_message) {
                $update = $pdo->prepare("
                    UPDATE students 
                    SET name=?, email=?, profile_photo=?, course_id=?, year_id=?, session_id=? 
                    WHERE id=?
                ");

                $update->execute([$name,$email,$photo,$course_id,$year_id,$session_id,$user_id]);

                $_SESSION['name'] = $name;
                $user = array_merge($user, $_POST);
                $user['profile_photo'] = $photo;

                $success_message = "Profile updated successfully!";
            }
        }
    }
}

$photoPath = $user['profile_photo']
    ? "../uploads/profiles/{$user['profile_photo']}?v=".time()
    : "https://via.placeholder.com/180?text=No+Photo";
?>

<!-- ===============================
   PAGE CONTENT
================================ -->
<main class="main-content">
<div class="px-4 pt-4 pb-5">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title mb-0">✏️ Edit Profile</h2>
    <a href="profile.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if ($success_message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= $success_message ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= $error_message ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<div class="row g-4">

<!-- LEFT -->
<div class="col-lg-4">
<div class="card shadow-sm border-0 h-100 text-center">
<div class="card-body p-4">

<img src="<?= $photoPath ?>" id="profilePreview"
     class="rounded-circle shadow mb-3"
     style="width:180px;height:180px;object-fit:cover;">

<label class="btn btn-outline-primary btn-sm mt-2">
    <i class="bi bi-camera"></i> Change Photo
    <input type="file" name="profile_photo" hidden onchange="previewImage(this)">
</label>

<hr>

<input class="form-control form-control-lg mb-3" name="name"
       value="<?= htmlspecialchars($user['name']) ?>" placeholder="Full Name" required>

<input class="form-control form-control-lg" name="email"
       value="<?= htmlspecialchars($user['email']) ?>" placeholder="Email" required>

</div>
</div>
</div>

<!-- RIGHT -->
<div class="col-lg-8">
<div class="card shadow-sm border-0 h-100">
<div class="card-body p-5">

<h4 class="fw-bold text-primary mb-4">Academic Details</h4>

<div class="mb-4">
<label class="fw-semibold">Course</label>
<select name="course_id" class="form-select form-select-lg" required>
<?php foreach ($courses as $c): ?>
<option value="<?= $c['id'] ?>" <?= $user['course_id']==$c['id']?'selected':'' ?>>
<?= htmlspecialchars($c['course_name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-4">
<label class="fw-semibold">Semester</label>
<select name="year_id" class="form-select form-select-lg" required>
<?php foreach ($years as $y): ?>
<option value="<?= $y['id'] ?>" <?= $user['year_id']==$y['id']?'selected':'' ?>>
<?= htmlspecialchars($y['year_name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-5">
<label class="fw-semibold">Session</label>
<select name="session_id" class="form-select form-select-lg" required>
<?php foreach ($sessions as $s): ?>
<option value="<?= $s['id'] ?>" <?= $user['session_id']==$s['id']?'selected':'' ?>>
<?= htmlspecialchars($s['session_name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="text-end">
<button class="btn btn-primary btn-lg px-5">
<i class="bi bi-check-circle"></i> Save Changes
</button>
</div>

</div>
</div>
</div>

</div>
</form>

</div>
</main>

<script>
function previewImage(input) {
    const reader = new FileReader();
    reader.onload = e => document.getElementById('profilePreview').src = e.target.result;
    reader.readAsDataURL(input.files[0]);
}
</script>
<?php include('../includes/faculty_footer.php');?>