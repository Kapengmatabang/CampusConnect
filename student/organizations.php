<?php

session_start();
if (!isset($_SESSION["user"]) || $_SESSION["usertype"] !== "student") {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION["user"];
$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

// GET STUDENT COURSE
$stmt = $conn->prepare(
    "SELECT course FROM students WHERE student_id=?"
);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// NORMALIZE COURSE
$student_course = strtoupper(trim($user['course']));
$courseAliases = [
    'BSPSYCH' => 'BSPSY',
];

$lookupCourse =
    $courseAliases[$student_course] ?? $student_course;

// MAP COURSE → GROUP
$group = 'GENERAL';
$gStmt = $conn->prepare(
    "SELECT course_group FROM course_groups WHERE course_code = ?"
);
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
    $cg = trim($org['course_group']);
    $cat = trim(
        $org['category']
        ?? 'Student Club and Organizations'
    );

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

function orderedCategoryKeys(
    $byCategory,
    $preferredOrder
) {
    $known = array_values(
        array_intersect(
            $preferredOrder,
            array_keys($byCategory)
        )
    );
    $rest = array_diff(
        array_keys($byCategory),
        $known
    );
    sort($rest);
    return array_merge(
        $known,
        $rest
    );
}

$generalCategories = orderedCategoryKeys(
    $generalByCategory,
    $generalOrder
);

$courseCategories = orderedCategoryKeys(
    $courseByCategory,
    $courseOrder
);

$mediaCategories = orderedCategoryKeys(
    $mediaByCategory,
    $mediaOrder
);

// GET APPLICATIONS
$applications = [];
$q = $conn->prepare(
    "SELECT org_id, status
     FROM applications
     WHERE student_id=?"
);
$q->bind_param("s", $student_id);
$q->execute();
$r = $q->get_result();
while ($row = $r->fetch_assoc()) {
    $applications[$row['org_id']] =
        $row['status'];
}

// ORGANIZATION IMAGE
function img($o) {
    return !empty($o['image'])
        ? "../" . $o['image']
        : "../img/default.png";
}

// APPLICATION STATUS
function statusPill($status) {
    switch ($status) {
        case 'Interview':
            return [
                'label' => '📅 Interview Scheduled',
                'class' => 'status-interview'
            ];

        case 'Approved':
            return [
                'label' => '✔ Approved',
                'class' => 'status-approved'
            ];

        case 'Rejected':
            return [
                'label' => 'Not Selected',
                'class' => 'status-rejected'
            ];

        case 'Pending':
        default:
            return [
                'label' => '⏳ Pending Review',
                'class' => 'status-pending'
            ];
    }
}

// SPLIT POSITIONS / QUALIFICATIONS
function splitLines($text) {
    if (empty($text)) {
        return [];
    }
    $lines = preg_split(
        '/\r\n|\r|\n/',
        trim($text)
    );
    return array_values(
        array_filter(
            array_map('trim', $lines)
        )
    );
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Explore Organizations</title>

    <!-- EXTERNAL STUDENT ORGANIZATIONS CSS -->
    <link
        rel="stylesheet"
        href="../css/student/organizations.css"
    >
</head>
<body>

<!-- HEADER -->
<div class="header">
    <a
        href="dashboard.php"
        class="back-btn"
    >
        ⬅ Back
    </a>
    <h2>
        Explore Organizations
    </h2>
    <p>
        <?= htmlspecialchars($student_course) ?>
    </p>
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
function renderCards(
    $list,
    $applications
) {
?>

<div class="grid">
<?php if (empty($list)): ?>
    <div class="empty-note">
        No organizations here yet.
    </div>

<?php else: ?>
<?php foreach ($list as $o):
    $status =
        $applications[$o['org_id']]
        ?? null;

    // POSITIONS
    $positions = splitLines(
        $o['positions'] ?? ''
    );

    // QUALIFICATIONS
    $qualifications = splitLines(
        $o['qualifications'] ?? ''
    );

    // FACEBOOK
    $facebookLink =
        trim(
            $o['facebook_link']
            ?? ''
        );
?>

<div
    class="card org-card"
    data-name="<?= htmlspecialchars(
        strtolower($o['org_name'])
    ) ?>"
>
    <!-- IMAGE -->
    <div class="img-box">
        <img
            src="<?= img($o) ?>"
            alt="<?= htmlspecialchars(
                $o['org_name']
            ) ?>"
        >
    </div>

    <!-- ORGANIZATION NAME -->
    <h4>
        <?= htmlspecialchars(
            $o['org_name']
        ) ?>
    </h4>

    <!-- DESCRIPTION -->
    <p class="desc">
        <?= htmlspecialchars(
            mb_strimwidth(
                $o['description'] ?? '',
                0,
                90,
                '...'
            )
        ) ?>
    </p>

    <!-- FACEBOOK -->
    <?php if (!empty($facebookLink)): ?>
        <a
            class="fb-link"
            href="<?= htmlspecialchars(
                $facebookLink
            ) ?>"
            target="_blank"
            rel="noopener"
            onclick="event.stopPropagation()"
        >
            Facebook Page
        </a>
    <?php endif; ?>

    <!-- BUTTONS -->
    <div class="btn-group">
        <?php
        if ($status):
            $pill =
                statusPill($status);
        ?>
            <div
                class="status-pill
                <?= $pill['class'] ?>"
            >
                <?= $pill['label'] ?>
            </div>

        <?php else: ?>
            <form
                method="GET"
                action="application_form.php"
            >
                <input
                    type="hidden"
                    name="org_id"
                    value="<?= $o['org_id'] ?>"
                >
                <button
                    class="btn apply"
                    type="submit"
                >
                    Apply
                </button>
            </form>
        <?php endif; ?>

        <!-- READ MORE -->
        <button
            class="btn view"
            data-title="<?= htmlspecialchars(
                $o['org_name']
            ) ?>"
            data-desc="<?= htmlspecialchars(
                $o['description'] ?? ''
            ) ?>"
            data-img="<?= htmlspecialchars(
                img($o)
            ) ?>"
            data-facebook="<?= htmlspecialchars(
                $facebookLink
            ) ?>"
            data-positions='<?= htmlspecialchars(
                json_encode($positions),
                ENT_QUOTES
            ) ?>'
            data-qualifications='<?= htmlspecialchars(
                json_encode($qualifications),
                ENT_QUOTES
            ) ?>'
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
function renderSection(
    $sectionTitle,
    $byCategory,
    $categoryKeys,
    $applications
) {
?>
<div class="section-title">
    <?= htmlspecialchars(
        $sectionTitle
    ) ?>
</div>

<?php foreach ($categoryKeys as $cat):
    $isMajor =
        (stripos($cat, 'major') !== false);
?>
<div class="subsection-title">
    <?= htmlspecialchars($cat) ?>
    <?php if ($isMajor): ?>
        <span class="badge">
            Major
        </span>
    <?php endif; ?>
</div>

<?php
renderCards(
    $byCategory[$cat],
    $applications
);
?>
<?php endforeach; ?>
<?php
}

// RENDER SECTIONS
renderSection(
    'General Organizations',
    $generalByCategory,
    $generalCategories,
    $applications
);
renderSection(
    'Your Course Organizations',
    $courseByCategory,
    $courseCategories,
    $applications
);
renderSection(
    'Media Organizations',
    $mediaByCategory,
    $mediaCategories,
    $applications
);
?>

<!-- MODAL -->
<div
    id="modal"
    class="modal"
>
    <div class="modal-content">
        <img
            id="mImg"
            alt=""
        >
        <h3 id="mTitle"></h3>
        <p
            class="modal-desc"
            id="mDesc"
        ></p>

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

        <!-- POSITIONS -->
        <div
            class="modal-section"
            id="mPositionsSection"
        >
            <h4>
                Open Positions
            </h4>
            <div
                class="position-tags"
                id="mPositions"
            ></div>
        </div>

        <!-- QUALIFICATIONS -->
        <div
            class="modal-section"
            id="mQualificationsSection"
        >
            <h4>
                Qualifications
            </h4>
            <ul
                class="qual-list"
                id="mQualifications"
            ></ul>
        </div>

        <!-- CLOSE -->
        <button
            class="btn view"
            onclick="closeModal()"
        >
            Close
        </button>
    </div>
</div>
<script>

/* =========================
   OPEN MODAL
========================= */
function openModal(btn) {
    document.getElementById(
        "modal"
    ).style.display = "block";

    document.getElementById(
        "mTitle"
    ).innerText =
        btn.dataset.title;

    document.getElementById(
        "mDesc"
    ).innerText =
        btn.dataset.desc;

    document.getElementById(
        "mImg"
    ).src =
        btn.dataset.img;

    /* =========================
       FACEBOOK LINK
    ========================= */
    const fbLink =
        document.getElementById(
            "mFacebook"
        );

    if (btn.dataset.facebook) {
        fbLink.href =
            btn.dataset.facebook;
        fbLink.style.display =
            "inline-block";
    } else {
        fbLink.style.display =
            "none";
    }

    /* =========================
       POSITIONS
    ========================= */
    const positions =
        JSON.parse(
            btn.dataset.positions || "[]"
        );

    const positionsWrap =
        document.getElementById(
            "mPositions"
        );

    const positionsSection =
        document.getElementById(
            "mPositionsSection"
        );
    positionsWrap.innerHTML = "";
    if (positions.length > 0) {
        positions.forEach(p => {
            const tag =
                document.createElement(
                    "span"
                );
            tag.className =
                "position-tag";
            tag.innerText = p;
            positionsWrap.appendChild(
                tag
            );
        });
        positionsSection.style.display =
            "block";
    } else {
        positionsSection.style.display =
            "none";
    }

    /* =========================
       QUALIFICATIONS
    ========================= */
    const qualifications =
        JSON.parse(
            btn.dataset.qualifications || "[]"
        );
    const qualWrap =
        document.getElementById(
            "mQualifications"
        );
    const qualSection =
        document.getElementById(
            "mQualificationsSection"
        );
    qualWrap.innerHTML = "";
    if (qualifications.length > 0) {
        qualifications.forEach(q => {
            const li =
                document.createElement(
                    "li"
                );
            li.innerText = q;
            qualWrap.appendChild(li);
        });

        qualSection.style.display =
            "block";
    } else {
        qualSection.style.display =
            "none";
    }
}

/* =========================
   CLOSE MODAL
========================= */
function closeModal() {
    document.getElementById(
        "modal"
    ).style.display = "none";
}

/* =========================
   BACKDROP CLICK
========================= */
document
    .getElementById("modal")
    .addEventListener(
        "click",
        function(e) {
            if (e.target === this) {
                closeModal();
            }
        }
    );

/* =========================
   ESCAPE KEY
========================= */
document.addEventListener(
    "keydown",
    function(e) {
        if (e.key === "Escape") {
            closeModal();
        }
    }
);

/* =========================
   LIVE SEARCH
========================= */
function filterOrgs() {
    const q =
        document
            .getElementById("orgSearch")
            .value
            .trim()
            .toLowerCase();
    document
        .querySelectorAll(".org-card")
        .forEach(card => {
            card.style.display =
                card.dataset.name.includes(q)
                    ? ""
                    : "none";
        });
}

/* =========================
   APPLY SAVED THEME
========================= */
const savedTheme =
    localStorage.getItem("cc_theme")
    || "dark";
if (savedTheme === "light") {
    document.documentElement
        .classList.add("light");
}
</script>
</body>
</html>