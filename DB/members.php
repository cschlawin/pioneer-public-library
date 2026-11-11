<?php
/**
 * DB/members.php
 *
 * The member table, stored in SQLite (DB/library.db) through PDO prepared
 * statements. The page files call the same functions they always have
 * (members_all, member_find, member_insert, ...) and get the same row shape
 * back, so moving from the Phase 1 session table to the database changed
 * nothing in members/*.php.
 *
 * Deactivation is a status change, never a row removal (BR 06, FR 20). There
 * is deliberately no DELETE statement for members anywhere in the app.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reference.php';
require_once __DIR__ . '/checkouts.php';

/**
 * Read-only fee history for the seeded members, shown on WF 05.
 *
 * Loans come from the Checkout table (DB/checkouts.php). Fees and payments
 * are a later step, so the fees drawn in the wireframes stay a fixed fixture
 * until then. New members have none. The balance shown on the list is
 * derived from the unpaid fees here.
 */
function member_history_fixture(): array
{
    return [
        'M-01045' => [
            'fees' => [
                ['title' => 'Severance', 'returned' => '2026-10-18', 'days_late' => 4, 'amount' => 4.00, 'status' => 'Paid'],
                ['title' => 'Klara and the Sun', 'returned' => '2026-08-11', 'days_late' => 1, 'amount' => 1.00, 'status' => 'Paid'],
            ],
        ],
        'M-00778' => [
            'fees' => [
                ['title' => 'Blue Train', 'returned' => '2026-10-02', 'days_late' => 3, 'amount' => 4.50, 'status' => 'Unpaid'],
            ],
        ],
        'M-00312' => [
            'fees' => [
                ['title' => 'The Garden Party', 'returned' => '2025-12-14', 'days_late' => 12, 'amount' => 12.00, 'status' => 'Unpaid'],
            ],
        ],
        'M-00087' => [
            'fees' => [
            ],
        ],
    ];
}

/** Select list shared by every member query: database columns to page keys. */
const MEMBER_SELECT = 'SELECT Member_ID AS id, FirstName AS first_name, LastName AS last_name,
        Email AS email, Phone AS phone, Street AS street, City AS city, State AS state,
        ZIP AS zip, Member_Status AS status, Start_Date AS joined
    FROM Member';

/** Turn a database row into the array shape the pages use. */
function member_from_row(array $row): array
{
    $history = member_history_fixture()[$row['id']] ?? ['fees' => []];
    $balance = 0.0;
    foreach ($history['fees'] as $fee) {
        if (($fee['status'] ?? '') === 'Unpaid') {
            $balance += (float) $fee['amount'];
        }
    }
    $row['balance'] = $balance;
    $row['checkouts'] = checkouts_for_member((string) $row['id']);
    $row['fees'] = $history['fees'];
    return $row;
}

/** Every member row, in the order they were added. */
function members_all(): array
{
    $rows = db()->query(MEMBER_SELECT . ' ORDER BY rowid')->fetchAll();
    return array_map('member_from_row', $rows);
}

/**
 * Restore the seeded members. Backs the footer's Reset demo data action.
 * Removes every loan and member row, then re-runs the setup script, whose
 * INSERT OR IGNORE seed rows put the six seeded members and the seeded loans
 * back exactly as they were. Copy statuses are then lined up with the open
 * loans. Inventory rows are left alone because their seed rows already exist.
 */
function members_reset(): void
{
    db_transaction(static function (PDO $pdo): void {
        checkouts_clear($pdo);
        $pdo->exec('DELETE FROM Member');
        db_run_setup($pdo);
        checkouts_sync_copy_status($pdo);
    });
}

/** One member by id, or null. */
function member_find(string $id): ?array
{
    $stmt = db()->prepare(MEMBER_SELECT . ' WHERE Member_ID = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row === false ? null : member_from_row($row);
}

/** "Nguyen, Thao", the form the member tables use. */
function member_name(array $member): string
{
    return $member['last_name'] . ', ' . $member['first_name'];
}

/** "Thao Nguyen", the form the detail pages use in prose. */
function member_name_plain(array $member): string
{
    return $member['first_name'] . ' ' . $member['last_name'];
}

/** The next free member id, M-00001 numbering, never reused (DR 02). */
function member_next_id(): string
{
    $highest = 0;
    foreach (db()->query('SELECT Member_ID FROM Member')->fetchAll(PDO::FETCH_COLUMN) as $id) {
        if (preg_match('/^M-(\d+)$/', (string) $id, $m) === 1) {
            $highest = max($highest, (int) $m[1]);
        }
    }
    return 'M-' . str_pad((string) ($highest + 1), 5, '0', STR_PAD_LEFT);
}

/**
 * True when the email already belongs to another member. The comparison is
 * case-insensitive and ignores surrounding spaces (FR 18). The UNIQUE
 * constraint on Member.Email (NOCASE) backs this up in the database.
 */
function member_email_taken(string $email, ?string $except_id = null): bool
{
    $needle = mb_strtolower(trim($email));
    if ($needle === '') {
        return false;
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM Member WHERE lower(trim(Email)) = ? AND Member_ID <> ?');
    $stmt->execute([$needle, $except_id ?? '']);
    return (int) $stmt->fetchColumn() > 0;
}

/** Add a member. The caller validates first. Returns the stored row. */
function member_insert(array $data): array
{
    $id = db_transaction(static function (PDO $pdo) use ($data): string {
        $id = member_next_id();
        $stmt = $pdo->prepare('INSERT INTO Member (Member_ID, FirstName, LastName, Email, Phone,
                Street, City, State, ZIP, Start_Date, Member_Status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $id,
            $data['first_name'],
            $data['last_name'],
            trim((string) $data['email']),
            $data['phone'] ?? '',
            $data['street'],
            $data['city'],
            strtoupper((string) $data['state']),
            $data['zip'],
            // Set on save and never edited afterwards (DR 02, WF 04).
            date('Y-m-d'),
            'active',
        ]);
        return $id;
    });
    return member_find($id);
}

/** Update the editable fields of one member. Id and joined date never change. */
function member_update(string $id, array $data): ?array
{
    $stmt = db()->prepare('UPDATE Member SET FirstName = ?, LastName = ?, Email = ?, Phone = ?,
            Street = ?, City = ?, State = ?, ZIP = ?
        WHERE Member_ID = ?');
    $stmt->execute([
        $data['first_name'],
        $data['last_name'],
        trim((string) $data['email']),
        $data['phone'] ?? '',
        $data['street'],
        $data['city'],
        strtoupper((string) $data['state']),
        $data['zip'],
        $id,
    ]);
    return $stmt->rowCount() === 1 ? member_find($id) : null;
}

/**
 * Set a member's status. Called by members/delete.php, which is a status
 * change: the row and all of its history stay (BR 06).
 */
function member_set_status(string $id, string $status): ?array
{
    if (!array_key_exists($status, member_statuses())) {
        return null;
    }
    $stmt = db()->prepare('UPDATE Member SET Member_Status = ? WHERE Member_ID = ?');
    $stmt->execute([$status, $id]);
    return $stmt->rowCount() === 1 ? member_find($id) : null;
}

/**
 * Server-side search and status filter for WF 03.
 * $filters: search, status ('all' shows everything).
 */
function members_search(array $filters): array
{
    $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
    $status = (string) ($filters['status'] ?? 'all');
    $sql = MEMBER_SELECT . ' WHERE 1 = 1';
    $args = [];
    if ($status !== 'all' && $status !== '') {
        $sql .= ' AND Member_Status = ?';
        $args[] = $status;
    }
    if ($search !== '') {
        $sql .= " AND instr(lower(Member_ID || ' ' || LastName || ' ' || FirstName || ' ' || Email || ' ' || Phone), ?) > 0";
        $args[] = $search;
    }
    $stmt = db()->prepare($sql . ' ORDER BY rowid');
    $stmt->execute($args);
    return array_map('member_from_row', $stmt->fetchAll());
}
