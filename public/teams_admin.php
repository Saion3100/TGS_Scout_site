<?php
declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';
session_start();
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
if (empty($_SESSION['admin_authenticated'])) { header('Location: ' . url('admin.php')); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

function teamAdminText(string $name): string { return trim((string) ($_POST[$name] ?? '')); }
function teamAdminList(string $name): array { $parts=preg_split('/[\r\n,、]+/u',teamAdminText($name))?:[]; return array_values(array_unique(array_filter(array_map('trim',$parts)))); }
function teamAdminListText(array $values): string { return implode(PHP_EOL,array_map('strval',$values)); }

$repo=repository('teams'); $teams=$repo->all(); $error=''; $notice=''; $selectedId=(string)($_GET['id']??''); $team=$selectedId!==''?findById($teams,$selectedId):null;
$students = data('students');
$mappingRepo = repository('mapping');
$relations = $mappingRepo->all();
$memberValues = [];
foreach ($relations as $relation) {
    if ((string) $relation['team_id'] === $selectedId) $memberValues[(string) $relation['student_id']] = $relation;
}
$memberRows = array_values($memberValues);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_members'])) {
    try {
        if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) throw new InvalidArgumentException('セッションの有効期限が切れました。再読み込みしてください。');
        if ($team === null || teamAdminText('id') !== $selectedId) throw new InvalidArgumentException('編集対象の作品が見つかりません。');
        $postedMembers = $_POST['members'] ?? [];
        if (!is_array($postedMembers)) throw new InvalidArgumentException('メンバーの指定が正しくありません。');
        if (count($postedMembers) > 8) throw new InvalidArgumentException('プロジェクトメンバーは最大8名です。');
        $memberRows = [];
        foreach ($postedMembers as $values) {
            if (!is_array($values) || !is_string($values['student_id'] ?? null) || !is_string($values['role'] ?? null)) throw new InvalidArgumentException('メンバーの指定が正しくありません。');
            $memberRows[] = ['student_id' => trim($values['student_id']), 'role' => $values['role']];
        }
        $nextMembers = [];
        foreach ($memberRows as $values) {
            $studentId = $values['student_id'];
            if ($studentId === '') continue;
            if (findById($students, $studentId) === null) throw new InvalidArgumentException('登録済みの学生を選択してください。');
            if (isset($nextMembers[$studentId])) throw new InvalidArgumentException('同じチーム内で同じ学生を複数の枠に登録できません。兼任する役職は1つの欄に改行して入力してください。');
            $roles = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,、\/]+/u', $values['role']) ?: []), static fn(string $role): bool => $role !== '')));
            if (!$roles) throw new InvalidArgumentException('選択したメンバーの役職を入力してください。');
            $nextMembers[$studentId] = array_merge($memberValues[$studentId] ?? [], ['team_id' => $selectedId, 'student_id' => $studentId, 'role' => implode(' / ', $roles)]);
            unset($nextMembers[$studentId]['role_group']);
        }
        $memberValues = $nextMembers;
        $otherRelations = array_values(array_filter($relations, static fn(array $item): bool => (string) $item['team_id'] !== $selectedId));
        $mappingRepo->replaceAll(array_merge($otherRelations, array_values($memberValues)));
        header('Location: ' . url('teams_admin.php') . '?id=' . rawurlencode($selectedId) . '&members_saved=1#project-members');
        exit;
    } catch (Throwable $exception) {
        $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'メンバーを保存できませんでした。書き込み権限を確認してください。';
    }
}
if (isset($_GET['members_saved'])) $notice = 'プロジェクトメンバーを保存しました。';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_team'])) {
    try {
        if (!hash_equals((string)($_SESSION['csrf_token']??''),(string)($_POST['csrf_token']??''))) throw new RuntimeException('セッションの有効期限が切れました。再読み込みしてください。');
        $id=teamAdminText('id'); $existing=findById($teams,$id); if($existing===null)throw new InvalidArgumentException('編集対象の作品が見つかりません。');
        $required=['team_name'=>'チーム名','game_name'=>'作品名','genre'=>'ジャンル','players'=>'プレイ人数','engine'=>'使用エンジン','production_period'=>'制作期間','booth_no'=>'ブース番号','catchcopy'=>'キャッチコピー','description'=>'作品説明'];
        $values=[]; foreach($required as $key=>$label){$values[$key]=teamAdminText($key);if($values[$key]==='')throw new InvalidArgumentException($label.'を入力してください。');}
        $video=teamAdminText('video_url'); if($video!==''&&(filter_var($video,FILTER_VALIDATE_URL)===false||preg_match('~^https?://~i',$video)!==1))throw new InvalidArgumentException('動画URLを正しく入力してください。');
        $team=['id'=>$id,'team_name'=>$values['team_name'],'game_name'=>$values['game_name'],'genre'=>$values['genre'],'players'=>$values['players'],'platforms'=>teamAdminList('platforms'),'engine'=>$values['engine'],'production_period'=>$values['production_period'],'booth_no'=>$values['booth_no'],'catchcopy'=>$values['catchcopy'],'description'=>$values['description'],'highlights'=>teamAdminList('highlights'),'controls'=>teamAdminList('controls'),'screenshots'=>$existing['screenshots']??[],'theme'=>teamAdminText('theme')?:'red','video_url'=>$video];
        $repo->saveById($team,false);
        syncManagedQr('team-'.$id,'作品：'.$team['game_name'],absoluteUrl('team_detail.php',['id'=>$id]),true);
        header('Location: '.url('teams_admin.php').'?id='.rawurlencode($id).'&saved=1'); exit;
    } catch(Throwable $exception){$error=$exception->getMessage();}
}
if(isset($_GET['saved']))$notice='作品データとQRコードを保存しました。';
?>
<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>作品データ管理 | TGS SCOUT ADMIN</title><link rel="stylesheet" href="<?= e(assetUrl('style.css')) ?>"><link rel="stylesheet" href="<?= e(assetUrl('qr-admin.css')) ?>"></head><body class="admin-body"><main class="admin-main"><section class="admin-shell">
<div class="admin-toolbar"><div><p class="section-number">TGS SCOUT ADMIN</p><h1>作品データ管理</h1><p class="admin-lead">作品情報の更新とQRコード作成</p></div><div class="admin-toolbar-actions"><a class="button button-outline" href="<?= e(url()) ?>">← 公開ページへ</a><form method="post" action="<?= e(url('admin.php')) ?>"><button class="button button-primary" name="logout" value="1">ログアウト</button></form></div></div>
<nav class="admin-tabs" aria-label="管理データ"><a href="<?= e(url('admin.php')) ?>">学生</a><a href="<?= e(url('admin.php')) ?>?section=featured">注目学生</a><a class="is-active" aria-current="page" href="<?= e(url('teams_admin.php')) ?>">作品</a></nav>
<?php if($notice):?><p class="admin-success"><?=e($notice)?></p><?php endif;?><?php if($error):?><p class="admin-error" role="alert"><?=e($error)?></p><?php endif;?>
<div class="admin-grid"><aside class="admin-list"><div class="admin-list-head"><strong>登録作品</strong><span><?=count($teams)?>件</span></div><?php foreach($teams as $item):?><a class="<?= $selectedId===($item['id']??'')?'is-current':''?>" href="<?=e(url('teams_admin.php'))?>?id=<?=e($item['id'])?>"><span><strong><?=e($item['game_name'])?></strong><small><?=e($item['team_name'])?></small></span><span class="admin-status is-public">公開</span></a><?php endforeach;?></aside>
<div class="admin-editor"><?php if($team!==null):$teamQrUrl=managedQrUrl('team-'.$team['id']);?><div class="admin-editor-head"><div><p class="section-number">EDIT WORK</p><h2><?=e($team['game_name'])?></h2></div><span>必須項目 <b>*</b></span></div><form method="post" class="admin-student-form"><input type="hidden" name="csrf_token" value="<?=e($_SESSION['csrf_token'])?>"><input type="hidden" name="id" value="<?=e($team['id'])?>">
<fieldset><legend><span>01</span>基本情報</legend><div class="admin-form-grid"><label>作品名 <b>*</b><input name="game_name" value="<?=e($team['game_name'])?>" required></label><label>チーム名 <b>*</b><input name="team_name" value="<?=e($team['team_name'])?>" required></label><label>ジャンル <b>*</b><input name="genre" value="<?=e($team['genre'])?>" required></label><label>プレイ人数 <b>*</b><input name="players" value="<?=e($team['players'])?>" required></label><label>対応機種<textarea name="platforms" rows="3"><?=e(teamAdminListText($team['platforms']))?></textarea></label><label>使用エンジン <b>*</b><input name="engine" value="<?=e($team['engine'])?>" required></label><label>制作期間 <b>*</b><input name="production_period" value="<?=e($team['production_period'])?>" required></label><label>ブース番号 <b>*</b><input name="booth_no" value="<?=e($team['booth_no'])?>" required></label><label>テーマ<select name="theme"><?php foreach(['red','navy','yellow'] as $theme):?><option value="<?=e($theme)?>" <?=($team['theme']??'')===$theme?'selected':''?>><?=e($theme)?></option><?php endforeach;?></select></label></div></fieldset>
<fieldset><legend><span>02</span>紹介内容</legend><div class="admin-form-grid"><label class="admin-span-2">キャッチコピー <b>*</b><input name="catchcopy" value="<?=e($team['catchcopy'])?>" required></label><label class="admin-span-2">作品説明 <b>*</b><textarea name="description" rows="5" required><?=e($team['description'])?></textarea></label><label>見どころ<textarea name="highlights" rows="5"><?=e(teamAdminListText($team['highlights']))?></textarea></label><label>操作方法<textarea name="controls" rows="5"><?=e(teamAdminListText($team['controls']))?></textarea></label><label class="admin-span-2">動画URL<input type="url" name="video_url" value="<?=e($team['video_url'])?>"></label></div></fieldset>
<fieldset id="project-members"><legend><span>03</span>プロジェクトメンバー</legend>
<p class="form-note">1チームにつき最大8名まで登録できます。同じ学生を複数のチームに登録できます。所属を解除する場合は「未選択」に戻してください。兼任する役職は改行して複数入力できます。非公開の学生は公開ページには表示されません。</p>
<?php for ($slot = 0; $slot < 8; $slot++): $member = $memberRows[$slot] ?? ['student_id' => '', 'role' => '']; ?>
<div class="admin-form-grid project-member-row">
<label>メンバー <?= $slot + 1 ?><select data-member-student aria-describedby="member-warning-<?= $slot ?>" form="project-members-form" name="members[<?= $slot ?>][student_id]">
<option value="">未選択</option>
<?php foreach ($students as $student): ?><option data-public="<?= isStudentPublic($student) ? '1' : '0' ?>" value="<?= e($student['id']) ?>" <?= (string) $student['id'] === (string) $member['student_id'] ? 'selected' : '' ?>><?= e($student['name']) ?>（ID: <?= e($student['id']) ?>）<?= !isStudentPublic($student) ? '［非公開］' : '' ?></option><?php endforeach; ?>
</select></label>
<?php $selectedStudent = findById($students, (string) $member['student_id']); ?>
<p id="member-warning-<?= $slot ?>" class="admin-alert admin-span-2" data-member-warning role="alert" <?= $selectedStudent !== null && !isStudentPublic($selectedStudent) ? '' : 'hidden' ?>>警告：選択した学生は非公開です。所属は保存できますが、公開ページには表示されません。</p>
<label>役職（複数可・1行に1つ）<textarea form="project-members-form" name="members[<?= $slot ?>][role]" rows="3" placeholder="例：リーダー&#10;プログラマー"><?= e(str_replace(' / ', PHP_EOL, $member['role'])) ?></textarea></label>
</div>
<?php endfor; ?>
<?php if (!$students): ?><p class="form-note">学生管理画面で学生を登録してください。</p><?php endif; ?>
<div class="admin-form-actions"><p>メンバーの変更はこのボタンで保存します。</p><button form="project-members-form" class="button button-primary" name="save_members" value="1">メンバーを保存する →</button></div>
</fieldset>
<fieldset><legend><span>04</span>作品QRコード</legend><div class="qr-preview-grid"><div data-qr-value="<?=e($teamQrUrl)?>"></div><div><label>固定URL<input value="<?=e($teamQrUrl)?>" readonly></label><p class="form-note">変更を保存するとQRコードが自動で有効になります。</p><button type="button" class="button button-outline" data-qr-download>PNGをダウンロード</button><button type="button" class="button button-outline" data-qr-print>印刷する</button></div></div></fieldset>
<div class="admin-form-actions"><p>作品情報とQRコードを同時に保存します。</p><button class="button button-primary" name="save_team" value="1">変更を保存する →</button></div></form><form id="project-members-form" method="post" action="<?= e(url('teams_admin.php') . '?id=' . rawurlencode((string) $team['id']) . '#project-members') ?>"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="id" value="<?= e($team['id']) ?>"></form><?php else:?><div class="admin-empty"><span>WORK DATA</span><h2>編集する作品を選択</h2><p>左の一覧から作品を選んでください。</p></div><?php endif;?></div></div></section><script src="<?=e(url('assets/vendor/qrcode.min.js'))?>"></script><script src="<?=e(url('assets/qr-admin.js'))?>"></script><script src="<?= e(assetUrl('team-members-admin.js')) ?>"></script></main></body></html>
