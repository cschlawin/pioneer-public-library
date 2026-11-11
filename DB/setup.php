<?php
/**
 * DB/setup.php
 *
 * Build (or rebuild) DB/library.db from DB/setup-database.sql, for machines
 * without the sqlite3 command, such as a fresh Codespace:
 *
 *     php DB/setup.php            rebuild DB/library.db from scratch
 *     php DB/setup.php --if-missing   only build it when the file is absent;
 *                                     an existing file only gets any missing
 *                                     tables (for example Checkout) added
 *
 * CLI only; the router also refuses every request under /DB/.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/db.php';

$path = db_path();
$ifMissing = in_array('--if-missing', $argv, true);

if ($ifMissing && is_file($path) && filesize($path) > 0) {
    $pdo = openDatabase($path);
    if (!db_has_table($pdo, 'Checkout')) {
        // An older database from before checkouts existed: add the table and
        // its seed loans without touching the member or inventory rows.
        db_run_setup($pdo);
        echo 'Added the Checkout table to ' . $path . PHP_EOL;
    } else {
        echo 'Database already present: ' . $path . PHP_EOL;
    }
    exit(0);
}

if (is_file($path) && !unlink($path)) {
    fwrite(STDERR, 'Could not remove the old database at ' . $path . PHP_EOL);
    exit(1);
}

$pdo = openDatabase($path);
db_run_setup($pdo);

$members = (int) $pdo->query('SELECT COUNT(*) FROM Member')->fetchColumn();
$items = (int) $pdo->query('SELECT COUNT(*) FROM Item')->fetchColumn();
$copies = (int) $pdo->query('SELECT COUNT(*) FROM Copy')->fetchColumn();
$loans = (int) $pdo->query('SELECT COUNT(*) FROM Checkout')->fetchColumn();
$open = (int) $pdo->query('SELECT COUNT(*) FROM Checkout WHERE Check_In_Date IS NULL')->fetchColumn();
echo 'Created ' . $path . PHP_EOL;
echo "Seeded {$members} members, {$items} items, {$copies} copies, {$loans} checkouts ({$open} open)." . PHP_EOL;
