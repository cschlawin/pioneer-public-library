<?php
/**
 * DB/members.php
 *
 * The member table. There is no database in Phase 1, so the table lives in
 * the session: the seed below is copied into $_SESSION['members'] on first
 * use, and every read and write goes through the functions in this file.
 * Swapping these functions for SQL in Phase 2 changes nothing in the views.
 *
 * Deactivation is a status change, never a row removal (BR 06, FR 20).
 */
declare(strict_types=1);

require_once __DIR__ . '/reference.php';

const MEMBERS_SESSION_KEY = 'members';
const MEMBERS_SEED_VERSION = 1;

/**
 * The seed rows. The first four are the rows drawn in WF 03 and the detail
 * record in WF 05, reproduced field for field.
 */
function member_seed(): array
{
    return [
        [
            'id'         => 'M-01045',
            'first_name' => 'Thao',
            'last_name'  => 'Nguyen',
            'email'      => 't.nguyen@example.org',
            'phone'      => '801-555-0142',
            'street'     => '88 Emigration Ct',
            'city'       => 'Salt Lake City',
            'state'      => 'UT',
            'zip'        => '84108',
            'status'     => 'active',
            'joined'     => '2021-03-04',
            'balance'    => 0.00,
            'checkouts'  => [
                ['title' => 'The Overstory',  'out' => '2026-10-02', 'due' => '2026-11-01', 'status' => 'On loan'],
                ['title' => 'Severance',      'out' => '2026-09-14', 'due' => '2026-10-14', 'status' => 'Returned'],
                ['title' => 'Braiding Sweetgrass', 'out' => '2026-08-22', 'due' => '2026-09-21', 'status' => 'Returned'],
                ['title' => 'Klara and the Sun',   'out' => '2026-07-09', 'due' => '2026-08-08', 'status' => 'Returned'],
            ],
            'fees' => [
                ['title' => 'Severance',          'returned' => '2026-10-18', 'days_late' => 4, 'amount' => 4.00, 'status' => 'Paid'],
                ['title' => 'Klara and the Sun',  'returned' => '2026-08-11', 'days_late' => 1, 'amount' => 1.00, 'status' => 'Paid'],
            ],
        ],
        [
            'id'         => 'M-00778',
            'first_name' => 'Daniel',
            'last_name'  => 'Okafor',
            'email'      => 'd.okafor@example.org',
            'phone'      => '801-555-0187',
            'street'     => '412 E 300 S',
            'city'       => 'Salt Lake City',
            'state'      => 'UT',
            'zip'        => '84111',
            'status'     => 'active',
            'joined'     => '2019-08-19',
            'balance'    => 4.50,
            'checkouts'  => [
                ['title' => 'Arrival',        'out' => '2026-10-11', 'due' => '2026-11-10', 'status' => 'On loan'],
                ['title' => 'Blue Train',     'out' => '2026-08-30', 'due' => '2026-09-29', 'status' => 'Returned'],
            ],
            'fees' => [
                ['title' => 'Blue Train', 'returned' => '2026-09-29', 'days_late' => 3, 'amount' => 4.50, 'status' => 'Unpaid'],
            ],
        ],
        [
            'id'         => 'M-00312',
            'first_name' => 'Renata',
            'last_name'  => 'Silva',
            'email'      => 'r.silva@example.org',
            'phone'      => '801-555-0113',
            'street'     => '77 Harvard Ave',
            'city'       => 'Salt Lake City',
            'state'      => 'UT',
            'zip'        => '84102',
            'status'     => 'inactive',
            'joined'     => '2017-01-23',
            'balance'    => 12.00,
            'checkouts'  => [
                ['title' => 'The Garden Party', 'out' => '2025-11-02', 'due' => '2025-12-02', 'status' => 'Returned'],
            ],
            'fees' => [
                ['title' => 'The Garden Party', 'returned' => '2025-12-14', 'days_late' => 12, 'amount' => 12.00, 'status' => 'Unpaid'],
            ],
        ],
        [
            'id'         => 'M-00087',
            'first_name' => 'Amy',
            'last_name'  => 'Whitfield',
            'email'      => 'a.whitfield@example.org',
            'phone'      => '801-555-0155',
            'street'     => '1901 S Windsor St',
            'city'       => 'Salt Lake City',
            'state'      => 'UT',
            'zip'        => '84105',
            'status'     => 'active',
            'joined'     => '2015-06-08',
            'balance'    => 0.00,
            'checkouts'  => [
                ['title' => 'Gardens of the Moon', 'out' => '2026-10-20', 'due' => '2026-11-19', 'status' => 'On loan'],
            ],
            'fees' => [],
        ],
        [
            'id'         => 'M-01210',
            'first_name' => 'Ellis',
            'last_name'  => 'Brooks',
            'email'      => 'e.brooks@example.org',
            'phone'      => '801-555-0121',
            'street'     => '350 N Center St',
            'city'       => 'Lehi',
            'state'      => 'UT',
            'zip'        => '84043',
            'status'     => 'active',
            'joined'     => '2024-02-14',
            'balance'    => 0.00,
            'checkouts'  => [],
            'fees'       => [],
        ],
        [
            'id'         => 'M-00566',
            'first_name' => 'Luis',
            'last_name'  => 'Ibarra',
            'email'      => 'l.ibarra@example.org',
            'phone'      => '801-555-0198',
            'street'     => '620 W 400 N',
            'city'       => 'Salt Lake City',
            'state'      => 'UT',
            'zip'        => '84116',
            'status'     => 'inactive',
            'joined'     => '2018-11-05',
            'balance'    => 0.00,
            'checkouts'  => [],
            'fees'       => [],
        ],
    ];
}

/** Seed the session table on first use, or after a reset. */
function members_boot(): void
{
    $current = $_SESSION[MEMBERS_SESSION_KEY] ?? null;
    if (!is_array($current) || ($_SESSION['members_seed_version'] ?? 0) !== MEMBERS_SEED_VERSION) {
        $_SESSION[MEMBERS_SESSION_KEY] = member_seed();
        $_SESSION['members_seed_version'] = MEMBERS_SEED_VERSION;
    }
}

/** Every member row, in table order. */
function members_all(): array
{
    members_boot();
    return $_SESSION[MEMBERS_SESSION_KEY];
}

/** Write the whole table back. */
function members_write(array $rows): void
{
    $_SESSION[MEMBERS_SESSION_KEY] = array_values($rows);
}

/** Restore the seed rows. Backs the footer's Reset demo data action. */
function members_reset(): void
{
    $_SESSION[MEMBERS_SESSION_KEY] = member_seed();
    $_SESSION['members_seed_version'] = MEMBERS_SEED_VERSION;
}

/** One member by id, or null. */
function member_find(string $id): ?array
{
    foreach (members_all() as $row) {
        if ($row['id'] === $id) {
            return $row;
        }
    }
    return null;
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
    foreach (members_all() as $row) {
        if (preg_match('/^M-(\d+)$/', (string) $row['id'], $m) === 1) {
            $highest = max($highest, (int) $m[1]);
        }
    }
    return 'M-' . str_pad((string) ($highest + 1), 5, '0', STR_PAD_LEFT);
}

/**
 * True when the email already belongs to another member. The comparison is
 * case-insensitive and ignores surrounding spaces (FR 18).
 */
function member_email_taken(string $email, ?string $except_id = null): bool
{
    $needle = mb_strtolower(trim($email));
    if ($needle === '') {
        return false;
    }
    foreach (members_all() as $row) {
        if ($except_id !== null && $row['id'] === $except_id) {
            continue;
        }
        if (mb_strtolower(trim((string) $row['email'])) === $needle) {
            return true;
        }
    }
    return false;
}

/** Append a member. The caller validates first. Returns the stored row. */
function member_insert(array $data): array
{
    $rows = members_all();
    $row = [
        'id'         => member_next_id(),
        'first_name' => $data['first_name'],
        'last_name'  => $data['last_name'],
        'email'      => $data['email'],
        'phone'      => $data['phone'],
        'street'     => $data['street'],
        'city'       => $data['city'],
        'state'      => strtoupper($data['state']),
        'zip'        => $data['zip'],
        'status'     => 'active',
        // Set on save and never edited afterwards (DR 02, WF 04).
        'joined'     => date('Y-m-d'),
        'balance'    => 0.00,
        'checkouts'  => [],
        'fees'       => [],
    ];
    $rows[] = $row;
    members_write($rows);
    return $row;
}

/** Update the editable fields of one member. Id and joined date never change. */
function member_update(string $id, array $data): ?array
{
    $rows = members_all();
    $updated = null;
    foreach ($rows as $index => $row) {
        if ($row['id'] !== $id) {
            continue;
        }
        $rows[$index]['first_name'] = $data['first_name'];
        $rows[$index]['last_name']  = $data['last_name'];
        $rows[$index]['email']      = $data['email'];
        $rows[$index]['phone']      = $data['phone'];
        $rows[$index]['street']     = $data['street'];
        $rows[$index]['city']       = $data['city'];
        $rows[$index]['state']      = strtoupper($data['state']);
        $rows[$index]['zip']        = $data['zip'];
        $updated = $rows[$index];
        break;
    }
    if ($updated !== null) {
        members_write($rows);
    }
    return $updated;
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
    $rows = members_all();
    $updated = null;
    foreach ($rows as $index => $row) {
        if ($row['id'] !== $id) {
            continue;
        }
        $rows[$index]['status'] = $status;
        $updated = $rows[$index];
        break;
    }
    if ($updated !== null) {
        members_write($rows);
    }
    return $updated;
}

/**
 * Server-side search and status filter for WF 03.
 * $filters: search, status ('all' shows everything).
 */
function members_search(array $filters): array
{
    $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
    $status = (string) ($filters['status'] ?? 'all');
    $out = [];
    foreach (members_all() as $row) {
        if ($status !== 'all' && $status !== '' && $row['status'] !== $status) {
            continue;
        }
        if ($search !== '') {
            $haystack = mb_strtolower(
                $row['id'] . ' ' . $row['last_name'] . ' ' . $row['first_name'] . ' ' .
                $row['email'] . ' ' . $row['phone']
            );
            if (!str_contains($haystack, $search)) {
                continue;
            }
        }
        $out[] = $row;
    }
    return $out;
}
