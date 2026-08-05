<?php
declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';
$id = filter_input(INPUT_GET, 'id', FILTER_UNSAFE_RAW) ?: '';
$team = findById(data('teams'), $id);
if (!$team) { http_response_code(404); renderHeader('作品が見つかりません'); ?>
<section class="empty-state"><p>404</p><h1>作品が見つかりません</h1><a class="button button-primary" href="<?= e(url('teams.php')) ?>">作品一覧へ戻る</a></section>
<?php renderFooter(); exit; }
$members = teamMembers($id);
renderHeader($team['game_name'], 'teams');
?>
<section class="detail-hero theme-<?= e($team['theme']) ?>">
    <div class="breadcrumbs"><a href="<?= e(url()) ?>">HOME</a><span>/</span><a href="<?= e(url('teams.php')) ?>">GAMES</a><span>/</span><?= e($team['game_name']) ?></div>
    <div class="detail-hero-grid">
        <div>
            <p class="kicker"><span></span><?= e($team['genre']) ?></p>
            <h1><?= e($team['game_name']) ?></h1>
            <p class="game-catch"><?= e($team['catchcopy']) ?></p>
            <div class="booth-chip">TGS 2026　BOOTH <strong><?= e($team['booth_no']) ?></strong></div>
        </div>
        <div class="detail-art"><strong><?= e($team['game_name']) ?></strong><span>PLAYABLE AT TGS 2026</span></div>
    </div>
</section>
<section class="game-facts section-pad">
    <div class="facts-grid">
        <div><span>制作チーム</span><strong><?= e($team['team_name']) ?></strong></div>
        <div><span>プレイ人数</span><strong><?= e($team['players']) ?></strong></div>
        <div><span>対応機種</span><strong><?= e(implode(' / ', $team['platforms'])) ?></strong></div>
        <div><span>使用エンジン</span><strong><?= e($team['engine']) ?></strong></div>
        <div><span>制作期間</span><strong><?= e($team['production_period']) ?></strong></div>
        <div><span>試遊台</span><strong><?= e($team['booth_no']) ?></strong></div>
    </div>
</section>
<section class="game-about section-pad">
    <p class="section-number">01 — ABOUT THE GAME</p>
    <div class="intro-grid">
        <h2>ゲームについて</h2>
        <div><p class="large-copy"><?= e($team['description']) ?></p>
            <ul class="highlight-list"><?php foreach ($team['highlights'] as $highlight): ?><li><?= e($highlight) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
</section>
<section class="controls section-pad">
    <p class="section-number">02 — HOW TO PLAY</p>
    <div class="intro-grid"><h2>操作方法</h2><ul class="control-list"><?php foreach ($team['controls'] as $control): ?><li><?= e($control) ?></li><?php endforeach; ?></ul></div>
</section>
<?php if (!empty($team['video_url'])): ?>
<section class="media-section section-pad"><iframe src="<?= e($team['video_url']) ?>" title="<?= e($team['game_name']) ?> 紹介動画" loading="lazy" allowfullscreen></iframe></section>
<?php endif; ?>
<section class="members section-pad">
    <div class="section-head"><div><p class="section-number">03 — TEAM MEMBERS</p><h2>この作品をつくった学生</h2></div><span class="count-label"><?= count($members) ?> CREATORS</span></div>
    <div class="student-grid team-member-grid">
        <?php foreach ($members as $member): ?>
        <div class="team-member-item">
            <p class="member-role"><?= e($member['team_role_group']) ?> / <?= e($member['team_role']) ?></p>
            <p class="member-duty"><?= e($member['team_responsibility']) ?></p>
            <?php studentCard($member); ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<section class="detail-cta"><p>この作品について詳しく聞きたい</p><a class="button button-primary" href="<?= e(url('contact.php')) ?>?team=<?= e($team['id']) ?>">学校へ問い合わせる <span>→</span></a></section>
<a class="detail-sticky-cta" href="<?= e(url('contact.php')) ?>?team=<?= e($team['id']) ?>" aria-label="作品「<?= e($team['game_name']) ?>」について学校へ相談する">
    <span><small>気になる作品が見つかったら</small>この作品について相談</span><b aria-hidden="true">→</b>
</a>
<?php renderFooter(); ?>
