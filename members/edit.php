<?php
/**
 * members/edit.php - UC 13, update a member (WF 05 in its edit state).
 *
 * The same contact fields as WF 04, pre-filled from the record. Member ID and
 * start date are shown read-only and are never updated (FR 19, DR 02). The
 * email uniqueness check ignores this member's own row (FR 18).
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../DB/members.php';

boot_session();
require_admin();

$id = get_str('id');
$member = $id === '' ? null : member_find($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = post_str('id');
    $member = $id === '' ? null : member_find($id);
}

if ($member === null) {
    http_response_code(404);
    $page_title = 'Member not found';
    $active_nav = 'Members';
    $layout = 'admin';
    require __DIR__ . '/../includes/header.php';
    ?>
    <h1 class="page-title">Member not found</h1>
    <p class="lede">No member has that ID, so there is nothing to edit.</p>
    <p><a class="btn btn-secondary" href="/members/list.php">Back to members</a></p>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$errors = [];
$values = [
    'first_name' => $member['first_name'],
    'last_name'  => $member['last_name'],
    'email'      => $member['email'],
    'phone'      => $member['phone'],
    'street'     => $member['street'],
    'city'       => $member['city'],
    'state'      => $member['state'],
    'zip'        => $member['zip'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach (array_keys($values) as $key) {
        $values[$key] = post_str($key);
    }

    $errors['first_name'] = validate_required($values['first_name'], 'a first name')
        ?? validate_max_length($values['first_name'], 60, 'the first name');
    $errors['last_name'] = validate_required($values['last_name'], 'a last name')
        ?? validate_max_length($values['last_name'], 60, 'the last name');
    $errors['email'] = validate_email($values['email']);
    if ($errors['email'] === null && member_email_taken($values['email'], $member['id'])) {
        $errors['email'] = 'That email already belongs to another member. Enter a different address (FR 18).';
    }
    $errors['phone'] = validate_phone($values['phone']);
    $errors['street'] = validate_required($values['street'], 'a street address')
        ?? validate_max_length($values['street'], 120, 'the street address');
    $errors['city'] = validate_required($values['city'], 'a city')
        ?? validate_max_length($values['city'], 80, 'the city');
    $errors['state'] = validate_state($values['state']);
    $errors['zip'] = validate_zip($values['zip']);

    $errors = array_filter($errors, static fn (?string $message): bool => $message !== null);

    if ($errors === []) {
        $updated = member_update($member['id'], $values);
        flash_set('success', 'Saved changes to ' . member_name($updated ?? $member) . '.');
        header('Location: /members/view.php?id=' . rawurlencode($member['id']), true, 303);
        exit;
    }
    http_response_code(422);
}

$page_title = 'Edit ' . member_name($member);
$active_nav = 'Members';
$layout = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<p class="breadcrumb">
  <a href="/members/list.php">Members</a> &rsaquo;
  <a href="/members/view.php?id=<?= e(rawurlencode($member['id'])) ?>"><?= e(member_name($member)) ?></a> &rsaquo;
  Edit
</p>

<div class="page-head">
  <div>
    <h1 class="page-title">Edit <?= e(member_name($member)) ?></h1>
    <p class="meta-line">
      <?= e($member['id']) ?> &nbsp;&middot;&nbsp; Joined <?= e(date_long((string) $member['joined'])) ?>
      &nbsp;&middot;&nbsp; <span class="badge <?= $member['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>"><?= e(member_statuses()[$member['status']] ?? $member['status']) ?></span>
    </p>
  </div>
</div>

<?php if ($errors !== []): ?>
  <div class="flash flash-error" role="alert">
    <p class="flash-text">The changes were not saved. Fix the highlighted fields and save again.</p>
  </div>
<?php endif; ?>

<form class="form-panel form-grid-wide" action="/members/edit.php" method="post" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= e($member['id']) ?>">

  <div class="field">
    <label for="first_name">First name <span class="req">*</span></label>
    <input type="text" id="first_name" name="first_name" value="<?= e($values['first_name']) ?>" required<?= field_aria($errors, 'first_name') ?>>
    <?= field_error_html($errors, 'first_name') ?>
  </div>

  <div class="field">
    <label for="last_name">Last name <span class="req">*</span></label>
    <input type="text" id="last_name" name="last_name" value="<?= e($values['last_name']) ?>" required<?= field_aria($errors, 'last_name') ?>>
    <?= field_error_html($errors, 'last_name') ?>
  </div>

  <div class="field">
    <label for="email">Email <span class="req">*</span></label>
    <input type="email" id="email" name="email" value="<?= e($values['email']) ?>" required<?= field_aria($errors, 'email') ?>>
    <?= field_error_html($errors, 'email') ?>
    <p class="field-hint">Enter an email address with an @ and a domain.</p>
  </div>

  <div class="field">
    <label for="phone">Phone <span class="req">*</span></label>
    <input type="tel" id="phone" name="phone" value="<?= e($values['phone']) ?>" required<?= field_aria($errors, 'phone') ?>>
    <?= field_error_html($errors, 'phone') ?>
  </div>

  <div class="field">
    <label for="street">Street <span class="req">*</span></label>
    <input type="text" id="street" name="street" value="<?= e($values['street']) ?>" required<?= field_aria($errors, 'street') ?>>
    <?= field_error_html($errors, 'street') ?>
  </div>

  <div class="field">
    <label for="city">City <span class="req">*</span></label>
    <input type="text" id="city" name="city" value="<?= e($values['city']) ?>" required<?= field_aria($errors, 'city') ?>>
    <?= field_error_html($errors, 'city') ?>
  </div>

  <div class="field">
    <label for="state">State <span class="req">*</span></label>
    <input type="text" id="state" name="state" value="<?= e($values['state']) ?>" maxlength="2" required<?= field_aria($errors, 'state') ?>>
    <?= field_error_html($errors, 'state') ?>
  </div>

  <div class="field">
    <label for="zip">ZIP <span class="req">*</span></label>
    <input type="text" id="zip" name="zip" value="<?= e($values['zip']) ?>" inputmode="numeric" required<?= field_aria($errors, 'zip') ?>>
    <?= field_error_html($errors, 'zip') ?>
  </div>

  <div class="field">
    <span class="field-label">Member ID</span>
    <div class="readonly-box"><?= e($member['id']) ?> &nbsp;·&nbsp; read-only</div>
  </div>

  <div class="field">
    <span class="field-label">Start date</span>
    <div class="readonly-box"><?= e(date_long((string) $member['joined'])) ?> &nbsp;·&nbsp; read-only</div>
  </div>

  <div class="cell-full">
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Save changes</button>
      <a class="btn btn-secondary" href="/members/view.php?id=<?= e(rawurlencode($member['id'])) ?>">Cancel</a>
    </div>
  </div>
</form>

<p class="required-note">* Required. Email must be unique across every member (FR 18). Member ID and start date never change (DR 02).</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
