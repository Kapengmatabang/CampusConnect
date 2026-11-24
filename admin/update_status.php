<?php
require 'auth.php'; // sets $conn, $scopedOrgId, $isSuperAdmin; redirects if not logged in as admin

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$id     = intval($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

$allowedStatuses = ['Approved', 'Rejected'];
if (!$id || !in_array($status, $allowedStatuses, true)) {
    die(" Missing or invalid data.");
}

// RBAC CHECK: a scoped org admin can only act on applications that
// belong to their own organization.
requireOrgAccessToApplication($conn, $id, $scopedOrgId, $isSuperAdmin);

// ================= UPDATE STATUS =================
if ($status === 'Rejected') {
    // Store the reapply date in the DB (not just in the email text) so
    // it can be shown on Records and checked later by the notifier script.
    $reapplyDateSql = date('Y-m-d', strtotime('+30 days'));
    $stmt = $conn->prepare("
        UPDATE applications
        SET status = ?, reapply_date = ?, reapply_notified = 0
        WHERE application_id = ?
    ");
    $stmt->bind_param("ssi", $status, $reapplyDateSql, $id);
} else {
    $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
    $stmt->bind_param("si", $status, $id);
}
$stmt->execute();

// ================= GET STUDENT + ORG INFO FOR EMAIL =================
$get = $conn->prepare("
    SELECT s.email, s.full_name, o.org_name
    FROM applications a
    JOIN students s ON a.student_id = s.student_id
    JOIN organizations o ON a.org_id = o.org_id
    WHERE a.application_id = ?
");
$get->bind_param("i", $id);
$get->execute();
$data = $get->get_result()->fetch_assoc();

if ($data) {

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

        if ($status === 'Rejected') {

            // Reapply waiting period — adjust to match your org's actual policy
            $reapplyDate = date('F j, Y', strtotime('+30 days'));

            $mail->Subject = 'Application Update — ' . $data['org_name'];
            $mail->Body = "
                <h2>Application Update</h2>
                <p>Hello {$data['full_name']},</p>
                <p>Thank you for applying to <b>{$data['org_name']}</b> and for taking the time to go through the process.</p>
                <p>After careful review, we're unable to move forward with your application at this time.</p>
                <p>You're welcome to reapply starting <b>{$reapplyDate}</b>. We'd genuinely love to see another application from you then.</p>
                <p>Thank you again for your interest in {$data['org_name']}.</p>
                <p>— CampusConnect</p>
            ";

        } else { // Approved
            $mail->Subject = 'Congratulations! Application Approved — ' . $data['org_name'];
            $mail->Body = "
                <h2>Congratulations!</h2>
                <p>Hello {$data['full_name']},</p>
                <p>We're happy to let you know your application to <b>{$data['org_name']}</b> has been approved.</p>
                <p>Welcome aboard — you'll be contacted with next steps soon.</p>
                <p>— CampusConnect</p>
            ";
        }

        $mail->send();

    } catch (Exception $e) {
        error_log("Status update email failed for application $id: " . $mail->ErrorInfo);
    }
}

// ================= REDIRECT BACK TO WHEREVER THIS CAME FROM =================
$referer = $_SERVER['HTTP_REFERER'] ?? 'applications.php';
header("Location: " . $referer);
exit();