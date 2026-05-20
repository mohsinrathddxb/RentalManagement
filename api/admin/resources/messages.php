<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_admin();

$result = mysqli_query($connection, 'SELECT * FROM `contacts` ORDER BY `date` DESC, `id` DESC');
$items = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = [
            'id' => (int) $row['id'],
            'names' => isset($row['names']) ? (string) $row['names'] : '',
            'email' => isset($row['email']) ? (string) $row['email'] : '',
            'message' => isset($row['message']) ? (string) $row['message'] : '',
            'date' => isset($row['date']) ? (string) $row['date'] : '',
        ];
    }
}

api_json([
    'ok' => true,
    'items' => $items,
]);
