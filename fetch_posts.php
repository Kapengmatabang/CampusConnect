<?php
$conn = new mysqli("localhost", "root", "", "campusconnect");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM posts ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {

    while($row = $result->fetch_assoc()) {

        // SAFE VALUES (NO WARNINGS)
        $id      = $row['post_id'] ?? 0;
        $tag     = $row['tag'] ?? 'latest';
        $title   = $row['title'] ?? 'No Title';
        $content = $row['content'] ?? '';
        $image   = $row['image'] ?? '';
        $date    = $row['created_at'] ?? '';

        echo '
        <div class="timeline-item">

            <div class="tag '.$tag.'">'.strtoupper($tag).'</div>

            <h4>'.htmlspecialchars($title).'</h4>
            <p>'.nl2br(htmlspecialchars($content)).'</p>';

        // IMAGE DISPLAY (SAFE)
        if (!empty($image) && file_exists(__DIR__ . "/uploads/" . $image)) {
            echo '
            <img src="uploads/'.$image.'" alt="post image">
            ';
        }

        echo '
            <div class="post-date">'.$date.'</div>

        </div>';
    }

} else {
    echo "<p style='color:white;text-align:center;'>No posts yet.</p>";
}
?>