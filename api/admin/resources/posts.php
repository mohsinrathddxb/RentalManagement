<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_admin();

$result = mysqli_query($connection, 'SELECT * FROM `posts` ORDER BY `date` DESC, `id` DESC');
$items = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = [
            'id' => (int) $row['id'],
            'title' => isset($row['title']) ? (string) $row['title'] : '',
            'content' => isset($row['content']) ? (string) $row['content'] : '',
            'date' => isset($row['date']) ? (string) $row['date'] : '',
        ];
    }
}

api_json(['ok' => true, 'items' => $items]);
