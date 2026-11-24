<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/animations.css">  
    <link rel="stylesheet" href="css/main.css">  
    <link rel="stylesheet" href="css/signup.css">
        
    <title>Create Student Account</title>

    <style>
        .container {
            animation: transitionIn-X 0.5s;
        }
    </style>
</head>

<body>

<?php
session_start();

$_SESSION["user"] = "";
$_SESSION["usertype"] = "";

date_default_timezone_set('Asia/Kolkata');
$_SESSION["date"] = date('Y-m-d');

include("connection.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Pull data from previous signup step
    $fname      = $_SESSION['student']['fname'];
    $lname      = $_SESSION['student']['lname'];
    $email      = $_SESSION['student']['email'];
    $student_id = $_SESSION['student']['student_id'];
    $dob        = $_SESSION['student']['dob'];

    // New password fields
    $password   = trim($_POST['newpassword']);
    $cpassword  = trim($_POST['cpassword']);
    $tele       = trim($_POST['tele']); // phone number

    if ($password !== $cpassword) {
        $error = "<label class='form-label' style='color:red;'>Password confirmation does not match!</label>";
    } else {

        // Check if student ID already exists
        $check = $database->prepare("SELECT * FROM patient WHERE student_id = ?");
        $check->bind_param("s", $student_id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "<label class='form-label' style='color:red;'>Student ID already registered!</label>";

        } else {

            // Insert record (medical fields empty)
            $stmt = $database->prepare("
                INSERT INTO patient (student_id, password, medical_record, record_date, record_time)
                VALUES (?, ?, NULL, NULL, NULL)
            ");
            $stmt->bind_param("ss", $student_id, $password);
            $stmt->execute();
            $stmt->close();

            // Redirect to login after successful signup
            header("Location: login.php?registered=1");
            exit;
        }
    }
}
?>

<center>
<div class="container">
    <table border="0" style="width: 69%;">
        <tr>
            <td colspan="2">
                <p class="header-text">Create Student Account</p>
                <p class="sub-text">Almost done! Set your login password.</p>
                <?php echo $error; ?>
            </td>
        </tr>

        <form action="" method="POST">

            <tr>
                <td class="label-td" colspan="2">
                    <label class="form-label">Mobile Number:</label>
                </td>
            </tr>

            <tr>
                <td class="label-td" colspan="2">
                    <input type="tel" name="tele" class="input-text"
                        placeholder="ex: 09123456789"
                        pattern="^09[0-9]{9}$"
                        maxlength="11"
                        required>
                </td>
            </tr>

            <tr>
                <td class="label-td" colspan="2">
                    <label class="form-label">Create New Password:</label>
                </td>
            </tr>

            <tr>
                <td class="label-td" colspan="2">
                    <input type="password" name="newpassword" class="input-text" placeholder="New Password" required>
                </td>
            </tr>

            <tr>
                <td class="label-td" colspan="2">
                    <label class="form-label">Confirm Password:</label>
                </td>
            </tr>

            <tr>
                <td class="label-td" colspan="2">
                    <input type="password" name="cpassword" class="input-text" placeholder="Confirm Password" required>
                </td>
            </tr>

            <tr>
                <td>
                    <input type="reset" value="Reset" class="login-btn btn-primary-soft btn">
                </td>
                <td>
                    <input type="submit" value="Create Account" class="login-btn btn-primary btn">
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <br>
                    <label class="sub-text">Already have an account?</label>
                    <a href="login.php" class="hover-link1 non-style-link">Login</a>
                    <br><br><br>
                </td>
            </tr>

        </form>
    </table>
</div>
</center>

</body>
</html>
