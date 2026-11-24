<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

$student_id = $_SESSION["user"];
$org_id     = $_POST['org_id'] ?? '';

if ($org_id) {

    // STEP 1: Verify student exists in DB
    $verify = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
    $verify->bind_param("s", $student_id);
    $verify->execute();

    if ($verify->get_result()->num_rows === 0) {
        // Student not in DB — clear session and send back to login
        session_destroy();
        header("Location: ../login.php?error=session_invalid");
        exit();
    }

    // STEP 2: Prevent duplicate application
    $check = $conn->prepare("SELECT 1 FROM applications WHERE student_id = ? AND org_id = ?");
    $check->bind_param("si", $student_id, $org_id);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {

        // STEP 3: Insert application
        $insert = $conn->prepare("INSERT INTO applications (student_id, org_id) VALUES (?, ?)");
        $insert->bind_param("si", $student_id, $org_id);

        if (!$insert->execute()) {
            die("Application failed: " . $conn->error);
        }
    }
}

header("Location: dashboard.php");
exit();