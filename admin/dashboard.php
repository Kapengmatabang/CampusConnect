<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

// CREATE POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {

    $title = $_POST['title'];
    $content = $_POST['content'];
    $tag = $_POST['tag'];
    $imageName = "";

    if (!empty($_FILES['image']['name'])) {
        if (!is_dir("../uploads")) mkdir("../uploads", 0777, true);
        $imageName = time() . "_" . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $imageName);
    }

    $stmt = $conn->prepare("INSERT INTO posts (title, content, tag, image) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $title, $content, $tag, $imageName);
    $stmt->execute();

    header("Location: dashboard.php");
    exit();
}

// DELETE POST
if (isset($_POST['delete_post'])) {
    $id = intval($_POST['id']);
    $res = $conn->query("SELECT image FROM posts WHERE post_id=$id");
    $data = $res->fetch_assoc();

    if (!empty($data['image']) && file_exists("../uploads/".$data['image'])) {
        unlink("../uploads/".$data['image']);
    }

    $conn->query("DELETE FROM posts WHERE post_id=$id");
    header("Location: dashboard.php");
    exit();
}

// DATA
$stats = $conn->query("
    SELECT COUNT(*) total,
    SUM(status='Pending') pending,
    SUM(status='Approved') approved,
    SUM(status='Rejected') rejected
    FROM applications
")->fetch_assoc();

$applications = $conn->query("
    SELECT a.*, s.full_name, s.email, o.org_name
    FROM applications a
    LEFT JOIN students s ON a.student_id = s.student_id
    LEFT JOIN organizations o ON a.org_id = o.org_id
    ORDER BY a.applied_at DESC
");

$posts = $conn->query("SELECT * FROM posts ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>

<style>
body {
    margin:0;
    font-family:'Segoe UI',sans-serif;
    background:linear-gradient(135deg,#0f172a,#1e293b);
    color:white;
    display:flex;
}

/* SIDEBAR */
.sidebar {
    width:220px;
    background:rgba(255,255,255,0.05);
    backdrop-filter:blur(12px);
    padding:20px;
    height:100vh;
}
.sidebar h2 { margin-bottom:20px; }
.sidebar a {
    display:block;
    padding:10px;
    border-radius:8px;
    margin-bottom:10px;
    color:white;
    text-decoration:none;
    transition:.3s;
}
.sidebar a:hover {
    background:rgba(255,255,255,0.15);
}

/* MAIN */
.main {
    flex:1;
    padding:25px;
}

/* STATS */
.cards {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
}
.card {
    background:rgba(255,255,255,0.08);
    backdrop-filter:blur(12px);
    padding:20px;
    border-radius:15px;
    text-align:center;
    font-size:18px;
    transition:.3s;
}
.card:hover {
    transform:translateY(-5px);
}

/* TABLE */
table {
    width:100%;
    margin-top:20px;
    border-collapse:collapse;
    background:rgba(255,255,255,0.05);
    backdrop-filter:blur(10px);
    border-radius:10px;
    overflow:hidden;
}
th, td {
    padding:10px;
    text-align:center;
}
th {
    background:rgba(255,255,255,0.1);
}
tr:hover {
    background:rgba(255,255,255,0.05);
}

/* BUTTONS */
button {
    padding:6px 12px;
    border:none;
    border-radius:8px;
    color:white;
    cursor:pointer;
    transition:.2s;
}
button:hover { transform:scale(1.05); }

.approve { background:#22c55e; }
.reject { background:#ef4444; }
.btn-interview { background:#3b82f6; }
.delete { background:#ef4444; }

/* POSTS */
.post-box {
    background:rgba(255,255,255,0.08);
    padding:15px;
    border-radius:15px;
    margin-bottom:15px;
    backdrop-filter:blur(10px);
}
.post-box img {
    width:100%;
    border-radius:10px;
    margin-top:10px;
}

/* MODAL */
.modal {
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.6);
}
.modal-content {
    background:#1e293b;
    padding:20px;
    border-radius:15px;
    width:350px;
    margin:100px auto;
}
.modal input, .modal textarea, .modal select {
    width:100%;
    padding:8px;
    margin:8px 0;
    border-radius:8px;
    border:none;
}
</style>
</head>

<body>

<div class="sidebar">
    <h2>Admin</h2>
    <a onclick="openPostModal()">➕ Create Post</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main">

<h2>Dashboard</h2>

<div class="cards">
    <div class="card">Total<br><?= $stats['total'] ?></div>
    <div class="card">Pending<br><?= $stats['pending'] ?></div>
    <div class="card">Approved<br><?= $stats['approved'] ?></div>
    <div class="card">Rejected<br><?= $stats['rejected'] ?></div>
</div>

<h3>Applications</h3>

<table>
<tr>
<th>Student</th>
<th>Email</th>
<th>Org</th>
<th>Status</th>
<th>Actions</th>
</tr>

<?php while($a = $applications->fetch_assoc()): ?>
<tr>
<td><?= $a['full_name'] ?></td>
<td><?= $a['email'] ?></td>
<td><?= $a['org_name'] ?></td>
<td><?= $a['status'] ?></td>
<td>
<form method="POST" action="update_status.php" style="display:inline;">
<input type="hidden" name="id" value="<?= $a['application_id'] ?>">
<input type="hidden" name="status" value="Approved">
<button class="approve">Approve</button>
</form>

<form method="POST" action="update_status.php" style="display:inline;">
<input type="hidden" name="id" value="<?= $a['application_id'] ?>">
<input type="hidden" name="status" value="Rejected">
<button class="reject">Reject</button>
</form>

<button class="btn-interview" onclick="openInterviewModal(<?= $a['application_id'] ?>)">Interview</button>
</td>
</tr>
<?php endwhile; ?>
</table>

<h3>Posts</h3>

<?php while($p = $posts->fetch_assoc()): ?>
<div class="post-box">
<b><?= $p['title'] ?></b>
<p><?= $p['content'] ?></p>

<?php if (!empty($p['image'])): ?>
<img src="../uploads/<?= $p['image'] ?>">
<?php endif; ?>

<form method="POST">
<input type="hidden" name="id" value="<?= $p['post_id'] ?>">
<button name="delete_post" class="delete">Delete</button>
</form>
</div>
<?php endwhile; ?>

</div>

<!-- MODALS -->
<div id="interviewModal" class="modal">
<div class="modal-content">
<h3>Set Interview</h3>
<form method="POST" action="set_interview.php">
<input type="hidden" name="application_id" id="app_id">
<input type="date" name="interview_date" required>
<input type="time" name="interview_time" required>
<input type="text" name="interview_link" placeholder="Meeting link" required>
<button class="btn-interview">Save</button>
<button type="button" onclick="closeInterviewModal()" class="reject">Cancel</button>
</form>
</div>
</div>

<div id="postModal" class="modal">
<div class="modal-content">
<h3>Create Post</h3>
<form method="POST" enctype="multipart/form-data">
<input type="text" name="title" placeholder="Title" required>
<textarea name="content" placeholder="Content" required></textarea>
<select name="tag">
<option value="latest">Latest</option>
<option value="applicants">Applicants</option>
<option value="research">Research</option>
</select>
<input type="file" name="image">
<button name="create_post" class="approve">Post</button>
<button type="button" onclick="closePostModal()" class="reject">Cancel</button>
</form>
</div>
</div>

<script>
function openPostModal(){ document.getElementById("postModal").style.display="block"; }
function closePostModal(){ document.getElementById("postModal").style.display="none"; }

function openInterviewModal(id){
    document.getElementById("interviewModal").style.display="block";
    document.getElementById("app_id").value=id;
}
function closeInterviewModal(){
    document.getElementById("interviewModal").style.display="none";
}
</script>

</body>
</html>