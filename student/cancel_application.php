<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["usertype"] !== "student") {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

$student_id = $_SESSION["user"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $org_id = $_POST['org_id'] ?? '';

    // ✅ Only allow cancel if status = Pending
    $stmt = $conn->prepare("
        DELETE FROM applications 
        WHERE student_id=? AND org_id=? AND status='Pending'
    ");
    $stmt->bind_param("si", $student_id, $org_id);
    $stmt->execute();

    header("Location: dashboard.php");
    exit();
}
?>