<?php
/**
 * phase2.php - the placeholder for a screen that is out of Phase 1 scope.
 *
 * The approved header design lists six admin labels, so the nav keeps all
 * six. Employees, Reports, and Accounts are not part of the Phase 1 coding
 * requirement (home, login, and the two CRUD sets), so they point here to a
 * plain statement of the schedule rather than to a half-built screen.
 *
 * This page holds no data and performs no action.
 */
declare(strict_types=1);

require_once __DIR__ . '/security/auth.php';
require_once __DIR__ . '/includes/flash.php';

boot_session();
require_admin();

$screen = get_str('screen', 'This screen');
$allowed = ['Employees', 'Reports', 'Accounts'];
if (!in_array($screen, $allowed, true)) {
    $screen = 'This screen';
}

$page_title = $screen . ' · Phase 2';
$active_nav = $screen;
$layout = 'admin';
require __DIR__ . '/includes/header.php';
?>

<div class="panel notice-panel">
  <h1 class="page-title"><?= e($screen) ?></h1>
  <p class="lede"><?= e($screen) ?> is scheduled for Phase 2 and is not part of the Phase 1 build.</p>
  <p class="muted">
    Phase 1 covers the home page, the login page, and two complete CRUD sets: member
    management (UC 10&ndash;UC 13) and inventory management (UC 14&ndash;UC 17).
    The <?= e(strtolower($screen)) ?> screen is listed in the approved navigation design, so the
    link appears here, but no functionality sits behind it yet.
  </p>
  <p class="actions">
    <a class="btn btn-primary" href="/members/list.php">Go to Members</a>
    <a class="btn btn-secondary" href="/inventory/list.php">Go to Inventory</a>
  </p>
  <p class="hint">Nothing is stored or changed on this page.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
