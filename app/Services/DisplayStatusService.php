<?php
namespace App\Services;

use App\Core\Database;
class DisplayStatusService
{
    private PlaylistSelectionService $playlistSelection;

    public function __construct(private Database $db)
    {
        $this->playlistSelection = new PlaylistSelectionService($db);
    }

    public function getAllDisplayStatuses(bool $activeOnly = false): array
    {
        $sql = 'SELECT d.id, d.name, d.slug, d.description, d.timezone, d.orientation, d.is_active,
                       d.transition_effect, d.slide_duration_seconds, d.updated_at,
                       h.last_seen_at, h.last_seen_ip, h.current_channel_id, h.current_channel_name,
                       h.reported_state_signature, h.reported_playback_status, h.playback_reported_at,
                       h.pending_state_signature, h.pending_activation_at_ms,
                       h.browser_name, h.browser_version, h.os_name, h.os_version, h.platform,
                       h.language, h.client_timezone, h.screen_width, h.screen_height,
                       h.avail_screen_width, h.avail_screen_height, h.viewport_width, h.viewport_height,
                       h.device_pixel_ratio, h.color_depth, h.max_touch_points, h.hardware_concurrency,
                       h.device_memory_gb, h.screen_orientation, h.is_online, h.cookies_enabled,
                       h.user_agent,
                       TIMESTAMPDIFF(SECOND, h.last_seen_at, NOW()) AS heartbeat_age_seconds
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
                    h.reported_state_signature, h.reported_playback_status, h.playback_reported_at,
                    h.pending_state_signature, h.pending_activation_at_ms,
                    h.browser_name, h.browser_version, h.os_name, h.os_version, h.platform,
                    h.language, h.client_timezone, h.screen_width, h.screen_height,
                    h.avail_screen_width, h.avail_screen_height, h.viewport_width, h.viewport_height,
                    h.device_pixel_ratio, h.color_depth, h.max_touch_points, h.hardware_concurrency,
                    h.device_memory_gb, h.screen_orientation, h.is_online, h.cookies_enabled,
                    h.user_agent,
                    TIMESTAMPDIFF(SECOND, h.last_seen_at, NOW()) AS heartbeat_age_seconds
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
        return $this->playlistSelection->resolve($display)['assignment'];
    }

    private function hydrateDisplayStatus(array $display): array
    {
        $activeAssignment = $this->resolveActiveAssignment($display);
        $display['expected_channel_id'] = isset($activeAssignment['channel_id'])
            ? (int)$activeAssignment['channel_id']
            : null;
        $display['expected_channel_name'] = $activeAssignment['channel_name'] ?? null;
        $display['reported_channel_id'] = $display['current_channel_id'] !== null
            ? (int)$display['current_channel_id']
            : null;
        $display['reported_channel_name'] = $display['current_channel_name'] ?: null;
        // Retain the old resolved fields as the expected schedule for callers
        // that have not migrated to the explicit expected/reported contract.
        $display['resolved_channel_id'] = $display['expected_channel_id'];
        $display['resolved_channel_name'] = $display['expected_channel_name'];
        $display['playback_in_sync'] = $display['playback_reported_at'] === null
            ? null
            : $display['expected_channel_id'] === $display['reported_channel_id'];
        $heartbeatAgeSeconds = $this->heartbeatAgeSeconds($display['heartbeat_age_seconds'] ?? null);
        $display['seconds_since_seen'] = $this->secondsSinceSeen($display['last_seen_at'] ?? null, $heartbeatAgeSeconds);
        $display['minutes_since_seen'] = $this->minutesSinceSeen($display['last_seen_at'] ?? null, $heartbeatAgeSeconds);
        $display['monitoring_status'] = $this->determineMonitoringStatus((int)$display['is_active'], $display['last_seen_at'] ?? null, $heartbeatAgeSeconds);
        $display['online'] = $display['monitoring_status'] === 'online';

        return $display;
    }

    private function determineMonitoringStatus(int $isActive, ?string $lastSeenAt, ?int $heartbeatAgeSeconds = null): string
    {
        if ($isActive !== 1) {
            return 'inactive';
        }

        if (!$lastSeenAt) {
            return 'never_seen';
        }

        $seconds = $this->secondsSinceSeen($lastSeenAt, $heartbeatAgeSeconds);
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

    private function heartbeatAgeSeconds(mixed $value): ?int
    {
        return $value === null ? null : max(0, (int)$value);
    }

    private function secondsSinceSeen(?string $lastSeenAt, ?int $heartbeatAgeSeconds = null): ?int
    {
        if (!$lastSeenAt) {
            return null;
        }

        if ($heartbeatAgeSeconds !== null) {
            return $heartbeatAgeSeconds;
        }

        $timestamp = strtotime($lastSeenAt);
        if ($timestamp === false) {
            return null;
        }

        return max(0, time() - $timestamp);
    }

    private function minutesSinceSeen(?string $lastSeenAt, ?int $heartbeatAgeSeconds = null): ?int
    {
        $seconds = $this->secondsSinceSeen($lastSeenAt, $heartbeatAgeSeconds);
        return $seconds === null ? null : (int) floor($seconds / 60);
    }

    private function onlineThresholdSeconds(): int
    {
        return max(30, (int) app_core_setting('monitoring.online_threshold_seconds', 180));
    }

    private function staleThresholdSeconds(): int
    {
        return max($this->onlineThresholdSeconds(), (int) app_core_setting('monitoring.stale_threshold_seconds', 1800));
    }
}
