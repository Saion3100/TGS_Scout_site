<?php
declare(strict_types=1);

/**
 * Contact form: configuration, validation, mail composition and the three
 * views (input / confirm / completed). The controller lives in public/contact.php.
 */

require_once __DIR__ . '/Mailer.php';

const CONTACT_ACTIONS = [
    '学校の先生に連絡したい',
    '説明会の相談をしたい',
    '産学連携の相談をしたい',
    'この学生(チーム)と話をしたい',
    'この学生(チーム)に応募してほしい',
    'この学生(チーム)に注目している',
];

/** Purposes that only make sense with a specific student attached. */
const CONTACT_STUDENT_REQUIRED_ACTIONS = [
    'この学生(チーム)と話をしたい',
    'この学生(チーム)に応募してほしい',
    'この学生(チーム)に注目している',
];

const CONTACT_MIN_FILL_SECONDS = 4;
const CONTACT_MAX_PER_WINDOW = 3;
const CONTACT_WINDOW_SECONDS = 600;

/** @return array<string,mixed> */
function contactConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $env = static function (string $key, string $default = ''): string {
        $value = getenv($key);
        return $value === false || trim((string) $value) === '' ? $default : trim((string) $value);
    };

    $recipients = array_values(array_filter(
        array_map('trim', explode(',', $env('TGS_CONTACT_RECIPIENTS', 'krc24a11@st.itc.ac.jp'))),
        static fn(string $address): bool => $address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) !== false
    ));
    if ($recipients === []) {
        $recipients = ['krc24a11@st.itc.ac.jp'];
    }

    $config = [
        'smtp_host'         => $env('TGS_SMTP_HOST'),
        'smtp_port'         => (int) $env('TGS_SMTP_PORT', '587'),
        'smtp_secure'       => $env('TGS_SMTP_SECURE', 'tls'),
        'smtp_user'         => $env('TGS_SMTP_USER', 'tgs-scout@itc.ac.jp'),
        'smtp_pass'         => $env('TGS_SMTP_PASS'),
        'mail_from'         => $env('TGS_MAIL_FROM', 'tgs-scout@itc.ac.jp'),
        'mail_from_name'    => $env('TGS_MAIL_FROM_NAME', 'TGS SCOUT 2026 事務局'),
        'recipients'        => $recipients,
        'send_individually' => $env('TGS_CONTACT_SEND_INDIVIDUALLY', '1') !== '0',
        'reply_estimate'    => $env('TGS_CONTACT_REPLY_ESTIMATE', '3〜5日（土日祝を除く）'),
        'contact_fallback'  => $env('TGS_CONTACT_FALLBACK', 'krc24a11@st.itc.ac.jp'),
    ];
    return $config;
}

function contactMailConfigured(): bool
{
    $config = contactConfig();
    return $config['smtp_host'] !== '' && $config['smtp_pass'] !== '';
}

function contactLen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function contactHead(string $value, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
}

function contactNormalizeNewlines(string $value): string
{
    return str_replace(["\r\n", "\r"], "\n", $value);
}

function contactHasHtml(string $value): bool
{
    return preg_match('/<[a-z!\/]/i', $value) === 1;
}

function contactHasControlChars(string $value): bool
{
    return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1;
}

function contactReceiptNumber(): string
{
    $alphabet = 'ACDEFGHJKLMNPQRSTUVWXYZ2345679';
    $suffix = '';
    for ($i = 0; $i < 6; $i++) {
        $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'TGS-' . date('Ymd') . '-' . $suffix;
}

/** @return array<string,string> */
function contactDefaultValues(): array
{
    return array_fill_keys([
        'action', 'company', 'department', 'position', 'name', 'email',
        'phone', 'website', 'message', 'student_id', 'team_id', 'privacy_agreed',
    ], '');
}

/**
 * @param array<string,mixed> $post
 * @return array<string,string>
 */
function contactValuesFromPost(array $post): array
{
    $values = contactDefaultValues();
    foreach ($values as $key => $_) {
        if ($key === 'privacy_agreed') {
            $values[$key] = empty($post[$key]) ? '' : '1';
            continue;
        }
        $values[$key] = contactNormalizeNewlines(trim((string) ($post[$key] ?? '')));
    }
    return $values;
}

function contactResolveStudent(string $id): ?array
{
    $id = trim($id);
    return $id === '' ? null : (findById(publicStudents(), $id) ?: null);
}

function contactResolveTeam(string $id): ?array
{
    $id = trim($id);
    return $id === '' ? null : (findById(data('teams'), $id) ?: null);
}

/**
 * @param array<string,mixed> $input
 * @return array{0: array<string,string>, 1: array<string,string>}
 */
function contactValidate(array $input, ?array $student, ?array $team): array
{
    $errors = [];
    $get = static fn(string $key): string => contactNormalizeNewlines(trim((string) ($input[$key] ?? '')));

    $data = [
        'action'         => $get('action'),
        'company'        => $get('company'),
        'department'     => $get('department'),
        'position'       => $get('position'),
        'name'           => $get('name'),
        'email'          => $get('email'),
        'phone'          => $get('phone'),
        'website'        => $get('website'),
        'message'        => $get('message'),
        'student_id'     => $student['id'] ?? contactNormalizeNewlines(trim((string) ($input['student_id'] ?? ''))),
        'team_id'        => $team['id'] ?? contactNormalizeNewlines(trim((string) ($input['team_id'] ?? ''))),
        'privacy_agreed' => empty($input['privacy_agreed']) ? '' : '1',
    ];

    foreach (['company', 'department', 'position', 'name', 'message'] as $key) {
        if ($data[$key] !== '' && contactHasHtml($data[$key])) {
            $errors[$key] = 'HTMLタグは使用できません。';
        }
    }
    foreach (['company', 'department', 'position', 'name', 'email', 'phone', 'website'] as $key) {
        if (!isset($errors[$key]) && (strpos($data[$key], "\n") !== false || contactHasControlChars($data[$key]))) {
            $errors[$key] = '使用できない文字が含まれています。';
        }
    }

    if (!in_array($data['action'], CONTACT_ACTIONS, true)) {
        $errors['action'] = 'お問い合わせの目的を選択してください。';
    }
    if ($data['company'] === '' || contactLen($data['company']) > 100) {
        $errors['company'] = '企業名は1〜100文字で入力してください。';
    }
    if (contactLen($data['department']) > 100) {
        $errors['department'] = '部署名は100文字以内で入力してください。';
    }
    if (contactLen($data['position']) > 100) {
        $errors['position'] = '役職は100文字以内で入力してください。';
    }
    if ($data['name'] === '' || contactLen($data['name']) > 100) {
        $errors['name'] = 'お名前は1〜100文字で入力してください。';
    }
    if ($data['email'] === '' || contactLen($data['email']) > 254 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'メールアドレスを正しい形式で入力してください。';
    }
    if ($data['phone'] !== '' && (contactLen($data['phone']) > 30 || preg_match('/^[0-9+()\-\s]+$/', $data['phone']) !== 1)) {
        $errors['phone'] = '電話番号は数字と + ( ) - 空白のみ、30文字以内で入力してください。';
    }
    if ($data['website'] !== '') {
        $validUrl = filter_var($data['website'], FILTER_VALIDATE_URL) !== false
            && preg_match('~^https?://~i', $data['website']) === 1;
        if (contactLen($data['website']) > 2048 || !$validUrl) {
            $errors['website'] = '企業Webサイトは http(s):// から始まる正しいURLで入力してください。';
        }
    }
    $messageLength = contactLen($data['message']);
    if ($messageLength < 10 || $messageLength > 3000) {
        $errors['message'] = 'メッセージは10〜3000文字で入力してください。';
    }
    if ($data['privacy_agreed'] === '') {
        $errors['privacy_agreed'] = 'プライバシーポリシーへの同意が必要です。';
    }
    if ($data['student_id'] !== '' && $data['team_id'] !== '') {
        $errors['action'] = $errors['action'] ?? '対象は学生または作品のどちらか一方のみ指定できます。';
    }
    if (in_array($data['action'], CONTACT_STUDENT_REQUIRED_ACTIONS, true) && $data['student_id'] === '') {
        $errors['action'] = 'この目的は学生詳細ページから対象の学生を選んでお問い合わせください。';
    }

    return [$data, $errors];
}

function contactRateLimited(): bool
{
    $now = time();
    $times = array_values(array_filter(
        $_SESSION['contact.sent_times'] ?? [],
        static fn($time): bool => is_int($time) && $time > $now - CONTACT_WINDOW_SECONDS
    ));
    $_SESSION['contact.sent_times'] = $times;
    return count($times) >= CONTACT_MAX_PER_WINDOW;
}

function contactRecordSend(): void
{
    $_SESSION['contact.sent_times'][] = time();
}

function contactMaskEmail(string $email): string
{
    if (strpos($email, '@') === false) {
        return '';
    }
    [$local, $domain] = explode('@', $email, 2);
    $head = contactLen($local) <= 2 ? contactHead($local, 1) : contactHead($local, 2);
    return $head . '***@' . $domain;
}

/* --------------------------------------------------------------------------
 * Mail composition
 * ------------------------------------------------------------------------ */

/** @param array<string,string> $data */
function contactSchoolSubject(string $receipt, array $data, string $targetName): string
{
    $target = $targetName !== '' ? ' / ' . $targetName : '';
    return sprintf('[TGS SCOUT][%s][%s] %s%s', $receipt, $data['action'], $data['company'], $target);
}

/** @param array<string,string> $data */
function contactSchoolBody(string $receipt, array $data, string $targetLabel): string
{
    $orDash = static fn(string $value): string => $value !== '' ? $value : '（未入力）';
    $lines = [
        '国際理工カレッジ TGS SCOUT お問い合わせ受付',
        '',
        '受付番号  : ' . $receipt,
        '受付日時  : ' . date('Y-m-d H:i:s'),
        '目的      : ' . $data['action'],
        '',
        '― 企業・ご担当者 ―',
        '企業名    : ' . $data['company'],
        '部署      : ' . $orDash($data['department']),
        '役職      : ' . $orDash($data['position']),
        'ご担当者  : ' . $data['name'],
        'メール    : ' . $data['email'],
        '電話      : ' . $orDash($data['phone']),
        'Webサイト : ' . $orDash($data['website']),
        '',
        '― 対象 ―',
        '対象      : ' . ($targetLabel !== '' ? $targetLabel : '指定なし'),
        '学生ID    : ' . ($data['student_id'] !== '' ? $data['student_id'] : '-'),
        'チームID  : ' . ($data['team_id'] !== '' ? $data['team_id'] : '-'),
        '',
        '― メッセージ ―',
        $data['message'],
        '',
        '――',
        'このメールに返信すると、お問い合わせ者へ直接届きます（Reply-To 設定済み）。',
    ];
    return implode("\n", $lines);
}

/** @param array<string,string> $data */
function contactAutoReplyBody(string $receipt, array $data, string $targetLabel): string
{
    $config = contactConfig();
    $lines = [
        $data['name'] . ' 様',
        '',
        'このたびは国際理工カレッジ TGS SCOUT へお問い合わせいただき、ありがとうございます。',
        '下記の内容で受け付けました。担当教員より順次ご連絡いたします。',
        '',
        '受付番号  : ' . $receipt,
        '目的      : ' . $data['action'],
        '対象      : ' . ($targetLabel !== '' ? $targetLabel : '指定なし'),
        '返信の目安: ' . $config['reply_estimate'],
        '',
        'このメールは送信専用です。ご不明な点は ' . $config['contact_fallback'] . ' 宛にご連絡ください。',
        'お心当たりがない場合は、お手数ですが本メールを破棄してください。',
        '',
        '国際理工カレッジ TGS SCOUT 2026',
    ];
    return implode("\n", $lines);
}

/**
 * @param array<string,string> $data
 * @return bool true only when every school recipient accepted the message.
 */
function contactSendSchoolNotification(
    string $receipt,
    array $data,
    string $targetName,
    string $targetLabel,
    ?string &$error = null
): bool {
    $config = contactConfig();
    if (!contactMailConfigured()) {
        $error = 'mail_not_configured';
        error_log('[contact] SMTP not configured; receipt ' . $receipt);
        return false;
    }

    $mailer = new SmtpMailer(
        $config['smtp_host'],
        $config['smtp_port'],
        $config['smtp_user'],
        $config['smtp_pass'],
        $config['smtp_secure']
    );
    $subject = contactSchoolSubject($receipt, $data, $targetName);
    $body = contactSchoolBody($receipt, $data, $targetLabel);
    $groups = $config['send_individually']
        ? array_map(static fn(string $address): array => [$address], $config['recipients'])
        : [$config['recipients']];

    try {
        foreach ($groups as $group) {
            $mailer->send($config['mail_from'], $config['mail_from_name'], $group, $subject, $body, $data['email']);
        }
        return true;
    } catch (Throwable $exception) {
        $error = 'send_failed';
        error_log('[contact] school notification failed; receipt ' . $receipt . '; ' . $exception->getMessage());
        return false;
    }
}

/** @param array<string,string> $data */
function contactSendAutoReply(string $receipt, array $data, string $targetLabel): void
{
    $config = contactConfig();
    if (!contactMailConfigured()) {
        return;
    }
    $mailer = new SmtpMailer(
        $config['smtp_host'],
        $config['smtp_port'],
        $config['smtp_user'],
        $config['smtp_pass'],
        $config['smtp_secure']
    );
    $subject = sprintf('【TGS SCOUT 2026】お問い合わせを受け付けました（%s）', $receipt);
    try {
        $mailer->send(
            $config['mail_from'],
            $config['mail_from_name'],
            [$data['email']],
            $subject,
            contactAutoReplyBody($receipt, $data, $targetLabel)
        );
    } catch (Throwable $exception) {
        error_log('[contact] auto-reply failed; receipt ' . $receipt . '; ' . $exception->getMessage());
    }
}

/* --------------------------------------------------------------------------
 * Views
 * ------------------------------------------------------------------------ */

function contactFieldError(array $errors, string $key): string
{
    return isset($errors[$key])
        ? '<span class="field-error">' . e($errors[$key]) . '</span>'
        : '';
}

/**
 * @param array<string,string> $values
 * @param array<string,string> $errors
 */
function renderContactForm(
    array $values,
    array $errors,
    string $csrf,
    ?array $student,
    ?array $team,
    string $targetName,
    string $targetLabel
): void {
    $hasTarget = $student !== null || $team !== null;
    ?>
<section class="page-hero contact-hero">
    <p class="section-number">CONTACT</p>
    <h1>学校へ問い合わせる</h1>
    <p>採用・面談・インターン等のご相談を学校がお預かりし、担当教員よりご連絡します。</p>
</section>
<section class="contact-layout section-pad">
    <aside>
        <p class="section-number">INFORMATION</p>
        <h2>お問い合わせについて</h2>
        <p>学生個人への直接連絡ではなく、学校が窓口となって適切におつなぎします。</p>
        <dl>
            <dt>受付内容</dt><dd>採用、面談、インターン、学校説明会、産学連携</dd>
            <dt>現在の対象</dt><dd><?= $targetName !== '' ? e($targetName) : '指定なし' ?></dd>
        </dl>
    </aside>
    <form class="contact-form" action="<?= e(url('contact.php')) ?>" method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="do" value="confirm">
        <?php if ($student): ?><input type="hidden" name="student_id" value="<?= e($student['id']) ?>"><?php endif; ?>
        <?php if ($team): ?><input type="hidden" name="team_id" value="<?= e($team['id']) ?>"><?php endif; ?>
        <p class="honeypot" aria-hidden="true">
            <label>このフィールドは入力しないでください<input type="text" name="nickname" tabindex="-1" autocomplete="off"></label>
        </p>

        <?php if (isset($errors['_form'])): ?>
        <div class="form-errors" role="alert"><p><?= e($errors['_form']) ?></p></div>
        <?php elseif ($errors): ?>
        <div class="form-errors" role="alert"><p>入力内容にエラーがあります。各項目をご確認ください。</p></div>
        <?php endif; ?>

        <?php if ($hasTarget): ?>
        <fieldset>
            <legend><span>00</span>お問い合わせの対象</legend>
            <p class="target-summary"><?= e($targetLabel) ?></p>
            <p class="form-note">対象は詳細ページから引き継いでいます。変更する場合は各詳細ページからお進みください。</p>
        </fieldset>
        <?php endif; ?>

        <fieldset>
            <legend><span>01</span>お問い合わせの目的 <b class="req" aria-label="必須">*</b></legend>
            <?php foreach (CONTACT_ACTIONS as $action): ?>
            <label class="radio-card">
                <input type="radio" name="action" value="<?= e($action) ?>" <?= $values['action'] === $action ? 'checked' : '' ?>>
                <span><?= e($action) ?></span>
            </label>
            <?php endforeach; ?>
            <?= contactFieldError($errors, 'action') ?>
        </fieldset>

        <fieldset>
            <legend><span>02</span>企業・ご担当者情報</legend>
            <label>企業名 <b class="req" aria-label="必須">*</b>
                <input name="company" value="<?= e($values['company']) ?>" autocomplete="organization" maxlength="100">
                <?= contactFieldError($errors, 'company') ?>
            </label>
            <div class="form-row">
                <label>部署名
                    <input name="department" value="<?= e($values['department']) ?>" autocomplete="organization-title" maxlength="100">
                    <?= contactFieldError($errors, 'department') ?>
                </label>
                <label>役職
                    <input name="position" value="<?= e($values['position']) ?>" maxlength="100">
                    <?= contactFieldError($errors, 'position') ?>
                </label>
            </div>
            <div class="form-row">
                <label>お名前 <b class="req" aria-label="必須">*</b>
                    <input name="name" value="<?= e($values['name']) ?>" autocomplete="name" maxlength="100">
                    <?= contactFieldError($errors, 'name') ?>
                </label>
                <label>メールアドレス <b class="req" aria-label="必須">*</b>
                    <input type="email" name="email" value="<?= e($values['email']) ?>" autocomplete="email" maxlength="254">
                    <?= contactFieldError($errors, 'email') ?>
                </label>
            </div>
            <div class="form-row">
                <label>電話番号
                    <input type="tel" name="phone" value="<?= e($values['phone']) ?>" autocomplete="tel" maxlength="30">
                    <?= contactFieldError($errors, 'phone') ?>
                </label>
                <label>企業Webサイト
                    <input type="url" name="website" value="<?= e($values['website']) ?>" placeholder="https://" maxlength="2048">
                    <?= contactFieldError($errors, 'website') ?>
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend><span>03</span>ご相談内容</legend>
            <label>メッセージ <b class="req" aria-label="必須">*</b>
                <textarea name="message" rows="7" placeholder="ご相談内容をご記入ください（10文字以上）" maxlength="3000"><?= e($values['message']) ?></textarea>
                <?= contactFieldError($errors, 'message') ?>
            </label>
        </fieldset>

        <label class="privacy-check">
            <input type="checkbox" name="privacy_agreed" value="1" <?= $values['privacy_agreed'] === '1' ? 'checked' : '' ?>>
            <a href="<?= e(url('privacy.php')) ?>" target="_blank" rel="noopener">プライバシーポリシー</a>に同意する <b class="req" aria-label="必須">*</b>
        </label>
        <?= contactFieldError($errors, 'privacy_agreed') ?>

        <button class="button button-primary submit-button" type="submit">入力内容を確認する <span>→</span></button>
    </form>
</section>
    <?php
}

/**
 * @param array<string,string> $values
 */
function renderContactConfirm(
    array $values,
    string $csrf,
    string $submitToken,
    string $targetLabel,
    ?string $formError = null
): void {
    $rows = [
        'お問い合わせの目的' => $values['action'],
        '対象'               => $targetLabel !== '' ? $targetLabel : '指定なし',
        '企業名'             => $values['company'],
        '部署名'             => $values['department'],
        '役職'               => $values['position'],
        'お名前'             => $values['name'],
        'メールアドレス'     => $values['email'],
        '電話番号'           => $values['phone'],
        '企業Webサイト'      => $values['website'],
    ];
    ?>
<section class="page-hero contact-hero">
    <p class="section-number">CONFIRM</p>
    <h1>入力内容の確認</h1>
    <p>下記の内容で学校窓口へ送信します。修正がなければ「この内容で送信する」を押してください。</p>
</section>
<section class="contact-layout section-pad">
    <aside>
        <p class="section-number">CHECK</p>
        <h2>送信前のご確認</h2>
        <p>送信後は担当教員が内容を確認し、ご入力のメールアドレス宛に受付番号の自動返信をお送りします。</p>
    </aside>
    <div class="contact-form">
        <?php if ($formError !== null): ?>
        <div class="form-errors" role="alert"><p><?= e($formError) ?></p></div>
        <?php endif; ?>
        <dl class="confirm-list">
            <?php foreach ($rows as $label => $value): ?>
            <div>
                <dt><?= e($label) ?></dt>
                <dd><?= $value !== '' ? e($value) : '<span class="confirm-empty">（未入力）</span>' ?></dd>
            </div>
            <?php endforeach; ?>
            <div>
                <dt>メッセージ</dt>
                <dd class="confirm-message"><?= nl2br(e($values['message'])) ?></dd>
            </div>
        </dl>
        <div class="confirm-actions">
            <form action="<?= e(url('contact.php')) ?>" method="post">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="do" value="edit">
                <?php foreach (['action', 'company', 'department', 'position', 'name', 'email', 'phone', 'website', 'message', 'student_id', 'team_id'] as $key): ?>
                <input type="hidden" name="<?= e($key) ?>" value="<?= e($values[$key]) ?>">
                <?php endforeach; ?>
                <?php if ($values['privacy_agreed'] === '1'): ?><input type="hidden" name="privacy_agreed" value="1"><?php endif; ?>
                <button class="button button-outline" type="submit">← 入力内容を修正する</button>
            </form>
            <form action="<?= e(url('contact.php')) ?>" method="post" data-contact-confirm>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="do" value="send">
                <input type="hidden" name="submit_token" value="<?= e($submitToken) ?>">
                <?php if ($values['student_id'] !== ''): ?><input type="hidden" name="student_id" value="<?= e($values['student_id']) ?>"><?php endif; ?>
                <?php if ($values['team_id'] !== ''): ?><input type="hidden" name="team_id" value="<?= e($values['team_id']) ?>"><?php endif; ?>
                <button class="button button-primary submit-button" type="submit">この内容で送信する <span>→</span></button>
            </form>
        </div>
    </div>
</section>
    <?php
}

/**
 * @param array{id:string,email:string,at:int}|null $record
 */
function renderContactCompleted(string $receipt, ?array $record): void
{
    $config = contactConfig();
    renderHeader('送信完了', 'contact');
    ?>
<section class="page-hero contact-hero">
    <p class="section-number">COMPLETE</p>
    <h1>お問い合わせを受け付けました</h1>
    <p>担当教員が内容を確認し、順次ご連絡いたします。</p>
</section>
<section class="contact-layout section-pad">
    <aside>
        <p class="section-number">RECEIPT</p>
        <h2>受付内容</h2>
        <p>この番号はお問い合わせ後のご連絡時にお使いください。</p>
    </aside>
    <div class="contact-form contact-complete">
        <p class="receipt-number"><span>受付番号</span><strong><?= e($receipt) ?></strong></p>
        <?php if ($record !== null && $record['email'] !== ''): ?>
        <p>自動返信メールを <strong><?= e(contactMaskEmail($record['email'])) ?></strong> 宛にお送りしました。</p>
        <?php else: ?>
        <p>ご入力のメールアドレス宛に受付確認の自動返信をお送りしています。</p>
        <?php endif; ?>
        <p>返信の目安：<?= e($config['reply_estimate']) ?></p>
        <div class="form-note">
            <p>数分待っても自動返信が届かない場合は、次をご確認ください。</p>
            <ul>
                <li>迷惑メールフォルダーに振り分けられていないか</li>
                <li>メールアドレスの入力に誤りがなかったか</li>
                <li>受信拒否設定で外部ドメインを制限していないか</li>
            </ul>
            <p>ご不明な場合は受付番号を添えて <?= e($config['contact_fallback']) ?> までご連絡ください。</p>
        </div>
        <a class="button button-primary" href="<?= e(url()) ?>">トップへ戻る <span>→</span></a>
    </div>
</section>
    <?php
    renderFooter();
}
