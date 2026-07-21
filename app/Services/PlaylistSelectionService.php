<?php
namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * The single authority for deciding which playlist belongs on a display.
 *
 * Weekly assignments outrank Fulltime assignments. Within either class the
 * lower priority wins, with the assignment id providing a stable tie-breaker.
 * The returned boundary is the next instant at which any active weekly rule
 * can change the result; clients use it to re-query rather than guessing.
 */
final class PlaylistSelectionService
{
    public function __construct(private Database $db)
    {
    }

    public function resolve(array $display, ?DateTimeInterface $at = null): array
    {
        $timezone = $this->timezone((string)($display['timezone'] ?? 'UTC'));
        $now = $at
            ? new DateTimeImmutable($at->format('Y-m-d H:i:s.u'), $at->getTimezone())
            : new DateTimeImmutable('now', $timezone);
        $now = $now->setTimezone($timezone);

        $rows = $this->db->all(
            'SELECT cdsa.id, cdsa.display_id, cdsa.channel_id, cdsa.schedule_id, cdsa.priority AS sort_order,
                    cdsa.is_active AS assignment_is_active, c.is_active AS channel_is_active, s.is_active AS schedule_is_active,
                    cdsa.created_at AS assignment_created_at,
                    s.name AS schedule_name, s.type AS schedule_type, s.updated_at AS schedule_updated_at,
                    sr.id AS schedule_rule_id, sr.weekday AS schedule_rule_weekday,
                    sr.start_time AS schedule_rule_start_time, sr.end_time AS schedule_rule_end_time,
                    c.name AS channel_name, c.description AS channel_description,
                    c.transition_effect, c.slide_duration_seconds, c.updated_at AS channel_updated_at
             FROM channel_display_schedule_assignments cdsa
             INNER JOIN channels c ON c.id = cdsa.channel_id
             INNER JOIN schedules s ON s.id = cdsa.schedule_id
             LEFT JOIN schedule_rules sr ON sr.schedule_id = s.id
             WHERE cdsa.display_id = ?
               AND cdsa.is_active = 1
               AND c.is_active = 1
               AND s.is_active = 1
             ORDER BY cdsa.id ASC, sr.id ASC',
            [(int)$display['id']]
        );

        return self::resolveRows($rows, $now);
    }

    /** @param array<int,array<string,mixed>> $rows */
    public static function resolveRows(array $rows, DateTimeImmutable $now): array
    {
        $assignments = [];
        foreach ($rows as $row) {
            if ((isset($row['assignment_is_active']) && !(int)$row['assignment_is_active'])
                || (isset($row['channel_is_active']) && !(int)$row['channel_is_active'])
                || (isset($row['schedule_is_active']) && !(int)$row['schedule_is_active'])) {
                continue;
            }
            $id = (int)$row['id'];
            $assignments[$id] ??= ['assignment' => $row, 'rules' => []];
            if (($row['schedule_type'] ?? '') === 'weekly_time_slot' && !empty($row['schedule_rule_id'])) {
                $assignments[$id]['rules'][] = [
                    'id' => (int)$row['schedule_rule_id'],
                    'weekday' => (int)$row['schedule_rule_weekday'],
                    'start' => (string)$row['schedule_rule_start_time'],
                    'end' => (string)$row['schedule_rule_end_time'],
                ];
            }
        }

        $eligible = [];
        $nextBoundary = null;
        foreach ($assignments as $candidate) {
            $assignment = $candidate['assignment'];
            $type = (string)($assignment['schedule_type'] ?? '');
            $matchingRule = null;

            foreach ($candidate['rules'] as $rule) {
                [$start, $end] = self::ruleWindow($now, $rule, 0);
                if ($now >= $start && $now < $end && $matchingRule === null) {
                    $matchingRule = $rule;
                }

                // Inspect a full week plus today so both later-today and next-week
                // changes are represented, even around DST transitions.
                for ($dayOffset = 0; $dayOffset <= 7; $dayOffset++) {
                    [$boundaryStart, $boundaryEnd] = self::ruleWindow($now, $rule, $dayOffset);
                    foreach ([$boundaryStart, $boundaryEnd] as $boundary) {
                        if ($boundary > $now && ($nextBoundary === null || $boundary < $nextBoundary)) {
                            $nextBoundary = $boundary;
                        }
                    }
                }
            }

            if ($type === 'fulltime' || ($type === 'weekly_time_slot' && $matchingRule !== null)) {
                if ($matchingRule !== null) {
                    $assignment['schedule_rule_id'] = $matchingRule['id'];
                    $assignment['schedule_rule_weekday'] = $matchingRule['weekday'];
                    $assignment['schedule_rule_start_time'] = $matchingRule['start'];
                    $assignment['schedule_rule_end_time'] = $matchingRule['end'];
                }
                $eligible[] = $assignment;
            }
        }

        usort($eligible, static function (array $left, array $right): int {
            $leftClass = ($left['schedule_type'] ?? '') === 'fulltime' ? 1 : 0;
            $rightClass = ($right['schedule_type'] ?? '') === 'fulltime' ? 1 : 0;
            return [$leftClass, (int)$left['sort_order'], (int)$left['id']]
                <=> [$rightClass, (int)$right['sort_order'], (int)$right['id']];
        });

        return [
            'assignment' => $eligible[0] ?? null,
            'next_selection_at_ms' => $nextBoundary ? ((int)$nextBoundary->format('U')) * 1000 : 0,
        ];
    }

    public static function playbackStatus(?array $assignment, array $slides): string
    {
        return $assignment === null ? 'no_playlist' : ($slides === [] ? 'no_slides' : 'ready');
    }

    private static function ruleWindow(DateTimeImmutable $now, array $rule, int $dayOffset): array
    {
        $date = $now->setTime(0, 0)->modify('+' . $dayOffset . ' days');
        $dateWeekday = (int)$date->format('N');
        $daysUntilRule = ((int)$rule['weekday'] - $dateWeekday + 7) % 7;
        $date = $date->modify('+' . $daysUntilRule . ' days');

        return [self::atTime($date, (string)$rule['start']), self::atTime($date, (string)$rule['end'])];
    }

    private static function atTime(DateTimeImmutable $date, string $time): DateTimeImmutable
    {
        [$hour, $minute, $second] = array_pad(array_map('intval', explode(':', $time)), 3, 0);
        return $date->setTime($hour, $minute, $second);
    }

    private function timezone(string $name): DateTimeZone
    {
        try {
            return new DateTimeZone($name !== '' ? $name : 'UTC');
        } catch (\Throwable) {
            return new DateTimeZone('UTC');
        }
    }
}
