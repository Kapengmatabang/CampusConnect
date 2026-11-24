<?php

require 'auth.php'; // sets $conn, $scopedOrgId, $isSuperAdmin

// This page is the Org Admin's home.
// Main Admin has their own full dashboard.php.
if ($isSuperAdmin) {
    header("Location: dashboard.php");
    exit();
}

$today = date('Y-m-d');

$orgError = "";
$orgSuccess = "";

// ================= EDIT ORGANIZATION (own org only) =================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_org'])) {

    $orgDescription = trim($_POST['description'] ?? '');
    $orgPositions = trim($_POST['positions'] ?? '');
    $orgQualifications = trim($_POST['qualifications'] ?? '');

    // Org name and course group are managed by Main Admin.

    if (!empty($_FILES['org_image']['name'])) {

        if (!is_dir("../uploads/orgs")) {
            mkdir("../uploads/orgs", 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES['org_image']['name']);

        move_uploaded_file(
            $_FILES['org_image']['tmp_name'],
            "../uploads/orgs/" . $fileName
        );

        $imagePath = "uploads/orgs/" . $fileName;

        $stmt = $conn->prepare("
            UPDATE organizations
            SET description=?, image=?, positions=?, qualifications=?
            WHERE org_id=?
        ");

        $stmt->bind_param(
            "ssssi",
            $orgDescription,
            $imagePath,
            $orgPositions,
            $orgQualifications,
            $scopedOrgId
        );

    } else {

        $stmt = $conn->prepare("
            UPDATE organizations
            SET description=?, positions=?, qualifications=?
            WHERE org_id=?
        ");

        $stmt->bind_param(
            "sssi",
            $orgDescription,
            $orgPositions,
            $orgQualifications,
            $scopedOrgId
        );
    }

    if ($stmt->execute()) {
        $orgSuccess = "Organization info updated.";
    } else {
        $orgError = "Something went wrong saving your changes.";
    }
}

// ================= ORG INFO =================

$orgStmt = $conn->prepare("
    SELECT org_name, description, image, positions, qualifications
    FROM organizations
    WHERE org_id = ?
");

$orgStmt->bind_param("i", $scopedOrgId);
$orgStmt->execute();

$org = $orgStmt->get_result()->fetch_assoc();

// ================= APPLICATION STATS =================

$statStmt = $conn->prepare("
    SELECT
        COUNT(*) total,
        SUM(status='Pending') pending,
        SUM(status='Interview') interview,
        SUM(status='Approved') approved,
        SUM(status='Rejected') rejected
    FROM applications
    WHERE org_id = ?
");

$statStmt->bind_param("i", $scopedOrgId);
$statStmt->execute();

$stats = $statStmt->get_result()->fetch_assoc();

// ================= TODAY'S INTERVIEWS =================

$todayStmt = $conn->prepare("
    SELECT a.*, s.full_name
    FROM applications a
    LEFT JOIN students s ON a.student_id = s.student_id
    WHERE a.org_id = ?
    AND a.status = 'Interview'
    AND a.interview_date = ?
    ORDER BY a.interview_time ASC
");

$todayStmt->bind_param("is", $scopedOrgId, $today);
$todayStmt->execute();

$todayInterviews = $todayStmt->get_result();

?>

<!DOCTYPE html>
<html>

<head>

    <title>Organization Dashboard</title>

    <!-- External CSS -->
    <link rel="stylesheet" href="../css/admin/org_dashboard.css">

</head>

<body>

<div class="sidebar">

    <h2>Admin</h2>

    <span class="role-label">
        Organization Admin
    </span>

    <div class="theme-toggle" id="themeToggle">

        <span>
            <span id="themeIcon"></span>
            <span id="themeLabel">Dark Mode</span>
        </span>

        <span>Tap to switch</span>

    </div>

    <a href="org_dashboard.php">Dashboard</a>

    <a href="applications.php">Applications</a>

    <a href="interviews.php">Interviews</a>

    <a href="records.php">Records</a>

    <a href="settings.php">Settings</a>

    <a href="../logout.php">Logout</a>

</div>


<div class="main">

    <!-- ORGANIZATION HEADER -->

    <div class="org-header">

        <img
            class="org-img"
            src="<?= !empty($org['image'])
                ? '../' . htmlspecialchars($org['image'])
                : '../img/default.png' ?>"
            alt="Organization Logo"
        >

        <div class="org-info">

            <h2>
                <?= htmlspecialchars($org['org_name'] ?? 'Your Organization') ?>
            </h2>

            <p>
                <?= htmlspecialchars($org['description'] ?? '') ?>
            </p>

        </div>

        <button
            class="edit-org-btn"
            onclick="openEditOrgModal()"
        >
            Edit Organization
        </button>

    </div>


    <!-- ALERTS -->

    <?php if ($orgSuccess): ?>

        <div class="alert success">
            <?= htmlspecialchars($orgSuccess) ?>
        </div>

    <?php endif; ?>


    <?php if ($orgError): ?>

        <div class="alert error">
            <?= htmlspecialchars($orgError) ?>
        </div>

    <?php endif; ?>


    <!-- STATISTICS -->

    <div class="cards">

        <div class="card">
            <span>Total</span>

            <span class="num">
                <?= (int)($stats['total'] ?? 0) ?>
            </span>
        </div>


        <div class="card pending">
            <span>Pending</span>

            <span class="num">
                <?= (int)($stats['pending'] ?? 0) ?>
            </span>
        </div>


        <div class="card interview">
            <span>Interview</span>

            <span class="num">
                <?= (int)($stats['interview'] ?? 0) ?>
            </span>
        </div>


        <div class="card approved">
            <span>Approved</span>

            <span class="num">
                <?= (int)($stats['approved'] ?? 0) ?>
            </span>
        </div>


        <div class="card rejected">
            <span>Rejected</span>

            <span class="num">
                <?= (int)($stats['rejected'] ?? 0) ?>
            </span>
        </div>

    </div>


    <!-- NOTIFICATION -->

    <?php
    $pendingCount = (int)($stats['pending'] ?? 0);
    ?>

    <div class="notification-card <?= $pendingCount > 0 ? 'has-new' : '' ?>">

        <div class="notif-icon">

            🔔

            <?php if ($pendingCount > 0): ?>

                <span class="notif-badge">
                    <?= $pendingCount ?>
                </span>

            <?php endif; ?>

        </div>


        <div class="notif-text">

            <?php if ($pendingCount > 0): ?>

                <strong>
                    <?= $pendingCount ?>
                    New Application<?= $pendingCount === 1 ? '' : 's' ?>
                </strong>

                <span>
                    Applicants are waiting for a decision.
                </span>

            <?php else: ?>

                <strong>
                    No New Applications
                </strong>

                <span>
                    You're all caught up.
                </span>

            <?php endif; ?>

        </div>


        <a
            href="applications.php"
            class="view-btn"
        >
            View Applications
        </a>

    </div>


    <!-- TODAY'S INTERVIEWS -->

    <div class="section">

        <div class="section-heading">

            <h3>Today's Interviews</h3>

        </div>


        <?php if ($todayInterviews->num_rows === 0): ?>

            <div class="empty-state">
                No interviews scheduled for today.
            </div>

        <?php else: ?>

            <table>

                <tr>

                    <th>Student</th>

                    <th>Time</th>

                    <th>Location / Link</th>

                </tr>


                <?php while ($t = $todayInterviews->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                !empty($t['fullname'])
                                    ? $t['fullname']
                                    : $t['full_name']
                            ) ?>
                        </td>


                        <td>
                            <?= substr($t['interview_time'], 0, 5) ?>
                        </td>


                        <td>

                            <?php if (!empty($t['interview_link'])): ?>

                                <a
                                    href="<?= htmlspecialchars($t['interview_link']) ?>"
                                    target="_blank"
                                    class="interview-link"
                                >
                                    Link ↗
                                </a>

                            <?php elseif (!empty($t['interview_location'])): ?>

                                <?= htmlspecialchars($t['interview_location']) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

            </table>

        <?php endif; ?>

    </div>

</div>


<!-- EDIT ORGANIZATION MODAL -->

<div id="orgModal" class="modal">

    <div class="modal-content">

        <h3>Edit Organization</h3>


        <p class="modal-description">

            Name and course group are managed by the Main Admin.
            You can update your description, open positions,
            qualifications, and logo.

        </p>


        <form method="POST" enctype="multipart/form-data">

            <label class="field-label">
                Organization Name
            </label>

            <input
                type="text"
                value="<?= htmlspecialchars($org['org_name'] ?? '') ?>"
                disabled
            >


            <label class="field-label">
                Description
            </label>

            <textarea
                name="description"
                placeholder="Short description shown to students"
            ><?= htmlspecialchars($org['description'] ?? '') ?></textarea>


            <label class="field-label">
                Open Positions
                <span class="field-note">(one per line)</span>
            </label>

            <textarea
                name="positions"
                placeholder="Secretary&#10;Treasurer&#10;Committee Member"
            ><?= htmlspecialchars($org['positions'] ?? '') ?></textarea>


            <label class="field-label">
                Qualifications
                <span class="field-note">(one per line)</span>
            </label>

            <textarea
                name="qualifications"
                placeholder="Currently enrolled&#10;Good academic standing"
            ><?= htmlspecialchars($org['qualifications'] ?? '') ?></textarea>


            <label class="field-label">
                Logo / Image
            </label>


            <p class="current-image">

                <?= !empty($org['image'])
                    ? "Current image is kept unless you upload a new one."
                    : "No image set yet." ?>

            </p>


            <input
                type="file"
                name="org_image"
            >


            <button
                type="submit"
                name="edit_org"
                class="save-btn"
            >
                Save Changes
            </button>


            <button
                type="button"
                onclick="closeEditOrgModal()"
                class="cancel-btn"
            >
                Cancel
            </button>

        </form>

    </div>

</div>


<script>

function openEditOrgModal() {
    document.getElementById("orgModal").style.display = "block";
}

function closeEditOrgModal() {
    document.getElementById("orgModal").style.display = "none";
}


/* Re-open modal automatically on validation error */

<?php if ($orgError): ?>

document.addEventListener("DOMContentLoaded", () => {
    openEditOrgModal();
});

<?php endif; ?>


/* ============ THEME TOGGLE ============ */

const root = document.documentElement;

const themeToggle = document.getElementById("themeToggle");

const themeIcon = document.getElementById("themeIcon");

const themeLabel = document.getElementById("themeLabel");


function applyTheme(theme) {

    if (theme === "light") {

        root.classList.add("light");

        themeIcon.textContent = "";

        themeLabel.textContent = "Light Mode";

    } else {

        root.classList.remove("light");

        themeIcon.textContent = "";

        themeLabel.textContent = "Dark Mode";

    }

}


const savedTheme =
    localStorage.getItem("cc_admin_theme") || "dark";

applyTheme(savedTheme);


themeToggle.addEventListener("click", () => {

    const isLight =
        root.classList.contains("light");

    const newTheme =
        isLight ? "dark" : "light";

    applyTheme(newTheme);

    localStorage.setItem(
        "cc_admin_theme",
        newTheme
    );

});

</script>

</body>
</html>