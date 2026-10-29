<?php
/**
 * security/csrf.php
 *
 * Per-session CSRF token. Every POST in this project calls csrf_check()
 * before it touches data.
 */
declare(strict_types=1);

/** The current session's token, created on first use. */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('Start the session before asking for a CSRF token.');
    }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** The hidden input a form carries. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Refuse the request unless the posted token matches the session token.
 * Called at the top of every POST handler.
 */
function csrf_check(): void
{
    $posted = $_POST['csrf_token'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';
    if (!is_string($posted) || !is_string($known) || $known === '' || !hash_equals($known, $posted)) {
        http_response_code(400);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Request could not be verified</title></head><body>'
            . '<h1>Request could not be verified</h1>'
            . '<p>The form expired or came from another page. Go back, reload the page, and try again.</p>'
            . '<p><a href="/index.php">Return to the home page</a></p>'
            . '</body></html>';
        exit;
    }
}
