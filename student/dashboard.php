<?php

session_start();

// PROTECT PAGE
if (!isset($_SESSION["user"]) || $_SESSION["usertype"] !== "student") {

    header("Location: ../login.php");

    exit();
}
$student_id = $_SESSION["user"];
require __DIR__ . '/../db.php';
if ($conn->connect_error) {
    die("DB Error");
}

// GET USER INFO
// (student_id is stored as a string, so bind as "s" to keep leading zeros)
$stmt = $conn->prepare(
    "SELECT * FROM students WHERE student_id=?"
);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- REQUIRED for mobile: without this, phones render the page at desktop width and shrink it -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard</title>
    <link
        rel="stylesheet"
        href="../css/student/dashboard.css"
    >

    <!-- MOBILE LAYOUT (loaded after dashboard.css so it wins) -->
    <style>
    @media (max-width: 768px) {

        html, body {
            min-height: 100vh;
            overflow-x: hidden;
        }

        body {
            display: block !important;
        }

        /* SIDEBAR -> TOP BAR */
        .sidebar {
            position: static !important;
            width: 100% !important;
            height: auto !important;
            min-height: 0 !important;
            box-sizing: border-box;
            padding: 16px !important;
            display: flex !important;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .sidebar h2 {
            flex: 1 1 auto;
            margin: 0 !important;
            font-size: 22px !important;
        }

        .theme-toggle {
            flex: 0 0 auto;
            order: 2;
            font-size: 14px !important;
            padding: 10px 14px !important;
            margin: 0 !important;
        }

        #themeSwitchHint {
            display: none;
        }

        .sidebar a {
            flex: 1 1 calc(33.333% - 10px);
            text-align: center;
            font-size: 15px !important;
            padding: 12px 8px !important;
            margin: 0 !important;
            border-radius: 10px;
            box-sizing: border-box;
            order: 3;
        }

        /* MAIN CONTENT */
        .main {
            margin-left: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            padding: 16px !important;
            box-sizing: border-box;
        }

        .card {
            padding: 18px !important;
            border-radius: 16px !important;
        }

        .profile h2 {
            font-size: 22px !important;
        }

        .profile p {
            font-size: 15px !important;
            word-break: break-word;
        }

        #apps h3 {
            font-size: 20px !important;
        }

        /* APPLICATIONS TABLE -> STACKED CARDS */
        #apps table,
        #apps tbody {
            display: block;
            width: 100%;
        }

        #apps thead {
            display: none;
        }

        #apps tr {
            display: block;
            margin-bottom: 14px;
            padding: 12px 14px;
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 12px;
            background: rgba(148, 163, 184, 0.08);
        }

        #apps td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 8px 0 !important;
            border: none !important;
            font-size: 15px !important;
            text-align: right !important;
        }

        #apps td::before {
            content: attr(data-label);
            font-weight: 600;
            font-size: 13px;
            opacity: 0.7;
            text-align: left;
            flex: 0 0 auto;
        }

        /* Loading / empty message rows */
        #apps td[colspan] {
            display: block;
            text-align: center !important;
        }

        #apps td[colspan]::before {
            content: none;
        }

        #apps td button,
        #apps td a {
            font-size: 15px !important;
            padding: 10px 16px !important;
        }
    }
    </style>
</head>

<body>
<!-- SIDEBAR -->
<div class="sidebar">
    <h2>Student</h2>
    <div
        class="theme-toggle"
        id="themeToggle"
    >
        <span>
            <span
                class="icon"
                id="themeIcon"
            >
            </span>
            <span id="themeLabel">
                Dark Mode
            </span>
        </span>
        <span id="themeSwitchHint">
            Tap to switch
        </span>
    </div>
    <a href="student_checkin.php">
        Wellness
    </a>
    <a href="organizations.php">
        Explore Orgs
    </a>
    <a href="../logout.php">
        Logout
    </a>
</div>

<!-- MAIN -->
<div class="main">

    <!-- PROFILE -->
    <div class="card profile">
        <h2>
            <?= htmlspecialchars($user['full_name']); ?>
        </h2>
        <p>
            <?= htmlspecialchars(
                $user['course'] . " - " . $user['section']
            ); ?>
        </p>
        <p>
            <?= htmlspecialchars($user['email']); ?>
        </p>
    </div>

    <!-- APPLICATIONS -->
    <div
        class="card"
        id="apps"
    >
        <h3>
            My Applications
        </h3>
        <table>
            <thead>
                <tr>
                    <th>
                        Organization
                    </th>
                    <th>
                        Status
                    </th>
                    <th>
                        Interview
                    </th>
                    <th>
                        Date Applied
                    </th>
                    <th>
                        Action
                    </th>
                </tr>
            </thead>
            <tbody id="applicationTable">
                <tr>
                    <td
                        colspan="5"
                        class="loading"
                    >
                        Loading...
                    </td>
                </tr>
            </tbody>
        </table>
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
        themeLabel.textContent = "Light Mode";
    } else {
        root.classList.remove("light");
        themeLabel.textContent = "Dark Mode";
    }
}

// Load saved preference
const savedTheme =
    localStorage.getItem("cc_theme") || "dark";
applyTheme(savedTheme);
themeToggle.addEventListener("click", () => {
    const isLight =
        root.classList.contains("light");
    const newTheme =
        isLight ? "dark" : "light";
    applyTheme(newTheme);
    localStorage.setItem(
        "cc_theme",
        newTheme
    );
});

// ======= LOAD APPLICATIONS ========

// Adds a data-label to every cell so the mobile card layout
// can show "Organization", "Status", etc. next to each value.
function addMobileLabels() {
    const headers = Array.from(
        document.querySelectorAll("#apps thead th")
    ).map(th => th.textContent.trim());

    document.querySelectorAll("#applicationTable tr").forEach(row => {
        Array.from(row.children).forEach((cell, i) => {
            if (!cell.hasAttribute("colspan") && headers[i]) {
                cell.setAttribute("data-label", headers[i]);
            }
        });
    });
}

function loadApplications() {
    fetch("fetch_applications.php")
        .then(res => res.text())
        .then(data => {
            document.getElementById(
                "applicationTable"
            ).innerHTML = data;
            addMobileLabels();
        })
        .catch(error => {
            console.error(
                "Error loading applications:",
                error
            );
        });
}

// AUTO REFRESH

setInterval(
    loadApplications,
    3000
);

// INITIAL LOAD

loadApplications();
</script>
</body>
</html>