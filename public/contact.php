<?php
declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';
require_once is_file(__DIR__ . '/src/contact.php') ? __DIR__ . '/src/contact.php' : dirname(__DIR__) . '/src/contact.php';

$isHttps = ((string) ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('tgs_contact');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => url('/'),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $isHttps,
    ]);
    session_start();
}

if (empty($_SESSION['contact.csrf'])) {
    $_SESSION['contact.csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['contact.csrf'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- Completion screen (Post/Redirect/Get target) ---
$completed = (string) (filter_input(INPUT_GET, 'completed', FILTER_UNSAFE_RAW) ?: '');
if ($method === 'GET' && $completed !== '') {
    $record = $_SESSION['contact.completed'] ?? null;
    $known = is_array($record) && hash_equals((string) ($record['id'] ?? ''), $completed);
    renderContactCompleted($completed, $known ? $record : null);
    return;
}

// --- Resolve the target (detail-page links use ?student= / ?team=, POSTs carry hidden ids) ---
$studentParam = (string) (filter_input(INPUT_GET, 'student', FILTER_UNSAFE_RAW) ?: ($_POST['student_id'] ?? ''));
$teamParam = (string) (filter_input(INPUT_GET, 'team', FILTER_UNSAFE_RAW) ?: ($_POST['team_id'] ?? ''));
$student = contactResolveStudent($studentParam);
$team = $student ? null : contactResolveTeam($teamParam);
$targetName = $student ? '学生：' . $student['name'] : ($team ? '作品「' . $team['game_name'] . '」' : '');
// User-facing label: name only. Internal IDs go to the school notification separately.
$targetLabel = $targetName;

$values = contactDefaultValues();
$errors = [];
$stage = 'input';

if ($method === 'POST') {
    $tokenOk = hash_equals($csrf, (string) ($_POST['csrf'] ?? ''));
    $do = (string) ($_POST['do'] ?? 'confirm');

    if (!$tokenOk) {
        $errors['_form'] = 'セッションの有効期限が切れました。お手数ですが最初から入力し直してください。';
        $values = contactValuesFromPost($_POST);
    } elseif ($do === 'edit') {
        $stored = $_SESSION['contact.form_data'] ?? null;
        $values = is_array($stored) ? $stored + contactDefaultValues() : contactValuesFromPost($_POST);
    } elseif ($do === 'send') {
        $formData = $_SESSION['contact.form_data'] ?? null;
        $submitOk = !empty($_SESSION['contact.submit_token'])
            && hash_equals($_SESSION['contact.submit_token'], (string) ($_POST['submit_token'] ?? ''));

        if (!is_array($formData) || !$submitOk) {
            unset($_SESSION['contact.submit_token']);
            $values = is_array($formData) ? $formData : contactValuesFromPost($_POST);
            $errors['_form'] = '確認画面の有効期限が切れました。お手数ですが、内容をもう一度ご確認のうえお進みください。';
        } else {
            [$values, $errors] = contactValidate($formData, $student, $team);

            if (!$errors && $formData['student_id'] !== '' && !contactResolveStudent($formData['student_id'])) {
                $errors['_form'] = '対象の学生情報が更新されました。お手数ですが内容をご確認のうえ、再度お進みください。';
            } elseif (!$errors && $formData['team_id'] !== '' && !contactResolveTeam($formData['team_id'])) {
                $errors['_form'] = '対象の作品情報が更新されました。お手数ですが内容をご確認のうえ、再度お進みください。';
            }
            if (!$errors && contactRateLimited()) {
                $errors['_form'] = '短時間に複数の送信がありました。しばらく時間をおいてから再度お試しください。';
            }

            if (!$errors) {
                $receipt = contactReceiptNumber();
                $sendError = null;
                if (contactSendSchoolNotification($receipt, $values, $targetName, $targetLabel, $sendError)) {
                    contactSendAutoReply($receipt, $values, $targetLabel);
                    contactRecordSend();
                    $_SESSION['contact.completed'] = ['id' => $receipt, 'email' => $values['email'], 'at' => time()];
                    unset($_SESSION['contact.form_data'], $_SESSION['contact.submit_token'], $_SESSION['contact.started_at']);
                    header('Location: ' . url('contact.php') . '?completed=' . rawurlencode($receipt));
                    return;
                }
                // School notification failed: keep the input, let the user retry, do not complete.
                $_SESSION['contact.submit_token'] = bin2hex(random_bytes(32));
                $errors['_form'] = 'メールの送信に失敗しました。時間をおいて再度お試しいただくか、'
                    . contactConfig()['contact_fallback'] . ' へ直接ご連絡ください。';
                $stage = 'confirm';
            }
        }
    } else { // confirm
        [$values, $errors] = contactValidate($_POST, $student, $team);

        if (trim((string) ($_POST['nickname'] ?? '')) !== '') {
            $errors['_form'] = '送信を確認できませんでした。しばらく時間をおいてから再度お試しください。';
        } else {
            $startedAt = $_SESSION['contact.started_at'] ?? null;
            if (!is_int($startedAt)) {
                $errors['_form'] = 'セッションの有効期限が切れました。お手数ですが最初から入力し直してください。';
            } elseif (time() - $startedAt < CONTACT_MIN_FILL_SECONDS) {
                $errors['_form'] = '入力が早すぎます。内容をご確認のうえ、もう一度送信してください。';
            }
        }

        if (!$errors) {
            $_SESSION['contact.form_data'] = $values;
            $_SESSION['contact.submit_token'] = bin2hex(random_bytes(32));
            $stage = 'confirm';
        }
    }
}

if ($method === 'GET') {
    $_SESSION['contact.started_at'] = time();
    if ($student) {
        $values['student_id'] = $student['id'];
    }
    if ($team) {
        $values['team_id'] = $team['id'];
    }
}

renderHeader('お問い合わせ', 'contact');
if ($stage === 'confirm') {
    renderContactConfirm($values, $csrf, $_SESSION['contact.submit_token'] ?? '', $targetLabel, $errors['_form'] ?? null);
} else {
    renderContactForm($values, $errors, $csrf, $student, $team, $targetName, $targetLabel);
}
renderFooter();
