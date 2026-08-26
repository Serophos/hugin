<?php

declare(strict_types=1);

use App\Core\PluginApi;
use App\Core\PluginJsonCache;
use App\Core\ScheduledTaskProviderInterface;
use Plugins\BrightSkyDwdWeather\Plugin as BrightSkyPlugin;
use Plugins\Weather\Plugin as OpenMeteoPlugin;

require_once __DIR__ . '/../app/Core/SlidePluginInterface.php';
require_once __DIR__ . '/../app/Core/ScheduledTaskProviderInterface.php';
require_once __DIR__ . '/../app/Core/AbstractSlidePlugin.php';
require_once __DIR__ . '/../app/Core/PluginApi.php';
require_once __DIR__ . '/../app/Core/PluginJsonCache.php';
require_once __DIR__ . '/../plugins/weather/Plugin.php';
require_once __DIR__ . '/../plugins/brightsky-dwd-weather/Plugin.php';

function weather_task_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class WeatherTaskApi extends PluginApi
{
    public function __construct(
        private array $globalSettings,
        private array $activeSlides,
        private string $cacheRoot,
    ) {
    }

    public function loadGlobalSettings(string $pluginName): array
    {
        return $this->globalSettings[$pluginName] ?? [];
    }

    public function listActiveSlideSettings(string $pluginName, string $slideType): array
    {
        return $this->activeSlides[$pluginName] ?? [];
    }

    public function pluginCachePath(string $pluginName, string $relativePath = ''): string
    {
        $directory = $this->cacheRoot . '/' . $pluginName;
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        return $relativePath === '' ? $directory : $directory . '/' . $relativePath;
    }
}

$temporary = sys_get_temp_dir() . '/hugin-weather-task-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);

try {
    $api = new WeatherTaskApi(
        [
            'weather' => ['cache_ttl_seconds' => 30],
            'brightsky-dwd-weather' => ['cache_ttl_seconds' => 1000],
        ],
        [
            'weather' => [
                ['slide_id' => 7, 'settings' => ['latitude' => '52.52', 'longitude' => '13.405']],
                ['slide_id' => 8, 'settings' => ['latitude' => '', 'longitude' => '13.405']],
            ],
            'brightsky-dwd-weather' => [
                ['slide_id' => 11, 'settings' => ['dwd_station_id' => '00433']],
                ['slide_id' => 12, 'settings' => ['dwd_station_id' => '']],
            ],
        ],
        $temporary,
    );

    $openMeteoManifest = json_decode((string)file_get_contents(__DIR__ . '/../plugins/weather/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
    $openMeteo = new OpenMeteoPlugin($openMeteoManifest, __DIR__ . '/../plugins/weather');
    weather_task_assert($openMeteo instanceof ScheduledTaskProviderInterface, 'Open-Meteo does not implement scheduled tasks.');
    $openMeteoTasks = $openMeteo->getScheduledTasks($api);
    weather_task_assert(count($openMeteoTasks) === 1, 'Open-Meteo registered an unconfigured slide.');
    weather_task_assert($openMeteoTasks[0] === [
        'name' => 'refresh-slide-7',
        'interval_seconds' => 60,
        'retry_seconds' => 60,
    ], 'Open-Meteo task definition does not enforce the scheduler minimum.');

    $brightSkyManifest = json_decode((string)file_get_contents(__DIR__ . '/../plugins/brightsky-dwd-weather/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
    $brightSky = new BrightSkyPlugin($brightSkyManifest, __DIR__ . '/../plugins/brightsky-dwd-weather');
    weather_task_assert($brightSky instanceof ScheduledTaskProviderInterface, 'BrightSky does not implement scheduled tasks.');
    $brightSkyTasks = $brightSky->getScheduledTasks($api);
    weather_task_assert(count($brightSkyTasks) === 1, 'BrightSky registered an unconfigured slide.');
    weather_task_assert($brightSkyTasks[0] === [
        'name' => 'refresh-slide-11',
        'interval_seconds' => 1000,
        'retry_seconds' => 300,
    ], 'BrightSky task interval or bounded retry is incorrect.');

    $cacheFile = $temporary . '/atomic/weather.json';
    mkdir(dirname($cacheFile), 0700, true);
    $first = PluginJsonCache::load($cacheFile, 60, true, static fn(): array => ['temperature' => 18.5]);
    weather_task_assert($first['temperature'] === 18.5, 'Initial weather payload was not returned.');
    $firstBytes = (string)file_get_contents($cacheFile);
    $firstRevision = PluginJsonCache::revision($cacheFile);

    $loaderCalled = false;
    PluginJsonCache::load($cacheFile, 60, false, static function () use (&$loaderCalled): array {
        $loaderCalled = true;
        return ['temperature' => 99];
    });
    weather_task_assert(!$loaderCalled, 'Fresh request-time cache unexpectedly fetched weather.');

    PluginJsonCache::load($cacheFile, 60, true, static fn(): array => ['temperature' => 18.5]);
    weather_task_assert(PluginJsonCache::revision($cacheFile) === $firstRevision, 'Identical weather changed its content revision.');

    PluginJsonCache::load($cacheFile, 60, true, static fn(): array => ['temperature' => 19.0]);
    $changedRevision = PluginJsonCache::revision($cacheFile);
    weather_task_assert($changedRevision !== $firstRevision, 'Changed weather did not change its content revision.');
    $changedBytes = (string)file_get_contents($cacheFile);

    try {
        PluginJsonCache::load($cacheFile, 60, true, static function (): array {
            throw new RuntimeException('upstream unavailable');
        });
        throw new RuntimeException('Failed refresh did not report its error.');
    } catch (RuntimeException $error) {
        weather_task_assert($error->getMessage() === 'upstream unavailable', 'Unexpected failed-refresh error.');
    }
    weather_task_assert((string)file_get_contents($cacheFile) === $changedBytes, 'Failed refresh replaced the last good weather cache.');
    weather_task_assert(PluginJsonCache::revision($cacheFile) === $changedRevision, 'Failed refresh changed the weather revision.');
    weather_task_assert($firstBytes !== $changedBytes, 'Changed weather bytes were not written.');

    $openState = $openMeteo->getStateData([], ['latitude' => '52.52', 'longitude' => '13.405'], $api);
    $brightState = $brightSky->getStateData([], ['dwd_station_id' => '00433'], $api);
    weather_task_assert(array_key_exists('weather_content_revision', $openState), 'Open-Meteo state omits the weather revision.');
    weather_task_assert(array_key_exists('weather_content_revision', $brightState), 'BrightSky state omits the weather revision.');

    $managerSource = (string)file_get_contents(__DIR__ . '/../app/Core/PluginManager.php');
    weather_task_assert(str_contains($managerSource, 's.is_active = 1'), 'Scheduled weather discovery does not filter inactive slides.');
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $path) {
        $path->isDir() ? @rmdir($path->getPathname()) : @unlink($path->getPathname());
    }
    @rmdir($temporary);
}

echo "Weather scheduled-task tests passed.\n";
