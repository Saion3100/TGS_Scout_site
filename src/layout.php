<?php
declare(strict_types=1);

require_once __DIR__ . '/routes.php';

redirectLegacyRoute();

function url(string $path = '', array $query = []): string
{
    static $basePath;

    if ($basePath === null) {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $directory = str_replace('\\', '/', dirname($scriptName));
        $basePath = in_array($directory, ['', '.', '/'], true) ? '' : rtrim($directory, '/');
    }

    return $basePath . '/' . routePath($path, $query);
}

function assetUrl(string $path): string
{
    $relativePath = ltrim($path, '/');
    $localPath = dirname(__DIR__) . '/public/assets/' . $relativePath;
    $serverPath = dirname(__DIR__) . '/assets/' . $relativePath;
    $filePath = is_file($localPath) ? $localPath : $serverPath;
    $version = is_file($filePath) ? (string) filemtime($filePath) : '1';
    return url('assets/' . $relativePath) . '?v=' . rawurlencode($version);
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function renderBackToTop(): void
{
    ?>
    <a class="back-to-top" href="#page-top"><span aria-hidden="true">↑</span> ページトップへ</a>
    <?php
}

function renderHeader(string $title = '', string $current = ''): void
{
    $fullTitle = $title ? $title . ' | TGS SCOUT 2026' : 'TGS SCOUT 2026';
    ?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="theme-color" content="#ffffff">
    <title><?= e($fullTitle) ?></title>
    <link rel="stylesheet" href="<?= e(assetUrl('style.css')) ?>">
</head>
<body id="page-top">
<?php renderBackToTop(); ?>
<header class="site-header">
    <div class="brand-group">
        <a class="school-logo-link" href="https://www.itc.ac.jp/" target="_blank" rel="noopener noreferrer" aria-label="国際理工カレッジ公式サイト（新しいタブで開く）">
            <img class="brand-logo" src="<?= e(url('assets/KRClogo.jpg')) ?>" alt="国際理工カレッジ">
        </a>
        <div class="brand-stack">
            <span class="brand-audience">国際理工カレッジ関係者向け</span>
            <a class="brand" href="<?= e(url()) ?>" aria-label="TGS Scout ホーム">
                <span class="brand-mark">TGS</span><span>SCOUT</span><small>2026</small>
            </a>
        </div>
    </div>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="global-nav">MENU</button>
    <nav id="global-nav" class="global-nav" aria-label="メインナビゲーション">
        <a class="<?= $current === 'teams' ? 'is-current' : '' ?>" href="<?= e(url('teams.php')) ?>">作品を探す</a>
        <a class="<?= $current === 'students' ? 'is-current' : '' ?>" href="<?= e(url('students.php')) ?>">学生を探す</a>
        <a class="<?= $current === 'guide' ? 'is-current' : '' ?>" href="<?= e(url('guide.php')) ?>">企業の方へ</a>
        <a class="nav-contact <?= $current === 'contact' ? 'is-current' : '' ?>" href="<?= e(url('contact.php')) ?>">学校へ問い合わせ</a>
    </nav>
</header>
<main>
<?php
}

function renderFooter(): void
{
    ?>
</main>
<footer class="site-footer">
    <div>
        <a class="brand brand-footer" href="<?= e(url()) ?>"><span class="brand-mark">TGS</span><span>SCOUT</span><small>2026</small></a>
        <p>ゲームを遊ぶだけで終わらせない。<br>つくった学生の技術と実績へ、その場でつながる。</p>
    </div>
    <div class="footer-links">
        <a href="<?= e(url('teams.php')) ?>">出展作品</a>
        <a href="<?= e(url('students.php')) ?>">学生一覧</a>
        <a href="<?= e(url('guide.php')) ?>">企業向け案内</a>
        <a href="<?= e(url('contact.php')) ?>">お問い合わせ</a>
        <a href="<?= e(url('privacy.php')) ?>">プライバシーポリシー</a>
    </div>
    <small>© 2026 TGS SCOUT PROJECT</small>
</footer>
<script src="<?= e(assetUrl('app.js')) ?>"></script>
</body>
</html>
<?php
}

