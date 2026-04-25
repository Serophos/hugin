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

interface SlidePluginInterface
{
    public function getName(): string;

    public function getManifest(): array;

    public function getSlideType(): string;

    public function getDisplayName(): string;

    public function getDescription(): string;

    public function getDefaultSettings(): array;

    public function getDefaultGlobalSettings(): array;

    public function renderGlobalSettings(array $settings, PluginApi $api): string;

    public function normalizeGlobalSettings(array $input, array $existingSettings, PluginApi $api): array;

    public function renderAdminSettings(array $slide, array $settings, PluginApi $api): string;

    public function normalizeSettings(array $input, array $existingSettings, PluginApi $api): array;

    public function renderFrontend(array $slide, array $settings, PluginApi $api): string;

    public function getFrontendAssets(array $slide, array $settings, PluginApi $api): array;

    public function getStateData(array $slide, array $settings, PluginApi $api): array;
}
