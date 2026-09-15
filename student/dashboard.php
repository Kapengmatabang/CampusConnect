<?php

session_start();

// PROTECT PAGE
if (!isset($_SESSION["user"]) || $_SESSION["usertype"] !== "student") {

    header("Location: ../login.php");

    exit();
}
$student_id = $_SESSION["user"];
$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) {
    die("DB Error");
}

// GET USER INFO
$stmt = $conn->prepare(
    "SELECT * FROM students WHERE student_id=?"
);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <link
        rel="stylesheet"
        href="../css/student/dashboard.css"
    >
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

function loadApplications() {
    fetch("fetch_applications.php")
        .then(res => res.text())
        .then(data => {
            document.getElementById(
                "applicationTable"
            ).innerHTML = data;
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