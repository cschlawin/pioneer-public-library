<?php
/**
 * members/add.php - UC 11, add a member (WF 04).
 *
 * POST only. Every field is validated on the server (FR 32) and the email is
 * checked for uniqueness across the whole table, case-insensitively (FR 18).
 * When a check fails the form re-renders with the submitted values still in
 * place and a message under each bad field.
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../DB/members.php';

boot_session();
require_admin();

$errors = [];
$values = [
    'first_name' => '',
    'last_name'  => '',
    'email'      => '',
    'phone'      => '',
    'street'     => '',
    'city'       => '',
    'state'      => 'UT',
    'zip'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach (array_keys($values) as $key) {
        $values[$key] = post_str($key);
    }

    // Required fields, then shape, then uniqueness. The email message is the
    // exact copy the wireframe shows for this field.
    $errors['first_name'] = validate_required($values['first_name'], 'a first name')
        ?? validate_max_length($values['first_name'], 60, 'the first name');
    $errors['last_name'] = validate_required($values['last_name'], 'a last name')
        ?? validate_max_length($values['last_name'], 60, 'the last name');
    $errors['email'] = validate_email($values['email']);
    if ($errors['email'] === null && member_email_taken($values['email'])) {
        $errors['email'] = 'That email already belongs to another member. Enter a different address (FR 18).';
    }
    $errors['phone'] = validate_phone($values['phone']);
    $errors['street'] = validate_required($values['street'], 'a street address')
        ?? validate_max_length($values['street'], 120, 'the street address');
    $errors['city'] = validate_required($values['city'], 'a city')
        ?? validate_max_length($values['city'], 80, 'the city');
    $errors['state'] = validate_state($values['state']);
    $errors['zip'] = validate_zip($values['zip']);

    // Drop the keys that passed so only real failures remain.
    $errors = array_filter($errors, static fn (?string $message): bool => $message !== null);

    if ($errors === []) {
        $created = member_insert($values);
        flash_set('success', 'Added ' . member_name($created) . ' as ' . $created['id'] . '.');
        header('Location: /members/list.php', true, 303);
        exit;
    }
    // Re-render with the values and messages. No redirect here: this is a
    // failed submission, not a mutation.
    http_response_code(422);
}

$page_title = 'Add member';
$active_nav = 'Members';
$layout = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Add member</h1>
<p class="breadcrumb"><a href="/members/list.php">Members</a> &rsaquo; Add member</p>

<?php if ($errors !== []): ?>
  <div class="flash flash-error" role="alert">
    <p class="flash-text">The member was not saved. Fix the highlighted fields and save again.</p>
  </div>
<?php endif; ?>

<form class="form-panel form-grid-wide" action="/members/add.php" method="post" novalidate>
  <?= csrf_field() ?>

  <div class="field">
    <label for="first_name">First name <span class="req">*</span></label>
    <input type="text" id="first_name" name="first_name" value="<?= e($values['first_name']) ?>"
           placeholder="Marisol" required<?= field_aria($errors, 'first_name') ?>>
    <?= field_error_html($errors, 'first_name') ?>
  </div>

  <div class="field">
    <label for="last_name">Last name <span class="req">*</span></label>
    <input type="text" id="last_name" name="last_name" value="<?= e($values['last_name']) ?>"
           placeholder="Vega" required<?= field_aria($errors, 'last_name') ?>>
    <?= field_error_html($errors, 'last_name') ?>
  </div>

  <div class="field">
    <label for="email">Email <span class="req">*</span></label>
    <input type="email" id="email" name="email" value="<?= e($values['email']) ?>"
           placeholder="m.vega@example.org" required<?= field_aria($errors, 'email') ?>>
    <?= field_error_html($errors, 'email') ?>
    <p class="field-hint">Enter an email address with an @ and a domain.</p>
  </div>

  <div class="field">
    <label for="phone">Phone <span class="req">*</span></label>
    <input type="tel" id="phone" name="phone" value="<?= e($values['phone']) ?>"
           placeholder="801-555-0199" required<?= field_aria($errors, 'phone') ?>>
    <?= field_error_html($errors, 'phone') ?>
  </div>

  <div class="field">
    <label for="street">Street <span class="req">*</span></label>
    <input type="text" id="street" name="street" value="<?= e($values['street']) ?>"
           placeholder="1428 Wasatch Ave" required<?= field_aria($errors, 'street') ?>>
    <?= field_error_html($errors, 'street') ?>
  </div>

  <div class="field">
    <label for="city">City <span class="req">*</span></label>
    <input type="text" id="city" name="city" value="<?= e($values['city']) ?>"
           placeholder="Salt Lake City" required<?= field_aria($errors, 'city') ?>>
    <?= field_error_html($errors, 'city') ?>
  </div>

  <div class="field">
    <label for="state">State <span class="req">*</span></label>
    <input type="text" id="state" name="state" value="<?= e($values['state']) ?>"
           placeholder="UT" maxlength="2" required<?= field_aria($errors, 'state') ?>>
    <?= field_error_html($errors, 'state') ?>
  </div>

  <div class="field">
    <label for="zip">ZIP <span class="req">*</span></label>
    <input type="text" id="zip" name="zip" value="<?= e($values['zip']) ?>"
           placeholder="84104" inputmode="numeric" required<?= field_aria($errors, 'zip') ?>>
    <?= field_error_html($errors, 'zip') ?>
  </div>

  <div class="field">
    <span class="field-label">Member ID</span>
    <div class="readonly-box">Member ID: assigned on save</div>
  </div>

  <div class="field">
    <span class="field-label">Start date</span>
    <div class="readonly-box">Start date: today, read-only</div>
  </div>

  <div class="cell-full">
    <div class="form-actions cell-full">
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-secondary" href="/members/list.php">Cancel</a>
    </div>
  </div>
</form>

<p class="required-note">* Required. Email must be unique across every member (FR 18).</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
