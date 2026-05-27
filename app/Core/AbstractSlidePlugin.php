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
        return $this->localizedManifestValue('display_name', (string)($this->manifest['name'] ?? ''));
    }

    public function getDescription(): string
    {
        return $this->localizedManifestValue('description', '');
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

    public function handleAdminAction(string $action, array $input, PluginApi $api): array
    {
        throw new \RuntimeException(__('plugins.action_not_found', [], 'Plugin action not found.'));
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

    protected function localizedManifestValue(string $key, string $default = ''): string
    {
        $value = $this->manifest[$key] ?? null;
        if (is_scalar($value)) {
            $text = trim((string)$value);
            return $text !== '' ? $text : $default;
        }

        if (!is_array($value)) {
            return $default;
        }

        foreach ($this->localeCandidates() as $locale) {
            if (array_key_exists($locale, $value) && is_scalar($value[$locale])) {
                $text = trim((string)$value[$locale]);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        foreach ($value as $text) {
            if (is_scalar($text) && trim((string)$text) !== '') {
                return trim((string)$text);
            }
        }

        return $default;
    }

    private function localeCandidates(): array
    {
        $locales = [];
        $i18n = $GLOBALS['i18n'] ?? null;
        if ($i18n && method_exists($i18n, 'getLocale')) {
            $locales[] = (string)$i18n->getLocale();
        } else {
            $locales[] = current_locale();
        }
        if ($i18n && method_exists($i18n, 'getFallbackLocale')) {
            $locales[] = (string)$i18n->getFallbackLocale();
        }
        $locales[] = 'en';
        $locales[] = 'default';

        $candidates = [];
        foreach ($locales as $locale) {
            $locale = trim($locale);
            if ($locale === '') {
                continue;
            }
            foreach ([$locale, str_replace('-', '_', $locale), str_replace('_', '-', $locale)] as $candidate) {
                $candidates[] = $candidate;
                $base = preg_split('/[-_]/', $candidate)[0] ?? '';
                if ($base !== '') {
                    $candidates[] = $base;
                }
            }
        }

        return array_values(array_unique($candidates));
    }
}
