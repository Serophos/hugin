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

class GlobalSettingsApi
{
    /** @var array<string, true> */
    private array $publicNamespaces;

    /**
     * @param string[] $publicNamespaces
     */
    public function __construct(private Database $db, array $publicNamespaces = ['branding'])
    {
        $this->publicNamespaces = [];
        foreach ($publicNamespaces as $namespace) {
            $namespace = trim($namespace);
            if ($namespace !== '') {
                $this->publicNamespaces[$namespace] = true;
            }
        }
    }

    public function getSetting(string $namespace, string $key, mixed $default = null): mixed
    {
        $settings = $this->getSettings($namespace);
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function getSettings(string $namespace): array
    {
        $namespace = trim($namespace);
        if (!isset($this->publicNamespaces[$namespace])) {
            return [];
        }

        $rows = $this->db->all('SELECT setting_key, setting_value FROM app_settings WHERE namespace = ?', [$namespace]);
        $settings = [];
        foreach ($rows as $row) {
            if (!is_string($row['setting_key']) || !array_key_exists('setting_value', $row)) {
                continue;
            }
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }
}
