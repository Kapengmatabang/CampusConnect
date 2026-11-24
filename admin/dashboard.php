<?php

// ============================================================
// SETTINGS
// Set DEBUG to true ONLY while fixing problems.
// Set it back to false when everything works.
// ============================================================
define('DEBUG', true);

ini_set('display_errors', DEBUG ? 1 : 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Make mysqli throw exceptions so we can catch them
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Any uncaught error shows a readable message instead of a blank HTTP 500
set_exception_handler(function (Throwable $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Error</title></head>'
       . '<body style="font-family:sans-serif;padding:24px;">'
       . '<h2>Something went wrong</h2>'
       . '<p>' . (DEBUG
            ? nl2br(htmlspecialchars($e->getMessage()))
            : 'Please try again later.') . '</p>'
       . '</body></html>';
});

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");


// ============================================================
// LOCATE auth.php  (FIX for "Failed to open stream" error)
// Tries every common location instead of assuming one.
// ============================================================
$authCandidates = [
    __DIR__ . '/auth.php',
    __DIR__ . '/admin/auth.php',
    __DIR__ . '/includes/auth.php',
    dirname(__DIR__) . '/auth.php',
    dirname(__DIR__) . '/admin/auth.php',
    dirname(__DIR__) . '/includes/auth.php',
];

$authFile = null;
foreach ($authCandidates as $candidate) {
    if (is_file($candidate)) {
        $authFile = $candidate;
        break;
    }
}

if ($authFile === null) {
    throw new Exception(
        "auth.php was not found. I looked in:\n" . implode("\n", $authCandidates) .
        "\n\nPut auth.php in one of those places, or add its real path to \$authCandidates."
    );
}

require $authFile;
// sets $conn, $scopedOrgId, $isSuperAdmin;
// redirects if not logged in as admin

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dashboard is Main Admin only.
redirectIfNotSuperAdmin($isSuperAdmin, 'org_dashboard.php');


// ============================================================
// PATHS
// If this file is inside an "admin" folder, assets are one level up.
// If it is in the project root, assets are in the same folder.
// ============================================================
$base       = (basename(__DIR__) === 'admin') ? '../' : '';
$uploadsDir = __DIR__ . '/' . $base . 'uploads';
$selfUrl    = basename($_SERVER['SCRIPT_NAME']);

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

$orgError   = "";
$orgSuccess = "";

// Show a success message saved before a redirect
if (!empty($_SESSION['flash_success'])) {
    $orgSuccess = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}


/* =========================================================
   HELPERS
========================================================= */

/**
 * Safely saves an uploaded image.
 * Returns:  ''      -> no file was chosen
 *           string  -> the new file name (saved in $targetDir)
 *           false   -> the file was rejected (wrong type / too big / failed)
 */
function saveUploadedImage(array $file, string $targetDir)
{
    if (empty($file['name'])) {
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return false;
    }

    // Max 5 MB
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed, true)) {
        return false;
    }

    // Must really be an image (blocks renamed .php files)
    if (@getimagesize($file['tmp_name']) === false) {
        return false;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $newName = time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;

    if (!move_uploaded_file($file['tmp_name'], rtrim($targetDir, '/\\') . "/" . $newName)) {
        return false;
    }

    return $newName;
}

$imageErrorMsg = "Image must be a JPG, PNG, GIF or WEBP file under 5 MB.";


/* =========================================================
   CSRF PROTECTION
========================================================= */

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$csrf   = $_SESSION['csrf'];
$csrfOk = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfOk = hash_equals($csrf, $_POST['csrf'] ?? '');

    if (!$csrfOk) {
        $orgError = "Your session expired. Please refresh the page and try again.";
    }
}


/* =========================================================
   CREATE POST
========================================================= */

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {

    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $tag     = $_POST['tag'] ?? 'latest';

    if (!in_array($tag, ['latest', 'applicants', 'research'], true)) {
        $tag = 'latest';
    }

    if ($title === '' || $content === '') {

        $orgError = "Title and content are required.";

    } else {

        $imageName = saveUploadedImage($_FILES['image'] ?? [], $uploadsDir);

        if ($imageName === false) {

            $orgError = $imageErrorMsg;

        } else {

            $stmt = $conn->prepare("
                INSERT INTO posts (title, content, tag, image)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param("ssss", $title, $content, $tag, $imageName);
            $stmt->execute();
            $stmt->close();

            header("Location: " . $selfUrl);
            exit();
        }
    }
}


/* =========================================================
   DELETE POST
========================================================= */

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {

    $id = intval($_POST['id'] ?? 0);

    $stmt = $conn->prepare("SELECT image FROM posts WHERE post_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!empty($data['image'])) {

        $imageFile = $uploadsDir . "/" . basename($data['image']);

        if (is_file($imageFile)) {
            unlink($imageFile);
        }
    }

    $stmt = $conn->prepare("DELETE FROM posts WHERE post_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: " . $selfUrl);
    exit();
}


/* =========================================================
   ADD ORGANIZATION
========================================================= */

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_org'])) {

    $orgName           = trim($_POST['org_name'] ?? '');
    $orgDescription    = trim($_POST['description'] ?? '');
    $orgFacebook       = trim($_POST['facebook_link'] ?? '');
    $orgCourseGroup    = trim($_POST['course_group'] ?? '');
    $orgPositions      = trim($_POST['positions'] ?? '');
    $orgQualifications = trim($_POST['qualifications'] ?? '');

    if ($orgName === '' || !in_array($orgCourseGroup, $courseGroups, true)) {

        $orgError = "Organization name and a valid course group are required.";

    } elseif ($orgFacebook !== '' && !filter_var($orgFacebook, FILTER_VALIDATE_URL)) {

        $orgError = "Please enter a valid Facebook link.";

    } else {

        $fileName = saveUploadedImage($_FILES['org_image'] ?? [], $uploadsDir . "/orgs");

        if ($fileName === false) {

            $orgError = $imageErrorMsg;

        } else {

            $imagePath = ($fileName !== '') ? "uploads/orgs/" . $fileName : null;

            try {

                $stmt = $conn->prepare("
                    INSERT INTO organizations
                        (org_name, description, facebook_link, course_group, image, positions, qualifications)
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

                $stmt->execute();
                $stmt->close();

                $_SESSION['flash_success'] = "Organization \"$orgName\" created.";
                header("Location: " . $selfUrl);
                exit();

            } catch (mysqli_sql_exception $e) {

                // 1062 = duplicate entry (name already used)
                if ((int)$e->getCode() === 1062) {
                    $orgError = "That organization name is already in use.";
                } else {
                    throw $e;
                }
            }
        }
    }
}


/* =========================================================
   EDIT ORGANIZATION
========================================================= */

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_org'])) {

    $orgId             = intval($_POST['org_id'] ?? 0);
    $orgName           = trim($_POST['org_name'] ?? '');
    $orgDescription    = trim($_POST['description'] ?? '');
    $orgFacebook       = trim($_POST['facebook_link'] ?? '');
    $orgCourseGroup    = trim($_POST['course_group'] ?? '');
    $orgPositions      = trim($_POST['positions'] ?? '');
    $orgQualifications = trim($_POST['qualifications'] ?? '');

    if (!$orgId || $orgName === '' || !in_array($orgCourseGroup, $courseGroups, true)) {

        $orgError = "Organization name and a valid course group are required.";

    } elseif ($orgFacebook !== '' && !filter_var($orgFacebook, FILTER_VALIDATE_URL)) {

        $orgError = "Please enter a valid Facebook link.";

    } else {

        // Only update image when a new file is uploaded.
        $fileName = saveUploadedImage($_FILES['org_image'] ?? [], $uploadsDir . "/orgs");

        if ($fileName === false) {

            $orgError = $imageErrorMsg;

        } else {

            try {

                if ($fileName !== '') {

                    $imagePath = "uploads/orgs/" . $fileName;

                    $stmt = $conn->prepare("
                        UPDATE organizations
                        SET org_name=?, description=?, facebook_link=?, course_group=?,
                            image=?, positions=?, qualifications=?
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
                        SET org_name=?, description=?, facebook_link=?, course_group=?,
                            positions=?, qualifications=?
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

                $stmt->execute();
                $stmt->close();

                $_SESSION['flash_success'] = "Organization \"$orgName\" updated.";
                header("Location: " . $selfUrl);
                exit();

            } catch (mysqli_sql_exception $e) {

                if ((int)$e->getCode() === 1062) {
                    $orgError = "That organization name is already in use.";
                } else {
                    throw $e;
                }
            }
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

$stats = [
    'total'    => (int)($stats['total'] ?? 0),
    'pending'  => (int)($stats['pending'] ?? 0),
    'approved' => (int)($stats['approved'] ?? 0),
    'rejected' => (int)($stats['rejected'] ?? 0),
];

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

$pendingCount = $stats['pending'];

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard</title>

    <link rel="icon" type="image/png" href="<?= $base ?>img/favicon.png">

    <!-- EXTERNAL CSS -->
    <link rel="stylesheet" href="<?= $base ?>css/admin/dashboard.css">

    <!-- Apply saved theme immediately (prevents a dark/light flash) -->
    <script>
        try {
            if (localStorage.getItem("cc_admin_theme") === "light") {
                document.documentElement.classList.add("light");
            }
        } catch (e) {}
    </script>

    <!-- RESPONSIVE SAFETY FIXES -->
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            max-width: 100%;
            overflow-x: hidden;
            overflow-x: clip;
        }

        img { max-width: 100%; height: auto; }

        .modal-content {
            max-width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>

</head>

<body>


<!-- SIDEBAR -->

<div class="sidebar">

    <h2>Admin</h2>

    <span class="role-label">Web Admin</span>

    <div class="theme-toggle" id="themeToggle">
        <span>
            <span id="themeIcon">🌙</span>
            <span id="themeLabel">Dark Mode</span>
        </span>
        <span>Tap to switch</span>
    </div>

    <a href="<?= htmlspecialchars($selfUrl) ?>">Dashboard</a>

    <a href="javascript:void(0)" onclick="openPostModal()">Create Post</a>

    <a href="applications.php">Applications</a>

    <a href="interviews.php">Interviews</a>

    <a href="records.php">Records</a>

    <a href="reports.php">Reports</a>

    <a href="settings.php">Settings</a>

    <a href="<?= $base ?>logout.php">Logout</a>

</div>


<!-- MAIN -->

<div class="main">

    <h2>Dashboard</h2>


    <!-- STATISTICS -->

    <div class="cards">

        <div class="card">Total<br><?= $stats['total'] ?></div>

        <div class="card">Pending<br><?= $stats['pending'] ?></div>

        <div class="card">Approved<br><?= $stats['approved'] ?></div>

        <div class="card">Rejected<br><?= $stats['rejected'] ?></div>

    </div>


    <!-- NOTIFICATION -->

    <div class="notification-card <?= $pendingCount > 0 ? 'has-new' : '' ?>">

        <div class="notif-icon">

            🔔

            <?php if ($pendingCount > 0): ?>
                <span class="notif-badge"><?= $pendingCount ?></span>
            <?php endif; ?>

        </div>

        <div class="notif-text">

            <?php if ($pendingCount > 0): ?>

                <strong>
                    <?= $pendingCount ?>
                    New Application<?= $pendingCount === 1 ? '' : 's' ?>
                </strong>

                <span>Applicants are waiting for a decision.</span>

            <?php else: ?>

                <strong>No New Applications</strong>

                <span>You're all caught up.</span>

            <?php endif; ?>

        </div>

        <a href="applications.php" class="view-application-btn">
            View Applications
        </a>

    </div>


    <!-- ALERTS -->

    <?php if ($orgSuccess): ?>
        <div class="alert success"><?= htmlspecialchars($orgSuccess) ?></div>
    <?php endif; ?>

    <?php if ($orgError): ?>
        <div class="alert error"><?= htmlspecialchars($orgError) ?></div>
    <?php endif; ?>


    <!-- ORGANIZATIONS -->

    <div class="section-header">

        <h3>Organizations</h3>

        <button type="button" class="add-btn" onclick="openAddOrgModal()">
            + Add Organization
        </button>

    </div>


    <div class="org-grid">

        <?php while ($o = $organizations->fetch_assoc()): ?>

            <?php
            $orgImg = !empty($o['image'])
                ? $base . htmlspecialchars($o['image'])
                : $base . 'img/default.png';
            ?>

            <div class="org-card">

                <div class="org-img-box">
                    <img src="<?= $orgImg ?>"
                         alt="<?= htmlspecialchars($o['org_name']) ?>">
                </div>

                <span class="badge">
                    <?= htmlspecialchars($o['course_group']) ?>
                </span>

                <h4><?= htmlspecialchars($o['org_name']) ?></h4>

                <p class="org-desc">
                    <?= htmlspecialchars(
                        mb_strimwidth($o['description'] ?? '', 0, 70, '...')
                    ) ?>
                </p>

                <?php if (!empty($o['facebook_link'])): ?>
                    <a class="org-fb-link"
                       href="<?= htmlspecialchars($o['facebook_link']) ?>"
                       target="_blank"
                       rel="noopener">
                        Facebook Page ↗
                    </a>
                <?php endif; ?>

                <button
                    type="button"
                    class="edit-btn"
                    data-id="<?= (int)$o['org_id'] ?>"
                    data-name="<?= htmlspecialchars($o['org_name'], ENT_QUOTES) ?>"
                    data-description="<?= htmlspecialchars($o['description'] ?? '', ENT_QUOTES) ?>"
                    data-facebook="<?= htmlspecialchars($o['facebook_link'] ?? '', ENT_QUOTES) ?>"
                    data-course-group="<?= htmlspecialchars($o['course_group'], ENT_QUOTES) ?>"
                    data-positions="<?= htmlspecialchars($o['positions'] ?? '', ENT_QUOTES) ?>"
                    data-qualifications="<?= htmlspecialchars($o['qualifications'] ?? '', ENT_QUOTES) ?>"
                    data-image="<?= !empty($o['image']) ? $base . htmlspecialchars($o['image'], ENT_QUOTES) : '' ?>"
                    onclick="openEditOrgModal(this)">
                    Edit
                </button>

            </div>

        <?php endwhile; ?>

    </div>


    <!-- POSTS -->

    <div class="section-header">
        <h3>Posts</h3>
    </div>

    <?php while ($p = $posts->fetch_assoc()): ?>

        <div class="post-box">

            <b><?= htmlspecialchars($p['title']) ?></b>

            <p><?= nl2br(htmlspecialchars($p['content'])) ?></p>

            <?php if (!empty($p['image'])): ?>
                <img src="<?= $base ?>uploads/<?= htmlspecialchars($p['image']) ?>"
                     alt="Post image">
            <?php endif; ?>

            <form method="POST" onsubmit="return confirm('Delete this post?');">

                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int)$p['post_id'] ?>">

                <button type="submit" name="delete_post" class="delete">
                    Delete
                </button>

            </form>

        </div>

    <?php endwhile; ?>

</div>


<!-- CREATE POST MODAL -->

<div id="postModal" class="modal">

    <div class="modal-content">

        <h3>Create Post</h3>

        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

            <input type="text" name="title" placeholder="Title" required>

            <textarea name="content" placeholder="Content" required></textarea>

            <select name="tag">
                <option value="latest">Latest</option>
                <option value="applicants">Applicants</option>
                <option value="research">Research</option>
            </select>

            <input type="file" name="image" accept="image/*">

            <button type="submit" name="create_post" class="approve">Post</button>

            <button type="button" onclick="closePostModal()" class="reject">Cancel</button>

        </form>

    </div>

</div>


<!-- ADD / EDIT ORGANIZATION MODAL -->

<div id="orgModal" class="modal">

    <div class="modal-content">

        <h3 id="orgModalTitle">Add Organization</h3>

        <form method="POST" enctype="multipart/form-data" id="orgForm">

            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

            <input type="hidden" name="org_id" id="orgIdField">

            <label class="field-label">Organization Name</label>
            <input type="text" name="org_name" id="orgNameField"
                   placeholder="e.g. BS Computer Science Society" required>

            <label class="field-label">Course Group</label>
            <select name="course_group" id="orgCourseGroupField" required>
                <?php foreach ($courseGroups as $cg): ?>
                    <option value="<?= htmlspecialchars($cg) ?>">
                        <?= htmlspecialchars($cg) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="field-label">Description</label>
            <textarea name="description" id="orgDescriptionField"
                      placeholder="Short description shown to students"></textarea>

            <label class="field-label">Facebook Page</label>
            <input type="url" name="facebook_link" id="orgFacebookField"
                   placeholder="https://facebook.com/yourorgpage">

            <label class="field-label">
                Open Positions
                <span class="optional-label">(one per line)</span>
            </label>
            <textarea name="positions" id="orgPositionsField"
                      placeholder="Secretary&#10;Treasurer&#10;Committee Member"></textarea>

            <label class="field-label">
                Qualifications
                <span class="optional-label">(one per line)</span>
            </label>
            <textarea name="qualifications" id="orgQualificationsField"
                      placeholder="Currently enrolled&#10;Good academic standing"></textarea>

            <label class="field-label">Logo / Image</label>

            <p class="current-image" id="orgCurrentImageNote"></p>

            <input type="file" name="org_image" id="orgImageField" accept="image/*">

            <button type="submit" name="add_org" id="orgSubmitBtn" class="approve">
                Create Organization
            </button>

            <button type="button" onclick="closeOrgModal()" class="reject">
                Cancel
            </button>

        </form>

    </div>

</div>


<script>

/* ---------- POST MODAL ---------- */

function openPostModal() {
    document.getElementById("postModal").style.display = "block";
}

function closePostModal() {
    document.getElementById("postModal").style.display = "none";
}


/* ---------- ORGANIZATION MODAL ---------- */

const orgModal               = document.getElementById("orgModal");
const orgModalTitle          = document.getElementById("orgModalTitle");
const orgIdField             = document.getElementById("orgIdField");
const orgNameField           = document.getElementById("orgNameField");
const orgCourseGroupField    = document.getElementById("orgCourseGroupField");
const orgDescriptionField    = document.getElementById("orgDescriptionField");
const orgFacebookField       = document.getElementById("orgFacebookField");
const orgPositionsField      = document.getElementById("orgPositionsField");
const orgQualificationsField = document.getElementById("orgQualificationsField");
const orgCurrentImageNote    = document.getElementById("orgCurrentImageNote");
const orgSubmitBtn           = document.getElementById("orgSubmitBtn");
const orgImageField          = document.getElementById("orgImageField");

function openAddOrgModal() {
    orgModalTitle.innerText = "Add Organization";
    orgIdField.value = "";
    orgNameField.value = "";
    orgCourseGroupField.value = "GENERAL";
    orgDescriptionField.value = "";
    orgFacebookField.value = "";
    orgPositionsField.value = "";
    orgQualificationsField.value = "";
    orgImageField.value = "";
    orgCurrentImageNote.innerText = "";
    orgSubmitBtn.name = "add_org";
    orgSubmitBtn.innerText = "Create Organization";
    orgModal.style.display = "block";
}

function openEditOrgModal(btn) {
    orgModalTitle.innerText = "Edit Organization";
    orgIdField.value = btn.dataset.id;
    orgNameField.value = btn.dataset.name;
    orgCourseGroupField.value = btn.dataset.courseGroup;
    orgDescriptionField.value = btn.dataset.description;
    orgFacebookField.value = btn.dataset.facebook;
    orgPositionsField.value = btn.dataset.positions;
    orgQualificationsField.value = btn.dataset.qualifications;
    orgImageField.value = "";
    orgCurrentImageNote.innerText = btn.dataset.image
        ? "Current image is kept unless you upload a new one."
        : "No image set yet.";
    orgSubmitBtn.name = "edit_org";
    orgSubmitBtn.innerText = "Save Changes";
    orgModal.style.display = "block";
}

function closeOrgModal() {
    orgModal.style.display = "none";
}


/* ---------- THEME ---------- */

const root        = document.documentElement;
const themeToggle = document.getElementById("themeToggle");
const themeIcon   = document.getElementById("themeIcon");
const themeLabel  = document.getElementById("themeLabel");

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

// Safe even if the browser blocks storage
let savedTheme = "dark";
try {
    savedTheme = localStorage.getItem("cc_admin_theme") || "dark";
} catch (e) {}

applyTheme(savedTheme);

themeToggle.addEventListener("click", () => {
    const newTheme = root.classList.contains("light") ? "dark" : "light";
    applyTheme(newTheme);
    try {
        localStorage.setItem("cc_admin_theme", newTheme);
    } catch (e) {}
});


/* ---------- CLOSE MODALS ---------- */

window.addEventListener("click", function (event) {
    if (event.target === orgModal) {
        closeOrgModal();
    }
    if (event.target === document.getElementById("postModal")) {
        closePostModal();
    }
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        closeOrgModal();
        closePostModal();
    }
});

</script>

</body>
</html>