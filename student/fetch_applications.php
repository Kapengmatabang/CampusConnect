<?php
session_start();

// ✅ PROTECT PAGE
if (!isset($_SESSION["user"])) {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

$student_id = $_SESSION["user"];

// ✅ SAFE QUERY
$stmt = $conn->prepare("
    SELECT o.org_name, o.org_id, a.*
    FROM applications a
    JOIN organizations o ON a.org_id = o.org_id
    WHERE a.student_id = ?
    ORDER BY a.applied_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$apps = $stmt->get_result();

while ($a = $apps->fetch_assoc()) {

    $statusClass = strtolower($a['status']);

    echo "<tr>
        <td>{$a['org_name']}</td>

        <td class='$statusClass'>
            {$a['status']}";

    // ✅ NOTIFICATION
    if (!empty($a['notification'])) {
        echo "<div style='color:green; font-size:12px; margin-top:5px;'>
                🔔 {$a['notification']}
              </div>";
    }

    echo "</td>

        <td>";

    // ✅ INTERVIEW INFO ONLY
    if (!empty($a['interview_date'])) {

        echo "<span class='interview'>
                📅 {$a['interview_date']}<br>";

        if (!empty($a['interview_link'])) {
            echo "🔗 <a href='{$a['interview_link']}' target='_blank'>Join Meeting</a>";
        }

        echo "</span>";

    } else {
        echo "-";
    }

    echo "</td>

        <td>{$a['applied_at']}</td>

        <td>";

    // ✅ ONLY CANCEL BUTTON
    if ($a['status'] == 'Pending') {
        echo "
        <form method='POST' action='cancel_application.php'
              onsubmit=\"return confirm('Cancel application?');\">
            <input type='hidden' name='org_id' value='{$a['org_id']}'>
            <button class='cancel-btn'>Cancel</button>
        </form>";
    } else {
        echo "-";
    }

    echo "</td></tr>";
}
?>