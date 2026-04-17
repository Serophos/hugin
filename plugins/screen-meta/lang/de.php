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
        'display_name' => 'Bildschirm-Metadaten',
        'description' => 'Rendert die zuletzt per Heartbeat vom Display-Client erfassten Metadaten.',
    ],
    'defaults' => [
        'heading' => 'Informationen zum Display-Client',
    ],
    'config' => [
        'title' => 'Plugin Bildschirm-Metadaten',
        'intro' => 'Dieses Plugin rendert Heartbeat-Metadaten des Clients, der das Display aktuell anzeigt.',
        'heading' => 'Ueberschrift',
        'show_browser' => 'Browser',
        'show_os' => 'Betriebssystem',
        'show_resolution' => 'Bildschirmaufloesung',
        'show_viewport' => 'Viewport-Groesse',
        'show_ip' => 'Client-IP',
        'show_timezone' => 'Zeitzone',
        'note' => 'Optionaler Hinweis',
    ],
    'errors' => [
        'heading_required' => 'Plugin Bildschirm-Metadaten: Eine Ueberschrift ist erforderlich.',
    ],
    'rows' => [
        'browser' => 'Browser',
        'operating_system' => 'Betriebssystem',
        'screen_resolution' => 'Bildschirmaufloesung',
        'viewport' => 'Viewport',
        'client_ip' => 'Client-IP',
        'timezone' => 'Zeitzone',
        'display' => 'Display',
        'orientation' => 'Ausrichtung',
        'unknown_display' => 'Unbekanntes Display',
    ],
];
