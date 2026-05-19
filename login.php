<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

// DATABASE
$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB failed");

$error = "";
$submitted = false;

// LOGIN
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $submitted = true;

    $userid   = trim($_POST['userid']);
    $password = trim($_POST['userpassword']);

    if (empty($userid) || empty($password)) {
        $error = "Please fill all fields.";
    } else {

        // ADMIN LOGIN
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username=?");
        $stmt->bind_param("s", $userid);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $admin = $res->fetch_assoc();

            if (password_verify($password, $admin['password'])) {
                $_SESSION['user'] = $admin['id'];
                $_SESSION['usertype'] = 'admin';
                $_SESSION['username'] = $admin['username'];

                header("Location: admin/dashboard.php");
                exit();
            }
        }

        $stmt->close();

        // STUDENT LOGIN
        $stmt = $conn->prepare("SELECT * FROM students WHERE student_id=?");
        $stmt->bind_param("s", $userid);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $user = $res->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user'] = $user['student_id'];
                $_SESSION['usertype'] = 'student';
                $_SESSION['username'] = $user['full_name'];

                header("Location: student/dashboard.php");
                exit();
            } else {
                $error = "Wrong password.";
            }
        } else {
            $error = "Account not found.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<link rel="icon" type="image/png" href="img/favicon.png">

<style>
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:'Segoe UI', sans-serif;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:
        linear-gradient(135deg, #0a5c0a, #020617),
        url('img/bg.jpg') center/cover;
}

/* LOGO */
.logo{
    position:absolute;
    top:20px;
    left:20px;
    width:150px;
}

/* GLASS CONTAINER */
.container{
    width:380px;
    padding:35px;
    border-radius:20px;
    background:rgba(255,255,255,0.1);
    backdrop-filter:blur(15px);
    border:1px solid rgba(255,255,255,0.2);
    box-shadow:0 10px 40px rgba(0,0,0,0.4);
    text-align:center;
    animation:fadeUp 0.8s ease;
}

/* TEXT */
h2{
    margin-bottom:5px;
}
.sub{
    color:#ccc;
    font-size:14px;
    margin-bottom:20px;
}

/* INPUT */
.input-group{
    position:relative;
    margin-bottom:15px;
}

.input-group input{
    width:100%;
    padding:12px;
    border-radius:10px;
    border:none;
    outline:none;
    background:rgba(255,255,255,0.15);
    color:white;
}

.input-group input::placeholder{
    color:#ccc;
}

/* BUTTON */
.login-btn{
    width:100%;
    padding:12px;
    border:none;
    border-radius:30px;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:white;
    font-weight:bold;
    cursor:pointer;
    transition:0.3s;
}

.login-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 5px 15px rgba(34,197,94,0.4);
}

/* MESSAGES */
.alert{
    padding:10px;
    border-radius:10px;
    margin-bottom:15px;
    font-size:13px;
}

.success{ background:#22c55e; }
.error{ background:#ef4444; }

/* LINK */
a{
    color:#22c55e;
    text-decoration:none;
}

/* ANIMATION */
@keyframes fadeUp{
    from{
        opacity:0;
        transform:translateY(30px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}
</style>
</head>

<body>

<a href="index.html">
    <img src="img/cclogo.png" class="logo">
</a>

<div class="container">

<h2>Welcome Back</h2>
<p class="sub">Login to your account</p>

<?php if (isset($_GET['registered'])): ?>
<div class="alert success">
Account created successfully!
</div>
<?php endif; ?>

<?php if ($submitted && $error): ?>
<div class="alert error">
<?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<form method="POST">

<div class="input-group">
<input type="text" name="userid" placeholder="Student ID / Admin Username" required>
</div>

<div class="input-group">
<input type="password" id="password" name="userpassword" placeholder="Password" required>
</div>

<div style="font-size:12px; margin-bottom:15px;">
<input type="checkbox" onclick="togglePassword()"> Show Password
</div>

<input type="submit" value="Login" class="login-btn" id="loginBtn">

<p style="margin-top:15px; font-size:13px;">
Don't have an account? <a href="signup.php">Sign Up</a>
</p>

</form>

</div>

<script>
function togglePassword(){
    let x = document.getElementById("password");
    x.type = x.type === "password" ? "text" : "password";
}

document.querySelector("form").addEventListener("submit", function(){
    document.getElementById("loginBtn").value = "Logging in...";
});
</script>

</body>
</html>