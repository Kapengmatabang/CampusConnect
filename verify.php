<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$database = new mysqli("localhost", "root", "", "campusconnect");

if ($database->connect_error) {
    die("Database connection failed: " . $database->connect_error);
}

$status = "";
$message = "";

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    $status = "error";
    $message = "Missing verification token.";
} else {

    $stmt = $database->prepare("SELECT student_id, is_verified FROM students WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $status = "error";
        $message = "Invalid or expired verification link.";
    } else {
        $student = $result->fetch_assoc();

        if ($student['is_verified'] == 1) {
            $status = "info";
            $message = "This account is already verified. You can log in now.";
        } else {
            $update = $database->prepare("UPDATE students SET is_verified = 1, verification_token = NULL WHERE student_id = ?");
            $update->bind_param("s", $student['student_id']);

            if ($update->execute()) {
                $status = "success";
                $message = "Your account has been verified! You can now log in.";
            } else {
                $status = "error";
                $message = "Something went wrong while verifying your account. Please try again.";
            }

            $update->close();
        }
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Verify Account</title>
<link rel="icon" type="image/png" href="img/favicon.png">

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Segoe UI', sans-serif;
    background: #ffffff;
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    color: #111827;
}

.logo-img {
    position: absolute;
    top: 20px;
    left: 20px;
    width: 140px;
}

.container {
    width: 400px;
    padding: 35px;
    border-radius: 18px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    text-align: center;
}

.icon {
    font-size: 48px;
    margin-bottom: 15px;
}

h2 {
    margin-bottom: 10px;
    color: #111827;
}

p.message {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 20px;
    line-height: 1.6;
}

.btn {
    display: inline-block;
    padding: 12px 28px;
    border-radius: 30px;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    font-weight: bold;
    text-decoration: none;
    transition: 0.3s;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(34,197,94,0.4);
}
</style>
</head>

<body>

<a href="index.html">
<img src="img/cclogo.png" class="logo-img">
</a>

<div class="container">
    <?php if ($status === "success"): ?>
        <div class="icon"></div>
        <h2>Account Verified</h2>
    <?php elseif ($status === "info"): ?>
        <div class="icon">ℹ</div>
        <h2>Already Verified</h2>
    <?php else: ?>
        <div class="icon"></div>
        <h2>Verification Failed</h2>
    <?php endif; ?>

    <p class="message"><?= htmlspecialchars($message) ?></p>

    <a href="login.php" class="btn">Go to Login</a>
</div>

</body>
</html>