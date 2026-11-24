<?php
session_start();

// ================= CONFIG =================
$redirectPage = "organizations.php";          // must be in /student/
$maxSizeMB    = 10;                            // max attachment size in MB
$allowedExt   = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$allowedMime  = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip',                         // some servers report .docx as zip
    'application/octet-stream',                // some servers report .doc this way
    'image/jpeg',
    'image/png',
];
$sendConfirmationEmail = true;                 // uses ../mailer.php if it exists

$maxSize = $maxSizeMB * 1024 * 1024;

function fail($msg) {
    die("Error: " . htmlspecialchars($msg) . " <a href='javascript:history.back()'>Go back</a>");
}

// ================= AUTH CHECK =================
if (!isset($_SESSION["user"])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . $redirectPage);
    exit();
}

// If the whole POST was dropped because it exceeded post_max_size
if (empty($_POST) && empty($_FILES) && !empty($_SERVER['CONTENT_LENGTH'])) {
    fail("The upload is larger than the server allows (post_max_size). Try a smaller file.");
}

// ================= DATABASE =================
require __DIR__ . '/../db.php';
if ($conn->connect_error) {
    die("DB Error: " . htmlspecialchars($conn->connect_error));
}
$conn->set_charset("utf8mb4");

// ================= GET DATA =================
$student_id = $_SESSION["user"];
$org_id     = (int)($_POST['org_id'] ?? 0);
$fullname   = trim($_POST['fullname'] ?? '');
$email      = trim($_POST['email'] ?? '');
$contact    = trim($_POST['contact'] ?? '');
$course     = trim($_POST['course'] ?? '');
$section    = trim($_POST['section'] ?? '');
$role       = trim($_POST['role'] ?? '');

// ================= BASIC VALIDATION =================
if ($fullname === '' || $email === '' || $contact === '' || $course === '' || $section === '' || $role === '') {
    fail("Please fill in all required fields.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail("Invalid email address.");
}

// ================= VALIDATE ORG =================
$checkOrg = $conn->prepare("SELECT org_id FROM organizations WHERE org_id = ?");
$checkOrg->bind_param("i", $org_id);
$checkOrg->execute();
$orgExists = $checkOrg->get_result()->fetch_assoc();
$checkOrg->close();

if (!$orgExists) {
    fail("Invalid organization (org_id received: " . $org_id . "). Please go back and try applying again.");
}

// ================= FILE UPLOAD =================
$attachment = "";

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {

    $file = $_FILES['attachment'];

    // Report real upload errors instead of silently ignoring them
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => "File exceeds the server limit (upload_max_filesize in php.ini).",
            UPLOAD_ERR_FORM_SIZE  => "File exceeds the form size limit.",
            UPLOAD_ERR_PARTIAL    => "The file was only partially uploaded. Please try again.",
            UPLOAD_ERR_NO_TMP_DIR => "Server is missing a temporary upload folder.",
            UPLOAD_ERR_CANT_WRITE => "Server could not write the file to disk.",
            UPLOAD_ERR_EXTENSION  => "A PHP extension blocked the upload.",
        ];
        fail($uploadErrors[$file['error']] ?? "Upload failed (error code " . $file['error'] . ").");
    }

    // Size check
    if ($file['size'] > $maxSize) {
        fail("File is too large (max {$maxSizeMB} MB).");
    }

    // Extension check
    $originalName = $file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        fail("Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG.");
    }

    // Real MIME type check (not just the extension)
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedMime, true)) {
            fail("The file content does not match an allowed file type.");
        }
    }

    // Upload folder
    $uploadDir = __DIR__ . "/../uploads/";
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        fail("Could not create the uploads folder.");
    }
    if (!is_writable($uploadDir)) {
        fail("The uploads folder is not writable.");
    }

    // Random stored name (keeps the extension, avoids collisions/overwrites)
    $attachment = time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $attachment)) {
        fail("Failed to save the uploaded file.");
    }
}

// ================= INSERT =================
$stmt = $conn->prepare("
    INSERT INTO applications
    (student_id, org_id, fullname, email, contact, course, section, role, attachment, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
");

$stmt->bind_param(
    "sisssssss",
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

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    $conn->close();
    // Remove the uploaded file so we don't leave orphans
    if ($attachment !== "" && file_exists(__DIR__ . "/../uploads/" . $attachment)) {
        unlink(__DIR__ . "/../uploads/" . $attachment);
    }
    die("Error: " . htmlspecialchars($err));
}

$stmt->close();
$conn->close();

// ================= OPTIONAL CONFIRMATION EMAIL =================
// Adjust the path if your mailer.php is somewhere else.
$mailerPath = __DIR__ . "/../mailer.php";
if ($sendConfirmationEmail && file_exists($mailerPath)) {
    require_once $mailerPath;
    if (function_exists('sendMail')) {
        $safeName = htmlspecialchars($fullname);
        $safeRole = htmlspecialchars($role);
        $body = "<p>Hi {$safeName},</p>"
              . "<p>Your application for the role of <strong>{$safeRole}</strong> was received and is now <strong>Pending</strong>.</p>"
              . "<p>We will notify you once it has been reviewed.</p>"
              . "<p>- CampusConnect</p>";
        // A mail failure must not break the application, so the result is only logged
        if (!sendMail($email, $fullname, "CampusConnect: Application received", $body)) {
            error_log("Confirmation email failed for " . $email);
        }
    }
}

// ================= REDIRECT =================
if (!file_exists(__DIR__ . "/" . $redirectPage)) {
    die("Application submitted successfully, but the page '" . htmlspecialchars($redirectPage) .
        "' was not found in the /student/ folder. Update \$redirectPage at the top of this file.");
}

header("Location: " . $redirectPage . "?msg=" . urlencode("Application Submitted!"));
exit();