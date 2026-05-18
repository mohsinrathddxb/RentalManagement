<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

api_require_admin();
$input = api_get_json_input();
$author = is_username(isset($input['author']) ? (string) $input['author'] : '');
$title = uncrack(isset($input['title']) ? (string) $input['title'] : '');
$content = uncrack(isset($input['content']) ? (string) $input['content'] : '');
$date = date('Y-m-d H:i:s');

if ($title === '' || $content === '') {
    api_json(['ok' => false, 'message' => 'Title and content are required.'], 422);
}

$safeAuthor = mysqli_real_escape_string($connection, $author);
$safeTitle = mysqli_real_escape_string($connection, $title);
$safeContent = mysqli_real_escape_string($connection, $content);
$safeDate = mysqli_real_escape_string($connection, $date);
$inserted = mysqli_query($connection, "INSERT INTO `posts` (`author`, `title`, `content`, `date`) VALUES ('$safeAuthor', '$safeTitle', '$safeContent', '$safeDate')");
if (!$inserted) {
    api_json(['ok' => false, 'message' => 'Post could not be created.'], 500);
}

api_json(['ok' => true, 'id' => (int) mysqli_insert_id($connection)]);
