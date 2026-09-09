<?php
declare(strict_types=1);

function redirectToError(int $status = 500, string $reason = ''): void
{
    $status = in_array($status, [403, 404, 500, 503], true) ? $status : 500;
    while (ob_get_level() > 0) {
        if (!ob_end_clean()) break;
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script, '/api/') !== false) {
        http_response_code($status);
        if ($status === 503) header('Retry-After: 300');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unable to process the request.']);
        exit;
    }
    $directory = str_replace('\\', '/', dirname($script));
    $base = in_array($directory, ['', '.', '/'], true) ? '' : rtrim($directory, '/');
    $target = $base . '/error.php?' . http_build_query(['status' => $status, 'reason' => $reason]);
    if (!headers_sent()) {
        header('Location: ' . $target, true, 303);
    } else {
        echo '<p>処理に失敗しました。<a href="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '">エラー画面へ</a></p>';
    }
    exit;
}

if (PHP_SAPI !== 'cli') {
    // Keep redirects possible even after rendering starts.
    ob_start();
    set_exception_handler(static function (Throwable $exception): void {
        error_log((string) $exception);
        redirectToError(500);
    });
    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            redirectToError(500);
        }
    });
}
