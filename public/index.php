<?php
declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';
$teams = data('teams');
$students = array_values(array_filter(data('students'), fn(array $s): bool => (bool) ($s['is_active'] ?? false)));
$featuredEntries = data('featured_students');
usort($featuredEntries, fn(array $a, array $b): int => $a['order'] <=> $b['order']);
$featuredStudents = [];
foreach ($featuredEntries as $entry) {
    $student = findById($students, $entry['student_id']);
    if ($student) {
        $student['featured_focus'] = $entry['focus'];
        $student['teacher_comment'] = $entry['teacher_comment'];
        $featuredStudents[] = $student;
    }
}
renderHeader();
?>
<section class="home-hero">
    <div class="hero-copy">
        <a class="official-label" href="https://www.itc.ac.jp/" target="_blank" rel="noopener noreferrer">国際理工カレッジ公式 <span>↗</span></a>
        <p class="kicker"><span></span>TGS2026 企業関係者向け 学生紹介サイト</p>
        <h1>未来をつくる学生と、<br><em>今、つながる。</em></h1>
        <p class="lead">国際理工カレッジが、TGS2026出展作品とその制作学生を企業関係者向けにご紹介します。作品、担当箇所、技術、ポートフォリオをご覧いただけます。</p>
        <div class="hero-actions">
            <a class="button button-primary" href="#featured-students">注目学生を見る <span>↓</span></a>
            <a class="text-link" href="<?= e(url('teams.php')) ?>">TGS出展作品を見る <span>→</span></a>
        </div>
    </div>
    <div class="hero-visual" aria-label="TGS出展学生作品">
        <div class="visual-type">PLAY<br><span>MEET</span><br>CREATE</div>
        <span class="visual-badge">TGS<br>2026</span>
        <div class="visual-caption">STUDENT GAME CREATORS<br>PORTFOLIO DIRECTORY</div>
    </div>
    <div class="scroll-cue">SCROLL <span>→</span></div>
</section>

<section class="value-section section-pad" aria-labelledby="value-title">
    <div class="section-head value-heading">
        <div>
            <p class="section-number">01 — WHY TGS SCOUT</p>
            <h2 id="value-title">学校公式だからできる、<br>一足早い出会い。</h2>
        </div>
        <p>作品を見て終わるのではなく、担当した学生の技術と実績を確認し、学校を通じて面談・採用・インターンをご相談いただけます。</p>
    </div>
    <div class="value-grid">
        <article>
            <span>01 / SCHOOL OFFICIAL</span>
            <h3>学校が直接紹介</h3>
            <p>国際理工カレッジが学生情報を確認して掲載。ご連絡も学校が窓口となります。</p>
        </article>
        <article>
            <span>02 / FOR TGS2026 COMPANIES</span>
            <h3>企業関係者向け</h3>
            <p>TGS2026でご案内する企業関係者向けの学生紹介サイトです。</p>
        </article>
        <article>
            <span>03 / EARLY ACCESS</span>
            <h3>28卒・29卒と早期接点</h3>
            <p>就職活動が本格化する前の学生クリエイターを、作品と実績からご覧いただけます。</p>
        </article>
    </div>
</section>

<section class="intro section-pad">
    <p class="section-number">02 — CONCEPT</p>
    <div class="intro-grid">
        <h2>つくったゲームから、<br><em>つくった人</em>へ。</h2>
        <div>
            <p>試遊で感じた「おもしろい」の先にいる、一人ひとりの力を見る。TGS SCOUTは、展示ゲームを起点に学生の担当箇所や技術、ポートフォリオへつながるサイトです。</p>
            <p>気になる学生へのご連絡は学校が窓口となり、面談・採用・インターンのご相談をおつなぎします。</p>
        </div>
    </div>
</section>

<section id="featured-students" class="people featured-students section-pad">
    <div class="section-head">
        <div>
            <p class="section-number">03 — TEACHER'S PICK</p>
            <h2>まずは、この学生から。</h2>
            <p class="section-description">教員が制作への取り組みと今後の成長に注目する、28卒・29卒の学生クリエイターです。</p>
        </div>
        <a class="text-link" href="<?= e(url('students.php')) ?>">すべての学生を見る <span>→</span></a>
    </div>
    <div class="featured-student-grid">
        <?php foreach (array_slice($featuredStudents, 0, 3) as $student): ?>
        <article class="featured-student">
            <?php studentCard($student); ?>
            <div class="teacher-note">
                <span>TEACHER'S NOTE</span>
                <strong><?= e($student['featured_focus']) ?></strong>
                <p><?= e($student['teacher_comment']) ?></p>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="featured section-pad">
    <div class="section-head">
        <div><p class="section-number">04 — FEATURED GAMES</p><h2>TGS出展作品</h2></div>
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

<section class="student-search-cta section-pad">
    <div class="section-head">
        <div>
            <p class="section-number">05 — FIND CREATORS</p>
            <h2>求める職種・技術から、<br>学生を探す。</h2>
        </div>
        <div class="search-cta-copy">
            <p>プログラマー、デザイナー、プランナーなどの職種や、卒業予定年、使用技術から学生を絞り込めます。</p>
            <a class="button button-primary" href="<?= e(url('students.php')) ?>">学生を探す <span>→</span></a>
        </div>
    </div>
</section>

<section class="contact-band">
    <p>FOR RECRUITERS / COMPANIES</p>
    <h2>気になる作品・学生が<br>見つかりましたか？</h2>
    <a class="button button-white" href="<?= e(url('contact.php')) ?>">学校へ問い合わせる <span>→</span></a>
</section>
<?php renderFooter(); ?>
