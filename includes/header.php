<?php
/**
 * includes/header.php
 *
 * The doctype, head, and site header. Two header modes:
 *   $layout = 'admin'  - the staff bar: wordmark, Admin label, Members,
 *                        Inventory, Employees, Reports, Accounts, sign out,
 *                        and the avatar with the user's initials.
 *   $layout = 'public' - the public bar: wordmark, Catalog, Events, Classes,
 *                        My Account, the catalog search, and Sign in.
 *
 * Set $page_title, $active_nav and $layout before including this file.
 * The current nav item carries aria-current="page" for screen readers.
 */
declare(strict_types=1);

$page_title = $page_title ?? 'Pioneer Public Library';
$active_nav = $active_nav ?? '';
$layout     = $layout ?? 'public';
$user       = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?> · Pioneer Public Library</title>
  <link rel="icon" href="/images/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/css/site.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<?php if ($layout === 'admin'): ?>
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="wordmark" href="/index.php">Pioneer Public Library</a>
      <span class="admin-tag">Admin</span>
      <nav class="site-nav" aria-label="Administration">
        <?php foreach ([
            'Members'   => '/members/list.php',
            'Inventory' => '/inventory/list.php',
            'Employees' => '/phase2.php?screen=Employees',
            'Reports'   => '/phase2.php?screen=Reports',
            'Accounts'  => '/phase2.php?screen=Accounts',
        ] as $label => $href): ?>
          <?php if ($href === ''): ?>
            <span class="nav-link nav-disabled" title="Planned for Phase 2"><?= e($label) ?></span>
          <?php else: ?>
            <a class="nav-link<?= $active_nav === $label ? ' is-active' : '' ?>"
               href="<?= e($href) ?>"<?= $active_nav === $label ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <div class="header-user">
        <a class="signout-link" href="/logout.php">Sign out</a>
        <span class="avatar" aria-hidden="true"><?= e(initials((string) ($user['display_name'] ?? '?'))) ?></span>
        <span class="sr-only">Signed in as <?= e((string) ($user['display_name'] ?? 'guest')) ?></span>
      </div>
    </div>
  </header>
<?php else: ?>
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="wordmark" href="/index.php">Pioneer Public Library</a>
      <nav class="site-nav" aria-label="Main">
        <?php foreach ([
            'Catalog' => '/index.php#catalog',
            'Events'  => '/index.php#events',
            'Classes' => '',
            'My Account' => is_logged_in() ? '/index.php#account' : '/login.php',
        ] as $label => $href): ?>
          <?php if ($href === ''): ?>
            <span class="nav-link nav-disabled" title="Planned for Phase 2"><?= e($label) ?></span>
          <?php else: ?>
            <a class="nav-link<?= $active_nav === $label ? ' is-active' : '' ?>"
               href="<?= e($href) ?>"<?= $active_nav === $label ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <form class="header-search" action="/index.php" method="get" role="search">
        <label class="sr-only" for="header-q">Search the catalog</label>
        <input type="search" id="header-q" name="q" placeholder="Search the catalog" value="<?= e(get_str('q')) ?>">
        <button class="btn btn-primary btn-sm" type="submit">Search</button>
      </form>
      <?php if (is_logged_in()): ?>
        <a class="btn btn-dark btn-sm" href="/logout.php">Sign out</a>
      <?php else: ?>
        <a class="btn btn-dark btn-sm" href="/login.php">Sign in</a>
      <?php endif; ?>
    </div>
  </header>
<?php endif; ?>
<main class="wrap" id="main">
<?php flash_render(); ?>
