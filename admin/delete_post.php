<?php
$conn = new mysqli("localhost", "root", "", "campusconnect");

$id = $_POST['id'] ?? 0;

// OPTIONAL: delete image file
$res = $conn->query("SELECT image FROM posts WHERE post_id=$id");
$data = $res->fetch_assoc();

if (!empty($data['image']) && file_exists("../uploads/".$data['image'])) {
    unlink("../uploads/".$data['image']);
}

// DELETE POST
$conn->query("DELETE FROM posts WHERE post_id=$id");

header("Location: dashboard.php?msg=Post deleted");
exit();