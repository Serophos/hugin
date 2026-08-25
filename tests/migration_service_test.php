<?php

declare(strict_types=1);

use App\Core\MigrationService;

require_once __DIR__ . '/../app/Core/MigrationService.php';

function migration_assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function expect_migration_error(callable $callback, string $fragment): void
{
    try { $callback(); } catch (RuntimeException $error) {
        migration_assert(str_contains($error->getMessage(), $fragment), 'Unexpected error: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected migration error containing: ' . $fragment);
}

$pdo = new PDO('sqlite::memory:');
$service = new MigrationService($pdo, __DIR__ . '/../db-migrations');
$migrations = $service->discover();
migration_assert(count($migrations) === 23, 'Expected 23 migrations.');
migration_assert(array_keys($migrations) === range(1, 23), 'Migrations are not sequential.');
migration_assert($migrations[1]['filename'] === '001_database.display-groups-migration.sql', 'Unexpected first migration.');
migration_assert($migrations[23]['filename'] === '023_database.display-playback-reporting-migration.sql', 'Unexpected last migration.');
foreach ($migrations as $migration) {
    $sql = file_get_contents($migration['path']);
    migration_assert(is_string($sql) && $service->splitStatements($sql) !== [], 'Migration does not contain executable SQL: ' . $migration['filename']);
}

$schema = file_get_contents(__DIR__ . '/../database.sql');
migration_assert(is_string($schema), 'Could not read database.sql.');
foreach ($migrations as $migration) {
    $seed = sprintf("(%d, '%s', '%s', 'applied'", $migration['sequence'], $migration['filename'], $migration['checksum']);
    migration_assert(str_contains($schema, $seed), 'database.sql ledger seed mismatch: ' . $migration['filename']);
}

$statements = $service->splitStatements("-- comment\nINSERT INTO t VALUES ('a; b'); /* ignored; */ UPDATE t SET v = \"x; y\"; # tail\n");
migration_assert(count($statements) === 2, 'SQL statement parser split quoted semicolons.');
migration_assert(str_contains($statements[0], "'a; b'"), 'SQL statement content changed.');
expect_migration_error(static fn() => $service->splitStatements("SELECT 'unterminated"), 'unterminated');

$temporary = sys_get_temp_dir() . '/hugin-migrations-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
try {
    file_put_contents($temporary . '/001_first.sql', 'SELECT 1;');
    file_put_contents($temporary . '/003_third.sql', 'SELECT 3;');
    $invalid = new MigrationService($pdo, $temporary);
    expect_migration_error(static fn() => $invalid->discover(), 'gap');
    unlink($temporary . '/003_third.sql');
    file_put_contents($temporary . '/bad.sql', 'SELECT 2;');
    expect_migration_error(static fn() => $invalid->discover(), 'Invalid migration filename');
    unlink($temporary . '/bad.sql');
    symlink($temporary . '/001_first.sql', $temporary . '/002_link.sql');
    expect_migration_error(static fn() => $invalid->discover(), 'symlinks');
    unlink($temporary . '/002_link.sql');
    unlink($temporary . '/001_first.sql');
} finally {
    @rmdir($temporary);
}

echo "Migration service tests passed.\n";
