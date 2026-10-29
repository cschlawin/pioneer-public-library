<?php
/**
 * reset.php
 *
 * Restores the seeded demo tables. POST only, with a CSRF token, so a plain
 * link or a prefetch can never wipe the session's data.
 */
declare(strict_types=1);

require_once __DIR__ . '/security/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/DB/members.php';
require_once __DIR__ . '/DB/inventory.php';

boot_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Method not allowed</title></head>'
        . '<body><h1>Method not allowed</h1><p>Resetting demo data is a POST action.</p>'
        . '<p><a href="/index.php">Return to the home page</a></p></body></html>';
    exit;
}

csrf_check();
members_reset();
inventory_reset();
flash_set('success', 'Demo data reset. The seeded members and inventory items are back.');

$returnTo = post_str('return_to');
// Only ever redirect to a path on this site.
if ($returnTo === '' || $returnTo[0] !== '/' || str_starts_with($returnTo, '//')) {
    $returnTo = '/index.php';
}
header('Location: ' . $returnTo, true, 303);
exit;
