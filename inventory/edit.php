<?php
/**
 * inventory/edit.php - UC 17, update an item (WF 08 in its edit state).
 *
 * Updates the descriptive fields and, below them, the condition and status of
 * each copy. The status dropdown offers Available and Missing only: checked
 * out is owned by the checkout flow and cannot be set by hand (BR 08).
 *
 * A copy that is currently checked out keeps its status. Its condition can be
 * corrected, but the status control is drawn disabled and the posted value is
 * ignored, so a crafted POST cannot quietly check a copy back in.
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../DB/inventory.php';

boot_session();
require_admin();

$id = get_str('id');
$item = $id === '' ? null : inventory_find($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = post_str('id');
    $item = $id === '' ? null : inventory_find($id);
}

if ($item === null || !empty($item['deleted'])) {
    http_response_code(404);
    $page_title = 'Item not found';
    $active_nav = 'Inventory';
    $layout = 'admin';
    require __DIR__ . '/../includes/header.php';
    ?>
    <h1 class="page-title">Item not found</h1>
    <p class="lede">No inventory item has that ID, or it has been deleted.</p>
    <p><a class="btn btn-secondary" href="/inventory/list.php">Back to inventory</a></p>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$errors = [];
$values = [
    'media_type'   => $item['media_type'],
    'title'        => $item['title'],
    'creator'      => (string) ($item['creator'] ?? ''),
    'publisher'    => (string) ($item['publisher'] ?? ''),
    'publish_date' => (string) ($item['publish_date'] ?? ''),
    'genre'        => (string) ($item['genre'] ?? ''),
    'isbn'         => (string) ($item['isbn'] ?? ''),
    'issue'        => (string) ($item['issue'] ?? ''),
    'year'         => (string) ($item['year'] ?? ''),
];
// Posted copy values, kept so a failed submission re-renders what was typed.
$postedCopies = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach (array_keys($values) as $key) {
        $values[$key] = post_str($key);
    }

    if (!is_valid_media_type($values['media_type'])) {
        $errors['media_type'] = 'Choose a media type from the list.';
        $values['media_type'] = $item['media_type'];
    }

    $fields = media_type_fields($values['media_type']);

    $errors['title'] = validate_required($values['title'], 'a title')
        ?? validate_max_length($values['title'], 180, 'the title');
    if (isset($fields['creator'])) {
        $label = strtolower($fields['creator']);
        $errors['creator'] = validate_required($values['creator'], 'the ' . $label)
            ?? validate_max_length($values['creator'], 120, 'the ' . $label);
    }
    if (isset($fields['publisher'])) {
        $errors['publisher'] = validate_required($values['publisher'], 'a publisher')
            ?? validate_max_length($values['publisher'], 120, 'the publisher');
    }
    if (isset($fields['publish_date'])) {
        $errors['publish_date'] = validate_iso_date($values['publish_date'], 'a publish date');
    }
    if (isset($fields['genre'])) {
        $errors['genre'] = validate_required($values['genre'], 'a genre')
            ?? validate_max_length($values['genre'], 80, 'the genre');
    }
    if (isset($fields['isbn'])) {
        $errors['isbn'] = validate_required($values['isbn'], 'an ISBN')
            ?? (preg_match('/^[0-9Xx\- ]{10,20}$/', $values['isbn']) === 1
                ? null
                : 'Enter an ISBN of 10 or 13 digits; hyphens are allowed.');
    }
    if (isset($fields['issue'])) {
        $errors['issue'] = validate_required($values['issue'], 'an issue')
            ?? validate_max_length($values['issue'], 60, 'the issue');
    }
    if (isset($fields['year'])) {
        $errors['year'] = validate_year($values['year']);
    }

    // Copy rows. A checked-out copy cannot be changed here at all.
    $conditions = copy_conditions();
    $newCopies = [];
    foreach ($item['copies'] as $copy) {
        $rowKey = 'copy_' . $copy['id'];
        $postedCondition = post_str($rowKey . '_condition');
        $postedStatus = post_str($rowKey . '_status');
        $postedCopies[$copy['id']] = ['condition' => $postedCondition, 'status' => $postedStatus];

        if ($copy['status'] === 'checked out') {
            // Locked: keep what is stored, ignore anything posted.
            $newCopies[] = $copy;
            continue;
        }
        if (!array_key_exists($postedCondition, $conditions)) {
            $errors[$rowKey . '_condition'] = 'Choose a condition from the list.';
            $postedCondition = $copy['condition'];
        }
        if (!in_array($postedStatus, ['available', 'missing'], true)) {
            $errors[$rowKey . '_status'] = 'Choose Available or Missing. Checked out is set by the checkout flow (BR 08).';
            $postedStatus = $copy['status'];
        }
        $newCopies[] = [
            'id'        => $copy['id'],
            'barcode'   => $copy['barcode'],
            'condition' => $postedCondition,
            'status'    => $postedStatus,
        ];
    }

    $errors = array_filter($errors, static fn (?string $message): bool => $message !== null);

    if ($errors === []) {
        $updated = inventory_update($item['id'], $values, $newCopies);
        flash_set('success', 'Saved changes to "' . ($updated['title'] ?? $item['title']) . '".');
        header('Location: /inventory/view.php?id=' . rawurlencode($item['id']), true, 303);
        exit;
    }
    http_response_code(422);
}

$counts = inventory_counts($item);
$blocked = $counts['out'] > 0;
$type = $values['media_type'];
$fields = media_type_fields($type);

// Which value to show in a copy control: the posted one, or the stored one.
$copyValue = static function (string $copyId, string $part, string $fallback) use ($postedCopies): string {
    if (isset($postedCopies[$copyId][$part]) && $postedCopies[$copyId][$part] !== '') {
        return $postedCopies[$copyId][$part];
    }
    return $fallback;
};

$page_title = 'Edit ' . $item['title'];
$active_nav = 'Inventory';
$layout = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<p class="breadcrumb">
  <a href="/inventory/list.php">Inventory</a> &rsaquo;
  <a href="/inventory/view.php?id=<?= e(rawurlencode($item['id'])) ?>"><?= e($item['title']) ?></a> &rsaquo;
  Edit
</p>

<div class="page-head">
  <div>
    <h1 class="page-title">Edit <?= e($item['title']) ?></h1>
    <p class="meta-line">
      <?= e($item['id']) ?> &nbsp;&middot;&nbsp; <?= e(media_type_label($item['media_type'])) ?>
      &nbsp;&middot;&nbsp; <?= (int) $counts['total'] ?> total, <?= (int) $counts['available'] ?> available
    </p>
  </div>
</div>

<?php if ($errors !== []): ?>
  <div class="flash flash-error" role="alert">
    <p class="flash-text">The changes were not saved. Fix the highlighted fields and save again.</p>
  </div>
<?php endif; ?>

<form class="form-panel" action="/inventory/edit.php" method="post" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= e($item['id']) ?>">

  <div class="field field-narrow">
    <label for="media_type">Media type <span class="req">*</span></label>
    <select id="media_type" name="media_type" data-media-type-select<?= field_aria($errors, 'media_type') ?>>
      <?php foreach (media_types() as $key => $label): ?>
        <option value="<?= e($key) ?>"<?= $type === $key ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?= field_error_html($errors, 'media_type') ?>
    <p class="field-hint">Changing the type changes which fields below apply (DR 04).</p>
  </div>

  <div class="form-grid form-grid-wide">
    <div class="field" data-for-type="book audiobook film music periodical">
      <label for="title">Title <span class="req">*</span></label>
      <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required<?= field_aria($errors, 'title') ?>>
      <?= field_error_html($errors, 'title') ?>
    </div>

    <div class="field" data-for-type="book audiobook film music">
      <label for="creator">Author or creator <span class="req">*</span></label>
      <input type="text" id="creator" name="creator" value="<?= e($values['creator']) ?>"<?= field_aria($errors, 'creator') ?>>
      <?= field_error_html($errors, 'creator') ?>
    </div>

    <div class="field" data-for-type="book audiobook">
      <label for="publisher">Publisher</label>
      <input type="text" id="publisher" name="publisher" value="<?= e($values['publisher']) ?>"<?= field_aria($errors, 'publisher') ?>>
      <?= field_error_html($errors, 'publisher') ?>
    </div>

    <div class="field" data-for-type="book periodical">
      <label for="publish_date">Publish date</label>
      <input type="date" id="publish_date" name="publish_date" value="<?= e($values['publish_date']) ?>"<?= field_aria($errors, 'publish_date') ?>>
      <?= field_error_html($errors, 'publish_date') ?>
    </div>

    <div class="field" data-for-type="book film audiobook">
      <label for="genre">Genre</label>
      <input type="text" id="genre" name="genre" value="<?= e($values['genre']) ?>"<?= field_aria($errors, 'genre') ?>>
      <?= field_error_html($errors, 'genre') ?>
    </div>

    <div class="field" data-for-type="book">
      <label for="isbn">ISBN</label>
      <input type="text" id="isbn" name="isbn" value="<?= e($values['isbn']) ?>"<?= field_aria($errors, 'isbn') ?>>
      <?= field_error_html($errors, 'isbn') ?>
    </div>

    <div class="field" data-for-type="film">
      <label for="year">Year</label>
      <input type="text" id="year" name="year" value="<?= e($values['year']) ?>" inputmode="numeric" maxlength="4"<?= field_aria($errors, 'year') ?>>
      <?= field_error_html($errors, 'year') ?>
    </div>

    <div class="field" data-for-type="periodical">
      <label for="issue">Issue</label>
      <input type="text" id="issue" name="issue" value="<?= e($values['issue']) ?>"<?= field_aria($errors, 'issue') ?>>
      <?= field_error_html($errors, 'issue') ?>
    </div>
  </div>

  <h2 class="detail-section-h" id="copies">Copies (<?= (int) $counts['total'] ?> total, <?= (int) $counts['available'] ?> available)</h2>
  <div class="table-wrap">
    <table class="data">
      <caption class="sr-only">Condition and status of each copy of <?= e($item['title']) ?>. A checked-out copy cannot be edited here.</caption>
      <thead>
        <tr>
          <th scope="col">Copy ID</th>
          <th scope="col">Barcode</th>
          <th scope="col">Condition</th>
          <th scope="col">Status</th>
          <th scope="col">Lock</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($item['copies'] as $copy): ?>
          <?php
          $rowKey = 'copy_' . $copy['id'];
          $locked = $copy['status'] === 'checked out';
          $conditionValue = $copyValue($copy['id'], 'condition', $copy['condition']);
          $statusValue = $copyValue($copy['id'], 'status', $copy['status']);
          ?>
          <tr>
            <td><?= e($copy['id']) ?></td>
            <td><?= e($copy['barcode']) ?></td>
            <td>
              <label class="sr-only" for="<?= e($rowKey) ?>_condition">Condition of copy <?= e($copy['id']) ?></label>
              <select id="<?= e($rowKey) ?>_condition" name="<?= e($rowKey) ?>_condition"<?= $locked ? ' disabled' : '' ?><?= field_aria($errors, $rowKey . '_condition') ?>>
                <?php foreach (copy_conditions() as $key => $label): ?>
                  <option value="<?= e($key) ?>"<?= $conditionValue === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?= field_error_html($errors, $rowKey . '_condition') ?>
            </td>
            <td>
              <label class="sr-only" for="<?= e($rowKey) ?>_status">Status of copy <?= e($copy['id']) ?></label>
              <select id="<?= e($rowKey) ?>_status" name="<?= e($rowKey) ?>_status"<?= $locked ? ' disabled' : '' ?><?= field_aria($errors, $rowKey . '_status') ?>>
                <option value="available"<?= $statusValue === 'available' ? ' selected' : '' ?>>Available</option>
                <option value="missing"<?= $statusValue === 'missing' ? ' selected' : '' ?>>Missing</option>
                <?php if ($locked): ?>
                  <option value="checked out" selected>Checked out</option>
                <?php endif; ?>
              </select>
              <?php if ($locked): ?>
                <p class="field-hint">Locked by the checkout flow (BR 08).</p>
              <?php endif; ?>
              <?= field_error_html($errors, $rowKey . '_status') ?>
            </td>
            <td>
              <?php if ($locked): ?>
                <span class="badge badge-status-checked out">Checked out</span>
              <?php else: ?>
                <span class="muted">Editable</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="table-note">Checked out is set by the checkout flow, never by hand (BR 08).</p>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Save changes</button>
    <a class="btn btn-secondary" href="/inventory/view.php?id=<?= e(rawurlencode($item['id'])) ?>">Cancel</a>
    <?php if ($blocked): ?>
      <button class="btn btn-danger" type="button" disabled
              title="Delete is disabled while any copy is checked out (BR 07)">Delete</button>
    <?php endif; ?>
  </div>
</form>

<p class="required-note">* Required. Delete is refused while a copy is out (BR 07). Deleting is done from the item detail page.</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
