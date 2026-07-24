<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$students = array_values(array_filter(data('students'), fn(array $s): bool => (bool) ($s['is_active'] ?? false)));
$roles = array_values(array_unique(array_column($students, 'role_ja')));
$grades = array_values(array_unique(array_column($students, 'grade')));
$graduationYears = array_values(array_unique(array_column($students, 'graduation_year')));
renderHeader('学生一覧', 'students');
?>
<section class="page-hero">
    <p class="section-number">STUDENT CREATORS</p>
    <h1>学生を探す</h1>
    <p>職種や技術から、ゲーム制作を担った学生クリエイターを探せます。</p>
</section>
<section class="listing section-pad">
    <div class="filters" data-filter-root>
        <div class="filter-row" aria-label="職種で絞り込み">
            <button class="filter-button is-active" data-role="all">すべて</button>
            <?php foreach ($roles as $role): ?><button class="filter-button" data-role="<?= e($role) ?>"><?= e($role) ?></button><?php endforeach; ?>
        </div>
        <div class="filter-selects">
            <label>学年<select data-filter-field="grade"><option value="">すべて</option><?php foreach ($grades as $value): ?><option><?= e($value) ?></option><?php endforeach; ?></select></label>
            <label>卒業予定年<select data-filter-field="graduation"><option value="">すべて</option><?php foreach ($graduationYears as $value): ?><option><?= e($value) ?></option><?php endforeach; ?></select></label>
            <label>面談・インターン<select data-filter-field="availability"><option value="">すべて</option><option value="interview">面談可</option><option value="internship">インターン希望</option></select></label>
        </div>
        <label class="search-box"><span>⌕</span><input type="search" placeholder="名前・技術で検索" data-search aria-label="名前・技術で検索"></label>
    </div>
    <div class="listing-meta"><span><strong data-result-count><?= count($students) ?></strong> CREATORS</span></div>
    <div class="student-grid student-grid-list" data-student-grid>
        <?php foreach ($students as $student): ?>
        <?php $studentTeamData = studentTeams($student['id']); ?>
        <div data-student data-role="<?= e($student['role_ja']) ?>" data-grade="<?= e($student['grade']) ?>" data-graduation="<?= e($student['graduation_year']) ?>" data-interview="<?= !empty($student['interview_available']) ? '1' : '0' ?>" data-internship="<?= !empty($student['internship_interest']) ? '1' : '0' ?>" data-keywords="<?= e($student['name'] . ' ' . $student['name_kana'] . ' ' . $student['name_en'] . ' ' . implode(' ', $student['skills']) . ' ' . implode(' ', array_column($studentTeamData, 'team_name')) . ' ' . implode(' ', array_column($studentTeamData, 'game_name'))) ?>"><?php studentCard($student); ?></div>
        <?php endforeach; ?>
    </div>
    <p class="no-results" data-no-results hidden>条件に合う学生が見つかりませんでした。</p>
</section>
<?php renderFooter(); ?>
