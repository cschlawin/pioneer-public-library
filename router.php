<?php
/**
 * router.php
 *
 * Router script for PHP's built-in server:
 *
 *     php -S 0.0.0.0:8000 router.php
 *
 * It refuses anything that should never be served (the DB/ folder, the
 * SQLite file, SQL scripts, Markdown notes, tests, dotfiles and path
 * traversal) with a 404, and hands every real page or asset back to the
 * built-in server by returning false. A production server would instead use
 * a document root that holds only the public files.
 */
declare(strict_types=1);

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

function router_not_found(): bool
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Not found';
    return true;
}

$blocked = str_contains($path, "\0")
    || str_contains($path, '..')
    || preg_match('~^/(?:DB|tests|docs/diagrams|security|includes)(?:/|$)~i', $path) === 1
    || preg_match('~(?:^|/)\.~', $path) === 1
    || preg_match('~\.(?:db|sqlite|sqlite3|sql|md|log|drawio)$~i', $path) === 1
    || strcasecmp(basename($path), 'router.php') === 0;

if ($blocked) {
    return router_not_found();
}

if ($path === '/' || $path === '') {
    $path = '/index.php';
}

$base = realpath(__DIR__);
$full = realpath(__DIR__ . $path);
if ($base === false || $full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
    return router_not_found();
}
if (is_dir($full)) {
    $index = $full . DIRECTORY_SEPARATOR . 'index.php';
    if (!is_file($index)) {
        return router_not_found();
    }
}

return false;
