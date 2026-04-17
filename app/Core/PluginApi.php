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

class PluginApi
{
    public function __construct(
        private Database $db,
        private PluginManager $plugins,
        private ?array $display = null,
        private ?array $channel = null,
        private ?array $heartbeat = null,
        private ?array $systemMeta = null,
    ) {
    }

    public function loadSlideSettings(int $slideId, string $pluginName): array
    {
        return $this->plugins->loadSlideSettings($slideId, $pluginName);
    }

    public function saveSlideSettings(int $slideId, string $pluginName, array $settings): void
    {
        $this->plugins->saveSlideSettings($slideId, $pluginName, $settings);
    }

    public function deleteSlideSettings(int $slideId, string $pluginName): void
    {
        $this->plugins->deleteSlideSettings($slideId, $pluginName);
    }

    public function getDisplay(): ?array
    {
        return $this->display;
    }

    public function getChannel(): ?array
    {
        return $this->channel;
    }

    public function getHeartbeat(): ?array
    {
        return $this->heartbeat;
    }

    public function getSystemMeta(): array
    {
        return $this->systemMeta ?? [
            'app_name' => (string)app_config('app.name', 'Hugin'),
            'plugin_api_version' => 1,
        ];
    }

    public function getScreenMetadata(): array
    {
        return $this->heartbeat ?? [];
    }

    public function pluginAssetUrl(string $pluginName, string $relativePath): string
    {
        return url('/plugin-assets/' . rawurlencode($pluginName) . '/' . ltrim($relativePath, '/'));
    }
}
