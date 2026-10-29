<?php
/**
 * security/auth.php
 *
 * Session start, sign-in and sign-out, the current user, and the two guards
 * every protected page uses: require_login() and require_admin() (FR 29).
 */
declare(strict_types=1);

require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/users.php';

/**
 * Start the session with hardening flags and send the security headers.
 * Every page includes this file, so the headers are set in one place.
 */
function boot_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_name('PPLSESSION');
        session_start();
    }
    send_security_headers();
}

/**
 * Security headers. The CSP allows same-origin styles and scripts only, so
 * the site carries no inline JavaScript and no inline style attribute.
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self'; "
        . "img-src 'self' data:; "
        . "font-src 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'none'; "
        . "base-uri 'self'; "
        . "object-src 'none'"
    );
}

/**
 * Try to sign in. Returns true on success.
 *
 * The caller shows one generic message on failure so the page never reveals
 * whether the username or the password was wrong (FR 27, WF 02).
 */
function attempt_login(string $username, string $password): bool
{
    $user = find_demo_user($username);
    // Always run password_verify so a missing username and a wrong password
    // take the same time.
    $hash = $user['password_hash']
        ?? '$2y$12$0000000000000000000000000000000000000000000000000000';
    $ok = password_verify($password, $hash);
    if ($user === null || !$ok || $password === '') {
        return false;
    }
    // New session id on privilege change, and a fresh CSRF token.
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'username'     => $user['username'],
        'display_name' => $user['display_name'],
        'role'         => $user['role'],
    ];
    unset($_SESSION['csrf_token']);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return true;
}

/** Sign out and destroy the session completely. */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '',
            (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

/** The signed-in user, or null. */
function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    return is_array($user) ? $user : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && ($user['role'] ?? '') === 'admin';
}

/** Send a 403 page and stop. Used for signed-in users who lack the role. */
function deny_403(string $detail = ''): never
{
    http_response_code(403);
    $user = current_user();
    $signedIn = $user === null ? '' : (string) $user['display_name'];
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Access denied · Pioneer Public Library</title>
  <link rel="stylesheet" href="/css/site.css">
</head>
<body>
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="wordmark" href="/index.php">Pioneer Public Library</a>
    </div>
  </header>
  <main class="wrap" id="main">
    <div class="panel notice-panel">
      <h1 class="page-title">Access denied</h1>
      <p class="lede">This area is limited to library staff in the Admin role.</p>
      <?php if ($detail !== ''): ?>
        <p class="muted"><?= e($detail) ?></p>
      <?php endif; ?>
      <p class="muted">
        <?php if ($signedIn !== ''): ?>
          You are signed in as <strong><?= e($signedIn) ?></strong>, which is a member account.
        <?php endif; ?>
        Member accounts can browse the catalog and use My Account. Ask a staff member at any branch if you need admin access.
      </p>
      <p class="actions">
        <a class="btn btn-secondary" href="/index.php">Back to the home page</a>
        <a class="btn btn-secondary" href="/logout.php">Sign out</a>
      </p>
      <p class="hint">Error 403 · Your account does not hold the Admin role (BR 10).</p>
    </div>
  </main>
</body>
</html>
    <?php
    exit;
}

/** Require any signed-in user; send guests to the login page. */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        $target = $_SERVER['REQUEST_URI'] ?? '/index.php';
        header('Location: /login.php?next=' . rawurlencode($target), true, 302);
        exit;
    }
    return $user;
}

/** Require the Admin role on every administrative page (FR 29, BR 10). */
function require_admin(): array
{
    $user = require_login();
    if (($user['role'] ?? '') !== 'admin') {
        deny_403('The page you asked for is an administrative page.');
    }
    return $user;
}
