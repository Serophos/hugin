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
        'display_name' => 'Flip Clock',
        'description' => 'Displays a responsive split-flap style clock.',
    ],
    'config' => [
        'title' => 'Flip Clock plugin',
        'intro' => 'Displays the current browser time as a large split-flap clock.',
        'background_color' => 'Background color',
        'background_media_asset' => 'Background image',
        'current_background_image' => 'Current background image',
        'show_seconds' => 'Show seconds',
        'upload_background_image' => 'Upload background image',
    ],
    'errors' => [
        'background_asset_not_found' => 'Flip Clock plugin: selected background image was not found.',
        'background_invalid_type' => 'Flip Clock plugin: please select an image background.',
        'background_upload_failed' => 'Flip Clock plugin: background image upload failed.',
    ],
];
