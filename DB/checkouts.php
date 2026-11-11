<?php
/**
 * DB/checkouts.php
 *
 * Checkout and return at the staff desk (UC 04, UC 06), stored in the
 * Checkout table of DB/library.db through PDO prepared statements.
 *
 * Rules this file enforces, in the data layer and not only on the page:
 *   - The due date is LOAN_PERIOD_DAYS (30) calendar days after the checkout
 *     date (BR 01, FR 07).
 *   - Only an active member may borrow (BR 05), and not while the unpaid fee
 *     balance is at or above FEE_BLOCK_LIMIT (BR 03, FR 08).
 *   - Only an available copy of an item that is not deleted can go out. A
 *     copy already on loan cannot go out again until it is returned (BR 04);
 *     the partial unique index on Checkout (Copy_ID) backs that up.
 *   - Writing the loan and changing the copy status happen in one
 *     transaction, so a loan never exists without its copy marked checked
 *     out, and a return never leaves the copy marked out (BR 08).
 */
declare(strict_types=1);

require_once __DIR__ . '/../security/sanitize.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/members.php';
require_once __DIR__ . '/inventory.php';

/** Loan period in calendar days (BR 01). */
const LOAN_PERIOD_DAYS = 30;

/** Unpaid balance at which borrowing is refused (BR 03). */
const FEE_BLOCK_LIMIT = 10.00;

/**
 * A checkout or return the rules refuse. $field names the form field the
 * message belongs to ('member_id' or 'barcode') so the page can show it
 * next to that field.
 */
final class CheckoutRefused extends RuntimeException
{
    public function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }
}

/** Loan rows joined to the copy, item title and member name. */
const CHECKOUT_SELECT = "SELECT c.Check_Num AS id, c.Copy_ID AS copy_id, c.Member_ID AS member_id,
        m.FirstName AS first_name, m.LastName AS last_name,
        cp.Item_ID AS item_id, cp.Copy_Label AS copy_label, cp.Barcode AS barcode,
        COALESCE(b.Book_Title, mu.Album_Title, f.Film_Title, p.Periodical_Name, a.Audiobook_Title, '') AS title,
        c.Check_Out_Date AS out, c.Check_Due_Date AS due, c.Check_In_Date AS returned,
        c.Checkout_Emp_ID AS checkout_by, c.Checkin_Emp_ID AS checkin_by
    FROM Checkout c
    JOIN Member m          ON m.Member_ID = c.Member_ID
    JOIN Copy cp           ON cp.Copy_ID = c.Copy_ID
    LEFT JOIN Book b       ON b.Item_ID = cp.Item_ID
    LEFT JOIN Music mu     ON mu.Item_ID = cp.Item_ID
    LEFT JOIN Film f       ON f.Item_ID = cp.Item_ID
    LEFT JOIN Periodical p ON p.Item_ID = cp.Item_ID
    LEFT JOIN Audiobook a  ON a.Item_ID = cp.Item_ID";

/** Today's date as YYYY-MM-DD, the format every date column uses. */
function checkout_today(): string
{
    return date('Y-m-d');
}

/** The due date for a checkout made on $out (BR 01). */
function checkout_due_date(string $out): string
{
    return (new DateTimeImmutable($out))->modify('+' . LOAN_PERIOD_DAYS . ' days')->format('Y-m-d');
}

/** Whole days from $from to $to; negative when $to is earlier. */
function checkout_days_between(string $from, string $to): int
{
    $diff = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to));
    return $diff->invert === 1 ? -(int) $diff->days : (int) $diff->days;
}

/** Barcodes are printed with a space ("30111 00482"); compare without spaces. */
function barcode_normalize(string $barcode): string
{
    return preg_replace('/\s+/', '', $barcode) ?? '';
}

/**
 * Add the derived fields the pages show: status ("On loan", "Overdue" or
 * "Returned"), days late, and the member's display name.
 */
function checkout_from_row(array $row, ?string $today = null): array
{
    $today ??= checkout_today();
    $returned = $row['returned'];
    if ($returned !== null) {
        $late = max(0, checkout_days_between((string) $row['due'], (string) $returned));
        $row['status'] = 'Returned';
    } else {
        $late = max(0, checkout_days_between((string) $row['due'], $today));
        $row['status'] = $late > 0 ? 'Overdue' : 'On loan';
    }
    $row['days_late'] = $late;
    $row['copy_id'] = (int) $row['copy_id'];
    $row['member_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
    return $row;
}

/** One loan by its checkout number, or null. */
function checkout_find(string $id): ?array
{
    $stmt = db()->prepare(CHECKOUT_SELECT . ' WHERE c.Check_Num = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row === false ? null : checkout_from_row($row);
}

/**
 * A member's loans for WF 05: open loans first (soonest due first), then
 * returned loans, newest first.
 */
function checkouts_for_member(string $memberId): array
{
    $stmt = db()->prepare(CHECKOUT_SELECT . ' WHERE c.Member_ID = ?
        ORDER BY (c.Check_In_Date IS NOT NULL), 
                 CASE WHEN c.Check_In_Date IS NULL THEN c.Check_Due_Date END,
                 c.Check_Out_Date DESC, c.Check_Num DESC');
    $stmt->execute([$memberId]);
    return array_map('checkout_from_row', $stmt->fetchAll());
}

/** Every open loan, soonest due first (the return desk list). */
function checkouts_open(): array
{
    $rows = db()->query(CHECKOUT_SELECT . ' WHERE c.Check_In_Date IS NULL
        ORDER BY c.Check_Due_Date, c.Check_Num')->fetchAll();
    return array_map('checkout_from_row', $rows);
}

/** The most recent checkouts, newest first (the checkout desk list). */
function checkouts_recent(int $limit = 10): array
{
    $stmt = db()->prepare(CHECKOUT_SELECT . ' ORDER BY c.Check_Out_Date DESC, c.Check_Num DESC LIMIT ?');
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return array_map('checkout_from_row', $stmt->fetchAll());
}

/** The most recent returns, newest first (the return desk list). */
function checkouts_recent_returns(int $limit = 10): array
{
    $stmt = db()->prepare(CHECKOUT_SELECT . ' WHERE c.Check_In_Date IS NOT NULL
        ORDER BY c.Check_In_Date DESC, c.Check_Num DESC LIMIT ?');
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return array_map('checkout_from_row', $stmt->fetchAll());
}

/** Next checkout number: one past the highest L-#####. */
function checkout_next_id(?PDO $pdo = null): string
{
    $pdo ??= db();
    $max = (int) $pdo->query("SELECT COALESCE(MAX(CAST(substr(Check_Num, 3) AS INTEGER)), 0) FROM Checkout")
        ->fetchColumn();
    return sprintf('L-%05d', $max + 1);
}

/**
 * The copy a barcode belongs to, with its item title and deleted flag, or
 * null when no copy has that barcode. Spaces in the barcode are ignored.
 */
function checkout_copy_by_barcode(string $barcode, ?PDO $pdo = null): ?array
{
    $pdo ??= db();
    $stmt = $pdo->prepare("SELECT cp.Copy_ID AS copy_id, cp.Item_ID AS item_id, cp.Copy_Label AS copy_label,
            cp.Barcode AS barcode, cp.Copy_Status AS status, i.Is_Deleted AS deleted,
            COALESCE(b.Book_Title, mu.Album_Title, f.Film_Title, p.Periodical_Name, a.Audiobook_Title, '') AS title
        FROM Copy cp
        JOIN Item i            ON i.Item_ID = cp.Item_ID
        LEFT JOIN Book b       ON b.Item_ID = cp.Item_ID
        LEFT JOIN Music mu     ON mu.Item_ID = cp.Item_ID
        LEFT JOIN Film f       ON f.Item_ID = cp.Item_ID
        LEFT JOIN Periodical p ON p.Item_ID = cp.Item_ID
        LEFT JOIN Audiobook a  ON a.Item_ID = cp.Item_ID
        WHERE replace(cp.Barcode, ' ', '') = ?");
    $stmt->execute([barcode_normalize($barcode)]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }
    $row['copy_id'] = (int) $row['copy_id'];
    $row['deleted'] = (bool) (int) $row['deleted'];
    return $row;
}

/** The open loan on a copy, or null. */
function checkout_open_for_copy(int $copyId, ?PDO $pdo = null): ?array
{
    $pdo ??= db();
    $stmt = $pdo->prepare('SELECT Check_Num AS id, Member_ID AS member_id, Check_Due_Date AS due
        FROM Checkout WHERE Copy_ID = ? AND Check_In_Date IS NULL');
    $stmt->execute([$copyId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Check a copy out to a member (UC 04 at the staff desk). Writes the loan and
 * marks the copy checked out in one transaction, and returns the new loan.
 * Throws CheckoutRefused, with nothing written, when a rule says no.
 */
function checkout_create(string $memberId, string $barcode, string $staff, ?string $today = null): array
{
    $today ??= checkout_today();
    $memberId = strtoupper(trim($memberId));

    $id = db_transaction(static function (PDO $pdo) use ($memberId, $barcode, $staff, $today): string {
        $member = $memberId === '' ? null : member_find($memberId);
        if ($member === null) {
            throw new CheckoutRefused('Choose a member. No member has the ID ' . ($memberId === '' ? '(blank)' : $memberId) . '.', 'member_id');
        }
        $name = member_name_plain($member);
        if ($member['status'] !== 'active') {
            throw new CheckoutRefused($name . ' is inactive, so the account cannot borrow (BR 05). Reactivate the member first.', 'member_id');
        }
        if ((float) $member['balance'] >= FEE_BLOCK_LIMIT) {
            throw new CheckoutRefused($name . ' owes ' . money($member['balance']) . ' in unpaid fees. Borrowing is refused at '
                . money(FEE_BLOCK_LIMIT) . ' or more (BR 03).', 'member_id');
        }

        if (barcode_normalize($barcode) === '') {
            throw new CheckoutRefused('Enter the copy barcode.', 'barcode');
        }
        $copy = checkout_copy_by_barcode($barcode, $pdo);
        if ($copy === null) {
            throw new CheckoutRefused('No copy has the barcode ' . trim($barcode) . '. Check the number and scan again.', 'barcode');
        }
        $label = $copy['title'] . ' (' . $copy['copy_label'] . ', ' . $copy['barcode'] . ')';
        if ($copy['deleted']) {
            throw new CheckoutRefused($label . ' belongs to a deleted item and cannot be checked out.', 'barcode');
        }
        if ($copy['status'] === 'checked out') {
            $open = checkout_open_for_copy($copy['copy_id'], $pdo);
            throw new CheckoutRefused($label . ' is already checked out'
                . ($open !== null ? ', due ' . date_long((string) $open['due']) : '')
                . '. Return it before it can go out again (BR 04).', 'barcode');
        }
        if ($copy['status'] !== 'available') {
            throw new CheckoutRefused($label . ' is marked ' . copy_status_label((string) $copy['status'])
                . ' and cannot be checked out. Only an available copy can go out.', 'barcode');
        }

        $id = checkout_next_id($pdo);
        $pdo->prepare('INSERT INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Check_Out_Date, Check_Due_Date)
            VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$id, $copy['copy_id'], $member['id'], $staff, $today, checkout_due_date($today)]);
        $stmt = $pdo->prepare("UPDATE Copy SET Copy_Status = 'checked out' WHERE Copy_ID = ? AND Copy_Status = 'available'");
        $stmt->execute([$copy['copy_id']]);
        if ($stmt->rowCount() !== 1) {
            throw new CheckoutRefused($label . ' changed while you were working. Scan it again.', 'barcode');
        }
        return $id;
    });

    return checkout_find($id) ?? throw new RuntimeException('Checkout ' . $id . ' was not saved.');
}

/**
 * Record the return of a copy (UC 06 at the staff desk). Closes the open loan
 * with today's date and marks the copy available in one transaction, and
 * returns the closed loan (with days_late). Throws CheckoutRefused, with
 * nothing written, when the barcode is unknown or the copy is not on loan.
 */
function checkout_return(string $barcode, string $staff, ?string $today = null): array
{
    $today ??= checkout_today();

    $id = db_transaction(static function (PDO $pdo) use ($barcode, $staff, $today): string {
        if (barcode_normalize($barcode) === '') {
            throw new CheckoutRefused('Enter the copy barcode.', 'barcode');
        }
        $copy = checkout_copy_by_barcode($barcode, $pdo);
        if ($copy === null) {
            throw new CheckoutRefused('No copy has the barcode ' . trim($barcode) . '. Check the number and scan again.', 'barcode');
        }
        $label = $copy['title'] . ' (' . $copy['copy_label'] . ', ' . $copy['barcode'] . ')';
        $open = checkout_open_for_copy($copy['copy_id'], $pdo);
        if ($open === null) {
            throw new CheckoutRefused($label . ' is not checked out, so there is nothing to return.', 'barcode');
        }

        $stmt = $pdo->prepare('UPDATE Checkout SET Check_In_Date = ?, Checkin_Emp_ID = ?
            WHERE Check_Num = ? AND Check_In_Date IS NULL');
        $stmt->execute([$today, $staff, $open['id']]);
        if ($stmt->rowCount() !== 1) {
            throw new CheckoutRefused($label . ' changed while you were working. Scan it again.', 'barcode');
        }
        $pdo->prepare("UPDATE Copy SET Copy_Status = 'available' WHERE Copy_ID = ?")
            ->execute([$copy['copy_id']]);
        return (string) $open['id'];
    });

    return checkout_find($id) ?? throw new RuntimeException('Checkout ' . $id . ' was not found after the return.');
}

/**
 * Used by the two reset functions: remove every loan, and after the seed
 * rows are back, make every copy's status agree with the open loans, so a
 * copy is checked out exactly when it has an open loan.
 */
function checkouts_clear(PDO $pdo): void
{
    $pdo->exec('DELETE FROM Checkout');
}

function checkouts_sync_copy_status(PDO $pdo): void
{
    $pdo->exec("UPDATE Copy SET Copy_Status = 'available'
        WHERE Copy_Status = 'checked out'
          AND Copy_ID NOT IN (SELECT Copy_ID FROM Checkout WHERE Check_In_Date IS NULL)");
    $pdo->exec("UPDATE Copy SET Copy_Status = 'checked out'
        WHERE Copy_ID IN (SELECT Copy_ID FROM Checkout WHERE Check_In_Date IS NULL)");
}

/** Badge class for a loan's status on the circulation and member pages. */
function loan_badge_class(array $loan): string
{
    return match ($loan['status'] ?? '') {
        'Overdue'  => 'badge-loan-overdue',
        'On loan'  => 'badge-loan-open',
        default    => 'badge-loan-returned',
    };
}
