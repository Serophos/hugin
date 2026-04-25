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
        private ?UploadManager $uploadManager = null,
        private ?int $currentUserId = null,
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

    public function loadGlobalSettings(string $pluginName): array
    {
        return $this->plugins->loadGlobalSettings($pluginName);
    }

    public function saveGlobalSettings(string $pluginName, array $settings): void
    {
        $this->plugins->saveGlobalSettings($pluginName, $settings);
    }

    public function listMediaAssets(?string $kind = null): array
    {
        if ($kind !== null && !in_array($kind, ['image', 'video'], true)) {
            throw new \RuntimeException(__('media.invalid_kind', [], 'Invalid media kind.'));
        }

        if ($kind === null) {
            return $this->db->all('SELECT * FROM media_assets ORDER BY created_at DESC, id DESC');
        }

        return $this->db->all('SELECT * FROM media_assets WHERE media_kind = ? ORDER BY created_at DESC, id DESC', [$kind]);
    }

    public function getMediaAsset(int $id): ?array
    {
        return $this->db->one('SELECT * FROM media_assets WHERE id = ?', [$id]);
    }

    public function mediaAssetUrl(int|array|null $asset): ?string
    {
        if ($asset === null) {
            return null;
        }
        if (is_int($asset)) {
            $asset = $this->getMediaAsset($asset);
        }
        if (!is_array($asset) || empty($asset['file_path'])) {
            return null;
        }

        return url((string)$asset['file_path']);
    }

    public function pluginUploadedFile(string $pluginName, string $field, string $root = 'plugin_settings'): ?array
    {
        $files = $_FILES[$root] ?? null;
        if (!is_array($files) || !isset($files['name'][$pluginName][$field])) {
            return null;
        }

        return [
            'name' => $files['name'][$pluginName][$field] ?? '',
            'type' => $files['type'][$pluginName][$field] ?? '',
            'tmp_name' => $files['tmp_name'][$pluginName][$field] ?? '',
            'error' => $files['error'][$pluginName][$field] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$pluginName][$field] ?? 0,
        ];
    }

    public function storeMediaAsset(?array $file, string $label = ''): ?array
    {
        if (!$this->uploadManager || !$this->currentUserId) {
            throw new \RuntimeException(__('plugins.media_upload_unavailable', [], 'Media upload is not available in this plugin context.'));
        }

        return $this->uploadManager->storeUploadedFile($file, $this->currentUserId, $label);
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
            'plugin_api_version' => 2,
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

    public function pluginStoragePath(string $pluginName, string $relativePath = ''): string
    {
        return $this->resolvePluginStoragePath('storage/plugins', $pluginName, $relativePath);
    }

    public function pluginCachePath(string $pluginName, string $relativePath = ''): string
    {
        return $this->resolvePluginStoragePath('storage/cache/plugins', $pluginName, $relativePath);
    }

    private function resolvePluginStoragePath(string $baseRelativePath, string $pluginName, string $relativePath): string
    {
        $safePluginName = preg_replace('/[^a-zA-Z0-9_-]/', '', $pluginName);
        if ($safePluginName === '' || $safePluginName !== $pluginName) {
            throw new \RuntimeException(__('plugins.invalid_plugin_name', [], 'Invalid plugin name.'));
        }

        $relativePath = str_replace('\\', '/', trim($relativePath));
        $relativePath = trim($relativePath, '/');
        if ($relativePath !== '' && preg_match('#(^|/)\.\.(/|$)#', $relativePath)) {
            throw new \RuntimeException(__('plugins.invalid_storage_path', [], 'Invalid plugin storage path.'));
        }

        $root = (string)app_config('paths.root', dirname(__DIR__, 2));
        $base = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $baseRelativePath) . DIRECTORY_SEPARATOR . $safePluginName;
        if (!is_dir($base) && !mkdir($base, 0775, true) && !is_dir($base)) {
            throw new \RuntimeException(__('plugins.storage_directory_failed', [], 'Plugin storage directory could not be created.'));
        }

        if ($relativePath === '') {
            return $base;
        }

        $path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(__('plugins.storage_directory_failed', [], 'Plugin storage directory could not be created.'));
        }

        return $path;
    }
}
