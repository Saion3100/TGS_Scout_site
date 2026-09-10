<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/errorHandling.php';
require_once __DIR__ . '/JsonRepository.php';
require_once __DIR__ . '/StudentPhoto.php';

// Recheck publication status on each request, including after back navigation.
header('Cache-Control: no-store, max-age=0');

const DATA_DIR = __DIR__ . '/../data';

function loadLocalEnvironment(string $filePath): void
{
    if (!is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if ($name === '' || preg_match('/^[A-Z_][A-Z0-9_]*$/i', $name) !== 1 || getenv($name) !== false) {
            continue;
        }
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

loadLocalEnvironment(__DIR__ . '/../.env');

// The standalone error page does not load bootstrap, preventing redirect loops.
if (PHP_SAPI !== 'cli' && filter_var(getenv('TGS_MAINTENANCE'), FILTER_VALIDATE_BOOLEAN)) {
    redirectToError(503);
}

function data(string $name): array
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $cache[$name] = (new JsonRepository(DATA_DIR . '/' . $name . '.json'))->all();
    }
    return $cache[$name];
}

function valueList($value): array
{
    $items = is_array($value) ? $value : [$value];
    return array_values(array_unique(array_filter(array_map(
        static fn($item): string => is_scalar($item) ? trim((string) $item) : '',
        $items
    ), static fn(string $item): bool => $item !== '')));
}

function listText($value): string
{
    return implode(' / ', valueList($value));
}

function teamDevelopmentPeriod(array $team): string
{
    $format = static function (string $value): string {
        if (preg_match('/^\d{4}$/', $value)) return $value . '年';
        $month = DateTimeImmutable::createFromFormat('!Y-m', $value);
        if ($month && $month->format('Y-m') === $value) return $month->format('Y年n月');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date->format('Y年n月j日') : $value;
    };
    $start = $format(trim((string) ($team['development_start_date'] ?? '')));
    $endValue = trim((string) ($team['development_end_date'] ?? ''));
    $end = $endValue === '' ? '開発中' : $format($endValue);
    return $start === '' ? $end : $start . '〜' . $end;
}

function teamFilterOptions(array $teams, string $field): array
{
    $options = [];
    foreach ($teams as $team) {
        $options = array_merge($options, valueList($team[$field] ?? []));
    }
    return array_values(array_unique($options));
}
function repository(string $name): JsonRepository
{
    if (!in_array($name, ['students', 'teams', 'featured_students', 'qr_codes', 'mapping'], true)) {
        throw new InvalidArgumentException('Unsupported data type.');
    }
    return new JsonRepository(DATA_DIR . '/' . $name . '.json');
}

function absoluteUrl(string $path, array $query = []): string
{
    $configuredBaseUrl = trim((string) getenv('TGS_PUBLIC_BASE_URL'));
    $baseUrl = $configuredBaseUrl !== ''
        ? rtrim($configuredBaseUrl, '/')
        : 'https://r1u2.v2011.coreserver.jp/it-work/TGS_Scout';
    return $baseUrl . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : '');
}

function syncManagedQr(string $id, string $label, string $targetUrl, bool $isActive): void
{
    $repo = repository('qr_codes');
    $items = $repo->all();
    $record = ['id' => $id, 'label' => $label, 'target_url' => $targetUrl, 'is_active' => $isActive, 'updated_at' => date(DATE_ATOM)];
    foreach ($items as $index => $item) {
        if ((string) ($item['id'] ?? '') === $id) {
            $items[$index] = $record;
            $repo->replaceAll($items);
            return;
        }
    }
    if ($isActive) {
        $items[] = $record;
        $repo->replaceAll($items);
    }
}

function managedQrUrl(string $id): string
{
    return absoluteUrl('qr.php', ['code' => $id]);
}

function firstCharacter(string $value): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 1, 'UTF-8');
    }

    return preg_match('/^./us', $value, $matches) === 1 ? $matches[0] : '';
}

function findById(array $items, string $id): ?array
{
    foreach ($items as $item) {
        if (($item['id'] ?? null) === $id) {
            return $item;
        }
    }
    return null;
}

function isStudentPublic(array $student): bool
{
    return in_array($student['is_active'] ?? false, [true, 1, '1'], true);
}

function publicStudents(): array
{
    return array_values(array_filter(data('students'), 'isStudentPublic'));
}
function teamMembers(string $teamId): array
{
    $students = data('students');
    $members = [];
    foreach (data('mapping') as $relation) {
        if ($relation['team_id'] !== $teamId) {
            continue;
        }
        $student = findById($students, $relation['student_id']);
        if ($student && isStudentPublic($student)) {
            $student['team_role'] = $relation['role'];
            $student['team_role_group'] = $relation['role_group'] ?? $student['role'];
            $student['team_responsibility'] = $relation['responsibility'] ?? '';
            $members[] = $student;
        }
    }
    return $members;
}

function studentTeams(string $studentId): array
{
    $teams = data('teams');
    $result = [];
    foreach (data('mapping') as $relation) {
        if ($relation['student_id'] !== $studentId) {
            continue;
        }
        $team = findById($teams, $relation['team_id']);
        if ($team) {
            $team['student_role'] = $relation['role'];
            $result[] = $team;
        }
    }
    return $result;
}

function studentImageUrl(array $student): string
{
    if (!empty($student['photo_drive_file_id'])) return url('student_photo.php') . '?id=' . rawurlencode((string) $student['id']);
    return imageSourceUrl((string) ($student['photo_url'] ?? $student['icon_url'] ?? ''));
}

function teamThumbnail(array $team, bool $lazy = true): void
{
    $source = imageSourceUrl((string) ($team['thumbnail'] ?? ''));
    if ($source === '') return;
    ?>
    <img class="team-thumbnail" src="<?= e($source) ?>" alt="<?= e($team['game_name']) ?>"<?= $lazy ? ' loading="lazy"' : '' ?> referrerpolicy="no-referrer">
    <?php
}

function imageSourceUrl(string $source): string
{
    $source = trim($source);
    $parts = parse_url($source);
    if (($parts['host'] ?? '') === 'drive.google.com') {
        parse_str($parts['query'] ?? '', $query);
        $id = $query['id'] ?? '';
        if (preg_match('~^/file/d/([A-Za-z0-9_-]+)~', $parts['path'] ?? '', $matches)) {
            $id = $matches[1];
        }
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]+$/D', $id)
            ? 'https://drive.google.com/thumbnail?id=' . rawurlencode($id) . '&sz=w800'
            : '';
    }
    return $source;
}

function googleDrivePreviewUrl(string $url): string
{
    $parts = parse_url(trim($url));
    if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) return '';
    $host = strtolower($parts['host'] ?? '');
    $path = $parts['path'] ?? '';
    if ($host === 'docs.google.com' && preg_match('~^/presentation/d/([A-Za-z0-9_-]+)(?:/|$)~', $path, $matches)) {
        return 'https://docs.google.com/presentation/d/' . $matches[1] . '/embed';
    }
    if ($host !== 'drive.google.com') return '';
    parse_str($parts['query'] ?? '', $query);
    $id = in_array($path, ['/open', '/uc'], true) ? ($query['id'] ?? '') : '';
    if (preg_match('~^/file/d/([A-Za-z0-9_-]+)(?:/|$)~', $path, $matches)) $id = $matches[1];
    if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]+$/D', $id)) return '';
    $preview = 'https://drive.google.com/file/d/' . $id . '/preview';
    if (isset($query['resourcekey']) && is_string($query['resourcekey'])) $preview .= '?resourcekey=' . rawurlencode($query['resourcekey']);
    return $preview;
}

/** @return array<string, string> */
function studentResourceFields(): array
{
    return [
        'portfolio_drive_url' => 'ポートフォリオ（Google Drive）',
        'portfolio_site_url' => 'ポートフォリオ（外部サイト）',
        'source_code_drive_url' => 'ソースコード（Google Drive）',
        'source_code_site_url' => 'ソースコード（外部サイト）',
        'public_work_url' => '公開可能な作品',
    ];
}

function studentResources(array $student): array
{
    $resources = [];
    foreach (studentResourceFields() as $key => $label) {
        $url = trim((string) ($student[$key] ?? ''));
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $url)) continue;
        if (in_array($key, ['portfolio_drive_url', 'source_code_drive_url'], true) && !in_array(strtolower(parse_url($url, PHP_URL_HOST) ?: ''), ['drive.google.com', 'docs.google.com'], true)) {
            $label = str_replace('Google Drive', '外部サイト', $label);
        }
        $group = strpos($key, 'portfolio') === 0 ? 'ポートフォリオ' : (strpos($key, 'source_code') === 0 ? 'ソースコード' : '公開可能な作品');
        $isGoogleDrive = in_array(strtolower(parse_url($url, PHP_URL_HOST) ?: ''), ['drive.google.com', 'docs.google.com'], true);
        $resources[] = ['label' => $label, 'group' => $group, 'link_label' => $key === 'public_work_url' ? '作品を見る' : ($isGoogleDrive ? 'Google Driveで見る' : '外部サイトで見る'), 'url' => $url, 'preview' => strpos($key, 'portfolio') === 0 ? googleDrivePreviewUrl($url) : ''];
    }
    return $resources;
}

function studentCard(array $student): void
{
    if (!isStudentPublic($student)) return;
    ?>
<a class="student-card" href="<?= e(url('student_detail.php')) ?>?id=<?= e($student['id']) ?>">
    <div class="portrait" aria-hidden="true"><span><?= e(firstCharacter($student['name'])) ?></span><?php if (studentImageUrl($student) !== ''): ?><img class="student-photo" src="<?= e(studentImageUrl($student)) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?></div>
    <div class="student-card-body">
        <span class="role-label"><?= e(listText($student['role'] ?? [])) ?></span>
        <h3><?= e($student['name']) ?></h3>
        <p class="name-en"><?= e($student['name_en']) ?></p>
        <p class="student-meta"><?= e($student['graduation_year'] ?? '') ?></p>
        <div class="tag-list">
            <?php foreach (array_slice($student['skills'], 0, 3) as $skill): ?><span><?= e($skill) ?></span><?php endforeach; ?>
        </div>
    </div>
    <span class="circle-arrow" aria-hidden="true">↗</span>
</a>
<?php
}
