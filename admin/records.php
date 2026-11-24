<?php
require 'auth.php'; // sets $conn, $scopedOrgId, $isSuperAdmin; redirects if not logged in as admin

// Optional status filter via ?status=Pending / Interview / Approved / Rejected / All
$statusFilter = $_GET['status'] ?? 'All';
$validStatuses = ['All', 'Pending', 'Interview', 'Approved', 'Rejected'];
if (!in_array($statusFilter, $validStatuses, true)) $statusFilter = 'All';

$sql = "
    SELECT a.*, s.full_name, s.email, o.org_name
    FROM applications a
    LEFT JOIN students s ON a.student_id = s.student_id
    LEFT JOIN organizations o ON a.org_id = o.org_id
    WHERE 1=1
";
$params = [];
$types = "";

if (!$isSuperAdmin) {
    $sql .= " AND a.org_id = ? ";
    $params[] = $scopedOrgId;
    $types .= "i";
}

if ($statusFilter !== 'All') {
    $sql .= " AND a.status = ? ";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " ORDER BY a.applied_at DESC";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$records = $stmt->get_result();

// Counts for the filter tabs (respecting org scope, not status filter)
$countSql = "
    SELECT
    COUNT(*) total,
    SUM(status='Pending') pending,
    SUM(status='Interview') interview,
    SUM(status='Approved') approved,
    SUM(status='Rejected') rejected
    FROM applications
";
if (!$isSuperAdmin) {
    $countStmt = $conn->prepare($countSql . " WHERE org_id = ?");
    $countStmt->bind_param("i", $scopedOrgId);
    $countStmt->execute();
    $counts = $countStmt->get_result()->fetch_assoc();
} else {
    $counts = $conn->query($countSql)->fetch_assoc();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Records</title>
    <link rel="stylesheet" href="../css/admin/records.css">
</head>

<body>

<div class="sidebar">
    <h2>Admin</h2>

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

    <h2>Applicant Records</h2>

    <p class="page-description">
        <?= $isSuperAdmin
            ? 'Full history across every organization.'
            : 'Full history for your organization.'
        ?>
    </p>

    <div class="filter-tabs">

        <a href="records.php?status=All"
           class="filter-tab <?= $statusFilter === 'All' ? 'active' : '' ?>">
            All
            <span class="count"><?= (int)($counts['total'] ?? 0) ?></span>
        </a>

        <a href="records.php?status=Pending"
           class="filter-tab <?= $statusFilter === 'Pending' ? 'active' : '' ?>">
            Pending
            <span class="count"><?= (int)($counts['pending'] ?? 0) ?></span>
        </a>

        <a href="records.php?status=Interview"
           class="filter-tab <?= $statusFilter === 'Interview' ? 'active' : '' ?>">
            Interview
            <span class="count"><?= (int)($counts['interview'] ?? 0) ?></span>
        </a>

        <a href="records.php?status=Approved"
           class="filter-tab <?= $statusFilter === 'Approved' ? 'active' : '' ?>">
            Approved
            <span class="count"><?= (int)($counts['approved'] ?? 0) ?></span>
        </a>

        <a href="records.php?status=Rejected"
           class="filter-tab <?= $statusFilter === 'Rejected' ? 'active' : '' ?>">
            Rejected
            <span class="count"><?= (int)($counts['rejected'] ?? 0) ?></span>
        </a>

    </div>

    <?php if ($records->num_rows === 0): ?>

        <div class="empty-state">
            No records found for this filter.
        </div>

    <?php else: ?>

        <table>

            <tr>
                <th>Student</th>
                <th>Email</th>

                <?php if ($isSuperAdmin): ?>
                    <th>Org</th>
                <?php endif; ?>

                <th>Status</th>
                <th>Reapply Date</th>
                <th>Applied</th>
                <th>Actions</th>
            </tr>

            <?php while ($r = $records->fetch_assoc()):

                $displayName = !empty($r['fullname'])
                    ? $r['fullname']
                    : $r['full_name'];

            ?>

            <tr>

                <td>
                    <?= htmlspecialchars($displayName) ?>
                </td>

                <td>
                    <?= htmlspecialchars($r['email']) ?>
                </td>

                <?php if ($isSuperAdmin): ?>
                    <td>
                        <?= htmlspecialchars($r['org_name']) ?>
                    </td>
                <?php endif; ?>

                <td>
                    <span class="status-label <?= strtolower($r['status']) ?>">
                        <?= htmlspecialchars($r['status']) ?>
                    </span>
                </td>

                <td>

                    <?php if ($r['status'] === 'Rejected' && !empty($r['reapply_date'])): ?>

                        <?= date('M j, Y', strtotime($r['reapply_date'])) ?>

                        <?php
                        $daysUntil =
                            (strtotime($r['reapply_date']) - strtotime('today')) / 86400;
                        ?>

                        <?php if ($r['reapply_notified']): ?>

                            <div class="reapply-status notified">
                                ✓ Notified
                            </div>

                        <?php elseif ($daysUntil <= 0): ?>

                            <div class="reapply-status open-now">
                                Open now
                            </div>

                        <?php elseif ($daysUntil == 1): ?>

                            <div class="reapply-status tomorrow">
                                Opens tomorrow
                            </div>

                        <?php endif; ?>

                    <?php else: ?>

                        <span class="no-file">—</span>

                    <?php endif; ?>

                </td>

                <td>
                    <?= $r['applied_at'] ?>
                </td>

                <td>

                    <div class="actions">

                        <?php if (!empty($r['attachment'])): ?>

                            <button
                                class="view-btn"
                                onclick="openFileModal('../uploads/<?= htmlspecialchars($r['attachment']) ?>')">
                                View File
                            </button>

                        <?php else: ?>

                            <span class="no-file">
                                No file
                            </span>

                        <?php endif; ?>

                        <?php if ($isSuperAdmin): ?>

                            <form
                                method="POST"
                                action="delete_application.php"
                                onsubmit="return confirm('Permanently delete this applicant record? This cannot be undone.');">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= $r['application_id'] ?>">

                                <input
                                    type="hidden"
                                    name="status_filter"
                                    value="<?= htmlspecialchars($statusFilter) ?>">

                                <button
                                    type="submit"
                                    class="delete">
                                    Delete
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                </td>

            </tr>

            <?php endwhile; ?>

        </table>

    <?php endif; ?>

</div>


<!-- FILE PREVIEW MODAL -->

<div id="fileModal" class="modal">

    <div class="modal-content">

        <div class="modal-header">

            <h3>Submitted File</h3>

            <button
                type="button"
                onclick="closeFileModal()"
                class="delete">
                Close
            </button>

        </div>

        <iframe
            id="filePreviewFrame"
            src="">
        </iframe>

        <div id="filePreviewUnsupported">

            This file type can't be previewed in-browser.<br>

            Use "Open in new tab" or "Download" below instead.

        </div>

        <p class="file-actions">

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

    </div>

</div>


<script>

function openFileModal(fileUrl) {

    const frame =
        document.getElementById("filePreviewFrame");

    const unsupported =
        document.getElementById("filePreviewUnsupported");

    // Only load the iframe for file types
    // that browsers can render inline.

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


// ============ THEME TOGGLE ============

const root = document.documentElement;

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

</script>

</body>
</html>