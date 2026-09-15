<?php

require 'auth.php';
// sets $conn, $scopedOrgId, $isSuperAdmin;
// redirects if not logged in as admin

// Dashboard is Main Admin only.
redirectIfNotSuperAdmin($isSuperAdmin, 'org_dashboard.php');

$courseGroups = [
    'GENERAL',
    'MEDIA',
    'CAS',
    'CITCS',
    'CBA',
    'CCJ',
    'CTE',
    'INSTITUTE'
];

$orgError = "";
$orgSuccess = "";


/* =========================================================
   CREATE POST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {

    $title = $_POST['title'];
    $content = $_POST['content'];
    $tag = $_POST['tag'];

    $imageName = "";

    if (!empty($_FILES['image']['name'])) {

        if (!is_dir("../uploads")) {
            mkdir("../uploads", 0777, true);
        }

        $imageName =
            time() . "_" . basename($_FILES['image']['name']);

        move_uploaded_file(
            $_FILES['image']['tmp_name'],
            "../uploads/" . $imageName
        );
    }

    $stmt = $conn->prepare("
        INSERT INTO posts (title, content, tag, image)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssss",
        $title,
        $content,
        $tag,
        $imageName
    );

    $stmt->execute();

    header("Location: dashboard.php");
    exit();
}


/* =========================================================
   DELETE POST
========================================================= */

if (isset($_POST['delete_post'])) {

    $id = intval($_POST['id']);

    $res = $conn->query(
        "SELECT image FROM posts WHERE post_id=$id"
    );

    $data = $res->fetch_assoc();

    if (
        !empty($data['image']) &&
        file_exists("../uploads/" . $data['image'])
    ) {
        unlink("../uploads/" . $data['image']);
    }

    $conn->query(
        "DELETE FROM posts WHERE post_id=$id"
    );

    header("Location: dashboard.php");
    exit();
}


/* =========================================================
   ADD ORGANIZATION
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_org'])) {

    $orgName = trim($_POST['org_name'] ?? '');
    $orgDescription = trim($_POST['description'] ?? '');
    $orgFacebook = trim($_POST['facebook_link'] ?? '');
    $orgCourseGroup = trim($_POST['course_group'] ?? '');
    $orgPositions = trim($_POST['positions'] ?? '');
    $orgQualifications = trim($_POST['qualifications'] ?? '');

    if (
        empty($orgName) ||
        !in_array($orgCourseGroup, $courseGroups, true)
    ) {

        $orgError =
            "Organization name and a valid course group are required.";

    } elseif (
        $orgFacebook !== '' &&
        !filter_var($orgFacebook, FILTER_VALIDATE_URL)
    ) {

        $orgError =
            "Please enter a valid Facebook link.";

    } else {

        $imagePath = null;

        if (!empty($_FILES['org_image']['name'])) {

            if (!is_dir("../uploads/orgs")) {
                mkdir("../uploads/orgs", 0777, true);
            }

            $fileName =
                time() . "_" .
                basename($_FILES['org_image']['name']);

            move_uploaded_file(
                $_FILES['org_image']['tmp_name'],
                "../uploads/orgs/" . $fileName
            );

            $imagePath =
                "uploads/orgs/" . $fileName;
        }

        $stmt = $conn->prepare("
            INSERT INTO organizations
            (
                org_name,
                description,
                facebook_link,
                course_group,
                image,
                positions,
                qualifications
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssssss",
            $orgName,
            $orgDescription,
            $orgFacebook,
            $orgCourseGroup,
            $imagePath,
            $orgPositions,
            $orgQualifications
        );

        if ($stmt->execute()) {

            $orgSuccess =
                "Organization \"$orgName\" created.";

        } else {

            $orgError =
                "That organization name is already in use.";
        }
    }
}


/* =========================================================
   EDIT ORGANIZATION
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_org'])) {

    $orgId = intval($_POST['org_id'] ?? 0);
    $orgName = trim($_POST['org_name'] ?? '');
    $orgDescription = trim($_POST['description'] ?? '');
    $orgFacebook = trim($_POST['facebook_link'] ?? '');
    $orgCourseGroup = trim($_POST['course_group'] ?? '');
    $orgPositions = trim($_POST['positions'] ?? '');
    $orgQualifications = trim($_POST['qualifications'] ?? '');

    if (
        !$orgId ||
        empty($orgName) ||
        !in_array($orgCourseGroup, $courseGroups, true)
    ) {

        $orgError =
            "Organization name and a valid course group are required.";

    } elseif (
        $orgFacebook !== '' &&
        !filter_var($orgFacebook, FILTER_VALIDATE_URL)
    ) {

        $orgError =
            "Please enter a valid Facebook link.";

    } else {

        /*
         * Only update image when a new file is uploaded.
         */

        if (!empty($_FILES['org_image']['name'])) {

            if (!is_dir("../uploads/orgs")) {
                mkdir("../uploads/orgs", 0777, true);
            }

            $fileName =
                time() . "_" .
                basename($_FILES['org_image']['name']);

            move_uploaded_file(
                $_FILES['org_image']['tmp_name'],
                "../uploads/orgs/" . $fileName
            );

            $imagePath =
                "uploads/orgs/" . $fileName;

            $stmt = $conn->prepare("
                UPDATE organizations
                SET
                    org_name=?,
                    description=?,
                    facebook_link=?,
                    course_group=?,
                    image=?,
                    positions=?,
                    qualifications=?
                WHERE org_id=?
            ");

            $stmt->bind_param(
                "sssssssi",
                $orgName,
                $orgDescription,
                $orgFacebook,
                $orgCourseGroup,
                $imagePath,
                $orgPositions,
                $orgQualifications,
                $orgId
            );

        } else {

            $stmt = $conn->prepare("
                UPDATE organizations
                SET
                    org_name=?,
                    description=?,
                    facebook_link=?,
                    course_group=?,
                    positions=?,
                    qualifications=?
                WHERE org_id=?
            ");

            $stmt->bind_param(
                "ssssssi",
                $orgName,
                $orgDescription,
                $orgFacebook,
                $orgCourseGroup,
                $orgPositions,
                $orgQualifications,
                $orgId
            );
        }

        if ($stmt->execute()) {

            $orgSuccess =
                "Organization \"$orgName\" updated.";

        } else {

            $orgError =
                "That organization name is already in use.";
        }
    }
}


/* =========================================================
   DASHBOARD DATA
========================================================= */

$stats = $conn->query("
    SELECT
        COUNT(*) total,
        SUM(status='Pending') pending,
        SUM(status='Approved') approved,
        SUM(status='Rejected') rejected
    FROM applications
")->fetch_assoc();

$posts = $conn->query("
    SELECT *
    FROM posts
    ORDER BY created_at DESC
");

$organizations = $conn->query("
    SELECT *
    FROM organizations
    ORDER BY course_group ASC, org_name ASC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard</title>

    <!-- EXTERNAL CSS -->
    <link rel="stylesheet" href="../css/admin/dashboard.css">

</head>

<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<div class="sidebar">

    <h2>Admin</h2>

    <span class="role-label">
        Main Admin
    </span>


    <div class="theme-toggle" id="themeToggle">

        <span>
            <span id="themeIcon"></span>
            <span id="themeLabel">Dark Mode</span>
        </span>

        <span>Tap to switch</span>

    </div>


    <a href="dashboard.php">
        Dashboard
    </a>

    <a href="javascript:void(0)"
       onclick="openPostModal()">
        Create Post
    </a>

    <a href="applications.php">
        Applications
    </a>

    <a href="interviews.php">
        Interviews
    </a>

    <a href="records.php">
        Records
    </a>

    <a href="reports.php">
        Reports
    </a>

    <a href="settings.php">
        Settings
    </a>

    <a href="../logout.php">
        Logout
    </a>

</div>


<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">

    <h2>Dashboard</h2>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="cards">

        <div class="card">
            Total<br>
            <?= $stats['total'] ?>
        </div>

        <div class="card">
            Pending<br>
            <?= $stats['pending'] ?>
        </div>

        <div class="card">
            Approved<br>
            <?= $stats['approved'] ?>
        </div>

        <div class="card">
            Rejected<br>
            <?= $stats['rejected'] ?>
        </div>

    </div>


    <!-- =====================================================
         NOTIFICATION
    ====================================================== -->

    <?php
    $pendingCount =
        (int)($stats['pending'] ?? 0);
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


        <a href="applications.php"
           class="view-application-btn">

            View Applications

        </a>

    </div>


    <!-- =====================================================
         ALERTS
    ====================================================== -->

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


    <!-- =====================================================
         ORGANIZATIONS
    ====================================================== -->

    <div class="section-header">

        <h3>
            Organizations
        </h3>

        <button
            type="button"
            class="add-btn"
            onclick="openAddOrgModal()">

            + Add Organization

        </button>

    </div>


    <div class="org-grid">

        <?php while ($o = $organizations->fetch_assoc()): ?>

            <div class="org-card">

                <div class="org-img-box">

                    <img
                        src="<?= !empty($o['image'])
                            ? '../' . htmlspecialchars($o['image'])
                            : '../img/default.png' ?>"
                        alt="<?= htmlspecialchars($o['org_name']) ?>">

                </div>


                <span class="badge">

                    <?= htmlspecialchars($o['course_group']) ?>

                </span>


                <h4>

                    <?= htmlspecialchars($o['org_name']) ?>

                </h4>


                <p class="org-desc">

                    <?= htmlspecialchars(
                        mb_strimwidth(
                            $o['description'] ?? '',
                            0,
                            70,
                            '...'
                        )
                    ) ?>

                </p>


                <?php if (!empty($o['facebook_link'])): ?>

                    <a
                        class="org-fb-link"
                        href="<?= htmlspecialchars($o['facebook_link']) ?>"
                        target="_blank"
                        rel="noopener">

                        Facebook Page ↗

                    </a>

                <?php endif; ?>


                <button
                    type="button"
                    class="edit-btn"

                    data-id="<?= $o['org_id'] ?>"

                    data-name="<?= htmlspecialchars(
                        $o['org_name'],
                        ENT_QUOTES
                    ) ?>"

                    data-description="<?= htmlspecialchars(
                        $o['description'] ?? '',
                        ENT_QUOTES
                    ) ?>"

                    data-facebook="<?= htmlspecialchars(
                        $o['facebook_link'] ?? '',
                        ENT_QUOTES
                    ) ?>"

                    data-course-group="<?= htmlspecialchars(
                        $o['course_group'],
                        ENT_QUOTES
                    ) ?>"

                    data-positions="<?= htmlspecialchars(
                        $o['positions'] ?? '',
                        ENT_QUOTES
                    ) ?>"

                    data-qualifications="<?= htmlspecialchars(
                        $o['qualifications'] ?? '',
                        ENT_QUOTES
                    ) ?>"

                    data-image="<?= !empty($o['image'])
                        ? '../' . htmlspecialchars($o['image'])
                        : '' ?>"

                    onclick="openEditOrgModal(this)">

                    Edit

                </button>

            </div>

        <?php endwhile; ?>

    </div>


    <!-- =====================================================
         POSTS
    ====================================================== -->

    <div class="section-header">

        <h3>
            Posts
        </h3>

    </div>


    <?php while ($p = $posts->fetch_assoc()): ?>

        <div class="post-box">

            <b>
                <?= htmlspecialchars($p['title']) ?>
            </b>


            <p>
                <?= nl2br(htmlspecialchars($p['content'])) ?>
            </p>


            <?php if (!empty($p['image'])): ?>

                <img
                    src="../uploads/<?= htmlspecialchars($p['image']) ?>"
                    alt="Post image">

            <?php endif; ?>


            <form method="POST">

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)$p['post_id'] ?>">

                <button
                    type="submit"
                    name="delete_post"
                    class="delete">

                    Delete

                </button>

            </form>

        </div>

    <?php endwhile; ?>

</div>


<!-- =========================================================
     CREATE POST MODAL
========================================================= -->

<div id="postModal" class="modal">

    <div class="modal-content">

        <h3>
            Create Post
        </h3>


        <form
            method="POST"
            enctype="multipart/form-data">

            <input
                type="text"
                name="title"
                placeholder="Title"
                required>


            <textarea
                name="content"
                placeholder="Content"
                required></textarea>


            <select name="tag">

                <option value="latest">
                    Latest
                </option>

                <option value="applicants">
                    Applicants
                </option>

                <option value="research">
                    Research
                </option>

            </select>


            <input
                type="file"
                name="image">


            <button
                type="submit"
                name="create_post"
                class="approve">

                Post

            </button>


            <button
                type="button"
                onclick="closePostModal()"
                class="reject">

                Cancel

            </button>

        </form>

    </div>

</div>


<!-- =========================================================
     ADD / EDIT ORGANIZATION MODAL
========================================================= -->

<div id="orgModal" class="modal">

    <div class="modal-content">

        <h3 id="orgModalTitle">
            Add Organization
        </h3>


        <form
            method="POST"
            enctype="multipart/form-data"
            id="orgForm">


            <input
                type="hidden"
                name="org_id"
                id="orgIdField">


            <label class="field-label">
                Organization Name
            </label>

            <input
                type="text"
                name="org_name"
                id="orgNameField"
                placeholder="e.g. BS Computer Science Society"
                required>


            <label class="field-label">
                Course Group
            </label>

            <select
                name="course_group"
                id="orgCourseGroupField"
                required>

                <?php foreach ($courseGroups as $cg): ?>

                    <option value="<?= htmlspecialchars($cg) ?>">
                        <?= htmlspecialchars($cg) ?>
                    </option>

                <?php endforeach; ?>

            </select>


            <label class="field-label">
                Description
            </label>

            <textarea
                name="description"
                id="orgDescriptionField"
                placeholder="Short description shown to students"></textarea>


            <label class="field-label">
                Facebook Page
            </label>

            <input
                type="url"
                name="facebook_link"
                id="orgFacebookField"
                placeholder="https://facebook.com/yourorgpage">


            <label class="field-label">
                Open Positions
                <span class="optional-label">
                    (one per line)
                </span>
            </label>

            <textarea
                name="positions"
                id="orgPositionsField"
                placeholder="Secretary&#10;Treasurer&#10;Committee Member"></textarea>


            <label class="field-label">
                Qualifications
                <span class="optional-label">
                    (one per line)
                </span>
            </label>

            <textarea
                name="qualifications"
                id="orgQualificationsField"
                placeholder="Currently enrolled&#10;Good academic standing"></textarea>


            <label class="field-label">
                Logo / Image
            </label>


            <p
                class="current-image"
                id="orgCurrentImageNote">
            </p>


            <input
                type="file"
                name="org_image"
                id="orgImageField">


            <button
                type="submit"
                name="add_org"
                id="orgSubmitBtn"
                class="approve">

                Create Organization

            </button>


            <button
                type="button"
                onclick="closeOrgModal()"
                class="reject">

                Cancel

            </button>

        </form>

    </div>

</div>


<script>

/* =========================================================
   POST MODAL
========================================================= */

function openPostModal() {

    document.getElementById("postModal").style.display =
        "block";
}


function closePostModal() {

    document.getElementById("postModal").style.display =
        "none";
}


/* =========================================================
   ORGANIZATION MODAL
========================================================= */

const orgModal =
    document.getElementById("orgModal");

const orgForm =
    document.getElementById("orgForm");

const orgModalTitle =
    document.getElementById("orgModalTitle");

const orgIdField =
    document.getElementById("orgIdField");

const orgNameField =
    document.getElementById("orgNameField");

const orgCourseGroupField =
    document.getElementById("orgCourseGroupField");

const orgDescriptionField =
    document.getElementById("orgDescriptionField");

const orgFacebookField =
    document.getElementById("orgFacebookField");

const orgPositionsField =
    document.getElementById("orgPositionsField");

const orgQualificationsField =
    document.getElementById("orgQualificationsField");

const orgCurrentImageNote =
    document.getElementById("orgCurrentImageNote");

const orgSubmitBtn =
    document.getElementById("orgSubmitBtn");


function openAddOrgModal() {

    orgModalTitle.innerText =
        "Add Organization";

    orgIdField.value = "";

    orgNameField.value = "";

    orgCourseGroupField.value =
        "GENERAL";

    orgDescriptionField.value = "";

    orgFacebookField.value = "";

    orgPositionsField.value = "";

    orgQualificationsField.value = "";

    orgCurrentImageNote.innerText = "";

    orgSubmitBtn.name =
        "add_org";

    orgSubmitBtn.innerText =
        "Create Organization";

    orgModal.style.display =
        "block";
}


function openEditOrgModal(btn) {

    orgModalTitle.innerText =
        "Edit Organization";

    orgIdField.value =
        btn.dataset.id;

    orgNameField.value =
        btn.dataset.name;

    orgCourseGroupField.value =
        btn.dataset.courseGroup;

    orgDescriptionField.value =
        btn.dataset.description;

    orgFacebookField.value =
        btn.dataset.facebook;

    orgPositionsField.value =
        btn.dataset.positions;

    orgQualificationsField.value =
        btn.dataset.qualifications;

    orgCurrentImageNote.innerText =
        btn.dataset.image
            ? "Current image is kept unless you upload a new one."
            : "No image set yet.";

    orgSubmitBtn.name =
        "edit_org";

    orgSubmitBtn.innerText =
        "Save Changes";

    orgModal.style.display =
        "block";
}


function closeOrgModal() {

    orgModal.style.display =
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
    localStorage.getItem("cc_admin_theme") ||
    "dark";


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

    if (event.target === orgModal) {
        closeOrgModal();
    }

    if (
        event.target ===
        document.getElementById("postModal")
    ) {
        closePostModal();
    }

});


/* =========================================================
   ESC KEY
========================================================= */

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        closeOrgModal();
        closePostModal();

    }

});

</script>

</body>
</html>