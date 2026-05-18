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
        'description' => 'Zeigt eine responsive Uhr im Split-Flap-Stil.',
    ],
    'config' => [
        'title' => 'Plugin Flip Clock',
        'intro' => 'Zeigt die aktuelle Browserzeit als grosse Split-Flap-Uhr.',
        'background_color' => 'Hintergrundfarbe',
        'background_media_asset' => 'Hintergrundbild',
        'current_background_image' => 'Aktuelles Hintergrundbild',
        'show_seconds' => 'Sekunden anzeigen',
        'upload_background_image' => 'Hintergrundbild hochladen',
    ],
    'errors' => [
        'background_asset_not_found' => 'Plugin Flip Clock: Das ausgewaehlte Hintergrundbild wurde nicht gefunden.',
        'background_invalid_type' => 'Plugin Flip Clock: Bitte ein Bild als Hintergrund auswaehlen.',
        'background_upload_failed' => 'Plugin Flip Clock: Das Hochladen des Hintergrundbilds ist fehlgeschlagen.',
    ],
];
