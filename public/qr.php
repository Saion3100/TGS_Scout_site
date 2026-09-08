<?php
declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';

$code = trim((string) ($_GET['code'] ?? ''));
$record = $code !== '' ? findById(data('qr_codes'), $code) : null;

if (preg_match('/^student-(.+)$/', $code, $matches) === 1) {
    $student = findById(data('students'), $matches[1]);
    if ($student === null || !isStudentPublic($student)) $record = null;
    if ($record === null && $student !== null && isStudentPublic($student)) {
        $record = [
            'target_url' => absoluteUrl('student_detail.php', ['id' => $matches[1]]),
            'is_active' => true,
        ];
    }
}

if ($record === null && preg_match('/^team-(.+)$/', $code, $matches) === 1) {
    $team = findById(data('teams'), $matches[1]);
    if ($team !== null) {
        $record = [
            'target_url' => absoluteUrl('team_detail.php', ['id' => $matches[1]]),
            'is_active' => true,
        ];
    }
}

if ($record === null || empty($record['is_active'])) {
    redirectToError(404, 'qr');
}

$target = (string) ($record['target_url'] ?? '');
if (filter_var($target, FILTER_VALIDATE_URL) === false || preg_match('~^https?://~i', $target) !== 1) {
    redirectToError(500, 'qr');
}

header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Location: ' . $target, true, 302);
exit;
