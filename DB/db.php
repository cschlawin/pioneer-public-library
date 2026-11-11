<?php
/**
 * DB/db.php
 *
 * One SQLite connection for the whole request, opened with PDO: exceptions
 * on, associative fetches, and foreign keys enforced on every connection.
 *
 * The database file is DB/library.db. The router refuses to serve anything
 * under /DB/ and any *.db or *.sql file, so the file is never downloadable.
 * Tests point the app at a throwaway copy by setting PPL_DB_PATH.
 */
declare(strict_types=1);

const DB_DEFAULT_PATH = __DIR__ . '/library.db';
const DB_SETUP_SQL = __DIR__ . '/setup-database.sql';

/** Open a PDO connection to a SQLite file (standard PDO setup). */
function openDatabase(?string $path = null): PDO
{
    if ($path === null) {
        $path = DB_DEFAULT_PATH;
    }
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

/** The path the app uses: PPL_DB_PATH when set (tests), else DB/library.db. */
function db_path(): string
{
    $override = getenv('PPL_DB_PATH');
    return is_string($override) && $override !== '' ? $override : DB_DEFAULT_PATH;
}

/**
 * The shared connection. Created on first use; if the database file does not
 * exist yet it is built and seeded from DB/setup-database.sql.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $path = db_path();
    $fresh = !is_file($path) || filesize($path) === 0;
    $pdo = openDatabase($path);
    if ($fresh || !db_has_table($pdo, 'Checkout')) {
        // New file, or a file built before the Checkout table existed. Setup
        // only creates what is missing and only inserts missing seed rows.
        db_run_setup($pdo);
    }
    return $pdo;
}

/** True when the database already has a table with this name. */
function db_has_table(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Run DB/setup-database.sql. Every CREATE is IF NOT EXISTS and every seed
 * INSERT is OR IGNORE, so running it again only puts back seed rows that are
 * missing. The reset functions rely on that.
 */
function db_run_setup(PDO $pdo): void
{
    $sql = file_get_contents(DB_SETUP_SQL);
    if ($sql === false) {
        throw new RuntimeException('Cannot read ' . DB_SETUP_SQL);
    }
    $pdo->exec($sql);
}

/** Run $work inside a transaction; roll back and rethrow on any failure. */
function db_transaction(callable $work): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $work($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
