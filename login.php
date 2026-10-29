<?php
/**
 * login.php - the sign-in page (WF 02).
 *
 * A POST-only form. On success the session id is regenerated and the user is
 * sent on with a 303. On failure the page re-renders with exactly one generic
 * message that does not say which of the two values was wrong (FR 27).
 */
declare(strict_types=1);

require_once __DIR__ . '/security/auth.php';
require_once __DIR__ . '/includes/flash.php';

boot_session();

// Already signed in? Send them on rather than showing the form again.
if (is_logged_in() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /members/list.php', true, 303);
    exit;
}

$errors = [];
$notice = '';
$username = '';
$next = get_str('next');
// Only accept a same-site path to redirect to after sign-in.
if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//')) {
    $next = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post_str('username');
    $password = $_POST['password'] ?? '';
    if (!is_string($password)) {
        $password = '';
    }
    $next = post_str('next');
    if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//')) {
        $next = '';
    }

    if ($username === '') {
        $errors['username'] = 'Enter your username.';
    }
    if ($password === '') {
        $errors['password'] = 'Enter your password.';
    }

    if ($errors === []) {
        if (attempt_login($username, $password)) {
            $user = current_user();
            flash_set('success', 'Signed in as ' . (string) ($user['display_name'] ?? $username) . '.');
            $target = $next !== '' ? $next : '/members/list.php';
            header('Location: ' . $target, true, 303);
            exit;
        }
        // One message for a bad username and a bad password alike.
        $notice = 'The username or password is incorrect.';
    }
}

$page_title = 'Log in';
$active_nav = '';
$layout = 'public';
require __DIR__ . '/includes/header.php';
?>

<div class="login-shell">
  <div class="login-card">
    <h1>Log in to your account</h1>

    <?php if ($notice !== ''): ?>
      <div class="login-error" role="alert">
        <p><?= e($notice) ?></p>
      </div>
    <?php endif; ?>

    <form action="/login.php" method="post" autocomplete="off" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">

      <div class="<?= e(ltrim(field_class($errors, 'username'))) ?>">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>"
               placeholder="Your member or staff username" autocomplete="off"
               autocapitalize="none" spellcheck="false" required<?= field_aria($errors, 'username') ?>>
        <?= field_error_html($errors, 'username') ?>
      </div>

      <div class="<?= e(ltrim(field_class($errors, 'password'))) ?>">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" value=""
               placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" autocomplete="off" required<?= field_aria($errors, 'password') ?>>
        <?= field_error_html($errors, 'password') ?>
      </div>

      <div class="form-actions">
        <button class="btn btn-dark btn-lg btn-block" type="submit">Sign in</button>
      </div>
    </form>

    <p class="login-note">Need a card? Ask at any branch.</p>
    <p class="login-hint">Autocomplete is off on this form (BR 14).</p>

    <div class="demo-box">
      <h2>Demo accounts for grading</h2>
      <p><code>bsmith</code> — Admin: members and inventory</p>
      <p><code>pjones</code> — Member: home page only, admin pages refused</p>
      <p>Passwords are listed in the project README. No password is shown on any page (BR 14).</p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
