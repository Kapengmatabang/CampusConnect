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
$org_id = $_POST['org_id'] ?? 0;

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
(student_id, org_id, attachment, status)
VALUES (?, ?, ?, 'Pending')
");

$stmt->bind_param("iis",
    $student_id,
    $org_id,
    $attachment
);

if ($stmt->execute()) {
    header("Location: explore_orgs.php?msg=Application Submitted!");
    exit();
} else {
    echo "Error: " . $stmt->error;
}
?>