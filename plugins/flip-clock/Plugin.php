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

namespace Plugins\FlipClock;

use App\Core\AbstractSlidePlugin;
use App\Core\PluginApi;
use RuntimeException;

class Plugin extends AbstractSlidePlugin
{
    private const DEFAULT_BACKGROUND_COLOR = '#111827';

    public function getDefaultSettings(): array
    {
        return [
            'background_color' => '',
            'background_media_asset_id' => null,
            'show_seconds' => false,
        ];
    }

    public function renderAdminSettings(array $slide, array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        $settings['background_color'] = $this->normalizeColor(
            (string)($settings['background_color'] ?? ''),
            $this->huginDefaultBackgroundColor($api)
        );
        $backgroundAssetId = $this->normalizeOptionalId($settings['background_media_asset_id'] ?? null);
        $backgroundAsset = $backgroundAssetId !== null ? $api->getMediaAsset($backgroundAssetId) : null;
        if (($backgroundAsset['media_kind'] ?? null) !== 'image') {
            $backgroundAsset = null;
        }

        return $this->renderView('views/config.php', [
            'settings' => $settings,
            'plugin' => $this,
            'imageMediaAssets' => $api->listMediaAssets('image'),
            'backgroundImageUrl' => $api->mediaAssetUrl($backgroundAsset),
        ]);
    }

    public function normalizeSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        $existingSettings = array_replace($this->getDefaultSettings(), $existingSettings);
        $backgroundColor = $this->normalizeColor(
            (string)($input['background_color'] ?? $existingSettings['background_color'] ?? ''),
            $this->huginDefaultBackgroundColor($api)
        );
        $backgroundMediaAssetId = $this->normalizeOptionalId($input['background_media_asset_id'] ?? null);

        $uploadedBackground = $api->pluginUploadedFile($this->getName(), 'background_image_file');
        if ($uploadedBackground && ($uploadedBackground['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $this->assertUploadedImage($uploadedBackground);
            $mediaAsset = $api->storeMediaAsset($uploadedBackground, $this->getDisplayName() . ' background');
            if (!$mediaAsset || ($mediaAsset['media_kind'] ?? '') !== 'image') {
                throw new RuntimeException(__('plugins.flip-clock.errors.background_invalid_type'));
            }
            $backgroundMediaAssetId = (int)$mediaAsset['id'];
        } elseif ($backgroundMediaAssetId !== null) {
            $mediaAsset = $api->getMediaAsset($backgroundMediaAssetId);
            if (!$mediaAsset) {
                throw new RuntimeException(__('plugins.flip-clock.errors.background_asset_not_found'));
            }
            if (($mediaAsset['media_kind'] ?? '') !== 'image') {
                throw new RuntimeException(__('plugins.flip-clock.errors.background_invalid_type'));
            }
        }

        return [
            'background_color' => $backgroundColor,
            'background_media_asset_id' => $backgroundMediaAssetId,
            'show_seconds' => !empty($input['show_seconds']),
        ];
    }

    public function renderFrontend(array $slide, array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        $backgroundColor = $this->normalizeColor((string)($settings['background_color'] ?? ''), $this->huginDefaultBackgroundColor($api));
        $backgroundAssetId = $this->normalizeOptionalId($settings['background_media_asset_id'] ?? null);
        $backgroundAsset = $backgroundAssetId !== null ? $api->getMediaAsset($backgroundAssetId) : null;
        if (($backgroundAsset['media_kind'] ?? null) !== 'image') {
            $backgroundAsset = null;
        }

        return $this->renderView('views/render.php', [
            'settings' => $settings,
            'backgroundColor' => $backgroundColor,
            'backgroundImageUrl' => $api->mediaAssetUrl($backgroundAsset),
            'showSeconds' => !empty($settings['show_seconds']),
        ]);
    }

    public function getFrontendAssets(array $slide, array $settings, PluginApi $api): array
    {
        return [
            'css' => [$api->pluginAssetUrl($this->getName(), 'assets/flip-clock.css')],
            'js' => [$api->pluginAssetUrl($this->getName(), 'assets/flip-clock.js')],
        ];
    }

    public function getStateData(array $slide, array $settings, PluginApi $api): array
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);

        return [
            'settings' => [
                'background_color' => $this->normalizeColor((string)($settings['background_color'] ?? ''), $this->huginDefaultBackgroundColor($api)),
                'background_media_asset_id' => $this->normalizeOptionalId($settings['background_media_asset_id'] ?? null),
                'show_seconds' => !empty($settings['show_seconds']),
            ],
        ];
    }

    private function normalizeColor(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value !== '' && $value[0] !== '#') {
            $value = '#' . $value;
        }

        return normalize_hex_color($value, normalize_hex_color($fallback, self::DEFAULT_BACKGROUND_COLOR));
    }

    private function normalizeOptionalId(mixed $value): ?int
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        $id = (int)$value;
        return $id > 0 ? $id : null;
    }

    private function huginDefaultBackgroundColor(PluginApi $api): string
    {
        return $this->normalizeColor(
            (string)$api->getHuginSetting('branding', 'default_background_color', self::DEFAULT_BACKGROUND_COLOR),
            self::DEFAULT_BACKGROUND_COLOR
        );
    }

    /** @param array<string, mixed> $file */
    private function assertUploadedImage(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(__('plugins.flip-clock.errors.background_upload_failed'));
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '') {
            throw new RuntimeException(__('plugins.flip-clock.errors.background_upload_failed'));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo !== false ? (string)finfo_file($finfo, $tmpName) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            throw new RuntimeException(__('plugins.flip-clock.errors.background_invalid_type'));
        }
    }
}
