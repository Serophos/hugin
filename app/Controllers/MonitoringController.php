<?php
namespace App\Controllers;

use App\Core\Database;
use App\Services\DisplayStatusService;

class MonitoringController
{
    public function __construct(private Database $db, private DisplayStatusService $displayStatusService)
    {
    }

    public function health(): void
    {
        $this->authorize();
        json_response($this->displayStatusService->getHealth());
    }

    public function summary(): void
    {
        $this->authorize();
        json_response($this->displayStatusService->getSummary());
    }

    public function displays(): void
    {
        $this->authorize();

        $activeOnly = $this->isTruthy($_GET['active_only'] ?? null);
        $statuses = $this->displayStatusService->getAllDisplayStatuses($activeOnly);

        json_response([
            'generated_at' => date('c'),
            'count' => count($statuses),
            'active_only' => $activeOnly,
            'displays' => array_map([$this, 'transformDisplay'], $statuses),
        ]);
    }

    public function display(string $slug): void
    {
        $this->authorize();

        $status = $this->displayStatusService->getDisplayStatusBySlug($slug);
        if (!$status) {
            json_response([
                'ok' => false,
                'message' => __('monitoring.display_not_found', ['slug' => $slug], 'Display not found: ' . $slug),
            ], 404);
        }

        json_response([
            'generated_at' => date('c'),
            'display' => $this->transformDisplay($status),
        ]);
    }

    private function authorize(): void
    {
        if (!app_config('monitoring.enabled', false)) {
            json_response([
                'ok' => false,
                'message' => __('monitoring.disabled', [], 'Monitoring API is disabled.'),
            ], 404);
        }

        $expectedToken = trim((string) app_config('monitoring.api_token', ''));
        if ($expectedToken === '') {
            json_response([
                'ok' => false,
                'message' => __('monitoring.token_not_configured', [], 'Monitoring API token is not configured.'),
            ], 503);
        }

        $providedToken = $this->extractBearerToken();
        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            header('WWW-Authenticate: Bearer realm="monitoring"');
            json_response([
                'ok' => false,
                'message' => __('monitoring.unauthorized', [], 'Unauthorized.'),
            ], 401);
        }
    }

    private function extractBearerToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!is_string($header) || $header === '') {
            return '';
        }

        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function transformDisplay(array $status): array
    {
        return [
            'id' => (int) $status['id'],
            'name' => (string) $status['name'],
            'slug' => (string) $status['slug'],
            'description' => (string) ($status['description'] ?? ''),
            'active' => (int) $status['is_active'] === 1,
            'status' => (string) $status['monitoring_status'],
            'last_seen_at' => $status['last_seen_at'] ?: null,
            'seconds_since_seen' => $status['seconds_since_seen'],
            'minutes_since_seen' => $status['minutes_since_seen'],
            'channel' => [
                'id' => $status['resolved_channel_id'] !== null ? (int) $status['resolved_channel_id'] : null,
                'name' => $status['resolved_channel_name'] ?: null,
            ],
            'client' => [
                'ip' => $status['last_seen_ip'] ?: null,
                'browser' => $status['browser_name'] ?: null,
                'browser_version' => $status['browser_version'] ?: null,
                'os' => $status['os_name'] ?: null,
                'os_version' => $status['os_version'] ?: null,
                'platform' => $status['platform'] ?: null,
                'language' => $status['language'] ?: null,
                'timezone' => $status['client_timezone'] ?: null,
                'navigator_online' => $status['is_online'] === null ? null : ((int) $status['is_online'] === 1),
                'cookies_enabled' => $status['cookies_enabled'] === null ? null : ((int) $status['cookies_enabled'] === 1),
                'user_agent' => $status['user_agent'] ?: null,
            ],
            'screen' => [
                'width' => $status['screen_width'] !== null ? (int) $status['screen_width'] : null,
                'height' => $status['screen_height'] !== null ? (int) $status['screen_height'] : null,
                'available_width' => $status['avail_screen_width'] !== null ? (int) $status['avail_screen_width'] : null,
                'available_height' => $status['avail_screen_height'] !== null ? (int) $status['avail_screen_height'] : null,
                'viewport_width' => $status['viewport_width'] !== null ? (int) $status['viewport_width'] : null,
                'viewport_height' => $status['viewport_height'] !== null ? (int) $status['viewport_height'] : null,
                'pixel_ratio' => $status['device_pixel_ratio'] !== null ? (float) $status['device_pixel_ratio'] : null,
                'color_depth' => $status['color_depth'] !== null ? (int) $status['color_depth'] : null,
                'orientation' => $status['screen_orientation'] ?: null,
                'max_touch_points' => $status['max_touch_points'] !== null ? (int) $status['max_touch_points'] : null,
                'hardware_concurrency' => $status['hardware_concurrency'] !== null ? (int) $status['hardware_concurrency'] : null,
                'device_memory_gb' => $status['device_memory_gb'] !== null ? (float) $status['device_memory_gb'] : null,
            ],
        ];
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
