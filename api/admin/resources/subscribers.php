<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_admin();

$result = mysqli_query($connection, 'SELECT * FROM `subscribers` ORDER BY `date` DESC');
$items = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = [
            'email' => isset($row['email']) ? (string) $row['email'] : '',
            'date' => isset($row['date']) ? (string) $row['date'] : '',
        ];
    }
}

api_json([
    'ok' => true,
    'items' => $items,
]);
