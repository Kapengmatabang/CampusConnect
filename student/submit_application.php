<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

// ================= GET DATA =================
$student_id = $_SESSION["user"];
$org_id     = $_POST['org_id'] ?? 0;
$fullname   = $_POST['fullname'] ?? '';
$email      = $_POST['email'] ?? '';
$contact    = $_POST['contact'] ?? '';
$course     = $_POST['course'] ?? '';
$section    = $_POST['section'] ?? '';
$role       = $_POST['role'] ?? '';

// ================= VALIDATE ORG =================
// Prevents the fatal foreign key crash — tells us clearly if org_id is bad
$checkOrg = $conn->prepare("SELECT org_id FROM organizations WHERE org_id = ?");
$checkOrg->bind_param("i", $org_id);
$checkOrg->execute();
$orgExists = $checkOrg->get_result()->fetch_assoc();

if (!$orgExists) {
    die("Error: Invalid organization (org_id received: " . htmlspecialchars($org_id) . "). Please go back and try applying again.");
}

// ================= FILE UPLOAD =================
$attachment = "";

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === 0) {

    if (!is_dir("../uploads")) {
        mkdir("../uploads", 0777, true);
    }

    $safeName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $_FILES['attachment']['name']);
    $attachment = time() . "_" . $safeName;

    move_uploaded_file(
        $_FILES['attachment']['tmp_name'],
        "../uploads/" . $attachment
    );
}

// ================= INSERT =================
$stmt = $conn->prepare("
INSERT INTO applications
(student_id, org_id, fullname, email, contact, course, section, role, attachment, status)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
");

$stmt->bind_param("sisssssss",
    $student_id,
    $org_id,
    $fullname,
    $email,
    $contact,
    $course,
    $section,
    $role,
    $attachment
);

if ($stmt->execute()) {
    header("Location: explore_orgs.php?msg=Application Submitted!");
    exit();
} else {
    echo "Error: " . $stmt->error;
}
?>