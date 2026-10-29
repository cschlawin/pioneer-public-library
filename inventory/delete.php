<?php
/**
 * inventory/delete.php - UC 17, delete an inventory item.
 *
 * A soft delete: the item is flagged deleted and its copy records are kept,
 * so checkout history stays complete (DR 14). It is refused outright while any
 * copy is checked out (BR 07, FR 24), and the refusal names how many copies
 * are out and what to do about it.
 *
 * POST only, with a CSRF token. A GET gets 405, so a link or a prefetch can
 * never delete anything.
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../DB/inventory.php';

boot_session();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    $page_title = 'Method not allowed';
    $active_nav = 'Inventory';
    $layout = 'admin';
    require __DIR__ . '/../includes/header.php';
    ?>
    <h1 class="page-title">Method not allowed</h1>
    <p class="lede">Deleting an item is a POST action. Open the item and use its Delete button.</p>
    <p><a class="btn btn-secondary" href="/inventory/list.php">Back to inventory</a></p>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

csrf_check();

$id = post_str('id');
$item = $id === '' ? null : inventory_find($id);

if ($item === null || !empty($item['deleted'])) {
    flash_set('error', 'No inventory item has that ID, so nothing was deleted.');
    header('Location: /inventory/list.php', true, 303);
    exit;
}

[$ok, $message] = inventory_soft_delete($item['id']);

if ($ok) {
    flash_set('success', $message);
    header('Location: /inventory/list.php', true, 303);
    exit;
}

// Refused: say why, and send the user back to the record that explains it.
flash_set('error', $message);
header('Location: /inventory/view.php?id=' . rawurlencode($item['id']), true, 303);
exit;
