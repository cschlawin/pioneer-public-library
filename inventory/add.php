<?php
/**
 * inventory/add.php - UC 15, add an inventory item (WF 07).
 *
 * The media type decides which descriptive fields apply (DR 04). All five
 * types' fields are rendered, each group tagged with data-for-type so the
 * small script can show only the chosen type's group. Without JavaScript
 * every group is visible, and the server validates the fields that matter for
 * the submitted type, so the form works either way.
 *
 * Number of copies creates that many copy records, each in the Available
 * state (BR 08).
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../DB/inventory.php';

boot_session();
require_admin();

$errors = [];
$values = [
    'media_type'   => 'book',
    'title'        => '',
    'creator'      => '',
    'publisher'    => '',
    'publish_date' => '',
    'genre'        => '',
    'isbn'         => '',
    'issue'        => '',
    'year'         => '',
    'copies'       => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach (array_keys($values) as $key) {
        $values[$key] = post_str($key);
    }

    // Media type must be one of the five in the reference list (DR 04).
    if (!is_valid_media_type($values['media_type'])) {
        $errors['media_type'] = 'Choose a media type from the list.';
        $values['media_type'] = 'book';
    }

    $type = $values['media_type'];
    $fields = media_type_fields($type);

    // Title is required for every type (FR 22).
    $errors['title'] = validate_required($values['title'], 'a title')
        ?? validate_max_length($values['title'], 180, 'the title');

    // Then only the fields this type carries.
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

    $errors['copies'] = validate_int_range($values['copies'], 1, 99, 'the number of copies');

    $errors = array_filter($errors, static fn (?string $message): bool => $message !== null);

    if ($errors === []) {
        $created = inventory_insert($values, (int) $values['copies']);
        flash_set('success', 'Added "' . $created['title'] . '" as ' . $created['id'] . ' with '
            . count($created['copies']) . ' ' . (count($created['copies']) === 1 ? 'copy' : 'copies')
            . ', each in the Available state (BR 08).');
        header('Location: /inventory/list.php', true, 303);
        exit;
    }
    http_response_code(422);
}

$page_title = 'Add inventory item';
$active_nav = 'Inventory';
$layout = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Add inventory item</h1>
<p class="breadcrumb"><a href="/inventory/list.php">Inventory</a> &rsaquo; Add item</p>

<?php if ($errors !== []): ?>
  <div class="flash flash-error" role="alert">
    <p class="flash-text">The item was not saved. Fix the highlighted fields and save again.</p>
  </div>
<?php endif; ?>

<form class="form-panel" action="/inventory/add.php" method="post" novalidate>
  <?= csrf_field() ?>

  <div class="field field-narrow">
    <label for="media_type">Media type <span class="req">*</span></label>
    <select id="media_type" name="media_type" data-media-type-select<?= field_aria($errors, 'media_type') ?>>
      <?php foreach (media_types() as $key => $label): ?>
        <option value="<?= e($key) ?>"<?= $values['media_type'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?= field_error_html($errors, 'media_type') ?>
    <p class="field-hint">Choosing a type shows only that type's fields (DR 04).</p>
  </div>

  <div class="form-grid form-grid-wide">
    <div class="field" data-for-type="book audiobook film music periodical">
      <label for="title">Title <span class="req">*</span></label>
      <input type="text" id="title" name="title" value="<?= e($values['title']) ?>"
             placeholder="The Overstory" required<?= field_aria($errors, 'title') ?>>
      <?= field_error_html($errors, 'title') ?>
    </div>

    <div class="field" data-for-type="book audiobook film music">
      <label for="creator">Author or creator <span class="req">*</span></label>
      <input type="text" id="creator" name="creator" value="<?= e($values['creator']) ?>"
             placeholder="Richard Powers"<?= field_aria($errors, 'creator') ?>>
      <?= field_error_html($errors, 'creator') ?>
      <p class="field-hint" data-role-note>Books and audiobooks ask for the author, film for the director, music for the artist.</p>
    </div>

    <div class="field" data-for-type="book audiobook">
      <label for="publisher">Publisher</label>
      <input type="text" id="publisher" name="publisher" value="<?= e($values['publisher']) ?>"
             placeholder="W. W. Norton"<?= field_aria($errors, 'publisher') ?>>
      <?= field_error_html($errors, 'publisher') ?>
    </div>

    <div class="field" data-for-type="book periodical">
      <label for="publish_date">Publish date</label>
      <input type="date" id="publish_date" name="publish_date" value="<?= e($values['publish_date']) ?>"<?= field_aria($errors, 'publish_date') ?>>
      <?= field_error_html($errors, 'publish_date') ?>
      <p class="field-hint">YYYY-MM-DD, for example 2018-04-03.</p>
    </div>

    <div class="field" data-for-type="book film audiobook">
      <label for="genre">Genre</label>
      <input type="text" id="genre" name="genre" value="<?= e($values['genre']) ?>"
             placeholder="Literary fiction"<?= field_aria($errors, 'genre') ?>>
      <?= field_error_html($errors, 'genre') ?>
    </div>

    <div class="field" data-for-type="book">
      <label for="isbn">ISBN</label>
      <input type="text" id="isbn" name="isbn" value="<?= e($values['isbn']) ?>"
             placeholder="978-0393635522"<?= field_aria($errors, 'isbn') ?>>
      <?= field_error_html($errors, 'isbn') ?>
    </div>

    <div class="field" data-for-type="film">
      <label for="year">Year</label>
      <input type="text" id="year" name="year" value="<?= e($values['year']) ?>"
             placeholder="2016" inputmode="numeric" maxlength="4"<?= field_aria($errors, 'year') ?>>
      <?= field_error_html($errors, 'year') ?>
      <p class="field-hint">Film only.</p>
    </div>

    <div class="field" data-for-type="periodical">
      <label for="issue">Issue</label>
      <input type="text" id="issue" name="issue" value="<?= e($values['issue']) ?>"
             placeholder="November 2026"<?= field_aria($errors, 'issue') ?>>
      <?= field_error_html($errors, 'issue') ?>
      <p class="field-hint">Periodical only.</p>
    </div>
  </div>

  <div class="field field-copies">
    <label for="copies">Number of copies <span class="req">*</span></label>
    <input type="number" id="copies" name="copies" value="<?= e($values['copies']) ?>"
           min="1" max="99" step="1" required<?= field_aria($errors, 'copies') ?>>
    <?= field_error_html($errors, 'copies') ?>
    <p class="field-hint">Each copy is created in the Available state (BR 08).</p>
  </div>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Save</button>
    <a class="btn btn-secondary" href="/inventory/list.php">Cancel</a>
  </div>
</form>

<p class="required-note">* Required. Film asks for director, year, and genre; music asks for artist; periodical asks for issue and date.</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
