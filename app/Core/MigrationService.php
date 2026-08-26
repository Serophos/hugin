<?php

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationService
{
    private const TABLE = 'schema_migrations';
    private const LOCK = 'hugin:schema-migrations';

    public function __construct(private PDO $pdo, private string $directory)
    {
    }

    /** @return array<int,array{sequence:int,filename:string,path:string,checksum:string}> */
    public function discover(): array
    {
        $root = realpath($this->directory);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Migration directory is missing.');
        }

        $entries = scandir($root);
        if ($entries === false) {
            throw new RuntimeException('Migration directory cannot be read.');
        }

        $migrations = [];
        foreach ($entries as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }
            $path = $root . DIRECTORY_SEPARATOR . $filename;
            if (is_link($path)) {
                throw new RuntimeException('Migration symlinks are not allowed: ' . $filename);
            }
            if (!is_file($path)) {
                continue;
            }
            if (!preg_match('/^(\d{3})_[A-Za-z0-9][A-Za-z0-9._-]*\.sql$/D', $filename, $match)) {
                throw new RuntimeException('Invalid migration filename: ' . $filename);
            }
            $resolved = realpath($path);
            if ($resolved === false || dirname($resolved) !== $root) {
                throw new RuntimeException('Migration path escapes the migration directory.');
            }
            $sequence = (int)$match[1];
            if ($sequence < 1 || isset($migrations[$sequence])) {
                throw new RuntimeException('Duplicate or invalid migration sequence: ' . $match[1]);
            }
            $checksum = hash_file('sha256', $resolved);
            if ($checksum === false) {
                throw new RuntimeException('Cannot checksum migration: ' . $filename);
            }
            $migrations[$sequence] = compact('sequence', 'filename', 'resolved', 'checksum');
            $migrations[$sequence]['path'] = $migrations[$sequence]['resolved'];
            unset($migrations[$sequence]['resolved']);
        }
        ksort($migrations, SORT_NUMERIC);
        $expected = 1;
        foreach ($migrations as $sequence => $_migration) {
            if ($sequence !== $expected) {
                throw new RuntimeException(sprintf('Migration sequence has a gap: expected %03d.', $expected));
            }
            $expected++;
        }
        if ($migrations === []) {
            throw new RuntimeException('No migrations were found.');
        }
        return $migrations;
    }

    /** @return array{healthy:bool,kind:string,migrations:array,rows:array,problems:array} */
    public function status(): array
    {
        try {
            $migrations = $this->discover();
        } catch (Throwable $error) {
            return ['healthy' => false, 'kind' => 'invalid', 'migrations' => [], 'rows' => [], 'problems' => [$error->getMessage()]];
        }
        if (!$this->tableExists(self::TABLE)) {
            $kind = $this->hasApplicationTables() ? 'legacy' : 'uninitialized';
            return ['healthy' => false, 'kind' => $kind, 'migrations' => $migrations, 'rows' => [], 'problems' => ['Migration ledger is missing.']];
        }

        $rows = [];
        foreach ($this->pdo->query('SELECT sequence, filename, checksum, state, error_summary FROM schema_migrations ORDER BY sequence')->fetchAll() as $row) {
            $rows[(int)$row['sequence']] = $row;
        }
        $problems = [];
        foreach ($rows as $sequence => $row) {
            if (!isset($migrations[$sequence])) {
                $problems[] = sprintf('Ledger contains unknown migration %03d.', $sequence);
                continue;
            }
            if (!hash_equals($migrations[$sequence]['filename'], (string)$row['filename'])) {
                $problems[] = sprintf('Filename mismatch for migration %03d.', $sequence);
            }
            if (!hash_equals($migrations[$sequence]['checksum'], (string)$row['checksum'])) {
                $problems[] = sprintf('Checksum mismatch for migration %03d.', $sequence);
            }
            if ((string)$row['state'] !== 'applied') {
                $problems[] = sprintf('Migration %03d is %s.', $sequence, (string)$row['state']);
            }
        }
        foreach ($migrations as $sequence => &$migration) {
            $migration['state'] = isset($rows[$sequence]) ? (string)$rows[$sequence]['state'] : 'pending';
        }
        unset($migration);
        $pending = array_filter($migrations, static fn(array $migration): bool => $migration['state'] === 'pending');
        if ($pending !== []) {
            $problems[] = count($pending) . ' migration(s) pending.';
        }
        return ['healthy' => $problems === [], 'kind' => $problems === [] ? 'current' : 'blocked', 'migrations' => $migrations, 'rows' => $rows, 'problems' => $problems];
    }

    public function createLedger(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            sequence SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            checksum CHAR(64) NOT NULL,
            state ENUM('running', 'applied', 'failed') NOT NULL,
            started_at DATETIME NULL,
            applied_at DATETIME NULL,
            execution_ms INT UNSIGNED NULL,
            error_summary VARCHAR(500) NULL,
            UNIQUE KEY uniq_schema_migrations_filename (filename)
        )");
    }

    /** @return list<string> */
    public function validateCurrentSchema(): array
    {
        $requiredTables = [
            'display_locations', 'display_groups', 'display_group_memberships', 'plugin_global_settings',
            'schedules', 'schedule_rules', 'channel_display_schedule_assignments', 'app_settings',
            'slide_templates', 'slide_template_data', 'display_cache_readiness', 'display_sync_releases',
        ];
        $requiredColumns = [
            'displays' => ['icon_file', 'display_language', 'timezone'],
            'slides' => ['background_media_asset_id', 'text_markup', 'background_color', 'text_color', 'text_box_background_color', 'text_box_layout', 'text_box_animation', 'text_box_animation_duration_ms', 'text_box_animation_delay_ms', 'text_box_blur_enabled', 'text_box_width_percent', 'qr_foreground_color', 'qr_background_color', 'qr_position', 'qr_size_percent', 'text_box_radius_top_left_rem', 'text_box_radius_top_right_rem', 'text_box_radius_bottom_right_rem', 'text_box_radius_bottom_left_rem', 'qr_animation_enabled', 'qr_radius_top_left_rem', 'qr_radius_top_right_rem', 'qr_radius_bottom_right_rem', 'qr_radius_bottom_left_rem'],
            'users' => ['password_changed_at', 'first_name', 'last_name', 'auth_provider', 'oidc_issuer', 'oidc_subject', 'department', 'title', 'picture_url'],
            'media_assets' => ['preview_file_path', 'font_family_name', 'font_full_name', 'font_subfamily', 'font_weight', 'font_postscript_name', 'font_version', 'font_format', 'license_note'],
            'display_groups' => ['primary_display_id'],
            'display_heartbeats' => ['reported_state_signature', 'reported_playback_status', 'playback_reported_at', 'pending_state_signature', 'pending_activation_at_ms'],
        ];
        $errors = [];
        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                $errors[] = 'Missing table: ' . $table;
            }
        }
        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                if (!$this->columnExists($table, $column)) {
                    $errors[] = 'Missing column: ' . $table . '.' . $column;
                }
            }
        }
        foreach (['vnc_username', 'vnc_password'] as $removed) {
            if ($this->columnExists('displays', $removed)) {
                $errors[] = 'Removed column is still present: displays.' . $removed;
            }
        }
        if (!$this->indexExists('display_groups', 'idx_display_groups_primary_display')) {
            $errors[] = 'Missing index: display_groups.idx_display_groups_primary_display';
        }
        if (!$this->constraintExists('display_groups', 'fk_display_group_primary_display')) {
            $errors[] = 'Missing constraint: display_groups.fk_display_group_primary_display';
        }
        if (!$this->indexExists('users', 'uniq_users_oidc_identity')) {
            $errors[] = 'Missing index: users.uniq_users_oidc_identity';
        }
        $password = $this->columnMetadata('users', 'password_hash');
        if ($password !== null && $password['IS_NULLABLE'] !== 'YES') {
            $errors[] = 'users.password_hash must be nullable.';
        }
        $mediaKind = $this->columnMetadata('media_assets', 'media_kind');
        if ($mediaKind !== null && !str_contains((string)$mediaKind['COLUMN_TYPE'], "'font'")) {
            $errors[] = 'media_assets.media_kind does not allow fonts.';
        }
        $timezone = $this->columnMetadata('displays', 'timezone');
        if ($timezone !== null && (string)$timezone['COLUMN_DEFAULT'] !== 'Europe/Berlin') {
            $errors[] = 'displays.timezone has an unexpected default.';
        }
        foreach (['display_channel_schedules', 'display_channel_assignments'] as $removedTable) {
            if ($this->tableExists($removedTable)) {
                $errors[] = 'Removed table is still present: ' . $removedTable;
            }
        }
        foreach (['slide_plugin_data', 'plugin_global_settings'] as $pluginTable) {
            if ($this->tableExists($pluginTable)) {
                $stale = $this->pdo->query("SELECT COUNT(*) FROM `{$pluginTable}` WHERE plugin_name = 'tl1menu'")->fetchColumn();
                if ((int)$stale > 0) { $errors[] = 'Legacy tl1menu identifiers remain in ' . $pluginTable . '.'; }
            }
        }
        return $errors;
    }

    public function adopt(): void
    {
        $this->acquireLock();
        try {
            if ($this->tableExists(self::TABLE)) {
                throw new RuntimeException('Migration ledger already exists; adoption is not allowed.');
            }
            $states = [];
            $partial = [];
            foreach ($this->discover() as $migration) {
                $state = $this->migrationEffectState($migration['sequence']);
                $states[$migration['sequence']] = $state;
                if ($state === 'partial') {
                    $partial[] = sprintf('%03d %s', $migration['sequence'], $migration['filename']);
                }
            }
            if ($partial !== []) {
                throw new RuntimeException("Legacy schema has partial migration effects; resolve these before adoption:\n - " . implode("\n - ", $partial));
            }
            $this->createLedger();
            $this->pdo->beginTransaction();
            try {
                $statement = $this->pdo->prepare("INSERT INTO schema_migrations
                    (sequence, filename, checksum, state, started_at, applied_at, execution_ms)
                    VALUES (?, ?, ?, 'applied', NOW(), NOW(), 0)");
                foreach ($this->discover() as $migration) {
                    if ($states[$migration['sequence']] === 'applied') {
                        $statement->execute([$migration['sequence'], $migration['filename'], $migration['checksum']]);
                    }
                }
                $this->pdo->commit();
            } catch (Throwable $error) {
                if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
                throw $error;
            }
        } finally {
            $this->releaseLock();
        }
    }

    private function migrationEffectState(int $sequence): string
    {
        $tables = fn(array $names): array => array_map(fn(string $name): bool => $this->tableExists($name), $names);
        $columns = fn(string $table, array $names): array => array_map(fn(string $name): bool => $this->columnExists($table, $name), $names);
        $classify = static function (array $effects): string {
            if ($effects !== [] && !in_array(false, $effects, true)) { return 'applied'; }
            if (!in_array(true, $effects, true)) { return 'pending'; }
            return 'partial';
        };

        return match ($sequence) {
            1 => $classify($tables(['display_locations', 'display_groups', 'display_group_memberships'])),
            2 => $classify($columns('displays', ['icon_file'])),
            3 => $classify($tables(['plugin_global_settings'])),
            4 => $this->scheduleMigrationState(),
            5 => $classify($tables(['app_settings'])),
            6 => $classify(array_merge(
                $columns('slides', ['background_media_asset_id', 'text_markup', 'background_color']),
                [$this->constraintExists('slides', 'fk_slides_background_media')]
            )),
            7 => $classify($columns('slides', ['text_color', 'text_box_background_color', 'text_box_layout', 'text_box_animation', 'qr_foreground_color', 'qr_background_color'])),
            8 => $classify($columns('slides', ['text_box_animation_duration_ms', 'text_box_animation_delay_ms', 'text_box_blur_enabled', 'text_box_width_percent', 'qr_position'])),
            9 => $classify($columns('slides', ['qr_size_percent'])),
            10 => $classify($columns('slides', ['text_box_radius_top_left_rem', 'text_box_radius_top_right_rem', 'text_box_radius_bottom_right_rem', 'text_box_radius_bottom_left_rem', 'qr_animation_enabled', 'qr_radius_top_left_rem', 'qr_radius_top_right_rem', 'qr_radius_bottom_right_rem', 'qr_radius_bottom_left_rem'])),
            11 => $this->tl1RenameEffectPresent() ? 'applied' : 'pending',
            12 => $classify($tables(['slide_templates', 'slide_template_data'])),
            13 => $classify($columns('users', ['password_changed_at'])),
            14 => $classify($columns('media_assets', ['preview_file_path'])),
            15 => $this->mediaFontsMigrationState($classify),
            16 => $classify($tables(['display_cache_readiness', 'display_sync_releases'])),
            17 => $classify($columns('displays', ['display_language'])),
            18 => $classify([
                $this->columnExists('display_groups', 'primary_display_id'),
                $this->indexExists('display_groups', 'idx_display_groups_primary_display'),
                $this->constraintExists('display_groups', 'fk_display_group_primary_display'),
            ]),
            19 => $this->openidAuthenticationMigrationState($classify),
            20 => $classify($columns('users', ['department', 'title', 'picture_url'])),
            21 => $this->removedVncMigrationState(),
            22 => ((string)($this->columnMetadata('displays', 'timezone')['COLUMN_DEFAULT'] ?? '') === 'Europe/Berlin') ? 'applied' : 'pending',
            23 => $classify($columns('display_heartbeats', [
                'reported_state_signature',
                'reported_playback_status',
                'playback_reported_at',
                'pending_state_signature',
                'pending_activation_at_ms',
            ])),
            default => throw new RuntimeException(sprintf('No legacy adoption signature exists for migration %03d.', $sequence)),
        };
    }

    private function scheduleMigrationState(): string
    {
        $new = array_map(fn(string $table): bool => $this->tableExists($table), ['schedules', 'schedule_rules', 'channel_display_schedule_assignments']);
        $old = array_map(fn(string $table): bool => $this->tableExists($table), ['display_channel_schedules', 'display_channel_assignments']);
        if (!in_array(false, $new, true) && !in_array(true, $old, true)) { return 'applied'; }
        if (!in_array(true, $new, true) && !in_array(false, $old, true)) { return 'pending'; }
        return 'partial';
    }

    /** @param callable(array):string $classify */
    private function mediaFontsMigrationState(callable $classify): string
    {
        $effects = array_map(fn(string $column): bool => $this->columnExists('media_assets', $column), [
            'font_family_name', 'font_full_name', 'font_subfamily', 'font_weight', 'font_postscript_name', 'font_version', 'font_format', 'license_note',
        ]);
        $metadata = $this->columnMetadata('media_assets', 'media_kind');
        $effects[] = $metadata !== null && str_contains((string)$metadata['COLUMN_TYPE'], "'font'");
        return $classify($effects);
    }

    /** @param callable(array):string $classify */
    private function openidAuthenticationMigrationState(callable $classify): string
    {
        $effects = array_map(fn(string $column): bool => $this->columnExists('users', $column), ['first_name', 'last_name', 'auth_provider', 'oidc_issuer', 'oidc_subject']);
        $password = $this->columnMetadata('users', 'password_hash');
        $effects[] = $password !== null && $password['IS_NULLABLE'] === 'YES';
        $effects[] = $this->indexExists('users', 'uniq_users_oidc_identity');
        return $classify($effects);
    }

    private function removedVncMigrationState(): string
    {
        $username = $this->columnExists('displays', 'vnc_username');
        $password = $this->columnExists('displays', 'vnc_password');
        if (!$username && !$password) { return 'applied'; }
        if ($username && $password) { return 'pending'; }
        return 'partial';
    }

    private function tl1RenameEffectPresent(): bool
    {
        foreach (['slide_plugin_data', 'plugin_global_settings'] as $table) {
            if (!$this->tableExists($table)) { continue; }
            $stale = $this->pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE plugin_name = 'tl1menu'")->fetchColumn();
            if ((int)$stale > 0) { return false; }
        }
        return true;
    }

    /** @param callable(string):void $output */
    public function migrate(bool $backupAcknowledged, ?int $retry, callable $output): void
    {
        if (!$backupAcknowledged) {
            throw new RuntimeException('Refusing to migrate without --backup-confirmed.');
        }
        $this->acquireLock();
        try {
            if (!$this->tableExists(self::TABLE)) {
                throw new RuntimeException('Migration ledger is missing. Import database.sql or run adopt --yes for a verified legacy schema.');
            }
            $status = $this->status();
            foreach ($status['problems'] as $problem) {
                $retryProblem = $retry !== null && in_array($problem, [
                    sprintf('Migration %03d is failed.', $retry),
                    sprintf('Migration %03d is running.', $retry),
                ], true);
                if (!str_contains($problem, 'migration(s) pending.') && !$retryProblem) {
                    throw new RuntimeException('Unsafe migration state: ' . $problem);
                }
            }
            if ($retry !== null) {
                $row = $status['rows'][$retry] ?? null;
                if ($row === null || !in_array($row['state'], ['failed', 'running'], true)) {
                    throw new RuntimeException(sprintf('Migration %03d is not failed or interrupted.', $retry));
                }
                $firstPending = array_key_first(array_filter($status['migrations'], static fn(array $m): bool => $m['state'] === 'pending'));
                if ($firstPending !== null && $retry > $firstPending) {
                    throw new RuntimeException('Cannot retry past an earlier pending migration.');
                }
            }
            foreach ($status['migrations'] as $migration) {
                if ($migration['state'] !== 'pending' && $migration['sequence'] !== $retry) {
                    continue;
                }
                $this->runOne($migration, $output);
            }
        } finally {
            $this->releaseLock();
        }
    }

    /** @param array{sequence:int,filename:string,path:string,checksum:string} $migration @param callable(string):void $output */
    private function runOne(array $migration, callable $output): void
    {
        $sequence = $migration['sequence'];
        $output(sprintf('Applying %03d %s', $sequence, $migration['filename']));
        $started = microtime(true);
        $upsert = $this->pdo->prepare("INSERT INTO schema_migrations
            (sequence, filename, checksum, state, started_at, applied_at, execution_ms, error_summary)
            VALUES (?, ?, ?, 'running', NOW(), NULL, NULL, NULL)
            ON DUPLICATE KEY UPDATE filename = VALUES(filename), checksum = VALUES(checksum), state = 'running', started_at = NOW(), applied_at = NULL, execution_ms = NULL, error_summary = NULL");
        $upsert->execute([$sequence, $migration['filename'], $migration['checksum']]);
        try {
            if ($sequence === 18 && $this->primaryDisplayEffectPresent()) {
                $output('  Existing compatible schema effect verified; recording as applied.');
            } elseif ($sequence === 18 && $this->primaryDisplayEffectPartiallyPresent()) {
                throw new RuntimeException('Migration 018 effects are only partially present; refusing conflicting DDL.');
            } else {
                $sql = file_get_contents($migration['path']);
                if ($sql === false) {
                    throw new RuntimeException('Cannot read migration file.');
                }
                foreach ($this->splitStatements($sql) as $statement) {
                    $this->pdo->exec($statement);
                }
            }
            $duration = max(0, (int)round((microtime(true) - $started) * 1000));
            $done = $this->pdo->prepare("UPDATE schema_migrations SET state = 'applied', applied_at = NOW(), execution_ms = ?, error_summary = NULL WHERE sequence = ?");
            $done->execute([$duration, $sequence]);
        } catch (Throwable $error) {
            $duration = max(0, (int)round((microtime(true) - $started) * 1000));
            $summary = substr(preg_replace('/\s+/', ' ', $error->getMessage()) ?? 'Migration failed.', 0, 500);
            $failed = $this->pdo->prepare("UPDATE schema_migrations SET state = 'failed', execution_ms = ?, error_summary = ? WHERE sequence = ?");
            $failed->execute([$duration, $summary, $sequence]);
            throw $error;
        } finally {
            try {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            } catch (Throwable) {
            }
        }
    }

    /** @return list<string> */
    public function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';
            if ($lineComment) {
                if ($char === "\n") { $lineComment = false; $buffer .= $char; }
                continue;
            }
            if ($blockComment) {
                if ($char === '*' && $next === '/') { $blockComment = false; $i++; }
                continue;
            }
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $next !== '') { $buffer .= $next; $i++; continue; }
                if ($char === $quote) {
                    if ($next === $quote) { $buffer .= $next; $i++; } else { $quote = null; }
                }
                continue;
            }
            if (($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) || $char === '#') {
                $lineComment = true; $i += $char === '-' ? 1 : 0; continue;
            }
            if ($char === '/' && $next === '*') { $blockComment = true; $i++; continue; }
            if ($char === "'" || $char === '"' || $char === '`') { $quote = $char; $buffer .= $char; continue; }
            if ($char === ';') {
                if (trim($buffer) !== '') { $statements[] = trim($buffer); }
                $buffer = ''; continue;
            }
            $buffer .= $char;
        }
        if ($quote !== null || $blockComment) {
            throw new RuntimeException('Migration contains an unterminated quote or comment.');
        }
        if (trim($buffer) !== '') { $statements[] = trim($buffer); }
        return $statements;
    }

    private function acquireLock(): void
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(?, 0)');
        $statement->execute([self::LOCK]);
        if ((int)$statement->fetchColumn() !== 1) {
            throw new RuntimeException('Another migration process holds the database lock.');
        }
    }

    private function releaseLock(): void
    {
        try { $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(?)'); $statement->execute([self::LOCK]); } catch (Throwable) {}
    }

    private function hasApplicationTables(): bool
    {
        $statement = $this->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name <> 'schema_migrations'");
        return (int)$statement->fetchColumn() > 0;
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $statement->execute([$table]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $statement->execute([$table, $column]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function indexExists(string $table, string $index): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
        $statement->execute([$table, $index]);
        return (int)$statement->fetchColumn() > 0;
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?');
        $statement->execute([$table, $constraint]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function columnMetadata(string $table, string $column): ?array
    {
        $statement = $this->pdo->prepare('SELECT IS_NULLABLE, COLUMN_TYPE, COLUMN_DEFAULT FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $statement->execute([$table, $column]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    private function primaryDisplayEffectPresent(): bool
    {
        return $this->columnExists('display_groups', 'primary_display_id')
            && $this->indexExists('display_groups', 'idx_display_groups_primary_display')
            && $this->constraintExists('display_groups', 'fk_display_group_primary_display');
    }

    private function primaryDisplayEffectPartiallyPresent(): bool
    {
        return $this->columnExists('display_groups', 'primary_display_id')
            || $this->indexExists('display_groups', 'idx_display_groups_primary_display')
            || $this->constraintExists('display_groups', 'fk_display_group_primary_display');
    }
}
