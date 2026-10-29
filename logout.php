<?php
/**
 * logout.php - end the session (FR 28).
 *
 * Reached by a link, so it accepts GET, but it changes nothing except the
 * visitor's own session, and it never trusts a redirect target from the URL.
 */
declare(strict_types=1);

require_once __DIR__ . '/security/auth.php';
require_once __DIR__ . '/includes/flash.php';

boot_session();
logout();

// The session is gone, so start a clean one just to carry the message.
session_start();
flash_set('success', 'You are signed out.');
header('Location: /index.php', true, 303);
exit;
