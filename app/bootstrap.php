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

require_once __DIR__ . '/session_policy.php';

$requestRequiresSession = $GLOBALS['app_request_requires_session']
    ?? app_request_requires_session(
        (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        (string)($_SERVER['REQUEST_URI'] ?? '/'),
    );
if ($requestRequiresSession && session_status() === PHP_SESSION_NONE) {
    session_name($config['app']['session_name'] ?? 'info_display_session');
    session_start();
} elseif (!$requestRequiresSession && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Core/I18n.php';
require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/MigrationService.php';
require_once __DIR__ . '/Core/View.php';
require_once __DIR__ . '/Core/Request.php';
require_once __DIR__ . '/Core/Auth.php';
require_once __DIR__ . '/Core/SecretCipher.php';
require_once __DIR__ . '/Core/FontMetadataExtractor.php';
require_once __DIR__ . '/Core/UploadManager.php';
require_once __DIR__ . '/Core/SlidePluginInterface.php';
require_once __DIR__ . '/Core/ScheduledTaskProviderInterface.php';
require_once __DIR__ . '/Core/AbstractSlidePlugin.php';
require_once __DIR__ . '/Core/GlobalSettingsApi.php';
require_once __DIR__ . '/Core/PluginJsonCache.php';
require_once __DIR__ . '/Core/PluginApi.php';
require_once __DIR__ . '/Core/PluginManager.php';
require_once __DIR__ . '/Services/DisplayStatusService.php';
require_once __DIR__ . '/Services/PluginTaskScheduler.php';
require_once __DIR__ . '/Services/OpenIdConnectService.php';
require_once __DIR__ . '/Controllers/AdminController.php';
require_once __DIR__ . '/Controllers/FrontendController.php';
require_once __DIR__ . '/Controllers/MonitoringController.php';
require_once __DIR__ . '/Controllers/OpenIdConnectController.php';
require_once __DIR__ . '/Controllers/AccountController.php';

$db = new App\Core\Database($config['db']);
$GLOBALS['app_db'] = $db;
$migrationService = new App\Core\MigrationService($db->pdo(), __DIR__ . '/../db-migrations');
$migrationStatus = ['healthy' => false];
try {
    $migrationStatus = $migrationService->status();
} catch (\Throwable) {
    // Fail closed without exposing database or filesystem details to HTTP clients.
}
if (!$migrationStatus['healthy']) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('Retry-After: 300');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Hugin maintenance</title><style>body{font:16px system-ui,sans-serif;max-width:42rem;margin:10vh auto;padding:1.5rem;color:#1f2937}'
        . 'main{border:1px solid #d1d5db;border-radius:.75rem;padding:2rem}code{background:#f3f4f6;padding:.15rem .35rem}</style></head>'
        . '<body><main><h1>Database upgrade required</h1><p>Hugin is temporarily unavailable while an administrator completes a database upgrade.</p>'
        . '<p>Administrator: run <code>php bin/hugin-db status</code> on the server. Database upgrades can only be run from the command line.</p></main></body></html>';
    exit;
}
app_import_legacy_config_settings($config);

$locale = (string)app_core_setting('system.locale', $config['app']['locale'] ?? 'en');
if (isset($_SESSION['_locale']) && array_key_exists((string)$_SESSION['_locale'], app_available_locales())) {
    $locale = (string)$_SESSION['_locale'];
}
$fallbackLocale = (string)($config['app']['fallback_locale'] ?? $locale);
$i18n = app_build_i18n($locale, $fallbackLocale);

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
