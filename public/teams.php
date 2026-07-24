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
    <div class="filters" data-team-filter-root>
        <div class="filter-selects">
            <label>ジャンル<select data-team-field="genre"><option value="">すべて</option><?php foreach (array_unique(array_column($teams, 'genre')) as $value): ?><option><?= e($value) ?></option><?php endforeach; ?></select></label>
            <label>エンジン<select data-team-field="engine"><option value="">すべて</option><?php foreach (array_unique(array_column($teams, 'engine')) as $value): ?><option><?= e($value) ?></option><?php endforeach; ?></select></label>
            <label>対応機種<select data-team-field="platform"><option value="">すべて</option><?php foreach (array_unique(array_merge(...array_column($teams, 'platforms'))) as $value): ?><option><?= e($value) ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="search-box"><span>⌕</span><input type="search" placeholder="チーム名・ゲーム名・試遊台番号" data-team-search></label>
    </div>
    <div class="listing-meta"><strong><?= count($teams) ?></strong> GAMES <span>TGS 2026 EXHIBITION</span></div>
    <div class="team-grid team-grid-large">
        <?php foreach ($teams as $i => $team): ?>
        <a class="team-card theme-<?= e($team['theme']) ?>" data-team data-genre="<?= e($team['genre']) ?>" data-engine="<?= e($team['engine']) ?>" data-platform="<?= e(implode(' ', $team['platforms'])) ?>" data-keywords="<?= e($team['team_name'] . ' ' . $team['game_name'] . ' ' . $team['booth_no']) ?>" href="/team_detail.php?id=<?= e($team['id']) ?>">
            <div class="game-art">
                <span class="game-number">0<?= $i + 1 ?></span>
                <strong><?= e($team['game_name']) ?></strong>
            </div>
            <div class="team-card-body">
                <span><?= e($team['genre']) ?></span>
                <h2><?= e($team['game_name']) ?></h2><p class="team-name"><?= e($team['team_name']) ?></p>
                <p><?= e($team['catchcopy']) ?></p>
                <small>BOOTH <?= e($team['booth_no']) ?>　/　<?= count(teamMembers($team['id'])) ?> MEMBERS</small>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <p class="no-results" data-team-no-results hidden>条件に合う作品が見つかりませんでした。</p>
</section>
<?php renderFooter(); ?>
