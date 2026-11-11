<?php
/**
 * DB/inventory.php
 *
 * The inventory: catalogue items, their media-type details, and their
 * physical copies, stored in SQLite (DB/library.db) through PDO prepared
 * statements. Item holds what every title shares; Book, Music, Film,
 * Periodical and Audiobook hold the descriptive fields, keyed on Item_ID as
 * in the ERD; Copy holds one row per physical copy.
 *
 * The page files call the same functions as before and get the same item
 * shape back (id, media_type, title, creator, ..., deleted, copies).
 *
 * Three rules shape this file:
 *   - Copy counts are never stored. They are derived from the copy records
 *     every time they are asked for (FR 22).
 *   - Deleting an item is a soft delete: Is_Deleted is set and the copies
 *     stay, so checkout history stays complete (DR 14). It is refused
 *     outright while any copy is checked out (BR 07).
 *   - A copy has exactly one status, and "checked out" is set only by the
 *     checkout flow, never by hand (BR 08).
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reference.php';
require_once __DIR__ . '/checkouts.php';

/** The five subtype tables, by media type key. */
const INVENTORY_SUBTYPES = [
    'book'       => 'Book',
    'music'      => 'Music',
    'film'       => 'Film',
    'periodical' => 'Periodical',
    'audiobook'  => 'Audiobook',
];

/** One query that flattens Item plus whichever subtype row it has. */
const INVENTORY_SELECT = "SELECT i.Item_ID AS id,
        i.Item_Type AS media_type,
        COALESCE(b.Book_Title, mu.Album_Title, f.Film_Title, p.Periodical_Name, a.Audiobook_Title, '') AS title,
        COALESCE(b.Author_Name, mu.Artist, f.Director, a.Author_Name, '') AS creator,
        COALESCE(b.Publisher, a.Publisher, '') AS publisher,
        COALESCE(b.Publish_Date, p.Publish_Date, a.Publish_Date, '') AS publish_date,
        COALESCE(b.Genre, f.Genre, a.Genre, '') AS genre,
        COALESCE(b.ISBN, '') AS isbn,
        COALESCE(p.Issue_Number, '') AS issue,
        COALESCE(CAST(f.Year_Released AS TEXT), '') AS year,
        i.Is_Deleted AS deleted
    FROM Item i
    LEFT JOIN Book b        ON b.Item_ID = i.Item_ID
    LEFT JOIN Music mu      ON mu.Item_ID = i.Item_ID
    LEFT JOIN Film f        ON f.Item_ID = i.Item_ID
    LEFT JOIN Periodical p  ON p.Item_ID = i.Item_ID
    LEFT JOIN Audiobook a   ON a.Item_ID = i.Item_ID";

/** Build one copy record in the page shape. */
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
 * Attach copies to item rows. One query for all the items asked for, in
 * Copy_ID order, so C-1, C-2, ... come back in the order they were added.
 */
function inventory_attach_copies(array $rows): array
{
    if ($rows === []) {
        return [];
    }
    $ids = array_column($rows, 'id');
    $marks = implode(', ', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status
        FROM Copy WHERE Item_ID IN ($marks) ORDER BY Copy_ID");
    $stmt->execute($ids);
    $byItem = [];
    foreach ($stmt->fetchAll() as $copy) {
        $byItem[$copy['Item_ID']][] = inv_copy(
            $copy['Copy_Label'],
            $copy['Barcode'],
            $copy['Copy_Condition'],
            $copy['Copy_Status']
        );
    }
    foreach ($rows as $index => $row) {
        $rows[$index]['deleted'] = (bool) (int) $row['deleted'];
        $rows[$index]['copies'] = $byItem[$row['id']] ?? [];
    }
    return $rows;
}

/** Every item, including soft-deleted rows. Views filter. */
function inventory_all(): array
{
    return inventory_attach_copies(db()->query(INVENTORY_SELECT . ' ORDER BY i.rowid')->fetchAll());
}

/**
 * Restore the seeded inventory. Backs the footer's Reset demo data action.
 * Removes every loan, copy, subtype and item row, then re-runs the setup
 * script, whose INSERT OR IGNORE seed rows put the seven seeded items, their
 * seventeen copies and the seeded loans back. Member rows are left alone.
 */
function inventory_reset(): void
{
    db_transaction(static function (PDO $pdo): void {
        checkouts_clear($pdo);
        $pdo->exec('DELETE FROM Copy');
        foreach (INVENTORY_SUBTYPES as $table) {
            $pdo->exec('DELETE FROM ' . $table);
        }
        $pdo->exec('DELETE FROM Item');
        db_run_setup($pdo);
        checkouts_sync_copy_status($pdo);
    });
}

/** One item by id, or null. Soft-deleted items are returned too. */
function inventory_find(string $id): ?array
{
    $stmt = db()->prepare(INVENTORY_SELECT . ' WHERE i.Item_ID = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row === false ? null : inventory_attach_copies([$row])[0];
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
    return inventory_count_out($item) > 0;
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
    foreach (db()->query('SELECT Item_ID FROM Item')->fetchAll(PDO::FETCH_COLUMN) as $id) {
        if (preg_match('/^I-(\d+)$/', (string) $id, $m) === 1) {
            $highest = max($highest, (int) $m[1]);
        }
    }
    return 'I-' . str_pad((string) ($highest + 1), 5, '0', STR_PAD_LEFT);
}

/** The next barcode in the 30111 nnnnn series. */
function inventory_next_barcode(): string
{
    $highest = 0;
    foreach (db()->query('SELECT Barcode FROM Copy')->fetchAll(PDO::FETCH_COLUMN) as $barcode) {
        if (preg_match('/^30111 (\d{5})$/', (string) $barcode, $m) === 1) {
            $highest = max($highest, (int) $m[1]);
        }
    }
    return '30111 ' . str_pad((string) ($highest + 1), 5, '0', STR_PAD_LEFT);
}

/** Empty strings become NULL for the optional subtype columns. */
function inv_nullable(mixed $value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

/** Write the one subtype row an item of this media type carries. */
function inventory_write_subtype(PDO $pdo, string $id, string $type, array $data): void
{
    $title = (string) $data['title'];
    $creator = (string) ($data['creator'] ?? '');
    switch ($type) {
        case 'book':
            $stmt = $pdo->prepare('INSERT INTO Book (Item_ID, Book_Title, Author_Name, Publisher, Publish_Date, Genre, ISBN)
                VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$id, $title, $creator, inv_nullable($data['publisher'] ?? null),
                inv_nullable($data['publish_date'] ?? null), inv_nullable($data['genre'] ?? null),
                inv_nullable($data['isbn'] ?? null)]);
            return;
        case 'music':
            $stmt = $pdo->prepare('INSERT INTO Music (Item_ID, Album_Title, Artist) VALUES (?, ?, ?)');
            $stmt->execute([$id, $title, $creator]);
            return;
        case 'film':
            $year = inv_nullable($data['year'] ?? null);
            $stmt = $pdo->prepare('INSERT INTO Film (Item_ID, Film_Title, Director, Year_Released, Genre)
                VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$id, $title, inv_nullable($creator), $year === null ? null : (int) $year,
                inv_nullable($data['genre'] ?? null)]);
            return;
        case 'periodical':
            $stmt = $pdo->prepare('INSERT INTO Periodical (Item_ID, Periodical_Name, Publish_Date, Issue_Number)
                VALUES (?, ?, ?, ?)');
            $stmt->execute([$id, $title, inv_nullable($data['publish_date'] ?? null),
                inv_nullable($data['issue'] ?? null)]);
            return;
        case 'audiobook':
            $stmt = $pdo->prepare('INSERT INTO Audiobook (Item_ID, Audiobook_Title, Author_Name, Publisher, Publish_Date, Genre)
                VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$id, $title, $creator, inv_nullable($data['publisher'] ?? null),
                inv_nullable($data['publish_date'] ?? null), inv_nullable($data['genre'] ?? null)]);
            return;
    }
    throw new InvalidArgumentException('Unknown media type: ' . $type);
}

/**
 * Insert an item and its copies in one transaction: if any row fails, none
 * is kept. Every new copy starts Available in Good condition (BR 08).
 * $data holds the descriptive fields; $copy_count is how many to create.
 */
function inventory_insert(array $data, int $copy_count): array
{
    $type = (string) $data['media_type'];
    if (!is_valid_media_type($type)) {
        throw new InvalidArgumentException('Unknown media type: ' . $type);
    }
    if ($copy_count < 1) {
        throw new InvalidArgumentException('An item needs at least one copy.');
    }
    $id = db_transaction(static function (PDO $pdo) use ($data, $type, $copy_count): string {
        $id = inventory_next_id();
        $pdo->prepare("INSERT INTO Item (Item_ID, Item_Type, Item_Condition, Is_Deleted) VALUES (?, ?, 'good', 0)")
            ->execute([$id, $type]);
        inventory_write_subtype($pdo, $id, $type, $data);

        $number = (int) substr(inventory_next_barcode(), 6);
        $copy = $pdo->prepare("INSERT INTO Copy (Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status)
            VALUES (?, ?, ?, 'good', 'available')");
        for ($i = 1; $i <= $copy_count; $i++) {
            $copy->execute([$id, 'C-' . $i, '30111 ' . str_pad((string) $number, 5, '0', STR_PAD_LEFT)]);
            $number++;
        }
        return $id;
    });
    return inventory_find($id);
}

/**
 * Update an item's descriptive fields and, optionally, its copies' condition
 * and status, all in one transaction. A copy that is checked out keeps its
 * status, and no copy can be moved to "checked out" here (BR 08).
 * Returns the updated item, or null when no item has that id.
 */
function inventory_update(string $id, array $data, ?array $copies = null): ?array
{
    $type = (string) $data['media_type'];
    if (!is_valid_media_type($type)) {
        throw new InvalidArgumentException('Unknown media type: ' . $type);
    }
    $found = db_transaction(static function (PDO $pdo) use ($id, $data, $type, $copies): bool {
        $stmt = $pdo->prepare('UPDATE Item SET Item_Type = ? WHERE Item_ID = ?');
        $stmt->execute([$type, $id]);
        if ($stmt->rowCount() !== 1) {
            return false;
        }
        // The subtype row is rewritten, so a change of media type moves the
        // item to the matching subtype table.
        foreach (INVENTORY_SUBTYPES as $table) {
            $pdo->prepare('DELETE FROM ' . $table . ' WHERE Item_ID = ?')->execute([$id]);
        }
        inventory_write_subtype($pdo, $id, $type, $data);

        if ($copies !== null) {
            $current = $pdo->prepare('SELECT Copy_Status FROM Copy WHERE Item_ID = ? AND Copy_Label = ?');
            $write = $pdo->prepare('UPDATE Copy SET Copy_Condition = ?, Copy_Status = ?
                WHERE Item_ID = ? AND Copy_Label = ?');
            foreach ($copies as $copy) {
                $current->execute([$id, $copy['id']]);
                $stored = $current->fetchColumn();
                if ($stored === false) {
                    throw new InvalidArgumentException('Item ' . $id . ' has no copy ' . $copy['id'] . '.');
                }
                $status = (string) $copy['status'];
                if ($stored === 'checked out') {
                    $status = 'checked out';
                } elseif ($status === 'checked out') {
                    throw new InvalidArgumentException(
                        'Checked out is set by the checkout flow, not by hand (BR 08).'
                    );
                }
                $write->execute([$copy['condition'], $status, $id, $copy['id']]);
            }
        }
        return true;
    });
    return $found ? inventory_find($id) : null;
}

/**
 * Soft-delete an item: flag it and keep every copy (DR 14).
 * Returns [ok, message]. Refused while any copy is checked out (BR 07).
 */
function inventory_soft_delete(string $id): array
{
    return db_transaction(static function (PDO $pdo) use ($id): array {
        $item = inventory_find($id);
        if ($item === null) {
            return [false, 'No item has that ID.'];
        }
        if ($item['deleted']) {
            return [false, 'That item is already deleted.'];
        }
        $out = inventory_count_out($item);
        if ($out > 0) {
            $plural = $out === 1 ? 'copy is' : 'copies are';
            return [false, 'This item cannot be deleted: ' . $out . ' ' . $plural
                . ' checked out. Check the ' . ($out === 1 ? 'copy' : 'copies')
                . ' in first. Delete is refused while a copy is out (BR 07).'];
        }
        $stmt = $pdo->prepare("UPDATE Item SET Is_Deleted = 1 WHERE Item_ID = ? AND Is_Deleted = 0
            AND NOT EXISTS (SELECT 1 FROM Copy WHERE Copy.Item_ID = Item.Item_ID AND Copy_Status = 'checked out')");
        $stmt->execute([$id]);
        if ($stmt->rowCount() !== 1) {
            return [false, 'That item could not be deleted. Reload the page and try again.'];
        }
        return [true, 'Deleted "' . $item['title'] . '". The record is flagged deleted and its '
            . count($item['copies']) . ' copy record(s) are kept, so history stays complete (DR 14).'];
    });
}

/**
 * Server-side search and filters for WF 06. Filters combine: text search plus
 * media type plus copy status. Soft-deleted items never appear.
 * $filters: search, type ('all'), status ('all', 'available', 'out', 'missing')
 */
function inventory_search(array $filters): array
{
    $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
    $type = (string) ($filters['type'] ?? 'all');
    $status = (string) ($filters['status'] ?? 'all');

    $sql = 'SELECT v.* FROM (' . str_replace('i.Is_Deleted AS deleted', 'i.Is_Deleted AS deleted, i.rowid AS sort_key', INVENTORY_SELECT)
        . ') v WHERE v.deleted = 0';
    $args = [];
    if ($type !== 'all' && $type !== '') {
        $sql .= ' AND v.media_type = ?';
        $args[] = $type;
    }
    if ($search !== '') {
        $sql .= " AND instr(lower(v.id || ' ' || v.title || ' ' || v.creator || ' ' || v.isbn || ' ' || v.publisher), ?) > 0";
        $args[] = $search;
    }
    $copyStatus = match ($status) {
        'available' => 'available',
        'out'       => 'checked out',
        'missing'   => 'missing',
        default     => null,
    };
    if ($copyStatus !== null) {
        $sql .= ' AND EXISTS (SELECT 1 FROM Copy c WHERE c.Item_ID = v.id AND c.Copy_Status = ?)';
        $args[] = $copyStatus;
    }
    $stmt = db()->prepare($sql . ' ORDER BY v.sort_key');
    $stmt->execute($args);
    $rows = array_map(static function (array $row): array {
        unset($row['sort_key']);
        return $row;
    }, $stmt->fetchAll());
    return inventory_attach_copies($rows);
}
