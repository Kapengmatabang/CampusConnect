<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

// ====== CONFIG (edit these) ======
define('SMTP_USER', 'campusconnect.plmun@gmail.com');
define('SMTP_PASS', 'ogcvxwzsbcfyjgzo');   // spaces are removed automatically
define('SMTP_FROM_NAME', 'CampusConnect Admin');
define('MAIL_LOG', __DIR__ . '/mail_log.txt');
// =================================

function mailLog($line) {
    @file_put_contents(MAIL_LOG, '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND);
}

function sendMail($toEmail, $toName, $subject, $body) {
    $GLOBALS['mail_error'] = '';
    $toEmail = trim((string)$toEmail);

    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $GLOBALS['mail_error'] = "Invalid or empty recipient: '" . $toEmail . "'";
        mailLog("FAILED  to=[$toEmail] reason=" . $GLOBALS['mail_error']);
        return false;
    }

    $mail  = new PHPMailer(true);
    $trace = '';

    try {
        $mail->SMTPDebug   = 2;
        $mail->Debugoutput = function ($str, $level) use (&$trace) {
            $trace .= trim($str) . "\n";
        };

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = str_replace(' ', '', SMTP_PASS);
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;

        // Local XAMPP certificate fix (remove when you go live)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->Sender = SMTP_USER;
        $mail->addReplyTo(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = trim(strip_tags($body));

        $mail->send();
        mailLog("SENT    to=[$toEmail] subject=[$subject]");
        return true;

    } catch (Exception $e) {
        $GLOBALS['mail_error'] = $mail->ErrorInfo;
        mailLog("FAILED  to=[$toEmail] reason=" . $mail->ErrorInfo);
        mailLog("SMTP trace:\n" . $trace);
        return false;
    }
}

/**
 * Ready-made decision email.
 * $status: 'Accepted' or 'Rejected' (anything else is sent as a status update)
 */
function sendDecisionEmail($toEmail, $toName, $orgName, $role, $status, $remarks = '') {
    $n = htmlspecialchars($toName);
    $o = htmlspecialchars($orgName);
    $r = htmlspecialchars($role);
    $s = htmlspecialchars($status);
    $remarksHtml = $remarks !== '' ? '<p><strong>Remarks:</strong> ' . nl2br(htmlspecialchars($remarks)) . '</p>' : '';

    if (strcasecmp($status, 'Accepted') === 0) {
        $subject = "CampusConnect: Congratulations, you were accepted to $orgName";
        $line    = "We are happy to inform you that your application for <strong>$r</strong> in <strong>$o</strong> has been <strong style=\"color:#16a34a\">ACCEPTED</strong>.";
    } elseif (strcasecmp($status, 'Rejected') === 0) {
        $subject = "CampusConnect: Update on your application to $orgName";
        $line    = "Thank you for applying for <strong>$r</strong> in <strong>$o</strong>. After review, your application was <strong style=\"color:#dc2626\">not selected</strong> this time.";
    } else {
        $subject = "CampusConnect: Application status update ($status)";
        $line    = "Your application for <strong>$r</strong> in <strong>$o</strong> is now: <strong>$s</strong>.";
    }

    $body = "<div style=\"font-family:Arial,sans-serif;max-width:560px\">"
          . "<h2>CampusConnect</h2>"
          . "<p>Hi $n,</p><p>$line</p>"
          . $remarksHtml
          . "<p>Thank you,<br>CampusConnect Admin</p></div>";

    return sendMail($toEmail, $toName, $subject, $body);
}