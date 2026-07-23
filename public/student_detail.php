<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$id = filter_input(INPUT_GET, 'id', FILTER_UNSAFE_RAW) ?: '';
$student = findById(data('students'), $id);
if (!$student || !($student['is_active'] ?? false)) { http_response_code(404); renderHeader('学生が見つかりません'); ?>
<section class="empty-state"><p>404</p><h1>学生が見つかりません</h1><a class="button button-primary" href="/students.php">学生一覧へ戻る</a></section>
<?php renderFooter(); exit; }
$teams = studentTeams($id);
renderHeader($student['name'], 'students');
?>
<section class="profile-hero">
    <div class="breadcrumbs"><a href="/">HOME</a><span>/</span><a href="/students.php">CREATORS</a><span>/</span><?= e($student['name']) ?></div>
    <div class="profile-hero-grid">
        <div class="profile-portrait"><span><?= e(mb_substr($student['name'], 0, 1)) ?></span><small>CREATOR PROFILE</small></div>
        <div class="profile-title">
            <span class="role-label"><?= e($student['role_ja']) ?></span>
            <h1><?= e($student['name']) ?></h1>
            <p class="name-en"><?= e($student['name_en']) ?></p>
            <h2>“<?= e($student['headline']) ?>”</h2>
            <div class="tag-list"><?php foreach ($student['skills'] as $skill): ?><span><?= e($skill) ?></span><?php endforeach; ?></div>
        </div>
    </div>
</section>
<section class="profile-about section-pad">
    <p class="section-number">01 — PROFILE</p>
    <div class="intro-grid"><h2>プロフィール</h2><p class="large-copy"><?= e($student['bio']) ?></p></div>
</section>
<section class="responsibilities section-pad">
    <p class="section-number">02 — RESPONSIBILITIES</p>
    <div class="intro-grid"><h2>担当したこと</h2><ol><?php foreach ($student['responsibilities'] as $i => $item): ?><li><span>0<?= $i + 1 ?></span><?= e($item) ?></li><?php endforeach; ?></ol></div>
</section>
<section class="works section-pad">
    <div class="section-head"><div><p class="section-number">03 — WORKS</p><h2>参加作品</h2></div></div>
    <div class="team-grid"><?php foreach ($teams as $i => $team): ?>
        <a class="team-card compact theme-<?= e($team['theme']) ?>" href="/team_detail.php?id=<?= e($team['id']) ?>">
            <div class="game-art"><strong><?= e($team['game_name']) ?></strong></div>
            <div class="team-card-body"><span><?= e($team['student_role']) ?></span><h3><?= e($team['game_name']) ?></h3><p><?= e($team['genre']) ?></p></div>
        </a>
    <?php endforeach; ?></div>
</section>
<section class="portfolio section-pad">
    <p class="section-number">04 — PORTFOLIO</p>
    <div class="section-head"><h2>ポートフォリオ</h2>
    <?php if (!empty($student['vivivit_url'])): ?><a class="button button-primary" href="<?= e($student['vivivit_url']) ?>" target="_blank" rel="noopener">VIVIVITで見る <span>↗</span></a>
    <?php elseif (!empty($student['portfolio_url'])): ?><a class="button button-primary" href="<?= e($student['portfolio_url']) ?>" target="_blank" rel="noopener">資料を別画面で見る <span>↗</span></a><?php endif; ?></div>
    <?php if (!empty($student['drive_pdf_id'])): ?><iframe class="portfolio-frame" src="https://drive.google.com/file/d/<?= e($student['drive_pdf_id']) ?>/preview" title="<?= e($student['name']) ?>のポートフォリオ" loading="lazy"></iframe>
    <?php else: ?><div class="portfolio-placeholder"><span>PORTFOLIO PREVIEW</span><p>公開資料はリンクからご覧ください。</p></div><?php endif; ?>
</section>
<section class="detail-cta"><p><?= e($student['name']) ?>さんについて話を聞きたい</p><a class="button button-primary" href="/contact.php?student=<?= e($student['id']) ?>">学校へ問い合わせる <span>→</span></a></section>
<?php renderFooter(); ?>
