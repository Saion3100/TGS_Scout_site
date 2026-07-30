<?php
declare(strict_types=1);

require_once __DIR__ . '/JsonRepository.php';

const DATA_DIR = __DIR__ . '/../data';

function url(string $path = ''): string
{
    static $basePath;

    if ($basePath === null) {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $directory = str_replace('\\', '/', dirname($scriptName));
        $basePath = in_array($directory, ['', '.', '/'], true) ? '' : rtrim($directory, '/');
    }

    return $basePath . ($path === '' || $path === '/' ? '/' : '/' . ltrim($path, '/'));
}

function data(string $name): array
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $cache[$name] = (new JsonRepository(DATA_DIR . '/' . $name . '.json'))->all();
    }
    return $cache[$name];
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function firstCharacter(string $value): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 1, 'UTF-8');
    }

    return preg_match('/^./us', $value, $matches) === 1 ? $matches[0] : '';
}

function findById(array $items, string $id): ?array
{
    foreach ($items as $item) {
        if (($item['id'] ?? null) === $id) {
            return $item;
        }
    }
    return null;
}

function teamMembers(string $teamId): array
{
    $students = data('students');
    $members = [];
    foreach (data('mapping') as $relation) {
        if ($relation['team_id'] !== $teamId) {
            continue;
        }
        $student = findById($students, $relation['student_id']);
        if ($student && ($student['is_active'] ?? false)) {
            $student['team_role'] = $relation['role'];
            $student['team_role_group'] = $relation['role_group'] ?? $student['role_ja'];
            $student['team_responsibility'] = $relation['responsibility'] ?? '';
            $members[] = $student;
        }
    }
    return $members;
}

function studentTeams(string $studentId): array
{
    $teams = data('teams');
    $result = [];
    foreach (data('mapping') as $relation) {
        if ($relation['student_id'] !== $studentId) {
            continue;
        }
        $team = findById($teams, $relation['team_id']);
        if ($team) {
            $team['student_role'] = $relation['role'];
            $result[] = $team;
        }
    }
    return $result;
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
    <meta name="theme-color" content="#e60012">
    <title><?= e($fullTitle) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
</head>
<body>
<header class="site-header">
    <div class="brand-group">
        <a class="school-logo-link" href="https://www.itc.ac.jp/" target="_blank" rel="noopener noreferrer" aria-label="国際理工カレッジ公式サイト（新しいタブで開く）">
            <img class="brand-logo" src="<?= e(url('assets/KRClogo.jpg')) ?>" alt="国際理工カレッジ">
        </a>
        <a class="brand" href="<?= e(url()) ?>" aria-label="TGS Scout ホーム">
            <span class="brand-mark">TGS</span><span>SCOUT</span><small>2026</small>
        </a>
    </div>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="global-nav">MENU</button>
    <nav id="global-nav" class="global-nav" aria-label="メインナビゲーション">
        <a class="<?= $current === 'teams' ? 'is-current' : '' ?>" href="<?= e(url('teams.php')) ?>">作品を探す</a>
        <a class="<?= $current === 'students' ? 'is-current' : '' ?>" href="<?= e(url('students.php')) ?>">学生を探す</a>
        <a class="<?= $current === 'roles' ? 'is-current' : '' ?>" href="<?= e(url('roles.php')) ?>">職種から探す</a>
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
        <a href="<?= e(url('roles.php')) ?>">職種別一覧</a>
        <a href="<?= e(url('guide.php')) ?>">企業向け案内</a>
        <a href="<?= e(url('contact.php')) ?>">お問い合わせ</a>
        <a href="<?= e(url('privacy.php')) ?>">プライバシーポリシー</a>
    </div>
    <small>© 2026 TGS SCOUT PROJECT</small>
</footer>
<script src="<?= e(url('assets/app.js')) ?>"></script>
</body>
</html>
<?php
}

function studentCard(array $student): void
{
    ?>
<a class="student-card" href="<?= e(url('student_detail.php')) ?>?id=<?= e($student['id']) ?>">
    <span class="card-index"><?= e(str_pad((string) (array_search($student, data('students'), true) + 1), 2, '0', STR_PAD_LEFT)) ?></span>
    <div class="portrait" aria-hidden="true"><span><?= e(firstCharacter($student['name'])) ?></span></div>
    <div class="student-card-body">
        <span class="role-label"><?= e($student['role_ja']) ?></span>
        <h3><?= e($student['name']) ?></h3>
        <p class="name-en"><?= e($student['name_en']) ?></p>
        <p class="student-meta"><?= e($student['grade'] ?? '') ?> / <?= e($student['graduation_year'] ?? '') ?></p>
        <div class="tag-list">
            <?php foreach (array_slice($student['skills'], 0, 3) as $skill): ?><span><?= e($skill) ?></span><?php endforeach; ?>
        </div>
    </div>
    <span class="circle-arrow" aria-hidden="true">↗</span>
</a>
<?php
}
