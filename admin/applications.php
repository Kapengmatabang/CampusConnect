<?php

session_start();

if (!isset($_SESSION['user']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "campusconnect");

if ($conn->connect_error) die("DB Error");

$scopedOrgId = $_SESSION['org_id'] ?? null;
$isSuperAdmin = empty($scopedOrgId);

/*
|--------------------------------------------------------------------------
| PENDING APPLICATIONS ONLY
|--------------------------------------------------------------------------
| Scoped organization admins only see applicants who applied
| to their own organization.
|
| Super Admin sees all pending applications.
*/

if ($isSuperAdmin) {

    $result = $conn->query("
        SELECT 
            a.*,
            s.full_name,
            s.email,
            s.course AS student_course,
            o.org_name
        FROM applications a
        LEFT JOIN students s 
            ON a.student_id = s.student_id
        LEFT JOIN organizations o 
            ON a.org_id = o.org_id
        WHERE a.status = 'Pending'
        ORDER BY 
            COALESCE(NULLIF(a.course,''), s.course) ASC,
            a.applied_at DESC
    ");

} else {

    $stmt = $conn->prepare("
        SELECT 
            a.*,
            s.full_name,
            s.email,
            s.course AS student_course,
            o.org_name
        FROM applications a
        LEFT JOIN students s 
            ON a.student_id = s.student_id
        LEFT JOIN organizations o 
            ON a.org_id = o.org_id
        WHERE a.status = 'Pending'
          AND a.org_id = ?
        ORDER BY 
            COALESCE(NULLIF(a.course,''), s.course) ASC,
            a.applied_at DESC
    ");

    $stmt->bind_param("i", $scopedOrgId);
    $stmt->execute();
    $result = $stmt->get_result();
}

/*
|--------------------------------------------------------------------------
| GROUP BY COURSE
|--------------------------------------------------------------------------
*/

$byCourse = [];

while ($row = $result->fetch_assoc()) {

    $course = !empty($row['course'])
        ? $row['course']
        : ($row['student_course'] ?? 'Unspecified');

    $course = trim($course) !== ''
        ? $course
        : 'Unspecified';

    $byCourse[$course][] = $row;
}

ksort($byCourse);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Applications</title>

    <!-- EXTERNAL CSS -->
    <link rel="stylesheet" href="../css/admin/applications.css">

</head>

<body>

<div class="sidebar">

    <h2>Admin</h2>

    <span class="org-label">
        <?= $isSuperAdmin ? 'Super Admin' : 'Organization Admin' ?>
    </span>

    <div class="theme-toggle" id="themeToggle">
        <span>
            <span id="themeIcon"></span>
            <span id="themeLabel">Dark Mode</span>
        </span>

        <span>Tap to switch</span>
    </div>

    <?php if ($isSuperAdmin): ?>

        <a href="dashboard.php">Dashboard</a>
        <a href="dashboard.php">Create Post</a>

    <?php else: ?>

        <a href="org_dashboard.php">Dashboard</a>

    <?php endif; ?>

    <a href="applications.php">Applications</a>
    <a href="interviews.php">Interviews</a>
    <a href="records.php">Records</a>

    <?php if ($isSuperAdmin): ?>
        <a href="reports.php">Reports</a>
    <?php endif; ?>

    <a href="settings.php">Settings</a>
    <a href="../logout.php">Logout</a>

</div>


<div class="main">

    <h2>Applications</h2>

    <p class="page-description">
        Review each applicant's file, then set an interview or reject.
    </p>


    <?php if (empty($byCourse)): ?>

        <div class="empty-state">
            No pending applications right now.
        </div>

    <?php else: ?>

        <?php foreach ($byCourse as $course => $apps): ?>

            <div class="course-section">

                <div class="course-heading">

                    <h3>
                        <?= htmlspecialchars($course) ?>
                    </h3>

                    <span class="course-count">
                        <?= count($apps) ?> pending
                    </span>

                </div>


                <table>

                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Org</th>
                        <th>Role</th>
                        <th>Applied</th>
                        <th>Actions</th>
                    </tr>


                    <?php foreach ($apps as $a): ?>

                        <?php
                        $displayName = !empty($a['fullname'])
                            ? $a['fullname']
                            : ($a['full_name'] ?? '—');

                        $displayEmail = $a['email'] ?? '—';
                        ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($displayName) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($displayEmail) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($a['org_name'] ?? '—') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($a['role'] ?? '—') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($a['applied_at'] ?? '—') ?>
                            </td>

                            <td>

                                <div class="actions">

                                    <?php if (!empty($a['attachment'])): ?>

                                        <button
                                            type="button"
                                            class="view-btn"
                                            onclick="openFileModal(
                                                '../uploads/<?= htmlspecialchars($a['attachment']) ?>',
                                                <?= (int)$a['application_id'] ?>
                                            )">
                                            View File
                                        </button>

                                    <?php else: ?>

                                        <span class="no-file">
                                            No file
                                        </span>

                                    <?php endif; ?>


                                    <button
                                        type="button"
                                        class="btn-interview"
                                        onclick="openInterviewModal(<?= (int)$a['application_id'] ?>)">
                                        Set Interview
                                    </button>


                                    <form
                                        method="POST"
                                        action="update_status.php">

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$a['application_id'] ?>">

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="Rejected">

                                        <button
                                            type="submit"
                                            class="reject">
                                            Reject
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </table>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>


<!-- =========================================================
     FILE PREVIEW MODAL
========================================================= -->

<div id="fileModal" class="modal">

    <div class="modal-content file-modal-content">

        <div class="modal-header">

            <h3>Submitted File</h3>

            <button
                type="button"
                onclick="closeFileModal()"
                class="reject">
                Close
            </button>

        </div>


        <iframe
            id="filePreviewFrame"
            src="">
        </iframe>


        <div
            id="filePreviewUnsupported"
            class="file-preview-unsupported">

            This file type can't be previewed in-browser.
            <br>
            Use "Open in new tab" or "Download" below instead.

        </div>


        <p class="file-links">

            <a
                id="fileOpenNewTab"
                href="#"
                target="_blank">
                Open in new tab ↗
            </a>


            <a
                id="fileDownloadLink"
                href="#"
                download>
                ⬇ Download
            </a>

        </p>


        <div class="actions modal-actions">

            <button
                type="button"
                class="btn-interview"
                onclick="proceedToInterview()">
                Set Interview
            </button>


            <form
                method="POST"
                action="update_status.php"
                id="fileRejectForm">

                <input
                    type="hidden"
                    name="id"
                    id="fileRejectId">

                <input
                    type="hidden"
                    name="status"
                    value="Rejected">

                <button
                    type="submit"
                    class="reject">
                    Reject
                </button>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     SET INTERVIEW MODAL
========================================================= -->

<div id="interviewModal" class="modal">

    <div class="modal-content">

        <h3>Set Interview</h3>


        <form
            method="POST"
            action="set_interview.php">

            <input
                type="hidden"
                name="application_id"
                id="app_id">


            <input
                type="date"
                name="interview_date"
                required>


            <input
                type="time"
                name="interview_time"
                required>


            <input
                type="text"
                name="interview_location"
                placeholder="Location (e.g. Room 301)">


            <input
                type="text"
                name="interview_link"
                placeholder="Meeting link (optional)">


            <textarea
                name="interview_notes"
                placeholder="Notes for the applicant (optional)"></textarea>


            <button
                type="submit"
                class="btn-interview">
                Save
            </button>


            <button
                type="button"
                onclick="closeInterviewModal()"
                class="reject">
                Cancel
            </button>

        </form>

    </div>

</div>


<script>

let currentFileAppId = null;


/* =========================================================
   FILE MODAL
========================================================= */

function openFileModal(fileUrl, appId) {

    currentFileAppId = appId;

    const frame =
        document.getElementById("filePreviewFrame");

    const unsupported =
        document.getElementById("filePreviewUnsupported");


    /*
     * Only preview files that browsers can render directly.
     */

    const previewable =
        /\.(pdf|png|jpe?g|gif|webp)$/i;


    if (previewable.test(fileUrl)) {

        frame.src = fileUrl;
        frame.style.display = "block";

        unsupported.style.display = "none";

    } else {

        frame.src = "";
        frame.style.display = "none";

        unsupported.style.display = "block";
    }


    document.getElementById("fileOpenNewTab").href =
        fileUrl;

    document.getElementById("fileDownloadLink").href =
        fileUrl;

    document.getElementById("fileRejectId").value =
        appId;

    document.getElementById("fileModal").style.display =
        "block";
}


function closeFileModal() {

    document.getElementById("fileModal").style.display =
        "none";

    document.getElementById("filePreviewFrame").src =
        "";

    document.getElementById("filePreviewUnsupported").style.display =
        "none";
}


function proceedToInterview() {

    if (currentFileAppId === null) {
        return;
    }

    closeFileModal();

    openInterviewModal(currentFileAppId);
}


/* =========================================================
   INTERVIEW MODAL
========================================================= */

function openInterviewModal(id) {

    document.getElementById("interviewModal").style.display =
        "block";

    document.getElementById("app_id").value = id;
}


function closeInterviewModal() {

    document.getElementById("interviewModal").style.display =
        "none";
}


/* =========================================================
   THEME
========================================================= */

const root =
    document.documentElement;

const themeToggle =
    document.getElementById("themeToggle");

const themeIcon =
    document.getElementById("themeIcon");

const themeLabel =
    document.getElementById("themeLabel");


function applyTheme(theme) {

    if (theme === "light") {

        root.classList.add("light");

        themeIcon.textContent = "";

        themeLabel.textContent =
            "Light Mode";

    } else {

        root.classList.remove("light");

        themeIcon.textContent = "";

        themeLabel.textContent =
            "Dark Mode";
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


/* =========================================================
   CLOSE MODALS WHEN CLICKING OUTSIDE
========================================================= */

window.addEventListener("click", function(event) {

    const fileModal =
        document.getElementById("fileModal");

    const interviewModal =
        document.getElementById("interviewModal");


    if (event.target === fileModal) {
        closeFileModal();
    }


    if (event.target === interviewModal) {
        closeInterviewModal();
    }

});


/* =========================================================
   ESC KEY
========================================================= */

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        closeFileModal();
        closeInterviewModal();

    }

});

</script>

</body>
</html>