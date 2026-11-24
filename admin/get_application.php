<?php
$conn = new mysqli("localhost", "root", "", "campusconnect");

$id = $_GET['id'];

$result = $conn->query("
    SELECT a.*, s.full_name, s.email, o.org_name
    FROM applications a
    JOIN students s ON a.student_id = s.student_id
    JOIN organizations o ON a.org_id = o.org_id
    WHERE a.application_id = $id
");

$a = $result->fetch_assoc();
?>

<h3>Application Details</h3>

<p><b>Name:</b> <?= $a['full_name'] ?></p>
<p><b>Email:</b> <?= $a['email'] ?></p>
<p><b>Organization:</b> <?= $a['org_name'] ?></p>
<p><b>Reason:</b> <?= $a['remarks'] ?></p>
<p><b>Status:</b> <?= $a['status'] ?></p>