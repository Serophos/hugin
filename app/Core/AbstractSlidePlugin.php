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

abstract class AbstractSlidePlugin implements SlidePluginInterface
{
    public function __construct(protected array $manifest, protected string $rootPath)
    {
    }

    public function getName(): string
    {
        return (string)$this->manifest['name'];
    }

    public function getManifest(): array
    {
        return $this->manifest;
    }

    public function getSlideType(): string
    {
        return (string)$this->manifest['slide_type'];
    }

    public function getDisplayName(): string
    {
        return $this->t('meta.display_name', (string)($this->manifest['display_name'] ?? $this->manifest['name']));
    }

    public function getDescription(): string
    {
        return $this->t('meta.description', (string)($this->manifest['description'] ?? ''));
    }

    public function getDefaultSettings(): array
    {
        return [];
    }

    public function getDefaultGlobalSettings(): array
    {
        return [];
    }

    public function renderGlobalSettings(array $settings, PluginApi $api): string
    {
        return '';
    }

    public function normalizeGlobalSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        return array_replace($this->getDefaultGlobalSettings(), $existingSettings, $input);
    }

    public function getFrontendAssets(array $slide, array $settings, PluginApi $api): array
    {
        return ['css' => [], 'js' => []];
    }

    public function getStateData(array $slide, array $settings, PluginApi $api): array
    {
        return $settings;
    }

    protected function renderView(string $relativePath, array $data = []): string
    {
        $file = $this->rootPath . '/' . ltrim($relativePath, '/');
        if (!is_file($file)) {
            return '';
        }

        extract($data);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }

    protected function t(string $key, string $default = ''): string
    {
        return __('plugins.' . $this->getName() . '.' . ltrim($key, '.'), [], $default);
    }
}
