<?php
declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';

$code = trim((string) ($_GET['code'] ?? ''));
$record = $code !== '' ? findById(data('qr_codes'), $code) : null;

if ($record === null && preg_match('/^student-(.+)$/', $code, $matches) === 1) {
    $student = findById(data('students'), $matches[1]);
    if ($student !== null && !empty($student['is_active'])) {
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
    http_response_code(404);
    renderHeader('QRコードが見つかりません');
    ?>
    <section class="page-hero compact"><div><p class="section-number">QR CODE</p><h1>リンクを開けません</h1><p>このQRコードは無効か、登録されていません。</p></div></section>
    <?php renderFooter();
    exit;
}

$target = (string) ($record['target_url'] ?? '');
if (filter_var($target, FILTER_VALIDATE_URL) === false || preg_match('~^https?://~i', $target) !== 1) {
    http_response_code(500);
    exit('Invalid QR destination.');
}

header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Location: ' . $target, true, 302);
exit;
