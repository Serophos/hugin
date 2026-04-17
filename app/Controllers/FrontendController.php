<?php
/**
 * Hugin - Digital Signage System
 * Copyright (C) 2026 Thees Winkler
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Source code: https://github.com/Serophos/hugin
 */

namespace App\Controllers;

use App\Core\Database;
use App\Core\PluginManager;
use App\Core\View;
use DateTime;
use DateTimeZone;

class FrontendController
{
    public function __construct(private Database $db, private View $view, private PluginManager $plugins)
    {
    }

    public function display(string $slug): void
    {
        $display = $this->db->one('SELECT * FROM displays WHERE slug = ? AND is_active = 1', [$slug]);

        if (!$display) {
            http_response_code(404);
            echo __('frontend.display_not_found');
            return;
        }

        $activeAssignment = $this->resolveActiveAssignment($display);
        if (!$activeAssignment) {
            http_response_code(500);
            echo __('frontend.no_active_channel');
            return;
        }

        $slides = $this->loadChannelSlides((int)$activeAssignment['channel_id']);
        if (!$slides) {
            http_response_code(500);
            echo __('frontend.no_active_slides');
            return;
        }

        $effect = $activeAssignment['transition_effect'] !== 'inherit'
            ? $activeAssignment['transition_effect']
            : $display['transition_effect'];

        $duration = (int)($activeAssignment['slide_duration_seconds'] ?: $display['slide_duration_seconds']);
        $heartbeat = $this->db->one('SELECT * FROM display_heartbeats WHERE display_id = ?', [$display['id']]);
        [$resolvedSlides, $pluginAssets] = $this->resolveSlides($slides, $display, $activeAssignment, $heartbeat, $duration);
        $state = $this->buildDisplayState($display, $activeAssignment, $resolvedSlides, $effect, $duration);

        $this->view->render('frontend/display', [
            'display' => $display,
            'channel' => [
                'id' => $activeAssignment['channel_id'],
                'name' => $activeAssignment['channel_name'],
                'description' => $activeAssignment['channel_description'],
            ],
            'slides' => $resolvedSlides,
            'effect' => $effect,
            'duration' => $duration,
            'stateSignature' => $state['signature'],
            'orientation' => $display['orientation'] ?? 'landscape',
            'pluginAssets' => $pluginAssets,
        ]);
    }

    public function state(string $slug): void
    {
        $display = $this->db->one('SELECT * FROM displays WHERE slug = ? AND is_active = 1', [$slug]);
        if (!$display) {
            json_response(['ok' => false, 'message' => __('frontend.state_message_display_not_found')], 404);
        }

        $activeAssignment = $this->resolveActiveAssignment($display);
        if (!$activeAssignment) {
            json_response(['ok' => false, 'message' => __('frontend.state_message_no_channel')], 409);
        }

        $slides = $this->loadChannelSlides((int)$activeAssignment['channel_id']);
        if (!$slides) {
            json_response(['ok' => false, 'message' => __('frontend.state_message_no_slides')], 409);
        }

        $effect = $activeAssignment['transition_effect'] !== 'inherit'
            ? $activeAssignment['transition_effect']
            : $display['transition_effect'];

        $duration = (int)($activeAssignment['slide_duration_seconds'] ?: $display['slide_duration_seconds']);
        $heartbeat = $this->db->one('SELECT * FROM display_heartbeats WHERE display_id = ?', [$display['id']]);
        [$resolvedSlides] = $this->resolveSlides($slides, $display, $activeAssignment, $heartbeat, $duration);

        json_response($this->buildDisplayState($display, $activeAssignment, $resolvedSlides, $effect, $duration));
    }

    public function heartbeat(string $slug): void
    {
        $display = $this->db->one('SELECT * FROM displays WHERE slug = ? AND is_active = 1', [$slug]);
        if (!$display) {
            json_response(['ok' => false, 'message' => __('frontend.state_message_display_not_found')], 404);
        }

        $activeAssignment = $this->resolveActiveAssignment($display);
        $payload = $this->readJsonBody();
        $ipAddress = client_ip();
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $channelId = $activeAssignment ? (int)$activeAssignment['channel_id'] : null;
        $channelName = $activeAssignment['channel_name'] ?? null;

        $browserName = $this->limitString($payload['browserName'] ?? null, 80);
        $browserVersion = $this->limitString($payload['browserVersion'] ?? null, 80);
        $osName = $this->limitString($payload['osName'] ?? null, 80);
        $osVersion = $this->limitString($payload['osVersion'] ?? null, 80);
        $platform = $this->limitString($payload['platform'] ?? null, 120);
        $language = $this->limitString($payload['language'] ?? null, 32);
        $timezone = $this->limitString($payload['timezone'] ?? null, 64);
        $screenWidth = $this->positiveIntOrNull($payload['screenWidth'] ?? null);
        $screenHeight = $this->positiveIntOrNull($payload['screenHeight'] ?? null);
        $availableScreenWidth = $this->positiveIntOrNull($payload['availScreenWidth'] ?? null);
        $availableScreenHeight = $this->positiveIntOrNull($payload['availScreenHeight'] ?? null);
        $viewportWidth = $this->positiveIntOrNull($payload['viewportWidth'] ?? null);
        $viewportHeight = $this->positiveIntOrNull($payload['viewportHeight'] ?? null);
        $pixelRatio = $this->floatOrNull($payload['devicePixelRatio'] ?? null);
        $colorDepth = $this->positiveIntOrNull($payload['colorDepth'] ?? null);
        $touchPoints = $this->positiveIntOrNull($payload['maxTouchPoints'] ?? null);
        $hardwareConcurrency = $this->positiveIntOrNull($payload['hardwareConcurrency'] ?? null);
        $deviceMemory = $this->floatOrNull($payload['deviceMemory'] ?? null);
        $orientation = $this->limitString($payload['screenOrientation'] ?? null, 32);
        $onlineState = array_key_exists('online', $payload) ? ((bool)$payload['online'] ? 1 : 0) : null;
        $cookieEnabled = array_key_exists('cookieEnabled', $payload) ? ((bool)$payload['cookieEnabled'] ? 1 : 0) : null;
        $clientPayloadJson = $this->normalizeJson($payload);

        $this->db->execute(
            'INSERT INTO display_heartbeats (
                display_id, current_channel_id, current_channel_name, last_seen_ip, user_agent,
                browser_name, browser_version, os_name, os_version, platform, language, client_timezone,
                screen_width, screen_height, avail_screen_width, avail_screen_height,
                viewport_width, viewport_height, device_pixel_ratio, color_depth,
                max_touch_points, hardware_concurrency, device_memory_gb, screen_orientation,
                is_online, cookies_enabled, client_payload_json, last_seen_at
            )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                current_channel_id = VALUES(current_channel_id),
                current_channel_name = VALUES(current_channel_name),
                last_seen_ip = VALUES(last_seen_ip),
                user_agent = VALUES(user_agent),
                browser_name = VALUES(browser_name),
                browser_version = VALUES(browser_version),
                os_name = VALUES(os_name),
                os_version = VALUES(os_version),
                platform = VALUES(platform),
                language = VALUES(language),
                client_timezone = VALUES(client_timezone),
                screen_width = VALUES(screen_width),
                screen_height = VALUES(screen_height),
                avail_screen_width = VALUES(avail_screen_width),
                avail_screen_height = VALUES(avail_screen_height),
                viewport_width = VALUES(viewport_width),
                viewport_height = VALUES(viewport_height),
                device_pixel_ratio = VALUES(device_pixel_ratio),
                color_depth = VALUES(color_depth),
                max_touch_points = VALUES(max_touch_points),
                hardware_concurrency = VALUES(hardware_concurrency),
                device_memory_gb = VALUES(device_memory_gb),
                screen_orientation = VALUES(screen_orientation),
                is_online = VALUES(is_online),
                cookies_enabled = VALUES(cookies_enabled),
                client_payload_json = VALUES(client_payload_json),
                last_seen_at = NOW()',
            [
                $display['id'], $channelId, $channelName, $ipAddress, $userAgent,
                $browserName, $browserVersion, $osName, $osVersion, $platform, $language, $timezone,
                $screenWidth, $screenHeight, $availableScreenWidth, $availableScreenHeight,
                $viewportWidth, $viewportHeight, $pixelRatio, $colorDepth,
                $touchPoints, $hardwareConcurrency, $deviceMemory, $orientation,
                $onlineState, $cookieEnabled, $clientPayloadJson,
            ]
        );

        json_response([
            'ok' => true,
            'display' => $display['name'],
            'channel' => $channelName,
            'seen_at' => date('c'),
        ]);
    }

    private function loadChannelSlides(int $channelId): array
    {
        return $this->db->all(
            'SELECT s.*, m.file_path AS media_file_path
             FROM channel_slide_assignments csa
             INNER JOIN slides s ON s.id = csa.slide_id
             LEFT JOIN media_assets m ON m.id = s.media_asset_id
             WHERE csa.channel_id = ? AND s.is_active = 1
             ORDER BY csa.sort_order ASC, s.id ASC',
            [$channelId]
        );
    }

    private function resolveSlides(array $slides, array $display, array $activeAssignment, ?array $heartbeat, int $duration): array
    {
        $resolved = [];
        $assets = ['css' => [], 'js' => []];
        $channelContext = [
            'id' => $activeAssignment['channel_id'],
            'name' => $activeAssignment['channel_name'],
            'description' => $activeAssignment['channel_description'],
        ];

        foreach ($slides as $slide) {
            $slide['resolved_source_url'] = $slide['media_file_path'] ?: $slide['source_url'];
            $slide['resolved_duration'] = (int)($slide['duration_seconds'] ?: $duration);
            $slide['plugin_name'] = null;
            $slide['plugin_settings'] = [];
            $slide['plugin_rendered_html'] = null;
            $slide['plugin_state'] = null;

            $plugin = $this->plugins->getPluginBySlideType((string)$slide['slide_type'], true);
            if ($plugin) {
                $settings = $this->plugins->loadSlideSettings((int)$slide['id'], $plugin->getName());
                $api = $this->plugins->buildApi($display, $channelContext, $heartbeat);
                $slide['plugin_name'] = $plugin->getName();
                $slide['plugin_settings'] = $settings;
                $slide['plugin_rendered_html'] = $plugin->renderFrontend($slide, $settings, $api);
                $slide['plugin_state'] = $plugin->getStateData($slide, $settings, $api);
                $pluginAssets = $plugin->getFrontendAssets($slide, $settings, $api);
                foreach (['css', 'js'] as $type) {
                    foreach (($pluginAssets[$type] ?? []) as $asset) {
                        if (!in_array($asset, $assets[$type], true)) {
                            $assets[$type][] = $asset;
                        }
                    }
                }
            } elseif (!$this->isBuiltInSlideType((string)$slide['slide_type'])) {
                $knownPlugin = $this->plugins->getPluginBySlideType((string)$slide['slide_type'], false);
                $slide['plugin_name'] = $knownPlugin ? $knownPlugin->getName() : 'missing-plugin';
                $slide['plugin_rendered_html'] = '<div class="plugin-slide-error">' . __('frontend.plugin_missing', ['slide_type' => e((string)$slide['slide_type'])]) . '</div>';
                $slide['plugin_state'] = ['missing_plugin' => true, 'slide_type' => (string)$slide['slide_type']];
            }

            $resolved[] = $slide;
        }

        return [$resolved, $assets];
    }

    private function buildDisplayState(array $display, array $activeAssignment, array $resolvedSlides, string $effect, int $duration): array
    {
        $payload = [
            'display_id' => (int)$display['id'],
            'display_slug' => (string)$display['slug'],
            'display_updated_at' => (string)($display['updated_at'] ?? ''),
            'channel_id' => (int)$activeAssignment['channel_id'],
            'channel_name' => (string)$activeAssignment['channel_name'],
            'channel_updated_at' => (string)($activeAssignment['updated_at'] ?? ''),
            'assignment_id' => (int)$activeAssignment['id'],
            'assignment_default' => (int)$activeAssignment['is_default'],
            'assignment_sort_order' => (int)$activeAssignment['sort_order'],
            'effect' => $effect,
            'duration' => $duration,
            'orientation' => (string)($display['orientation'] ?? 'landscape'),
            'slides' => array_map(static function (array $slide): array {
                return [
                    'id' => (int)$slide['id'],
                    'name' => (string)$slide['name'],
                    'slide_type' => (string)$slide['slide_type'],
                    'source_mode' => (string)$slide['source_mode'],
                    'resolved_source_url' => (string)$slide['resolved_source_url'],
                    'resolved_duration' => (int)$slide['resolved_duration'],
                    'updated_at' => (string)($slide['updated_at'] ?? ''),
                    'title_position' => (string)($slide['title_position'] ?? ''),
                    'plugin_name' => (string)($slide['plugin_name'] ?? ''),
                    'plugin_state' => $slide['plugin_state'] ?? null,
                ];
            }, $resolvedSlides),
        ];

        $payload['signature'] = sha1(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
        $payload['ok'] = true;
        return $payload;
    }

    private function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function limitString(mixed $value, int $maxLength): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }

        return substr($text, 0, $maxLength);
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (int)$value;
        return $number >= 0 ? $number : null;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }

    private function isBuiltInSlideType(string $slideType): bool
    {
        return in_array($slideType, ['image', 'video', 'website'], true);
    }

    private function normalizeJson(array $payload): ?string
    {
        if ($payload === []) {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : null;
    }

    public function resolveActiveAssignment(array $display): ?array
    {
        $timezone = new DateTimeZone($display['timezone'] ?: 'UTC');
        $now = new DateTime('now', $timezone);
        $weekday = (int)$now->format('w');
        $currentTime = $now->format('H:i:s');

        $activeAssignment = $this->db->one(
            'SELECT dca.*, c.name AS channel_name, c.description AS channel_description, c.transition_effect, c.slide_duration_seconds, c.is_active AS channel_is_active
             FROM display_channel_assignments dca
             INNER JOIN channels c ON c.id = dca.channel_id
             INNER JOIN display_channel_schedules dcs ON dcs.display_channel_assignment_id = dca.id
             WHERE dca.display_id = ?
               AND dca.is_default = 0
               AND c.is_active = 1
               AND dcs.is_enabled = 1
               AND dcs.weekday = ?
               AND ? BETWEEN dcs.start_time AND dcs.end_time
             ORDER BY dca.sort_order ASC, dca.id ASC
             LIMIT 1',
            [$display['id'], $weekday, $currentTime]
        );

        if ($activeAssignment) {
            return $activeAssignment;
        }

        return $this->db->one(
            'SELECT dca.*, c.name AS channel_name, c.description AS channel_description, c.transition_effect, c.slide_duration_seconds, c.is_active AS channel_is_active
             FROM display_channel_assignments dca
             INNER JOIN channels c ON c.id = dca.channel_id
             WHERE dca.display_id = ?
               AND dca.is_default = 1
               AND c.is_active = 1
             ORDER BY dca.sort_order ASC, dca.id ASC
             LIMIT 1',
            [$display['id']]
        );
    }
}
