<?php
require 'auth.php'; // sets $conn, $scopedOrgId, $isSuperAdmin; redirects if not logged in as admin

$today = date('Y-m-d');

// ================= STATS =================
if ($isSuperAdmin) {
    $stats = $conn->query("
        SELECT
        SUM(status='Interview') AS scheduled,
        SUM(status='Interview' AND interview_date = '$today') AS today_count,
        SUM(status='Approved' AND interview_date IS NOT NULL) AS accepted,
        SUM(status='Rejected' AND interview_date IS NOT NULL) AS rejected
        FROM applications
    ")->fetch_assoc();
} else {
    $statStmt = $conn->prepare("
        SELECT
        SUM(status='Interview') AS scheduled,
        SUM(status='Interview' AND interview_date = ?) AS today_count,
        SUM(status='Approved' AND interview_date IS NOT NULL) AS accepted,
        SUM(status='Rejected' AND interview_date IS NOT NULL) AS rejected
        FROM applications
        WHERE org_id = ?
    ");
    $statStmt->bind_param("si", $today, $scopedOrgId);
    $statStmt->execute();
    $stats = $statStmt->get_result()->fetch_assoc();
}

// ================= SCHEDULED INTERVIEWS =================
if ($isSuperAdmin) {
    $scheduled = $conn->query("
        SELECT a.*, s.full_name, s.email, o.org_name
        FROM applications a
        LEFT JOIN students s ON a.student_id = s.student_id
        LEFT JOIN organizations o ON a.org_id = o.org_id
        WHERE a.status = 'Interview'
        ORDER BY a.interview_date ASC, a.interview_time ASC
    ");
} else {
    $schedStmt = $conn->prepare("
        SELECT a.*, s.full_name, s.email, o.org_name
        FROM applications a
        LEFT JOIN students s ON a.student_id = s.student_id
        LEFT JOIN organizations o ON a.org_id = o.org_id
        WHERE a.status = 'Interview' AND a.org_id = ?
        ORDER BY a.interview_date ASC, a.interview_time ASC
    ");
    $schedStmt->bind_param("i", $scopedOrgId);
    $schedStmt->execute();
    $scheduled = $schedStmt->get_result();
}

$todayList = [];
$upcomingList = [];
$pastList = [];

while ($row = $scheduled->fetch_assoc()) {
    if ($row['interview_date'] === $today) {
        $todayList[] = $row;
    } elseif ($row['interview_date'] > $today) {
        $upcomingList[] = $row;
    } else {
        $pastList[] = $row;
    }
}

// ================= RECENT DECISIONS (HISTORY) =================
if ($isSuperAdmin) {
    $history = $conn->query("
        SELECT a.*, s.full_name, o.org_name
        FROM applications a
        LEFT JOIN students s ON a.student_id = s.student_id
        LEFT JOIN organizations o ON a.org_id = o.org_id
        WHERE a.status IN ('Approved','Rejected') AND a.interview_date IS NOT NULL
        ORDER BY a.interview_date DESC, a.interview_time DESC
        LIMIT 8
    ");
} else {
    $histStmt = $conn->prepare("
        SELECT a.*, s.full_name, o.org_name
        FROM applications a
        LEFT JOIN students s ON a.student_id = s.student_id
        LEFT JOIN organizations o ON a.org_id = o.org_id
        WHERE a.status IN ('Approved','Rejected') AND a.interview_date IS NOT NULL AND a.org_id = ?
        ORDER BY a.interview_date DESC, a.interview_time DESC
        LIMIT 8
    ");
    $histStmt->bind_param("i", $scopedOrgId);
    $histStmt->execute();
    $history = $histStmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Interviews</title>
    <link rel="stylesheet" href="../css/admin/interviews.css">
</head>

<body>
<div class="sidebar">
    <h2>Admin</h2>
    <span class="role-label"><?= $isSuperAdmin ? 'Main Admin' : 'Organization Admin' ?></span>

    <div class="theme-toggle" id="themeToggle">
        <span><span id="themeIcon"></span> <span id="themeLabel">Dark Mode</span></span>
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

<h2>Interviews</h2>
<p style="color:var(--text-secondary);">Track scheduled interviews and record the final decision.</p>

<div class="cards">
    <div class="card"><span>Scheduled</span><span class="num"><?= (int)($stats['scheduled'] ?? 0) ?></span></div>
    <div class="card today"><span>Today</span><span class="num"><?= (int)($stats['today_count'] ?? 0) ?></span></div>
    <div class="card accepted"><span>Accepted</span><span class="num"><?= (int)($stats['accepted'] ?? 0) ?></span></div>
    <div class="card rejected"><span>Rejected</span><span class="num"><?= (int)($stats['rejected'] ?? 0) ?></span></div>
</div>

<?php
function renderInterviewTable($list, $showDecisionButtons = true) {
    if (empty($list)) {
        echo '<div class="empty-state">Nothing here.</div>';
        return;
    }
    ?>
    <table>
    <tr>
        <th>Student</th>
        <th>Org</th>
        <th>Date</th>
        <th>Time</th>
        <th>Location / Link</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($list as $a): ?>
    <tr>
        <td><?= htmlspecialchars(!empty($a['fullname']) ? $a['fullname'] : $a['full_name']) ?></td>
        <td><?= htmlspecialchars($a['org_name']) ?></td>
        <td><?= $a['interview_date'] ?></td>
        <td><?= substr($a['interview_time'], 0, 5) ?></td>
        <td>
            <?php if (!empty($a['interview_link'])): ?>
                <a href="<?= htmlspecialchars($a['interview_link']) ?>" target="_blank" style="color:#3b82f6;">Link ↗</a>
            <?php elseif (!empty($a['interview_location'])): ?>
                <?= htmlspecialchars($a['interview_location']) ?>
            <?php else: ?>
                <span class="no-file">—</span>
            <?php endif; ?>
        </td>
        <td>
        <div class="actions">

            <?php if (!empty($a['attachment'])): ?>
            <button class="view-btn" onclick="openFileModal('../uploads/<?= htmlspecialchars($a['attachment']) ?>')">View File</button>
            <?php endif; ?>

            <?php if ($showDecisionButtons): ?>
            <form method="POST" action="update_status.php">
                <input type="hidden" name="id" value="<?= $a['application_id'] ?>">
                <input type="hidden" name="status" value="Approved">
                <button class="approve">Accept</button>
            </form>
            <form method="POST" action="update_status.php">
                <input type="hidden" name="id" value="<?= $a['application_id'] ?>">
                <input type="hidden" name="status" value="Rejected">
                <button class="reject">Reject</button>
            </form>
            <?php endif; ?>

            <button class="reschedule" onclick="openInterviewModal(<?= $a['application_id'] ?>, '<?= $a['interview_date'] ?>', '<?= substr($a['interview_time'],0,5) ?>', '<?= htmlspecialchars(addslashes($a['interview_location'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($a['interview_link'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($a['interview_notes'] ?? '')) ?>')">Reschedule</button>

        </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
    <?php
}
?>

<div class="section">
    <div class="section-heading">
        <h3>Today</h3>
        <span class="section-badge <?= count($todayList) > 0 ? 'urgent' : '' ?>"><?= count($todayList) ?> interview<?= count($todayList) === 1 ? '' : 's' ?></span>
    </div>
    <?php renderInterviewTable($todayList); ?>
</div>

<div class="section">
    <div class="section-heading">
        <h3>Upcoming</h3>
        <span class="section-badge"><?= count($upcomingList) ?> scheduled</span>
    </div>
    <?php renderInterviewTable($upcomingList); ?>
</div>

<div class="section">
    <div class="section-heading">
        <h3>Past — Awaiting Decision</h3>
        <span class="section-badge <?= count($pastList) > 0 ? 'urgent' : '' ?>"><?= count($pastList) ?> pending</span>
    </div>
    <?php renderInterviewTable($pastList); ?>
</div>

<div class="section">
    <div class="section-heading">
        <h3>Recent Decisions</h3>
    </div>
    <div class="history-list">
    <?php if ($history->num_rows === 0): ?>
        <div class="empty-state">No decisions recorded yet.</div>
    <?php else: while ($h = $history->fetch_assoc()): ?>
        <div class="history-item">
            <div class="who">
                <strong><?= htmlspecialchars(!empty($h['fullname']) ? $h['fullname'] : $h['full_name']) ?></strong>
                <small><?= htmlspecialchars($h['org_name']) ?> · interviewed <?= $h['interview_date'] ?></small>
            </div>
            <span class="outcome <?= strtolower($h['status']) ?>"><?= $h['status'] === 'Approved' ? 'Accepted' : 'Rejected' ?></span>
        </div>
    <?php endwhile; endif; ?>
    </div>
</div>

</div>

<!-- FILE PREVIEW MODAL -->
<div id="fileModal" class="modal">
<div class="modal-content">
    <div class="modal-header">
        <h3>Submitted File</h3>
        <button type="button" onclick="closeFileModal()" class="reject">Close</button>
    </div>
    <iframe id="filePreviewFrame" src="" style="display:none;"></iframe>
    <div id="filePreviewUnsupported" style="display:none; padding:40px 20px; text-align:center; color:var(--text-secondary); background:var(--input-bg); border-radius:10px;">
        This file type can't be previewed in-browser.<br>
        Use "Open in new tab" or "Download" below instead.
    </div>
    <p style="margin-top:10px; display:flex; gap:15px; align-items:center;">
        <a id="fileOpenNewTab" href="#" target="_blank" style="color:#3b82f6;">Open in new tab ↗</a>
        <a id="fileDownloadLink" href="#" download style="color:#22c55e; font-weight:bold;">⬇ Download</a>
    </p>
</div>
</div>

<!-- SET / RESCHEDULE INTERVIEW MODAL -->
<div id="interviewModal" class="modal">
<div class="modal-content">
<h3 id="interviewModalTitle">Reschedule Interview</h3>
<form method="POST" action="set_interview.php">
<input type="hidden" name="application_id" id="app_id">
<input type="date" name="interview_date" id="int_date" required>
<input type="time" name="interview_time" id="int_time" required>
<input type="text" name="interview_location" id="int_location" placeholder="Location (e.g. Room 301)">
<input type="text" name="interview_link" id="int_link" placeholder="Meeting link (optional)">
<textarea name="interview_notes" id="int_notes" placeholder="Notes for the applicant (optional)"></textarea>
<button class="btn-interview">Save</button>
<button type="button" onclick="closeInterviewModal()" class="reject">Cancel</button>
</form>
</div>
</div>

<script>
function openFileModal(fileUrl){
    const frame = document.getElementById("filePreviewFrame");
    const unsupported = document.getElementById("filePreviewUnsupported");

    // Only load the iframe for types browsers can actually render inline.
    // Anything else (docx, pptx, etc) would just trigger a silent download
    // instead of a preview, so we show a message instead.
    const previewable = /\.(pdf|png|jpe?g|gif|webp)$/i;

    if (previewable.test(fileUrl)) {
        frame.src = fileUrl;
        frame.style.display = "block";
        unsupported.style.display = "none";
    } else {
        frame.src = "";
        frame.style.display = "none";
        unsupported.style.display = "block";
    }

    document.getElementById("fileOpenNewTab").href = fileUrl;
    document.getElementById("fileDownloadLink").href = fileUrl;
    document.getElementById("fileModal").style.display = "block";
}
function closeFileModal(){
    document.getElementById("fileModal").style.display = "none";
    document.getElementById("filePreviewFrame").src = "";
    document.getElementById("filePreviewUnsupported").style.display = "none";
}

function openInterviewModal(id, date, time, location, link, notes){
    document.getElementById("interviewModal").style.display = "block";
    document.getElementById("app_id").value = id;
    document.getElementById("int_date").value = date || "";
    document.getElementById("int_time").value = time || "";
    document.getElementById("int_location").value = location || "";
    document.getElementById("int_link").value = link || "";
    document.getElementById("int_notes").value = notes || "";
}
function closeInterviewModal(){
    document.getElementById("interviewModal").style.display = "none";
}

// ============ THEME TOGGLE ============
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

const savedTheme = localStorage.getItem("cc_admin_theme") || "dark";
applyTheme(savedTheme);

themeToggle.addEventListener("click", () => {
    const isLight = root.classList.contains("light");
    const newTheme = isLight ? "dark" : "light";
    applyTheme(newTheme);
    localStorage.setItem("cc_admin_theme", newTheme);
});
</script>

</body>
</html>