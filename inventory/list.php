<?php
/**
 * inventory/list.php - UC 14, view and search inventory (WF 06).
 *
 * The four copy counts come from the copy records on every render, never from
 * a stored column (FR 22). Delete is drawn as a disabled control with a title
 * tooltip whenever any copy is checked out, because it would be refused
 * (BR 07). The form is still guarded on the server, so the markup is a
 * courtesy and the rule holds even if the button is re-enabled by hand.
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../DB/inventory.php';

boot_session();
require_admin();

$search = get_str('search');
$type = get_str('type', 'all');
$status = get_str('status', 'all');
if ($type !== 'all' && !is_valid_media_type($type)) {
    $type = 'all';
}
if (!in_array($status, ['all', 'available', 'out', 'missing'], true)) {
    $status = 'all';
}

$rows = inventory_search(['search' => $search, 'type' => $type, 'status' => $status]);
$total = 0;
foreach (inventory_all() as $item) {
    if (empty($item['deleted'])) {
        $total++;
    }
}

$page_title = 'Inventory';
$active_nav = 'Inventory';
$layout = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-title">Inventory</h1>
    <p class="lede">Search by title, creator, or ISBN, then narrow by media type or copy status.</p>
  </div>
</div>

<form class="toolbar" action="/inventory/list.php" method="get" role="search">
  <div class="field field-grow">
    <label for="search">Search inventory</label>
    <input type="search" id="search" name="search" value="<?= e($search) ?>"
           placeholder="Search title, creator, or ISBN">
  </div>
  <div class="field">
    <label for="type">Media type</label>
    <select id="type" name="type">
      <option value="all"<?= $type === 'all' ? ' selected' : '' ?>>All types</option>
      <?php foreach (media_types() as $key => $label): ?>
        <option value="<?= e($key) ?>"<?= $type === $key ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="status">Copy status</label>
    <select id="status" name="status">
      <option value="all"<?= $status === 'all' ? ' selected' : '' ?>>Any status</option>
      <option value="available"<?= $status === 'available' ? ' selected' : '' ?>>Has a copy available</option>
      <option value="out"<?= $status === 'out' ? ' selected' : '' ?>>Has a copy checked out</option>
      <option value="missing"<?= $status === 'missing' ? ' selected' : '' ?>>Has a copy missing</option>
    </select>
  </div>
  <button class="btn btn-primary" type="submit">Filter</button>
  <?php if ($search !== '' || $type !== 'all' || $status !== 'all'): ?>
    <a class="btn btn-secondary" href="/inventory/list.php">Clear</a>
  <?php endif; ?>
  <span class="count-note">Showing <?= count($rows) ?> of <?= (int) $total ?></span>
  <span class="toolbar-spacer"></span>
  <a class="btn btn-primary" href="/inventory/add.php">Add item</a>
</form>

<?php if ($rows === []): ?>
  <div class="empty-state">
    <h2>No items match those filters</h2>
    <p>Try a different title, creator, or ISBN, or clear the filters.</p>
    <a class="btn btn-secondary" href="/inventory/list.php">Clear the filters</a>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <caption class="sr-only">Inventory items, showing <?= count($rows) ?> of <?= (int) $total ?> records with item ID, title, type, and the total, available, checked out, and missing copy counts</caption>
      <thead>
        <tr>
          <th scope="col">Item ID</th>
          <th scope="col">Title</th>
          <th scope="col">Type</th>
          <th scope="col" class="num">Total</th>
          <th scope="col" class="num">Avail</th>
          <th scope="col" class="num">Out</th>
          <th scope="col" class="num">Missing</th>
          <th scope="col">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $item): ?>
          <?php
          $counts = inventory_counts($item);
          $hasOut = $counts['out'] > 0;
          ?>
          <tr>
            <td><a href="/inventory/view.php?id=<?= e(rawurlencode($item['id'])) ?>"><?= e($item['id']) ?></a></td>
            <td><?= e($item['title']) ?></td>
            <td><?= e(media_type_label($item['media_type'])) ?></td>
            <td class="num"><?= (int) $counts['total'] ?></td>
            <td class="num"><?= (int) $counts['available'] ?></td>
            <td class="num"><?= (int) $counts['out'] ?></td>
            <td class="num"><?= (int) $counts['missing'] ?></td>
            <td class="actions-cell">
              <a class="link-action" href="/inventory/edit.php?id=<?= e(rawurlencode($item['id'])) ?>">Edit</a>
              <?php if ($hasOut): ?>
                <span class="link-action link-muted" aria-disabled="true"
                      title="Delete is disabled while any copy is checked out (BR 07)">Delete</span>
              <?php else: ?>
                <a class="link-action link-danger" href="/inventory/view.php?id=<?= e(rawurlencode($item['id'])) ?>#delete">Delete</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="table-note">Delete is disabled while any copy is checked out (BR 07).</p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
