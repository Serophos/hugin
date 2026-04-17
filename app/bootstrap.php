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

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/../config/config.example.php';
}

$config = require $configFile;
$config['paths'] = [
    'root' => realpath(__DIR__ . '/..'),
    'public' => realpath(__DIR__ . '/../public'),
];

$GLOBALS['app_config'] = $config;

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['app']['session_name'] ?? 'info_display_session');
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Core/I18n.php';
require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/View.php';
require_once __DIR__ . '/Core/Request.php';
require_once __DIR__ . '/Core/Auth.php';
require_once __DIR__ . '/Core/UploadManager.php';
require_once __DIR__ . '/Core/SlidePluginInterface.php';
require_once __DIR__ . '/Core/AbstractSlidePlugin.php';
require_once __DIR__ . '/Core/PluginApi.php';
require_once __DIR__ . '/Core/PluginManager.php';
require_once __DIR__ . '/Controllers/AdminController.php';
require_once __DIR__ . '/Controllers/FrontendController.php';

$locale = (string)($config['app']['locale'] ?? 'en');
$fallbackLocale = (string)($config['app']['fallback_locale'] ?? $locale);
$i18n = new App\Core\I18n($locale, $fallbackLocale);
$i18n->loadFile($fallbackLocale, __DIR__ . '/lang/' . $fallbackLocale . '.php');
if ($locale !== $fallbackLocale) {
    $i18n->loadFile($locale, __DIR__ . '/lang/' . $locale . '.php');
}

$pluginsRoot = __DIR__ . '/../plugins';
if (is_dir($pluginsRoot)) {
    foreach (scandir($pluginsRoot) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $langDir = $pluginsRoot . '/' . $entry . '/lang';
        if (!is_dir($langDir)) {
            continue;
        }
        $i18n->loadFile($fallbackLocale, $langDir . '/' . $fallbackLocale . '.php', 'plugins.' . $entry);
        if ($locale !== $fallbackLocale) {
            $i18n->loadFile($locale, $langDir . '/' . $locale . '.php', 'plugins.' . $entry);
        }
    }
}

$GLOBALS['i18n'] = $i18n;
$GLOBALS['i18n_locale'] = $locale;

$db = new App\Core\Database($config['db']);
$view = new App\Core\View(__DIR__ . '/Views');
$request = new App\Core\Request();
$auth = new App\Core\Auth($db);
$uploadManager = new App\Core\UploadManager($db, $config);
$pluginManager = new App\Core\PluginManager($db, __DIR__ . '/../plugins');
try {
    $pluginManager->syncRegistry();
} catch (\Throwable $e) {
    // Allow the app to boot before the database schema has been imported.
}
