<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$students = array_values(array_filter(data('students'), fn(array $s): bool => (bool) ($s['is_active'] ?? false)));
$roles = array_values(array_unique(array_column($students, 'role_ja')));
renderHeader('職種別一覧', 'roles');
?>
<section class="page-hero">
    <p class="section-number">CREATORS BY ROLE</p>
    <h1>職種から探す</h1>
    <p>専門領域ごとに、出展作品を制作した学生と成果物を確認できます。</p>
</section>
<?php foreach ($roles as $i => $role): $roleStudents = array_values(array_filter($students, fn(array $s): bool => $s['role_ja'] === $role)); ?>
<section class="role-section section-pad <?= $i % 2 ? 'soft-section' : '' ?>">
    <div class="section-head"><div><p class="section-number">0<?= $i + 1 ?> — <?= e(strtoupper($roleStudents[0]['role'])) ?></p><h2><?= e($role) ?></h2></div><span class="count-label"><?= count($roleStudents) ?> CREATORS</span></div>
    <div class="student-grid"><?php foreach ($roleStudents as $student) studentCard($student); ?></div>
</section>
<?php endforeach; ?>
<?php renderFooter(); ?>
