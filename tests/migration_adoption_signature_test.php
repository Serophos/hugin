<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$serviceSource = file_get_contents($root . '/app/Core/MigrationService.php');
if (!is_string($serviceSource)) {
    throw new RuntimeException('Could not read MigrationService.php.');
}

if (!preg_match(
    '/private function migrationEffectState\(int \$sequence\): string(?<body>[\s\S]+?)\n    private function scheduleMigrationState\(\): string/',
    $serviceSource,
    $method
)) {
    throw new RuntimeException('Could not locate migrationEffectState().');
}

$migrationFiles = glob($root . '/db-migrations/*.sql');
if (!is_array($migrationFiles) || $migrationFiles === []) {
    throw new RuntimeException('No migrations found.');
}

foreach ($migrationFiles as $migrationFile) {
    $filename = basename($migrationFile);
    if (!preg_match('/^(\d{3})_/', $filename, $match)) {
        continue;
    }
    $sequence = (int)$match[1];
    if (!preg_match('/\b' . preg_quote((string)$sequence, '/') . '\s*=>/', $method['body'])) {
        throw new RuntimeException(sprintf(
            'Migration %03d has no legacy adoption signature.',
            $sequence
        ));
    }
}

$playbackColumns = [
    'reported_state_signature',
    'reported_playback_status',
    'playback_reported_at',
    'pending_state_signature',
    'pending_activation_at_ms',
];
foreach ($playbackColumns as $column) {
    if (substr_count($serviceSource, "'" . $column . "'") < 2) {
        throw new RuntimeException(
            'Migration 023 column is missing from adoption or current-schema validation: ' . $column
        );
    }
}

echo "Migration adoption signature tests passed.\n";
