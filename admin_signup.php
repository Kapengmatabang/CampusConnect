<?php
session_start();

define('MAIN_ADMIN_SETUP_KEY', 'ADMINSETUPMILLION');

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $setupKey  = $_POST['setup_key'] ?? '';

    // Allow letters, numbers, dots, underscores, and hyphens — e.g. "cc.root"
    $usernameValid = preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username);

    if (!$usernameValid) {
        $error = "Username must be 3-50 characters (letters, numbers, dots, underscores, hyphens only).";
    } elseif (empty($password) || strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (!hash_equals(MAIN_ADMIN_SETUP_KEY, $setupKey)) {
        $error = "Invalid setup key. Main Admin registration is restricted.";
    } else {

        $check = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "That username is already taken.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            // org_id left NULL -> super admin
            $insert = $conn->prepare("INSERT INTO admins (username, password, org_id) VALUES (?, ?, NULL)");
            $insert->bind_param("ss", $username, $hashed);

            if ($insert->execute()) {
                $success = "Main Admin account \"$username\" created. You can now log in.";
            } else {
                $error = "Something went wrong creating the account.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Main Admin Registration</title>
<link rel="icon" type="image/png" href="img/favicon.png">

<link rel="stylesheet" href="css/admin_signup.css">
</head>

<body>
    <body>

<a href="signup.php" class="back-btn">
    <svg viewBox="0 0 24 24">
        <path d="M15 18l-6-6 6-6"
              stroke-linecap="round"
              stroke-linejoin="round"/>
    </svg>
    Back
</a>

<div class="dot-grid top-right"></div>
<div class="dot-grid bottom-left"></div>

<div class="side-icon left">
    <svg viewBox="0 0 100 100" fill="none">
        <path d="M50 12 L82 24 V48 C82 68 68 82 50 90 C32 82 18 68 18 48 V24 Z" stroke="#16a34a" stroke-width="2.5" stroke-linejoin="round"/>
        <path d="M38 50 L47 59 L64 40" stroke="#16a34a" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</div>

<div class="side-icon right">
    <svg viewBox="0 0 100 100" fill="none">
        <rect x="25" y="45" width="50" height="38" rx="6" stroke="#f5a623" stroke-width="2.5"/>
        <path d="M35 45 V32 a15 15 0 0 1 30 0 V45" stroke="#f5a623" stroke-width="2.5" fill="none" stroke-linecap="round"/>
        <circle cx="50" cy="62" r="4.5" fill="#f5a623"/>
    </svg>
</div>

<div class="container">

<h2>Admin Registration</h2>
<p class="sub">Restricted registration for the platform's main admin.</p>

<div class="note">
Register here your <b>Organization Admin</b> account.
</div>

<?php if ($error): ?>
<div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" id="signupForm">

<label class="field-label">Username</label>
<input type="text" name="username" id="username" class="input" >
<p class="hint">Letters, numbers, dots, underscores, or hyphens — whatever you want.</p>

<label class="field-label">Setup Key</label>
<input type="password" name="setup_key" class="input" placeholder="Required to register as Main Admin" required>
<p class="hint">Ask whoever manages the server for this key.</p>

<label class="field-label">Password</label>
<input type="password" name="password" class="input" placeholder="At least 8 characters" required>

<label class="field-label">Confirm Password</label>
<input type="password" name="confirm_password" class="input" placeholder="Repeat password" required>

<button type="submit" class="btn">Create Account</button>

</form>

<div class="login-link">
Already have an account? <a href="login.php">Login</a>
</div>

<a href="signup.php" class="switch-btn">
    <svg viewBox="0 0 24 24">
        <path d="M4 8h14M18 8l-3.5-3.5M18 8l-3.5 3.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M20 16H6M6 16l3.5-3.5M6 16l3.5 3.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    Switch to Student Registration
</a>

</div>

</body>
</html>