<?php
declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';
$teams = data('teams');
$students = array_values(array_filter(data('students'), fn(array $s): bool => (bool) ($s['is_active'] ?? false)));
renderHeader();
?>
<section class="home-hero">
    <div class="hero-copy">
        <p class="kicker"><span></span>TOKYO GAME SHOW 2026</p>
        <h1>つくったゲームから、<br><em>つくった人</em>へ。</h1>
        <p class="lead">試遊で感じた「おもしろい」の先にいる、学生クリエイターの技術と実績を紹介します。</p>
        <div class="hero-actions">
            <a class="button button-primary" href="<?= e(url('teams.php')) ?>">作品から探す <span>→</span></a>
            <a class="text-link" href="<?= e(url('students.php')) ?>">職種・技術から学生を探す <span>↗</span></a>
        </div>
    </div>
    <div class="hero-visual" aria-label="TGS出展学生作品">
        <div class="visual-type">PLAY<br><span>MEET</span><br>CREATE</div>
        <span class="visual-badge">TGS<br>2026</span>
        <div class="visual-caption">STUDENT GAME CREATORS<br>PORTFOLIO DIRECTORY</div>
    </div>
    <div class="scroll-cue">SCROLL <span>↓</span></div>
</section>

<section class="intro section-pad">
    <p class="section-number">01 — ABOUT</p>
    <div class="intro-grid">
        <h2>作品の裏側にいる、<br>一人ひとりの力を見る。</h2>
        <div>
            <p>TGS SCOUTは、展示ゲームを起点に学生の担当箇所や技術、ポートフォリオへつながる企業担当者向けサイトです。</p>
            <p>気になる学生へのご連絡は学校が窓口となり、面談・採用・インターンのご相談をおつなぎします。</p>
        </div>
    </div>
</section>

<section class="featured section-pad">
    <div class="section-head">
        <div><p class="section-number">02 — FEATURED GAMES</p><h2>出展作品</h2></div>
        <a class="text-link" href="<?= e(url('teams.php')) ?>">すべての作品を見る <span>→</span></a>
    </div>
    <div class="team-grid">
        <?php foreach ($teams as $i => $team): ?>
        <a class="team-card theme-<?= e($team['theme']) ?>" href="<?= e(url('team_detail.php')) ?>?id=<?= e($team['id']) ?>">
            <div class="game-art">
                <span class="game-number">0<?= $i + 1 ?></span>
                <strong><?= e($team['game_name']) ?></strong>
            </div>
            <div class="team-card-body">
                <span><?= e($team['genre']) ?></span>
                <h3><?= e($team['game_name']) ?></h3>
                <p><?= e($team['catchcopy']) ?></p>
                <small>BOOTH <?= e($team['booth_no']) ?></small>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="people section-pad">
    <div class="section-head">
        <div><p class="section-number">03 — CREATORS</p><h2>学生クリエイター</h2></div>
        <a class="text-link" href="<?= e(url('students.php')) ?>">すべての学生を見る <span>→</span></a>
    </div>
    <div class="student-grid">
        <?php foreach (array_slice($students, 0, 4) as $student) studentCard($student); ?>
    </div>
</section>

<section class="contact-band">
    <p>FOR RECRUITERS / COMPANIES</p>
    <h2>気になる作品・学生が<br>見つかりましたか？</h2>
    <a class="button button-white" href="<?= e(url('contact.php')) ?>">学校へ問い合わせる <span>→</span></a>
</section>
<?php renderFooter(); ?>
