<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_admin();

$sql = "
    SELECT c.*, p.`title` AS `post_title`
    FROM `comments` c
    LEFT JOIN `posts` p ON c.`blogid` = p.`id`
    ORDER BY c.`date` DESC, c.`id` DESC
";

$result = mysqli_query($connection, $sql);
$items = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = [
            'id' => (int) $row['id'],
            'blogid' => isset($row['blogid']) ? (int) $row['blogid'] : 0,
            'name' => isset($row['name']) ? (string) $row['name'] : '',
            'comment' => isset($row['comment']) ? (string) $row['comment'] : '',
            'date' => isset($row['date']) ? (string) $row['date'] : '',
            'post_title' => isset($row['post_title']) ? (string) $row['post_title'] : '',
        ];
    }
}

api_json(['ok' => true, 'items' => $items]);
