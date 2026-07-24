<?php
declare(strict_types=1);
require_once is_file(dirname(__DIR__) . '/src/bootstrap.php') ? dirname(__DIR__) . '/src/bootstrap.php' : dirname(__DIR__, 2) . '/src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);
try {
    echo json_encode(array_values(array_filter(data('students'), fn(array $s): bool => (bool) ($s['is_active'] ?? false))), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load student data.'], JSON_UNESCAPED_UNICODE);
}
