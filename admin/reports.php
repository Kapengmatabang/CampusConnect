<?php
require 'auth.php'; // sets $conn, $scopedOrgId, $isSuperAdmin; redirects if not logged in as admin

// Reports is a system-wide analytics view — Main Admin only.
redirectIfNotSuperAdmin($isSuperAdmin, 'org_dashboard.php');

// ================= OVERVIEW STATS =================
$totalStudents = $conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'];
$totalOrgs     = $conn->query("SELECT COUNT(*) c FROM organizations")->fetch_assoc()['c'];
$totalPosts    = $conn->query("SELECT COUNT(*) c FROM posts")->fetch_assoc()['c'];

$appStats = $conn->query("
    SELECT
    COUNT(*) total,
    SUM(status='Pending') pending,
    SUM(status='Approved') approved,
    SUM(status='Rejected') rejected,
    SUM(status='Interview') interview
    FROM applications
")->fetch_assoc();

// ================= APPLICATIONS BY ORGANIZATION =================
$byOrg = $conn->query("
    SELECT o.org_name,
    COUNT(a.application_id) total,
    SUM(a.status='Approved') approved,
    SUM(a.status='Rejected') rejected,
    SUM(a.status='Pending') pending,
    SUM(a.status='Interview') interview
    FROM organizations o
    LEFT JOIN applications a ON a.org_id = o.org_id
    GROUP BY o.org_id, o.org_name
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 10
");
$orgRows = [];
$maxOrgTotal = 1;
while ($r = $byOrg->fetch_assoc()) {
    $orgRows[] = $r;
    if ($r['total'] > $maxOrgTotal) $maxOrgTotal = $r['total'];
}

// ================= APPLICATIONS BY COURSE =================
$byCourse = $conn->query("
    SELECT COALESCE(NULLIF(a.course,''), s.course, 'Unspecified') AS course, COUNT(*) total
    FROM applications a
    LEFT JOIN students s ON a.student_id = s.student_id
    GROUP BY course
    ORDER BY total DESC
");
$courseRows = [];
$maxCourseTotal = 1;
while ($r = $byCourse->fetch_assoc()) {
    $courseRows[] = $r;
    if ($r['total'] > $maxCourseTotal) $maxCourseTotal = $r['total'];
}

// ================= MONTHLY APPLICATION TREND (last 6 months) =================
$trend = $conn->query("
    SELECT DATE_FORMAT(applied_at, '%Y-%m') ym, COUNT(*) c
    FROM applications
    WHERE applied_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym
    ORDER BY ym ASC
");
$trendRows = [];
$maxTrend = 1;
while ($r = $trend->fetch_assoc()) {
    $trendRows[] = $r;
    if ($r['c'] > $maxTrend) $maxTrend = $r['c'];
}

// ================= INTERVIEW OUTCOMES =================
$outcomes = $conn->query("
    SELECT
    SUM(status='Approved' AND interview_date IS NOT NULL) accepted,
    SUM(status='Rejected' AND interview_date IS NOT NULL) rejected_after_interview,
    SUM(status='Rejected' AND interview_date IS NULL) rejected_before_interview
    FROM applications
")->fetch_assoc();
?>

<!DOCTYPE html>
<html>

<head>
    <title>Reports</title>

    <link rel="stylesheet" href="../css/admin/reports.css">
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

    <a href="dashboard.php">Dashboard</a>
    <a href="dashboard.php">Create Post</a>
    <a href="applications.php">Applications</a>
    <a href="interviews.php">Interviews</a>
    <a href="records.php">Records</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>
    <a href="../logout.php">Logout</a>

</div>


<div class="main">

    <h2>Reports</h2>

    <p class="page-description">
        A snapshot of applications, organizations, and activity across CampusConnect.
    </p>


    <!-- OVERVIEW STATS -->

    <div class="cards">

        <div class="card">
            <span>Total Applications</span>
            <span class="num">
                <?= (int)($appStats['total'] ?? 0) ?>
            </span>
        </div>

        <div class="card pending">
            <span>Pending</span>
            <span class="num">
                <?= (int)($appStats['pending'] ?? 0) ?>
            </span>
        </div>

        <div class="card interview">
            <span>In Interview</span>
            <span class="num">
                <?= (int)($appStats['interview'] ?? 0) ?>
            </span>
        </div>

        <div class="card approved">
            <span>Accepted Members</span>
            <span class="num">
                <?= (int)($appStats['approved'] ?? 0) ?>
            </span>
        </div>

    </div>


    <div class="cards secondary-cards">

        <div class="card">
            <span>Registered Students</span>
            <span class="num">
                <?= (int)$totalStudents ?>
            </span>
        </div>

        <div class="card">
            <span>Organizations</span>
            <span class="num">
                <?= (int)$totalOrgs ?>
            </span>
        </div>

        <div class="card">
            <span>Posts Published</span>
            <span class="num">
                <?= (int)$totalPosts ?>
            </span>
        </div>

    </div>


    <!-- INTERVIEW OUTCOMES -->

    <div class="section">

        <div class="section-heading">
            <h3>Interview Outcomes</h3>
        </div>

        <div class="panel">

            <div class="outcome-cards">

                <div class="outcome-card">

                    <span class="num accepted">
                        <?= (int)($outcomes['accepted'] ?? 0) ?>
                    </span>

                    <span class="label">
                        Accepted after interview
                    </span>

                </div>


                <div class="outcome-card">

                    <span class="num rejected">
                        <?= (int)($outcomes['rejected_after_interview'] ?? 0) ?>
                    </span>

                    <span class="label">
                        Rejected after interview
                    </span>

                </div>


                <div class="outcome-card">

                    <span class="num rejected-before">
                        <?= (int)($outcomes['rejected_before_interview'] ?? 0) ?>
                    </span>

                    <span class="label">
                        Rejected before interview
                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- TWO COLUMN: BY ORG + BY COURSE -->

    <div class="section grid-2">


        <!-- TOP ORGANIZATIONS -->

        <div>

            <div class="section-heading">
                <h3>Top Organizations by Applicants</h3>
            </div>

            <div class="panel">

                <?php if (empty($orgRows)): ?>

                    <div class="empty-state">
                        No applications yet.
                    </div>

                <?php else: ?>

                    <?php foreach ($orgRows as $r): ?>

                        <div class="bar-row">

                            <div class="bar-label">

                                <span>
                                    <?= htmlspecialchars($r['org_name']) ?>
                                </span>

                                <span class="count">
                                    <?= $r['total'] ?>
                                </span>

                            </div>


                            <div class="bar-track">

                                <div
                                    class="bar-fill"
                                    style="width:<?= round(($r['total'] / $maxOrgTotal) * 100) ?>%;">
                                </div>

                            </div>


                            <div class="chip-row">

                                <?php if ($r['approved'] > 0): ?>

                                    <span class="chip approved">
                                        <?= $r['approved'] ?> approved
                                    </span>

                                <?php endif; ?>


                                <?php if ($r['interview'] > 0): ?>

                                    <span class="chip interview">
                                        <?= $r['interview'] ?> interview
                                    </span>

                                <?php endif; ?>


                                <?php if ($r['pending'] > 0): ?>

                                    <span class="chip pending">
                                        <?= $r['pending'] ?> pending
                                    </span>

                                <?php endif; ?>


                                <?php if ($r['rejected'] > 0): ?>

                                    <span class="chip rejected">
                                        <?= $r['rejected'] ?> rejected
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>


        <!-- APPLICATIONS BY COURSE -->

        <div>

            <div class="section-heading">
                <h3>Applications by Course</h3>
            </div>

            <div class="panel">

                <?php if (empty($courseRows)): ?>

                    <div class="empty-state">
                        No applications yet.
                    </div>

                <?php else: ?>

                    <?php foreach ($courseRows as $r): ?>

                        <div class="bar-row">

                            <div class="bar-label">

                                <span>
                                    <?= htmlspecialchars($r['course']) ?>
                                </span>

                                <span class="count">
                                    <?= $r['total'] ?>
                                </span>

                            </div>


                            <div class="bar-track">

                                <div
                                    class="bar-fill course"
                                    style="width:<?= round(($r['total'] / $maxCourseTotal) * 100) ?>%;">
                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- MONTHLY TREND -->

    <div class="section">

        <div class="section-heading">

            <h3>Applications Over Time</h3>

            <span class="section-sub">
                Last 6 months
            </span>

        </div>


        <div class="panel">

            <?php if (empty($trendRows)): ?>

                <div class="empty-state">
                    Not enough data yet.
                </div>

            <?php else: ?>

                <?php foreach ($trendRows as $r): ?>

                    <div class="bar-row">

                        <div class="bar-label">

                            <span>
                                <?= date('F Y', strtotime($r['ym'] . '-01')) ?>
                            </span>

                            <span class="count">
                                <?= $r['c'] ?>
                            </span>

                        </div>


                        <div class="bar-track">

                            <div
                                class="bar-fill trend"
                                style="width:<?= round(($r['c'] / $maxTrend) * 100) ?>%;">
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>


<script>

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