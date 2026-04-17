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

class I18n
{
    private string $locale;
    private string $fallbackLocale;
    /** @var array<string, array<string, string>> */
    private array $messages = [];

    public function __construct(string $locale = 'en', ?string $fallbackLocale = null)
    {
        $locale = trim($locale) ?: 'en';
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale ?: $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
    }

    public function loadFile(string $locale, string $file, string $namespace = ''): void
    {
        if (!is_file($file)) {
            return;
        }

        $messages = require $file;
        if (!is_array($messages)) {
            return;
        }

        $this->register($locale, $messages, $namespace);
    }

    public function register(string $locale, array $messages, string $namespace = ''): void
    {
        $flat = $this->flatten($messages);
        if ($namespace !== '') {
            $prefix = rtrim($namespace, '.') . '.';
            $prefixed = [];
            foreach ($flat as $key => $value) {
                $prefixed[$prefix . $key] = $value;
            }
            $flat = $prefixed;
        }

        $this->messages[$locale] = array_replace($this->messages[$locale] ?? [], $flat);
    }

    public function has(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?: $this->locale;
        if (array_key_exists($key, $this->messages[$locale] ?? [])) {
            return true;
        }

        return $locale !== $this->fallbackLocale && array_key_exists($key, $this->messages[$this->fallbackLocale] ?? []);
    }

    public function translate(string $key, array $replace = [], ?string $default = null): string
    {
        $message = $this->messages[$this->locale][$key]
            ?? $this->messages[$this->fallbackLocale][$key]
            ?? $default
            ?? $key;

        foreach ($replace as $name => $value) {
            $message = str_replace(':' . $name, (string)$value, $message);
        }

        return $message;
    }

    private function flatten(array $messages, string $prefix = ''): array
    {
        $flat = [];
        foreach ($messages as $key => $value) {
            $fullKey = $prefix === '' ? (string)$key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat += $this->flatten($value, $fullKey);
                continue;
            }
            $flat[$fullKey] = (string)$value;
        }
        return $flat;
    }
}
