<?php
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    throw new RuntimeException('Hugin setup incomplete: missing config/config.php. Copy config/config.example.php to config/config.php and configure it for this installation.');
}

$config = require $configFile;
$config['paths'] = [
    'root' => realpath(__DIR__ . '/..'),
    'public' => realpath(__DIR__ . '/../public'),
];

$GLOBALS['app_config'] = $config;

$manifestFile = __DIR__ . '/../manifest.json';
$manifest = [];
if (is_file($manifestFile)) {
    $decodedManifest = json_decode((string)file_get_contents($manifestFile), true);
    if (is_array($decodedManifest)) {
        $manifest = $decodedManifest;
    }
}
$GLOBALS['app_manifest'] = $manifest;

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['app']['session_name'] ?? 'info_display_session');
    session_start();
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
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
require_once __DIR__ . '/Core/GlobalSettingsApi.php';
require_once __DIR__ . '/Core/PluginApi.php';
require_once __DIR__ . '/Core/PluginManager.php';
require_once __DIR__ . '/Services/DisplayStatusService.php';
require_once __DIR__ . '/Controllers/AdminController.php';
require_once __DIR__ . '/Controllers/FrontendController.php';
require_once __DIR__ . '/Controllers/MonitoringController.php';

$db = new App\Core\Database($config['db']);
$GLOBALS['app_db'] = $db;
app_import_legacy_config_settings($config);

$locale = (string)app_core_setting('system.locale', $config['app']['locale'] ?? 'en');
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

$view = new App\Core\View(__DIR__ . '/Views');
$request = new App\Core\Request();
$auth = new App\Core\Auth($db);
$uploadManager = new App\Core\UploadManager($db, $config);
$pluginManager = new App\Core\PluginManager($db, __DIR__ . '/../plugins', $uploadManager);
$displayStatusService = new App\Services\DisplayStatusService($db);
try {
    $pluginManager->syncRegistry();
} catch (\Throwable $e) {
    // Allow the app to boot before the database schema has been imported.
}
