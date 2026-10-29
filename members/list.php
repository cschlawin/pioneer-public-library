<?php
/**
 * members/list.php - UC 10, view and search members (WF 03).
 *
 * Server-side search over the whole table (not just the visible page) plus a
 * status filter. Deactivated members stay in the list: the row is flagged
 * inactive and never removed (BR 06).
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../DB/members.php';

boot_session();
require_admin();

$search = get_str('search');
$status = get_str('status', 'all');
if (!in_array($status, ['all', 'active', 'inactive'], true)) {
    $status = 'all';
}

$rows = members_search(['search' => $search, 'status' => $status]);
$total = count(members_all());

$page_title = 'Members';
$active_nav = 'Members';
$layout = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-title">Members</h1>
    <p class="lede">Search the whole member table by last name, email, or member ID.</p>
  </div>
</div>

<form class="toolbar" action="/members/list.php" method="get" role="search">
  <div class="field">
    <label for="search">Search members</label>
    <input type="search" id="search" name="search" value="<?= e($search) ?>"
           placeholder="Search last name, email, or member ID">
  </div>
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status">
      <option value="all"<?= $status === 'all' ? ' selected' : '' ?>>All</option>
      <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option>
      <option value="inactive"<?= $status === 'inactive' ? ' selected' : '' ?>>Inactive</option>
    </select>
  </div>
  <button class="btn btn-primary" type="submit">Search</button>
  <?php if ($search !== '' || $status !== 'all'): ?>
    <a class="btn btn-secondary" href="/members/list.php">Clear</a>
  <?php endif; ?>
  <span class="count-note">Showing <?= count($rows) ?> of <?= (int) $total ?></span>
  <span class="toolbar-spacer"></span>
  <a class="btn btn-primary" href="/members/add.php">Add member</a>
</form>

<?php if ($rows === []): ?>
  <div class="empty-state">
    <h2>No members match that search</h2>
    <p>Try a different name, email, or member ID, or clear the filters.</p>
    <a class="btn btn-secondary" href="/members/list.php">Clear the search</a>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <caption class="sr-only">Members, showing <?= count($rows) ?> of <?= (int) $total ?> records with member ID, name, email, phone, status, and outstanding balance</caption>
      <thead>
        <tr>
          <th scope="col">Member ID</th>
          <th scope="col">Name</th>
          <th scope="col">Email</th>
          <th scope="col">Phone</th>
          <th scope="col">Status</th>
          <th scope="col" class="num">Balance</th>
          <th scope="col">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $member): ?>
          <tr>
            <td><a href="/members/view.php?id=<?= e(rawurlencode($member['id'])) ?>"><?= e($member['id']) ?></a></td>
            <td><?= e(member_name($member)) ?></td>
            <td><?= e($member['email']) ?></td>
            <td><?= e($member['phone']) ?></td>
            <td>
              <span class="badge badge-<?= e($member['status']) ?>"><?= e(member_statuses()[$member['status']] ?? $member['status']) ?></span>
            </td>
            <td class="num"><?= e(money($member['balance'])) ?></td>
            <td class="actions-cell">
              <a class="link-action" href="/members/edit.php?id=<?= e(rawurlencode($member['id'])) ?>">Edit</a>
              <?php if ($member['status'] === 'active'): ?>
                <a class="link-action link-danger" href="/members/view.php?id=<?= e(rawurlencode($member['id'])) ?>#deactivate">Deactivate</a>
              <?php else: ?>
                <a class="link-action" href="/members/view.php?id=<?= e(rawurlencode($member['id'])) ?>#deactivate">Reactivate</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="table-note">Deactivated members stay listed; the record is never removed (BR 06).</p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
