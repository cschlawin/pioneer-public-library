<?php
/**
 * members/delete.php - UC 13, deactivate or reactivate a member.
 *
 * Despite the file name the action is never a row removal: a member is
 * deactivated, keeping the record and all of its checkout and fee history
 * (BR 06, FR 20, DR 14).
 *
 * POST only, with a CSRF token. A GET is refused with 405 rather than being
 * treated as a delete, so a link, a prefetch, or a crawler can never change
 * the table.
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../DB/members.php';

boot_session();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    $page_title = 'Method not allowed';
    $active_nav = 'Members';
    $layout = 'admin';
    require __DIR__ . '/../includes/header.php';
    ?>
    <h1 class="page-title">Method not allowed</h1>
    <p class="lede">Changing a member's status is a POST action. Open the member record and use its Deactivate button.</p>
    <p><a class="btn btn-secondary" href="/members/list.php">Back to members</a></p>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

csrf_check();

$id = post_str('id');
$action = post_str('action', 'deactivate');
$member = $id === '' ? null : member_find($id);

if ($member === null) {
    flash_set('error', 'No member has that ID, so nothing was changed.');
    header('Location: /members/list.php', true, 303);
    exit;
}

// deactivate -> inactive, reactivate -> active.
$newStatus = $action === 'reactivate' ? 'active' : 'inactive';

// Refuse a no-op rather than reporting a change that did not happen.
if ($member['status'] === $newStatus) {
    flash_set('error', member_name($member) . ' is already ' . $newStatus . '. Nothing was changed.');
    header('Location: /members/view.php?id=' . rawurlencode($member['id']), true, 303);
    exit;
}

$updated = member_set_status($member['id'], $newStatus);

if ($updated === null) {
    flash_set('error', 'The status change could not be saved.');
    header('Location: /members/view.php?id=' . rawurlencode($member['id']), true, 303);
    exit;
}

if ($newStatus === 'inactive') {
    flash_set('success', 'Deactivated ' . member_name($updated) . '. The record stays in the list and all checkout and fee history is kept (BR 06).');
} else {
    flash_set('success', 'Reactivated ' . member_name($updated) . '. Borrowing is available again.');
}

header('Location: /members/view.php?id=' . rawurlencode($updated['id']), true, 303);
exit;
