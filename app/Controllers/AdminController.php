<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\UploadManager;
use App\Core\View;
use App\Core\PluginManager;
use RuntimeException;

class AdminController
{
    public function __construct(
        private Database $db,
        private View $view,
        private Auth $auth,
        private Request $request,
        private UploadManager $uploadManager,
        private PluginManager $plugins
    ) {
    }

    public function loginForm(): void
    {
        $this->view->render('admin/login', ['error' => flash('error')]);
    }

    public function login(): void
    {
        $username = trim((string)$this->request->input('username'));
        $password = (string)$this->request->input('password');

        if ($this->auth->attempt($username, $password)) {
            flash('success', __('messages.welcome_back'));
            redirect('/admin');
        }

        flash('error', __('errors.invalid_username_password'));
        redirect('/admin/login');
    }

    public function logout(): void
    {
        $this->auth->logout();
        redirect('/admin/login');
    }

    public function about(): void
    {
        $this->auth->requireLogin();

        $this->view->render('admin/about', [
            'software' => [
                'name' => __('app.name', [], 'Hugin'),
                'type' => __('about.software_type'),
                'stack' => __('about.software_stack'),
                'features' => [
                    __('about.feature_1'),
                    __('about.feature_2'),
                    __('about.feature_3'),
                    __('about.feature_4'),
                    __('about.feature_5'),
                    __('about.feature_6'),
                ],
            ],
            'license' => [
                'name' => __('about.license_name'),
                'short_name' => __('about.license_short_name'),
                'summary' => __('about.license_summary'),
                'source_url' => 'https://example.com/your-org/hugin',
            ],
        ]);
    }


    public function plugins(): void
    {
        $this->auth->requireRole('admin');
        $this->plugins->syncRegistry();
        $this->view->render('admin/plugins', [
            'plugins' => $this->plugins->listForAdmin(),
            'flash' => flash('success'),
        ]);
    }

    public function togglePlugin(string $pluginName): void
    {
        $this->auth->requireRole('admin');
        $plugin = $this->plugins->getPlugin($pluginName);
        if (!$plugin) {
            flash('error', __('plugins.not_found'));
            redirect('/admin/plugins');
        }

        $enable = $this->request->input('enable') ? true : false;
        $this->plugins->setEnabled($pluginName, $enable);
        flash('success', __($enable ? 'plugins.enabled_message' : 'plugins.disabled_message', ['plugin' => $plugin->getDisplayName()]));
        redirect('/admin/plugins');
    }

    public function dashboard(): void
    {
        $this->auth->requireLogin();

        $stats = [
            'displays' => $this->count('displays'),
            'channels' => $this->count('channels'),
            'slides' => $this->count('slides'),
            'media' => $this->count('media_assets'),
            'users' => $this->count('users'),
            'online_displays' => $this->countOnlineDisplays(),
            'plugins' => count($this->plugins->getEnabledPlugins()),
        ];

        $recentSlides = $this->db->all(
            'SELECT s.name, s.slide_type,
                    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ", ") AS channel_names
             FROM slides s
             LEFT JOIN channel_slide_assignments csa ON csa.slide_id = s.id
             LEFT JOIN channels c ON c.id = csa.channel_id
             GROUP BY s.id, s.name, s.slide_type, s.updated_at
             ORDER BY s.updated_at DESC
             LIMIT 5'
        );

        $displayStatuses = $this->db->all(
            'SELECT d.id, d.name, d.slug, d.timezone, d.is_active,
                    h.last_seen_at, h.last_seen_ip, h.current_channel_id, h.current_channel_name
             FROM displays d
             LEFT JOIN display_heartbeats h ON h.display_id = d.id
             ORDER BY d.sort_order ASC, d.name ASC'
        );

        foreach ($displayStatuses as &$displayStatus) {
            $activeAssignment = $this->resolveActiveAssignment($displayStatus);
            $displayStatus['resolved_channel_name'] = $activeAssignment['channel_name'] ?? ($displayStatus['current_channel_name'] ?: __('dashboard.no_channel'));
            $displayStatus['status'] = $this->isDisplayOnline($displayStatus['last_seen_at']) ? 'online' : 'offline';
            $displayStatus['minutes_since_seen'] = $this->minutesSinceSeen($displayStatus['last_seen_at']);
        }
        unset($displayStatus);

        $this->view->render('admin/dashboard', [
            'stats' => $stats,
            'recentSlides' => $recentSlides,
            'displayStatuses' => $displayStatuses,
            'flash' => flash('success'),
        ]);
    }

    public function displays(): void
    {
        $this->auth->requireLogin();

        $displays = $this->db->all(
            'SELECT d.*,
                    (SELECT COUNT(*) FROM display_channel_assignments dca WHERE dca.display_id = d.id) AS channel_count
             FROM displays d
             ORDER BY d.sort_order ASC, d.name ASC'
        );

        $this->view->render('admin/displays', [
            'displays' => $displays,
            'flash' => flash('success'),
        ]);
    }

    public function displayForm(?int $id = null): void
    {
        $this->auth->requireLogin();

        $display = $id ? $this->db->one('SELECT * FROM displays WHERE id = ?', [$id]) : null;
        $heartbeat = null;

        if ($id) {
            $heartbeat = $this->db->one(
                'SELECT h.*, d.name AS display_name
                 FROM display_heartbeats h
                 INNER JOIN displays d ON d.id = h.display_id
                 WHERE h.display_id = ?',
                [$id]
            );
        }

        $this->view->render('admin/display_form', [
            'display' => $display,
            'heartbeat' => $heartbeat,
            'error' => flash('error'),
        ]);
    }

    public function saveDisplay(?int $id = null): void
    {
        $this->auth->requireLogin();

        $name = trim((string)$this->request->input('name'));
        $slug = slugify((string)$this->request->input('slug', $name));
        $description = trim((string)$this->request->input('description'));
        $effect = $this->sanitizeEffect((string)$this->request->input('transition_effect', 'fade'), false);
        $duration = max(1, (int)$this->request->input('slide_duration_seconds', 8));
        $timezone = trim((string)$this->request->input('timezone', 'UTC')) ?: 'UTC';
        $sortOrder = max(0, (int)$this->request->input('sort_order', 0));
        $orientation = $this->sanitizeOrientation((string)$this->request->input('orientation', 'landscape'));
        $isActive = $this->request->input('is_active') ? 1 : 0;

        if ($name === '' || $slug === '') {
            flash('error', __('display.name_and_slug_required'));
            redirect($id ? '/admin/displays/' . $id . '/edit' : '/admin/displays/create');
        }

        $existing = $this->db->one('SELECT id FROM displays WHERE slug = ? LIMIT 1', [$slug]);
        if ($existing && (int)$existing['id'] !== (int)$id) {
            flash('error', __('display.slug_exists'));
            redirect($id ? '/admin/displays/' . $id . '/edit' : '/admin/displays/create');
        }

        if ($id) {
            $this->db->execute(
                'UPDATE displays SET name = ?, slug = ?, description = ?, transition_effect = ?, slide_duration_seconds = ?, timezone = ?, sort_order = ?, orientation = ?, is_active = ? WHERE id = ?',
                [$name, $slug, $description, $effect, $duration, $timezone, $sortOrder, $orientation, $isActive, $id]
            );
            flash('success', __('display.updated'));
        } else {
            $nextSort = $sortOrder ?: ((int)($this->db->one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM displays')['next_sort'] ?? 1));
            $this->db->execute(
                'INSERT INTO displays (name, slug, description, transition_effect, slide_duration_seconds, timezone, sort_order, orientation, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$name, $slug, $description, $effect, $duration, $timezone, $nextSort, $orientation, $isActive]
            );
            flash('success', __('display.created'));
        }

        redirect('/admin/displays');
    }

    public function deleteDisplay(int $id): void
    {
        $this->auth->requireLogin();
        $this->db->execute('DELETE FROM displays WHERE id = ?', [$id]);
        flash('success', __('display.deleted'));
        redirect('/admin/displays');
    }

    public function sortDisplays(): void
    {
        $this->auth->requireLogin();
        foreach ($this->normalizeIds($this->request->input('ids', [])) as $index => $id) {
            $this->db->execute('UPDATE displays SET sort_order = ? WHERE id = ?', [$index + 1, $id]);
        }
        json_response(['ok' => true]);
    }

    public function channels(): void
    {
        $this->auth->requireLogin();

        $rows = $this->db->all(
            'SELECT d.id AS display_id, d.name AS display_name, d.slug AS display_slug,
                    c.id AS channel_id, c.name AS channel_name, c.transition_effect, c.is_active,
                    dca.is_default, dca.sort_order,
                    (SELECT COUNT(*) FROM channel_slide_assignments csa WHERE csa.channel_id = c.id) AS slide_count,
                    (SELECT COUNT(*) FROM display_channel_schedules dcs WHERE dcs.display_channel_assignment_id = dca.id AND dcs.is_enabled = 1) AS schedule_count
             FROM display_channel_assignments dca
             INNER JOIN displays d ON d.id = dca.display_id
             INNER JOIN channels c ON c.id = dca.channel_id
             ORDER BY d.sort_order ASC, dca.sort_order ASC, c.name ASC'
        );

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['display_id']]['display'] = [
                'id' => $row['display_id'],
                'name' => $row['display_name'],
                'slug' => $row['display_slug'],
            ];
            $groups[$row['display_id']]['channels'][] = $row;
        }

        $this->view->render('admin/channels', ['groups' => $groups, 'flash' => flash('success')]);
    }

    public function channelForm(?int $id = null): void
    {
        $this->auth->requireLogin();

        $channel = $id ? $this->db->one('SELECT * FROM channels WHERE id = ?', [$id]) : null;
        $displays = $this->db->all('SELECT id, name FROM displays ORDER BY sort_order ASC, name ASC');
        $assignments = [];
        $schedules = [];

        if ($id) {
            $assignmentRows = $this->db->all(
                'SELECT dca.id, dca.display_id, dca.is_default, dca.sort_order, d.name AS display_name
                 FROM display_channel_assignments dca
                 INNER JOIN displays d ON d.id = dca.display_id
                 WHERE dca.channel_id = ?
                 ORDER BY d.sort_order ASC, d.name ASC',
                [$id]
            );
            foreach ($assignmentRows as $assignment) {
                $assignments[$assignment['display_id']] = $assignment;
            }

            $schedules = $this->db->all(
                'SELECT dcs.*, dca.display_id
                 FROM display_channel_schedules dcs
                 INNER JOIN display_channel_assignments dca ON dca.id = dcs.display_channel_assignment_id
                 WHERE dca.channel_id = ?
                 ORDER BY dca.display_id ASC, dcs.weekday ASC, dcs.start_time ASC',
                [$id]
            );
        }

        $this->view->render('admin/channel_form', [
            'channel' => $channel,
            'displays' => $displays,
            'assignments' => $assignments,
            'schedules' => $schedules,
            'error' => flash('error'),
        ]);
    }

    public function saveChannel(?int $id = null): void
    {
        $this->auth->requireLogin();

        $name = trim((string)$this->request->input('name'));
        $description = trim((string)$this->request->input('description'));
        $effect = $this->sanitizeEffect((string)$this->request->input('transition_effect', 'inherit'), true);
        $durationRaw = trim((string)$this->request->input('slide_duration_seconds', ''));
        $duration = $durationRaw === '' ? null : max(1, (int)$durationRaw);
        $isActive = $this->request->input('is_active') ? 1 : 0;
        $displayIds = $this->normalizeIds($this->request->input('display_ids', []));
        $defaultDisplayIds = $this->normalizeIds($this->request->input('default_display_ids', []));

        if ($name === '' || !$displayIds) {
            flash('error', __('channel.name_and_display_required'));
            redirect($id ? '/admin/channels/' . $id . '/edit' : '/admin/channels/create');
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            if ($id) {
                $this->db->execute(
                    'UPDATE channels SET name = ?, description = ?, transition_effect = ?, slide_duration_seconds = ?, is_active = ? WHERE id = ?',
                    [$name, $description, $effect, $duration, $isActive, $id]
                );
                $channelId = $id;
            } else {
                $this->db->execute(
                    'INSERT INTO channels (name, description, transition_effect, slide_duration_seconds, is_active) VALUES (?, ?, ?, ?, ?)',
                    [$name, $description, $effect, $duration, $isActive]
                );
                $channelId = (int)$this->db->lastInsertId();
            }

            $this->db->execute('DELETE FROM display_channel_assignments WHERE channel_id = ?', [$channelId]);

            $assignmentMap = [];
            foreach ($displayIds as $index => $displayId) {
                $isDefault = in_array($displayId, $defaultDisplayIds, true) ? 1 : 0;
                if ($isDefault) {
                    $this->db->execute('UPDATE display_channel_assignments SET is_default = 0 WHERE display_id = ?', [$displayId]);
                }
                $this->db->execute(
                    'INSERT INTO display_channel_assignments (display_id, channel_id, is_default, sort_order) VALUES (?, ?, ?, ?)',
                    [$displayId, $channelId, $isDefault, $index + 1]
                );
                $assignmentMap[$displayId] = (int)$this->db->lastInsertId();
            }

            $displayScheduleIds = (array)$this->request->input('schedule_display_id', []);
            $weekdays = (array)$this->request->input('schedule_weekday', []);
            $starts = (array)$this->request->input('schedule_start', []);
            $ends = (array)$this->request->input('schedule_end', []);
            $rowCount = max(count($displayScheduleIds), count($weekdays), count($starts), count($ends));

            for ($index = 0; $index < $rowCount; $index++) {
                $displayId = (int)($displayScheduleIds[$index] ?? 0);
                $weekdayRaw = (string)($weekdays[$index] ?? '');
                $start = (string)($starts[$index] ?? '');
                $end = (string)($ends[$index] ?? '');

                if ($displayId <= 0 || !isset($assignmentMap[$displayId])) {
                    continue;
                }

                if (!preg_match('/^[0-6]$/', $weekdayRaw) || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
                    continue;
                }

                $this->db->execute(
                    'INSERT INTO display_channel_schedules (display_channel_assignment_id, weekday, start_time, end_time, is_enabled) VALUES (?, ?, ?, ?, 1)',
                    [$assignmentMap[$displayId], (int)$weekdayRaw, $start . ':00', $end . ':00']
                );
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        flash('success', __($id ? 'channel.updated' : 'channel.created'));
        redirect('/admin/channels');
    }

    public function deleteChannel(int $id): void
    {
        $this->auth->requireLogin();
        $this->db->execute('DELETE FROM channels WHERE id = ?', [$id]);
        flash('success', __('channel.deleted'));
        redirect('/admin/channels');
    }

    public function sortChannels(): void
    {
        $this->auth->requireLogin();
        $displayId = (int)$this->request->input('display_id');
        foreach ($this->normalizeIds($this->request->input('ids', [])) as $index => $channelId) {
            $this->db->execute('UPDATE display_channel_assignments SET sort_order = ? WHERE channel_id = ? AND display_id = ?', [$index + 1, $channelId, $displayId]);
        }
        json_response(['ok' => true]);
    }

    public function slides(): void
    {
        $this->auth->requireLogin();

        $rows = $this->db->all(
            'SELECT c.id AS channel_id, c.name AS channel_name, s.id, s.name, s.slide_type, s.source_url, s.duration_seconds, s.is_active,
                    csa.sort_order, m.original_name AS media_name
             FROM channel_slide_assignments csa
             INNER JOIN channels c ON c.id = csa.channel_id
             INNER JOIN slides s ON s.id = csa.slide_id
             LEFT JOIN media_assets m ON m.id = s.media_asset_id
             ORDER BY c.name ASC, csa.sort_order ASC, s.name ASC'
        );

        $groups = [];
        foreach ($rows as $row) {
            $key = $row['channel_id'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'channel_name' => $row['channel_name'],
                    'channel_id' => $row['channel_id'],
                    'slides' => [],
                ];
            }
            $groups[$key]['slides'][] = $row;
        }

        $this->view->render('admin/slides', ['groups' => $groups, 'flash' => flash('success'), 'pluginLabels' => $this->plugins->getPluginLabelMap()]);
    }

    public function slideForm(?int $id = null): void
    {
        $this->auth->requireLogin();

        $slide = $id ? $this->db->one('SELECT * FROM slides WHERE id = ?', [$id]) : null;
        $channels = $this->db->all('SELECT id, name AS label FROM channels WHERE is_active = 1 ORDER BY name ASC');
        $mediaAssets = $this->db->all('SELECT * FROM media_assets ORDER BY created_at DESC, id DESC');
        $imageMediaAssets = array_values(array_filter($mediaAssets, static fn(array $asset): bool => ($asset['media_kind'] ?? '') === 'image'));
        $assignedChannelIds = $id
            ? array_map(static fn(array $row): int => (int)$row['channel_id'], $this->db->all('SELECT channel_id FROM channel_slide_assignments WHERE slide_id = ?', [$id]))
            : [];

        $pluginDefinitions = [];
        $pluginForms = [];
        foreach ($this->plugins->getEnabledPlugins() as $plugin) {
            $settings = $id ? $this->plugins->loadSlideSettings($id, $plugin->getName()) : $plugin->getDefaultSettings();
            $pluginDefinitions[] = [
                'name' => $plugin->getName(),
                'slide_type' => $plugin->getSlideType(),
                'display_name' => $plugin->getDisplayName(),
                'description' => (string)($plugin->getManifest()['description'] ?? ''),
            ];
            $pluginForms[$plugin->getName()] = $plugin->renderAdminSettings($slide ?? [], $settings, $this->plugins->buildApi());
        }

        $this->view->render('admin/slide_form', [
            'slide' => $slide,
            'channels' => $channels,
            'assignedChannelIds' => $assignedChannelIds,
            'mediaAssets' => $mediaAssets,
            'imageMediaAssets' => $imageMediaAssets,
            'pluginDefinitions' => $pluginDefinitions,
            'pluginForms' => $pluginForms,
            'pluginLabels' => $this->plugins->getPluginLabelMap(),
            'error' => flash('error'),
        ]);
    }

    public function saveSlide(?int $id = null): void
    {
        $this->auth->requireLogin();

        $channelIds = $this->normalizeIds($this->request->input('channel_ids', []));
        $name = trim((string)$this->request->input('name'));
        $slideType = (string)$this->request->input('slide_type');
        $sourceMode = (string)$this->request->input('source_mode', 'external');
        $sourceUrl = trim((string)$this->request->input('source_url'));
        $mediaAssetId = $this->request->input('media_asset_id') ? (int)$this->request->input('media_asset_id') : null;
        $durationRaw = trim((string)$this->request->input('duration_seconds', ''));
        $duration = $durationRaw === '' ? null : max(1, (int)$durationRaw);
        $titlePosition = $this->sanitizeTitlePosition((string)$this->request->input('title_position', 'bottom-left'));
        $isActive = $this->request->input('is_active') ? 1 : 0;
        $textMarkup = trim((string)$this->request->input('text_markup', ''));
        $backgroundColor = normalize_hex_color((string)$this->request->input('background_color', '#0f172a'), '#0f172a');
        $backgroundMediaAssetId = $this->request->input('background_media_asset_id') ? (int)$this->request->input('background_media_asset_id') : null;
        $plugin = $this->plugins->getPluginBySlideType($slideType, true);
        $isPluginType = $plugin !== null;

        if (!$channelIds || $name === '' || (!$this->isBuiltInSlideType($slideType) && !$isPluginType)) {
            flash('error', __('slide.channel_assignment_required'));
            redirect($id ? '/admin/slides/' . $id . '/edit' : '/admin/slides/create');
        }

        $pluginSettings = [];
        $mediaAsset = null;
        try {
            if ($isPluginType) {
                $submittedPluginSettings = (array)(($this->request->input('plugin_settings', [])[$plugin->getName()] ?? []));
                $existingPluginSettings = $id ? $this->plugins->loadSlideSettings($id, $plugin->getName()) : [];
                $pluginSettings = $plugin->normalizeSettings($submittedPluginSettings, $existingPluginSettings, $this->plugins->buildApi());
                $sourceMode = 'external';
                $sourceUrl = '';
                $mediaAssetId = null;
            } elseif ($slideType === 'website') {
                if ($sourceUrl === '') {
                    throw new RuntimeException(__('slide.website_requires_url'));
                }
                $sourceMode = 'external';
                $mediaAssetId = null;
                $backgroundMediaAssetId = null;
                $textMarkup = '';
            } elseif ($slideType === 'text') {
                $uploadedBackground = $this->request->file('background_uploaded_file');
                if ($uploadedBackground && ($uploadedBackground['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $mediaAsset = $this->uploadManager->storeUploadedFile($uploadedBackground, (int)$this->auth->id(), $name . ' background');
                    $backgroundMediaAssetId = (int)$mediaAsset['id'];
                } elseif ($backgroundMediaAssetId) {
                    $mediaAsset = $this->db->one('SELECT * FROM media_assets WHERE id = ?', [$backgroundMediaAssetId]);
                    if (!$mediaAsset) {
                        throw new RuntimeException(__('slide.selected_media_not_found'));
                    }
                }

                if ($backgroundMediaAssetId) {
                    $mediaAsset = $mediaAsset ?: $this->db->one('SELECT * FROM media_assets WHERE id = ?', [$backgroundMediaAssetId]);
                    if (!$mediaAsset || $mediaAsset['media_kind'] !== 'image') {
                        throw new RuntimeException(__('slide.background_image_type_mismatch'));
                    }
                }

                $sourceMode = 'external';
                $sourceUrl = '';
                $mediaAssetId = null;
            } else {
                $backgroundMediaAssetId = null;
                $textMarkup = '';
                $uploadedFile = $this->request->file('uploaded_file');
                if ($uploadedFile && ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $mediaAsset = $this->uploadManager->storeUploadedFile($uploadedFile, (int)$this->auth->id(), $name);
                    $mediaAssetId = (int)$mediaAsset['id'];
                    $sourceUrl = (string)$mediaAsset['file_path'];
                    $sourceMode = 'media';
                } elseif ($sourceMode === 'media') {
                    if (!$mediaAssetId) {
                        throw new RuntimeException(__('slide.choose_media_or_upload'));
                    }
                    $mediaAsset = $this->db->one('SELECT * FROM media_assets WHERE id = ?', [$mediaAssetId]);
                    if (!$mediaAsset) {
                        throw new RuntimeException(__('slide.selected_media_not_found'));
                    }
                    $sourceUrl = (string)$mediaAsset['file_path'];
                } else {
                    if ($sourceUrl === '') {
                        throw new RuntimeException(__('slide.provide_external_or_media'));
                    }
                    $mediaAssetId = null;
                }

                if ($mediaAssetId) {
                    $mediaAsset = $mediaAsset ?: $this->db->one('SELECT * FROM media_assets WHERE id = ?', [$mediaAssetId]);
                    $expectedKind = $slideType === 'image' ? 'image' : 'video';
                    if (!$mediaAsset || $mediaAsset['media_kind'] !== $expectedKind) {
                        throw new RuntimeException(__('slide.selected_media_type_mismatch'));
                    }
                }
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect($id ? '/admin/slides/' . $id . '/edit' : '/admin/slides/create');
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            if ($id) {
                $this->db->execute(
                    'UPDATE slides SET name = ?, slide_type = ?, source_mode = ?, source_url = ?, media_asset_id = ?, background_media_asset_id = ?, text_markup = ?, background_color = ?, duration_seconds = ?, title_position = ?, is_active = ? WHERE id = ?',
                    [$name, $slideType, $sourceMode, $sourceUrl, $mediaAssetId, $backgroundMediaAssetId, $textMarkup !== '' ? $textMarkup : null, $backgroundColor, $duration, $titlePosition, $isActive, $id]
                );
                $slideId = $id;
            } else {
                $this->db->execute(
                    'INSERT INTO slides (name, slide_type, source_mode, source_url, media_asset_id, background_media_asset_id, text_markup, background_color, duration_seconds, title_position, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$name, $slideType, $sourceMode, $sourceUrl, $mediaAssetId, $backgroundMediaAssetId, $textMarkup !== '' ? $textMarkup : null, $backgroundColor, $duration, $titlePosition, $isActive]
                );
                $slideId = (int)$this->db->lastInsertId();
            }

            $this->db->execute('DELETE FROM channel_slide_assignments WHERE slide_id = ?', [$slideId]);
            foreach ($channelIds as $index => $channelId) {
                $this->db->execute(
                    'INSERT INTO channel_slide_assignments (channel_id, slide_id, sort_order) VALUES (?, ?, ?)',
                    [$channelId, $slideId, $index + 1]
                );
            }

            $this->plugins->deleteSlideSettings($slideId);
            if ($isPluginType) {
                $this->plugins->saveSlideSettings($slideId, $plugin->getName(), $pluginSettings);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        flash('success', __($id ? 'slide.updated' : 'slide.created'));
        redirect('/admin/slides');
    }

    public function deleteSlide(int $id): void
    {
        $this->auth->requireLogin();
        $this->db->execute('DELETE FROM slides WHERE id = ?', [$id]);
        flash('success', __('slide.deleted'));
        redirect('/admin/slides');
    }

    public function removeSlideFromChannel(int $slideId, int $channelId): void
    {
        $this->auth->requireLogin();

        $assignment = $this->db->one(
            'SELECT csa.channel_id, csa.slide_id, c.name AS channel_name, s.name AS slide_name
             FROM channel_slide_assignments csa
             INNER JOIN channels c ON c.id = csa.channel_id
             INNER JOIN slides s ON s.id = csa.slide_id
             WHERE csa.slide_id = ? AND csa.channel_id = ?
             LIMIT 1',
            [$slideId, $channelId]
        );

        if (!$assignment) {
            flash('error', __('slide.assignment_not_found'));
            redirect('/admin/slides');
        }

        $this->db->execute(
            'DELETE FROM channel_slide_assignments WHERE slide_id = ? AND channel_id = ?',
            [$slideId, $channelId]
        );

        $remaining = $this->db->all(
            'SELECT slide_id FROM channel_slide_assignments WHERE channel_id = ? ORDER BY sort_order ASC, slide_id ASC',
            [$channelId]
        );

        foreach ($remaining as $index => $row) {
            $this->db->execute(
                'UPDATE channel_slide_assignments SET sort_order = ? WHERE slide_id = ? AND channel_id = ?',
                [$index + 1, (int)$row['slide_id'], $channelId]
            );
        }

        flash('success', __('slide.removed_from_channel', ['slide' => $assignment['slide_name'], 'channel' => $assignment['channel_name']]));
        redirect('/admin/slides');
    }

    public function sortSlides(): void
    {
        $this->auth->requireLogin();
        $channelId = (int)$this->request->input('channel_id');
        foreach ($this->normalizeIds($this->request->input('ids', [])) as $index => $slideId) {
            $this->db->execute('UPDATE channel_slide_assignments SET sort_order = ? WHERE slide_id = ? AND channel_id = ?', [$index + 1, $slideId, $channelId]);
        }
        json_response(['ok' => true]);
    }

    public function media(): void
    {
        $this->auth->requireLogin();
        $media = $this->db->all(
            'SELECT m.*, COALESCE(u.display_name, u.username) AS uploaded_by,
                    (SELECT COUNT(*) FROM slides s WHERE s.media_asset_id = m.id) AS usage_count
             FROM media_assets m
             LEFT JOIN users u ON u.id = m.uploaded_by_user_id
             ORDER BY m.created_at DESC, m.id DESC'
        );
        $this->view->render('admin/media', ['media' => $media, 'flash' => flash('success'), 'error' => flash('error')]);
    }

    public function uploadMedia(): void
    {
        $this->auth->requireLogin();
        try {
            $asset = $this->uploadManager->storeUploadedFile($this->request->file('media_file'), (int)$this->auth->id(), trim((string)$this->request->input('name')));
            if (!$asset) {
                throw new RuntimeException(__('media.choose_file'));
            }
            flash('success', __('media.uploaded'));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/admin/media');
    }

    public function deleteMedia(int $id): void
    {
        $this->auth->requireRole('admin');
        $asset = $this->db->one('SELECT * FROM media_assets WHERE id = ?', [$id]);
        if (!$asset) {
            flash('error', __('media.not_found'));
            redirect('/admin/media');
        }
        $usage = (int)($this->db->one('SELECT COUNT(*) AS cnt FROM slides WHERE media_asset_id = ?', [$id])['cnt'] ?? 0);
        if ($usage > 0) {
            flash('error', __('media.still_used'));
            redirect('/admin/media');
        }
        $this->db->execute('DELETE FROM media_assets WHERE id = ?', [$id]);
        $this->uploadManager->deleteMediaFile((string)$asset['file_path']);
        flash('success', __('media.deleted'));
        redirect('/admin/media');
    }

    public function users(): void
    {
        $this->auth->requireRole('admin');
        $users = $this->db->all('SELECT id, username, display_name, role, is_active, created_at FROM users ORDER BY username ASC');
        $this->view->render('admin/users', ['users' => $users, 'flash' => flash('success')]);
    }

    public function userForm(?int $id = null): void
    {
        $this->auth->requireRole('admin');
        $user = $id ? $this->db->one('SELECT id, username, display_name, role, is_active FROM users WHERE id = ?', [$id]) : null;
        $this->view->render('admin/user_form', ['user' => $user, 'error' => flash('error')]);
    }

    public function saveUser(?int $id = null): void
    {
        $this->auth->requireRole('admin');
        $username = trim((string)$this->request->input('username'));
        $displayName = trim((string)$this->request->input('display_name'));
        $role = (string)$this->request->input('role', 'editor');
        $password = (string)$this->request->input('password');
        $isActive = $this->request->input('is_active') ? 1 : 0;

        if ($username === '' || !in_array($role, ['admin', 'editor'], true)) {
            flash('error', __('users.valid_role_required'));
            redirect($id ? '/admin/users/' . $id . '/edit' : '/admin/users/create');
        }

        $existing = $this->db->one('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]);
        if ($existing && (int)$existing['id'] !== (int)$id) {
            flash('error', __('users.username_exists'));
            redirect($id ? '/admin/users/' . $id . '/edit' : '/admin/users/create');
        }

        if ($id) {
            if ($password !== '') {
                $this->db->execute('UPDATE users SET username = ?, display_name = ?, role = ?, password_hash = ?, is_active = ? WHERE id = ?', [$username, $displayName, $role, password_hash($password, PASSWORD_DEFAULT), $isActive, $id]);
            } else {
                $this->db->execute('UPDATE users SET username = ?, display_name = ?, role = ?, is_active = ? WHERE id = ?', [$username, $displayName, $role, $isActive, $id]);
            }
            if ((int)$id === (int)$this->auth->id()) {
                $_SESSION['_user']['username'] = $username;
                $_SESSION['_user']['display_name'] = $displayName;
                $_SESSION['_user']['role'] = $role;
            }
            flash('success', __('users.updated'));
        } else {
            if ($password === '') {
                flash('error', __('users.password_required_new'));
                redirect('/admin/users/create');
            }
            $this->db->execute('INSERT INTO users (username, display_name, role, password_hash, is_active) VALUES (?, ?, ?, ?, ?)', [$username, $displayName, $role, password_hash($password, PASSWORD_DEFAULT), $isActive]);
            flash('success', __('users.created'));
        }
        redirect('/admin/users');
    }

    public function deleteUser(int $id): void
    {
        $this->auth->requireRole('admin');
        if ((int)$id === (int)$this->auth->id()) {
            flash('error', __('users.cannot_delete_self'));
            redirect('/admin/users');
        }
        $this->db->execute('DELETE FROM users WHERE id = ?', [$id]);
        flash('success', __('users.deleted'));
        redirect('/admin/users');
    }

    private function count(string $table): int
    {
        return (int)($this->db->one('SELECT COUNT(*) AS cnt FROM ' . $table)['cnt'] ?? 0);
    }

    private function countOnlineDisplays(): int
    {
        return (int)($this->db->one('SELECT COUNT(*) AS cnt FROM display_heartbeats WHERE last_seen_at >= (NOW() - INTERVAL 30 MINUTE)')['cnt'] ?? 0);
    }

    private function isDisplayOnline(?string $lastSeenAt): bool
    {
        if (!$lastSeenAt) {
            return false;
        }

        return strtotime($lastSeenAt) >= (time() - 1800);
    }

    private function minutesSinceSeen(?string $lastSeenAt): ?int
    {
        if (!$lastSeenAt) {
            return null;
        }

        return max(0, (int) floor((time() - strtotime($lastSeenAt)) / 60));
    }

    private function resolveActiveAssignment(array $display): ?array
    {
        $timezone = new \DateTimeZone(($display['timezone'] ?? '') ?: 'UTC');
        $now = new \DateTime('now', $timezone);
        $weekday = (int)$now->format('w');
        $currentTime = $now->format('H:i:s');

        $activeAssignment = $this->db->one(
            'SELECT dca.channel_id, c.name AS channel_name
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
            'SELECT dca.channel_id, c.name AS channel_name
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


    private function isBuiltInSlideType(string $slideType): bool
    {
        return in_array($slideType, ['image', 'video', 'website', 'text'], true);
    }

    private function sanitizeOrientation(string $value): string
    {
        return in_array($value, ['landscape', 'vertical'], true) ? $value : 'landscape';
    }

    private function sanitizeTitlePosition(string $value): string
    {
        $allowed = ['hide', 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'];

        return in_array($value, $allowed, true) ? $value : 'bottom-left';
    }

    private function sanitizeEffect(string $effect, bool $allowInherit): string
    {
        $allowed = ['fade', 'slide-left', 'slide-right', 'slide-up', 'slide-down', 'zoom', 'flip', 'blur', 'none'];
        if ($allowInherit) {
            $allowed[] = 'inherit';
        }
        return in_array($effect, $allowed, true) ? $effect : ($allowInherit ? 'inherit' : 'fade');
    }

    private function normalizeIds(mixed $value): array
    {
        $ids = is_array($value) ? $value : [$value];
        return array_values(array_unique(array_filter(array_map(static fn($id) => (int)$id, $ids))));
    }
}
