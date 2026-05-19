<?php
$conn = new mysqli("localhost", "root", "", "campusconnect");

$id = $_POST['post_id'];
$title = $_POST['title'];
$content = $_POST['content'];
$tag = $_POST['tag'];

$stmt = $conn->prepare("
    UPDATE posts
    SET title=?, content=?, tag=?
    WHERE post_id=?
");
$stmt->bind_param("sssi", $title, $content, $tag, $id);
$stmt->execute();

header("Location: dashboard.php?msg=Post updated");
exit();