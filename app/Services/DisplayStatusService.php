<?php
namespace App\Services;

use App\Core\Database;
use DateTime;
use DateTimeZone;

class DisplayStatusService
{
    public function __construct(private Database $db)
    {
    }

    public function getAllDisplayStatuses(bool $activeOnly = false): array
    {
        $sql = 'SELECT d.id, d.name, d.slug, d.description, d.timezone, d.orientation, d.is_active,
                       d.transition_effect, d.slide_duration_seconds, d.updated_at,
                       h.last_seen_at, h.last_seen_ip, h.current_channel_id, h.current_channel_name,
                       h.browser_name, h.browser_version, h.os_name, h.os_version, h.platform,
                       h.language, h.client_timezone, h.screen_width, h.screen_height,
                       h.avail_screen_width, h.avail_screen_height, h.viewport_width, h.viewport_height,
                       h.device_pixel_ratio, h.color_depth, h.max_touch_points, h.hardware_concurrency,
                       h.device_memory_gb, h.screen_orientation, h.is_online, h.cookies_enabled,
                       h.user_agent
                FROM displays d
                LEFT JOIN display_heartbeats h ON h.display_id = d.id';

        $params = [];
        if ($activeOnly) {
            $sql .= ' WHERE d.is_active = 1';
        }

        $sql .= ' ORDER BY d.sort_order ASC, d.name ASC';

        $rows = $this->db->all($sql, $params);
        foreach ($rows as &$row) {
            $row = $this->hydrateDisplayStatus($row);
        }
        unset($row);

        return $rows;
    }

    public function getDisplayStatusBySlug(string $slug): ?array
    {
        $row = $this->db->one(
            'SELECT d.id, d.name, d.slug, d.description, d.timezone, d.orientation, d.is_active,
                    d.transition_effect, d.slide_duration_seconds, d.updated_at,
                    h.last_seen_at, h.last_seen_ip, h.current_channel_id, h.current_channel_name,
                    h.browser_name, h.browser_version, h.os_name, h.os_version, h.platform,
                    h.language, h.client_timezone, h.screen_width, h.screen_height,
                    h.avail_screen_width, h.avail_screen_height, h.viewport_width, h.viewport_height,
                    h.device_pixel_ratio, h.color_depth, h.max_touch_points, h.hardware_concurrency,
                    h.device_memory_gb, h.screen_orientation, h.is_online, h.cookies_enabled,
                    h.user_agent
             FROM displays d
             LEFT JOIN display_heartbeats h ON h.display_id = d.id
             WHERE d.slug = ?
             LIMIT 1',
            [$slug]
        );

        return $row ? $this->hydrateDisplayStatus($row) : null;
    }

    public function getSummary(): array
    {
        $statuses = $this->getAllDisplayStatuses();
        $summary = [
            'generated_at' => date('c'),
            'thresholds' => [
                'online_seconds' => $this->onlineThresholdSeconds(),
                'stale_seconds' => $this->staleThresholdSeconds(),
            ],
            'totals' => [
                'displays' => count($statuses),
                'active_displays' => 0,
                'inactive_displays' => 0,
                'online_displays' => 0,
                'stale_displays' => 0,
                'offline_displays' => 0,
                'never_seen_displays' => 0,
            ],
            'channels' => [],
        ];

        $channels = [];
        foreach ($statuses as $status) {
            if ((int)$status['is_active'] === 1) {
                $summary['totals']['active_displays']++;
            } else {
                $summary['totals']['inactive_displays']++;
            }

            if ($status['monitoring_status'] === 'online') {
                $summary['totals']['online_displays']++;
            } elseif ($status['monitoring_status'] === 'stale') {
                $summary['totals']['stale_displays']++;
            } elseif ($status['monitoring_status'] === 'offline') {
                $summary['totals']['offline_displays']++;
            } elseif ($status['monitoring_status'] === 'never_seen') {
                $summary['totals']['never_seen_displays']++;
            }

            if ((int)$status['is_active'] === 1 && !empty($status['resolved_channel_name'])) {
                $name = (string)$status['resolved_channel_name'];
                $channels[$name] = ($channels[$name] ?? 0) + 1;
            }
        }

        ksort($channels, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($channels as $channelName => $displayCount) {
            $summary['channels'][] = [
                'channel' => $channelName,
                'display_count' => $displayCount,
            ];
        }

        return $summary;
    }

    public function getHealth(): array
    {
        $statuses = $this->getAllDisplayStatuses(true);
        $totals = [
            'displays' => count($statuses),
            'online' => 0,
            'stale' => 0,
            'offline' => 0,
            'never_seen' => 0,
        ];

        foreach ($statuses as $status) {
            if ($status['monitoring_status'] === 'online') {
                $totals['online']++;
            } elseif ($status['monitoring_status'] === 'stale') {
                $totals['stale']++;
            } elseif ($status['monitoring_status'] === 'offline') {
                $totals['offline']++;
            } elseif ($status['monitoring_status'] === 'never_seen') {
                $totals['never_seen']++;
            }
        }

        $status = 'ok';
        if ($totals['displays'] > 0 && $totals['online'] === 0) {
            $status = 'critical';
        } elseif ($totals['stale'] > 0 || $totals['offline'] > 0 || $totals['never_seen'] > 0) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'timestamp' => date('c'),
            'totals' => $totals,
            'checks' => [
                'database' => 'ok',
                'heartbeat_data' => 'ok',
            ],
            'thresholds' => [
                'online_seconds' => $this->onlineThresholdSeconds(),
                'stale_seconds' => $this->staleThresholdSeconds(),
            ],
        ];
    }

    public function resolveActiveAssignment(array $display): ?array
    {
        $timezone = new DateTimeZone(($display['timezone'] ?? '') ?: 'UTC');
        $now = new DateTime('now', $timezone);
        $weekday = (int)$now->format('N');
        $currentTime = $now->format('H:i:s');

        return $this->db->one(
            'SELECT cdsa.id, cdsa.channel_id, cdsa.schedule_id, cdsa.priority AS sort_order,
                    s.name AS schedule_name, s.type AS schedule_type,
                    c.name AS channel_name, c.description AS channel_description,
                    c.transition_effect, c.slide_duration_seconds, c.updated_at AS channel_updated_at, c.is_active AS channel_is_active
             FROM channel_display_schedule_assignments cdsa
             INNER JOIN channels c ON c.id = cdsa.channel_id
             INNER JOIN schedules s ON s.id = cdsa.schedule_id
             LEFT JOIN schedule_rules sr ON sr.schedule_id = s.id
                AND s.type = \'weekly_time_slot\'
                AND sr.weekday = ?
                AND ? >= sr.start_time
                AND ? < sr.end_time
             WHERE cdsa.display_id = ?
               AND cdsa.is_active = 1
               AND c.is_active = 1
               AND s.is_active = 1
               AND (
                    s.type = \'fulltime\'
                    OR (s.type = \'weekly_time_slot\' AND sr.id IS NOT NULL)
               )
             ORDER BY cdsa.priority ASC, cdsa.id ASC, sr.id ASC
             LIMIT 1',
            [$weekday, $currentTime, $currentTime, $display['id']]
        );
    }

    private function hydrateDisplayStatus(array $display): array
    {
        $activeAssignment = $this->resolveActiveAssignment($display);
        $display['resolved_channel_id'] = $activeAssignment['channel_id'] ?? ($display['current_channel_id'] ?: null);
        $display['resolved_channel_name'] = $activeAssignment['channel_name'] ?? ($display['current_channel_name'] ?: null);
        $display['seconds_since_seen'] = $this->secondsSinceSeen($display['last_seen_at'] ?? null);
        $display['minutes_since_seen'] = $this->minutesSinceSeen($display['last_seen_at'] ?? null);
        $display['monitoring_status'] = $this->determineMonitoringStatus((int)$display['is_active'], $display['last_seen_at'] ?? null);
        $display['online'] = $display['monitoring_status'] === 'online';

        return $display;
    }

    private function determineMonitoringStatus(int $isActive, ?string $lastSeenAt): string
    {
        if ($isActive !== 1) {
            return 'inactive';
        }

        if (!$lastSeenAt) {
            return 'never_seen';
        }

        $seconds = $this->secondsSinceSeen($lastSeenAt);
        if ($seconds === null) {
            return 'never_seen';
        }

        if ($seconds <= $this->onlineThresholdSeconds()) {
            return 'online';
        }

        if ($seconds <= $this->staleThresholdSeconds()) {
            return 'stale';
        }

        return 'offline';
    }

    private function secondsSinceSeen(?string $lastSeenAt): ?int
    {
        if (!$lastSeenAt) {
            return null;
        }

        $timestamp = strtotime($lastSeenAt);
        if ($timestamp === false) {
            return null;
        }

        return max(0, time() - $timestamp);
    }

    private function minutesSinceSeen(?string $lastSeenAt): ?int
    {
        $seconds = $this->secondsSinceSeen($lastSeenAt);
        return $seconds === null ? null : (int) floor($seconds / 60);
    }

    private function onlineThresholdSeconds(): int
    {
        return max(30, (int) app_config('monitoring.online_threshold_seconds', 180));
    }

    private function staleThresholdSeconds(): int
    {
        return max($this->onlineThresholdSeconds(), (int) app_config('monitoring.stale_threshold_seconds', 1800));
    }
}
