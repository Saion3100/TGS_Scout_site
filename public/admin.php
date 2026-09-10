<?php

declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';

session_start();
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
$configuredPassword = (string) getenv('TGS_ADMIN_PASSWORD');
$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $error = '送信サイズがサーバーの上限を超えた可能性があります。写真を小さくして再選択してください。';
}

function renderAdminStart(string $title = 'データ管理'): void
{
?>
    <!doctype html>
    <html lang="ja">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow,noarchive">
        <meta name="theme-color" content="#111111">
        <title><?= e($title) ?> | TGS SCOUT ADMIN</title>
        <link rel="stylesheet" href="<?= e(assetUrl('style.css')) ?>">
        <link rel="stylesheet" href="<?= e(assetUrl('qr-admin.css')) ?>">
    </head>

    <body id="page-top" class="admin-body"><?php renderBackToTop(); ?><main class="admin-main">
        <?php
    }

    function renderAdminEnd(): void
    {
        ?><script src="<?= e(assetUrl('vendor/qrcode.min.js')) ?>"></script>
            <script src="<?= e(assetUrl('qr-admin.js')) ?>"></script>
            <script src="<?= e(assetUrl('student-photo-admin.js')) ?>"></script>
            <script src="<?= e(assetUrl('admin-list-sort.js')) ?>"></script>
            <script src="<?= e(assetUrl('searchable-select.js')) ?>"></script>
        </main>
    </body>

    </html><?php
        }

        function adminText(string $name): string
        {
            return trim((string) ($_POST[$name] ?? ''));
        }

        /** @return list<string> */
        function adminList(string $name): array
        {
            $parts = preg_split('/[\r\n,、]+/u', adminText($name)) ?: [];
            return array_values(array_unique(array_filter(array_map('trim', $parts), static fn(string $value): bool => $value !== '')));
        }

        function adminListText(array $values): string
        {
            return implode(PHP_EOL, array_map('strval', $values));
        }

        function validateAdminUrl(string $value, string $label): string
        {
            if ($value !== '' && (filter_var($value, FILTER_VALIDATE_URL) === false || !preg_match('~^https?://~i', $value))) {
                throw new InvalidArgumentException($label . 'は http:// または https:// から始まるURLを入力してください。');
            }
            return $value;
        }

        /** @param list<array<string, mixed>> $students */
        function nextStudentId(array $students): string
        {
            $maximum = 0;
            foreach (array_merge($students, reservedManagementIds('student-')) as $student) {
                if (preg_match('/^\d+$/', (string) ($student['id'] ?? '')) === 1) {
                    $maximum = max($maximum, (int) $student['id']);
                }
            }
            return (string) ($maximum + 1);
        }

        /** @return array<string, mixed> */
        function studentFromPost(string $studentId): array
        {
            $required = ['name' => '氏名', 'name_kana' => 'ふりがな', 'name_en' => '英字氏名', 'graduation_year' => '卒業予定年', 'course' => 'コース', 'headline' => '見出し', 'bio' => 'プロフィール'];
            $values = [];
            foreach ($required as $key => $label) {
                $values[$key] = adminText($key);
                if ($values[$key] === '') {
                    throw new InvalidArgumentException($label . 'を入力してください。');
                }
            }
            $roles = valueList($_POST['role'] ?? []);
            if (!$roles) throw new InvalidArgumentException('職種を1つ以上選択してください。');
            return [
                'id' => $studentId,
                'name' => $values['name'],
                'name_kana' => $values['name_kana'],
                'name_en' => $values['name_en'],
                'graduation_year' => $values['graduation_year'],
                'course' => $values['course'],
                'role' => $roles,
                'desired_roles' => adminList('desired_roles'),
                'headline' => $values['headline'],
                'bio' => $values['bio'],
                'skills' => adminList('skills'),
                'fields' => adminList('fields'),
                'interview_available' => isset($_POST['interview_available']),
                'internship_interest' => isset($_POST['internship_interest']),
                'portfolio_drive_url' => validateAdminUrl(adminText('portfolio_drive_url'), 'ポートフォリオURL'),
                'portfolio_site_url' => validateAdminUrl(adminText('portfolio_site_url'), 'ポートフォリオ（外部サイト）URL'),
                'source_code_site_url' => validateAdminUrl(adminText('source_code_site_url'), 'ソースコード（外部サイト）URL'),
                'public_work_url' => validateAdminUrl(adminText('public_work_url'), '公開可能な作品URL'),
                'source_code_drive_url' => validateAdminUrl(adminText('source_code_drive_url'), 'ソースコードURL'),
                'video_url' => validateAdminUrl(adminText('video_url'), '動画URL'),
                'is_active' => isset($_POST['is_active']),
            ];
        }

        if (isset($_POST['logout'])) {
            $_SESSION = [];
            session_destroy();
            header('Location: ' . url('admin.php'));
            exit;
        }
        $studentPassword = (string) getenv('TGS_STUDENT_PASSWORD');
        $loginId = is_string($_POST['login_id'] ?? null) ? trim($_POST['login_id']) : '';
        if (!isManagementAuthenticated() && isset($_POST['password'])) {
            $password = is_string($_POST['password']) ? $_POST['password'] : '';
            $adminLogin = $loginId === 'admin' && $configuredPassword !== '' && hash_equals($configuredPassword, $password);
            $loginStudent = $studentPassword !== '' && hash_equals($studentPassword, $password)
                ? studentForLogin(repository('students')->all(), $loginId) : null;
            $studentLogin = $loginStudent !== null;
            if ($adminLogin || $studentLogin) {
                session_regenerate_id(true);
                $_SESSION = ['auth_role' => $adminLogin ? 'admin' : 'student', 'csrf_token' => bin2hex(random_bytes(32))];
                if ($adminLogin) $_SESSION['admin_authenticated'] = true;
                else $_SESSION['student_id'] = (string) $loginStudent['id'];
                header('Location: ' . url('admin.php'));
                exit;
            }
            $error = 'ID・名前またはパスワードが正しくありません。同姓同名の場合は学生IDを入力してください。';
        }
        if (!isManagementAuthenticated()) {
            $_SESSION = [];
            renderAdminStart('ログイン'); ?>
    <section class="admin-shell admin-login-shell">
        <div class="admin-panel">
            <p class="section-number">TGS SCOUT ADMIN</p>
            <h1>データ管理</h1>
            <?php if ($error): ?><p class="admin-error" role="alert"><?= e($error) ?></p><?php endif; ?>
            <form method="post" class="admin-login">
                <label>IDまたは名前<input type="text" name="login_id" value="<?= e($loginId) ?>" required autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="login-id-help"></label>
                <p id="login-id-help" class="form-note">学生：自分の学生IDまたは氏名（漢字）<br>同姓同名の場合は学生IDを入力してください。</p>
                <label>パスワード<input type="password" name="password" required autocomplete="current-password"></label>
                <button class="button button-primary">ログイン</button>
            </form>
            <a class="admin-back-link" href="<?= e(url()) ?>">← 公開ページへ戻る</a>
        </div>
    </section><?php renderAdminEnd();
                exit;
            }

            handleManagementDeletion('students');
$students = repository('students')->all();
            $section = (string) ($_GET['section'] ?? $_POST['section'] ?? 'students');

            if (!isAdminUser()) {
                if ($section !== 'students' || isset($_GET['new']) || ($_POST['mode'] ?? '') === 'new' || isset($_POST['save_featured']) || isset($_POST['delete_featured'])) redirectToError(403);
                foreach ([$_GET['id'] ?? currentStudentId(), $_POST['original_id'] ?? currentStudentId()] as $targetId) {
                    if (!is_string($targetId) || !canEditStudent($targetId)) redirectToError(403);
                }
            }
            if ($section === 'featured') {
                $featuredItems = repository('featured_students')->all();
                usort($featuredItems, static fn(array $a, array $b): int => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));
                $featuredStudentIds = array_fill_keys(array_map(
                    static fn(array $item): string => (string) ($item['student_id'] ?? ''),
                    $featuredItems
                ), true);
                $selectedStudentId = (string) ($_GET['student_id'] ?? '');
                $isFeaturedNew = isset($_GET['new']);
                $featured = null;
                if ($isFeaturedNew) {
                    $featured = [];
                } elseif ($selectedStudentId !== '') {
                    foreach ($featuredItems as $item) {
                        if ((string) ($item['student_id'] ?? '') === $selectedStudentId) {
                            $featured = $item;
                            break;
                        }
                    }
                }

                if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_featured']) || isset($_POST['delete_featured']))) {
                    try {
                        if (!is_string($_SESSION['csrf_token'] ?? null) || $_SESSION['csrf_token'] === '' || !is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                            redirectToError(403, 'csrf');
                        }
                        $originalStudentId = adminText('original_student_id');
                        if (isset($_POST['delete_featured'])) {
                            if ($originalStudentId === '') {
                                throw new InvalidArgumentException('削除対象が見つかりません。');
                            }
                            $remaining = array_values(array_filter($featuredItems, static fn(array $item): bool => (string) ($item['student_id'] ?? '') !== $originalStudentId));
                            if (count($remaining) === count($featuredItems)) {
                                throw new InvalidArgumentException('削除対象が見つかりません。');
                            }
                            repository('featured_students')->replaceAll($remaining);
                            header('Location: ' . url('admin.php') . '?section=featured&deleted=1');
                            exit;
                        }

                        $studentId = adminText('student_id');
                        $order = filter_input(INPUT_POST, 'order', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                        $focus = adminText('focus');
                        $teacherComment = adminText('teacher_comment');
                        if ($studentId === '' || findById($students, $studentId) === null) {
                            throw new InvalidArgumentException('学生を選択してください。');
                        }
                        if ($order === false || $order === null) {
                            throw new InvalidArgumentException('表示順は1以上の整数で入力してください。');
                        }
                        if ($focus === '' || $teacherComment === '') {
                            throw new InvalidArgumentException('注目ポイントと教員コメントを入力してください。');
                        }
                        foreach ($featuredItems as $item) {
                            if ((string) ($item['student_id'] ?? '') === $studentId && $studentId !== $originalStudentId) {
                                throw new InvalidArgumentException('選択した学生はすでに注目学生へ登録されています。');
                            }
                        }
                        $record = ['student_id' => $studentId, 'order' => (int) $order, 'focus' => $focus, 'teacher_comment' => $teacherComment];
                        $updated = false;
                        foreach ($featuredItems as $index => $item) {
                            if ((string) ($item['student_id'] ?? '') === $originalStudentId && $originalStudentId !== '') {
                                $featuredItems[$index] = $record;
                                $updated = true;
                                break;
                            }
                        }
                        if (!$updated) {
                            $featuredItems[] = $record;
                        }
                        usort($featuredItems, static fn(array $a, array $b): int => ((int) $a['order']) <=> ((int) $b['order']));
                        repository('featured_students')->replaceAll($featuredItems);
                        header('Location: ' . url('admin.php') . '?section=featured&student_id=' . rawurlencode($studentId) . '&saved=1');
                        exit;
                    } catch (Throwable $exception) {
                        if (!($exception instanceof InvalidArgumentException)) {
                            error_log((string) $exception);
                            redirectToError(500);
                        }

                        $error = $exception->getMessage();
                        $isFeaturedNew = adminText('original_student_id') === '';
                        $selectedStudentId = adminText('student_id');
                        $featured = ['student_id' => $selectedStudentId, 'order' => adminText('order'), 'focus' => adminText('focus'), 'teacher_comment' => adminText('teacher_comment')];
                    }
                }
                if (isset($_GET['deleted']) && $section === 'students') $notice = '学生を削除しました。';
if (isset($_GET['saved'])) {
                    $notice = '注目学生を保存しました。トップページにも反映されています。';
                }
                if (isset($_GET['deleted'])) {
                    $notice = '注目学生から削除しました。';
                }
                renderAdminStart('注目学生管理');
                ?>
    <section class="admin-shell">
        <div class="admin-toolbar">
            <div>
                <p class="section-number">TGS SCOUT ADMIN</p>
                <h1>注目学生管理</h1>
                <p class="admin-lead">トップページに掲載する学生と教員コメント</p>
            </div>
            <div class="admin-toolbar-actions"><a class="button button-outline" href="<?= e(url()) ?>">← 公開ページへ戻る</a>
                <form method="post"><button class="button button-primary" name="logout" value="1">ログアウト</button></form>
            </div>
        </div>
        <?php renderManagementTabs($section === 'featured' ? 'featured' : 'students'); ?>
        <?php if ($notice): ?><p class="admin-success"><?= e($notice) ?></p><?php endif; ?><?php if ($error): ?><p class="admin-error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <div class="admin-grid">
            <aside class="admin-list">
                <div class="admin-list-head"><strong>掲載中</strong><span><?= count($featuredItems) ?>名</span></div><a class="button button-primary" href="<?= e(url('admin.php')) ?>?section=featured&new=1">＋ 注目学生を追加</a>
                <?php foreach ($featuredItems as $item): $listedStudent = findById($students, (string) $item['student_id']); ?><a class="<?= !$isFeaturedNew && $selectedStudentId === (string) $item['student_id'] ? 'is-current' : '' ?>" href="<?= e(url('admin.php')) ?>?section=featured&student_id=<?= e($item['student_id']) ?>"><span><strong><?= e($listedStudent['name'] ?? '不明な学生') ?></strong><small><?= e($item['focus'] ?? '') ?></small></span><span class="admin-order"><?= e($item['order'] ?? '') ?></span></a><?php endforeach; ?>
            </aside>
            <div class="admin-editor"><?php if ($featured !== null): ?><div class="admin-editor-head">
                        <div>
                            <p class="section-number"><?= $isFeaturedNew ? 'NEW FEATURED STUDENT' : 'EDIT FEATURED STUDENT' ?></p>
                            <h2><?= $isFeaturedNew ? '注目学生を追加' : e((findById($students, (string) ($featured['student_id'] ?? ''))['name'] ?? '注目学生を編集')) ?></h2>
                        </div><span>必須項目 <b>*</b></span>
                    </div>
                    <form method="post" class="admin-student-form"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="section" value="featured"><input type="hidden" name="original_student_id" value="<?= e($isFeaturedNew ? '' : ($featured['student_id'] ?? '')) ?>">
                        <fieldset>
                            <legend><span>01</span>掲載内容</legend>
                            <div class="admin-form-grid"><label><span>学生 <b>*</b></span><select name="student_id" required data-searchable-select>
                                        <option value="">選択してください</option><?php foreach ($students as $candidate): ?><?php if ($isFeaturedNew && isset($featuredStudentIds[(string) ($candidate['id'] ?? '')])) {
                                                                                                                            continue;
                                                                                                                        } ?><option data-search="<?= e($candidate['name_kana'] ?? '') ?>" value="<?= e($candidate['id']) ?>" <?= (string) ($featured['student_id'] ?? '') === (string) $candidate['id'] ? 'selected' : '' ?>><?= e($candidate['name']) ?>（<?= e(listText($candidate['role'] ?? [])) ?>）</option><?php endforeach; ?>
                                    </select></label><label><span>表示順 <b>*</b></span><input type="number" name="order" min="1" value="<?= e($featured['order'] ?? count($featuredItems) + 1) ?>" required></label><label class="admin-span-2"><span>注目ポイント <b>*</b></span><input name="focus" value="<?= e($featured['focus'] ?? '') ?>" required></label><label class="admin-span-2"><span>教員コメント <b>*</b></span><textarea name="teacher_comment" rows="7" required><?= e($featured['teacher_comment'] ?? '') ?></textarea></label></div>
                        </fieldset>
                        <div class="admin-form-actions">
                            <p>保存するとトップページの注目学生へ即時反映されます。</p>
                            <div class="admin-action-buttons"><?php if (!$isFeaturedNew): ?><button class="button admin-delete-button" name="delete_featured" value="1" formnovalidate onclick="return confirm('この学生を注目学生から削除しますか？')">削除する</button><?php endif; ?><button class="button button-primary" name="save_featured" value="1"><?= $isFeaturedNew ? '追加する' : '変更を保存する' ?> <span>→</span></button></div>
                        </div>
                    </form>
                <?php else: ?><div class="admin-empty"><span>FEATURED STUDENTS</span>
                        <h2>編集する学生を選択</h2>
                        <p>左の一覧から選ぶか、注目学生を追加してください。</p>
                    </div><?php endif; ?>
            </div>
        </div>
    </section><?php renderAdminEnd();
                exit;
            }

            $selectedId = (string) ($_GET['id'] ?? (isAdminUser() ? '' : currentStudentId()));
            $isNew = isset($_GET['new']);
            $student = $isNew ? [] : ($selectedId !== '' ? findById($students, $selectedId) : null);

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_student'])) {
                $isNew = ($_POST['mode'] ?? '') === 'new';
                try {
                    if (!is_string($_SESSION['csrf_token'] ?? null) || $_SESSION['csrf_token'] === '' || !is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                        redirectToError(403, 'csrf');
                    }
                    $originalId = adminText('original_id');
                    if (!$isNew && ($originalId === '' || findById($students, $originalId) === null)) {
                        throw new InvalidArgumentException('編集対象の学生が見つかりません。');
                    }
                    if (!isAdminUser() && ($isNew || !canEditStudent($originalId))) redirectToError(403);
                    $studentId = $isNew ? nextStudentId($students) : $originalId;
                    $existing = $isNew ? [] : findById($students, $originalId);
                    $student = array_merge($existing, studentFromPost($studentId));
                    if (!isAdminUser()) $student['is_active'] = $existing['is_active'] ?? false;
                    if (isset($_FILES['student_photo']) && ($_FILES['student_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        if (!isset($_POST['photo_consent'])) throw new InvalidArgumentException('写真の掲載同意を確認してください。');
                        try {
                            $student['photo_drive_file_id'] = StudentPhoto::upload($_FILES['student_photo'], $studentId);
                            $student['photo_url'] = url('student_photo.php') . '?id=' . rawurlencode($studentId);
                        } catch (RuntimeException $photoError) {
                            error_log('Student photo upload: ' . $photoError->getMessage());
                            throw new InvalidArgumentException('Google Driveに写真を保存できませんでした。認証設定・権限・通信を確認して、写真を再選択してください。');
                        }
                    }
                    repository('students')->saveById($student, $isNew);
                    try {
                        StudentPhoto::trashPrevious($existing, $student, repository('students')->all());
                    } catch (Throwable $cleanupError) {
                        error_log('Student photo cleanup: ' . $cleanupError->getMessage());
                        $_SESSION['photo_cleanup_warning'] = '新しい写真と学生情報は保存済みですが、旧写真をゴミ箱へ移せませんでした。Driveの権限・通信を確認してください。';
                    }
                    syncManagedQr('student-' . $studentId, '学生：' . $student['name'], absoluteUrl('student_detail.php', ['id' => $studentId]), !empty($student['is_active']));
                    header('Location: ' . url('admin.php') . '?id=' . rawurlencode($student['id']) . '&saved=1');
                    exit;
                } catch (Throwable $exception) {
                    if (!($exception instanceof InvalidArgumentException)) {
                        error_log((string) $exception);
                        redirectToError(500);
                    }

                    $error = $exception->getMessage();
                    $student = array_merge($isNew ? [] : (findById($students, $originalId ?? '') ?? []), studentFromPostFallback());
                    if (!isAdminUser()) $student['is_active'] = (findById($students, currentStudentId())['is_active'] ?? false);
                    $selectedId = (string) ($student['id'] ?? '');
                }
            }

            function studentFromPostFallback(): array
            {
                $fields = ['name', 'name_kana', 'name_en', 'graduation_year', 'course', 'headline', 'bio', 'portfolio_drive_url', 'portfolio_site_url', 'source_code_drive_url', 'source_code_site_url', 'public_work_url', 'video_url'];
                $student = [];
                foreach ($fields as $field) {
                    $student[$field] = adminText($field);
                }
                $student['role'] = valueList($_POST['role'] ?? []);
                $student['id'] = adminText('original_id');
                foreach (['desired_roles', 'skills', 'fields'] as $field) {
                    $student[$field] = adminList($field);
                }
                foreach (['interview_available', 'internship_interest', 'is_active'] as $field) {
                    $student[$field] = isset($_POST[$field]);
                }
                return $student;
            }

            if (isset($_GET['saved'])) {
                $notice = '学生データを保存しました。公開ページにも反映されています。';
            }
            if (isset($_SESSION['photo_cleanup_warning'])) {
                $error = (string) $_SESSION['photo_cleanup_warning'];
                unset($_SESSION['photo_cleanup_warning']);
            }
            $roles = array_values(array_unique(array_merge(['プログラマー', 'デザイナー', 'プランナー', 'サウンド', 'その他'], teamFilterOptions($students, 'role'), valueList($student['role'] ?? []))));
            $courses = array_values(array_unique(array_filter(array_column($students, 'course'))));
            $graduationYears = array_values(array_unique(array_filter(array_column($students, 'graduation_year'))));
            if (!isAdminUser()) $students = array_values(array_filter($students, static fn(array $item): bool => (string) $item['id'] === currentStudentId()));
            renderAdminStart('学生データ管理');
                ?>
<section class="admin-shell">
    <div class="admin-toolbar">
        <div>
            <p class="section-number">TGS SCOUT ADMIN</p>
            <h1>学生データ管理</h1>
            <p class="admin-lead">学生プロフィールの登録・更新</p>
        </div>
        <div class="admin-toolbar-actions"><a class="button button-outline" href="<?= e(url()) ?>">← 公開ページへ戻る</a>
            <form method="post"><button class="button button-primary" name="logout" value="1">ログアウト</button></form>
        </div>
    </div>
    <?php renderManagementTabs($section === 'featured' ? 'featured' : 'students'); ?>
    <?php if ($notice): ?><p class="admin-success"><?= e($notice) ?></p><?php endif; ?><?php if ($error): ?><p class="admin-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <div class="admin-grid">
        <aside class="admin-list" data-admin-sort-list="students">
            <div class="admin-list-head"><strong>登録学生</strong><span><?= count($students) ?>名</span></div><label class="admin-list-sort">並び順<select data-admin-sort>
                    <option value="id">ID昇順</option>
                    <option value="name">名前昇順（ふりがな）</option>
                </select></label><label class="admin-list-search">検索<input type="search" data-admin-search placeholder="ID・名前・ふりがな"></label>
            <p data-admin-search-empty role="status" hidden>該当する項目がありません。</p><?php if (isAdminUser()): ?><a class="button button-primary" href="<?= e(url('admin.php')) ?>?new=1">＋ 新しい学生を追加</a><?php endif; ?><?php foreach ($students as $item): ?><a data-sort-id="<?= e($item['id'] ?? '') ?>" data-sort-name="<?= e(preg_replace('/\s+/u', '', trim((string) ($item['name_kana'] ?? ''))) ?: ($item['name'] ?? '')) ?>" class="<?= !$isNew && $selectedId === ($item['id'] ?? '') ? 'is-current' : '' ?>" href="<?= e(url('admin.php')) ?>?id=<?= e($item['id'] ?? '') ?>"><span><strong><?= e($item['id'] ?? '') ?>　<?= e($item['name'] ?? '(氏名なし)') ?></strong><small><?= e(listText($item['role'] ?? [])) ?></small></span><span class="admin-status <?= !empty($item['is_active']) ? 'is-public' : '' ?>"><?= !empty($item['is_active']) ? '公開' : '非公開' ?></span></a><?php endforeach; ?>
        </aside>
        <div class="admin-editor">
            <?php if ($student !== null): ?>
                <div class="admin-editor-head">
                    <div>
                        <p class="section-number"><?= $isNew ? 'NEW STUDENT' : 'EDIT STUDENT' ?></p>
                        <h2><?= $isNew ? '学生を新規追加' : e($student['name'] ?? '学生を編集') ?></h2>
                    </div><span>必須項目 <b>*</b></span>
                </div>
                <form method="post" enctype="multipart/form-data" class="admin-student-form">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="mode" value="<?= $isNew ? 'new' : 'edit' ?>"><input type="hidden" name="original_id" value="<?= e($student['id'] ?? '') ?>">
                    <fieldset>
                        <legend><span>01</span>基本情報</legend>
                        <div class="admin-form-grid">
                            <label><span>氏名 <b>*</b></span><input name="name" value="<?= e($student['name'] ?? '') ?>" required></label>
                            <label><span>ふりがな <b>*</b></span><input name="name_kana" value="<?= e($student['name_kana'] ?? '') ?>" required></label>
                            <label><span>英字氏名 <b>*</b></span><input name="name_en" value="<?= e($student['name_en'] ?? '') ?>" placeholder="Taro Kokusai" required></label>
                            <label><span>卒業予定年 <b>*</b></span><select name="graduation_year" required>
                                    <option value="">選択してください</option><?php foreach ($graduationYears as $value): ?><option value="<?= e($value) ?>" <?= ($student['graduation_year'] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?>
                                </select></label>
                            <label><span>コース <b>*</b></span><select name="course" required>
                                    <option value="">選択してください</option><?php foreach ($courses as $value): ?><option value="<?= e($value) ?>" <?= ($student['course'] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?>
                                </select></label>
                            <div class="admin-span-2">
                                <p>職種（複数選択可・1つ以上必須） <b>*</b></p>
                                <div class="admin-check-grid"><?php foreach ($roles as $value): ?><label><input type="checkbox" name="role[]" value="<?= e($value) ?>" <?= in_array($value, valueList($student['role'] ?? []), true) ? 'checked' : '' ?>><span><?= e($value) ?></span></label><?php endforeach; ?></div>
                            </div>
                            <label class="admin-span-2">希望職種<textarea name="desired_roles" rows="3" placeholder="1行に1項目"><?= e(adminListText($student['desired_roles'] ?? [])) ?></textarea></label>
                        </div>
                    </fieldset>
                    <fieldset data-photo-editor>
                        <legend>プロフィール写真</legend>
                        <p class="form-note">横3：縦4で切り抜きます。元画像のサイズ指定はありません（10MB以下）。写真は学生情報と一緒に保存されます。</p>
                        <?php if (studentImageUrl($student) !== ''): ?><img src="<?= e(studentImageUrl($student)) ?>" alt="現在のプロフィール写真" style="width:150px;aspect-ratio:3/4;object-fit:contain"><?php endif; ?>
                        <?php $photoError = StudentPhoto::configurationError();
                        if ($photoError !== ''): ?><p class="admin-error"><?= e($photoError) ?></p><?php endif; ?>
                        <label>新しい写真<input type="file" accept="image/jpeg,image/png,image/webp" data-photo-file <?= $photoError !== '' ? 'disabled' : '' ?>></label>
                        <div data-photo-controls hidden>
                            <canvas width="300" height="400" data-photo-canvas style="display:block;max-width:100%;touch-action:none;border:1px solid #aaa" aria-label="写真の切り抜きプレビュー"></canvas>
                            <label>拡大率<input type="range" min="1" max="4" step="0.01" value="1" data-photo-zoom></label>
                            <label>左右の位置<input type="range" min="0" max="100" value="50" data-photo-x></label>
                            <label>上下の位置<input type="range" min="0" max="100" value="50" data-photo-y></label>
                            <button type="button" class="button button-outline" data-photo-confirm>この範囲で確定</button>
                            <button type="button" class="button button-outline" data-photo-cancel>写真の変更を取り消す</button>
                            <label><input type="checkbox" name="photo_consent" value="1">本人の写真掲載への同意を確認しました</label>
                        </div>
                        <p role="status" data-photo-status></p>
                    </fieldset>
                    <fieldset>
                        <legend><span>02</span>プロフィール・スキル</legend>
                        <div class="admin-form-grid">
                            <label class="admin-span-2"><span>見出し <b>*</b></span><input name="headline" value="<?= e($student['headline'] ?? '') ?>" required></label>
                            <label class="admin-span-2"><span>プロフィール <b>*</b></span><textarea name="bio" rows="6" required><?= e($student['bio'] ?? '') ?></textarea></label>
                            <label>使用技術・ツール<textarea name="skills" rows="5" placeholder="C++&#10;Unreal Engine 5"><?= e(adminListText($student['skills'] ?? [])) ?></textarea><small>1行に1項目、またはカンマ区切り</small></label>
                            <label>専門分野<textarea name="fields" rows="5" placeholder="ゲームプレイ&#10;グラフィックス"><?= e(adminListText($student['fields'] ?? [])) ?></textarea><small>1行に1項目、またはカンマ区切り</small></label>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend><span>03</span>作品・資料リンク</legend>
                        <div class="admin-form-grid admin-url-fields">
                            <p class="admin-span-2 form-note">すべて任意です。入力した項目だけ表示されます。Google Driveの資料は閲覧できる共有設定にしてください。</p>
                            <?php foreach (studentResourceFields() as $key => $label): ?>
                                <label class="admin-span-2"><?= e($label) ?> URL（任意）<input type="url" name="<?= e($key) ?>" value="<?= e($student[$key] ?? '') ?>" placeholder="https://"></label>
                            <?php endforeach; ?>
                            <label class="admin-span-2">動画URL<input type="url" name="video_url" value="<?= e($student['video_url'] ?? '') ?>" placeholder="https://"></label>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend><span>04</span>公開・活動設定</legend>
                        <div class="admin-check-grid">
                            <label><input type="checkbox" name="interview_available" value="1" <?= !empty($student['interview_available']) ? 'checked' : '' ?>><span><strong>面談可能</strong><small>企業との面談を受け付ける</small></span></label>
                            <label><input type="checkbox" name="internship_interest" value="1" <?= !empty($student['internship_interest']) ? 'checked' : '' ?>><span><strong>インターン希望</strong><small>インターンを希望している</small></span></label>
                            <?php if (isAdminUser()): ?><label><input type="checkbox" name="is_active" value="1" <?= !empty($student['is_active']) ? 'checked' : '' ?>><span><strong>プロフィールを公開</strong><small>一覧と公開APIに表示する</small></span></label><?php else: ?><p>公開状態は管理者が変更します。</p><?php endif; ?>
                        </div>
                    </fieldset>
                    <?php if (!$isNew && !empty($student['is_active'])): $studentQrUrl = managedQrUrl('student-' . (string) ($student['id'] ?? '')); ?>
                        <fieldset>
                            <legend><span>05</span>プロフィールQRコード</legend>
                            <div class="qr-preview-grid">
                                <div data-qr-value="<?= e($studentQrUrl) ?>"></div>
                                <div><label>固定URL<input value="<?= e($studentQrUrl) ?>" readonly></label>
                                    <p class="form-note">公開プロフィール用のQRコードです。プロフィールを変更しても再印刷は不要です。</p><button type="button" class="button button-outline" data-qr-download>PNGをダウンロード</button><button type="button" class="button button-outline" data-qr-print>印刷する</button>
                                </div>
                            </div>
                        </fieldset>
                    <?php endif; ?>
                    <div class="admin-form-actions">
                        <p>保存するとJSONへ変換され、公開サイトへ即時反映されます。</p><?php if (isAdminUser() && !$isNew): ?><button class="button admin-delete-button" name="delete_student" value="1" formnovalidate onclick="return confirm('この学生を削除しますか？所属・注目学生の掲載も解除されます。元に戻せません。')">学生を削除する</button><?php endif; ?><button class="button button-primary" name="save_student" value="1"><?= $isNew ? '学生を追加する' : '変更を保存する' ?> <span>→</span></button>
                    </div>
                </form>
            <?php else: ?><div class="admin-empty"><span>STUDENT DATA</span>
                    <h2>編集する学生を選択</h2>
                    <p>左の一覧から学生を選ぶか、新しい学生を追加してください。</p>
                </div><?php endif; ?>
        </div>
    </div>
</section>
<?php renderAdminEnd();
