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

namespace App\Core;

class PluginManager
{
    private ?array $instances = null;

    public function __construct(private Database $db, private string $pluginsPath)
    {
    }

    public function syncRegistry(): void
    {
        foreach ($this->discoverAll() as $plugin) {
            $manifest = $plugin->getManifest();
            $json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $row = $this->db->one('SELECT plugin_name FROM plugins WHERE plugin_name = ?', [$plugin->getName()]);
            if ($row) {
                $this->db->execute(
                    'UPDATE plugins SET display_name = ?, version = ?, description = ?, slide_type = ?, manifest_json = ? WHERE plugin_name = ?',
                    [
                        $plugin->getDisplayName(),
                        (string)($manifest['version'] ?? '1.0.0'),
                        $plugin->getDescription(),
                        $plugin->getSlideType(),
                        $json,
                        $plugin->getName(),
                    ]
                );
            } else {
                $this->db->execute(
                    'INSERT INTO plugins (plugin_name, display_name, version, description, slide_type, is_enabled, manifest_json) VALUES (?, ?, ?, ?, ?, 1, ?)',
                    [
                        $plugin->getName(),
                        $plugin->getDisplayName(),
                        (string)($manifest['version'] ?? '1.0.0'),
                        $plugin->getDescription(),
                        $plugin->getSlideType(),
                        $json,
                    ]
                );
            }
        }
    }

    /** @return array<string, SlidePluginInterface> */
    public function discoverAll(): array
    {
        if ($this->instances !== null) {
            return $this->instances;
        }

        $instances = [];
        if (!is_dir($this->pluginsPath)) {
            return $this->instances = [];
        }

        foreach (scandir($this->pluginsPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $root = $this->pluginsPath . '/' . $entry;
            if (!is_dir($root)) {
                continue;
            }
            $manifestFile = $root . '/plugin.json';
            if (!is_file($manifestFile)) {
                continue;
            }
            $manifest = json_decode((string)file_get_contents($manifestFile), true);
            if (!is_array($manifest)) {
                continue;
            }
            $manifest['name'] = $manifest['name'] ?? $entry;
            $mainFile = $root . '/' . ($manifest['main'] ?? 'Plugin.php');
            $className = (string)($manifest['class'] ?? 'Plugin');
            if (!is_file($mainFile)) {
                continue;
            }
            require_once $mainFile;
            if (!class_exists($className)) {
                continue;
            }
            $instance = new $className($manifest, $root);
            if (!$instance instanceof SlidePluginInterface) {
                continue;
            }
            $instances[$instance->getName()] = $instance;
        }

        return $this->instances = $instances;
    }

    /** @return SlidePluginInterface[] */
    public function getEnabledPlugins(): array
    {
        $plugins = [];
        $rows = $this->db->all('SELECT plugin_name, is_enabled FROM plugins');
        $enabledMap = [];
        foreach ($rows as $row) {
            $enabledMap[$row['plugin_name']] = (int)$row['is_enabled'] === 1;
        }

        foreach ($this->discoverAll() as $plugin) {
            if (($enabledMap[$plugin->getName()] ?? true) === true) {
                $plugins[$plugin->getName()] = $plugin;
            }
        }

        return $plugins;
    }

    public function getEnabledPluginNames(): array
    {
        return array_keys($this->getEnabledPlugins());
    }

    public function getPlugin(string $name): ?SlidePluginInterface
    {
        return $this->discoverAll()[$name] ?? null;
    }

    public function getPluginBySlideType(string $slideType, bool $enabledOnly = true): ?SlidePluginInterface
    {
        $plugins = $enabledOnly ? $this->getEnabledPlugins() : $this->discoverAll();
        foreach ($plugins as $plugin) {
            if ($plugin->getSlideType() === $slideType) {
                return $plugin;
            }
        }

        return null;
    }

    public function isPluginSlideType(string $slideType): bool
    {
        return $this->getPluginBySlideType($slideType, false) !== null;
    }

    public function listForAdmin(): array
    {
        $dbRows = $this->db->all('SELECT * FROM plugins ORDER BY display_name ASC, plugin_name ASC');
        $dbMap = [];
        foreach ($dbRows as $row) {
            $dbMap[$row['plugin_name']] = $row;
        }

        $items = [];
        foreach ($this->discoverAll() as $plugin) {
            $row = $dbMap[$plugin->getName()] ?? null;
            $manifest = $plugin->getManifest();
            $items[] = [
                'plugin_name' => $plugin->getName(),
                'display_name' => $plugin->getDisplayName(),
                'version' => (string)($manifest['version'] ?? '1.0.0'),
                'description' => $plugin->getDescription(),
                'slide_type' => $plugin->getSlideType(),
                'api_version' => (string)($manifest['api_version'] ?? '1'),
                'is_enabled' => $row ? (int)$row['is_enabled'] : 1,
                'source_status' => 'available',
            ];
        }

        return $items;
    }

    public function setEnabled(string $pluginName, bool $enabled): void
    {
        $this->db->execute('UPDATE plugins SET is_enabled = ? WHERE plugin_name = ?', [$enabled ? 1 : 0, $pluginName]);
    }

    public function loadSlideSettings(int $slideId, string $pluginName): array
    {
        $row = $this->db->one('SELECT settings_json FROM slide_plugin_data WHERE slide_id = ? AND plugin_name = ?', [$slideId, $pluginName]);
        if (!$row || !is_string($row['settings_json']) || trim($row['settings_json']) === '') {
            return [];
        }
        $data = json_decode($row['settings_json'], true);
        return is_array($data) ? $data : [];
    }

    public function saveSlideSettings(int $slideId, string $pluginName, array $settings): void
    {
        $json = json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->db->execute(
            'INSERT INTO slide_plugin_data (slide_id, plugin_name, settings_json) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE settings_json = VALUES(settings_json)',
            [$slideId, $pluginName, $json]
        );
    }

    public function deleteSlideSettings(int $slideId, ?string $pluginName = null): void
    {
        if ($pluginName === null) {
            $this->db->execute('DELETE FROM slide_plugin_data WHERE slide_id = ?', [$slideId]);
            return;
        }
        $this->db->execute('DELETE FROM slide_plugin_data WHERE slide_id = ? AND plugin_name = ?', [$slideId, $pluginName]);
    }

    public function buildApi(?array $display = null, ?array $channel = null, ?array $heartbeat = null): PluginApi
    {
        return new PluginApi($this->db, $this, $display, $channel, $heartbeat, [
            'app_name' => (string)app_config('app.name', __('app.name', [], 'Hugin')),
            'plugin_api_version' => 1,
        ]);
    }

    public function getPluginLabelMap(): array
    {
        $labels = [];
        foreach ($this->discoverAll() as $plugin) {
            $labels[$plugin->getSlideType()] = $plugin->getDisplayName();
        }
        return $labels;
    }

    public function serveAsset(string $pluginName, string $assetPath): void
    {
        $plugin = $this->getPlugin($pluginName);
        if (!$plugin) {
            http_response_code(404);
            echo __('plugins.not_found', [], 'Plugin not found.');
            exit;
        }

        $root = realpath($this->pluginsPath . '/' . $pluginName);
        if ($root === false) {
            http_response_code(404);
            echo __('plugins.not_found', [], 'Plugin not found.');
            exit;
        }

        $path = realpath($root . '/' . ltrim($assetPath, '/'));
        if ($path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            http_response_code(404);
            echo __('plugins.asset_not_found', [], 'Asset not found.');
            exit;
        }

        $mimeMap = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
        ];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
        readfile($path);
        exit;
    }
}
