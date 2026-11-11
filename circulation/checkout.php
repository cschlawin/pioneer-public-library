<?php
/**
 * circulation/checkout.php - UC 04, check out an item at the staff desk (WF 20).
 *
 * Staff pick the member and scan or type the copy barcode. One POST writes
 * the loan and marks the copy checked out in a single transaction
 * (DB/checkouts.php). The due date is 30 days out (BR 01). Refused for an
 * inactive member (BR 05), a fee balance at or above the limit (BR 03), an
 * unknown barcode, a copy that is not available (BR 04), or a deleted item.
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../DB/checkouts.php';

boot_session();
$user = require_admin();

$errors = [];
$values = [
    'member_id' => strtoupper(get_str('member_id')),
    'barcode'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $values['member_id'] = strtoupper(post_str('member_id'));
    $values['barcode'] = post_str('barcode');

    if ($values['member_id'] === '') {
        $errors['member_id'] = 'Choose the member who is borrowing.';
    }
    if (barcode_normalize($values['barcode']) === '') {
        $errors['barcode'] = 'Enter the copy barcode.';
    } elseif (strlen($values['barcode']) > 40) {
        $errors['barcode'] = 'A barcode is at most 40 characters.';
    }

    if ($errors === []) {
        try {
            $loan = checkout_create($values['member_id'], $values['barcode'], (string) $user['username']);
            flash_set('success', 'Checked out ' . $loan['title'] . ' (' . $loan['copy_label'] . ', ' . $loan['barcode']
                . ') to ' . $loan['member_name'] . '. Due ' . date_long((string) $loan['due']) . '. Checkout ' . $loan['id'] . '.');
            header('Location: /circulation/checkout.php?member_id=' . rawurlencode((string) $loan['member_id']), true, 303);
            exit;
        } catch (CheckoutRefused $refused) {
            $errors[$refused->field] = $refused->getMessage();
        }
    }
    http_response_code(422);
}

$members = members_all();
usort($members, static fn (array $a, array $b): int => strcmp(member_name($a), member_name($b)));
$recent = checkouts_recent(10);
$dueIfToday = checkout_due_date(checkout_today());

$page_title = 'Check out';
$active_nav = 'Check out';
$layout = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-title">Check out an item</h1>
    <p class="lede">Choose the member, then scan or type the copy barcode. A checkout made today is due <?= e(date_long($dueIfToday)) ?> (<?= (int) LOAN_PERIOD_DAYS ?> days, BR 01).</p>
  </div>
  <div class="actions">
    <a class="btn btn-secondary" href="/circulation/return.php">Record a return</a>
  </div>
</div>

<?php if ($errors !== []): ?>
  <div class="flash flash-error" role="alert">
    <p class="flash-text">Nothing was checked out. <?= e((string) reset($errors)) ?></p>
  </div>
<?php endif; ?>

<form class="form-panel form-grid form-grid-wide" action="/circulation/checkout.php" method="post" novalidate>
  <?= csrf_field() ?>

  <div class="field">
    <label for="member_id">Member <span class="req">*</span></label>
    <select id="member_id" name="member_id" required<?= field_aria($errors, 'member_id') ?>>
      <option value="">Choose a member</option>
      <?php foreach ($members as $member): ?>
        <option value="<?= e($member['id']) ?>"<?= $values['member_id'] === $member['id'] ? ' selected' : '' ?>>
          <?= e(member_name($member)) ?> (<?= e($member['id']) ?>)<?= $member['status'] !== 'active' ? ' - inactive' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?= field_error_html($errors, 'member_id') ?>
    <p class="field-hint">Inactive members are listed but cannot borrow (BR 05).</p>
  </div>

  <div class="field">
    <label for="barcode">Copy barcode <span class="req">*</span></label>
    <input type="text" id="barcode" name="barcode" value="<?= e($values['barcode']) ?>" maxlength="40"
           autocomplete="off" placeholder="30111 00482" required<?= field_aria($errors, 'barcode') ?>>
    <?= field_error_html($errors, 'barcode') ?>
    <p class="field-hint">The space in the barcode is optional.</p>
  </div>

  <div class="cell-full">
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Check out</button>
      <a class="btn btn-secondary" href="/circulation/checkout.php">Clear</a>
    </div>
  </div>
</form>

<p class="required-note">* Required. Only an available copy of an item that is not deleted can go out, and only to an active member whose unpaid fees are under <?= e(money(FEE_BLOCK_LIMIT)) ?> (BR 03, BR 04, BR 05).</p>

<h2 class="section-title" id="recent-title">Recent checkouts</h2>
<?php if ($recent === []): ?>
  <div class="empty-state"><p>No checkouts yet.</p></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="data" aria-labelledby="recent-title">
      <caption class="sr-only">The ten most recent checkouts: checkout number, item, copy, member, checkout date, due date, and status</caption>
      <thead>
        <tr>
          <th scope="col">Checkout</th>
          <th scope="col">Item</th>
          <th scope="col">Copy</th>
          <th scope="col">Member</th>
          <th scope="col">Out</th>
          <th scope="col">Due</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $loan): ?>
          <tr>
            <td><?= e($loan['id']) ?></td>
            <td><a class="link-action" href="/inventory/view.php?id=<?= e(rawurlencode((string) $loan['item_id'])) ?>"><?= e($loan['title']) ?></a></td>
            <td><?= e($loan['copy_label']) ?> &middot; <?= e($loan['barcode']) ?></td>
            <td><a class="link-action" href="/members/view.php?id=<?= e(rawurlencode((string) $loan['member_id'])) ?>"><?= e($loan['member_name']) ?></a></td>
            <td><?= e(date_mmdd((string) $loan['out'])) ?></td>
            <td><?= e(date_mmdd((string) $loan['due'])) ?></td>
            <td><span class="badge <?= e(loan_badge_class($loan)) ?>"><?= e($loan['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
