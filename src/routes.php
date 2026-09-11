<?php
declare(strict_types=1);

/** Build a public path, retaining query parameters that are not route identifiers. */
function routePath(string $path, array $query = []): string
{
    $path = ltrim($path, '/');
    if (PHP_SAPI !== 'cli-server') {
        $routes = [
            'student_detail.php' => ['students', 'id'],
            'team_detail.php' => ['teams', 'id'],
            'qr.php' => ['qr', 'code'],
        ];
        if ($path === 'contact.php') {
            if (isset($query['student'])) $routes[$path] = ['contact/student', 'student'];
            elseif (isset($query['team'])) $routes[$path] = ['contact/team', 'team'];
        }
        if (isset($routes[$path])) {
            [$prefix, $key] = $routes[$path];
            if (isset($query[$key]) && is_scalar($query[$key]) && preg_match('/^[A-Za-z0-9_-]+$/D', (string) $query[$key])) {
                $path = $prefix . '/' . rawurlencode((string) $query[$key]);
                unset($query[$key]);
            }
        }
        $pages = ['index', 'students', 'teams', 'guide', 'privacy', 'contact', 'admin', 'teams_admin', 'mapping_admin', 'exhibited_admin'];
        foreach ($pages as $page) {
            if ($path === $page . '.php') {
                $path = $page === 'index' ? '' : $page;
                break;
            }
        }
    }
    return $path . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}

/** Only redirect explicit legacy GET/HEAD requests, never POST or internal errors. */
function redirectLegacyRoute(): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)
        || !empty($_SERVER['REDIRECT_STATUS'])) return;
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!is_string($requestPath) || basename($requestPath) !== $script) return;
    if (routePath($script, $_GET) === $script . ($_GET ? '?' . http_build_query($_GET, '', '&', PHP_QUERY_RFC3986) : '')) return;
    header('Location: ' . url($script, $_GET), true, 301);
    exit;
}
