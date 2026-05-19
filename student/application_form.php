<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

// GET STUDENT INFO
$student_id = $_SESSION["user"];
$stmtUser = $conn->prepare("SELECT * FROM students WHERE student_id=?");
$stmtUser->bind_param("s", $student_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

// GET ORG
$org_id = $_GET['org_id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM organizations WHERE org_id=?");
$stmt->bind_param("i", $org_id);
$stmt->execute();
$org = $stmt->get_result()->fetch_assoc();

if (!$org) {
    die("Invalid organization.");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Organization Application</title>

<style>
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:'Segoe UI',sans-serif;
    background:linear-gradient(135deg,#0f172a,#1e293b);
    color:white;
}

/* CONTAINER */
.container{
    max-width:750px;
    margin:50px auto;
    padding:30px;
    border-radius:15px;
    background:rgba(255,255,255,0.08);
    backdrop-filter:blur(15px);
    border:1px solid rgba(255,255,255,0.15);
    box-shadow:0 0 20px rgba(0,0,0,0.4);
}

/* BACK */
.back{
    display:inline-block;
    margin-bottom:15px;
    text-decoration:none;
    color:#22c55e;
    font-weight:bold;
}
.back:hover{
    text-decoration:underline;
}

/* HEADER */
.header{
    text-align:center;
    margin-bottom:20px;
}
.header h2{
    font-size:28px;
}
.sub{
    font-size:14px;
    color:#cbd5f5;
}

/* ORG CARD */
.org-box{
    display:flex;
    align-items:center;
    gap:15px;
    margin-bottom:20px;
    padding:12px;
    background:rgba(255,255,255,0.05);
    border-radius:10px;
}

.org-box img{
    width:60px;
    height:60px;
    object-fit:contain;
    background:#000;
    border-radius:8px;
}

/* FORM */
label{
    font-size:13px;
    color:#cbd5f5;
}

input, select{
    width:100%;
    padding:10px;
    margin:6px 0 15px;
    border-radius:8px;
    border:none;
    outline:none;
    background:rgba(255,255,255,0.1);
    color:white;
}

input::placeholder{
    color:#aaa;
}

/* FILE */
input[type="file"]{
    background:transparent;
}

/* BUTTON */
button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:25px;
    background:#22c55e;
    color:white;
    font-weight:bold;
    cursor:pointer;
    transition:0.3s;
}
button:hover{
    background:#16a34a;
    transform:translateY(-2px);
}

/* ANIMATION */
.container{
    animation:fadeUp 0.6s ease;
}

@keyframes fadeUp{
    from{
        opacity:0;
        transform:translateY(20px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}
</style>
</head>

<body>

<div class="container">

<a href="explore_orgs.php" class="back">⬅ Back</a>

<div class="header">
    <h2>Apply to Organization</h2>
    <p class="sub">Fill in your details below</p>
</div>

<!-- ORG INFO -->
<div class="org-box">
    <img src="<?= !empty($org['image']) ? '../'.$org['image'] : '../img/default.png' ?>">
    <div>
        <strong><?= htmlspecialchars($org['org_name']) ?></strong>
        <p style="font-size:12px;color:#aaa;">
            <?= substr(htmlspecialchars($org['description']),0,60) ?>...
        </p>
    </div>
</div>

<form method="POST" action="submit_application.php" enctype="multipart/form-data">

<input type="hidden" name="org_id" value="<?= $org_id ?>">

<label>Full Name</label>
<input type="text" name="fullname" value="<?= htmlspecialchars($user['full_name']) ?>" required>

<label>Email</label>
<input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

<label>Contact Number</label>
<input type="text" name="contact" placeholder="09XXXXXXXXX" required>

<label>Course</label>
<input type="text" name="course" value="<?= htmlspecialchars($user['course']) ?>" required>

<label>Year & Section</label>
<input type="text" name="section" value="<?= htmlspecialchars($user['section']) ?>" required>

<label>Attachment (Resume / File)</label>
<input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.png" required>

<label>Preferred Role</label>
<select name="role" required>
    <option value="">Select Role</option>
    <option>Member</option>
    <option>Officer</option>
    <option>Volunteer</option>
</select>

<button type="submit">🚀 Submit Application</button>

</form>

</div>

</body>
</html>