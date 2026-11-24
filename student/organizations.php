<?php

session_start();
if (!isset($_SESSION["user"]) || $_SESSION["usertype"] !== "student") {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION["user"];
require __DIR__ . '/../db.php';
if ($conn->connect_error) die("DB Error");
$conn->set_charset("utf8mb4");

// GET STUDENT COURSE
$stmt = $conn->prepare("SELECT course FROM students WHERE student_id=?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// NORMALIZE COURSE
$student_course = strtoupper(trim($user['course'] ?? ''));
$courseAliases = [
    'BSPSYCH' => 'BSPSY',
];
$lookupCourse = $courseAliases[$student_course] ?? $student_course;

// MAP COURSE → GROUP
$group = 'GENERAL';
$gStmt = $conn->prepare("SELECT course_group FROM course_groups WHERE course_code = ?");
$gStmt->bind_param("s", $lookupCourse);
$gStmt->execute();
$gRow = $gStmt->get_result()->fetch_assoc();
if ($gRow) {
    $group = $gRow['course_group'];
}

// FETCH ORGANIZATIONS
$stmt = $conn->prepare("
    SELECT * FROM organizations
    WHERE course_group = ?
    OR course_group = 'GENERAL'
    OR course_group = 'MEDIA'
");
$stmt->bind_param("s", $group);
$stmt->execute();
$res = $stmt->get_result();

// ORGANIZATION ARRAYS
$generalByCategory = [];
$mediaByCategory = [];
$courseByCategory = [];

while ($org = $res->fetch_assoc()) {
    $cg  = trim($org['course_group']);
    $cat = trim($org['category'] ?? 'Student Club and Organizations');

    if ($cg === 'GENERAL') {
        $generalByCategory[$cat][] = $org;
    } elseif ($cg === 'MEDIA') {
        $mediaByCategory[$cat][] = $org;
    } else {
        $courseByCategory[$cat][] = $org;
    }
}

// CATEGORY DISPLAY ORDER
$courseOrder = [
    'College Student Council (Major Organization)',
    'Department Student Council (Minor)'
];

$generalOrder = [
    'University Student Council',
    'Student Club and Organizations',
    'Institutional Student Organization',
    'Faith-Based Organizations',
    'Center for Culture and Arts'
];

$mediaOrder = [
    'Publication Organizations'
];

function orderedCategoryKeys($byCategory, $preferredOrder) {
    $known = array_values(array_intersect($preferredOrder, array_keys($byCategory)));
    $rest  = array_diff(array_keys($byCategory), $known);
    sort($rest);
    return array_merge($known, $rest);
}

$generalCategories = orderedCategoryKeys($generalByCategory, $generalOrder);
$courseCategories  = orderedCategoryKeys($courseByCategory, $courseOrder);
$mediaCategories   = orderedCategoryKeys($mediaByCategory, $mediaOrder);

// GET APPLICATIONS
$applications = [];
$q = $conn->prepare("SELECT org_id, status FROM applications WHERE student_id=?");
$q->bind_param("s", $student_id);
$q->execute();
$r = $q->get_result();
while ($row = $r->fetch_assoc()) {
    $applications[$row['org_id']] = $row['status'];
}

// ORGANIZATION IMAGE
function img($o) {
    return !empty($o['image']) ? "../" . $o['image'] : "../img/default.png";
}

// APPLICATION STATUS
function statusPill($status) {
    switch ($status) {
        case 'Interview':
            return ['label' => '📅 Interview Scheduled', 'class' => 'status-interview'];
        case 'Approved':
            return ['label' => '✔ Approved', 'class' => 'status-approved'];
        case 'Rejected':
            return ['label' => 'Not Selected', 'class' => 'status-rejected'];
        case 'Pending':
        default:
            return ['label' => '⏳ Pending Review', 'class' => 'status-pending'];
    }
}

// SPLIT TEXT INTO A LIST
// - One item per line (Enter key in the admin textarea)
// - Leading bullets/numbers (-, •, *, 1.) are stripped
// - $splitCommas = true also splits on commas (good for short position names)
function splitLines($text, $splitCommas = false) {
    if ($text === null || trim($text) === '') {
        return [];
    }

    $pattern = $splitCommas ? '/\r\n|\r|\n|,/' : '/\r\n|\r|\n/';
    $lines   = preg_split($pattern, trim($text));

    $clean = [];
    foreach ($lines as $line) {
        $line = trim($line);
        $line = preg_replace('/^(?:[-•*]|\d+[.)])\s*/u', '', $line);
        if ($line !== '') {
            $clean[] = $line;
        }
    }
    return array_values($clean);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Organizations</title>

    <!-- EXTERNAL STUDENT ORGANIZATIONS CSS -->
    <link rel="stylesheet" href="../css/student/organizations.css">

    <!-- Modal extras (positions / qualifications).
         Kept here so this single file is all you need to replace.
         Fallback colors are used if your CSS variables are missing. -->
    <style>
        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-section {
            text-align: left;
            margin: 16px 0 4px;
        }

        .modal-section h4 {
            margin: 0 0 8px;
            font-size: 14px;
            color: #22c55e;
        }

        .position-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .position-tag {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.4);
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .qual-list {
            margin: 0;
            padding-left: 20px;
            font-size: 13px;
            line-height: 1.6;
        }

        .qual-list li {
            margin-bottom: 4px;
        }

        .modal-empty {
            color: var(--text-secondary, #94a3b8);
            font-size: 13px;
            font-style: italic;
            list-style: none;
            margin: 0;
        }

        .modal-fb-link {
            display: none;
            margin: 8px 0 0;
            font-size: 13px;
        }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <a href="dashboard.php" class="back-btn">⬅ Back</a>
    <h2>Explore Organizations</h2>
    <p><?= htmlspecialchars($student_course) ?></p>
</div>

<!-- SEARCH -->
<div class="search-wrap">
    <input
        type="text"
        id="orgSearch"
        placeholder="Search organizations..."
        onkeyup="filterOrgs()"
    >
</div>

<?php
function renderCards($list, $applications) {
?>

<div class="grid">
<?php if (empty($list)): ?>
    <div class="empty-note">No organizations here yet.</div>

<?php else: ?>
<?php foreach ($list as $o):
    $status = $applications[$o['org_id']] ?? null;

    // POSITIONS (newline or comma separated) + QUALIFICATIONS (one per line)
    $positions      = splitLines($o['positions'] ?? '', true);
    $qualifications = splitLines($o['qualifications'] ?? '', false);

    // FACEBOOK
    $facebookLink = trim($o['facebook_link'] ?? '');
?>

<div class="card org-card" data-name="<?= htmlspecialchars(strtolower($o['org_name'])) ?>">

    <!-- IMAGE -->
    <div class="img-box">
        <img src="<?= htmlspecialchars(img($o)) ?>" alt="<?= htmlspecialchars($o['org_name']) ?>">
    </div>

    <!-- ORGANIZATION NAME -->
    <h4><?= htmlspecialchars($o['org_name']) ?></h4>

    <!-- DESCRIPTION -->
    <p class="desc">
        <?= htmlspecialchars(mb_strimwidth($o['description'] ?? '', 0, 90, '...')) ?>
    </p>

    <!-- FACEBOOK -->
    <?php if (!empty($facebookLink)): ?>
        <a
            class="fb-link"
            href="<?= htmlspecialchars($facebookLink) ?>"
            target="_blank"
            rel="noopener"
            onclick="event.stopPropagation()"
        >
            Facebook Page
        </a>
    <?php endif; ?>

    <!-- BUTTONS -->
    <div class="btn-group">
        <?php if ($status): $pill = statusPill($status); ?>
            <div class="status-pill <?= $pill['class'] ?>"><?= $pill['label'] ?></div>

        <?php else: ?>
            <form method="GET" action="application_form.php">
                <input type="hidden" name="org_id" value="<?= (int)$o['org_id'] ?>">
                <button class="btn apply" type="submit">Apply</button>
            </form>
        <?php endif; ?>

        <!-- READ MORE -->
        <button
            type="button"
            class="btn view"
            data-title="<?= htmlspecialchars($o['org_name']) ?>"
            data-desc="<?= htmlspecialchars($o['description'] ?? '') ?>"
            data-img="<?= htmlspecialchars(img($o)) ?>"
            data-facebook="<?= htmlspecialchars($facebookLink) ?>"
            data-positions="<?= htmlspecialchars(json_encode($positions, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
            data-qualifications="<?= htmlspecialchars(json_encode($qualifications, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
            onclick="openModal(this)"
        >
            Read More
        </button>
    </div>
</div>

<?php endforeach; ?>
<?php endif; ?>
</div>
<?php
}
?>

<?php
function renderSection($sectionTitle, $byCategory, $categoryKeys, $applications) {
?>
<div class="section-title"><?= htmlspecialchars($sectionTitle) ?></div>

<?php foreach ($categoryKeys as $cat):
    $isMajor = (stripos($cat, 'major') !== false);
?>
<div class="subsection-title">
    <?= htmlspecialchars($cat) ?>
    <?php if ($isMajor): ?>
        <span class="badge">Major</span>
    <?php endif; ?>
</div>

<?php renderCards($byCategory[$cat], $applications); ?>
<?php endforeach; ?>
<?php
}

// RENDER SECTIONS
renderSection('General Organizations',     $generalByCategory, $generalCategories, $applications);
renderSection('Your Course Organizations', $courseByCategory,  $courseCategories,  $applications);
renderSection('Media Organizations',       $mediaByCategory,   $mediaCategories,   $applications);
?>

<!-- MODAL -->
<div id="modal" class="modal">
    <div class="modal-content">
        <img id="mImg" alt="">
        <h3 id="mTitle"></h3>
        <p class="modal-desc" id="mDesc"></p>

        <!-- FACEBOOK -->
        <a
            id="mFacebook"
            class="modal-fb-link"
            href="#"
            target="_blank"
            rel="noopener"
        >
            Visit Facebook Page
        </a>

        <!-- POSITIONS (always visible) -->
        <div class="modal-section" id="mPositionsSection">
            <h4>Open Positions</h4>
            <div class="position-tags" id="mPositions"></div>
        </div>

        <!-- QUALIFICATIONS (always visible) -->
        <div class="modal-section" id="mQualificationsSection">
            <h4>Qualifications</h4>
            <ul class="qual-list" id="mQualifications"></ul>
        </div>

        <!-- CLOSE -->
        <button type="button" class="btn view" onclick="closeModal()">Close</button>
    </div>
</div>

<script>

/* =========================
   HELPERS
========================= */
function parseList(raw) {
    try {
        const arr = JSON.parse(raw || "[]");
        return Array.isArray(arr) ? arr : [];
    } catch (e) {
        return [];
    }
}

/* =========================
   OPEN MODAL
========================= */
function openModal(btn) {
    document.getElementById("mTitle").textContent = btn.dataset.title;
    document.getElementById("mDesc").textContent  = btn.dataset.desc;
    document.getElementById("mImg").src           = btn.dataset.img;

    /* FACEBOOK LINK */
    const fbLink = document.getElementById("mFacebook");
    if (btn.dataset.facebook) {
        fbLink.href = btn.dataset.facebook;
        fbLink.style.display = "inline-block";
    } else {
        fbLink.style.display = "none";
    }

    /* OPEN POSITIONS — section always shown */
    const positions = parseList(btn.dataset.positions);
    const positionsWrap = document.getElementById("mPositions");
    positionsWrap.innerHTML = "";

    if (positions.length > 0) {
        positions.forEach(p => {
            const tag = document.createElement("span");
            tag.className = "position-tag";
            tag.textContent = p;
            positionsWrap.appendChild(tag);
        });
    } else {
        const empty = document.createElement("p");
        empty.className = "modal-empty";
        empty.textContent = "No open positions posted yet.";
        positionsWrap.appendChild(empty);
    }

    /* QUALIFICATIONS — section always shown */
    const qualifications = parseList(btn.dataset.qualifications);
    const qualWrap = document.getElementById("mQualifications");
    qualWrap.innerHTML = "";

    if (qualifications.length > 0) {
        qualifications.forEach(q => {
            const li = document.createElement("li");
            li.textContent = q;
            qualWrap.appendChild(li);
        });
    } else {
        const li = document.createElement("li");
        li.className = "modal-empty";
        li.textContent = "No qualifications posted yet.";
        qualWrap.appendChild(li);
    }

    /* SHOW (scroll back to top of modal, lock page scroll) */
    const modal = document.getElementById("modal");
    modal.style.display = "block";
    modal.querySelector(".modal-content").scrollTop = 0;
    document.body.style.overflow = "hidden";
}

/* =========================
   CLOSE MODAL
========================= */
function closeModal() {
    document.getElementById("modal").style.display = "none";
    document.body.style.overflow = "";
}

/* BACKDROP CLICK */
document.getElementById("modal").addEventListener("click", function (e) {
    if (e.target === this) {
        closeModal();
    }
});

/* ESCAPE KEY */
document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
        closeModal();
    }
});

/* =========================
   LIVE SEARCH
========================= */
function filterOrgs() {
    const q = document.getElementById("orgSearch").value.trim().toLowerCase();
    document.querySelectorAll(".org-card").forEach(card => {
        card.style.display = card.dataset.name.includes(q) ? "" : "none";
    });
}

/* =========================
   APPLY SAVED THEME
========================= */
const savedTheme = localStorage.getItem("cc_theme") || "dark";
if (savedTheme === "light") {
    document.documentElement.classList.add("light");
}
</script>
</body>
</html>