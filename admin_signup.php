<?php
session_start();

// ============================================================
// SETTINGS
// ============================================================

// The "Setup Key" needed to register as Main Admin.
// CHANGE THIS to your own secret before using the page.
define('ADMIN_SETUP_KEY', 'ADMINMIKAELASEVEN');

// Set to false when everything works.
define('DEBUG', true);

ini_set('display_errors', DEBUG ? 1 : 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// DATABASE (same settings as login.php)
$conn = new mysqli("localhost", "root", "", "campusconnect");
$conn->set_charset("utf8mb4");

$error = "";

// CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

// Basic brute-force guard on the setup key (5 tries, then 5 min lock)
$maxTries = 5;
$lockSecs = 300;
$locked   = false;

if (!empty($_SESSION['key_lock_until']) && time() < $_SESSION['key_lock_until']) {
    $locked = true;
} elseif (!empty($_SESSION['key_lock_until'])) {
    unset($_SESSION['key_lock_until'], $_SESSION['key_fails']);
}


// ============================================================
// HANDLE FORM
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $setupKey = $_POST['setup_key'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {

        $error = "Your session expired. Please refresh the page and try again.";

    } elseif ($locked) {

        $error = "Too many wrong keys. Please wait a few minutes and try again.";

    } elseif (ADMIN_SETUP_KEY === 'CHANGE_THIS_KEY') {

        $error = "Open admin_signup.php and set ADMIN_SETUP_KEY at the top first.";

    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {

        $error = "Username must be 3-60 characters: letters, numbers, dots, underscores or hyphens.";

    } elseif (!hash_equals(ADMIN_SETUP_KEY, $setupKey)) {

        $_SESSION['key_fails'] = ($_SESSION['key_fails'] ?? 0) + 1;

        if ($_SESSION['key_fails'] >= $maxTries) {
            $_SESSION['key_lock_until'] = time() + $lockSecs;
        }

        $error = "Invalid setup key.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm) {

        $error = "Passwords do not match.";

    } else {

        try {

            $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($exists) {

                $error = "That username is already taken.";

            } else {

                $hash = password_hash($password, PASSWORD_DEFAULT);

                // org_id = NULL  ->  Main Admin (sees everything)
                $stmt = $conn->prepare(
                    "INSERT INTO admins (username, password, org_id) VALUES (?, ?, NULL)"
                );
                $stmt->bind_param("ss", $username, $hash);
                $stmt->execute();
                $stmt->close();

                unset($_SESSION['key_fails']);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));

                header("Location: login.php?registered=1");
                exit();
            }

        } catch (mysqli_sql_exception $e) {

            if ((int)$e->getCode() === 1062) {
                $error = "That username is already taken.";
            } else {
                $error = DEBUG
                    ? "Database error: " . $e->getMessage()
                    : "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Registration</title>
<link rel="icon" type="image/png" href="img/favicon.png">

<style>
    *, *::before, *::after { box-sizing: border-box; }

    html, body { margin: 0; padding: 0; }

    body {
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        color: #0f172a;
        background:
            radial-gradient(circle at 100% 100%, rgba(34, 197, 94, .08), transparent 40%),
            #ffffff;
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding: 40px 16px;
    }

    .card {
        width: 100%;
        max-width: 472px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 24px;
        box-shadow: 0 10px 40px rgba(15, 23, 42, .08);
        padding: 52px 26px 28px;
    }

    h1 {
        margin: 0 0 12px;
        text-align: center;
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
    }

    .subtitle {
        margin: 0 0 18px;
        text-align: center;
        font-size: 13px;
        color: #64748b;
    }

    .info {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #14532d;
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 12px;
        line-height: 1.45;
        margin-bottom: 18px;
    }

    .info strong { color: #166534; }

    .alert-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 13px;
        margin-bottom: 16px;
    }

    label {
        display: block;
        font-size: 12px;
        color: #475569;
        margin: 0 0 6px;
    }

    input[type="text"],
    input[type="password"] {
        width: 100%;
        height: 40px;
        padding: 0 14px;
        font-size: 14px;
        font-family: inherit;
        color: #0f172a;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        outline: none;
        transition: border-color .15s, box-shadow .15s, background .15s;
    }

    input::placeholder { color: #94a3b8; }

    input:focus {
        background: #ffffff;
        border-color: #22c55e;
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .18);
    }

    .field { margin-bottom: 4px; }

    .hint {
        font-size: 11px;
        color: #64748b;
        margin: 6px 0 14px;
    }

    .field-gap { margin-bottom: 14px; }

    .btn-primary {
        width: 100%;
        height: 40px;
        margin-top: 6px;
        border: none;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 700;
        font-family: inherit;
        color: #ffffff;
        cursor: pointer;
        background: linear-gradient(90deg, #22c55e, #16a34a);
        transition: filter .15s, transform .05s;
    }

    .btn-primary:hover { filter: brightness(1.05); }
    .btn-primary:active { transform: translateY(1px); }
    .btn-primary:disabled { opacity: .7; cursor: not-allowed; }

    .login-line {
        text-align: center;
        font-size: 13px;
        margin: 14px 0;
    }

    .login-line a,
    .link-green {
        color: #059669;
        text-decoration: none;
    }

    .login-line a:hover { text-decoration: underline; }

    .btn-switch {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        height: 40px;
        border-radius: 999px;
        border: 1px solid #a7f3d0;
        background: #ecfdf5;
        color: #047857;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: background .15s;
    }

    .btn-switch:hover { background: #d1fae5; }

    .btn-switch svg {
        width: 16px;
        height: 16px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
</style>
</head>

<body>

<div class="card">

    <h1>Admin Registration</h1>

    <p class="subtitle">Restricted registration for the platform's main admin.</p>

    <div class="info">
        This page creates the <strong>Main Admin</strong> account.
        Organization Admin accounts are created from the admin dashboard.
    </div>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off" id="signupForm">

        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   required>
        </div>
        <div class="hint">Letters, numbers, dots, underscores, or hyphens — whatever you want.</div>

        <div class="field">
            <label for="setup_key">Setup Key</label>
            <input type="password" id="setup_key" name="setup_key"
                   placeholder="Required to register as Main Admin" required>
        </div>
        <div class="hint">Ask whoever manages the server for this key.</div>

        <div class="field field-gap">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   placeholder="At least 8 characters" required>
        </div>

        <div class="field field-gap">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   placeholder="Repeat password" required>
        </div>

        <button type="submit" class="btn-primary" id="submitBtn">Create Account</button>

    </form>

    <p class="login-line">
        Already have an account? <a href="login.php">Login</a>
    </p>

    <a href="signup.php" class="btn-switch">
        <svg viewBox="0 0 24 24">
            <path d="M17 3l4 4-4 4"/>
            <path d="M21 7H9"/>
            <path d="M7 21l-4-4 4-4"/>
            <path d="M3 17h12"/>
        </svg>
        Switch to Student Registration
    </a>

</div>

<script>
document.getElementById("signupForm").addEventListener("submit", function () {
    var btn = document.getElementById("submitBtn");
    btn.disabled = true;
    btn.textContent = "Creating...";
});
</script>

</body>
</html>