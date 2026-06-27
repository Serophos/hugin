<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\PluginManager;
use App\Core\TemplateSlideService;
use App\Core\View;
use DateTime;
use DateTimeZone;

class FrontendController
{
    private TemplateSlideService $templateSlides;

    public function __construct(private Database $db, private View $view, private PluginManager $plugins)
    {
        $this->templateSlides = new TemplateSlideService($db);
    }

    public function display(string $slug): void
    {
        $display = $this->db->one('SELECT * FROM displays WHERE slug = ? AND is_active = 1', [$slug]);

        if (!$display) {
            http_response_code(404);
            echo __('frontend.display_not_found');
            return;
        }

        $displayGroup = $this->loadDisplayGroup((int)$display['id']);
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
        $state = $this->buildDisplayState($display, $activeAssignment, $resolvedSlides, $effect, $duration, $displayGroup, $pluginAssets);
        $brandingSettings = $this->loadBrandingSettings();

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
            'serverTimeMs' => $state['server_time_ms'],
            'displayGroup' => $displayGroup,
            'orientation' => $display['orientation'] ?? 'landscape',
            'pluginAssets' => $pluginAssets,
            'brandingSettings' => $brandingSettings,
        ]);
    }

    public function previewSlide(int $slideId): void
    {
        $slide = $this->db->one(
            'SELECT s.*, m.file_path AS media_file_path, bg.file_path AS background_media_file_path, bg.media_kind AS background_media_kind,
                    std.template_id, std.values_json AS template_values_json,
                    t.name AS template_name, t.landscape_spec_json AS template_landscape_spec_json, t.portrait_spec_json AS template_portrait_spec_json, t.updated_at AS template_updated_at
             FROM slides s
             LEFT JOIN media_assets m ON m.id = s.media_asset_id
             LEFT JOIN media_assets bg ON bg.id = s.background_media_asset_id
             LEFT JOIN slide_template_data std ON std.slide_id = s.id
             LEFT JOIN slide_templates t ON t.id = std.template_id
             WHERE s.id = ?',
            [$slideId]
        );

        if (!$slide) {
            http_response_code(404);
            echo __('slide.not_found');
            return;
        }

        $display = [
            'id' => 0,
            'slug' => 'preview-slide-' . $slideId,
            'name' => __('common.preview'),
            'orientation' => 'landscape',
            'transition_effect' => 'none',
            'slide_duration_seconds' => 8,
            'updated_at' => '',
        ];

        $activeAssignment = [
            'id' => 0,
            'channel_id' => 0,
            'channel_name' => __('common.preview'),
            'channel_description' => '',
            'transition_effect' => 'inherit',
            'slide_duration_seconds' => 0,
            'schedule_type' => 'fulltime',
            'schedule_id' => 0,
            'schedule_name' => '',
            'schedule_updated_at' => '',
            'schedule_rule_id' => 0,
            'schedule_rule_weekday' => 0,
            'schedule_rule_start_time' => '',
            'schedule_rule_end_time' => '',
            'sort_order' => 0,
            'assignment_created_at' => '',
            'channel_updated_at' => '',
        ];

        $duration = (int)(($slide['duration_seconds'] ?? 0) ?: $display['slide_duration_seconds']);
        $heartbeat = null;
        [$resolvedSlides, $pluginAssets] = $this->resolveSlides([$slide], $display, $activeAssignment, $heartbeat, $duration);
        $state = $this->buildDisplayState($display, $activeAssignment, $resolvedSlides, 'none', $duration, null, $pluginAssets);
        $brandingSettings = $this->loadBrandingSettings();

        $this->view->render('frontend/display', [
            'display' => $display,
            'channel' => [
                'id' => $activeAssignment['channel_id'],
                'name' => $activeAssignment['channel_name'],
                'description' => $activeAssignment['channel_description'],
            ],
            'slides' => $resolvedSlides,
            'effect' => 'none',
            'duration' => $duration,
            'stateSignature' => $state['signature'],
            'serverTimeMs' => $state['server_time_ms'],
            'orientation' => $display['orientation'],
            'pluginAssets' => $pluginAssets,
            'brandingSettings' => $brandingSettings,
        ]);
    }

    public function previewState(int $slideId): void
    {
        $slide = $this->db->one(
            'SELECT s.*, m.file_path AS media_file_path, bg.file_path AS background_media_file_path, bg.media_kind AS background_media_kind,
                    std.template_id, std.values_json AS template_values_json,
                    t.name AS template_name, t.landscape_spec_json AS template_landscape_spec_json, t.portrait_spec_json AS template_portrait_spec_json, t.updated_at AS template_updated_at
             FROM slides s
             LEFT JOIN media_assets m ON m.id = s.media_asset_id
             LEFT JOIN media_assets bg ON bg.id = s.background_media_asset_id
             LEFT JOIN slide_template_data std ON std.slide_id = s.id
             LEFT JOIN slide_templates t ON t.id = std.template_id
             WHERE s.id = ?',
            [$slideId]
        );

        if (!$slide) {
            json_response(['ok' => false, 'message' => __('slide.not_found')], 404);
        }

        $display = [
            'id' => 0,
            'slug' => 'preview-slide-' . $slideId,
            'name' => __('common.preview'),
            'orientation' => 'landscape',
            'transition_effect' => 'none',
            'slide_duration_seconds' => 8,
            'updated_at' => '',
        ];

        $activeAssignment = [
            'id' => 0,
            'channel_id' => 0,
            'channel_name' => __('common.preview'),
            'channel_description' => '',
            'transition_effect' => 'inherit',
            'slide_duration_seconds' => 0,
            'schedule_type' => 'fulltime',
            'schedule_id' => 0,
            'schedule_name' => '',
            'schedule_updated_at' => '',
            'schedule_rule_id' => 0,
            'schedule_rule_weekday' => 0,
            'schedule_rule_start_time' => '',
            'schedule_rule_end_time' => '',
            'sort_order' => 0,
            'assignment_created_at' => '',
            'channel_updated_at' => '',
        ];

        $duration = (int)(($slide['duration_seconds'] ?? 0) ?: $display['slide_duration_seconds']);
        $heartbeat = null;
        [$resolvedSlides, $pluginAssets] = $this->resolveSlides([$slide], $display, $activeAssignment, $heartbeat, $duration);

        json_response($this->buildDisplayState($display, $activeAssignment, $resolvedSlides, 'none', $duration, null, $pluginAssets));
    }

    public function previewHeartbeat(int $slideId): void
    {
        $slide = $this->db->one('SELECT id FROM slides WHERE id = ?', [$slideId]);
        if (!$slide) {
            json_response(['ok' => false, 'message' => __('slide.not_found')], 404);
        }

        json_response([
            'ok' => true,
            'display' => __('common.preview'),
            'channel' => __('common.preview'),
            'seen_at' => date('c'),
        ]);
    }

    public function mediaPreview(int $mediaId): void
    {
        $asset = $this->db->one('SELECT media_kind, preview_file_path FROM media_assets WHERE id = ?', [$mediaId]);
        $previewPath = (string)($asset['preview_file_path'] ?? '');
        if (!$asset || ($asset['media_kind'] ?? '') !== 'video' || $previewPath === '') {
            http_response_code(404);
            echo __('media.not_found');
            return;
        }

        $publicRoot = realpath((string)app_config('paths.public', dirname(__DIR__, 2) . '/public'));
        $filePath = realpath((string)app_config('paths.public', dirname(__DIR__, 2) . '/public') . '/' . ltrim($previewPath, '/'));
        if ($publicRoot === false || $filePath === false || !str_starts_with($filePath, $publicRoot . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
            http_response_code(404);
            echo __('media.not_found');
            return;
        }

        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Content-Length: ' . (string)filesize($filePath));
        readfile($filePath);
    }

    public function state(string $slug): void
    {
        $display = $this->db->one('SELECT * FROM displays WHERE slug = ? AND is_active = 1', [$slug]);
        if (!$display) {
            json_response(['ok' => false, 'message' => __('frontend.state_message_display_not_found')], 404);
        }

        $displayGroup = $this->loadDisplayGroup((int)$display['id']);
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
        [$resolvedSlides, $pluginAssets] = $this->resolveSlides($slides, $display, $activeAssignment, $heartbeat, $duration);

        json_response($this->buildDisplayState($display, $activeAssignment, $resolvedSlides, $effect, $duration, $displayGroup, $pluginAssets));
    }

    public function offlineManifest(string $slug): void
    {
        $display = $this->db->one('SELECT * FROM displays WHERE slug = ? AND is_active = 1', [$slug]);
        if (!$display) {
            json_response(['ok' => false, 'message' => __('frontend.state_message_display_not_found')], 404);
        }

        $displayGroup = $this->loadDisplayGroup((int)$display['id']);
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
        [$resolvedSlides, $pluginAssets] = $this->resolveSlides($slides, $display, $activeAssignment, $heartbeat, $duration);
        $state = $this->buildDisplayState($display, $activeAssignment, $resolvedSlides, $effect, $duration, $displayGroup, $pluginAssets);
        $brandingSettings = $this->loadBrandingSettings();

        json_response($this->buildOfflineManifest($display, $resolvedSlides, $pluginAssets, $state, $brandingSettings));
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

    public function cacheReadiness(string $slug): void
    {
        $display = $this->db->one('SELECT * FROM displays WHERE slug = ? AND is_active = 1', [$slug]);
        if (!$display) {
            json_response(['ok' => false, 'message' => __('frontend.state_message_display_not_found')], 404);
        }

        $displayGroup = $this->loadDisplayGroup((int)$display['id']);
        $context = $this->currentDisplayStateContext($display, $displayGroup);
        if (!$context) {
            json_response(['ok' => false, 'message' => __('frontend.state_message_no_channel')], 409);
        }

        $payload = $this->readJsonBody();
        $state = $context['state'];
        $payloadStateSignature = $this->signatureFromPayload($payload['state_signature'] ?? null, (string)$state['signature']);
        $manifestSignature = $this->signatureFromPayload($payload['manifest_signature'] ?? null, $payloadStateSignature);
        $cacheStatus = $this->cacheStatusFromPayload($payload['cache_status'] ?? null);
        $accepted = hash_equals((string)$state['signature'], $payloadStateSignature);

        $this->storeCacheReadiness(
            (int)$display['id'],
            $displayGroup ? (int)$displayGroup['id'] : null,
            $payloadStateSignature,
            $manifestSignature,
            $cacheStatus,
            (string)($this->limitString($payload['reason'] ?? 'startup', 50) ?? 'startup'),
            $this->nonNegativeInt($payload['total_assets'] ?? 0),
            $this->nonNegativeInt($payload['cached_assets'] ?? 0),
            $this->nonNegativeInt($payload['skipped_assets'] ?? 0),
            $this->nonNegativeInt($payload['bytes_reserved'] ?? 0),
            $this->normalizeJson($payload)
        );

        $response = $this->cacheReadinessResponse($display, $displayGroup, $state);
        $response['accepted'] = $accepted;
        json_response($response);
    }

    public function cacheReadinessStatus(string $slug): void
    {
        $display = $this->db->one('SELECT * FROM displays WHERE slug = ? AND is_active = 1', [$slug]);
        if (!$display) {
            json_response(['ok' => false, 'message' => __('frontend.state_message_display_not_found')], 404);
        }

        $displayGroup = $this->loadDisplayGroup((int)$display['id']);
        $context = $this->currentDisplayStateContext($display, $displayGroup);
        if (!$context) {
            json_response(['ok' => false, 'message' => __('frontend.state_message_no_channel')], 409);
        }

        json_response($this->cacheReadinessResponse($display, $displayGroup, $context['state']));
    }

    private function currentDisplayStateContext(array $display, ?array $displayGroup): ?array
    {
        $activeAssignment = $this->resolveActiveAssignment($display);
        if (!$activeAssignment) {
            return null;
        }

        $slides = $this->loadChannelSlides((int)$activeAssignment['channel_id']);
        if (!$slides) {
            return null;
        }

        $effect = $activeAssignment['transition_effect'] !== 'inherit'
            ? $activeAssignment['transition_effect']
            : $display['transition_effect'];

        $duration = (int)($activeAssignment['slide_duration_seconds'] ?: $display['slide_duration_seconds']);
        $heartbeat = $this->db->one('SELECT * FROM display_heartbeats WHERE display_id = ?', [$display['id']]);
        [$resolvedSlides, $pluginAssets] = $this->resolveSlides($slides, $display, $activeAssignment, $heartbeat, $duration);

        return [
            'active_assignment' => $activeAssignment,
            'resolved_slides' => $resolvedSlides,
            'plugin_assets' => $pluginAssets,
            'state' => $this->buildDisplayState($display, $activeAssignment, $resolvedSlides, $effect, $duration, $displayGroup, $pluginAssets),
        ];
    }

    private function storeCacheReadiness(
        int $displayId,
        ?int $displayGroupId,
        string $stateSignature,
        string $manifestSignature,
        string $cacheStatus,
        string $reason,
        int $totalAssets,
        int $cachedAssets,
        int $skippedAssets,
        int $bytesReserved,
        ?string $payloadJson
    ): void {
        $this->db->execute(
            'INSERT INTO display_cache_readiness (
                display_id, display_group_id, state_signature, manifest_signature, cache_status, reason,
                total_assets, cached_assets, skipped_assets, bytes_reserved, client_payload_json, ready_at
            )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                display_group_id = VALUES(display_group_id),
                manifest_signature = VALUES(manifest_signature),
                cache_status = VALUES(cache_status),
                reason = VALUES(reason),
                total_assets = VALUES(total_assets),
                cached_assets = VALUES(cached_assets),
                skipped_assets = VALUES(skipped_assets),
                bytes_reserved = VALUES(bytes_reserved),
                client_payload_json = VALUES(client_payload_json),
                ready_at = NOW()',
            [
                $displayId,
                $displayGroupId,
                $stateSignature,
                $manifestSignature,
                $cacheStatus,
                $reason,
                $totalAssets,
                $cachedAssets,
                $skippedAssets,
                $bytesReserved,
                $payloadJson,
            ]
        );
    }

    private function cacheReadinessResponse(array $display, ?array $displayGroup, array $state): array
    {
        $response = [
            'ok' => true,
            'server_time_ms' => (int)floor(microtime(true) * 1000),
            'state_signature' => (string)$state['signature'],
            'sync_enabled' => false,
            'released' => true,
            'participant_count' => 1,
            'ready_count' => 1,
            'pending_count' => 0,
            'current_display_ready' => true,
            'generation_hash' => '',
            'start_at_ms' => 0,
        ];

        if (!$displayGroup || empty($displayGroup['sync_reload_to_full_minute'])) {
            return $response;
        }

        return array_replace(
            $response,
            $this->groupCacheReadinessResponse((int)$displayGroup['id'], (int)$display['id'], $displayGroup)
        );
    }

    private function groupCacheReadinessResponse(int $groupId, int $currentDisplayId, array $displayGroup): array
    {
        $participants = $this->onlineGroupParticipants($groupId, $currentDisplayId, $displayGroup);
        if (!$participants) {
            return [
                'sync_enabled' => true,
                'released' => true,
                'participant_count' => 0,
                'ready_count' => 0,
                'pending_count' => 0,
                'current_display_ready' => false,
                'generation_hash' => '',
                'start_at_ms' => 0,
            ];
        }

        $generationPayload = array_map(
            static fn(array $participant): array => [
                'display_id' => (int)$participant['display_id'],
                'state_signature' => (string)$participant['state_signature'],
            ],
            $participants
        );
        $generationHash = sha1(json_encode($generationPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
        $readinessRows = $this->cacheReadinessRows($participants);
        $readyCount = 0;
        $currentDisplayReady = false;

        foreach ($participants as $participant) {
            $key = $this->readinessKey((int)$participant['display_id'], (string)$participant['state_signature']);
            $row = $readinessRows[$key] ?? null;
            $isReady = $row && in_array((string)$row['cache_status'], ['ready', 'degraded'], true);
            if ($isReady) {
                $readyCount++;
            }
            if ((int)$participant['display_id'] === $currentDisplayId) {
                $currentDisplayReady = $isReady;
            }
        }

        $participantCount = count($participants);
        $release = $this->syncRelease($groupId, $generationHash);
        if (!$release && $readyCount >= $participantCount) {
            $release = $this->releaseSyncGeneration($groupId, $generationHash, $participantCount, $readyCount);
        } elseif (!$release) {
            $this->storeSyncGenerationProgress($groupId, $generationHash, $participantCount, $readyCount);
        }

        $startAtMs = $release ? $this->syncReleaseStartAtMs($release) : 0;

        return [
            'sync_enabled' => true,
            'released' => $startAtMs > 0,
            'participant_count' => $participantCount,
            'ready_count' => $readyCount,
            'pending_count' => max(0, $participantCount - $readyCount),
            'current_display_ready' => $currentDisplayReady,
            'generation_hash' => $generationHash,
            'start_at_ms' => $startAtMs,
        ];
    }

    private function onlineGroupParticipants(int $groupId, int $currentDisplayId, array $displayGroup): array
    {
        $onlineThresholdSeconds = max(30, (int)app_core_setting('monitoring.online_threshold_seconds', 180));
        $rows = $this->db->all(
            'SELECT d.*, TIMESTAMPDIFF(SECOND, h.last_seen_at, NOW()) AS heartbeat_age_seconds
             FROM display_group_memberships dgm
             INNER JOIN displays d ON d.id = dgm.display_id
             LEFT JOIN display_heartbeats h ON h.display_id = d.id
             WHERE dgm.group_id = ?
               AND d.is_active = 1
               AND (
                    d.id = ?
                    OR (h.last_seen_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, h.last_seen_at, NOW()) <= ?)
               )
             ORDER BY dgm.sort_order ASC, d.sort_order ASC, d.id ASC',
            [$groupId, $currentDisplayId, $onlineThresholdSeconds]
        );

        $participants = [];
        foreach ($rows as $row) {
            $context = $this->currentDisplayStateContext($row, $displayGroup);
            if (!$context) {
                continue;
            }
            $participants[] = [
                'display_id' => (int)$row['id'],
                'state_signature' => (string)$context['state']['signature'],
            ];
        }

        return $participants;
    }

    private function cacheReadinessRows(array $participants): array
    {
        $displayIds = array_values(array_unique(array_map(static fn(array $participant): int => (int)$participant['display_id'], $participants)));
        if (!$displayIds) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($displayIds), '?'));
        $rows = $this->db->all(
            'SELECT display_id, state_signature, cache_status, ready_at
             FROM display_cache_readiness
             WHERE display_id IN (' . $placeholders . ')',
            $displayIds
        );

        $readiness = [];
        foreach ($rows as $row) {
            $readiness[$this->readinessKey((int)$row['display_id'], (string)$row['state_signature'])] = $row;
        }

        return $readiness;
    }

    private function syncRelease(int $groupId, string $generationHash): ?array
    {
        return $this->db->one(
            'SELECT *
             FROM display_sync_releases
             WHERE display_group_id = ?
               AND generation_hash = ?
               AND start_at IS NOT NULL
               AND released_at >= (NOW() - INTERVAL 5 MINUTE)
             LIMIT 1',
            [$groupId, $generationHash]
        );
    }

    private function storeSyncGenerationProgress(int $groupId, string $generationHash, int $participantCount, int $readyCount): void
    {
        $this->db->execute(
            'INSERT INTO display_sync_releases (display_group_id, generation_hash, participant_count, ready_count)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                participant_count = VALUES(participant_count),
                ready_count = VALUES(ready_count),
                start_at = IF(released_at IS NOT NULL AND released_at < (NOW() - INTERVAL 5 MINUTE), NULL, start_at),
                released_at = IF(released_at IS NOT NULL AND released_at < (NOW() - INTERVAL 5 MINUTE), NULL, released_at)',
            [$groupId, $generationHash, $participantCount, $readyCount]
        );
    }

    private function releaseSyncGeneration(int $groupId, string $generationHash, int $participantCount, int $readyCount): ?array
    {
        $startAt = date('Y-m-d H:i:s', $this->nextFullMinuteTimestamp(3));
        $this->db->execute(
            'INSERT INTO display_sync_releases (
                display_group_id, generation_hash, participant_count, ready_count, start_at, released_at
            )
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                participant_count = VALUES(participant_count),
                ready_count = VALUES(ready_count),
                start_at = IF(released_at IS NULL OR released_at < (NOW() - INTERVAL 5 MINUTE), VALUES(start_at), start_at),
                released_at = IF(released_at IS NULL OR released_at < (NOW() - INTERVAL 5 MINUTE), VALUES(released_at), released_at)',
            [$groupId, $generationHash, $participantCount, $readyCount, $startAt]
        );

        return $this->syncRelease($groupId, $generationHash);
    }

    private function syncReleaseStartAtMs(array $release): int
    {
        $timestamp = strtotime((string)($release['start_at'] ?? ''));
        return $timestamp === false ? 0 : $timestamp * 1000;
    }

    private function nextFullMinuteTimestamp(int $minLeadSeconds): int
    {
        $now = microtime(true);
        $next = (int)(ceil($now / 60) * 60);
        if (($next - $now) < max(0, $minLeadSeconds)) {
            $next += 60;
        }

        return $next;
    }

    private function readinessKey(int $displayId, string $stateSignature): string
    {
        return $displayId . ':' . $stateSignature;
    }

    private function signatureFromPayload(mixed $value, string $fallback): string
    {
        if (!is_scalar($value)) {
            return $fallback;
        }

        $signature = strtolower(trim((string)$value));
        return preg_match('/\A[a-f0-9]{40}\z/', $signature) ? $signature : $fallback;
    }

    private function cacheStatusFromPayload(mixed $value): string
    {
        $status = is_scalar($value) ? strtolower(trim((string)$value)) : '';
        return in_array($status, ['ready', 'degraded'], true) ? $status : 'degraded';
    }

    private function nonNegativeInt(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        return max(0, (int)$value);
    }

    private function loadChannelSlides(int $channelId): array
    {
        return $this->db->all(
            'SELECT s.*,
                    csa.sort_order AS assignment_sort_order,
                    csa.created_at AS assignment_created_at,
                    m.file_path AS media_file_path,
                    m.file_size AS media_file_size,
                    m.mime_type AS media_mime_type,
                    m.media_kind AS media_kind,
                    m.created_at AS media_created_at,
                    bg.file_path AS background_media_file_path,
                    bg.file_size AS background_media_file_size,
                    bg.mime_type AS background_media_mime_type,
                    bg.media_kind AS background_media_kind,
                    bg.created_at AS background_media_created_at,
                    std.template_id, std.values_json AS template_values_json,
                    t.name AS template_name, t.landscape_spec_json AS template_landscape_spec_json, t.portrait_spec_json AS template_portrait_spec_json, t.updated_at AS template_updated_at
             FROM channel_slide_assignments csa
             INNER JOIN slides s ON s.id = csa.slide_id
             LEFT JOIN media_assets m ON m.id = s.media_asset_id
             LEFT JOIN media_assets bg ON bg.id = s.background_media_asset_id
             LEFT JOIN slide_template_data std ON std.slide_id = s.id
             LEFT JOIN slide_templates t ON t.id = std.template_id
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
            $slide['text_rendered_html'] = null;
            $slide['template_rendered_html'] = null;
            $slide['text_background_url'] = $slide['background_media_file_path'] ?? null;
            $slide['text_background_kind'] = in_array(($slide['background_media_kind'] ?? ''), ['image', 'video'], true) ? (string)$slide['background_media_kind'] : '';
            $slide['resolved_background_color'] = normalize_hex_color((string)($slide['background_color'] ?? '#0f172a'), '#0f172a');
            $slide['resolved_text_color'] = normalize_css_rgba_color((string)($slide['text_color'] ?? ''), hex_to_rgba(readable_text_color($slide['resolved_background_color'])));
            $slide['resolved_overlay_color'] = normalize_css_rgba_color((string)($slide['text_box_background_color'] ?? ''), readable_overlay_rgba($slide['resolved_background_color']));
            $slide['resolved_text_box_layout'] = normalize_text_slide_layout((string)($slide['text_box_layout'] ?? ''));
            $slide['resolved_text_box_animation'] = normalize_text_slide_animation((string)($slide['text_box_animation'] ?? ''));
            $slide['resolved_text_box_animation_duration_ms'] = normalize_text_slide_animation_duration_ms($slide['text_box_animation_duration_ms'] ?? '');
            $slide['resolved_text_box_animation_delay_ms'] = normalize_text_slide_animation_delay_ms($slide['text_box_animation_delay_ms'] ?? '');
            $slide['resolved_text_box_blur_enabled'] = (int)($slide['text_box_blur_enabled'] ?? 1) === 1;
            $slide['resolved_text_box_width_percent'] = normalize_text_slide_box_width_percent($slide['text_box_width_percent'] ?? '');
            $slide['resolved_text_box_radius_top_left_rem'] = normalize_text_slide_radius_rem($slide['text_box_radius_top_left_rem'] ?? '');
            $slide['resolved_text_box_radius_top_right_rem'] = normalize_text_slide_radius_rem($slide['text_box_radius_top_right_rem'] ?? '');
            $slide['resolved_text_box_radius_bottom_right_rem'] = normalize_text_slide_radius_rem($slide['text_box_radius_bottom_right_rem'] ?? '');
            $slide['resolved_text_box_radius_bottom_left_rem'] = normalize_text_slide_radius_rem($slide['text_box_radius_bottom_left_rem'] ?? '');
            $slide['resolved_qr_foreground_color'] = normalize_css_rgba_color((string)($slide['qr_foreground_color'] ?? ''), 'rgba(15, 23, 42, 1)');
            $slide['resolved_qr_background_color'] = normalize_css_rgba_color((string)($slide['qr_background_color'] ?? ''), 'rgba(255, 255, 255, 1)');
            $slide['resolved_qr_position'] = normalize_text_slide_qr_position((string)($slide['qr_position'] ?? ''));
            $slide['resolved_qr_size_percent'] = normalize_text_slide_qr_size_percent($slide['qr_size_percent'] ?? '');
            $slide['resolved_qr_animation_enabled'] = (int)($slide['qr_animation_enabled'] ?? 0) === 1;
            $slide['resolved_qr_radius_top_left_rem'] = normalize_text_slide_radius_rem($slide['qr_radius_top_left_rem'] ?? '');
            $slide['resolved_qr_radius_top_right_rem'] = normalize_text_slide_radius_rem($slide['qr_radius_top_right_rem'] ?? '');
            $slide['resolved_qr_radius_bottom_right_rem'] = normalize_text_slide_radius_rem($slide['qr_radius_bottom_right_rem'] ?? '');
            $slide['resolved_qr_radius_bottom_left_rem'] = normalize_text_slide_radius_rem($slide['qr_radius_bottom_left_rem'] ?? '');
            $slide['resolved_qr_url'] = '';

            if (($slide['slide_type'] ?? '') === 'text') {
                $slide['text_rendered_html'] = render_markup((string)($slide['text_markup'] ?? ''));
                $slide['resolved_qr_url'] = trim((string)($slide['source_url'] ?? ''));
                $slide['resolved_source_url'] = '';
            }
            if (($slide['slide_type'] ?? '') === 'template' && !empty($slide['template_id'])) {
                $template = [
                    'id' => (int)$slide['template_id'],
                    'name' => (string)($slide['template_name'] ?? ''),
                    'landscape_spec_json' => (string)($slide['template_landscape_spec_json'] ?? ''),
                    'portrait_spec_json' => (string)($slide['template_portrait_spec_json'] ?? ''),
                ];
                $values = $this->templateSlides->decodeValues((string)($slide['template_values_json'] ?? ''));
                $slide['template_font_asset_ids'] = $this->templateSlides->fontAssetIdsForTemplate($template);
                $slide['template_rendered_html'] = $this->templateSlides->render($template, $values, (string)($display['orientation'] ?? 'landscape'));
                $slide['resolved_source_url'] = '';
            }
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

    private function buildOfflineManifest(array $display, array $resolvedSlides, array $pluginAssets, array $state, array $brandingSettings): array
    {
        $assets = [];
        $slideManifests = [];

        $this->addManifestAsset($assets, url('/display/' . $display['slug']), 'shell', 'document', null, true);
        $this->addManifestAsset($assets, url('/display/' . $display['slug'] . '/offline-manifest'), 'manifest', 'json', null, true);
        $this->addManifestAsset($assets, asset_url('/assets/css/display.css'), 'static', 'style', $this->publicFileSize('/assets/css/display.css'), true);
        $this->addManifestAsset($assets, asset_url('/assets/js/hugin-qr.js'), 'static', 'script', $this->publicFileSize('/assets/js/hugin-qr.js'), true);
        $this->addManifestAsset($assets, asset_url('/assets/js/slideshow.js'), 'static', 'script', $this->publicFileSize('/assets/js/slideshow.js'), true);
        $this->addManifestAsset($assets, asset_url('/display-service-worker.js'), 'static', 'script', $this->publicFileSize('/display-service-worker.js'), true);
        $this->addManifestAsset($assets, url('/assets/img/hugin-logo.webp'), 'static', 'image', $this->publicFileSize('/assets/img/hugin-logo.webp'), true);

        foreach (($pluginAssets['css'] ?? []) as $asset) {
            $this->addManifestAsset($assets, (string)$asset, 'plugin', 'style', $this->publicFileSizeFromUrl((string)$asset), true);
        }
        foreach (($pluginAssets['js'] ?? []) as $asset) {
            $this->addManifestAsset($assets, (string)$asset, 'plugin', 'script', $this->publicFileSizeFromUrl((string)$asset), true);
        }

        $fontAssetIds = array_values(array_unique(array_merge(
            uploaded_font_ids_from_tokens([
                (string)($brandingSettings['default_font_heading'] ?? ''),
                (string)($brandingSettings['default_font_text'] ?? ''),
            ]),
            $this->templateFontAssetIdsForSlides($resolvedSlides)
        )));
        foreach (list_uploaded_fonts($fontAssetIds) as $font) {
            $this->addManifestAsset($assets, asset_url((string)$font['file_path']), 'font', 'font', $this->publicFileSize((string)$font['file_path']), false);
        }

        foreach ($resolvedSlides as $slide) {
            $slideAssets = [];
            $policy = $this->offlinePolicyForSlide($slide);

            if (in_array((string)$slide['slide_type'], ['image', 'video'], true)) {
                $this->addSlideAsset($assets, $slideAssets, url((string)$slide['resolved_source_url']), 'media', (string)$slide['slide_type'], (int)($slide['media_file_size'] ?? 0), true);
            }

            if ((string)$slide['slide_type'] === 'text' && !empty($slide['text_background_url'])) {
                $this->addSlideAsset(
                    $assets,
                    $slideAssets,
                    url((string)$slide['text_background_url']),
                    'media',
                    (string)($slide['text_background_kind'] ?? 'image'),
                    (int)($slide['background_media_file_size'] ?? 0),
                    true
                );
            }

            if (is_string($slide['plugin_rendered_html'] ?? null) && $slide['plugin_rendered_html'] !== '') {
                foreach ($this->discoverSameOriginAssets((string)$slide['plugin_rendered_html']) as $assetUrl) {
                    $this->addSlideAsset($assets, $slideAssets, $assetUrl, 'plugin-rendered', $this->assetKindFromUrl($assetUrl), $this->publicFileSizeFromUrl($assetUrl), false);
                }
            }

            if (is_string($slide['template_rendered_html'] ?? null) && $slide['template_rendered_html'] !== '') {
                foreach ($this->discoverSameOriginAssets((string)$slide['template_rendered_html']) as $assetUrl) {
                    $this->addSlideAsset($assets, $slideAssets, $assetUrl, 'template-rendered', $this->assetKindFromUrl($assetUrl), $this->publicFileSizeFromUrl($assetUrl), true);
                }
            }

            $slideManifests[] = [
                'id' => (int)$slide['id'],
                'type' => (string)$slide['slide_type'],
                'policy' => $policy,
                'required_asset_urls' => array_values(array_filter(array_map(
                    static fn(array $asset): string => !empty($asset['required']) ? (string)$asset['url'] : '',
                    $slideAssets
                ))),
                'asset_urls' => array_values(array_map(static fn(array $asset): string => (string)$asset['url'], $slideAssets)),
            ];
        }

        return [
            'ok' => true,
            'version' => 1,
            'generated_at' => date('c'),
            'signature' => (string)$state['signature'],
            'display_slug' => (string)$display['slug'],
            'shell_url' => url('/display/' . $display['slug']),
            'state_url' => url('/display/' . $display['slug'] . '/state'),
            'assets' => array_values($assets),
            'slides' => $slideManifests,
        ];
    }

    private function offlinePolicyForSlide(array $slide): string
    {
        $slideType = (string)($slide['slide_type'] ?? '');
        if ($slideType === 'website') {
            return 'skip';
        }
        if (is_string($slide['plugin_rendered_html'] ?? null) && $slide['plugin_rendered_html'] !== '') {
            return 'try';
        }
        if (in_array($slideType, ['image', 'video', 'text', 'template'], true)) {
            return 'play';
        }
        return 'skip';
    }

    private function addSlideAsset(array &$assets, array &$slideAssets, string $url, string $type, string $kind, ?int $size, bool $required): void
    {
        if (!$this->isSameOriginUrl($url)) {
            return;
        }

        $asset = $this->addManifestAsset($assets, $url, $type, $kind, $size, $required);
        if ($asset !== null) {
            $slideAssets[$asset['url']] = $asset;
        }
    }

    private function addManifestAsset(array &$assets, string $url, string $type, string $kind, ?int $size, bool $required): ?array
    {
        if (!$this->isSameOriginUrl($url)) {
            return null;
        }

        $normalized = $this->normalizeManifestUrl($url);
        if ($normalized === '') {
            return null;
        }

        if (isset($assets[$normalized])) {
            $assets[$normalized]['required'] = $assets[$normalized]['required'] || $required;
            return $assets[$normalized];
        }

        $asset = [
            'url' => $normalized,
            'type' => $type,
            'kind' => $kind,
            'size' => max(0, (int)($size ?? 0)),
            'required' => $required,
        ];
        $assets[$normalized] = $asset;
        return $asset;
    }

    private function discoverSameOriginAssets(string $html): array
    {
        $urls = [];
        if (preg_match_all('/\b(?:src|href|data-src|data-bg-src)=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                if ($this->isSameOriginUrl($url)) {
                    $urls[$this->normalizeManifestUrl($url)] = true;
                }
            }
        }
        if (preg_match_all('/url\(["\']?([^"\')]+)["\']?\)/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                if ($this->isSameOriginUrl($url)) {
                    $urls[$this->normalizeManifestUrl($url)] = true;
                }
            }
        }

        return array_keys($urls);
    }

    private function normalizeManifestUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return url($url);
    }

    private function isSameOriginUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (!preg_match('#^https?://#i', $url)) {
            return str_starts_with($url, '/');
        }

        $base = parse_url(url('/'));
        $parts = parse_url($url);
        if (!$base || !$parts) {
            return false;
        }

        return strtolower((string)($base['scheme'] ?? '')) === strtolower((string)($parts['scheme'] ?? ''))
            && strtolower((string)($base['host'] ?? '')) === strtolower((string)($parts['host'] ?? ''))
            && (int)($base['port'] ?? 0) === (int)($parts['port'] ?? 0);
    }

    private function publicFileSize(string $publicPath): int
    {
        $publicRoot = realpath((string)app_config('paths.public', dirname(__DIR__, 2) . '/public'));
        $path = realpath((string)app_config('paths.public', dirname(__DIR__, 2) . '/public') . '/' . ltrim($publicPath, '/'));
        if ($path === false || $publicRoot === false || !str_starts_with($path, $publicRoot . DIRECTORY_SEPARATOR) || !is_file($path)) {
            return 0;
        }
        return (int)filesize($path);
    }

    private function publicFileSizeFromUrl(string $url): int
    {
        $parts = parse_url($url);
        $path = is_array($parts) ? (string)($parts['path'] ?? '') : '';
        return $path !== '' ? $this->publicFileSize($path) : 0;
    }

    private function assetKindFromUrl(string $url): string
    {
        $extension = strtolower(pathinfo((string)(parse_url($url, PHP_URL_PATH) ?: $url), PATHINFO_EXTENSION));
        return match ($extension) {
            'css' => 'style',
            'js' => 'script',
            'mp4', 'webm', 'ogv' => 'video',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' => 'image',
            'otf', 'ttf', 'woff', 'woff2' => 'font',
            default => 'asset',
        };
    }

    private function templateFontAssetIdsForSlides(array $resolvedSlides): array
    {
        $ids = [];
        foreach ($resolvedSlides as $slide) {
            foreach ((array)($slide['template_font_asset_ids'] ?? []) as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
        }

        return array_map('intval', array_keys($ids));
    }

    private function loadBrandingSettings(): array
    {
        $rows = $this->db->all('SELECT setting_key, setting_value FROM app_settings WHERE namespace = ?', ['branding']);

        $defaults = [
            'default_background_color' => '#0f172a',
            'default_text_color' => '#f8fafc',
            'default_font_heading' => '',
            'default_font_text' => '',
        ];

        if (!$rows) {
            return $defaults;
        }

        $settings = [];
        foreach ($rows as $row) {
            if (!is_string($row['setting_key']) || !array_key_exists('setting_value', $row)) {
                continue;
            }
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return array_replace($defaults, $settings);
    }

    private function buildDisplayState(array $display, array $activeAssignment, array $resolvedSlides, string $effect, int $duration, ?array $displayGroup = null, array $pluginAssets = []): array
    {
        $payload = [
            'display_id' => (int)$display['id'],
            'display_slug' => (string)$display['slug'],
            'display_updated_at' => (string)($display['updated_at'] ?? ''),
            'display_group' => $displayGroup,
            'channel_id' => (int)$activeAssignment['channel_id'],
            'channel_name' => (string)$activeAssignment['channel_name'],
            'channel_updated_at' => (string)($activeAssignment['channel_updated_at'] ?? ''),
            'assignment_id' => (int)$activeAssignment['id'],
            'assignment_created_at' => (string)($activeAssignment['assignment_created_at'] ?? ''),
            'assignment_default' => (int)(($activeAssignment['schedule_type'] ?? '') === 'fulltime'),
            'assignment_schedule_id' => (int)($activeAssignment['schedule_id'] ?? 0),
            'assignment_schedule_name' => (string)($activeAssignment['schedule_name'] ?? ''),
            'assignment_schedule_type' => (string)($activeAssignment['schedule_type'] ?? ''),
            'assignment_schedule_updated_at' => (string)($activeAssignment['schedule_updated_at'] ?? ''),
            'assignment_schedule_rule_id' => (int)($activeAssignment['schedule_rule_id'] ?? 0),
            'assignment_schedule_rule_weekday' => (int)($activeAssignment['schedule_rule_weekday'] ?? 0),
            'assignment_schedule_rule_start_time' => (string)($activeAssignment['schedule_rule_start_time'] ?? ''),
            'assignment_schedule_rule_end_time' => (string)($activeAssignment['schedule_rule_end_time'] ?? ''),
            'assignment_sort_order' => (int)$activeAssignment['sort_order'],
            'effect' => $effect,
            'duration' => $duration,
            'orientation' => (string)($display['orientation'] ?? 'landscape'),
            'frontend_assets' => $this->frontendAssetUrls($pluginAssets),
            'plugin_global_updated_at' => $this->collectPluginGlobalUpdatedAt($resolvedSlides),
            'slides' => array_map(static function (array $slide): array {
                return [
                    'id' => (int)$slide['id'],
                    'name' => (string)$slide['name'],
                    'slide_type' => (string)$slide['slide_type'],
                    'source_mode' => (string)$slide['source_mode'],
                    'media_asset_id' => (int)($slide['media_asset_id'] ?? 0),
                    'background_media_asset_id' => (int)($slide['background_media_asset_id'] ?? 0),
                    'resolved_source_url' => (string)$slide['resolved_source_url'],
                    'resolved_background_url' => (string)($slide['text_background_url'] ?? ''),
                    'background_media_kind' => (string)($slide['text_background_kind'] ?? ''),
                    'text_color' => (string)($slide['resolved_text_color'] ?? ''),
                    'text_box_background_color' => (string)($slide['resolved_overlay_color'] ?? ''),
                    'text_box_layout' => (string)($slide['resolved_text_box_layout'] ?? ''),
                    'text_box_animation' => (string)($slide['resolved_text_box_animation'] ?? ''),
                    'text_box_animation_duration_ms' => (int)($slide['resolved_text_box_animation_duration_ms'] ?? 0),
                    'text_box_animation_delay_ms' => (int)($slide['resolved_text_box_animation_delay_ms'] ?? 0),
                    'text_box_blur_enabled' => (int)(($slide['resolved_text_box_blur_enabled'] ?? true) ? 1 : 0),
                    'text_box_width_percent' => (int)($slide['resolved_text_box_width_percent'] ?? 0),
                    'text_box_radius_top_left_rem' => $slide['resolved_text_box_radius_top_left_rem'] ?? null,
                    'text_box_radius_top_right_rem' => $slide['resolved_text_box_radius_top_right_rem'] ?? null,
                    'text_box_radius_bottom_right_rem' => $slide['resolved_text_box_radius_bottom_right_rem'] ?? null,
                    'text_box_radius_bottom_left_rem' => $slide['resolved_text_box_radius_bottom_left_rem'] ?? null,
                    'qr_url' => (string)($slide['resolved_qr_url'] ?? ''),
                    'qr_foreground_color' => (string)($slide['resolved_qr_foreground_color'] ?? ''),
                    'qr_background_color' => (string)($slide['resolved_qr_background_color'] ?? ''),
                    'qr_position' => (string)($slide['resolved_qr_position'] ?? ''),
                    'qr_animation_enabled' => (int)(($slide['resolved_qr_animation_enabled'] ?? false) ? 1 : 0),
                    'qr_radius_top_left_rem' => $slide['resolved_qr_radius_top_left_rem'] ?? null,
                    'qr_radius_top_right_rem' => $slide['resolved_qr_radius_top_right_rem'] ?? null,
                    'qr_radius_bottom_right_rem' => $slide['resolved_qr_radius_bottom_right_rem'] ?? null,
                    'qr_radius_bottom_left_rem' => $slide['resolved_qr_radius_bottom_left_rem'] ?? null,
                    'resolved_duration' => (int)$slide['resolved_duration'],
                    'updated_at' => (string)($slide['updated_at'] ?? ''),
                    'assignment_sort_order' => (int)($slide['assignment_sort_order'] ?? 0),
                    'assignment_created_at' => (string)($slide['assignment_created_at'] ?? ''),
                    'media_created_at' => (string)($slide['media_created_at'] ?? ''),
                    'background_media_created_at' => (string)($slide['background_media_created_at'] ?? ''),
                    'title_position' => (string)($slide['title_position'] ?? ''),
                    'text_rendered_hash' => is_string($slide['text_rendered_html'] ?? null) ? sha1((string)$slide['text_rendered_html']) : '',
                    'template_id' => (int)($slide['template_id'] ?? 0),
                    'template_updated_at' => (string)($slide['template_updated_at'] ?? ''),
                    'template_font_asset_ids' => array_values(array_map('intval', (array)($slide['template_font_asset_ids'] ?? []))),
                    'template_values_hash' => is_string($slide['template_values_json'] ?? null) ? sha1((string)$slide['template_values_json']) : '',
                    'template_rendered_hash' => is_string($slide['template_rendered_html'] ?? null) ? sha1((string)$slide['template_rendered_html']) : '',
                    'plugin_name' => (string)($slide['plugin_name'] ?? ''),
                    'plugin_state' => $slide['plugin_state'] ?? null,
                ];
            }, $resolvedSlides),
        ];

        $signaturePayload = $payload;
        unset($signaturePayload['frontend_assets']);
        foreach ($signaturePayload['slides'] as &$signatureSlide) {
            // Template-rendered HTML can contain time-based or client-animated elements.
            // Keep reload signatures tied to stable saved config/values instead of runtime DOM state.
            unset($signatureSlide['template_rendered_hash']);
        }
        unset($signatureSlide);

        $payload['signature'] = sha1(json_encode($signaturePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
        $payload['ok'] = true;
        $payload['server_time_ms'] = (int)floor(microtime(true) * 1000);
        return $payload;
    }

    private function frontendAssetUrls(array $pluginAssets): array
    {
        $css = [asset_url('/assets/css/display.css')];
        foreach (($pluginAssets['css'] ?? []) as $asset) {
            $asset = trim((string)$asset);
            if ($asset !== '' && !in_array($asset, $css, true)) {
                $css[] = $asset;
            }
        }

        $js = [
            asset_url('/assets/js/hugin-qr.js'),
            asset_url('/assets/js/slideshow.js'),
        ];
        foreach (($pluginAssets['js'] ?? []) as $asset) {
            $asset = trim((string)$asset);
            if ($asset !== '' && !in_array($asset, $js, true)) {
                $js[] = $asset;
            }
        }

        return [
            'css' => $css,
            'js' => $js,
            'service_worker' => asset_url('/display-service-worker.js'),
        ];
    }

    private function loadDisplayGroup(int $displayId): ?array
    {
        if ($displayId <= 0) {
            return null;
        }

        $group = $this->db->one(
            'SELECT g.id, g.name, g.sync_enabled, g.sync_mode, l.name AS location_name
             FROM display_group_memberships dgm
             INNER JOIN display_groups g ON g.id = dgm.group_id
             INNER JOIN display_locations l ON l.id = g.location_id
             WHERE dgm.display_id = ?
             LIMIT 1',
            [$displayId]
        );

        if (!$group) {
            return null;
        }

        $syncEnabled = (int)($group['sync_enabled'] ?? 0) === 1;
        return [
            'id' => (int)$group['id'],
            'name' => (string)$group['name'],
            'location_name' => (string)($group['location_name'] ?? ''),
            'sync_enabled' => $syncEnabled ? 1 : 0,
            'sync_mode' => (string)($group['sync_mode'] ?? 'independent'),
            'sync_reload_to_full_minute' => $syncEnabled,
        ];
    }

    private function collectPluginGlobalUpdatedAt(array $resolvedSlides): array
    {
        $pluginNames = [];
        foreach ($resolvedSlides as $slide) {
            $pluginName = (string)($slide['plugin_name'] ?? '');
            if ($pluginName !== '' && $pluginName !== 'missing-plugin') {
                $pluginNames[$pluginName] = true;
            }
        }

        if (!$pluginNames) {
            return [];
        }

        $names = array_keys($pluginNames);
        $placeholders = implode(', ', array_fill(0, count($names), '?'));
        $rows = $this->db->all(
            'SELECT plugin_name, updated_at FROM plugin_global_settings WHERE plugin_name IN (' . $placeholders . ')',
            $names
        );

        $updatedAt = [];
        foreach ($rows as $row) {
            $updatedAt[(string)$row['plugin_name']] = (string)($row['updated_at'] ?? '');
        }
        ksort($updatedAt);

        return $updatedAt;
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
        return in_array($slideType, ['image', 'video', 'website', 'text', 'template'], true);
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
        $weekday = (int)$now->format('N');
        $currentTime = $now->format('H:i:s');

        return $this->db->one(
            'SELECT cdsa.id, cdsa.display_id, cdsa.channel_id, cdsa.schedule_id, cdsa.priority AS sort_order,
                    cdsa.created_at AS assignment_created_at,
                    s.name AS schedule_name, s.type AS schedule_type, s.updated_at AS schedule_updated_at,
                    sr.id AS schedule_rule_id, sr.weekday AS schedule_rule_weekday,
                    sr.start_time AS schedule_rule_start_time, sr.end_time AS schedule_rule_end_time,
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
             ORDER BY CASE WHEN s.type = \'fulltime\' THEN 1 ELSE 0 END ASC, cdsa.priority ASC, cdsa.id ASC, sr.id ASC
             LIMIT 1',
            [$weekday, $currentTime, $currentTime, $display['id']]
        );
    }
}
