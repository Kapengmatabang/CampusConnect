<?php
$conn = new mysqli("localhost", "root", "", "campusconnect");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$title = $_POST['title'];
$content = $_POST['content'];
$tag = $_POST['tag'];

// IMAGE UPLOAD
$imageName = "";

if(isset($_FILES['image']) && $_FILES['image']['name'] != ""){
    $imageName = time() . "_" . $_FILES['image']['name'];
    move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $imageName);
}

// INSERT TO DATABASE
$sql = "INSERT INTO posts (title, content, tag, image)
        VALUES ('$title', '$content', '$tag', '$imageName')";

$conn->query($sql);

// REDIRECT BACK
header("Location: admin.php");
?>