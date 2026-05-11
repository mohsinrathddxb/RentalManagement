<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../admin/functions/db.php';

$post = null;
$comments = [];
$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($postId) {
    $statement = mysqli_prepare($connection, 'SELECT id, author, title, content, date FROM posts WHERE id = ?');
    mysqli_stmt_bind_param($statement, 'i', $postId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $post = mysqli_fetch_assoc($result);

    if ($post) {
        $post['id'] = (int) $post['id'];

        $commentsStatement = mysqli_prepare($connection, 'SELECT name, comment, date FROM comments WHERE blogid = ? ORDER BY id DESC');
        mysqli_stmt_bind_param($commentsStatement, 'i', $postId);
        mysqli_stmt_execute($commentsStatement);
        $commentsResult = mysqli_stmt_get_result($commentsStatement);

        while ($row = mysqli_fetch_assoc($commentsResult)) {
            $comments[] = $row;
        }
    }
}

echo json_encode([
    'post' => $post,
    'comments' => $comments,
]);
