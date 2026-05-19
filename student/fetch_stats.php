<?php
session_start();

$conn = new mysqli("localhost", "root", "", "campusconnect");

$student_id = $_SESSION["user"];

$stats = $conn->query("
    SELECT
    COUNT(*) total,
    SUM(status='Approved') approved,
    SUM(status='Pending') pending,
    SUM(status='Rejected') rejected
    FROM applications
    WHERE student_id='$student_id'
")->fetch_assoc();

echo "
<p>Total: {$stats['total']}</p>
<p>Approved: {$stats['approved']}</p>
<p>Pending: {$stats['pending']}</p>
<p>Rejected: {$stats['rejected']}</p>
";
?>