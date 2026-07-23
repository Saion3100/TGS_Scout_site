<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$teams = data('teams');
renderHeader('出展作品', 'teams');
?>
<section class="page-hero">
    <p class="section-number">EXHIBITED GAMES</p>
    <h1>作品から探す</h1>
    <p>試遊したゲームから、制作チームと学生の担当領域をご覧いただけます。</p>
</section>
<section class="listing section-pad">
    <div class="listing-meta"><strong><?= count($teams) ?></strong> GAMES <span>TGS 2026 EXHIBITION</span></div>
    <div class="team-grid team-grid-large">
        <?php foreach ($teams as $i => $team): ?>
        <a class="team-card theme-<?= e($team['theme']) ?>" href="/team_detail.php?id=<?= e($team['id']) ?>">
            <div class="game-art">
                <span class="game-number">0<?= $i + 1 ?></span>
                <strong><?= e($team['game_name']) ?></strong>
            </div>
            <div class="team-card-body">
                <span><?= e($team['genre']) ?></span>
                <h2><?= e($team['game_name']) ?></h2>
                <p><?= e($team['catchcopy']) ?></p>
                <small>BOOTH <?= e($team['booth_no']) ?>　/　<?= count(teamMembers($team['id'])) ?> MEMBERS</small>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php renderFooter(); ?>
