<?php
require 'auth.php'; // sets $conn, $scopedOrgId, $isSuperAdmin; redirects if not logged in as admin

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $application_id      = intval($_POST['application_id'] ?? 0);
    $interview_date       = $_POST['interview_date'] ?? '';
    $interview_time       = $_POST['interview_time'] ?? '';
    $interview_location   = $_POST['interview_location'] ?? '';
    $interview_link       = $_POST['interview_link'] ?? '';
    $interview_notes      = $_POST['interview_notes'] ?? '';

    if (!$application_id || !$interview_date || !$interview_time) {
        die("Missing data: interview date and time are required.");
    }

    // RBAC CHECK: a scoped org admin can only schedule interviews for
    // applications that belong to their own organization.
    requireOrgAccessToApplication($conn, $application_id, $scopedOrgId, $isSuperAdmin);

    // Build a short human-readable notification message
    if ($interview_link) {
        $whereMsg = "Join here: $interview_link";
    } elseif ($interview_location) {
        $whereMsg = "Location: $interview_location";
    } else {
        $whereMsg = "Details to follow.";
    }
    $message = "Interview scheduled on $interview_date at $interview_time. $whereMsg";

    // ================= SAVE TO DATABASE =================
    
    $stmt = $conn->prepare("
        UPDATE applications
        SET
            interview_date = ?,
            interview_time = ?,
            interview_location = ?,
            interview_link = ?,
            interview_notes = ?,
            status = 'Interview',
            notification = ?,
            response = NULL,
            responded_at = NULL
        WHERE application_id = ?
    ");
    $stmt->bind_param(
        "ssssssi",
        $interview_date,
        $interview_time,
        $interview_location,
        $interview_link,
        $interview_notes,
        $message,
        $application_id
    );

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

            $baseURL = "http://localhost/campus_connect/student/respond_interview.php";
            $acceptLink  = $baseURL . "?id=$application_id&response=Accepted";
            $declineLink = $baseURL . "?id=$application_id&response=Declined";

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;

                $mail->Username = 'millionmikay@gmail.com';
                $mail->Password = 'ceflpwtnrlzflnyx'; // app password

                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('millionmikay@gmail.com', 'CampusConnect');
                $mail->addAddress($data['email'], $data['full_name']);

                $mail->isHTML(true);
                $mail->Subject = 'Interview Invitation';

                $linkHtml = $interview_link
                    ? "<p><a href='{$interview_link}' target='_blank' style='background:#0a5c0a;color:white;padding:10px 15px;text-decoration:none;border-radius:5px;'>Join Meeting</a></p>"
                    : "";
                $locationHtml = $interview_location
                    ? "<p><b>Location:</b> " . htmlspecialchars($interview_location) . "</p>"
                    : "";
                $notesHtml = $interview_notes
                    ? "<p><b>Notes:</b> " . nl2br(htmlspecialchars($interview_notes)) . "</p>"
                    : "";

                $mail->Body = "
                    <h2>Interview Invitation</h2>
                    <p>Hello {$data['full_name']},</p>
                    <p>Your interview has been scheduled.</p>
                    <p><b>Date:</b> $interview_date</p>
                    <p><b>Time:</b> $interview_time</p>
                    $locationHtml
                    $linkHtml
                    $notesHtml
                    <hr>
                    <p><b>Respond:</b></p>
                    <a href='{$acceptLink}' style='color:green;'> Accept Interview</a><br><br>
                    <a href='{$declineLink}' style='color:red;'> Decline Interview</a>
                ";

                $mail->send();

            } catch (Exception $e) {
                error_log("Interview email failed for application $application_id: " . $mail->ErrorInfo);
            }
        }

        header("Location: applications.php?msg=Interview Scheduled");
        exit();

    } else {
        die("DB Error: " . $stmt->error);
    }

} else {
    header("Location: applications.php");
    exit();
}