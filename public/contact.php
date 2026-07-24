<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$studentId = filter_input(INPUT_GET, 'student', FILTER_UNSAFE_RAW) ?: '';
$teamId = filter_input(INPUT_GET, 'team', FILTER_UNSAFE_RAW) ?: '';
$student = $studentId ? findById(data('students'), $studentId) : null;
$team = $teamId ? findById(data('teams'), $teamId) : null;
$subject = $student ? $student['name'] . 'さん' : ($team ? '作品「' . $team['game_name'] . '」' : '');
renderHeader('お問い合わせ', 'contact');
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
        <dl><dt>受付内容</dt><dd>採用、面談、インターン、学校説明会、産学連携</dd><dt>現在の対象</dt><dd><?= $subject ? e($subject) : '指定なし' ?></dd></dl>
    </aside>
    <form class="contact-form" action="/contact.php?sent=1" method="get" data-contact-form>
        <input type="hidden" name="sent" value="1">
        <?php if ($student): ?><input type="hidden" name="student_id" value="<?= e($student['id']) ?>"><?php endif; ?>
        <?php if ($team): ?><input type="hidden" name="team_id" value="<?= e($team['id']) ?>"><?php endif; ?>
        <fieldset>
            <legend><span>01</span>お問い合わせの目的 <b>必須</b></legend>
            <?php
            $actions = ['学校の先生に連絡したい','説明会の相談をしたい','この学生と話をしたい','この学生に応募してほしい','この学生に注目している'];
            foreach ($actions as $i => $action): ?>
            <label class="radio-card"><input type="radio" name="action" value="<?= e($action) ?>" <?= $i === 0 ? 'required' : '' ?>><span><?= e($action) ?></span></label>
            <?php endforeach; ?>
        </fieldset>
        <fieldset>
            <legend><span>02</span>企業・ご担当者情報</legend>
            <label>企業名 <b>必須</b><input name="company" required autocomplete="organization"></label>
            <div class="form-row"><label>部署名<input name="department" autocomplete="organization-title"></label><label>役職<input name="position" autocomplete="organization-title"></label></div>
            <div class="form-row"><label>お名前 <b>必須</b><input name="name" required autocomplete="name"></label><label>メールアドレス <b>必須</b><input type="email" name="email" required autocomplete="email"></label></div>
            <div class="form-row"><label>電話番号<input type="tel" name="phone" autocomplete="tel"></label><label>企業Webサイト<input type="url" name="website" placeholder="https://"></label></div>
        </fieldset>
        <fieldset>
            <legend><span>03</span>ご相談内容</legend>
            <label>対象の作品・学生<input name="subject" value="<?= e($subject) ?>" placeholder="作品名または学生名"></label>
            <label>希望連絡時期<input name="preferred_time" placeholder="例：2026年10月上旬"></label>
            <label>メッセージ<textarea name="message" rows="7" placeholder="ご相談内容をご記入ください"></textarea></label>
        </fieldset>
        <label class="privacy-check"><input type="checkbox" name="privacy_agreed" required> <a href="/privacy.php" target="_blank">プライバシーポリシー</a>に同意する <b>必須</b></label>
        <p class="form-note">※ 現在はデモ版のため、入力内容は送信されません。</p>
        <button class="button button-primary submit-button" type="submit">入力内容を確認する <span>→</span></button>
    </form>
</section>
<dialog class="demo-dialog" data-demo-dialog><div><span class="dialog-mark">✓</span><h2>入力を確認しました</h2><p>デモ版のため送信は行われていません。メール送信機能はサーバー仕様確定後に接続できます。</p><button class="button button-primary" type="button" data-dialog-close>閉じる</button></div></dialog>
<?php renderFooter(); ?>
