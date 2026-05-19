<?php
$conn = new mysqli("localhost","root","","campusconnect");

$id = $_POST['id'];

// SIMPLE PROMPT (you can make UI later)
$date = date("Y-m-d H:i:s", strtotime("+2 days"));
$location = "Admin Office";

$stmt = $conn->prepare("
UPDATE applications 
SET interview_date=?, interview_location=?, interview_status='Scheduled'
WHERE application_id=?
");

$stmt->bind_param("ssi", $date, $location, $id);
$stmt->execute();

header("Location: dashboard.php");