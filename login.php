<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

// DATABASE
$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB failed");
$error = "";
$submitted = false;

// LOGIN
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted = true;
    $userid   = trim($_POST['userid'] ?? '');
    $password = trim($_POST['userpassword'] ?? '');
    if (empty($userid) || empty($password)) {
        $error = "Please fill all fields.";
    } else {

        // ADMIN LOGIN
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username=?");
        $stmt->bind_param("s", $userid);
        $stmt->execute();
        $res = $stmt->get_result();
        $adminMatch = ($res->num_rows === 1) ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($adminMatch) {
            // Username matched an admin account — from here on, this IS
            // an admin login attempt, so we never fall through to the
            // student check below (that used to produce a confusing
            // "Account not found" for an admin who just mistyped their
            // password).
            if (password_verify($password, $adminMatch['password'])) {
                $_SESSION['user'] = $adminMatch['id'];
                $_SESSION['usertype'] = 'admin';
                $_SESSION['username'] = $adminMatch['username'];

                // org_id is NULL for the super admin (sees everything),
                // or a specific organization's id for a scoped org admin
                // (e.g. USC.campcon@gmail.com only sees USC's data).
                $_SESSION['org_id'] = $adminMatch['org_id'];

                // Super admin lands on the full Dashboard. Org admins have
                // no access to Dashboard/Reports at all, so they go
                // straight to their actual home page: Applications.
                if (empty($adminMatch['org_id'])) {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: admin/org_dashboard.php");
                }
                exit();
            } else {
                $error = "Wrong password.";
            }
        } else {

            // STUDENT LOGIN
            $stmt = $conn->prepare("SELECT * FROM students WHERE student_id=?");
            $stmt->bind_param("s", $userid);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 1) {
                $user = $res->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    $_SESSION['user'] = $user['student_id'];
                    $_SESSION['usertype'] = 'student';
                    $_SESSION['username'] = $user['full_name'];

                    header("Location: student/dashboard.php");
                    exit();
                } else {
                    $error = "Wrong password.";
                }
            } else {
                $error = "Account not found.";
            }

            $stmt->close();
        }
    }

    // ============ POST/REDIRECT/GET ============
    // Every login failure above ends up here with $error set. Instead of
    // rendering the error directly from this POST request, we stash it
    // in a one-time session flash and redirect to a plain GET request.
    // Fixes: refreshing the error page used to trigger the browser's
    // "Confirm Form Resubmission" prompt (and if dismissed, looked like
    // the page just didn't reload). Now the error page is a normal GET,
    // so refresh just cleanly reloads a blank login form as expected.
    if ($error) {
        $_SESSION['login_error'] = $error;
        header("Location: login.php");
        exit();
    }
}

// Pick up a flashed error from the redirect above (one-time read).
if (empty($error) && isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    $submitted = true;
    unset($_SESSION['login_error']);
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<link rel="icon" type="image/png" href="img/favicon.png">

<link rel="stylesheet" href="css/login.css">
</head>

<body>

<a href="index.html" class="back-btn" id="backBtn">
    <svg viewBox="0 0 24 24">
        <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    Back
</a>

<a href="index.html">
<img src="img/cclogo.png" class="logo">
</a>

<div class="container">

<h2>Welcome Back</h2>
<p class="sub">Login to your account</p>

<?php if (isset($_GET['registered'])): ?>
<div class="alert success">
Account created successfully!
</div>
<?php endif; ?>

<?php if ($submitted && $error): ?>
<div class="alert error">
<?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="login-mode-switch" id="loginModeSwitch">
    <button type="button" class="active" data-mode="student">
        <svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5Z" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 12v5c0 1.5 2.5 3 6 3s6-1.5 6-3v-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Student
    </button>
    <button type="button" data-mode="admin">
        <svg viewBox="0 0 24 24"><path d="M12 2 4 5v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V5Z" stroke-linecap="round" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Admin
    </button>
</div>

<form method="POST">

<div class="input-group">
<input type="text" name="userid" id="userid" placeholder="Student ID" required>
</div>

<div class="input-group">
<input type="password" id="password" name="userpassword" placeholder="Password" required>
</div>

<div style="font-size:12px; margin-bottom:15px;">
<input type="checkbox" onclick="togglePassword()"> Show Password
</div>

<input type="submit" value="Login" class="login-btn" id="loginBtn">

<p style="margin-top:15px; font-size:13px;" id="signupLine">
Don't have an account? <a href="signup.php">Sign Up</a>
</p>

<p style="margin-top:8px; font-size:12px; color:#9ca3af; display:none;" id="adminSignupLine">
Main Admin? <a href="admin_signup.php">Register here</a>
</p>

</form>

</div>

<script>
function togglePassword(){
    let x = document.getElementById("password");
    x.type = x.type === "password" ? "text" : "password";
}

document.querySelector("form").addEventListener("submit", function(){
    document.getElementById("loginBtn").value = "Logging in...";
});

// BACK BUTTON: the anchor already has href="index.html", so the
// default click behavior always works. We only intervene when we're
// confident window.history.back() will land somewhere useful — i.e.
// the previous entry in THIS TAB's history is a page on our own site.
// Otherwise we don't call preventDefault() at all, so the plain href
// navigation just runs normally instead of silently doing nothing.
document.getElementById("backBtn")?.addEventListener("click", function (e) {
    const cameFromSameSite =
        document.referrer && document.referrer.indexOf(window.location.host) !== -1;
    const hasHistoryToGoBackTo = window.history.length > 1;

    if (cameFromSameSite && hasHistoryToGoBackTo) {
        e.preventDefault();
        window.history.back();
    }
    // else: let the browser follow href="index.html" normally.
});

// LOGIN MODE SWITCH (Student / Admin)
// Backend already auto-detects account type, so this only adjusts
// the placeholder + helper links for a clearer experience.
const modeButtons = document.querySelectorAll("#loginModeSwitch button");
const useridInput = document.getElementById("userid");
const signupLine = document.getElementById("signupLine");
const adminSignupLine = document.getElementById("adminSignupLine");

modeButtons.forEach(btn => {
    btn.addEventListener("click", () => {
        modeButtons.forEach(b => b.classList.remove("active"));
        btn.classList.add("active");

        if (btn.dataset.mode === "admin") {
            useridInput.placeholder = "Admin Username";
            signupLine.style.display = "none";
            adminSignupLine.style.display = "block";
        } else {
            useridInput.placeholder = "Student ID";
            signupLine.style.display = "block";
            adminSignupLine.style.display = "none";
        }
    });
});
</script>

</body>
</html>