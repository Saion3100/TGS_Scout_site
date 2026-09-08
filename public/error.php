<?php
declare(strict_types=1);

// Share the site layout without loading application data or error handlers.
require_once is_file(__DIR__ . '/src/layout.php') ? __DIR__ . '/src/layout.php' : dirname(__DIR__) . '/src/layout.php';
$status = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_INT);
$status = in_array($status, [403, 404, 500], true) ? $status : 500;
$reason = is_string($_GET['reason'] ?? null) ? $_GET['reason'] : '';
$titles = [403 => 'このページにはアクセスできません', 404 => 'ページが見つかりません', 500 => '処理中にエラーが発生しました'];
$title = $titles[$status];
$message = $status === 500 ? '時間をおいて、もう一度お試しください。' : 'URLをご確認いただくか、下のボタンから目的のページをお探しください。';
if ($status === 404) {
    $details = ['student' => '指定された学生は存在しないか、現在公開されていません。', 'team' => '指定された作品は見つかりませんでした。', 'qr' => 'このQRコードは無効か、登録されていません。'];
    $message = $details[$reason] ?? $message;
}
http_response_code($status);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
renderHeader($title);
?>
<section class="error-page" aria-labelledby="error-title">
    <div class="error-card">
        <p class="error-code"><?= $status ?></p>
        <h1 id="error-title"><?= e($title) ?></h1>
        <p class="error-message"><?= e($message) ?></p>
        <nav class="error-actions" aria-label="ページを探す">
            <a class="button button-primary" href="<?= e(url()) ?>">トップへ戻る</a>
            <a class="button" href="<?= e(url('students.php')) ?>">学生一覧へ</a>
            <a class="button" href="<?= e(url('teams.php')) ?>">作品一覧へ</a>
        </nav>
    </div>
</section>
<?php renderFooter(); ?>
