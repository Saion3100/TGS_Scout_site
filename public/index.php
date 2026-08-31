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
$heroSlides = [];
foreach (array_slice($featuredStudents ?: $students, 0, 2) as $student) {
    $heroSlides[] = ['type' => 'student', 'eyebrow' => 'FEATURED STUDENT', 'title' => $student['name'], 'meta' => $student['role'] . ' / ' . ($student['graduation_year'] ?? ''), 'label' => firstCharacter($student['name']), 'image' => $student['photo_url'] ?? '', 'href' => url('student_detail.php') . '?id=' . rawurlencode((string) $student['id']), 'theme' => 'red'];
}
foreach (array_slice($teams, 0, 3) as $team) {
    $heroSlides[] = ['type' => 'work', 'eyebrow' => 'TGS 2026 EXHIBITION', 'title' => $team['game_name'], 'meta' => $team['genre'] . ' / BOOTH ' . $team['booth_no'], 'label' => $team['game_name'], 'image' => $team['screenshots'][0] ?? '', 'href' => url('team_detail.php') . '?id=' . rawurlencode((string) $team['id']), 'theme' => $team['theme'] ?? 'red'];
}
renderHeader();
?>
<section class="home-hero">
    <div class="hero-copy">
        <a class="official-label" href="https://www.itc.ac.jp/" target="_blank" rel="noopener noreferrer">国際理工カレッジ公式 <span>↗</span></a>
        <p class="kicker"><span></span>TGS SCOUT 2026</p>
        <h1>国際理工カレッジ<br><em>関係者向け学生紹介サイト</em></h1>
        <p class="lead">国際理工カレッジのTGS2026出展作品とその制作学生、当日出展はできなかった作品を企業関係者向けにご紹介します。作品、担当箇所、技術、ポートフォリオをご覧いただけます。</p>
        <div class="hero-actions">
            <a class="button button-primary" href="#featured-students">注目学生を見る <span>↓</span></a>
            <a class="text-link" href="<?= e(url('teams.php')) ?>">TGS出展作品を見る <span>→</span></a>
        </div>
    </div>
    <div class="hero-carousel" data-hero-carousel aria-roledescription="カルーセル" aria-label="注目学生とTGS出展作品">
        <div class="hero-carousel-track">
            <?php foreach ($heroSlides as $index => $slide): ?>
            <a class="hero-slide theme-<?= e($slide['theme']) ?><?= $index === 0 ? ' is-active' : '' ?>" href="<?= e($slide['href']) ?>" data-hero-slide aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>" tabindex="<?= $index === 0 ? '0' : '-1' ?>">
                <div class="hero-slide-media">
                    <?php if ($slide['image'] !== ''): ?><img src="<?= e($slide['image']) ?>" alt=""><?php else: ?><span class="hero-slide-placeholder hero-slide-placeholder-<?= e($slide['type']) ?>"><?= e($slide['label']) ?></span><?php endif; ?>
                </div>
                <div class="hero-slide-copy"><span><?= e($slide['eyebrow']) ?></span><strong><?= e($slide['title']) ?></strong><small><?= e($slide['meta']) ?></small><b aria-hidden="true">VIEW ↗</b></div>
            </a>
            <?php endforeach; ?>
        </div>
        <span class="visual-badge">TGS<br>2026</span>
        <div class="hero-carousel-controls">
            <button type="button" data-hero-prev aria-label="前のスライド">←</button>
            <div class="hero-carousel-dots" aria-label="スライドを選択"><?php foreach ($heroSlides as $index => $slide): ?><button type="button" class="<?= $index === 0 ? 'is-active' : '' ?>" data-hero-dot="<?= $index ?>" aria-label="<?= $index + 1 ?>枚目を表示" aria-current="<?= $index === 0 ? 'true' : 'false' ?>"></button><?php endforeach; ?></div>
            <button type="button" data-hero-next aria-label="次のスライド">→</button>
        </div>
        <div class="hero-carousel-progress" aria-hidden="true"><span></span></div>
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

<section id="featured-students" class="people featured-students section-pad">
    <div class="section-head">
        <div>
            <p class="section-number">02 — TEACHER'S PICK</p>
            <h2>まずは、この学生から。</h2>
            <p class="section-description">教員が制作への取り組みと今後の成長に注目する、学生クリエイターです。</p>
        </div>
        <a class="text-link" href="<?= e(url('students.php')) ?>">すべての学生を見る <span>→</span></a>
    </div>
    <div class="featured-student-grid">
        <?php foreach (array_slice($featuredStudents, 0, 2) as $student): ?>
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
        <div><p class="section-number">03 — FEATURED GAMES</p><h2>TGS出展作品</h2></div>
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
            <p class="section-number">04 — FIND CREATORS</p>
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
