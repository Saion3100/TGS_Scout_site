<?php
declare(strict_types=1);
$_SERVER['SCRIPT_NAME'] = '/scout/student_detail.php';
require dirname(__DIR__) . '/src/layout.php';

$cases = [
    ['students.php', [], 'students'],
    ['student_detail.php', ['id' => '5'], 'students/5'],
    ['team_detail.php', ['id' => 't01', 'from' => 'qr'], 'teams/t01?from=qr'],
    ['contact.php', ['student' => '5'], 'contact/student/5'],
    ['contact.php', ['team' => 't01'], 'contact/team/t01'],
    ['qr.php', ['code' => 'student-5'], 'qr/student-5'],
    ['admin.php', ['id' => '5', 'saved' => '1'], 'admin?id=5&saved=1'],
    ['student_detail.php', [], 'student_detail.php'],
    ['student_detail.php', ['id' => '../bad'], 'student_detail.php?id=..%2Fbad'],
    ['student_detail.php', ['id' => ['5']], 'student_detail.php?id%5B0%5D=5'],
    ['assets/style.css', [], 'assets/style.css'],
    ['api/scouts.php', [], 'api/scouts.php'],
    ['index.php', [], ''],
];
foreach ($cases as [$path, $query, $expected]) {
    if (routePath($path, $query) !== $expected) throw new RuntimeException($path);
    if (url($path, $query) !== '/scout/' . $expected) throw new RuntimeException('Base path: ' . $path);
}
echo "Route checks passed.\n";
