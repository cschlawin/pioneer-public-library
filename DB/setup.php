<?php
/**
 * DB/setup.php
 *
 * Build (or rebuild) DB/library.db from DB/setup-database.sql, for machines
 * without the sqlite3 command, such as a fresh Codespace:
 *
 *     php DB/setup.php            rebuild DB/library.db from scratch
 *     php DB/setup.php --if-missing   only build it when the file is absent
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
    echo 'Database already present: ' . $path . PHP_EOL;
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
echo 'Created ' . $path . PHP_EOL;
echo "Seeded {$members} members, {$items} items, {$copies} copies." . PHP_EOL;
