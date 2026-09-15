<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$database = new mysqli("localhost", "root", "", "campusconnect");

if ($database->connect_error) {
    die("Database connection failed: " . $database->connect_error);
}

$error = "";

// Only students with this institutional email domain may sign up
$allowedDomain = "@plmun.edu.ph";

// Keep whatever the student typed/picked so the form doesn't clear on error
$old = [
    'fullname'   => '',
    'email'      => '',
    'student_id' => '',
    'course'     => '',
    'year'       => '',
    'section'    => '',
    'age'        => '',
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullname   = trim($_POST['fullname'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $course     = strtoupper(trim($_POST['course'] ?? ''));
    $year       = trim($_POST['year'] ?? '');
    $section    = strtoupper(trim($_POST['section'] ?? ''));
    $age        = trim($_POST['age'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    $old = [
        'fullname'   => $fullname,
        'email'      => $email,
        'student_id' => $student_id,
        'course'     => $course,
        'year'       => $year,
        'section'    => $section,
        'age'        => $age,
    ];

    if (empty($fullname) || empty($email) || empty($student_id) || empty($course) || empty($year) || empty($section) || empty($age) || empty($password)) {
        $error = "All fields are required.";
    }
    elseif (!preg_match("/^[0-9]{8}$/", $student_id)) {
        $error = "Student ID must be 8 digits.";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    }
    elseif (!str_ends_with(strtolower($email), strtolower($allowedDomain))) {
        $error = "You must sign up using your PLMUN institutional email ($allowedDomain).";
    }
    elseif (!preg_match("/^[A-Z]{2,10}$/", $course)) {
        $error = "Course code should be letters only, e.g. BSIT.";
    }
    elseif (!in_array($year, ['1', '2', '3', '4'], true)) {
        $error = "Please select a valid year (1-4).";
    }
    elseif (!preg_match("/^[A-Z]$/", $section)) {
        $error = "Please select a valid section (A-Z).";
    }
    else {

        // Combine into the stored format, e.g. course="BSIT", section="3O"
        $yearSection = $year . $section;

        $check = $database->prepare("SELECT student_id FROM students WHERE student_id=? OR email=?");
        $check->bind_param("ss", $student_id, $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "Student already exists.";
        } else {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Email verification removed — accounts are now active immediately
            // (is_verified = 1 right away, no verification_token needed).
            $insert = $database->prepare("
                INSERT INTO students
                (student_id, full_name, email, course, section, age, password, is_verified)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");

            $insert->bind_param("sssssis",
                $student_id,
                $fullname,
                $email,
                $course,
                $yearSection,
                $age,
                $hashed_password
            );

            if ($insert->execute()) {
                header("Location: login.php?registered=1");
                exit();
            } else {
                $error = "Insert failed.";
            }

            $insert->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Sign Up</title>
<link rel="icon" type="image/png" href="img/favicon.png">

<link rel="stylesheet" href="css/signup.css">
</head>

<body>

<a href="index.html" class="back-btn">
    <svg viewBox="0 0 24 24">
        <path d="M15 18l-6-6 6-6"
              stroke-linecap="round"
              stroke-linejoin="round"/>
    </svg>
    Back
</a>

<div class="container">

<h2>Create Account</h2>


<?php if ($error): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

<input type="text" name="fullname" placeholder="Full Name" class="input" value="<?= htmlspecialchars($old['fullname']) ?>" required>
<input type="email" name="email" placeholder="Email" class="input" value="<?= htmlspecialchars($old['email']) ?>" required>
<div class="hint">Use your Institional Email</div>
<input type="text" name="student_id" placeholder="Student ID (8 digits)" class="input" value="<?= htmlspecialchars($old['student_id']) ?>" required>

<div class="course-row">
    <input type="text" name="course" placeholder="Course (e.g. BSIT)" class="input course-field" value="<?= htmlspecialchars($old['course']) ?>" required>

    <select name="year" class="input year-field" required>
        <option value="" disabled <?= $old['year'] === '' ? 'selected' : '' ?>>Year</option>
        <?php for ($y = 1; $y <= 4; $y++): ?>
            <option value="<?= $y ?>" <?= $old['year'] == $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
    </select>

    <select name="section" class="input section-field" required>
        <option value="" disabled <?= $old['section'] === '' ? 'selected' : '' ?>>Sec</option>
        <?php for ($s = 65; $s <= 90; $s++): $letter = chr($s); ?>
            <option value="<?= $letter ?>" <?= $old['section'] === $letter ? 'selected' : '' ?>><?= $letter ?></option>
        <?php endfor; ?>
    </select>
</div>
<div class="hint">Course</div>

<input type="number" name="age" placeholder="Age" class="input" value="<?= htmlspecialchars($old['age']) ?>" required>
<input type="password" name="password" placeholder="Password" class="input" required>

<button class="btn">Sign Up</button>

</form>

<div class="login-link">
Already have an account? <a href="login.php">Login</a>
</div>

<a href="admin_signup.php" class="switch-btn">
    <svg viewBox="0 0 24 24">
        <path d="M4 8h14M18 8l-3.5-3.5M18 8l-3.5 3.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M20 16H6M6 16l3.5-3.5M6 16l3.5 3.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    Switch to Admin Registration
</a>

</div>

<script>
document.getElementById("backBtn").addEventListener("click", function(e) {
    e.preventDefault();
    if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) {
        window.history.back();
    } else {
        window.location.href = "index.html";
    }
});
</script>

</body>
</html>