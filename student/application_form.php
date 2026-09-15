<?php

session_start();

if (!isset($_SESSION["user"])) {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "campusconnect");

if ($conn->connect_error) die("DB Error");

// GET STUDENT INFO
$student_id = $_SESSION["user"];

$stmtUser = $conn->prepare("SELECT * FROM students WHERE student_id=?");
$stmtUser->bind_param("s", $student_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

// GET ORG
$org_id = $_GET['org_id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM organizations WHERE org_id=?");
$stmt->bind_param("i", $org_id);
$stmt->execute();
$org = $stmt->get_result()->fetch_assoc();

if (!$org) {
    die("Invalid organization.");
}

// Turn the org's newline-separated "positions" field
// into a clean list for the dropdown.
function splitLines($text) {
    if (empty($text)) return [];

    $lines = preg_split('/\r\n|\r|\n/', trim($text));

    return array_values(
        array_filter(
            array_map('trim', $lines)
        )
    );
}

$positions = splitLines($org['positions'] ?? '');

if (empty($positions)) {
    $positions = ['Member', 'Officer', 'Volunteer'];
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Organization Application</title>

    <!-- External CSS -->
    <link rel="stylesheet" href="../css/student/application_form.css">
</head>

<body>

<div class="container">

    <a href="organizations.php" class="back">⬅ Back</a>

    <div class="header">
        <h2>Apply to Organization</h2>
        <p class="sub">Fill in your details below</p>
    </div>

    <!-- ORG INFO -->
    <div class="org-box">

        <img 
            src="<?= !empty($org['image']) ? '../'.$org['image'] : '../img/default.png' ?>"
            alt="Organization Logo"
        >

        <div>
            <strong><?= htmlspecialchars($org['org_name']) ?></strong>

            <p class="org-description">
                <?= substr(htmlspecialchars($org['description']), 0, 60) ?>...
            </p>
        </div>

    </div>

    <form 
        method="POST" 
        action="submit_application.php" 
        enctype="multipart/form-data"
    >

        <input 
            type="hidden" 
            name="org_id" 
            value="<?= $org_id ?>"
        >

        <label>Full Name</label>

        <input 
            type="text" 
            name="fullname" 
            value="<?= htmlspecialchars($user['full_name']) ?>" 
            required
        >

        <label>Email</label>

        <input 
            type="email" 
            name="email" 
            value="<?= htmlspecialchars($user['email']) ?>" 
            required
        >

        <label>Contact Number</label>

        <input 
            type="text" 
            name="contact" 
            placeholder="09XXXXXXXXX" 
            required
        >

        <label>Course</label>

        <input 
            type="text" 
            name="course" 
            value="<?= htmlspecialchars($user['course']) ?>" 
            required
        >

        <label>Year & Section</label>

        <input 
            type="text" 
            name="section" 
            value="<?= htmlspecialchars($user['section']) ?>" 
            required
        >

        <label>Attachment (Resume / File)</label>

        <input 
            type="file" 
            name="attachment" 
            accept=".pdf,.doc,.docx,.jpg,.png" 
            required
        >

        <div class="file-hint">
            Accepted file types: PDF, JPG, PNG
        </div>

        <label>Preferred Role</label>

        <select name="role" required>

            <option value="">Select Role</option>

            <?php foreach ($positions as $position): ?>

                <option value="<?= htmlspecialchars($position) ?>">
                    <?= htmlspecialchars($position) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <button type="submit">
            Submit Application
        </button>

    </form>

</div>

</body>
</html>