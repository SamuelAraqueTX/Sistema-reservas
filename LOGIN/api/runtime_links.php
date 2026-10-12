<?php
header('Content-Type: application/json');

$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'runtime_links.json';

if (!file_exists($path)) {
    echo json_encode([
        'success' => true,
        'local_base' => 'http://127.0.0.1:8080',
        'public_base' => '',
        'updated_at' => ''
    ]);
    exit;
}

$content = file_get_contents($path);
$data = json_decode($content, true);

if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Archivo runtime_links inválido'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'local_base' => $data['local_base'] ?? 'http://127.0.0.1:8080',
    'public_base' => $data['public_base'] ?? '',
    'updated_at' => $data['updated_at'] ?? ''
]);
