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
$stmt = $conn->prepare("SELECT course FROM students WHERE student_id=?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// 🔥 FIX: normalize course (handles BSPSY / BSPSYCH issue)
$student_course = strtoupper(trim($user['course']));

// MAP COURSE → GROUP
$map = [
    'BSIT'=>'CITCS','BSCS'=>'CITCS','ACT'=>'CITCS',

    // 🔥 FIXED HERE
    'BSPSY'=>'CAS','BSPSYCH'=>'CAS',
    'BACOM'=>'CAS','BPA'=>'CAS','BAPOL'=>'CAS',

    'BSBA'=>'CBA','BSMM'=>'CBA','BSOM'=>'CBA',
    'BSCRIM'=>'CCJ','BSISM'=>'CCJ',
    'BEE'=>'CTE','BSSE'=>'CTE',
    'BSSW'=>'INSTITUTE'
];

$group = $map[$student_course] ?? 'GENERAL';

// FETCH ORGS (strong filter)
$stmt = $conn->prepare("
    SELECT * FROM organizations
    WHERE course_group = ?
    OR course_group = 'GENERAL'
    OR course_group = 'MEDIA'
");
$stmt->bind_param("s", $group);
$stmt->execute();
$res = $stmt->get_result();

// SORT
$general = [];
$media = [];
$courseOrgs = [];

while($org = $res->fetch_assoc()){
    $cg = trim($org['course_group']); // 🔥 remove spaces

    if($cg === 'GENERAL') $general[] = $org;
    elseif($cg === 'MEDIA') $media[] = $org;
    else $courseOrgs[] = $org;
}

// GET APPLICATIONS
$applications = [];
$q = $conn->prepare("SELECT org_id FROM applications WHERE student_id=?");
$q->bind_param("i",$student_id);
$q->execute();
$r = $q->get_result();

while($row = $r->fetch_assoc()){
    $applications[] = $row['org_id'];
}

function img($o){
    return !empty($o['image']) ? "../".$o['image'] : "../img/default.png";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Explore Organizations</title>

<style>
body{
    margin:0;
    font-family:'Segoe UI';
    background:linear-gradient(#0f172a,#1e293b);
    color:white;
}

/* HEADER */
.header{
    text-align:center;
    padding:20px;
    position:relative;
}
.back-btn{
    position:absolute;
    left:20px;
    top:20px;
    padding:8px 14px;
    border-radius:20px;
    background:rgba(255,255,255,0.1);
    color:white;
    text-decoration:none;
}

/* SECTION */
.section-title{
    margin:30px 20px 10px;
    font-size:20px;
    color:#22c55e;
}

/* GRID */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
    gap:15px;
    padding:0 20px;
}

/* CARD SAME SIZE */
.card{
    background:rgba(255,255,255,0.08);
    border:1px solid rgba(255,255,255,0.15);
    padding:12px;
    border-radius:12px;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    min-height:340px;
}

/* IMAGE FIX */
.img-box{
    width:100%;
    height:100px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#000;
    border-radius:8px;
    overflow:hidden;
}
.img-box img{
    max-width:100%;
    max-height:100%;
}

/* TITLE */
.card h4{
    margin:8px 0 5px;
    font-size:15px;
}

/* DESC */
.desc{
    font-size:12px;
    color:#cbd5f5;
    min-height:40px;
}

/* BUTTON GROUP */
.btn-group{
    margin-top:auto;
}

/* BUTTON */
.btn{
    width:100%;
    padding:7px;
    border:none;
    border-radius:20px;
    margin-top:6px;
    cursor:pointer;
    font-size:13px;
}
.apply{background:#22c55e;color:white;}
.view{background:#334155;color:white;}
.disabled{background:gray;}

/* MODAL */
.modal{
    display:none;
    position:fixed;
    top:0;left:0;
    width:100%;height:100%;
    background:rgba(0,0,0,0.7);
}
.modal-content{
    background:#1e293b;
    margin:8% auto;
    padding:20px;
    width:320px;
    border-radius:12px;
    text-align:center;
}
.modal-content img{
    width:100%;
    border-radius:10px;
}
</style>
</head>

<body>

<div class="header">
<a href="dashboard.php" class="back-btn">⬅ Back</a>
<h2>Explore Organizations</h2>
<p><?= $student_course ?></p>
</div>

<?php function renderCards($list, $applications){ ?>
<div class="grid">
<?php foreach($list as $o):
$applied = in_array($o['org_id'], $applications);
?>

<div class="card">

<div class="img-box">
<img src="<?= img($o) ?>">
</div>

<h4><?= htmlspecialchars($o['org_name']) ?></h4>

<!-- SHORT INFO -->
<p class="desc">
<?= substr(htmlspecialchars($o['description']),0,60) ?>...
</p>

<div class="btn-group">

<?php if($applied): ?>
<button class="btn disabled">✔ Applied</button>
<?php else: ?>
<form method="GET" action="application_form.php">
<input type="hidden" name="org_id" value="<?= $o['org_id'] ?>">
<button class="btn apply">Apply</button>
</form>
<?php endif; ?>

<button class="btn view"
onclick="openModal(
'<?= htmlspecialchars($o['org_name']) ?>',
'<?= htmlspecialchars($o['description']) ?>',
'<?= img($o) ?>'
)">
Read More
</button>

</div>

</div>

<?php endforeach; ?>
</div>
<?php } ?>

<!-- SECTIONS -->
<div class="section-title">General Organizations</div>
<?php renderCards($general,$applications); ?>

<div class="section-title"> Your Course Organizations</div>
<?php renderCards($courseOrgs,$applications); ?>

<div class="section-title"> Media Organizations</div>
<?php renderCards($media,$applications); ?>

<!-- MODAL -->
<div id="modal" class="modal">
<div class="modal-content">
<img id="mImg">
<h3 id="mTitle"></h3>
<p id="mDesc"></p>
<button class="btn view" onclick="closeModal()">Close</button>
</div>
</div>

<script>
function openModal(title,desc,img){
    document.getElementById("modal").style.display="block";
    document.getElementById("mTitle").innerText=title;
    document.getElementById("mDesc").innerText=desc;
    document.getElementById("mImg").src=img;
}
function closeModal(){
    document.getElementById("modal").style.display="none";
}
</script>

</body>
</html>