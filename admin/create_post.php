<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';
$tag = $_POST['tag'] ?? 'latest';

$imageName = null;

// ✅ IMAGE UPLOAD
if (!empty($_FILES['image']['name'])) {

    $targetDir = "../uploads/";

    // AUTO CREATE FOLDER
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $imageName = time() . "_" . basename($_FILES["image"]["name"]);
    $targetFile = $targetDir . $imageName;

    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
        die("Image upload failed.");
    }
}

// INSERT POST
$stmt = $conn->prepare("
    INSERT INTO posts (title, content, tag, image, created_at)
    VALUES (?, ?, ?, ?, NOW())
");

$stmt->bind_param("ssss", $title, $content, $tag, $imageName);
$stmt->execute();

header("Location: dashboard.php?msg=Post created");
exit();
?>