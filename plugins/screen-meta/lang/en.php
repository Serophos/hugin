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

return [
    'meta' => [
        'display_name' => 'Screen Metadata',
        'description' => 'Renders the most recent heartbeat metadata collected from the display client.',
    ],
    'defaults' => [
        'heading' => 'Display Client Information',
    ],
    'config' => [
        'title' => 'Screen Metadata plugin',
        'intro' => 'This plugin renders heartbeat metadata from the client currently showing the display.',
        'heading' => 'Heading',
        'show_browser' => 'Browser',
        'show_os' => 'Operating system',
        'show_resolution' => 'Screen resolution',
        'show_viewport' => 'Viewport size',
        'show_ip' => 'Client IP',
        'show_timezone' => 'Timezone',
        'note' => 'Optional note',
    ],
    'global_config' => [
        'title' => 'Global settings',
        'hello' => 'Hello world',
    ],
    'errors' => [
        'heading_required' => 'Screen Metadata plugin: heading is required.',
    ],
    'rows' => [
        'browser' => 'Browser',
        'operating_system' => 'Operating system',
        'screen_resolution' => 'Screen resolution',
        'viewport' => 'Viewport',
        'client_ip' => 'Client IP',
        'timezone' => 'Timezone',
        'display' => 'Display',
        'orientation' => 'Orientation',
        'unknown_display' => 'Unknown display',
    ],
];
