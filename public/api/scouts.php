<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/JsonRepository.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $repository = new JsonRepository(dirname(__DIR__, 2) . '/data/scouts.json');
    echo json_encode($repository->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load scouts.'], JSON_UNESCAPED_UNICODE);
}
