<?php
/**
 * members/view.php - UC 12, member detail (WF 05).
 *
 * Shows the contact fields, the read-only checkout history and fees that stay
 * visible after deactivation (BR 06), and the Deactivate or Reactivate control.
 * The control posts to members/delete.php; nothing changes on this GET.
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../DB/members.php';

boot_session();
require_admin();

$id = get_str('id');
$member = $id === '' ? null : member_find($id);
if ($member === null) {
    http_response_code(404);
    $page_title = 'Member not found';
    $active_nav = 'Members';
    $layout = 'admin';
    require __DIR__ . '/../includes/header.php';
    ?>
    <h1 class="page-title">Member not found</h1>
    <p class="lede">No member has the ID <?= e($id) ?>.</p>
    <p><a class="btn btn-secondary" href="/members/list.php">Back to members</a></p>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$isActive = $member['status'] === 'active';
$unpaid = 0.00;
foreach ($member['fees'] as $fee) {
    if (($fee['status'] ?? '') !== 'Paid') {
        $unpaid += (float) $fee['amount'];
    }
}

$page_title = member_name($member);
$active_nav = 'Members';
$layout = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<p class="breadcrumb"><a href="/members/list.php">Members</a> &rsaquo; <?= e($member['id']) ?></p>

<div class="page-head">
  <div>
    <div class="title-with-badge">
      <h1 class="page-title"><?= e(member_name($member)) ?></h1>
      <span class="badge <?= $isActive ? 'badge-active' : 'badge-inactive' ?>">
        <?= e(member_statuses()[$member['status']] ?? $member['status']) ?>
      </span>
    </div>
    <p class="meta-line">
      <?= e($member['id']) ?> &nbsp;&middot;&nbsp;
      Joined <?= e(date_long((string) $member['joined'])) ?> &nbsp;&middot;&nbsp;
      Balance <?= e(money($member['balance'])) ?>
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-primary" href="/members/edit.php?id=<?= e(rawurlencode($member['id'])) ?>">Edit member</a>
    <a class="btn btn-secondary" href="/members/list.php">Back to list</a>
  </div>
</div>

<div class="detail-grid">
  <section aria-labelledby="contact-title">
    <h2 class="detail-section-h" id="contact-title">Contact information</h2>
    <div class="panel">
      <dl class="detail-list">
        <div class="detail-row"><dt>First name</dt><dd><?= e($member['first_name']) ?></dd></div>
        <div class="detail-row"><dt>Last name</dt><dd><?= e($member['last_name']) ?></dd></div>
        <div class="detail-row"><dt>Email</dt><dd><?= e($member['email']) ?></dd></div>
        <div class="detail-row"><dt>Phone</dt><dd><?= e($member['phone']) ?></dd></div>
        <div class="detail-row"><dt>Street</dt><dd><?= e($member['street']) ?></dd></div>
        <div class="detail-row"><dt>City</dt><dd><?= e($member['city']) ?></dd></div>
        <div class="detail-row"><dt>State</dt><dd><?= e($member['state']) ?></dd></div>
        <div class="detail-row"><dt>ZIP</dt><dd><?= e($member['zip']) ?></dd></div>
        <div class="detail-row"><dt>Member ID</dt><dd><?= e($member['id']) ?> <span class="muted">(read-only)</span></dd></div>
        <div class="detail-row"><dt>Start date</dt><dd><?= e(date_long((string) $member['joined'])) ?> <span class="muted">(read-only)</span></dd></div>
      </dl>
    </div>

    <div class="panel danger-panel" id="deactivate">
      <h2 class="detail-section-h"><?= $isActive ? 'Deactivate this member' : 'Reactivate this member' ?></h2>
      <p class="muted">
        <?php if ($isActive): ?>
          Deactivating stops borrowing but keeps all history (BR 06). The record and every checkout and fee stay in the table.
        <?php else: ?>
          This member is inactive. Reactivating restores borrowing rights; the history was never lost (BR 06).
        <?php endif; ?>
      </p>
      <form action="/members/delete.php" method="post"
            data-confirm="<?= $isActive
                ? 'Deactivate ' . e(member_name($member)) . '? The record stays and all history is kept.'
                : 'Reactivate ' . e(member_name($member)) . '?' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($member['id']) ?>">
        <input type="hidden" name="action" value="<?= $isActive ? 'deactivate' : 'reactivate' ?>">
        <button class="btn <?= $isActive ? 'btn-danger' : 'btn-primary' ?>" type="submit">
          <?= $isActive ? 'Deactivate' : 'Reactivate' ?>
        </button>
      </form>
      <p class="hint">Deactivating stops borrowing but keeps all history (BR 06).</p>
    </div>
  </section>

  <section aria-labelledby="history-title">
    <h2 class="detail-section-h" id="history-title">Checkout history</h2>
    <?php if ($member['checkouts'] === []): ?>
      <div class="empty-state"><p>No checkouts on this account yet.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <caption class="sr-only">Checkout history for <?= e(member_name($member)) ?>: item, checkout date, due date, and status</caption>
          <thead>
            <tr><th scope="col">Item</th><th scope="col">Out</th><th scope="col">Due</th><th scope="col">Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($member['checkouts'] as $loan): ?>
              <tr>
                <td><?= e($loan['title']) ?></td>
                <td><?= e(date_mmdd((string) $loan['out'])) ?></td>
                <td><?= e(date_mmdd((string) $loan['due'])) ?></td>
                <td><?= e($loan['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <h2 class="detail-section-h" id="fees-title">Fees</h2>
    <?php if ($member['fees'] === []): ?>
      <div class="empty-state"><p>No fees on this account.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <caption class="sr-only">Fees for <?= e(member_name($member)) ?>: item, return date, days late, amount, and status</caption>
          <thead>
            <tr><th scope="col">Item</th><th scope="col">Returned</th><th scope="col">Days late</th><th scope="col" class="num">Amount</th><th scope="col">Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($member['fees'] as $fee): ?>
              <tr>
                <td><?= e($fee['title']) ?></td>
                <td><?= e(date_mmdd((string) $fee['returned'])) ?></td>
                <td class="num"><?= (int) $fee['days_late'] ?></td>
                <td class="num"><?= e(money($fee['amount'])) ?></td>
                <td><?= e($fee['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <p class="balance-line">
      Unpaid balance: <?= e(money($unpaid)) ?> &nbsp;&middot;&nbsp;
      <?= $unpaid <= 0.0 ? 'All fees settled' : 'Payment is recorded by staff in Phase 2' ?>
    </p>
    <p class="table-note">
      <?= $isActive ? 'History stays visible while the account is active.' : 'This account is inactive; the history above is retained (BR 06).' ?>
    </p>
  </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
