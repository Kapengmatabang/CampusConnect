<?php
session_start();

// ✅ PROTECT PAGE
if (!isset($_SESSION["user"]) || $_SESSION["usertype"] !== "student") {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION["user"];

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

// ✅ GET USER INFO
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id=?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<title>Student Dashboard</title>

<style>
* {
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #0f172a, #1e293b);
    color:white;
    display:flex;
}

/* SIDEBAR */
.sidebar {
    width:230px;
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(12px);
    border-right:1px solid rgba(255,255,255,0.1);
    height:100vh;
    padding:20px;
}

.sidebar h2 {
    margin-bottom:20px;
}

.sidebar a {
    display:block;
    padding:12px;
    margin-bottom:8px;
    border-radius:8px;
    color:white;
    text-decoration:none;
    transition:0.3s;
}

.sidebar a:hover {
    background: rgba(255,255,255,0.1);
    transform: translateX(5px);
}

/* MAIN */
.main {
    flex:1;
    padding:25px;
}

/* GLASS CARD */
.card {
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(12px);
    border:1px solid rgba(255,255,255,0.15);
    border-radius:15px;
    padding:20px;
    margin-bottom:20px;
    animation: fadeUp 0.4s ease;
}

/* PROFILE */
.profile h2 {
    margin-bottom:5px;
}

.profile p {
    color:#ccc;
    font-size:14px;
}

/* TABLE */
table {
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

th {
    background: rgba(255,255,255,0.1);
    padding:12px;
    font-size:13px;
}

td {
    padding:10px;
    font-size:13px;
    border-bottom:1px solid rgba(255,255,255,0.1);
}

tr:hover {
    background: rgba(255,255,255,0.05);
}

/* STATUS COLORS */
.pending { color:#facc15; font-weight:bold; }
.approved { color:#22c55e; font-weight:bold; }
.rejected { color:#ef4444; font-weight:bold; }
.interview { color:#38bdf8; font-weight:bold; }

/* BUTTON */
.cancel-btn {
    background: linear-gradient(135deg, #ef4444, #b91c1c);
    border:none;
    padding:6px 12px;
    color:white;
    border-radius:8px;
    cursor:pointer;
    transition:0.3s;
}

.cancel-btn:hover {
    transform: scale(1.05);
}

/* LOADING */
.loading {
    text-align:center;
    color:#aaa;
}

/* ANIMATION */
@keyframes fadeUp {
    from {
        opacity:0;
        transform: translateY(15px);
    }
    to {
        opacity:1;
        transform: translateY(0);
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>🎓 Student</h2>
    <a href="#">Dashboard</a>
    <a href="explore_orgs.php">Explore Orgs</a>
    <a href="#apps">My Applications</a>
    <a href="../logout.php">Logout</a>
</div>

<!-- MAIN -->
<div class="main">

<!-- PROFILE -->
<div class="card profile">
    <h2><?= htmlspecialchars($user['full_name']); ?> 👋</h2>
    <p><?= htmlspecialchars($user['course'] . " - " . $user['section']); ?></p>
    <p><?= htmlspecialchars($user['email']); ?></p>
</div>

<!-- APPLICATIONS -->
<div class="card" id="apps">
    <h3>📄 My Applications</h3>

    <table>
        <thead>
            <tr>
                <th>Organization</th>
                <th>Status</th>
                <th>Interview</th>
                <th>Date Applied</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody id="applicationTable">
            <tr><td colspan="5" class="loading">Loading...</td></tr>
        </tbody>
    </table>
</div>

</div>

<script>
// LOAD APPLICATIONS
function loadApplications() {
    fetch("fetch_applications.php")
    .then(res => res.text())
    .then(data => {
        document.getElementById("applicationTable").innerHTML = data;
    });
}

// AUTO REFRESH
setInterval(loadApplications, 3000);

// INITIAL LOAD
loadApplications();
</script>

</body>
</html>