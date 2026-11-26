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

    <style>
    /* ---------- HAMBURGER MENU: hidden on desktop ---------- */
    .menu-btn,
    .nav-overlay,
    .nav-close {
        display: none;
    }

    /* On desktop the wrapper is invisible, so the sidebar looks exactly as before */
    .nav-links {
        display: contents;
    }
    </style>

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
            flex-wrap: nowrap;
            align-items: center;
            gap: 10px;
            /* these would trap the fixed drawer inside the top bar */
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            transform: none !important;
            filter: none !important;
        }

        .sidebar h2 {
            order: 1;
            flex: 1 1 auto;
            margin: 0 !important;
            font-size: 22px !important;
        }

        .theme-toggle {
            order: 2;
            flex: 0 0 auto;
            font-size: 14px !important;
            padding: 10px 14px !important;
            margin: 0 !important;
        }

        #themeSwitchHint {
            display: none;
        }

        /* HAMBURGER BUTTON (3 lines) */
        .menu-btn {
            order: 3;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 5px;
            flex: 0 0 auto;
            width: 44px;
            height: 44px;
            padding: 0 11px;
            box-sizing: border-box;
            background: rgba(148, 163, 184, 0.15);
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 10px;
            cursor: pointer;
        }

        .menu-btn span {
            display: block;
            height: 3px;
            width: 100%;
            background: currentColor;
            color: #e2e8f0;
            border-radius: 2px;
            transition: transform 0.25s, opacity 0.25s;
        }

        html.light .menu-btn span {
            color: #1e293b;
        }

        /* SLIDE-IN DRAWER */
        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 75%;
            max-width: 280px;
            box-sizing: border-box;
            padding: 20px 16px;
            background: #0f172a;
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.5);
            transform: translateX(-105%);
            transition: transform 0.28s ease;
            z-index: 1001;
            overflow-y: auto;
        }

        html.light .nav-links {
            background: #ffffff;
        }

        .nav-links.open {
            transform: translateX(0);
        }

        .nav-close {
            display: block;
            align-self: flex-end;
            background: none;
            border: none;
            font-size: 28px;
            line-height: 1;
            color: inherit;
            cursor: pointer;
            padding: 4px 8px;
            margin-bottom: 8px;
        }

        .sidebar .nav-links a {
            flex: 0 0 auto;
            display: block;
            text-align: left;
            font-size: 17px !important;
            padding: 14px 16px !important;
            margin: 0 !important;
            border-radius: 10px;
            box-sizing: border-box;
            order: 0;
        }

        .nav-overlay {
            display: block;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.28s;
            z-index: 1000;
        }

        .nav-overlay.show {
            opacity: 1;
            pointer-events: auto;
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
<!-- SIDEBAR / TOP BAR -->
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

    <!-- 3-LINE MENU BUTTON (mobile only) -->
    <button
        class="menu-btn"
        id="menuBtn"
        type="button"
        aria-label="Open menu"
        aria-expanded="false"
    >
        <span></span>
        <span></span>
        <span></span>
    </button>

    <!-- LINKS (drawer on mobile, normal sidebar links on desktop) -->
    <div class="nav-links" id="navLinks">
        <button
            class="nav-close"
            id="navClose"
            type="button"
            aria-label="Close menu"
        >&times;</button>
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
</div>

<div class="nav-overlay" id="navOverlay"></div>

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

// ============ HAMBURGER MENU ============

const menuBtn = document.getElementById("menuBtn");
const navLinks = document.getElementById("navLinks");
const navOverlay = document.getElementById("navOverlay");
const navClose = document.getElementById("navClose");

function openMenu() {
    navLinks.classList.add("open");
    navOverlay.classList.add("show");
    menuBtn.setAttribute("aria-expanded", "true");
}

function closeMenu() {
    navLinks.classList.remove("open");
    navOverlay.classList.remove("show");
    menuBtn.setAttribute("aria-expanded", "false");
}

menuBtn.addEventListener("click", openMenu);
navClose.addEventListener("click", closeMenu);
navOverlay.addEventListener("click", closeMenu);
navLinks.querySelectorAll("a").forEach(a =>
    a.addEventListener("click", closeMenu)
);
document.addEventListener("keydown", e => {
    if (e.key === "Escape") closeMenu();
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