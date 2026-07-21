<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\PlaylistSelectionService;

$failures = 0;
$test = static function (string $name, callable $callback) use (&$failures): void {
    try {
        $callback();
        echo "PASS {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}: {$error->getMessage()}\n");
    }
};
$same = static function (mixed $expected, mixed $actual): void {
    if ($expected !== $actual) {
        throw new RuntimeException('expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};
$row = static function (int $id, string $type, int $priority, ?array $rule = null, array $flags = []): array {
    return array_replace([
        'id' => $id,
        'display_id' => 1,
        'channel_id' => $id,
        'schedule_id' => $id,
        'sort_order' => $priority,
        'schedule_type' => $type,
        'schedule_rule_id' => $rule['id'] ?? null,
        'schedule_rule_weekday' => $rule['weekday'] ?? null,
        'schedule_rule_start_time' => $rule['start'] ?? null,
        'schedule_rule_end_time' => $rule['end'] ?? null,
        'assignment_is_active' => 1,
        'channel_is_active' => 1,
        'schedule_is_active' => 1,
    ], $flags);
};
$at = static fn(string $value, string $timezone = 'UTC'): DateTimeImmutable => new DateTimeImmutable($value, new DateTimeZone($timezone));

$test('weekly beats fulltime regardless of numeric priority', function () use ($row, $at, $same): void {
    $rows = [$row(1, 'fulltime', 1), $row(2, 'weekly_time_slot', 99, ['id' => 20, 'weekday' => 1, 'start' => '09:00:00', 'end' => '11:00:00'])];
    $same(2, (int)PlaylistSelectionService::resolveRows($rows, $at('2026-07-20 10:00:00'))['assignment']['id']);
});

$test('priority and id are deterministic tie breakers', function () use ($row, $at, $same): void {
    $rows = [$row(8, 'fulltime', 2), $row(7, 'fulltime', 2), $row(9, 'fulltime', 3)];
    $same(7, (int)PlaylistSelectionService::resolveRows($rows, $at('2026-07-20 10:00:00'))['assignment']['id']);
});

$test('inactive records are ignored', function () use ($row, $at, $same): void {
    $rows = [$row(1, 'fulltime', 1, null, ['channel_is_active' => 0]), $row(2, 'fulltime', 2)];
    $same(2, (int)PlaylistSelectionService::resolveRows($rows, $at('2026-07-20 10:00:00'))['assignment']['id']);
});

$test('start is inclusive and end is exclusive', function () use ($row, $at, $same): void {
    $rows = [$row(1, 'fulltime', 1), $row(2, 'weekly_time_slot', 1, ['id' => 20, 'weekday' => 1, 'start' => '09:00:00', 'end' => '10:00:00'])];
    $same(2, (int)PlaylistSelectionService::resolveRows($rows, $at('2026-07-20 09:00:00'))['assignment']['id']);
    $same(1, (int)PlaylistSelectionService::resolveRows($rows, $at('2026-07-20 10:00:00'))['assignment']['id']);
});

$test('overlapping rules and playlists still obey priority', function () use ($row, $at, $same): void {
    $rows = [
        $row(2, 'weekly_time_slot', 2, ['id' => 21, 'weekday' => 1, 'start' => '08:00:00', 'end' => '12:00:00']),
        $row(1, 'weekly_time_slot', 1, ['id' => 11, 'weekday' => 1, 'start' => '09:00:00', 'end' => '11:00:00']),
    ];
    $same(1, (int)PlaylistSelectionService::resolveRows($rows, $at('2026-07-20 10:00:00'))['assignment']['id']);
});

$test('next boundary crosses midnight and week rollover', function () use ($row, $at, $same): void {
    $rows = [$row(2, 'weekly_time_slot', 1, ['id' => 20, 'weekday' => 1, 'start' => '00:15:00', 'end' => '01:00:00'])];
    $result = PlaylistSelectionService::resolveRows($rows, $at('2026-07-19 23:50:00'));
    $same($at('2026-07-20 00:15:00')->getTimestamp() * 1000, $result['next_selection_at_ms']);
});

$test('display timezone and DST use real local boundary instants', function () use ($row, $at, $same): void {
    $rows = [$row(2, 'weekly_time_slot', 1, ['id' => 20, 'weekday' => 7, 'start' => '03:30:00', 'end' => '04:30:00'])];
    $result = PlaylistSelectionService::resolveRows($rows, $at('2026-03-29 01:00:00', 'Europe/Berlin'));
    $same($at('2026-03-29 03:30:00', 'Europe/Berlin')->getTimestamp() * 1000, $result['next_selection_at_ms']);
});

$test('no eligible assignment is explicit', function () use ($row, $at, $same): void {
    $rows = [$row(2, 'weekly_time_slot', 1, ['id' => 20, 'weekday' => 2, 'start' => '09:00:00', 'end' => '10:00:00'])];
    $same(null, PlaylistSelectionService::resolveRows($rows, $at('2026-07-20 10:00:00'))['assignment']);
});

$test('playback status distinguishes missing and empty playlists', function () use ($same): void {
    $same('no_playlist', PlaylistSelectionService::playbackStatus(null, []));
    $same('no_slides', PlaylistSelectionService::playbackStatus(['id' => 1], []));
    $same('ready', PlaylistSelectionService::playbackStatus(['id' => 1], [['id' => 10]]));
});

exit($failures === 0 ? 0 : 1);
