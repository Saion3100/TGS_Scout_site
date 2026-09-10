<?php
declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
try {
    $student = findById(data('students'), (string) ($_GET['id'] ?? ''));
    session_start();
    $admin = !empty($_SESSION['admin_authenticated']);
    session_write_close();
    if (!$student || (empty($student['is_active']) && !$admin) || empty($student['photo_drive_file_id'])) {
        http_response_code(404); exit;
    }
    $image = StudentPhoto::download((string) $student['photo_drive_file_id']);
    header('Content-Type: image/jpeg');
    echo $image;
} catch (Throwable $error) {
    error_log('Student photo: ' . $error->getMessage());
    http_response_code(502);
}
