<?php
header('Content-Type: application/json; charset=utf-8');

$_POST = $_POST ?? [];
$_FILES = $_FILES ?? [];

$response = [
    'post_received' => array_keys($_POST),
    'files_received' => array_keys($_FILES),
    'files_details' => []
];

foreach ($_FILES as $key => $file) {
    $response['files_details'][$key] = [
        'name' => $file['name'],
        'type' => $file['type'],
        'size' => $file['size'],
        'error' => $file['error'],
        'tmp_name' => $file['tmp_name']
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
