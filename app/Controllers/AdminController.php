<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\SlidePluginInterface;
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

        $this->redirectWithForm('/admin/login', __('errors.invalid_username_password'), [
            'username' => $username,
        ], [
            'username' => __('errors.invalid_username_password'),
            'password' => __('errors.invalid_username_password'),
        ], 'login');
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
                'source_url' => 'https://github.com/Serophos/hugin',
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

    public function pluginSettingsForm(string $pluginName): void
    {
        $this->auth->requireRole('admin');
        $this->plugins->syncRegistry();

        $plugin = $this->plugins->getPlugin($pluginName);
        if (!$plugin) {
            flash('error', __('plugins.not_found'));
            redirect('/admin/plugins');
        }

        $settings = array_replace(
            $plugin->getDefaultGlobalSettings(),
            $this->plugins->loadGlobalSettings($pluginName)
        );
        if (form_has_old('plugin_settings')) {
            $settings = array_replace($settings, old_input('plugin_settings'));
        }
        $formHtml = $plugin->renderGlobalSettings($settings, $this->plugins->buildApi(null, null, null, $this->auth->id()));

        $this->view->render('admin/plugin_settings', [
            'plugin' => $plugin,
            'settings' => $settings,
            'formHtml' => $formHtml,
            'error' => flash('error'),
        ]);
    }

    public function savePluginSettings(string $pluginName): void
    {
        $this->auth->requireRole('admin');
        $this->plugins->syncRegistry();

        $plugin = $this->plugins->getPlugin($pluginName);
        if (!$plugin) {
            flash('error', __('plugins.not_found'));
            redirect('/admin/plugins');
        }

        $existingSettings = array_replace(
            $plugin->getDefaultGlobalSettings(),
            $this->plugins->loadGlobalSettings($pluginName)
        );
        $input = (array)(($this->request->input('plugin_global_settings', [])[$pluginName] ?? []));
        try {
            $settings = $plugin->normalizeGlobalSettings($input, $existingSettings, $this->plugins->buildApi(null, null, null, $this->auth->id()));
            $this->plugins->saveGlobalSettings($pluginName, $settings);
        } catch (RuntimeException $e) {
            $this->redirectWithForm(
                '/admin/plugins/' . $pluginName . '/settings',
                $e->getMessage(),
                $input,
                $this->pluginFieldErrors($plugin, $e->getMessage(), true),
                'plugin_settings'
            );
        }

        flash('success', __('plugins.settings_saved', ['plugin' => $plugin->getDisplayName()]));
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
        $this->auth->requireRole('admin');

        $displays = $this->db->all(
            'SELECT d.*,
                    g.name AS group_name,
                    l.name AS location_name,
                    (SELECT COUNT(DISTINCT cdsa.channel_id) FROM channel_display_schedule_assignments cdsa WHERE cdsa.display_id = d.id) AS channel_count
             FROM displays d
             LEFT JOIN display_group_memberships dgm ON dgm.display_id = d.id
             LEFT JOIN display_groups g ON g.id = dgm.group_id
             LEFT JOIN display_locations l ON l.id = g.location_id
             ORDER BY d.sort_order ASC, d.name ASC'
        );

        $this->view->render('admin/displays', [
            'displays' => $displays,
            'flash' => flash('success'),
        ]);
    }

    public function displayForm(?int $id = null): void
    {
        $this->auth->requireRole('admin');

        $display = $id ? $this->db->one('SELECT * FROM displays WHERE id = ?', [$id]) : null;
        if ($id && !$display) {
            flash('error', __('display.not_found'));
            redirect('/admin/displays');
        }

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
        $this->auth->requireRole('admin');

        if ($id && !$this->db->one('SELECT id FROM displays WHERE id = ?', [$id])) {
            flash('error', __('display.not_found'));
            redirect('/admin/displays');
        }

        $nameRaw = (string)$this->request->input('name');
        $slugRaw = (string)$this->request->input('slug', $nameRaw);
        $durationRaw = trim((string)$this->request->input('slide_duration_seconds', '8'));
        $sortOrderRaw = trim((string)$this->request->input('sort_order', '0'));
        $name = trim($nameRaw);
        $slug = slugify($slugRaw !== '' ? $slugRaw : $name);
        $description = trim((string)$this->request->input('description'));
        $effect = $this->sanitizeEffect((string)$this->request->input('transition_effect', 'fade'), false);
        $duration = max(1, (int)$durationRaw);
        $timezone = trim((string)$this->request->input('timezone', 'UTC')) ?: 'UTC';
        $sortOrder = max(0, (int)$sortOrderRaw);
        $orientation = $this->sanitizeOrientation((string)$this->request->input('orientation', 'landscape'));
        $isActive = $this->request->input('is_active') ? 1 : 0;
        $old = [
            'name' => $nameRaw,
            'slug' => $slugRaw,
            'description' => (string)$this->request->input('description'),
            'transition_effect' => $effect,
            'slide_duration_seconds' => $durationRaw,
            'timezone' => (string)$this->request->input('timezone', 'UTC'),
            'sort_order' => $sortOrderRaw,
            'orientation' => $orientation,
            'is_active' => $isActive,
        ];
        $errors = [];

        if ($name === '') {
            $errors['name'] = __('display.name_and_slug_required');
        }
        if (trim($slugRaw) === '') {
            $errors['slug'] = __('display.name_and_slug_required');
        } elseif (preg_match('/[a-z0-9]/i', $slugRaw) !== 1) {
            $errors['slug'] = __('display.invalid_slug');
        }
        if (!$this->isPositiveInteger($durationRaw)) {
            $errors['slide_duration_seconds'] = __('validation.positive_number');
        }
        if (!$this->isNonNegativeInteger($sortOrderRaw)) {
            $errors['sort_order'] = __('validation.non_negative_number');
        }
        if (!$this->isValidTimezone($timezone)) {
            $errors['timezone'] = __('display.invalid_timezone');
        }
        if ($errors !== []) {
            $this->redirectWithForm(
                $id ? '/admin/displays/' . $id . '/edit' : '/admin/displays/create',
                __('validation.fix_marked_fields'),
                $old,
                $errors,
                'display'
            );
        }

        $existing = $this->db->one('SELECT id FROM displays WHERE slug = ? LIMIT 1', [$slug]);
        if ($existing && (int)$existing['id'] !== (int)$id) {
            $this->redirectWithForm(
                $id ? '/admin/displays/' . $id . '/edit' : '/admin/displays/create',
                __('display.slug_exists'),
                $old,
                ['slug' => __('display.slug_exists')],
                'display'
            );
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
        $this->auth->requireRole('admin');
        $this->db->execute('DELETE FROM displays WHERE id = ?', [$id]);
        flash('success', __('display.deleted'));
        redirect('/admin/displays');
    }

    public function reloadDisplay(int $id): void
    {
        $this->auth->requireRole('admin');

        $display = $this->db->one('SELECT id, name FROM displays WHERE id = ?', [$id]);
        if (!$display) {
            flash('error', __('display.not_found'));
            redirect('/admin/displays');
        }

        $this->db->execute(
            'UPDATE displays
             SET updated_at = IF(updated_at >= CURRENT_TIMESTAMP, updated_at + INTERVAL 1 SECOND, CURRENT_TIMESTAMP)
             WHERE id = ?',
            [$id]
        );
        flash('success', __('display.reload_requested', ['display' => $display['name']]));
        redirect($this->adminReturnPath('/admin/displays'));
    }

    public function sortDisplays(): void
    {
        $this->auth->requireRole('admin');
        foreach ($this->normalizeIds($this->request->input('ids', [])) as $index => $id) {
            $this->db->execute('UPDATE displays SET sort_order = ? WHERE id = ?', [$index + 1, $id]);
        }
        json_response(['ok' => true]);
    }

    public function locations(): void
    {
        $this->auth->requireRole('admin');

        $locations = $this->db->all(
            'SELECT l.*,
                    COUNT(DISTINCT g.id) AS group_count,
                    COUNT(DISTINCT dgm.display_id) AS display_count
             FROM display_locations l
             LEFT JOIN display_groups g ON g.location_id = l.id
             LEFT JOIN display_group_memberships dgm ON dgm.group_id = g.id
             GROUP BY l.id
             ORDER BY l.sort_order ASC, l.name ASC'
        );

        $displays = $this->getDisplayOrganizationRows();
        $unassignedDisplays = array_values(array_filter(
            $displays,
            static fn(array $display): bool => empty($display['group_id'])
        ));

        $this->view->render('admin/locations', [
            'locations' => $locations,
            'unassignedDisplays' => $unassignedDisplays,
            'flash' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function locationForm(int $id): void
    {
        $this->auth->requireRole('admin');

        $location = $this->db->one(
            'SELECT l.*,
                    COUNT(DISTINCT g.id) AS group_count,
                    COUNT(DISTINCT dgm.display_id) AS display_count
             FROM display_locations l
             LEFT JOIN display_groups g ON g.location_id = l.id
             LEFT JOIN display_group_memberships dgm ON dgm.group_id = g.id
             WHERE l.id = ?
             GROUP BY l.id',
            [$id]
        );

        if (!$location) {
            flash('error', __('locations.not_found'));
            redirect('/admin/locations');
        }

        $groups = $this->db->all(
            'SELECT g.*, COUNT(dgm.display_id) AS display_count
             FROM display_groups g
             LEFT JOIN display_group_memberships dgm ON dgm.group_id = g.id
             WHERE g.location_id = ?
             GROUP BY g.id
             ORDER BY g.sort_order ASC, g.name ASC',
            [$id]
        );

        $unassignedDisplays = $this->getDisplayOrganizationRows('dgm.group_id IS NULL');

        $this->view->render('admin/location_edit', [
            'location' => $location,
            'groups' => $groups,
            'unassignedDisplays' => $unassignedDisplays,
            'flash' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function saveLocation(?int $id = null): void
    {
        $this->auth->requireRole('admin');

        $nameRaw = (string)$this->request->input('name');
        $sortOrderRaw = trim((string)$this->request->input('sort_order', '0'));
        $name = trim($nameRaw);
        $address = trim((string)$this->request->input('address'));
        $description = trim((string)$this->request->input('description'));
        $sortOrder = max(0, (int)$sortOrderRaw);
        $form = $id ? 'location_edit' : 'location_create';
        $returnPath = $id ? '/admin/locations/' . $id . '/edit' : '/admin/locations';
        $old = [
            'name' => $nameRaw,
            'address' => (string)$this->request->input('address'),
            'description' => (string)$this->request->input('description'),
            'sort_order' => $sortOrderRaw,
        ];
        $errors = [];

        if ($name === '') {
            $errors['name'] = __('locations.name_required');
        }
        if (!$this->isNonNegativeInteger($sortOrderRaw)) {
            $errors['sort_order'] = __('validation.non_negative_number');
        }
        if ($errors !== []) {
            $this->redirectWithForm($returnPath, __('validation.fix_marked_fields'), $old, $errors, $form);
        }

        $existing = $this->db->one('SELECT id FROM display_locations WHERE name = ? LIMIT 1', [$name]);
        if ($existing && (int)$existing['id'] !== (int)$id) {
            $this->redirectWithForm($returnPath, __('locations.name_exists'), $old, ['name' => __('locations.name_exists')], $form);
        }

        if ($id) {
            $location = $this->db->one('SELECT id FROM display_locations WHERE id = ?', [$id]);
            if (!$location) {
                flash('error', __('locations.not_found'));
                redirect('/admin/locations');
            }

            $this->db->execute(
                'UPDATE display_locations SET name = ?, address = ?, description = ?, sort_order = ? WHERE id = ?',
                [$name, $address, $description, $sortOrder, $id]
            );
            flash('success', __('locations.updated'));
            redirect('/admin/locations/' . $id . '/edit');
        } else {
            $nextSort = $sortOrder ?: ((int)($this->db->one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM display_locations')['next_sort'] ?? 1));
            $this->db->execute(
                'INSERT INTO display_locations (name, address, description, sort_order) VALUES (?, ?, ?, ?)',
                [$name, $address, $description, $nextSort]
            );
            flash('success', __('locations.created'));
            redirect('/admin/locations/' . $this->db->lastInsertId() . '/edit');
        }
    }

    public function deleteLocation(int $id): void
    {
        $this->auth->requireRole('admin');

        $location = $this->db->one('SELECT id, name FROM display_locations WHERE id = ?', [$id]);
        if (!$location) {
            flash('error', __('locations.not_found'));
            redirect('/admin/locations');
        }

        $displayCount = (int)($this->db->one(
            'SELECT COUNT(DISTINCT dgm.display_id) AS cnt
             FROM display_groups g
             INNER JOIN display_group_memberships dgm ON dgm.group_id = g.id
             WHERE g.location_id = ?',
            [$id]
        )['cnt'] ?? 0);

        $this->db->execute('DELETE FROM display_locations WHERE id = ?', [$id]);
        flash('success', __('locations.deleted', ['count' => $displayCount]));
        redirect('/admin/locations');
    }

    public function saveDisplayGroup(?int $id = null): void
    {
        $this->auth->requireRole('admin');

        $locationId = (int)$this->request->input('location_id');
        $nameRaw = (string)$this->request->input('name');
        $name = trim($nameRaw);
        $description = trim((string)$this->request->input('description'));
        $sortOrderRaw = trim((string)$this->request->input('sort_order', '0'));
        $sortOrder = max(0, (int)$sortOrderRaw);
        $defaultReturn = $locationId > 0 ? '/admin/locations/' . $locationId . '/edit' : '/admin/locations';
        $returnTo = $this->adminReturnPath($defaultReturn);
        $form = $id ? 'display_group_edit' : 'display_group_create';
        $old = [
            'location_id' => $locationId,
            'name' => $nameRaw,
            'description' => (string)$this->request->input('description'),
            'sort_order' => $sortOrderRaw,
        ];
        $errors = [];

        if ($name === '') {
            $errors['name'] = __('display_groups.name_required');
        }
        if (!$this->isNonNegativeInteger($sortOrderRaw)) {
            $errors['sort_order'] = __('validation.non_negative_number');
        }
        if ($errors !== []) {
            $this->redirectWithForm($returnTo, __('validation.fix_marked_fields'), $old, $errors, $form);
        }

        $location = $this->db->one('SELECT id FROM display_locations WHERE id = ?', [$locationId]);
        if (!$location) {
            $this->redirectWithForm('/admin/locations', __('locations.not_found'), $old, ['location_id' => __('locations.not_found')], $form);
        }

        $existing = $this->db->one(
            'SELECT id FROM display_groups WHERE location_id = ? AND name = ? LIMIT 1',
            [$locationId, $name]
        );
        if ($existing && (int)$existing['id'] !== (int)$id) {
            $this->redirectWithForm($returnTo, __('display_groups.name_exists'), $old, ['name' => __('display_groups.name_exists')], $form);
        }

        if ($id) {
            $group = $this->db->one('SELECT id FROM display_groups WHERE id = ?', [$id]);
            if (!$group) {
                flash('error', __('display_groups.not_found'));
                redirect($returnTo);
            }

            $this->db->execute(
                'UPDATE display_groups SET location_id = ?, name = ?, description = ?, sort_order = ? WHERE id = ?',
                [$locationId, $name, $description, $sortOrder, $id]
            );
            flash('success', __('display_groups.updated'));
        } else {
            $nextSort = $sortOrder ?: ((int)($this->db->one(
                'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM display_groups WHERE location_id = ?',
                [$locationId]
            )['next_sort'] ?? 1));
            $this->db->execute(
                'INSERT INTO display_groups (location_id, name, description, sort_order) VALUES (?, ?, ?, ?)',
                [$locationId, $name, $description, $nextSort]
            );
            flash('success', __('display_groups.created'));
        }

        redirect($returnTo);
    }

    public function deleteDisplayGroup(int $id): void
    {
        $this->auth->requireRole('admin');

        $group = $this->db->one('SELECT id, name, location_id FROM display_groups WHERE id = ?', [$id]);
        if (!$group) {
            flash('error', __('display_groups.not_found'));
            redirect('/admin/locations');
        }
        $returnTo = $this->adminReturnPath('/admin/locations/' . $group['location_id'] . '/edit');

        $displayCount = (int)($this->db->one(
            'SELECT COUNT(*) AS cnt FROM display_group_memberships WHERE group_id = ?',
            [$id]
        )['cnt'] ?? 0);

        $this->db->execute('DELETE FROM display_groups WHERE id = ?', [$id]);
        flash('success', __('display_groups.deleted', ['count' => $displayCount]));
        redirect($returnTo);
    }

    public function moveDisplaysToGroup(): void
    {
        $this->auth->requireRole('admin');

        $displayIds = $this->normalizeIds($this->request->input('display_ids', []));
        $targetGroupValue = trim((string)$this->request->input('target_group_id', ''));
        $targetGroupId = $targetGroupValue === '' ? null : (int)$targetGroupValue;
        $returnTo = $this->adminReturnPath();

        if ($displayIds === []) {
            flash('error', __('display_groups.bulk_none_selected'));
            redirect($returnTo);
        }

        if ($targetGroupId !== null) {
            $group = $this->db->one('SELECT id FROM display_groups WHERE id = ?', [$targetGroupId]);
            if (!$group) {
                flash('error', __('display_groups.not_found'));
                redirect($returnTo);
            }
        }

        $validDisplays = array_fill_keys(array_map(
            static fn(array $row): int => (int)$row['id'],
            $this->db->all('SELECT id FROM displays')
        ), true);
        $displayIds = array_values(array_filter($displayIds, static fn(int $id): bool => isset($validDisplays[$id])));

        if ($displayIds === []) {
            flash('error', __('display_groups.bulk_none_selected'));
            redirect($returnTo);
        }

        if ($targetGroupId === null) {
            $placeholders = implode(', ', array_fill(0, count($displayIds), '?'));
            $this->db->execute(
                'UPDATE display_group_memberships SET group_id = NULL, updated_at = CURRENT_TIMESTAMP WHERE display_id IN (' . $placeholders . ')',
                $displayIds
            );
        } else {
            foreach ($displayIds as $index => $displayId) {
                $this->db->execute(
                    'INSERT INTO display_group_memberships
                        (display_id, group_id, layout_x, layout_y, layout_width, layout_height, layout_rotation_degrees, sort_order)
                     VALUES (?, ?, 0, 0, NULL, NULL, 0, ?)
                     ON DUPLICATE KEY UPDATE
                        layout_x = IF(group_id <=> VALUES(group_id), layout_x, VALUES(layout_x)),
                        layout_y = IF(group_id <=> VALUES(group_id), layout_y, VALUES(layout_y)),
                        layout_width = IF(group_id <=> VALUES(group_id), layout_width, VALUES(layout_width)),
                        layout_height = IF(group_id <=> VALUES(group_id), layout_height, VALUES(layout_height)),
                        layout_rotation_degrees = IF(group_id <=> VALUES(group_id), layout_rotation_degrees, VALUES(layout_rotation_degrees)),
                        group_id = VALUES(group_id),
                        sort_order = VALUES(sort_order),
                        updated_at = CURRENT_TIMESTAMP',
                    [$displayId, $targetGroupId, $index + 1]
                );
            }
        }

        flash('success', __('display_groups.bulk_moved', ['count' => count($displayIds)]));
        redirect($returnTo);
    }

    public function displayGroup(int $id): void
    {
        $this->auth->requireRole('admin');

        $group = $this->db->one(
            'SELECT g.*, l.name AS location_name
             FROM display_groups g
             INNER JOIN display_locations l ON l.id = g.location_id
             WHERE g.id = ?',
            [$id]
        );

        if (!$group) {
            flash('error', __('display_groups.not_found'));
            redirect('/admin/locations');
        }

        $displays = $this->getDisplayOrganizationRows('dgm.group_id = ?', [$id]);
        foreach ($displays as &$display) {
            $defaults = $this->defaultLayoutSize($display);
            $display['layout_x'] = (int)($display['layout_x'] ?? 0);
            $display['layout_y'] = (int)($display['layout_y'] ?? 0);
            $display['layout_width'] = max(72, (int)($display['layout_width'] ?: $defaults['width']));
            $display['layout_height'] = max(72, (int)($display['layout_height'] ?: $defaults['height']));
            $display['layout_rotation_degrees'] = (int)($display['layout_rotation_degrees'] ?? 0);
        }
        unset($display);

        $unassignedDisplays = $this->getDisplayOrganizationRows('dgm.group_id IS NULL');

        $this->view->render('admin/display_group', [
            'group' => $group,
            'displays' => $displays,
            'unassignedDisplays' => $unassignedDisplays,
            'flash' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function saveDisplayGroupLayout(int $id): void
    {
        $this->auth->requireRole('admin');

        $group = $this->db->one('SELECT id FROM display_groups WHERE id = ?', [$id]);
        if (!$group) {
            json_response(['ok' => false, 'message' => __('display_groups.not_found')], 404);
        }

        $items = json_decode((string)$this->request->input('items', '[]'), true);
        if (!is_array($items)) {
            json_response(['ok' => false, 'message' => __('display_groups.layout_invalid')], 422);
        }

        $allowedDisplays = array_fill_keys(array_map(
            static fn(array $row): int => (int)$row['display_id'],
            $this->db->all('SELECT display_id FROM display_group_memberships WHERE group_id = ?', [$id])
        ), true);

        $this->db->pdo()->beginTransaction();
        try {
            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $displayId = (int)($item['display_id'] ?? 0);
                if (!isset($allowedDisplays[$displayId])) {
                    continue;
                }

                $x = $this->clampInt($item['x'] ?? 0, -100000, 100000);
                $y = $this->clampInt($item['y'] ?? 0, -100000, 100000);
                $width = $this->clampInt($item['width'] ?? 180, 72, 20000);
                $height = $this->clampInt($item['height'] ?? 120, 72, 20000);
                $rotation = $this->normalizeLayoutRotation((int)($item['rotation'] ?? 0));

                $this->db->execute(
                    'UPDATE display_group_memberships
                     SET layout_x = ?, layout_y = ?, layout_width = ?, layout_height = ?,
                         layout_rotation_degrees = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE display_id = ? AND group_id = ?',
                    [$x, $y, $width, $height, $rotation, $index + 1, $displayId, $id]
                );
            }

            $this->db->pdo()->commit();
        } catch (\Throwable $e) {
            $this->db->pdo()->rollBack();
            json_response(['ok' => false, 'message' => __('display_groups.layout_save_failed')], 500);
        }

        json_response(['ok' => true, 'message' => __('display_groups.layout_saved')]);
    }

    public function channels(): void
    {
        $this->auth->requireLogin();

        $rows = $this->db->all(
            'SELECT d.id AS display_id, d.name AS display_name, d.slug AS display_slug,
                    c.id AS channel_id, c.name AS channel_name, c.transition_effect, c.is_active,
                    cdsa.id AS assignment_id, cdsa.priority, cdsa.is_active AS assignment_is_active,
                    s.name AS schedule_name, s.type AS schedule_type,
                    (SELECT COUNT(*) FROM channel_slide_assignments csa WHERE csa.channel_id = c.id) AS slide_count
             FROM channel_display_schedule_assignments cdsa
             INNER JOIN displays d ON d.id = cdsa.display_id
             INNER JOIN channels c ON c.id = cdsa.channel_id
             INNER JOIN schedules s ON s.id = cdsa.schedule_id
             ORDER BY d.sort_order ASC, cdsa.priority ASC, c.name ASC, s.name ASC'
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
        if ($id && !$channel) {
            flash('error', __('channel.not_found'));
            redirect('/admin/channels');
        }

        $displays = $this->db->all('SELECT id, name FROM displays ORDER BY sort_order ASC, name ASC');
        $schedules = $this->db->all(
            'SELECT id, name, type, is_system, is_active FROM schedules ORDER BY is_system DESC, name ASC'
        );
        $assignments = [];

        if ($id) {
            $assignments = $this->db->all(
                'SELECT cdsa.id, cdsa.display_id, cdsa.schedule_id, cdsa.priority,
                        d.name AS display_name, s.name AS schedule_name, s.type AS schedule_type
                 FROM channel_display_schedule_assignments cdsa
                 INNER JOIN displays d ON d.id = cdsa.display_id
                 INNER JOIN schedules s ON s.id = cdsa.schedule_id
                 WHERE cdsa.channel_id = ?
                 ORDER BY d.sort_order ASC, cdsa.priority ASC, s.name ASC',
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

        if ($id && !$this->db->one('SELECT id FROM channels WHERE id = ?', [$id])) {
            flash('error', __('channel.not_found'));
            redirect('/admin/channels');
        }

        $nameRaw = (string)$this->request->input('name');
        $durationRaw = trim((string)$this->request->input('slide_duration_seconds', ''));
        $assignmentDisplayValues = (array)$this->request->input('assignment_display_id', []);
        $assignmentScheduleValues = (array)$this->request->input('assignment_schedule_id', []);
        $assignmentPriorityValues = (array)$this->request->input('assignment_priority', []);
        $name = trim($nameRaw);
        $description = trim((string)$this->request->input('description'));
        $effect = $this->sanitizeEffect((string)$this->request->input('transition_effect', 'inherit'), true);
        $duration = $durationRaw === '' ? null : max(1, (int)$durationRaw);
        $isActive = $this->request->input('is_active') ? 1 : 0;
        $old = [
            'name' => $nameRaw,
            'description' => (string)$this->request->input('description'),
            'transition_effect' => $effect,
            'slide_duration_seconds' => $durationRaw,
            'is_active' => $isActive,
            'assignment_display_id' => $assignmentDisplayValues,
            'assignment_schedule_id' => $assignmentScheduleValues,
            'assignment_priority' => $assignmentPriorityValues,
        ];
        $errors = [];

        if ($name === '') {
            $errors['name'] = __('channel.name_required');
        }
        if ($durationRaw !== '' && !$this->isPositiveInteger($durationRaw)) {
            $errors['slide_duration_seconds'] = __('validation.positive_number');
        }

        $assignmentErrors = [];
        $assignmentRows = $this->normalizeChannelAssignmentInput(
            $assignmentDisplayValues,
            $assignmentScheduleValues,
            $assignmentPriorityValues,
            $assignmentErrors
        );
        $errors = array_replace($errors, $assignmentErrors);
        if (!$assignmentRows) {
            $errors['assignment_display_id.0'] = $errors['assignment_display_id.0'] ?? __('channel.assignment_required');
        }
        if ($errors !== []) {
            $this->redirectWithForm(
                $id ? '/admin/channels/' . $id . '/edit' : '/admin/channels/create',
                __('validation.fix_marked_fields'),
                $old,
                $errors,
                'channel'
            );
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

            $this->db->execute('DELETE FROM channel_display_schedule_assignments WHERE channel_id = ?', [$channelId]);
            $nextPriorityByDisplay = [];
            foreach ($assignmentRows as $assignment) {
                $priority = $assignment['priority'];
                if ($priority === null) {
                    $displayId = $assignment['display_id'];
                    if (!isset($nextPriorityByDisplay[$displayId])) {
                        $nextPriorityByDisplay[$displayId] = (int)($this->db->one(
                            'SELECT COALESCE(MAX(priority), 0) + 1 AS next_priority FROM channel_display_schedule_assignments WHERE display_id = ?',
                            [$displayId]
                        )['next_priority'] ?? 1);
                    }
                    $priority = $nextPriorityByDisplay[$displayId]++;
                }
                $this->db->execute(
                    'INSERT INTO channel_display_schedule_assignments (display_id, channel_id, schedule_id, priority, is_active) VALUES (?, ?, ?, ?, 1)',
                    [$assignment['display_id'], $channelId, $assignment['schedule_id'], $priority]
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
        foreach ($this->normalizeIds($this->request->input('ids', [])) as $index => $assignmentId) {
            $this->db->execute('UPDATE channel_display_schedule_assignments SET priority = ? WHERE id = ? AND display_id = ?', [$index + 1, $assignmentId, $displayId]);
        }
        json_response(['ok' => true]);
    }

    public function schedules(): void
    {
        $this->auth->requireLogin();

        $schedules = $this->db->all(
            'SELECT s.id, s.name, s.type, s.is_system, s.is_active, s.created_at, s.updated_at,
                    COUNT(cdsa.id) AS assignment_count
             FROM schedules s
             LEFT JOIN channel_display_schedule_assignments cdsa ON cdsa.schedule_id = s.id
             GROUP BY s.id, s.name, s.type, s.is_system, s.is_active, s.created_at, s.updated_at
             ORDER BY s.is_system DESC, s.name ASC'
        );
        $rules = $this->db->all(
            'SELECT * FROM schedule_rules ORDER BY schedule_id ASC, weekday ASC, start_time ASC'
        );
        $rulesBySchedule = [];
        foreach ($rules as $rule) {
            $rulesBySchedule[(int)$rule['schedule_id']][] = $rule;
        }

        $this->view->render('admin/schedules', [
            'schedules' => $schedules,
            'rulesBySchedule' => $rulesBySchedule,
            'flash' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function scheduleForm(?int $id = null): void
    {
        $this->auth->requireLogin();

        $schedule = $id ? $this->db->one('SELECT * FROM schedules WHERE id = ?', [$id]) : null;
        if ($id && !$schedule) {
            flash('error', __('schedule.not_found'));
            redirect('/admin/schedules');
        }
        if ($schedule && !empty($schedule['is_system'])) {
            flash('error', __('schedule.system_not_editable'));
            redirect('/admin/schedules');
        }

        $rules = $id
            ? $this->db->all('SELECT * FROM schedule_rules WHERE schedule_id = ? ORDER BY weekday ASC, start_time ASC', [$id])
            : [];
        if (!$rules) {
            $rules = [['weekday' => '', 'start_time' => '', 'end_time' => '']];
        }

        $this->view->render('admin/schedule_form', [
            'schedule' => $schedule,
            'rules' => $rules,
            'error' => flash('error'),
        ]);
    }

    public function saveSchedule(?int $id = null): void
    {
        $this->auth->requireLogin();

        $schedule = $id ? $this->db->one('SELECT * FROM schedules WHERE id = ?', [$id]) : null;
        if ($id && !$schedule) {
            flash('error', __('schedule.not_found'));
            redirect('/admin/schedules');
        }
        if ($schedule && !empty($schedule['is_system'])) {
            flash('error', __('schedule.system_not_editable'));
            redirect('/admin/schedules');
        }

        $nameRaw = (string)$this->request->input('name');
        $name = trim($nameRaw);
        $isActive = $this->request->input('is_active') ? 1 : 0;
        $weekdayValues = (array)$this->request->input('rule_weekday', []);
        $startValues = (array)$this->request->input('rule_start_time', []);
        $endValues = (array)$this->request->input('rule_end_time', []);
        $old = [
            'name' => $nameRaw,
            'is_active' => $isActive,
            'rule_weekday' => $weekdayValues,
            'rule_start_time' => $startValues,
            'rule_end_time' => $endValues,
        ];
        $errors = [];
        if ($name === '') {
            $errors['name'] = __('schedule.name_required');
        }
        $ruleErrors = [];
        $rules = $this->normalizeScheduleRuleInput($weekdayValues, $startValues, $endValues, $ruleErrors);
        $errors = array_replace($errors, $ruleErrors);
        if (!$rules) {
            $errors['rule_weekday.0'] = $errors['rule_weekday.0'] ?? __('schedule.rule_required');
        }
        if ($errors !== []) {
            $this->redirectWithForm(
                $id ? '/admin/schedules/' . $id . '/edit' : '/admin/schedules/create',
                __('validation.fix_marked_fields'),
                $old,
                $errors,
                'schedule'
            );
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            if ($id) {
                $this->db->execute(
                    'UPDATE schedules SET name = ?, is_active = ? WHERE id = ?',
                    [$name, $isActive, $id]
                );
                $scheduleId = $id;
            } else {
                $this->db->execute(
                    'INSERT INTO schedules (name, type, is_system, is_active) VALUES (?, ?, 0, ?)',
                    [$name, 'weekly_time_slot', $isActive]
                );
                $scheduleId = (int)$this->db->lastInsertId();
            }

            $this->db->execute('DELETE FROM schedule_rules WHERE schedule_id = ?', [$scheduleId]);
            foreach ($rules as $rule) {
                $this->db->execute(
                    'INSERT INTO schedule_rules (schedule_id, weekday, start_time, end_time) VALUES (?, ?, ?, ?)',
                    [$scheduleId, $rule['weekday'], $rule['start_time'], $rule['end_time']]
                );
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        flash('success', __($id ? 'schedule.updated' : 'schedule.created'));
        redirect('/admin/schedules');
    }

    public function deleteSchedule(int $id): void
    {
        $this->auth->requireLogin();

        $schedule = $this->db->one('SELECT * FROM schedules WHERE id = ?', [$id]);
        if (!$schedule) {
            flash('error', __('schedule.not_found'));
            redirect('/admin/schedules');
        }
        if (!empty($schedule['is_system'])) {
            flash('error', __('schedule.system_not_deletable'));
            redirect('/admin/schedules');
        }

        $usage = (int)($this->db->one(
            'SELECT COUNT(*) AS cnt FROM channel_display_schedule_assignments WHERE schedule_id = ?',
            [$id]
        )['cnt'] ?? 0);
        if ($usage > 0) {
            flash('error', __('schedule.still_used'));
            redirect('/admin/schedules');
        }

        $this->db->execute('DELETE FROM schedules WHERE id = ?', [$id]);
        flash('success', __('schedule.deleted'));
        redirect('/admin/schedules');
    }

    public function slides(): void
    {
        $this->auth->requireLogin();

        $channels = $this->db->all('SELECT id, name, is_active FROM channels ORDER BY name ASC');
        $rows = $this->db->all(
            'SELECT c.id AS channel_id, c.name AS channel_name, s.id, s.name, s.slide_type, s.source_url, s.duration_seconds, s.is_active,
                    csa.sort_order, m.original_name AS media_name
             FROM channel_slide_assignments csa
             INNER JOIN channels c ON c.id = csa.channel_id
             INNER JOIN slides s ON s.id = csa.slide_id
             LEFT JOIN media_assets m ON m.id = s.media_asset_id
             ORDER BY c.name ASC, csa.sort_order ASC, s.name ASC'
        );

        $allSlides = $this->db->all(
            'SELECT s.id, s.name, s.slide_type, s.source_url, s.duration_seconds, s.is_active,
                    m.original_name AS media_name,
                    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name ASC SEPARATOR ", ") AS channel_names,
                    COUNT(DISTINCT c.id) AS channel_count
             FROM slides s
             LEFT JOIN channel_slide_assignments csa ON csa.slide_id = s.id
             LEFT JOIN channels c ON c.id = csa.channel_id
             LEFT JOIN media_assets m ON m.id = s.media_asset_id
             GROUP BY s.id, s.name, s.slide_type, s.source_url, s.duration_seconds, s.is_active, m.original_name
             ORDER BY s.name ASC'
        );
        $uniqueSlides = [];
        foreach ($allSlides as $slide) {
            $uniqueSlides[(int)$slide['id']] = $slide;
        }
        $allSlides = array_values($uniqueSlides);

        $groups = [];
        foreach ($channels as $channel) {
            $groups[(int)$channel['id']] = [
                'channel_name' => $channel['name'],
                'channel_id' => (int)$channel['id'],
                'is_active' => (int)$channel['is_active'],
                'slide_ids' => [],
                'slides' => [],
            ];
        }
        foreach ($rows as $row) {
            $key = (int)$row['channel_id'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'channel_name' => $row['channel_name'],
                    'channel_id' => $key,
                    'is_active' => 1,
                    'slide_ids' => [],
                    'slides' => [],
                ];
            }
            $groups[$key]['slide_ids'][] = (int)$row['id'];
            $groups[$key]['slides'][] = $row;
        }

        $this->view->render('admin/slides', [
            'groups' => $groups,
            'allSlides' => $allSlides,
            'flash' => flash('success'),
            'pluginLabels' => $this->plugins->getPluginLabelMap(),
        ]);
    }

    public function slideForm(?int $id = null): void
    {
        $this->auth->requireLogin();

        $slide = $id ? $this->db->one('SELECT * FROM slides WHERE id = ?', [$id]) : null;
        if ($id && !$slide) {
            flash('error', __('slide.not_found'));
            redirect('/admin/slides');
        }

        $channels = $this->db->all('SELECT id, name AS label, is_active FROM channels ORDER BY name ASC');
        $mediaAssets = $this->db->all('SELECT * FROM media_assets ORDER BY created_at DESC, id DESC');
        $imageMediaAssets = array_values(array_filter($mediaAssets, static fn(array $asset): bool => ($asset['media_kind'] ?? '') === 'image'));
        $assignedChannelIds = $id
            ? array_map(static fn(array $row): int => (int)$row['channel_id'], $this->db->all('SELECT channel_id FROM channel_slide_assignments WHERE slide_id = ?', [$id]))
            : [];
        $preselectedChannelId = (int)$this->request->input('channel_id', 0);
        if (!$id && $preselectedChannelId > 0 && $this->db->one('SELECT id FROM channels WHERE id = ?', [$preselectedChannelId])) {
            $assignedChannelIds = [$preselectedChannelId];
        }
        $oldSlideInput = form_has_old('slide') ? old_input('slide') : [];
        if (is_array($oldSlideInput['channel_ids'] ?? null)) {
            $assignedChannelIds = array_map(static fn(mixed $id): int => (int)$id, $oldSlideInput['channel_ids']);
        }
        $oldPluginSettings = is_array($oldSlideInput['plugin_settings'] ?? null) ? $oldSlideInput['plugin_settings'] : [];

        $pluginDefinitions = [];
        $pluginForms = [];
        foreach ($this->plugins->getEnabledPlugins() as $plugin) {
            $settings = $id ? $this->plugins->loadSlideSettings($id, $plugin->getName()) : $plugin->getDefaultSettings();
            if (is_array($oldPluginSettings[$plugin->getName()] ?? null)) {
                $settings = array_replace($settings, $oldPluginSettings[$plugin->getName()]);
            }
            $pluginDefinitions[] = [
                'name' => $plugin->getName(),
                'slide_type' => $plugin->getSlideType(),
                'display_name' => $plugin->getDisplayName(),
                'description' => (string)($plugin->getManifest()['description'] ?? ''),
            ];
            $pluginForms[$plugin->getName()] = $plugin->renderAdminSettings($slide ?? [], $settings, $this->plugins->buildApi(null, null, null, $this->auth->id()));
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
            'returnTo' => $this->adminReturnPath('/admin/slides'),
            'error' => flash('error'),
        ]);
    }

    public function saveSlide(?int $id = null): void
    {
        $this->auth->requireLogin();

        if ($id && !$this->db->one('SELECT id FROM slides WHERE id = ?', [$id])) {
            flash('error', __('slide.not_found'));
            redirect('/admin/slides');
        }

        $channelIds = $this->normalizeIds($this->request->input('channel_ids', []));
        $nameRaw = (string)$this->request->input('name');
        $name = trim($nameRaw);
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
        $pluginSettingsInput = (array)$this->request->input('plugin_settings', []);
        $old = [
            'channel_ids' => (array)$this->request->input('channel_ids', []),
            'name' => $nameRaw,
            'slide_type' => $slideType,
            'title_position' => (string)$this->request->input('title_position', 'bottom-left'),
            'duration_seconds' => $durationRaw,
            'source_mode' => $sourceMode,
            'source_url' => (string)$this->request->input('source_url'),
            'media_asset_id' => (string)$this->request->input('media_asset_id', ''),
            'text_markup' => (string)$this->request->input('text_markup', ''),
            'background_color' => (string)$this->request->input('background_color', '#0f172a'),
            'background_media_asset_id' => (string)$this->request->input('background_media_asset_id', ''),
            'plugin_settings' => $pluginSettingsInput,
            'is_active' => $isActive,
            'return_to' => (string)$this->request->input('return_to', '/admin/slides'),
        ];
        $errors = [];

        if (!$channelIds) {
            $errors['channel_ids'] = __('slide.assigned_channels_required');
        }
        if ($name === '') {
            $errors['name'] = __('slide.name_required');
        }
        if (!$this->isBuiltInSlideType($slideType) && !$isPluginType) {
            $errors['slide_type'] = __('slide.slide_type_required');
        }
        if ($durationRaw !== '' && !$this->isPositiveInteger($durationRaw)) {
            $errors['duration_seconds'] = __('validation.positive_number');
        }
        if (!in_array($sourceMode, ['external', 'media'], true)) {
            $errors['source_mode'] = __('slide.invalid_source_mode');
        }
        if ($errors !== []) {
            $this->redirectWithForm(
                $id ? '/admin/slides/' . $id . '/edit' : '/admin/slides/create',
                __('validation.fix_marked_fields'),
                $old,
                $errors,
                'slide'
            );
        }

        $pluginSettings = [];
        $mediaAsset = null;
        try {
            if ($isPluginType) {
                $submittedPluginSettings = (array)($pluginSettingsInput[$plugin->getName()] ?? []);
                $existingPluginSettings = $id ? $this->plugins->loadSlideSettings($id, $plugin->getName()) : [];
                $pluginSettings = $plugin->normalizeSettings($submittedPluginSettings, $existingPluginSettings, $this->plugins->buildApi(null, null, null, $this->auth->id()));
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
                    if ($this->uploadedMediaKind($uploadedBackground) !== 'image') {
                        throw new RuntimeException(__('slide.background_image_type_mismatch'));
                    }
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
                    $expectedKind = $slideType === 'image' ? 'image' : 'video';
                    $uploadedKind = $this->uploadedMediaKind($uploadedFile);
                    if ($uploadedKind !== null && $uploadedKind !== $expectedKind) {
                        throw new RuntimeException(__('slide.selected_media_type_mismatch'));
                    }
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
            $this->redirectWithForm(
                $id ? '/admin/slides/' . $id . '/edit' : '/admin/slides/create',
                $e->getMessage(),
                $old,
                $this->slideFieldErrors($slideType, $sourceMode, $plugin, $e->getMessage()),
                'slide'
            );
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

            $this->syncSlideChannelAssignments($slideId, $channelIds);

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
        redirect($this->adminReturnPath('/admin/slides'));
    }

    public function deleteSlide(int $id): void
    {
        $this->auth->requireLogin();
        $this->db->execute('DELETE FROM slides WHERE id = ?', [$id]);
        flash('success', __('slide.deleted'));
        redirect($this->adminReturnPath('/admin/slides'));
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
            redirect($this->adminReturnPath('/admin/slides'));
        }

        $this->db->execute(
            'DELETE FROM channel_slide_assignments WHERE slide_id = ? AND channel_id = ?',
            [$slideId, $channelId]
        );

        $this->reindexChannelSlides($channelId);

        flash('success', __('slide.removed_from_channel', ['slide' => $assignment['slide_name'], 'channel' => $assignment['channel_name']]));
        redirect($this->adminReturnPath('/admin/slides'));
    }

    public function addSlidesToChannel(int $channelId): void
    {
        $this->auth->requireLogin();

        $returnTo = $this->adminReturnPath('/admin/slides');
        $channel = $this->db->one('SELECT id, name FROM channels WHERE id = ?', [$channelId]);
        if (!$channel) {
            flash('error', __('channel.not_found'));
            redirect($returnTo);
        }

        $slideIds = $this->normalizeIds($this->request->input('slide_ids', []));
        if ($slideIds === []) {
            flash('error', __('slide.add_existing_none_selected'));
            redirect($returnTo);
        }

        $validSlideIds = array_fill_keys(array_map(
            static fn(array $row): int => (int)$row['id'],
            $this->db->all('SELECT id FROM slides')
        ), true);
        $existingSlideIds = array_fill_keys(array_map(
            static fn(array $row): int => (int)$row['slide_id'],
            $this->db->all('SELECT slide_id FROM channel_slide_assignments WHERE channel_id = ?', [$channelId])
        ), true);
        $slideIds = array_values(array_filter(
            $slideIds,
            static fn(int $slideId): bool => isset($validSlideIds[$slideId]) && !isset($existingSlideIds[$slideId])
        ));

        if ($slideIds === []) {
            flash('error', __('slide.add_existing_no_new_slides'));
            redirect($returnTo);
        }

        $nextSort = (int)($this->db->one(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM channel_slide_assignments WHERE channel_id = ?',
            [$channelId]
        )['next_sort'] ?? 1);

        foreach ($slideIds as $slideId) {
            $this->db->execute(
                'INSERT IGNORE INTO channel_slide_assignments (channel_id, slide_id, sort_order) VALUES (?, ?, ?)',
                [$channelId, $slideId, $nextSort++]
            );
        }

        flash('success', __('slide.added_to_channel', [
            'count' => count($slideIds),
            'channel' => $channel['name'],
        ]));
        redirect($returnTo);
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
        $old = ['name' => (string)$this->request->input('name')];
        try {
            $asset = $this->uploadManager->storeUploadedFile($this->request->file('media_file'), (int)$this->auth->id(), trim((string)$this->request->input('name')));
            if (!$asset) {
                throw new RuntimeException(__('media.choose_file'));
            }
            flash('success', __('media.uploaded'));
        } catch (RuntimeException $e) {
            $this->redirectWithForm('/admin/media', $e->getMessage(), $old, [
                'media_file' => $e->getMessage(),
            ], 'media_upload');
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
        if ($id && !$user) {
            flash('error', __('users.not_found'));
            redirect('/admin/users');
        }

        $this->view->render('admin/user_form', ['user' => $user, 'error' => flash('error')]);
    }

    public function saveUser(?int $id = null): void
    {
        $this->auth->requireRole('admin');
        if ($id && !$this->db->one('SELECT id FROM users WHERE id = ?', [$id])) {
            flash('error', __('users.not_found'));
            redirect('/admin/users');
        }

        $usernameRaw = (string)$this->request->input('username');
        $username = trim($usernameRaw);
        $displayName = trim((string)$this->request->input('display_name'));
        $role = (string)$this->request->input('role', 'editor');
        $password = (string)$this->request->input('password');
        $isActive = $this->request->input('is_active') ? 1 : 0;
        $old = [
            'username' => $usernameRaw,
            'display_name' => (string)$this->request->input('display_name'),
            'role' => $role,
            'is_active' => $isActive,
        ];
        $errors = [];

        if ($username === '') {
            $errors['username'] = __('users.username_required');
        }
        if (!in_array($role, ['admin', 'editor'], true)) {
            $errors['role'] = __('users.role_required');
        }
        if (!$id && $password === '') {
            $errors['password'] = __('users.password_required_new');
        }
        if ($errors !== []) {
            $this->redirectWithForm(
                $id ? '/admin/users/' . $id . '/edit' : '/admin/users/create',
                __('validation.fix_marked_fields'),
                $old,
                $errors,
                'user'
            );
        }

        $existing = $this->db->one('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]);
        if ($existing && (int)$existing['id'] !== (int)$id) {
            $this->redirectWithForm(
                $id ? '/admin/users/' . $id . '/edit' : '/admin/users/create',
                __('users.username_exists'),
                $old,
                ['username' => __('users.username_exists')],
                'user'
            );
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

    private function redirectWithForm(string $path, string $message, array $oldInput, array $errors, string $form = 'default'): void
    {
        flash('error', $message);
        flash_form_state($form, $oldInput, $errors);
        redirect($path);
    }

    private function isPositiveInteger(string $value): bool
    {
        return preg_match('/^[1-9][0-9]*$/', trim($value)) === 1;
    }

    private function isNonNegativeInteger(string $value): bool
    {
        return preg_match('/^[0-9]+$/', trim($value)) === 1;
    }

    private function isValidTimezone(string $timezone): bool
    {
        $timezone = trim($timezone);
        if ($timezone === '') {
            return false;
        }

        try {
            new \DateTimeZone($timezone);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    private function uploadedMediaKind(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '') {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo !== false ? (string)finfo_file($finfo, $tmpName) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return null;
    }

    private function pluginFieldErrors(SlidePluginInterface $plugin, string $message, bool $global = false): array
    {
        if (!$global) {
            return [];
        }

        if ($plugin->getName() === 'weather') {
            if ($this->messageMatches($message, [
                'plugins.weather.plugin.weather.error.invalid_weather_endpoint' => 'Weather plugin: invalid weather API endpoint.',
            ])) {
                return ['weather_base_url' => $message];
            }
            if ($this->messageMatches($message, [
                'plugins.weather.plugin.weather.error.invalid_geocoding_endpoint' => 'Weather plugin: invalid geocoding API endpoint.',
            ])) {
                return ['geocoding_base_url' => $message];
            }
        }

        if ($plugin->getName() === 'tl-1menu') {
            if ($this->messageMatches($message, ['plugins.tl-1menu.errors.background_asset_not_found'])) {
                return ['background_media_asset_id' => $message];
            }
            if ($this->messageMatches($message, ['plugins.tl-1menu.errors.background_invalid_type'])) {
                return [
                    'background_media_asset_id' => $message,
                    'background_image_file' => $message,
                ];
            }
        }

        return [];
    }

    private function slideFieldErrors(string $slideType, string $sourceMode, ?SlidePluginInterface $plugin, string $message): array
    {
        if ($plugin) {
            $pluginName = $plugin->getName();
            $prefix = 'plugin_settings.' . $pluginName . '.';

            if ($pluginName === 'weather') {
                if ($this->messageMatches($message, [
                    'plugins.weather.plugin.weather.error.location_required' => 'Weather plugin: please search and select a location.',
                    'plugins.weather.plugin.weather.error.location_not_found' => 'Weather plugin: no matching location found.',
                    'plugins.weather.plugin.weather.error.invalid_latitude' => 'Weather plugin: invalid latitude.',
                    'plugins.weather.plugin.weather.error.invalid_longitude' => 'Weather plugin: invalid longitude.',
                ])) {
                    return [$prefix . 'location_query' => $message];
                }
                if ($this->messageMatches($message, [
                    'plugins.weather.plugin.weather.error.invalid_temperature_unit' => 'Weather plugin: invalid temperature unit.',
                ])) {
                    return [$prefix . 'temperature_unit' => $message];
                }
                if ($this->messageMatches($message, [
                    'plugins.weather.plugin.weather.error.invalid_wind_speed_unit' => 'Weather plugin: invalid wind speed unit.',
                ])) {
                    return [$prefix . 'wind_speed_unit' => $message];
                }
                if ($this->messageMatches($message, [
                    'plugins.weather.plugin.weather.error.invalid_precipitation_unit' => 'Weather plugin: invalid precipitation unit.',
                ])) {
                    return [$prefix . 'precipitation_unit' => $message];
                }
            }

            if ($pluginName === 'tl-1menu') {
                if ($this->messageMatches($message, ['plugins.tl-1menu.errors.invalid_mensa'])) {
                    return [$prefix . 'mensa' => $message];
                }
                if ($this->messageMatches($message, ['plugins.tl-1menu.errors.invalid_language'])) {
                    return [$prefix . 'language' => $message];
                }
            }

            return ['plugin_settings.' . $pluginName => $message];
        }

        if ($this->messageMatches($message, ['slide.website_requires_url'])) {
            return ['source_url' => $message];
        }
        if ($this->messageMatches($message, ['slide.choose_media_or_upload'])) {
            return [
                'media_asset_id' => $message,
                'uploaded_file' => $message,
            ];
        }
        if ($this->messageMatches($message, ['slide.selected_media_not_found'])) {
            return $slideType === 'text'
                ? ['background_media_asset_id' => $message]
                : ['media_asset_id' => $message];
        }
        if ($this->messageMatches($message, ['slide.provide_external_or_media'])) {
            return [
                'source_url' => $message,
                'uploaded_file' => $message,
            ];
        }
        if ($this->messageMatches($message, ['slide.selected_media_type_mismatch'])) {
            return $sourceMode === 'media'
                ? ['media_asset_id' => $message]
                : ['uploaded_file' => $message];
        }
        if ($this->messageMatches($message, ['slide.background_image_type_mismatch'])) {
            return [
                'background_media_asset_id' => $message,
                'background_uploaded_file' => $message,
            ];
        }

        return ['slide_type' => $message];
    }

    private function messageMatches(string $message, array $keys): bool
    {
        foreach ($keys as $key => $default) {
            if (is_int($key)) {
                $key = (string)$default;
                $default = null;
            }

            if ($message === __((string)$key, [], is_string($default) ? $default : null)) {
                return true;
            }
            if (is_string($default) && $message === $default) {
                return true;
            }
        }

        return false;
    }

    private function adminReturnPath(string $default = '/admin/locations'): string
    {
        $returnTo = trim((string)$this->request->input('return_to', $default));
        return str_starts_with($returnTo, '/admin') ? $returnTo : $default;
    }

    private function getDisplayOrganizationRows(string $where = '', array $params = []): array
    {
        $sql = 'SELECT d.id, d.name, d.slug, d.description, d.orientation, d.is_active, d.sort_order,
                       dgm.group_id, dgm.layout_x, dgm.layout_y, dgm.layout_width, dgm.layout_height,
                       dgm.layout_rotation_degrees, dgm.bezel_top, dgm.bezel_right, dgm.bezel_bottom,
                       dgm.bezel_left, dgm.sort_order AS group_sort_order,
                       g.name AS group_name, g.location_id,
                       l.name AS location_name,
                       h.last_seen_at, h.screen_width, h.screen_height, h.avail_screen_width,
                       h.avail_screen_height, h.screen_orientation, h.current_channel_name
                FROM displays d
                LEFT JOIN display_group_memberships dgm ON dgm.display_id = d.id
                LEFT JOIN display_groups g ON g.id = dgm.group_id
                LEFT JOIN display_locations l ON l.id = g.location_id
                LEFT JOIN display_heartbeats h ON h.display_id = d.id';

        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        $sql .= ' ORDER BY COALESCE(l.sort_order, 999999) ASC,
                         l.name ASC,
                         COALESCE(g.sort_order, 999999) ASC,
                         g.name ASC,
                         COALESCE(dgm.sort_order, d.sort_order) ASC,
                         d.name ASC';

        $rows = $this->db->all($sql, $params);
        foreach ($rows as &$row) {
            $row['monitoring_status'] = $this->displayMonitoringStatus((int)$row['is_active'], $row['last_seen_at'] ?? null);
            $row['monitoring_label'] = $this->displayMonitoringLabel($row['monitoring_status']);
            $row['minutes_since_seen'] = $this->minutesSinceSeen($row['last_seen_at'] ?? null);
            $row['resolution_label'] = $this->resolutionLabel($row);
        }
        unset($row);

        return $rows;
    }

    private function defaultLayoutSize(array $display): array
    {
        $screenWidth = (int)($display['screen_width'] ?? 0);
        $screenHeight = (int)($display['screen_height'] ?? 0);

        if ($screenWidth > 0 && $screenHeight > 0) {
            $ratio = $screenWidth / max(1, $screenHeight);
            if ($ratio >= 1) {
                return [
                    'width' => 220,
                    'height' => max(96, (int)round(220 / $ratio)),
                ];
            }

            return [
                'width' => max(96, (int)round(220 * $ratio)),
                'height' => 220,
            ];
        }

        return ($display['orientation'] ?? 'landscape') === 'vertical'
            ? ['width' => 124, 'height' => 220]
            : ['width' => 220, 'height' => 124];
    }

    private function resolutionLabel(array $display): string
    {
        $width = (int)($display['screen_width'] ?? 0);
        $height = (int)($display['screen_height'] ?? 0);

        return $width > 0 && $height > 0
            ? $width . ' x ' . $height
            : __('common.unknown');
    }

    private function displayMonitoringStatus(int $isActive, ?string $lastSeenAt): string
    {
        if ($isActive !== 1) {
            return 'inactive';
        }

        if (!$lastSeenAt) {
            return 'never_seen';
        }

        $timestamp = strtotime($lastSeenAt);
        if ($timestamp === false) {
            return 'never_seen';
        }

        $seconds = max(0, time() - $timestamp);
        if ($seconds <= $this->monitoringOnlineThresholdSeconds()) {
            return 'online';
        }

        if ($seconds <= $this->monitoringStaleThresholdSeconds()) {
            return 'stale';
        }

        return 'offline';
    }

    private function displayMonitoringLabel(string $status): string
    {
        return match ($status) {
            'online' => __('monitoring.status_online', [], __('common.online')),
            'stale' => __('monitoring.status_stale', [], 'Stale'),
            'offline' => __('monitoring.status_offline', [], __('common.offline')),
            'never_seen' => __('monitoring.status_never_seen', [], 'Never seen'),
            'inactive' => __('common.inactive'),
            default => __('common.unknown'),
        };
    }

    private function monitoringOnlineThresholdSeconds(): int
    {
        return max(30, (int)app_config('monitoring.online_threshold_seconds', 180));
    }

    private function monitoringStaleThresholdSeconds(): int
    {
        return max($this->monitoringOnlineThresholdSeconds(), (int)app_config('monitoring.stale_threshold_seconds', 1800));
    }

    private function clampInt(mixed $value, int $min, int $max): int
    {
        return min($max, max($min, (int)$value));
    }

    private function normalizeLayoutRotation(int $rotation): int
    {
        $rotation %= 360;
        if ($rotation < 0) {
            $rotation += 360;
        }

        return in_array($rotation, [0, 90, 180, 270], true) ? $rotation : 0;
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
        $weekday = (int)$now->format('N');
        $currentTime = $now->format('H:i:s');

        return $this->db->one(
            'SELECT cdsa.channel_id, c.name AS channel_name
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

    private function syncSlideChannelAssignments(int $slideId, array $channelIds): void
    {
        $existingRows = $this->db->all(
            'SELECT channel_id FROM channel_slide_assignments WHERE slide_id = ?',
            [$slideId]
        );
        $existingChannelIds = [];
        foreach ($existingRows as $row) {
            $existingChannelIds[(int)$row['channel_id']] = true;
        }

        $selectedChannelIds = array_fill_keys($channelIds, true);
        $removedChannelIds = [];
        foreach (array_keys($existingChannelIds) as $channelId) {
            if (isset($selectedChannelIds[$channelId])) {
                continue;
            }

            $this->db->execute(
                'DELETE FROM channel_slide_assignments WHERE slide_id = ? AND channel_id = ?',
                [$slideId, $channelId]
            );
            $removedChannelIds[] = $channelId;
        }

        foreach ($channelIds as $channelId) {
            if (isset($existingChannelIds[$channelId])) {
                continue;
            }

            $nextSort = (int)($this->db->one(
                'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM channel_slide_assignments WHERE channel_id = ?',
                [$channelId]
            )['next_sort'] ?? 1);

            $this->db->execute(
                'INSERT INTO channel_slide_assignments (channel_id, slide_id, sort_order) VALUES (?, ?, ?)',
                [$channelId, $slideId, $nextSort]
            );
        }

        foreach (array_unique($removedChannelIds) as $channelId) {
            $this->reindexChannelSlides($channelId);
        }
    }

    private function reindexChannelSlides(int $channelId): void
    {
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
    }

    private function normalizeChannelAssignmentInput(array $displayValues, array $scheduleValues, array $priorityValues, array &$fieldErrors = []): array
    {
        $validDisplays = array_fill_keys(array_map(
            static fn (array $row): int => (int)$row['id'],
            $this->db->all('SELECT id FROM displays')
        ), true);
        $validSchedules = array_fill_keys(array_map(
            static fn (array $row): int => (int)$row['id'],
            $this->db->all('SELECT id FROM schedules')
        ), true);

        $rows = [];
        $seen = [];
        $count = max(count($displayValues), count($scheduleValues), count($priorityValues));
        for ($i = 0; $i < $count; $i++) {
            $displayRaw = trim((string)($displayValues[$i] ?? ''));
            $scheduleRaw = trim((string)($scheduleValues[$i] ?? ''));
            $priorityRaw = trim((string)($priorityValues[$i] ?? ''));

            if ($displayRaw === '' && $scheduleRaw === '' && $priorityRaw === '') {
                continue;
            }

            $rowHasError = false;
            if ($displayRaw === '') {
                $fieldErrors['assignment_display_id.' . $i] = __('channel.assignment_display_required');
                $rowHasError = true;
            } elseif (!ctype_digit($displayRaw) || !isset($validDisplays[(int)$displayRaw])) {
                $fieldErrors['assignment_display_id.' . $i] = __('channel.assignment_invalid_display');
                $rowHasError = true;
            }

            if ($scheduleRaw === '') {
                $fieldErrors['assignment_schedule_id.' . $i] = __('channel.assignment_schedule_required');
                $rowHasError = true;
            } elseif (!ctype_digit($scheduleRaw) || !isset($validSchedules[(int)$scheduleRaw])) {
                $fieldErrors['assignment_schedule_id.' . $i] = __('channel.assignment_invalid_schedule');
                $rowHasError = true;
            }

            $priority = null;
            if ($priorityRaw !== '') {
                if (!ctype_digit($priorityRaw) || (int)$priorityRaw < 1) {
                    $fieldErrors['assignment_priority.' . $i] = __('channel.assignment_invalid_priority');
                    $rowHasError = true;
                } else {
                    $priority = (int)$priorityRaw;
                }
            }

            if ($rowHasError) {
                continue;
            }

            $displayId = (int)$displayRaw;
            $scheduleId = (int)$scheduleRaw;

            $key = $displayId . ':' . $scheduleId;
            if (isset($seen[$key])) {
                $fieldErrors['assignment_display_id.' . $i] = __('channel.assignment_duplicate');
                $fieldErrors['assignment_schedule_id.' . $i] = __('channel.assignment_duplicate');
                continue;
            }
            $seen[$key] = true;
            $rows[] = [
                'display_id' => $displayId,
                'schedule_id' => $scheduleId,
                'priority' => $priority,
            ];
        }

        return $rows;
    }

    private function normalizeScheduleRuleInput(array $weekdayValues, array $startValues, array $endValues, array &$fieldErrors = []): array
    {
        $rules = [];
        $seen = [];
        $count = max(count($weekdayValues), count($startValues), count($endValues));
        for ($i = 0; $i < $count; $i++) {
            $weekdayRaw = trim((string)($weekdayValues[$i] ?? ''));
            $startRaw = trim((string)($startValues[$i] ?? ''));
            $endRaw = trim((string)($endValues[$i] ?? ''));

            if ($weekdayRaw === '' && $startRaw === '' && $endRaw === '') {
                continue;
            }
            if (!ctype_digit($weekdayRaw) || (int)$weekdayRaw < 1 || (int)$weekdayRaw > 7) {
                $fieldErrors['rule_weekday.' . $i] = __('schedule.invalid_weekday');
            }

            $start = $this->normalizeTimeInput($startRaw);
            $end = $this->normalizeTimeInput($endRaw);
            if ($start === null) {
                $fieldErrors['rule_start_time.' . $i] = __('schedule.invalid_start_time');
            }
            if ($end === null) {
                $fieldErrors['rule_end_time.' . $i] = __('schedule.invalid_end_time');
            }
            if (isset($fieldErrors['rule_weekday.' . $i], $fieldErrors['rule_start_time.' . $i], $fieldErrors['rule_end_time.' . $i])) {
                continue;
            }
            if (isset($fieldErrors['rule_weekday.' . $i]) || isset($fieldErrors['rule_start_time.' . $i]) || isset($fieldErrors['rule_end_time.' . $i])) {
                continue;
            }
            if ($start >= $end) {
                $fieldErrors['rule_end_time.' . $i] = __('schedule.no_day_overflow');
                continue;
            }

            $weekday = (int)$weekdayRaw;
            $key = $weekday . ':' . $start . ':' . $end;
            if (isset($seen[$key])) {
                $fieldErrors['rule_weekday.' . $i] = __('schedule.duplicate_rule');
                continue;
            }
            $seen[$key] = true;
            $rules[] = [
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
            ];
        }

        return $rules;
    }

    private function normalizeTimeInput(string $value): ?string
    {
        if (!preg_match('/^(\d{2}):(\d{2})$/', $value, $matches)) {
            return null;
        }

        $hour = (int)$matches[1];
        $minute = (int)$matches[2];
        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    private function normalizeIds(mixed $value): array
    {
        $ids = is_array($value) ? $value : [$value];
        return array_values(array_unique(array_filter(array_map(static fn($id) => (int)$id, $ids))));
    }
}
