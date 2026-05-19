<?php
session_start();

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $application_id = $_POST['application_id'] ?? 0;
    $response = $_POST['response'] ?? '';

    if ($application_id && in_array($response, ['Accepted', 'Declined'])) {

        $stmt = $conn->prepare("
            UPDATE applications
            SET 
                response = ?,
                responded_at = NOW()
            WHERE application_id = ?
        ");

        $stmt->bind_param("si", $response, $application_id);

        if ($stmt->execute()) {
            header("Location: my_applications.php?msg=Response submitted!");
            exit();
        } else {
            echo "Error: " . $stmt->error;
        }

    } else {
        echo "Invalid data.";
    }
}
?>