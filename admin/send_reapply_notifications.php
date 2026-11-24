<?php
// ============================================================
// Checks for rejected applicants whose reapply window opens
// TOMORROW and sends a one-time heads-up email — not spammy,
// fires exactly once per applicant the day before their window
// actually opens.
//
// Meant to be run on a schedule (once a day is plenty), NOT loaded
// in a browser by a logged-in admin.
//
// This is a background/CLI script, so it does NOT go through
// auth.php (no admin session exists when a scheduler runs this).
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error . "\n");

$today = date('Y-m-d');

// Find rejected applications whose reapply window opens TOMORROW
// (not today or already passed) — this is a heads-up notification sent
// exactly once, the day before, so it never repeats or spammy.
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$stmt = $conn->prepare("
    SELECT a.application_id, a.reapply_date, s.email, s.full_name, o.org_name
    FROM applications a
    JOIN students s ON a.student_id = s.student_id
    JOIN organizations o ON a.org_id = o.org_id
    WHERE a.status = 'Rejected'
    AND a.reapply_date = ?
    AND a.reapply_notified = 0
");
$stmt->bind_param("s", $tomorrow);
$stmt->execute();
$result = $stmt->get_result();

$sentCount = 0;
$failCount = 0;

while ($row = $result->fetch_assoc()) {

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
        $mail->addAddress($row['email'], $row['full_name']);
        $mail->isHTML(true);
        $mail->Subject = 'Reapplications to ' . $row['org_name'] . ' open tomorrow';

        $reapplyDateFormatted = date('F j, Y', strtotime($row['reapply_date']));

        $mail->Body = "
            <h2>Your Reapply Window Opens Tomorrow</h2>
            <p>Hello {$row['full_name']},</p>
            <p>Just a heads up — starting <b>{$reapplyDateFormatted}</b>, you'll be able to submit a new application to <b>{$row['org_name']}</b>.</p>
            <p>We'd love to see you apply again.</p>
            <p>— CampusConnect</p>
        ";

        $mail->send();

        // Mark as notified so this doesn't get sent again tomorrow
        $update = $conn->prepare("UPDATE applications SET reapply_notified = 1 WHERE application_id = ?");
        $update->bind_param("i", $row['application_id']);
        $update->execute();

        $sentCount++;
        echo "Sent: {$row['email']} ({$row['org_name']})\n";

    } catch (Exception $e) {
        $failCount++;
        error_log("Reapply notification failed for application {$row['application_id']}: " . $mail->ErrorInfo);
        echo "FAILED: {$row['email']} - " . $mail->ErrorInfo . "\n";
    }
}

echo "\nDone. Sent: $sentCount, Failed: $failCount\n";