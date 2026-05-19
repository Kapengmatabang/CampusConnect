<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// ✅ ADMIN CHECK
if (!isset($_SESSION['user']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// ✅ DB CONNECT
$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $application_id = $_POST['application_id'] ?? 0;
    $date = $_POST['interview_date'] ?? '';
    $time = $_POST['interview_time'] ?? '';
    $link = $_POST['interview_link'] ?? '';

    if ($application_id && $date && $time && $link) {

        $datetime = $date . " " . $time;

        // ================= SAVE TO DATABASE =================
        $message = "Interview scheduled on $datetime. Join here: $link";

        $stmt = $conn->prepare("
            UPDATE applications
            SET
                interview_date = ?,
                interview_link = ?,
                status = 'Interview',
                notification = ?,
                response = NULL,
                responded_at = NULL
            WHERE application_id = ?
        ");

        $stmt->bind_param("sssi", $datetime, $link, $message, $application_id);

        if ($stmt->execute()) {

            // ================= GET STUDENT INFO =================
            $get = $conn->prepare("
                SELECT s.email, s.full_name
                FROM applications a
                JOIN students s ON a.student_id = s.student_id
                WHERE a.application_id = ?
            ");
            $get->bind_param("i", $application_id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();

            if ($data) {

                // ================= RESPONSE LINKS =================
                $baseURL = "http://localhost/campus_connect/student/respond_interview.php";

                $acceptLink = $baseURL . "?id=$application_id&response=Accepted";
                $declineLink = $baseURL . "?id=$application_id&response=Declined";

                // ================= SEND EMAIL =================
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;

                    // ✅ YOUR WORKING GMAIL
                    $mail->Username = 'millionmikay@gmail.com';
                    $mail->Password = 'ceflpwtnrlzflnyx'; // app password

                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('millionmikay@gmail.com', 'CampusConnect');

                    // ✅ SEND TO STUDENT (CHANGE AFTER TEST)
                    $mail->addAddress($data['email'], $data['full_name']);
                    // $mail->addAddress('millionmikay@gmail.com'); // test only

                    $mail->isHTML(true);
                    $mail->Subject = 'Interview Invitation';

                    // ✅ FIXED HTML (NO BROKEN LINK)
                    $mail->Body = "
                        <h2>Interview Invitation</h2>
                        <p>Hello {$data['full_name']},</p>

                        <p>Your interview has been scheduled.</p>

                        <p><b>Date & Time:</b> $datetime</p>

                        <p>
                            <a href='{$link}' target='_blank'
                               style='background:#0a5c0a;color:white;padding:10px 15px;
                               text-decoration:none;border-radius:5px;'>
                               Join Meeting
                            </a>
                        </p>

                        <hr>

                        <p><b>Respond:</b></p>

                        <a href='{$acceptLink}' style='color:green;'>✅ Accept Interview</a><br><br>
                        <a href='{$declineLink}' style='color:red;'>❌ Decline Interview</a>
                    ";

                    // ❌ REMOVE DEBUG IN PRODUCTION
                    // $mail->SMTPDebug = 2;

                    if (!$mail->send()) {
                        echo "❌ MAIL ERROR: " . $mail->ErrorInfo;
                    } else {
                        header("Location: dashboard.php?msg=Interview Scheduled & Email Sent!");
                        exit();
                    }

                } catch (Exception $e) {
                    echo "❌ EXCEPTION: {$mail->ErrorInfo}";
                }
            }

        } else {
            echo "❌ DB Error: " . $stmt->error;
        }

    } else {
        echo "❌ Missing data.";
    }
}
?>