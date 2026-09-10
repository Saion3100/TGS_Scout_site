<?php
declare(strict_types=1);

/** Resolve a unique ID (ignoring numeric leading zeros) or registered name. */
function studentForLogin(array $students, string $input): ?array
{
    if ($input === '' || $input === 'admin') return null;
    if (preg_match('/^[0-9]+$/D', $input) === 1) {
        $numericId = ltrim($input, '0');
        $matches = array_values(array_filter($students, static function (array $student) use ($numericId): bool {
            $id = (string) ($student['id'] ?? '');
            return preg_match('/^[0-9]+$/D', $id) === 1 && ltrim($id, '0') === $numericId;
        }));
        if ($matches) return count($matches) === 1 ? $matches[0] : null;
    }
    $byId = findById($students, $input);
    if ($byId !== null) return $byId;
    $normalize = static fn(string $name): string => preg_replace('/[\s\x{3000}]+/u', '', $name) ?? '';
    $name = $normalize($input);
    if ($name === '') return null;
    $matches = array_values(array_filter($students, static fn(array $student): bool => $normalize((string) ($student['name'] ?? '')) === $name));
    return count($matches) === 1 ? $matches[0] : null;
}

function isAdminUser(): bool
{
    return !empty($_SESSION['admin_authenticated']) && ($_SESSION['auth_role'] ?? 'admin') === 'admin';
}

function currentStudentId(): string
{
    return ($_SESSION['auth_role'] ?? '') === 'student' && is_string($_SESSION['student_id'] ?? null)
        ? $_SESSION['student_id'] : '';
}

function isManagementAuthenticated(): bool
{
    if (isAdminUser()) return true;
    $id = currentStudentId();
    return $id !== '' && findById(repository('students')->all(), $id) !== null;
}

function requireManagementLogin(): void
{
    if (!isManagementAuthenticated()) {
        $_SESSION = [];
        header('Location: ' . url('admin.php'));
        exit;
    }
}

function requireAdminUser(): void
{
    requireManagementLogin();
    if (!isAdminUser()) redirectToError(403);
}

function canEditStudent(string $id): bool
{
    return isAdminUser() || ($id !== '' && currentStudentId() === $id && isManagementAuthenticated());
}

function canEditTeam(string $id): bool
{
    if (isAdminUser()) return true;
    if (!isManagementAuthenticated()) return false;
    foreach (repository('mapping')->all() as $relation) {
        if ((string) ($relation['student_id'] ?? '') === currentStudentId()
            && (string) ($relation['team_id'] ?? '') === $id) return true;
    }
    return false;
}

function renderManagementTabs(string $active): void
{
    $tabs = ['students' => ['admin.php', isAdminUser() ? '学生' : '自分のプロフィール']];
    if (isAdminUser()) $tabs['featured'] = ['admin.php?section=featured', '注目学生'];
    $tabs['teams'] = ['teams_admin.php', isAdminUser() ? '作品' : '参加作品'];
    if (isAdminUser()) $tabs['exhibited'] = ['exhibited_admin.php', '出展作品'];
    echo '<p class="admin-lead">ログイン中：' . (isAdminUser() ? '管理者' : '学生 ' . e((string) (findById(repository('students')->all(), currentStudentId())['name'] ?? ''))) . '</p>';
    echo '<nav class="admin-tabs" aria-label="管理データ">';
    foreach ($tabs as $key => [$path, $label]) {
        echo '<a href="' . e(url($path)) . '"' . ($key === $active ? ' class="is-active" aria-current="page"' : '') . '>' . e($label) . '</a>';
    }
    echo '</nav>';
}
