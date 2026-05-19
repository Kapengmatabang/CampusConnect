<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$database = new mysqli("localhost", "root", "", "campusconnect");

if ($database->connect_error) {
    die("Database connection failed: " . $database->connect_error);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullname   = trim($_POST['fullname'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $course_in  = strtoupper(trim($_POST['course'] ?? ''));
    $age        = trim($_POST['age'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    if (empty($fullname) || empty($email) || empty($student_id) || empty($course_in) || empty($age) || empty($password)) {
        $error = "All fields are required.";
    }
    elseif (!preg_match("/^[0-9]{8}$/", $student_id)) {
        $error = "Student ID must be 8 digits.";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    }
    else {

        $parts = explode(" ", $course_in);
        $course = $parts[0] ?? '';
        $section = $parts[1] ?? '';

        if (empty($course) || empty($section)) {
            $error = "Use format: BSIT 3S";
        } else {

            $check = $database->prepare("SELECT student_id FROM students WHERE student_id=? OR email=?");
            $check->bind_param("ss", $student_id, $email);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = "Student already exists.";
            } else {

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $insert = $database->prepare("
                    INSERT INTO students
                    (student_id, full_name, email, course, section, age, password)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $insert->bind_param("sssssis",
                    $student_id,
                    $fullname,
                    $email,
                    $course,
                    $section,
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
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Sign Up</title>
<link rel="icon" type="image/png" href="img/favicon.png">

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #0f172a, #1e293b);
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    color: white;
}

/* LOGO */
.logo-img {
    position: absolute;
    top: 20px;
    left: 20px;
    width: 140px;
}

/* GLASS CARD */
.container {
    width: 380px;
    padding: 30px;
    border-radius: 18px;
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255,255,255,0.15);
    box-shadow: 0 10px 40px rgba(0,0,0,0.4);
    animation: fadeUp 0.6s ease;
}

/* TITLE */
h2 {
    text-align: center;
    margin-bottom: 10px;
}

.sub {
    text-align: center;
    font-size: 13px;
    color: #aaa;
    margin-bottom: 20px;
}

/* INPUTS */
.input {
    width: 100%;
    padding: 12px;
    margin: 8px 0;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.05);
    color: white;
    outline: none;
    transition: 0.3s;
}

.input:focus {
    border-color: #22c55e;
    background: rgba(255,255,255,0.1);
}

/* BUTTON */
.btn {
    width: 100%;
    padding: 12px;
    margin-top: 10px;
    border-radius: 30px;
    border: none;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(34,197,94,0.4);
}

/* ERROR */
.error {
    background: #ef4444;
    padding: 10px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 10px;
}

/* LINK */
.login-link {
    text-align: center;
    margin-top: 15px;
    font-size: 13px;
}

.login-link a {
    color: #22c55e;
    text-decoration: none;
}

/* ANIMATION */
@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
</head>

<body>

<a href="index.html">
<img src="img/cclogo.png" class="logo-img">
</a>

<div class="container">

<h2>Create Account</h2>
<p class="sub">Join CampusConnect</p>

<?php if ($error): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

<input type="text" name="fullname" placeholder="Full Name" class="input" required>
<input type="email" name="email" placeholder="Email" class="input" required>
<input type="text" name="student_id" placeholder="Student ID (8 digits)" class="input" required>
<input type="text" name="course" placeholder="BSIT 3S" class="input" required>
<input type="number" name="age" placeholder="Age" class="input" required>
<input type="password" name="password" placeholder="Password" class="input" required>

<button class="btn">Sign Up</button>

</form>

<div class="login-link">
Already have an account? <a href="login.php">Login</a>
</div>

</div>

</body>
</html>