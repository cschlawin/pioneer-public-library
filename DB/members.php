<?php
/**
 * DB/inventory.php
 *
 * The inventory table: catalogue items plus their physical copies. Like the
 * members table it lives in the session for Phase 1, seeded from the rows
 * drawn in WF 06 and WF 08.
 *
 * Two rules shape this file:
 *   - Copy counts are never stored. They are derived from the copy records
 *     every time they are asked for (FR 22).
 *   - Deleting an item is a soft delete: the row is flagged and its copies
 *     stay, so checkout history stays complete (DR 14). It is refused
 *     outright while any copy is checked out (BR 07).
 */
declare(strict_types=1);

require_once __DIR__ . '/reference.php';

const INVENTORY_SESSION_KEY = 'inventory';
const INVENTORY_SEED_VERSION = 1;

/** Build one copy record. */
function inv_copy(string $id, string $barcode, string $condition, string $status): array
{
    return [
        'id'        => $id,
        'barcode'   => $barcode,
        'condition' => $condition,
        'status'    => $status,
    ];
}

/**
 * The seed rows, matching WF 06 row for row and WF 08 copy for copy.
 * The Overstory carries six copies: three available, two checked out, one
 * missing, which is exactly the table in WF 08.
 */
function inventory_seed(): array
{
    return [
        [
            'id'           => 'I-04412',
            'media_type'   => 'book',
            'title'        => 'The Overstory',
            'creator'      => 'Richard Powers',
            'publisher'    => 'W. W. Norton',
            'publish_date' => '2018-04-03',
            'genre'        => 'Literary fiction',
            'isbn'         => '978-0393635522',
            'deleted'      => false,
            'copies'       => [
                inv_copy('C-1', '30111 00482', 'good', 'available'),
                inv_copy('C-2', '30111 00483', 'good', 'checked out'),
                inv_copy('C-3', '30111 00484', 'fair', 'available'),
                inv_copy('C-4', '30111 00485', 'good', 'available'),
                inv_copy('C-5', '30111 00486', 'poor', 'missing'),
                inv_copy('C-6', '30111 00487', 'good', 'checked out'),
            ],
        ],
        [
            'id'           => 'I-03881',
            'media_type'   => 'film',
            'title'        => 'Arrival',
            'creator'      => 'Denis Villeneuve',
            'publisher'    => '',
            'publish_date' => '',
            'genre'        => 'Science fiction',
            'year'         => '2016',
            'deleted'      => false,
            'copies'       => [
                inv_copy('C-1', '30111 00311', 'good', 'available'),
                inv_copy('C-2', '30111 00312', 'good', 'checked out'),
            ],
        ],
        [
            'id'           => 'I-05102',
            'media_type'   => 'music',
            'title'        => 'Blue Train',
            'creator'      => 'John Coltrane',
            'publisher'    => '',
            'publish_date' => '',
            'genre'        => '',
            'deleted'      => false,
            'copies'       => [
                inv_copy('C-1', '30111 00550', 'good', 'available'),
                inv_copy('C-2', '30111 00551', 'good', 'available'),
                inv_copy('C-3', '30111 00552', 'fair', 'available'),
            ],
        ],
        [
            'id'           => 'I-04477',
            'media_type'   => 'periodical',
            'title'        => 'Wired, Nov 2026',
            'creator'      => '',
            'publisher'    => '',
            'publish_date' => '2026-11-01',
            'genre'        => '',
            'issue'        => 'November 2026',
            'deleted'      => false,
            'copies'       => [
                inv_copy('C-1', '30111 00777', 'good', 'checked out'),
            ],
        ],
        [
            'id'           => 'I-05220',
            'media_type'   => 'audiobook',
            'title'        => 'Braiding Sweetgrass',
            'creator'      => 'Robin Wall Kimmerer',
            'publisher'    => 'Milkweed Editions',
            'publish_date' => '2013-09-01',
            'genre'        => 'Nature writing',
            'deleted'      => false,
            'copies'       => [
                inv_copy('C-1', '30111 00901', 'good', 'available'),
                inv_copy('C-2', '30111 00902', 'new', 'checked out'),
            ],
        ],
        [
            'id'           => 'I-04099',
            'media_type'   => 'book',
            'title'        => 'Klara and the Sun',
            'creator'      => 'Kazuo Ishiguro',
            'publisher'    => 'Knopf',
            'publish_date' => '2021-03-02',
            'genre'        => 'Literary fiction',
            'isbn'         => '978-0593318171',
            'deleted'      => false,
            'copies'       => [
                inv_copy('C-1', '30111 00810', 'good', 'available'),
                inv_copy('C-2', '30111 00811', 'fair', 'missing'),
            ],
        ],
        [
            'id'           => 'I-03501',
            'media_type'   => 'book',
            'title'        => 'Severance',
            'creator'      => 'Ling Ma',
            'publisher'    => 'Farrar, Straus and Giroux',
            'publish_date' => '2018-08-14',
            'genre'        => 'Literary fiction',
            'isbn'         => '978-0374261597',
            'deleted'      => false,
            'copies'       => [
                inv_copy('C-1', '30111 00601', 'good', 'available'),
            ],
        ],
    ];
}

/** Seed the session table on first use, or after a reset. */
function inventory_boot(): void
{
    $current = $_SESSION[INVENTORY_SESSION_KEY] ?? null;
    if (!is_array($current) || ($_SESSION['inventory_seed_version'] ?? 0) !== INVENTORY_SEED_VERSION) {
        $_SESSION[INVENTORY_SESSION_KEY] = inventory_seed();
        $_SESSION['inventory_seed_version'] = INVENTORY_SEED_VERSION;
    }
}

/** Every item, including soft-deleted rows. Views filter. */
function inventory_all(): array
{
    inventory_boot();
    return $_SESSION[INVENTORY_SESSION_KEY];
}

/** Write the whole table back. */
function inventory_write(array $rows): void
{
    $_SESSION[INVENTORY_SESSION_KEY] = array_values($rows);
}

/** Restore the seed rows. Backs the footer's Reset demo data action. */
function inventory_reset(): void
{
    $_SESSION[INVENTORY_SESSION_KEY] = inventory_seed();
    $_SESSION['inventory_seed_version'] = INVENTORY_SEED_VERSION;
}

/** One item by id, or null. Soft-deleted items are returned too. */
function inventory_find(string $id): ?array
{
    foreach (inventory_all() as $row) {
        if ($row['id'] === $id) {
            return $row;
        }
    }
    return null;
}

/**
 * Copy counts derived from the copy records (FR 22). Never stored.
 * Returns total, available, out, missing.
 */
function inventory_counts(array $item): array
{
    $counts = ['total' => 0, 'available' => 0, 'out' => 0, 'missing' => 0];
    foreach ($item['copies'] ?? [] as $copy) {
        $counts['total']++;
        match ($copy['status']) {
            'available'   => $counts['available']++,
            'checked out' => $counts['out']++,
            'missing'     => $counts['missing']++,
            default       => null,
        };
    }
    return $counts;
}

/** True when at least one copy is checked out, which blocks deletion (BR 07). */
function inventory_has_copy_out(array $item): bool
{
    foreach ($item['copies'] ?? [] as $copy) {
        if ($copy['status'] === 'checked out') {
            return true;
        }
    }
    return false;
}

/** How many copies are checked out. Used in the refusal message. */
function inventory_count_out(array $item): int
{
    $count = 0;
    foreach ($item['copies'] ?? [] as $copy) {
        if ($copy['status'] === 'checked out') {
            $count++;
        }
    }
    return $count;
}

/** The next free item id, I-00001 numbering. */
function inventory_next_id(): string
{
    $highest = 0;
    foreach (inventory_all() as $row) {
        if (preg_match('/^I-(\d+)$/', (string) $row['id'], $m) === 1) {
            $highest = max($highest, (int) $m[1]);
        }
    }
    return 'I-' . str_pad((string) ($highest + 1), 5, '0', STR_PAD_LEFT);
}

/** The next barcode in the 30111 nnnnn series. */
function inventory_next_barcode(): string
{
    $highest = 0;
    foreach (inventory_all() as $row) {
        foreach ($row['copies'] ?? [] as $copy) {
            if (preg_match('/^30111 (\d{5})$/', (string) $copy['barcode'], $m) === 1) {
                $highest = max($highest, (int) $m[1]);
            }
        }
    }
    return '30111 ' . str_pad((string) ($highest + 1), 5, '0', STR_PAD_LEFT);
}

/**
 * Insert an item and its copies. Every new copy starts Available (BR 08).
 * $data holds the descriptive fields; $copy_count is how many to create.
 */
function inventory_insert(array $data, int $copy_count): array
{
    $rows = inventory_all();
    $copies = [];
    $barcode = inventory_next_barcode();
    $number = (int) str_replace(' ', '', substr($barcode, 6));
    for ($i = 1; $i <= $copy_count; $i++) {
        $copies[] = inv_copy(
            'C-' . $i,
            '30111 ' . str_pad((string) $number, 5, '0', STR_PAD_LEFT),
            'good',
            'available'
        );
        $number++;
    }
    $row = [
        'id'           => inventory_next_id(),
        'media_type'   => $data['media_type'],
        'title'        => $data['title'],
        'creator'      => $data['creator'] ?? '',
        'publisher'    => $data['publisher'] ?? '',
        'publish_date' => $data['publish_date'] ?? '',
        'genre'        => $data['genre'] ?? '',
        'isbn'         => $data['isbn'] ?? '',
        'issue'        => $data['issue'] ?? '',
        'year'         => $data['year'] ?? '',
        'deleted'      => false,
        'copies'       => $copies,
    ];
    $rows[] = $row;
    inventory_write($rows);
    return $row;
}

/**
 * Update an item's descriptive fields and, optionally, its copies' condition
 * and status. The status dropdown never offers "checked out" (BR 08).
 */
function inventory_update(string $id, array $data, ?array $copies = null): ?array
{
    $rows = inventory_all();
    $updated = null;
    foreach ($rows as $index => $row) {
        if ($row['id'] !== $id) {
            continue;
        }
        $rows[$index]['title']        = $data['title'];
        $rows[$index]['media_type']   = $data['media_type'];
        $rows[$index]['creator']      = $data['creator'] ?? '';
        $rows[$index]['publisher']    = $data['publisher'] ?? '';
        $rows[$index]['publish_date'] = $data['publish_date'] ?? '';
        $rows[$index]['genre']        = $data['genre'] ?? '';
        $rows[$index]['isbn']         = $data['isbn'] ?? '';
        $rows[$index]['issue']        = $data['issue'] ?? '';
        $rows[$index]['year']         = $data['year'] ?? '';
        if ($copies !== null) {
            $rows[$index]['copies'] = $copies;
        }
        $updated = $rows[$index];
        break;
    }
    if ($updated !== null) {
        inventory_write($rows);
    }
    return $updated;
}

/**
 * Soft-delete an item: flag it and keep every copy (DR 14).
 * Returns [ok, message]. Refused while any copy is checked out (BR 07).
 */
function inventory_soft_delete(string $id): array
{
    $rows = inventory_all();
    foreach ($rows as $index => $row) {
        if ($row['id'] !== $id) {
            continue;
        }
        if ($row['deleted']) {
            return [false, 'That item is already deleted.'];
        }
        $out = inventory_count_out($row);
        if ($out > 0) {
            $plural = $out === 1 ? 'copy is' : 'copies are';
            return [false, 'This item cannot be deleted: ' . $out . ' ' . $plural
                . ' checked out. Check the ' . ($out === 1 ? 'copy' : 'copies')
                . ' in first. Delete is refused while a copy is out (BR 07).'];
        }
        $rows[$index]['deleted'] = true;
        inventory_write($rows);
        return [true, 'Deleted "' . $row['title'] . '". The record is flagged deleted and its '
            . count($row['copies']) . ' copy record(s) are kept, so history stays complete (DR 14).'];
    }
    return [false, 'No item has that ID.'];
}

/**
 * Server-side search and filters for WF 06. Filters combine: text search plus
 * media type plus copy status.
 * $filters: search, type ('all'), status ('all', 'available', 'out', 'missing')
 */
function inventory_search(array $filters): array
{
    $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
    $type = (string) ($filters['type'] ?? 'all');
    $status = (string) ($filters['status'] ?? 'all');
    $out = [];
    foreach (inventory_all() as $row) {
        if (!empty($row['deleted'])) {
            continue;
        }
        if ($type !== 'all' && $type !== '' && $row['media_type'] !== $type) {
            continue;
        }
        if ($search !== '') {
            $haystack = mb_strtolower(
                $row['id'] . ' ' . $row['title'] . ' ' . ($row['creator'] ?? '') . ' ' .
                ($row['isbn'] ?? '') . ' ' . ($row['publisher'] ?? '')
            );
            if (!str_contains($haystack, $search)) {
                continue;
            }
        }
        if ($status !== 'all' && $status !== '') {
            $counts = inventory_counts($row);
            $matches = match ($status) {
                'available' => $counts['available'] > 0,
                'out'       => $counts['out'] > 0,
                'missing'   => $counts['missing'] > 0,
                default     => true,
            };
            if (!$matches) {
                continue;
            }
        }
        $out[] = $row;
    }
    return $out;
}
