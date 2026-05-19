<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require '../mailer.php'; // adjust path if needed

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

$id     = intval($_POST['id']);
$status = $_POST['status']; // 'Approved' or 'Rejected'

// Get application details (student name, email, org name)
$stmt = $conn->prepare("
    SELECT s.full_name, s.email, o.org_name
    FROM applications a
    LEFT JOIN students s ON a.student_id = s.student_id
    LEFT JOIN organizations o ON a.org_id = o.org_id
    WHERE a.application_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data   = $result->fetch_assoc();

// Update status
$upd = $conn->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
$upd->bind_param("si", $status, $id);
$upd->execute();

// Send email
$name    = $data['full_name'];
$email   = $data['email'];
$org     = $data['org_name'];

if ($status === 'Approved') {
    $subject = "Congratulations! Your Application to $org is Approved";
    $body    = "
        <h2>Hello, $name!</h2>
        <p>We are happy to inform you that your application to join <strong>$org</strong> has been <strong style='color:green;'>APPROVED</strong>.</p>
        <p>Welcome to the organization! Further onboarding details will be sent to you soon.</p>
        <br><p>— CampusConnect Admin</p>
    ";
} else {
    $subject = "Update on Your Application to $org";
    $body    = "
        <h2>Hello, $name!</h2>
        <p>Thank you for your interest in joining <strong>$org</strong>.</p>
        <p>After careful review, we regret to inform you that your application has been <strong style='color:red;'>REJECTED</strong> at this time.</p>
        <p>We encourage you to apply again in the future. Thank you for your effort.</p>
        <br><p>— CampusConnect Admin</p>
    ";
}

sendMail($email, $name, $subject, $body);

header("Location: dashboard.php?msg=Status updated and email sent");
exit();