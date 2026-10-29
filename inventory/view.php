<?php
/**
 * inventory/view.php - UC 16, item detail (WF 08).
 *
 * The descriptive fields, the derived copy counts, the copy table, and the
 * Delete control. Delete is refused while any copy is checked out (BR 07), so
 * the button is drawn disabled with the reason spelled out. The check that
 * matters lives in inventory/delete.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../DB/inventory.php';

boot_session();
require_admin();

$id = get_str('id');
$item = $id === '' ? null : inventory_find($id);

if ($item === null || !empty($item['deleted'])) {
    http_response_code(404);
    $page_title = 'Item not found';
    $active_nav = 'Inventory';
    $layout = 'admin';
    require __DIR__ . '/../includes/header.php';
    ?>
    <h1 class="page-title">Item not found</h1>
    <p class="lede">No inventory item has the ID <?= e($id) ?>, or it has been deleted.</p>
    <p><a class="btn btn-secondary" href="/inventory/list.php">Back to inventory</a></p>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$counts = inventory_counts($item);
$outCount = $counts['out'];
$blocked = $outCount > 0;
$fields = media_type_fields($item['media_type']);

$page_title = (string) $item['title'];
$active_nav = 'Inventory';
$layout = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<p class="breadcrumb"><a href="/inventory/list.php">Inventory</a> &rsaquo; <?= e($item['id']) ?></p>

<div class="page-head">
  <div>
    <h1 class="page-title"><?= e($item['title']) ?></h1>
    <p class="meta-line">
      <?= e($item['id']) ?> &nbsp;&middot;&nbsp;
      <?= e(media_type_label($item['media_type'])) ?><?php
        if (($item['creator'] ?? '') !== '') {
            echo ' &nbsp;&middot;&nbsp; ' . e($item['creator']);
        }
        if (($item['publisher'] ?? '') !== '') {
            $year = substr((string) ($item['publish_date'] ?? ''), 0, 4);
            echo ' &nbsp;&middot;&nbsp; ' . e($item['publisher']) . ($year !== '' ? ', ' . e($year) : '');
        }
      ?>
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-primary" href="/inventory/edit.php?id=<?= e(rawurlencode($item['id'])) ?>">Edit item</a>
    <a class="btn btn-secondary" href="/inventory/list.php">Back to list</a>
  </div>
</div>

<div class="detail-grid">
  <section aria-labelledby="item-title">
    <h2 class="detail-section-h" id="item-title">Item details</h2>
    <div class="panel">
      <dl class="detail-list">
        <div class="detail-row"><dt>Title</dt><dd><?= e($item['title']) ?></dd></div>
        <div class="detail-row"><dt>Media type</dt><dd><?= e(media_type_label($item['media_type'])) ?></dd></div>
        <?php foreach ($fields as $key => $label): ?>
          <?php $fieldValue = (string) ($item[$key] ?? ''); ?>
          <div class="detail-row">
            <dt><?= e($label) ?></dt>
            <dd><?= $fieldValue !== '' ? e($fieldValue) : '<span class="muted">Not recorded</span>' ?></dd>
          </div>
        <?php endforeach; ?>
        <div class="detail-row"><dt>Item ID</dt><dd><?= e($item['id']) ?> <span class="muted">(read-only)</span></dd></div>
      </dl>
    </div>

    <h2 class="detail-section-h">Availability</h2>
    <div class="panel">
      <div class="stat-row">
        <div><div class="stat-value"><?= (int) $counts['total'] ?></div><div class="stat-label">Total</div></div>
        <div><div class="stat-value"><?= (int) $counts['available'] ?></div><div class="stat-label">Available</div></div>
        <div><div class="stat-value"><?= (int) $counts['out'] ?></div><div class="stat-label">Checked out</div></div>
        <div><div class="stat-value"><?= (int) $counts['missing'] ?></div><div class="stat-label">Missing</div></div>
      </div>
      <p class="hint">Counts are derived from the copy records, never stored (FR 22).</p>
    </div>

    <div class="panel danger-panel" id="delete">
      <h2 class="detail-section-h">Delete this item</h2>
      <?php if ($blocked): ?>
        <p class="muted"><?php
          $copiesWord = $outCount === 1 ? 'copy is' : 'copies are';
          $whichWord = $outCount === 1 ? 'that copy' : 'those copies';
        ?>This item cannot be deleted: <?= (int) $outCount ?> <?= e($copiesWord) ?> checked out. Check <?= e($whichWord) ?> in first, then delete.</p>
        <button class="btn btn-danger" type="button" disabled
                title="Delete is disabled while any copy is checked out (BR 07)">Delete</button>
        <p class="hint">Delete is refused while a copy is out (BR 07).</p>
      <?php else: ?>
        <p class="muted">
          Deleting flags the item deleted and keeps its <?= (int) $counts['total'] ?>
          <?= $counts['total'] === 1 ? 'copy record' : 'copy records' ?> and all history (DR 14).
        </p>
        <form action="/inventory/delete.php" method="post"
              data-confirm="Delete &quot;<?= e($item['title']) ?>&quot;? The item is flagged deleted and its copy records are kept.">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e($item['id']) ?>">
          <button class="btn btn-danger" type="submit">Delete</button>
        </form>
        <p class="hint">Delete is refused while a copy is out (BR 07).</p>
      <?php endif; ?>
    </div>
  </section>

  <section aria-labelledby="copies-title">
    <h2 class="copy-heading" id="copies-title">
      Copies <span class="muted">(<?= (int) $counts['total'] ?> total, <?= (int) $counts['available'] ?> available)</span>
    </h2>
    <div class="table-wrap">
      <table class="data">
        <caption class="sr-only">Copies of <?= e($item['title']) ?>: copy ID, barcode, condition, status, and the edit action</caption>
        <thead>
          <tr>
            <th scope="col">Copy ID</th>
            <th scope="col">Barcode</th>
            <th scope="col">Condition</th>
            <th scope="col">Status</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($item['copies'] as $copy): ?>
            <tr>
              <td><?= e($copy['id']) ?></td>
              <td><?= e($copy['barcode']) ?></td>
              <td><?= e(copy_condition_label($copy['condition'])) ?></td>
              <td><span class="badge badge-status-<?= e($copy['status']) ?>"><?= e(copy_status_label($copy['status'])) ?></span></td>
              <td class="actions-cell">
                <?php if ($copy['status'] === 'checked out'): ?>
                  <span class="link-action link-muted" aria-disabled="true"
                        title="A checked-out copy is set by the checkout flow, never by hand (BR 08)">&mdash;</span>
                <?php else: ?>
                  <a class="link-action" href="/inventory/edit.php?id=<?= e(rawurlencode($item['id'])) ?>#copies">Edit</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="table-note">Checked out is set by the checkout flow, never by hand (BR 08).</p>
  </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
