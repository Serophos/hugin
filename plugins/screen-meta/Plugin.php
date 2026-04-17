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

namespace Plugins\ScreenMeta;

use App\Core\AbstractSlidePlugin;
use App\Core\PluginApi;
use RuntimeException;

class Plugin extends AbstractSlidePlugin
{
    public function getDefaultSettings(): array
    {
        return [
            'heading' => __('plugins.screen-meta.defaults.heading'),
            'show_browser' => true,
            'show_os' => true,
            'show_resolution' => true,
            'show_viewport' => true,
            'show_ip' => true,
            'show_timezone' => true,
            'note' => '',
        ];
    }

    public function renderAdminSettings(array $slide, array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        return $this->renderView('views/config.php', [
            'settings' => $settings,
            'plugin' => $this,
        ]);
    }

    public function normalizeSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        $defaults = $this->getDefaultSettings();
        $settings = [
            'heading' => trim((string)($input['heading'] ?? $defaults['heading'])),
            'show_browser' => !empty($input['show_browser']),
            'show_os' => !empty($input['show_os']),
            'show_resolution' => !empty($input['show_resolution']),
            'show_viewport' => !empty($input['show_viewport']),
            'show_ip' => !empty($input['show_ip']),
            'show_timezone' => !empty($input['show_timezone']),
            'note' => trim((string)($input['note'] ?? '')),
        ];

        if ($settings['heading'] === '') {
            throw new RuntimeException(__('plugins.screen-meta.errors.heading_required'));
        }

        return $settings;
    }

    public function renderFrontend(array $slide, array $settings, PluginApi $api): string
    {
        $display = $api->getDisplay() ?? [];
        $heartbeat = $api->getHeartbeat() ?? [];
        $settings = array_replace($this->getDefaultSettings(), $settings);

        $rows = [];
        if ($settings['show_browser']) {
            $rows[__('plugins.screen-meta.rows.browser')] = trim(((string)($heartbeat['browser_name'] ?? __('common.unknown', [], 'Unknown'))) . ' ' . ((string)($heartbeat['browser_version'] ?? '')));
        }
        if ($settings['show_os']) {
            $rows[__('plugins.screen-meta.rows.operating_system')] = trim(((string)($heartbeat['os_name'] ?? __('common.unknown', [], 'Unknown'))) . ' ' . ((string)($heartbeat['os_version'] ?? '')));
        }
        if ($settings['show_resolution']) {
            $rows[__('plugins.screen-meta.rows.screen_resolution')] = ($heartbeat['screen_width'] ?? '?') . ' × ' . ($heartbeat['screen_height'] ?? '?');
        }
        if ($settings['show_viewport']) {
            $rows[__('plugins.screen-meta.rows.viewport')] = ($heartbeat['viewport_width'] ?? '?') . ' × ' . ($heartbeat['viewport_height'] ?? '?');
        }
        if ($settings['show_ip']) {
            $rows[__('plugins.screen-meta.rows.client_ip')] = (string)($heartbeat['last_seen_ip'] ?? __('common.unknown'));
        }
        if ($settings['show_timezone']) {
            $rows[__('plugins.screen-meta.rows.timezone')] = (string)(($heartbeat['client_timezone'] ?? '') ?: ($display['timezone'] ?? 'UTC'));
        }
        $rows[__('plugins.screen-meta.rows.display')] = (string)($display['name'] ?? __('plugins.screen-meta.rows.unknown_display'));
        $rows[__('plugins.screen-meta.rows.orientation')] = enum_label('orientations', (string)(($display['orientation'] ?? 'landscape')), (string)($display['orientation'] ?? 'landscape'));

        return $this->renderView('views/render.php', [
            'settings' => $settings,
            'rows' => $rows,
        ]);
    }

    public function getFrontendAssets(array $slide, array $settings, PluginApi $api): array
    {
        return ['css' => [$api->pluginAssetUrl($this->getName(), 'assets/screen-meta.css')], 'js' => []];
    }

    public function getStateData(array $slide, array $settings, PluginApi $api): array
    {
        $heartbeat = $api->getHeartbeat() ?? [];
        return [
            'settings' => $settings,
            'heartbeat_last_seen_at' => $heartbeat['last_seen_at'] ?? null,
            'heartbeat_browser' => $heartbeat['browser_name'] ?? null,
            'heartbeat_resolution' => [
                'screen_width' => $heartbeat['screen_width'] ?? null,
                'screen_height' => $heartbeat['screen_height'] ?? null,
                'viewport_width' => $heartbeat['viewport_width'] ?? null,
                'viewport_height' => $heartbeat['viewport_height'] ?? null,
            ],
        ];
    }
}
