<?php
declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';
session_start();
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
requireAdminUser();

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_SESSION['csrf_token'] ?? null) || $_SESSION['csrf_token'] === '' || !is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            redirectToError(403, 'csrf');
        }
        if (!is_string($_POST['id'] ?? null) || !is_string($_POST['booth_no'] ?? null)) {
            throw new InvalidArgumentException('作品と試遊台番号を正しく指定してください。');
        }
        if (isset($_POST['register_exhibited']) && trim($_POST['booth_no']) === '') {
            throw new InvalidArgumentException('出展作品を登録するには試遊台番号を入力してください。');
        }
        $repo = repository('teams');
        $updatedTeam = findById($repo->all(), $_POST['id']);
        if ($updatedTeam === null) throw new InvalidArgumentException('作品が見つかりません。');
        if (isset($_POST['register_exhibited']) && trim((string) ($updatedTeam['booth_no'] ?? '')) !== '') {
            throw new InvalidArgumentException('このチームは既に出展作品に登録されています。番号は出展作品一覧から変更してください。');
        }
        $updatedTeam['booth_no'] = trim($_POST['booth_no']);
        $repo->saveById($updatedTeam, false);
        header('Location: ' . url('exhibited_admin.php') . '?id=' . rawurlencode((string) $updatedTeam['id']) . '&saved=1');
        exit;
    } catch (Throwable $exception) {
        if (!($exception instanceof InvalidArgumentException)) {
            error_log((string) $exception);
            redirectToError(500);
        }
        $error = $exception->getMessage();
    }
}
$allTeams = data('teams');
$unregisteredTeams = array_values(array_filter($allTeams, static fn(array $team): bool => trim((string) ($team['booth_no'] ?? '')) === ''));
$teams = array_values(array_filter($allTeams, static fn(array $team): bool => trim((string) ($team['booth_no'] ?? '')) !== ''));
usort($teams, static fn(array $a, array $b): int => strnatcmp(trim((string) $a['booth_no']), trim((string) $b['booth_no'])) ?: strcmp((string) $a['id'], (string) $b['id']));

$isNew = isset($_GET['new']);
$selectedId = (string) ($_GET['id'] ?? ($error !== '' && !isset($_POST['register_exhibited']) ? ($_POST['id'] ?? '') : ''));
$selectedTeam = $selectedId !== '' ? findById($teams, $selectedId) : null;
if ($selectedTeam === null && !$isNew && $error === '') {
    $isNew = $unregisteredTeams && !$teams;
}
$postedBoothNo = $error !== '' && is_string($_POST['booth_no'] ?? null) ? $_POST['booth_no'] : null;
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>出展作品管理 | TGS SCOUT ADMIN</title>
    <link rel="stylesheet" href="<?= e(assetUrl('style.css')) ?>">
    <link rel="stylesheet" href="<?= e(assetUrl('qr-admin.css')) ?>">
</head>
<body id="page-top" class="admin-body">
<?php renderBackToTop(); ?>
<main class="admin-main"><section class="admin-shell">
    <div class="admin-toolbar">
        <div><p class="section-number">TGS SCOUT ADMIN</p><h1>出展作品管理</h1><p class="admin-lead">試遊台番号の登録・更新</p></div>
        <div class="admin-toolbar-actions"><a class="button button-outline" href="<?= e(url()) ?>">← 公開ページへ</a><form method="post" action="<?= e(url('admin.php')) ?>"><button class="button button-primary" name="logout" value="1">ログアウト</button></form></div>
    </div>
    <?php renderManagementTabs('exhibited'); ?>
    <?php if (isset($_GET['saved'])): ?><p class="admin-success">試遊台番号を保存しました。</p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="admin-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <div class="admin-grid">
        <aside class="admin-list">
            <div class="admin-list-head"><strong>出展作品</strong><span><?= count($teams) ?>件</span></div>
            <a class="button button-primary <?= $isNew ? 'is-current' : '' ?>" href="<?= e(url('exhibited_admin.php')) ?>?new=1">＋ 出展作品を登録</a>
            <?php foreach ($teams as $item): ?>
            <a class="<?= !$isNew && $selectedId === (string) $item['id'] ? 'is-current' : '' ?>" href="<?= e(url('exhibited_admin.php')) ?>?id=<?= e($item['id']) ?>"><span><strong><?= e($item['game_name']) ?></strong><small><?= e($item['team_name']) ?></small></span><span class="admin-status is-public"><?= e(trim((string) $item['booth_no'])) ?></span></a>
            <?php endforeach; ?>
        </aside>
        <div class="admin-editor">
        <?php if ($isNew): ?>
            <div class="admin-editor-head"><div><p class="section-number">NEW EXHIBITED WORK</p><h2>出展作品を登録</h2></div><span>必須項目 <b>*</b></span></div>
            <form method="post" class="admin-student-form">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <fieldset><legend><span>01</span>登録内容</legend>
                    <p class="form-note">出展作品に未登録のチームのみ選択できます。同じ番号を複数作品に設定できます。番号は公開ページには表示されません。</p>
                    <div class="admin-form-grid">
                        <label class="admin-span-2"><span>チーム名 <b>*</b></span>
                            <select data-searchable-select name="id" required <?= !$unregisteredTeams ? 'disabled' : '' ?>>
                                <option value=""><?= $unregisteredTeams ? 'チームを選択してください' : '登録できるチームはありません' ?></option>
                                <?php foreach ($unregisteredTeams as $candidate): ?>
                                <option value="<?= e($candidate['id']) ?>" <?= isset($_POST['register_exhibited']) && ($_POST['id'] ?? null) === (string) $candidate['id'] ? 'selected' : '' ?>><?= e($candidate['team_name']) ?>（<?= e($candidate['game_name']) ?>）</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="admin-span-2"><span>試遊台番号（重複可） <b>*</b></span><input name="booth_no" required value="<?= e(isset($_POST['register_exhibited']) && $postedBoothNo !== null ? $postedBoothNo : '') ?>"></label>
                    </div>
                </fieldset>
                <div class="admin-form-actions"><p>登録すると出展作品一覧に追加されます。</p><button class="button button-primary" type="submit" name="register_exhibited" value="1" <?= !$unregisteredTeams ? 'disabled' : '' ?>>出展作品を登録する →</button></div>
            </form>
        <?php elseif ($selectedTeam !== null): ?>
            <div class="admin-editor-head"><div><p class="section-number">EDIT EXHIBITED WORK</p><h2><?= e($selectedTeam['game_name']) ?></h2></div><span>必須項目 <b>*</b></span></div>
            <form method="post" class="admin-student-form">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" value="<?= e($selectedTeam['id']) ?>">
                <fieldset><legend><span>01</span>試遊台番号</legend>
                    <p class="form-note">試遊台番号だけを変更・保存できます。同じ番号を複数作品に設定できます。空欄で保存すると出展作品一覧から外れます。番号は公開ページには表示されません。</p>
                    <div class="admin-form-grid">
                        <label class="admin-span-2"><span>チーム名</span><input value="<?= e($selectedTeam['team_name']) ?>" readonly></label>
                        <label class="admin-span-2"><span>試遊台番号（重複可）</span><input name="booth_no" value="<?= e($postedBoothNo !== null ? $postedBoothNo : trim((string) $selectedTeam['booth_no'])) ?>"></label>
                    </div>
                </fieldset>
                <div class="admin-form-actions"><p><a class="text-link" href="<?= e(url('teams_admin.php') . '?id=' . rawurlencode((string) $selectedTeam['id'])) ?>">作品を編集 →</a></p><button class="button button-primary" type="submit">番号を保存する →</button></div>
            </form>
        <?php else: ?>
            <div class="admin-empty"><span>EXHIBITED WORKS</span><h2>編集する出展作品を選択</h2><p>左の一覧から選ぶか、「＋ 出展作品を登録」で新しく追加してください。</p></div>
        <?php endif; ?>
        </div>
    </div>
</section></main>
<script src="<?= e(assetUrl('searchable-select.js')) ?>"></script>
</body>
</html>
