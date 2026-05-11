<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../admin/functions/db.php';

$posts = [];
$sql = 'SELECT id, author, title, content, date FROM posts ORDER BY id DESC';
$query = mysqli_query($connection, $sql);

if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        $row['id'] = (int) $row['id'];
        $row['comments_count'] = 0;
        $posts[] = $row;
    }
}

echo json_encode(['posts' => $posts]);
