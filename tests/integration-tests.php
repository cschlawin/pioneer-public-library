<?php
/**
 * tests/integration-tests.php
 *
 * CLI integration checks for member management, inventory management, and
 * checkout and return, against a throwaway copy of the database:
 *
 *     php tests/integration-tests.php
 *
 * It builds a fresh temporary database from DB/setup-database.sql, so
 * DB/library.db is never touched. Exits 1 if any check fails.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

$tmp = tempnam(sys_get_temp_dir(), 'ppl-test-');
unlink($tmp);
$tmp .= '.db';
putenv('PPL_DB_PATH=' . $tmp);
register_shutdown_function(static function () use ($tmp): void {
    if (is_file($tmp)) {
        unlink($tmp);
    }
});

require_once dirname(__DIR__) . '/DB/members.php';
require_once dirname(__DIR__) . '/DB/inventory.php';
require_once dirname(__DIR__) . '/DB/checkouts.php';

$failed = 0;
$passed = 0;
function check(bool $ok, string $label): void
{
    global $failed, $passed;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}
function throws(callable $fn, string $class = Throwable::class): bool
{
    try {
        $fn();
        return false;
    } catch (Throwable $e) {
        return $e instanceof $class;
    }
}

$pdo = db();
check(realpath(db_path()) !== realpath(DB_DEFAULT_PATH), 'Tests run on a temporary database, not DB/library.db');
check((int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() === 1, 'Foreign keys are enforced on the connection');
check(count(members_all()) === 6 && count(inventory_all()) === 7, 'Seed: 6 members and 7 items');
check((int) $pdo->query('SELECT COUNT(*) FROM Copy')->fetchColumn() === 17, 'Seed: 17 copies');

// ---- Set 1: members (UC 10-13) ------------------------------------------
$thao = member_find('M-01045');
check($thao !== null && $thao['email'] === 't.nguyen@example.org' && count($thao['checkouts']) === 4,
    'UC 11 view: seeded member M-01045 with WF 05 history');
check(member_find('M-00778')['balance'] === 4.5, 'Balance derived from unpaid fees (M-00778 owes 4.50)');

$new = member_insert([
    'first_name' => 'Jordan', 'last_name' => 'Lee', 'email' => 'jordan.lee@example.org',
    'phone' => '801-555-0101', 'street' => '1 Main St', 'city' => 'Ogden', 'state' => 'ut', 'zip' => '84401',
]);
check($new['id'] === 'M-01211' && $new['status'] === 'active' && $new['state'] === 'UT'
    && $new['joined'] === date('Y-m-d'), 'UC 10 add: new member M-01211, active, joined today');
check(member_find('M-01211') !== null, 'UC 10 add: member persisted in SQLite');

check(member_email_taken('  T.NGUYEN@example.org '), 'FR 18: duplicate email detected (case and spaces ignored)');
check(!member_email_taken('t.nguyen@example.org', 'M-01045'), 'FR 18: own email is not a duplicate on edit');
check(throws(static fn () => member_insert([
    'first_name' => 'Dup', 'last_name' => 'Email', 'email' => 'T.Nguyen@Example.org',
    'phone' => '', 'street' => 'x', 'city' => 'x', 'state' => 'UT', 'zip' => '84101',
]), PDOException::class), 'FR 18: UNIQUE constraint refuses a duplicate email at the database');
check(count(members_all()) === 7, 'Refused duplicate wrote nothing');

$upd = member_update('M-01211', [
    'first_name' => 'Jordan', 'last_name' => 'Lee-Park', 'email' => 'jordan.lp@example.org',
    'phone' => '801-555-0102', 'street' => '2 Main St', 'city' => 'Ogden', 'state' => 'UT', 'zip' => '84401',
]);
check($upd !== null && $upd['last_name'] === 'Lee-Park' && $upd['joined'] === $new['joined'],
    'UC 12 update: fields saved, joined date unchanged');
check(member_update('M-99999', $upd) === null, 'UC 12 update: unknown id returns null');

$deact = member_set_status('M-01211', 'inactive');
check($deact !== null && $deact['status'] === 'inactive', 'UC 13 delete: member deactivated');
check(member_find('M-01211') !== null, 'BR 06: deactivated member row is kept, not deleted');
check(member_set_status('M-01211', 'deleted') === null, 'BR 06: only active/inactive are accepted');
check(throws(static fn () => $pdo->exec("UPDATE Member SET Member_Status = 'gone' WHERE Member_ID = 'M-01211'"),
    PDOException::class), 'CHECK constraint refuses an invalid member status');
check(array_column(members_search(['search' => 'lee-park', 'status' => 'inactive']), 'id') === ['M-01211'],
    'WF 03 search plus status filter');

members_reset();
check(count(members_all()) === 6 && member_find('M-01211') === null && member_find('M-00312') !== null,
    'Reset restores the six seeded members');

// ---- Set 2: inventory (UC 14-17) ----------------------------------------
$over = inventory_find('I-04412');
check($over !== null && inventory_counts($over) === ['total' => 6, 'available' => 3, 'out' => 2, 'missing' => 1],
    'UC 15 view: I-04412 copy counts derived from copies (FR 22)');

$item = inventory_insert([
    'media_type' => 'book', 'title' => 'Piranesi', 'creator' => 'Susanna Clarke',
    'publisher' => 'Bloomsbury', 'publish_date' => '2020-09-15', 'genre' => 'Fantasy', 'isbn' => '978-1635575637',
], 3);
check($item['id'] === 'I-05221' && count($item['copies']) === 3 && $item['copies'][0]['barcode'] === '30111 00903'
    && $item['copies'][2]['id'] === 'C-3', 'UC 14 add: item I-05221 with 3 copies and new barcodes');
check(array_unique(array_column($item['copies'], 'status')) === ['available'], 'BR 08: new copies start available');
check((int) $pdo->query("SELECT COUNT(*) FROM Book WHERE Item_ID = 'I-05221'")->fetchColumn() === 1,
    'UC 14 add: descriptive fields stored in the Book subtype table');

$countBefore = (int) $pdo->query('SELECT COUNT(*) FROM Item')->fetchColumn();
// Make the second copy insert fail, after the item, subtype and first copy rows are written.
$pdo->exec("CREATE TEMP TRIGGER fail_second_copy BEFORE INSERT ON Copy WHEN NEW.Copy_Label = 'C-2'
    BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
check(throws(static fn () => inventory_insert(['media_type' => 'music', 'title' => 'Giant Steps', 'creator' => 'John Coltrane'], 2),
    PDOException::class), 'Item insert failing on the second copy is refused');
$pdo->exec('DROP TRIGGER fail_second_copy');
check((int) $pdo->query('SELECT COUNT(*) FROM Item')->fetchColumn() === $countBefore
    && (int) $pdo->query("SELECT COUNT(*) FROM Music WHERE Item_ID = 'I-05222'")->fetchColumn() === 0
    && (int) $pdo->query("SELECT COUNT(*) FROM Copy WHERE Item_ID = 'I-05222'")->fetchColumn() === 0,
    'Transaction rollback: no partial item or copies left behind');

$copies = $item['copies'];
$copies[1]['status'] = 'missing';
$copies[1]['condition'] = 'poor';
$upd = inventory_update('I-05221', ['media_type' => 'book', 'title' => 'Piranesi (PB)', 'creator' => 'Susanna Clarke',
    'publisher' => 'Bloomsbury', 'publish_date' => '2020-09-15', 'genre' => 'Fantasy', 'isbn' => '978-1635575637'], $copies);
check($upd['title'] === 'Piranesi (PB)' && $upd['copies'][1]['status'] === 'missing' && $upd['copies'][1]['condition'] === 'poor',
    'UC 16 update: title and copy condition/status saved');

$copies[0]['status'] = 'checked out';
check(throws(static fn () => inventory_update('I-05221', ['media_type' => 'book', 'title' => 'Piranesi (PB)',
    'creator' => 'Susanna Clarke'], $copies), InvalidArgumentException::class),
    'BR 08: checked out cannot be set by hand');
check(inventory_find('I-05221')['copies'][0]['status'] === 'available' && inventory_find('I-05221')['title'] === 'Piranesi (PB)',
    'BR 08 refusal rolled back the whole update');

$overCopies = $over['copies'];
$overCopies[1]['status'] = 'available';
inventory_update('I-04412', $over, $overCopies);
check(inventory_find('I-04412')['copies'][1]['status'] === 'checked out', 'A checked-out copy keeps its status on edit');
check(throws(static fn () => $pdo->exec("UPDATE Copy SET Copy_Status = 'lost' WHERE Copy_ID = 1"), PDOException::class),
    'CHECK constraint refuses an invalid copy status');

[$ok, $msg] = inventory_soft_delete('I-04412');
check(!$ok && str_contains($msg, '2 copies are checked out') && !inventory_find('I-04412')['deleted'],
    'BR 07: delete refused while copies are checked out');
[$ok, $msg] = inventory_soft_delete('I-05221');
check($ok && inventory_find('I-05221')['deleted'] === true, 'UC 17 delete: item soft-deleted');
check(count(inventory_find('I-05221')['copies']) === 3, 'DR 14: copies kept after soft delete');
check(!in_array('I-05221', array_column(inventory_search([]), 'id'), true), 'Soft-deleted item hidden from search');
[$ok] = inventory_soft_delete('I-05221');
check(!$ok, 'Second delete refused');
check(array_column(inventory_search(['search' => 'overstory', 'status' => 'out']), 'id') === ['I-04412']
    && array_column(inventory_search(['type' => 'film']), 'id') === ['I-03881'],
    'WF 06 search, type and status filters');
check(throws(static fn () => $pdo->exec("INSERT INTO Copy (Item_ID, Copy_Label, Barcode) VALUES ('I-99999', 'C-1', 'x')"),
    PDOException::class), 'Foreign key refuses a copy for an unknown item');

inventory_reset();
check(count(inventory_all()) === 7 && inventory_find('I-05221') === null
    && (int) $pdo->query('SELECT COUNT(*) FROM Copy')->fetchColumn() === 17, 'Reset restores the seeded inventory');

// ---- Set 3: checkout and return (UC 04, UC 06) -----------------------------
$count = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();
$copyStatus = static fn (int $id): string => (string) $pdo->query('SELECT Copy_Status FROM Copy WHERE Copy_ID = ' . $id)->fetchColumn();
$refusedOn = static function (callable $fn, string $field, string $needle): bool {
    try {
        $fn();
        return false;
    } catch (CheckoutRefused $e) {
        return $e->field === $field && str_contains($e->getMessage(), $needle);
    }
};
$openMatchesCopies = static fn (): bool => $pdo->query("SELECT Copy_ID FROM Checkout WHERE Check_In_Date IS NULL ORDER BY Copy_ID")
        ->fetchAll(PDO::FETCH_COLUMN)
    === $pdo->query("SELECT Copy_ID FROM Copy WHERE Copy_Status = 'checked out' ORDER BY Copy_ID")->fetchAll(PDO::FETCH_COLUMN);

check($count('SELECT COUNT(*) FROM Checkout') === 9 && $count('SELECT COUNT(*) FROM Checkout WHERE Check_In_Date IS NULL') === 5,
    'Seed: 9 checkouts, 5 open');
check($openMatchesCopies(), 'Seed: every checked-out copy has exactly one open loan');
$thaoLoans = checkouts_for_member('M-01045');
check(count($thaoLoans) === 4 && $thaoLoans[0]['title'] === 'The Overstory' && $thaoLoans[0]['returned'] === null
    && $thaoLoans[3]['title'] === 'Klara and the Sun' && $thaoLoans[3]['returned'] === '2026-08-11',
    'WF 05: member history comes from the Checkout table, open loan first');
check(member_find('M-01045')['checkouts'] === $thaoLoans, 'member_find returns the same loans');
check(checkout_due_date('2026-11-11') === '2026-12-11' && LOAN_PERIOD_DAYS === 30, 'BR 01: due date is 30 days after checkout');
check(checkout_from_row(checkout_find('L-00005'), '2026-11-11')['status'] === 'Overdue'
    && checkout_from_row(checkout_find('L-00005'), '2026-11-11')['days_late'] === 10
    && checkout_from_row(checkout_find('L-00009'), '2026-11-11')['status'] === 'On loan',
    'Open loans past due show as Overdue with days late');

$loan = checkout_create('m-01210', '3011100482', 'bsmith', '2026-11-11');
check($loan['id'] === 'L-00010' && $loan['member_id'] === 'M-01210' && $loan['copy_id'] === 1
    && $loan['out'] === '2026-11-11' && $loan['due'] === '2026-12-11' && $loan['checkout_by'] === 'bsmith'
    && $loan['returned'] === null, 'UC 04: checkout writes loan L-00010, due in 30 days, staff recorded');
check($copyStatus(1) === 'checked out', 'UC 04: copy marked checked out in the same transaction');

$before = $count('SELECT COUNT(*) FROM Checkout');
check($refusedOn(static fn () => checkout_create('M-00087', '30111 00482', 'bsmith', '2026-11-11'), 'barcode', 'BR 04'),
    'BR 04: a copy already out is refused');
check($refusedOn(static fn () => checkout_create('M-00566', '30111 00484', 'bsmith', '2026-11-11'), 'member_id', 'BR 05'),
    'BR 05: an inactive member is refused');
check($refusedOn(static fn () => checkout_create('M-99999', '30111 00484', 'bsmith', '2026-11-11'), 'member_id', 'M-99999'),
    'Unknown member is refused');
check($refusedOn(static fn () => checkout_create('M-00087', '30111 99999', 'bsmith', '2026-11-11'), 'barcode', 'No copy has the barcode'),
    'Unknown barcode is refused');
check($refusedOn(static fn () => checkout_create('M-00087', '30111 00486', 'bsmith', '2026-11-11'), 'barcode', 'Missing'),
    'A missing copy is refused (only available copies go out)');
check($refusedOn(static fn () => checkout_create('M-00087', '', 'bsmith', '2026-11-11'), 'barcode', 'Enter the copy barcode'),
    'Blank barcode is refused');
member_set_status('M-00312', 'active');
check($refusedOn(static fn () => checkout_create('M-00312', '30111 00484', 'bsmith', '2026-11-11'), 'member_id', 'BR 03'),
    'BR 03: unpaid fees at or above $10.00 are refused');
member_set_status('M-00312', 'inactive');
$gone = inventory_insert(['media_type' => 'music', 'title' => 'Kind of Blue', 'creator' => 'Miles Davis'], 1);
inventory_soft_delete($gone['id']);
check($refusedOn(static fn () => checkout_create('M-00087', $gone['copies'][0]['barcode'], 'bsmith', '2026-11-11'), 'barcode', 'deleted item'),
    'A copy of a deleted item is refused');
check($count('SELECT COUNT(*) FROM Checkout') === $before && $copyStatus(3) === 'available', 'Refused checkouts wrote nothing');

$pdo->exec("CREATE TEMP TRIGGER fail_checkout BEFORE UPDATE OF Copy_Status ON Copy WHEN NEW.Copy_Status = 'checked out'
    BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
check(throws(static fn () => checkout_create('M-00087', '30111 00484', 'bsmith', '2026-11-11'), PDOException::class),
    'Checkout failing on the copy update is refused');
$pdo->exec('DROP TRIGGER fail_checkout');
check($count('SELECT COUNT(*) FROM Checkout') === $before && $copyStatus(3) === 'available',
    'Transaction rollback: no loan left behind without its copy status');

check(throws(static fn () => $pdo->exec("INSERT INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Check_Out_Date, Check_Due_Date)
    VALUES ('L-09999', 1, 'M-00087', 'bsmith', '2026-11-11', '2026-12-11')"), PDOException::class),
    'Partial unique index refuses a second open loan on one copy');
check(throws(static fn () => $pdo->exec("INSERT INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Check_Out_Date, Check_Due_Date)
    VALUES ('L-09998', 3, 'M-00087', 'bsmith', '2026-11-11', '2026-11-01')"), PDOException::class),
    'CHECK constraint refuses a due date before the checkout date');
check(throws(static fn () => $pdo->exec("INSERT INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Check_Out_Date, Check_Due_Date)
    VALUES ('L-09997', 3, 'M-99999', 'bsmith', '2026-11-11', '2026-12-11')"), PDOException::class),
    'Foreign key refuses a loan for an unknown member');

$returned = checkout_return('30111 00482', 'bsmith', '2026-11-20');
check($returned['id'] === 'L-00010' && $returned['returned'] === '2026-11-20' && $returned['checkin_by'] === 'bsmith'
    && $returned['days_late'] === 0 && $returned['status'] === 'Returned', 'UC 06: return closes the open loan on time');
check($copyStatus(1) === 'available', 'UC 06: copy back to available in the same transaction');
check($refusedOn(static fn () => checkout_return('30111 00482', 'bsmith', '2026-11-20'), 'barcode', 'nothing to return'),
    'Return refused when the copy has no open loan');
check($refusedOn(static fn () => checkout_return('30111 99999', 'bsmith', '2026-11-20'), 'barcode', 'No copy has the barcode'),
    'Return refused for an unknown barcode');
$late = checkout_return('30111 00483', 'bsmith', '2026-11-11');
check($late['id'] === 'L-00005' && $late['days_late'] === 10, 'Late return records 10 days late (due 11/01, back 11/11)');

$pdo->exec("CREATE TEMP TRIGGER fail_return BEFORE UPDATE OF Copy_Status ON Copy WHEN NEW.Copy_Status = 'available'
    BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
check(throws(static fn () => checkout_return('30111 00312', 'bsmith', '2026-11-11'), PDOException::class),
    'Return failing on the copy update is refused');
$pdo->exec('DROP TRIGGER fail_return');
check(checkout_find('L-00006')['returned'] === null && $copyStatus(8) === 'checked out',
    'Transaction rollback: the loan stays open and the copy stays out');
check($openMatchesCopies(), 'Open loans and checked-out copies still agree');

checkout_create('M-00087', '30111 00550', 'bsmith', '2026-11-11');
[$ok, $msg] = inventory_soft_delete('I-05102');
check(!$ok && str_contains($msg, 'checked out'), 'BR 07: an item with a copy on loan cannot be deleted');
checkout_return('30111 00550', 'bsmith', '2026-11-11');

members_reset();
check($count('SELECT COUNT(*) FROM Checkout') === 9 && $count('SELECT COUNT(*) FROM Checkout WHERE Check_In_Date IS NULL') === 5
    && $openMatchesCopies(), 'Member reset restores the seeded loans and copy statuses');
checkout_create('M-00087', '30111 00484', 'bsmith', '2026-11-11');
inventory_reset();
check($count('SELECT COUNT(*) FROM Checkout') === 9 && $openMatchesCopies() && $copyStatus(3) === 'available',
    'Inventory reset restores the seeded loans and copy statuses');

echo PHP_EOL . "{$passed} passed, {$failed} failed" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
