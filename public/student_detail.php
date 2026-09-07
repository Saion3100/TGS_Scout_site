<?php
declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';
$id = filter_input(INPUT_GET, 'id', FILTER_UNSAFE_RAW) ?: '';
$student = findById(data('students'), $id);
if (!$student || !isStudentPublic($student)) { http_response_code(404); renderHeader('学生が見つかりません'); ?>
<section class="empty-state"><p>404</p><h1>学生が見つかりません</h1><a class="button button-primary" href="<?= e(url('students.php')) ?>">学生一覧へ戻る</a></section>
<?php renderFooter(); exit; }
$teams = studentTeams($id);
$portfolioPreviewUrl = googleDrivePreviewUrl($student['portfolio_url'] ?? '');
renderHeader($student['name'], 'students');
?>
<section class="profile-hero">
    <div class="breadcrumbs"><a href="<?= e(url()) ?>">HOME</a><span>/</span><a href="<?= e(url('students.php')) ?>">CREATORS</a><span>/</span><?= e($student['name']) ?></div>
    <div class="profile-hero-grid">
        <div class="profile-portrait"><span><?= e(firstCharacter($student['name'])) ?></span><small>CREATOR PROFILE</small><?php if (studentImageUrl($student) !== ''): ?><img class="student-photo" src="<?= e(studentImageUrl($student)) ?>" alt="<?= e($student['name']) ?>" referrerpolicy="no-referrer"><?php endif; ?></div>
        <div class="profile-title">
            <span class="role-label"><?= e($student['role']) ?></span>
            <h1><?= e($student['name']) ?></h1>
            <p class="name-en"><?= e($student['name_en']) ?></p>
            <p class="profile-meta"><?= e($student['name_kana'] ?? '') ?>　/　<?= e($student['graduation_year'] ?? '') ?><br><?= e($student['course'] ?? '') ?></p>
            <h2>“<?= e($student['headline']) ?>”</h2>
            <div class="tag-list"><?php foreach ($student['skills'] as $skill): ?><span><?= e($skill) ?></span><?php endforeach; ?></div>
        </div>
    </div>
</section>
<section class="profile-about section-pad">
    <p class="section-number">01 — PROFILE</p>
    <div class="intro-grid"><h2>プロフィール</h2><p class="large-copy"><?= e($student['bio']) ?></p></div>
</section>
<section class="profile-facts section-pad">
    <p class="section-number">02 — CAREER INFORMATION</p>
    <div class="facts-grid">
        <div><span>希望職種</span><strong><?= e(implode(' / ', $student['desired_roles'] ?? [$student['role']])) ?></strong></div>
        <div><span>面談</span><strong><?= !empty($student['interview_available']) ? '相談可能' : '要相談' ?></strong></div>
        <div><span>インターン</span><strong><?= !empty($student['internship_interest']) ? '希望あり' : '要相談' ?></strong></div>
        <div><span>専門分野</span><strong><?= e(implode(' / ', $student['fields'] ?? [])) ?></strong></div>
    </div>
</section>
<section class="works section-pad">
    <div class="section-head"><div><p class="section-number">03 — WORKS</p><h2>参加作品</h2></div></div>
    <div class="team-grid"><?php foreach ($teams as $i => $team): ?>
        <a class="team-card compact theme-<?= e($team['theme']) ?>" href="<?= e(url('team_detail.php')) ?>?id=<?= e($team['id']) ?>">
            <div class="game-art"><strong><?= e($team['game_name']) ?></strong></div>
            <div class="team-card-body"><span><?= e($team['student_role']) ?></span><h3><?= e($team['game_name']) ?></h3><p><?= e($team['genre']) ?></p></div>
        </a>
    <?php endforeach; ?></div>
</section>
<section class="portfolio section-pad">
    <p class="section-number">04 — PORTFOLIO</p>
    <div class="section-head"><h2>ポートフォリオ</h2>
    <div class="resource-actions">
    <?php if (!empty($student['portfolio_url'])): ?><a class="button button-primary" href="<?= e($student['portfolio_url']) ?>" target="_blank" rel="noopener">ポートフォリオを見る <span>↗</span></a><?php endif; ?>
    <?php if (!empty($student['source_code_url'])): ?><a class="button button-outline" href="<?= e($student['source_code_url']) ?>" target="_blank" rel="noopener">ソースコードを見る <span>↗</span></a><?php endif; ?>
    </div>
    </div>
    <?php if ($portfolioPreviewUrl !== ''): ?><iframe class="portfolio-frame" src="<?= e($portfolioPreviewUrl) ?>" title="<?= e($student['name']) ?>のポートフォリオ" loading="lazy"></iframe>
    <?php else: ?><div class="portfolio-placeholder"><span>PORTFOLIO PREVIEW</span><p>公開資料はリンクからご覧ください。</p></div><?php endif; ?>
</section>
<section class="detail-cta"><p><?= e($student['name']) ?>さんについて話を聞きたい</p><a class="button button-primary" href="<?= e(url('contact.php')) ?>?student=<?= e($student['id']) ?>">学校へ問い合わせる <span>→</span></a></section>
<a class="detail-sticky-cta" href="<?= e(url('contact.php')) ?>?student=<?= e($student['id']) ?>" aria-label="<?= e($student['name']) ?>さんについて学校へ相談する">
    <span><small>気になる学生が見つかったら</small>この学生について相談</span><b aria-hidden="true">→</b>
</a>
<?php renderFooter(); ?>
