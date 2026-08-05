<?php
declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';

session_start();
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
$configuredPassword = (string) getenv('TGS_ADMIN_PASSWORD');
$error = '';
$notice = '';

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . url('admin.php'));
    exit;
}
if (empty($_SESSION['admin_authenticated']) && isset($_POST['password'])) {
    if ($configuredPassword !== '' && hash_equals($configuredPassword, (string) $_POST['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: ' . url('admin.php'));
        exit;
    }
    $error = 'パスワードが正しくありません。';
}

if (empty($_SESSION['admin_authenticated'])) {
    renderHeader('データ管理'); ?>
    <section class="admin-shell"><div class="admin-panel"><p class="section-number">ADMIN</p><h1>データ管理</h1>
    <?php if ($configuredPassword === ''): ?><p class="admin-alert">サーバー環境変数 <code>TGS_ADMIN_PASSWORD</code> が未設定のため、管理画面を利用できません。</p>
    <?php else: ?><form method="post" class="admin-login"><label>管理パスワード<input type="password" name="password" required autocomplete="current-password"></label><?php if ($error): ?><p class="admin-error"><?= e($error) ?></p><?php endif; ?><button class="button button-primary">ログイン</button></form><?php endif; ?>
    </div></section><?php renderFooter(); exit;
}

$type = (string) ($_GET['type'] ?? $_POST['type'] ?? 'students');
if (!in_array($type, ['students', 'teams'], true)) { $type = 'students'; }
$items = repository($type)->all();
$selectedId = (string) ($_GET['id'] ?? '');
$isNew = isset($_GET['new']);
$selected = $isNew ? ['id' => ''] : ($selectedId !== '' ? findById($items, $selectedId) : null);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_json'])) {
    try {
        if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('セッションの有効期限が切れました。再読み込みしてください。');
        }
        $record = json_decode((string) $_POST['record_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($record)) {
            throw new InvalidArgumentException('1件分のJSONオブジェクトを入力してください。');
        }
        repository($type)->saveById($record, ($_POST['mode'] ?? '') === 'new');
        header('Location: ' . url('admin.php') . '?type=' . rawurlencode($type) . '&id=' . rawurlencode((string) $record['id']) . '&saved=1');
        exit;
    } catch (Throwable $exception) {
        $error = $exception instanceof JsonException ? 'JSONの形式が正しくありません：' . $exception->getMessage() : $exception->getMessage();
        $selected = json_decode((string) $_POST['record_json'], true) ?: ['id' => ''];
        $isNew = ($_POST['mode'] ?? '') === 'new';
    }
}
if (isset($_GET['saved'])) { $notice = '保存しました。公開ページにも反映されています。'; }
renderHeader('データ管理');
?>
<section class="admin-shell">
  <div class="admin-toolbar"><div><p class="section-number">ADMIN</p><h1>データ管理</h1></div><form method="post"><button class="button button-outline" name="logout" value="1">ログアウト</button></form></div>
  <nav class="admin-tabs"><a class="<?= $type === 'students' ? 'is-active' : '' ?>" href="<?= e(url('admin.php')) ?>?type=students">学生</a><a class="<?= $type === 'teams' ? 'is-active' : '' ?>" href="<?= e(url('admin.php')) ?>?type=teams">作品</a></nav>
  <?php if ($notice): ?><p class="admin-success"><?= e($notice) ?></p><?php endif; ?><?php if ($error): ?><p class="admin-error"><?= e($error) ?></p><?php endif; ?>
  <div class="admin-grid"><aside class="admin-list"><a class="button button-primary" href="<?= e(url('admin.php')) ?>?type=<?= e($type) ?>&new=1">新規追加</a><?php foreach ($items as $item): ?><a class="<?= !$isNew && $selectedId === ($item['id'] ?? '') ? 'is-current' : '' ?>" href="<?= e(url('admin.php')) ?>?type=<?= e($type) ?>&id=<?= e($item['id'] ?? '') ?>"><strong><?= e($item[$type === 'students' ? 'name' : 'game_name'] ?? '(名称なし)') ?></strong><small><?= e($item['id'] ?? '') ?></small></a><?php endforeach; ?></aside>
    <div class="admin-editor"><?php if ($selected !== null): ?><h2><?= $isNew ? '新規追加' : '編集' ?></h2><p>項目名を変えず、値を編集してください。配列は <code>["項目1", "項目2"]</code> の形式です。</p><form method="post"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="mode" value="<?= $isNew ? 'new' : 'edit' ?>"><textarea name="record_json" rows="28" spellcheck="false" required><?= e(json_encode($selected, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></textarea><button class="button button-primary">保存する</button></form><?php else: ?><p>左の一覧から編集対象を選ぶか、「新規追加」を押してください。</p><?php endif; ?></div>
  </div>
</section>
<?php renderFooter();
