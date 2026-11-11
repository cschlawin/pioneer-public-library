<?php
/**
 * circulation/return.php - UC 06, return an item at the staff desk (WF 21).
 *
 * Staff scan or type the barcode of the copy being returned. One POST closes
 * the open loan with today's date and marks the copy available in a single
 * transaction (DB/checkouts.php). Refused when the barcode is unknown or the
 * copy has no open loan. Late fees (BR 02) are a later step; the page shows
 * the days late so staff can tell the member.
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../DB/checkouts.php';

boot_session();
$user = require_admin();

$errors = [];
$values = ['barcode' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $values['barcode'] = post_str('barcode');

    if (barcode_normalize($values['barcode']) === '') {
        $errors['barcode'] = 'Enter the copy barcode.';
    } elseif (strlen($values['barcode']) > 40) {
        $errors['barcode'] = 'A barcode is at most 40 characters.';
    }

    if ($errors === []) {
        try {
            $loan = checkout_return($values['barcode'], (string) $user['username']);
            $late = (int) $loan['days_late'];
            flash_set('success', 'Returned ' . $loan['title'] . ' (' . $loan['copy_label'] . ', ' . $loan['barcode']
                . ') from ' . $loan['member_name'] . '. '
                . ($late > 0
                    ? $late . ' ' . ($late === 1 ? 'day' : 'days') . ' late; the late fee is recorded in a later step (BR 02).'
                    : 'Returned on time.')
                . ' The copy is available again.');
            header('Location: /circulation/return.php', true, 303);
            exit;
        } catch (CheckoutRefused $refused) {
            $errors[$refused->field] = $refused->getMessage();
        }
    }
    http_response_code(422);
}

$open = checkouts_open();
$returns = checkouts_recent_returns(5);

$page_title = 'Return';
$active_nav = 'Return';
$layout = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-title">Return an item</h1>
    <p class="lede">Scan or type the barcode of the copy being returned. The loan closes with today's date and the copy goes back to available.</p>
  </div>
  <div class="actions">
    <a class="btn btn-secondary" href="/circulation/checkout.php">Check out an item</a>
  </div>
</div>

<?php if ($errors !== []): ?>
  <div class="flash flash-error" role="alert">
    <p class="flash-text">No return was recorded. <?= e((string) reset($errors)) ?></p>
  </div>
<?php endif; ?>

<form class="form-panel form-grid form-grid-wide" action="/circulation/return.php" method="post" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="barcode">Copy barcode <span class="req">*</span></label>
    <input type="text" id="barcode" name="barcode" value="<?= e($values['barcode']) ?>" maxlength="40"
           autocomplete="off" placeholder="30111 00483" required<?= field_aria($errors, 'barcode') ?>>
    <?= field_error_html($errors, 'barcode') ?>
    <p class="field-hint">The space in the barcode is optional.</p>
  </div>
  <div class="cell-full">
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Record return</button>
    </div>
  </div>
</form>

<h2 class="section-title" id="open-title">Copies checked out (<?= count($open) ?>)</h2>
<?php if ($open === []): ?>
  <div class="empty-state"><p>Every copy is in. Nothing is checked out.</p></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="data" aria-labelledby="open-title">
      <caption class="sr-only">Open loans, soonest due first: item, copy and barcode, member, checkout date, due date, and status</caption>
      <thead>
        <tr>
          <th scope="col">Item</th>
          <th scope="col">Copy</th>
          <th scope="col">Member</th>
          <th scope="col">Out</th>
          <th scope="col">Due</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($open as $loan): ?>
          <tr>
            <td><?= e($loan['title']) ?></td>
            <td><?= e($loan['copy_label']) ?> &middot; <?= e($loan['barcode']) ?></td>
            <td><a class="link-action" href="/members/view.php?id=<?= e(rawurlencode((string) $loan['member_id'])) ?>"><?= e($loan['member_name']) ?></a></td>
            <td><?= e(date_mmdd((string) $loan['out'])) ?></td>
            <td><?= e(date_mmdd((string) $loan['due'])) ?></td>
            <td>
              <span class="badge <?= e(loan_badge_class($loan)) ?>"><?= e($loan['status']) ?></span>
              <?php if ($loan['days_late'] > 0): ?>
                <span class="muted"><?= (int) $loan['days_late'] ?> <?= $loan['days_late'] === 1 ? 'day' : 'days' ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if ($returns !== []): ?>
  <h2 class="section-title" id="returns-title">Recent returns</h2>
  <div class="table-wrap">
    <table class="data" aria-labelledby="returns-title">
      <caption class="sr-only">The five most recent returns: item, copy, member, due date, return date, and days late</caption>
      <thead>
        <tr>
          <th scope="col">Item</th>
          <th scope="col">Copy</th>
          <th scope="col">Member</th>
          <th scope="col">Due</th>
          <th scope="col">Returned</th>
          <th scope="col" class="num">Days late</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($returns as $loan): ?>
          <tr>
            <td><?= e($loan['title']) ?></td>
            <td><?= e($loan['copy_label']) ?> &middot; <?= e($loan['barcode']) ?></td>
            <td><?= e($loan['member_name']) ?></td>
            <td><?= e(date_mmdd((string) $loan['due'])) ?></td>
            <td><?= e(date_mmdd((string) $loan['returned'])) ?></td>
            <td class="num"><?= (int) $loan['days_late'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
