<?php
namespace Plugins\BrightSkyDwdWeather;

use App\Core\AbstractSlidePlugin;
use App\Core\PluginApi;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class Plugin extends AbstractSlidePlugin
{
    private const DEFAULT_CURRENT_WEATHER_URL = 'https://api.brightsky.dev/current_weather';
    private const DEFAULT_STATION_DATA_PATH = 'data/stations.json';
    private const DEFAULT_CACHE_TTL_SECONDS = 900;
    private const DEFAULT_HTTP_TIMEOUT_SECONDS = 12;
    private const DEFAULT_MAX_DIST_METERS = 50000;
    private const DEFAULT_TIMEZONE = 'Europe/Berlin';
    private const DEFAULT_USER_AGENT = 'Hugin BrightSky DWD Weather Plugin/1.0';

    private ?array $stations = null;
    private array $activeGlobalSettings = [];

    public function getDefaultSettings(): array
    {
        return [
            'station_query' => '',
            'station_name' => '',
            'display_name' => '',
            'dwd_station_id' => '',
            'wmo_station_id' => '',
            'latitude' => '',
            'longitude' => '',
            'height_m' => '',
            'unit_system' => 'metric',
            'show_datetime' => true,
            'enable_weather_animations' => false,
            'enable_rain_effect' => false,
        ];
    }

    public function getDefaultGlobalSettings(): array
    {
        return [
            'brightsky_current_weather_url' => self::DEFAULT_CURRENT_WEATHER_URL,
            'cache_ttl_seconds' => self::DEFAULT_CACHE_TTL_SECONDS,
            'http_timeout_seconds' => self::DEFAULT_HTTP_TIMEOUT_SECONDS,
            'timezone' => self::DEFAULT_TIMEZONE,
            'station_data_path' => self::DEFAULT_STATION_DATA_PATH,
            'max_dist_meters' => self::DEFAULT_MAX_DIST_METERS,
            'user_agent' => self::DEFAULT_USER_AGENT,
        ];
    }

    public function renderGlobalSettings(array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultGlobalSettings(), $settings);

        return $this->renderView('views/global_config.php', [
            'plugin' => $this,
            'settings' => $settings,
            'strings' => $this->globalSettingsStrings(),
            'timezoneOptions' => DateTimeZone::listIdentifiers(),
            'infrastructureUrl' => 'https://github.com/jdemaeyer/brightsky-infrastructure/',
        ]);
    }

    public function normalizeGlobalSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        $settings = array_replace($this->getDefaultGlobalSettings(), $existingSettings, $input);

        return [
            'brightsky_current_weather_url' => $this->normalizeEndpoint((string)($settings['brightsky_current_weather_url'] ?? '')),
            'cache_ttl_seconds' => $this->normalizeIntegerSetting($settings['cache_ttl_seconds'] ?? 900, 60, 86400, 'errors.invalid_cache_ttl', 'BrightSky DWD Weather: invalid cache TTL.'),
            'http_timeout_seconds' => $this->normalizeIntegerSetting($settings['http_timeout_seconds'] ?? 12, 3, 60, 'errors.invalid_timeout', 'BrightSky DWD Weather: invalid HTTP timeout.'),
            'timezone' => $this->normalizeTimezone((string)($settings['timezone'] ?? 'Europe/Berlin')),
            'station_data_path' => $this->normalizeRelativePath((string)($settings['station_data_path'] ?? self::DEFAULT_STATION_DATA_PATH)),
            'max_dist_meters' => $this->normalizeIntegerSetting($settings['max_dist_meters'] ?? self::DEFAULT_MAX_DIST_METERS, 1, PHP_INT_MAX, 'errors.invalid_max_dist', 'BrightSky DWD Weather: invalid maximum station distance.'),
            'user_agent' => $this->normalizeStringSetting($settings['user_agent'] ?? '', self::DEFAULT_USER_AGENT),
        ];
    }

    public function renderAdminSettings(array $slide, array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));

        return $this->renderView('views/config.php', [
            'plugin' => $this,
            'settings' => $settings,
            'stationDataUrl' => $api->pluginAssetUrl($this->getName(), (string)($globalSettings['station_data_path'] ?? self::DEFAULT_STATION_DATA_PATH)),
            'strings' => $this->adminStrings(),
        ]);
    }

    public function normalizeSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        $this->activeGlobalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
        $settings = array_replace($this->getDefaultSettings(), $existingSettings, $input);
        $settings['station_query'] = trim((string)($settings['station_query'] ?? ''));
        $settings['display_name'] = trim((string)($settings['display_name'] ?? ''));
        $settings['dwd_station_id'] = $this->normalizeStationId($settings['dwd_station_id'] ?? '');
        $settings['unit_system'] = (string)($settings['unit_system'] ?? 'metric');
        $settings['show_datetime'] = !empty($settings['show_datetime']);
        $settings['enable_weather_animations'] = !empty($settings['enable_weather_animations']) || !empty($settings['enable_rain_effect']);

        if (!in_array($settings['unit_system'], ['metric', 'imperial'], true)) {
            throw new RuntimeException($this->t('errors.invalid_unit_system', 'BrightSky DWD Weather: invalid unit system.'));
        }

        $station = $settings['dwd_station_id'] !== '' ? $this->findStationByDwdId($settings['dwd_station_id']) : null;
        if (!$station && $settings['station_query'] !== '') {
            $station = $this->findStationByQuery($settings['station_query']);
        }

        if (!$station) {
            throw new RuntimeException($this->t('errors.station_required', 'BrightSky DWD Weather: please search and select a DWD station.'));
        }

        return [
            'station_query' => (string)$station['name'],
            'station_name' => (string)$station['name'],
            'display_name' => $settings['display_name'] !== '' ? $settings['display_name'] : (string)$station['name'],
            'dwd_station_id' => (string)$station['dwd_station_id'],
            'wmo_station_id' => (string)($station['wmo_station_id'] ?? ''),
            'latitude' => (string)($station['latitude'] ?? ''),
            'longitude' => (string)($station['longitude'] ?? ''),
            'height_m' => (string)($station['height_m'] ?? ''),
            'unit_system' => $settings['unit_system'],
            'show_datetime' => $settings['show_datetime'],
            'enable_weather_animations' => $settings['enable_weather_animations'],
        ];
    }

    public function renderFrontend(array $slide, array $settings, PluginApi $api): string
    {
        $this->activeGlobalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
        $settings = array_replace($this->getDefaultSettings(), $settings);
        $station = $this->findStationByDwdId((string)($settings['dwd_station_id'] ?? ''));
        $weather = [];
        $sources = [];
        $error = '';

        try {
            if (!$station) {
                throw new RuntimeException($this->t('errors.station_required', 'BrightSky DWD Weather: please search and select a DWD station.'));
            }
            $payload = $this->fetchCurrentWeather((string)$station['dwd_station_id'], $api);
            $weather = is_array($payload['weather'] ?? null) ? $payload['weather'] : [];
            $sources = is_array($payload['sources'] ?? null) ? $payload['sources'] : [];
            if (!$weather) {
                throw new RuntimeException($this->t('errors.current_weather_missing', 'BrightSky DWD Weather: weather API did not return current data.'));
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        $visual = $this->resolveVisuals((string)($weather['icon'] ?? ''), (string)($weather['condition'] ?? ''));
        $rainEffectEnabled = $this->shouldRenderRainEffect($settings, $weather, $visual, $error === '');
        $lightningEffectEnabled = $this->shouldRenderLightningEffect($settings, $weather, $visual, $error === '');
        $unitSystem = (string)($settings['unit_system'] ?? 'metric');

        return $this->renderView('views/render.php', [
            'plugin' => $this,
            'settings' => $settings,
            'station' => $station,
            'source' => is_array($sources[0] ?? null) ? $sources[0] : null,
            'weather' => $weather,
            'error' => $error,
            'visual' => $visual,
            'rainEffectEnabled' => $rainEffectEnabled,
            'lightningEffectEnabled' => $lightningEffectEnabled,
            'unitSystem' => $unitSystem,
            'iconUrl' => $api->pluginAssetUrl($this->getName(), 'assets/icons/' . $visual['icon'] . '.svg'),
            'strings' => $this->frontendStrings(),
        ]);
    }

    public function getFrontendAssets(array $slide, array $settings, PluginApi $api): array
    {
        return [
            'css' => [$api->pluginAssetUrl($this->getName(), 'assets/brightsky-dwd-weather.css')],
            'js' => [$api->pluginAssetUrl($this->getName(), 'assets/brightsky-dwd-weather.js')],
        ];
    }

    public function getStateData(array $slide, array $settings, PluginApi $api): array
    {
        return [
            'station_name' => $settings['station_name'] ?? null,
            'display_name' => $settings['display_name'] ?? null,
            'dwd_station_id' => $settings['dwd_station_id'] ?? null,
            'wmo_station_id' => $settings['wmo_station_id'] ?? null,
            'unit_system' => $settings['unit_system'] ?? null,
            'enable_weather_animations' => $settings['enable_weather_animations'] ?? ($settings['enable_rain_effect'] ?? null),
        ];
    }

    public function adminStrings(): array
    {
        return [
            'title' => $this->t('admin.title', 'BrightSky DWD Weather'),
            'description' => $this->t('admin.description', 'Displays current DWD station weather using Bright Sky.'),
            'station_search' => $this->t('admin.station_search', 'DWD station search'),
            'station_placeholder' => $this->t('admin.station_placeholder', 'Search station name, DWD ID, or WMO ID'),
            'search_help' => $this->t('admin.search_help', 'Search the bundled DWD station list and select the station used for this slide.'),
            'selected_station' => $this->t('admin.selected_station', 'Selected station'),
            'display_name' => $this->t('admin.display_name', 'Name shown on slide'),
            'dwd_station_id' => $this->t('admin.dwd_station_id', 'DWD station ID'),
            'wmo_station_id' => $this->t('admin.wmo_station_id', 'WMO ID'),
            'coordinates' => $this->t('admin.coordinates', 'Coordinates'),
            'unit_system' => $this->t('admin.unit_system', 'Units'),
            'metric' => $this->t('admin.metric', 'Metric'),
            'imperial' => $this->t('admin.imperial', 'Imperial'),
            'show_datetime' => $this->t('admin.show_datetime', 'Show date and time'),
            'weather_animations' => $this->t('admin.weather_animations', 'Show weather animations'),
            'weather_animations_help' => $this->t('admin.weather_animations_help', 'Shows subtle animations that match the current weather, such as raindrops or lightning. Designed to preserve readability.'),
            'no_results' => $this->t('admin.no_results', 'No matching DWD station found.'),
            'loading_failed' => $this->t('admin.loading_failed', 'Station list could not be loaded.'),
            'footer_note' => $this->t('admin.footer_note', 'Weather data is loaded server-side from Bright Sky and cached by Hugin.'),
        ];
    }

    public function frontendStrings(): array
    {
        return [
            'unknown_station' => $this->t('frontend.unknown_station', 'Unknown station'),
            'current_weather' => $this->t('frontend.current_weather', 'Current weather'),
            'updated' => $this->t('frontend.updated', 'Updated'),
            'temperature' => $this->t('frontend.temperature', 'Temperature'),
            'wind' => $this->t('frontend.wind', 'Wind'),
            'gusts' => $this->t('frontend.gusts', 'Gusts'),
            'pressure' => $this->t('frontend.pressure', 'Pressure'),
            'humidity' => $this->t('frontend.humidity', 'Humidity'),
            'precipitation' => $this->t('frontend.precipitation', 'Precipitation'),
            'visibility' => $this->t('frontend.visibility', 'Visibility'),
            'station' => $this->t('frontend.station', 'Station'),
            'no_data' => $this->t('frontend.no_data', 'No current weather data available.'),
            'attribution' => $this->t('frontend.attribution', 'Data: Deutscher Wetterdienst (DWD), via Bright Sky'),
            'condition.clear-day' => $this->t('conditions.clear_day', 'Clear'),
            'condition.clear-night' => $this->t('conditions.clear_night', 'Clear night'),
            'condition.partly-cloudy-day' => $this->t('conditions.partly_cloudy_day', 'Partly cloudy'),
            'condition.partly-cloudy-night' => $this->t('conditions.partly_cloudy_night', 'Partly cloudy night'),
            'condition.cloudy' => $this->t('conditions.cloudy', 'Cloudy'),
            'condition.fog' => $this->t('conditions.fog', 'Fog'),
            'condition.wind' => $this->t('conditions.wind', 'Windy'),
            'condition.rain' => $this->t('conditions.rain', 'Rain'),
            'condition.sleet' => $this->t('conditions.sleet', 'Sleet'),
            'condition.snow' => $this->t('conditions.snow', 'Snow'),
            'condition.hail' => $this->t('conditions.hail', 'Hail'),
            'condition.thunderstorm' => $this->t('conditions.thunderstorm', 'Thunderstorm'),
            'condition.dry' => $this->t('conditions.dry', 'Dry'),
            'condition.unknown' => $this->t('conditions.unknown', 'Current weather'),
        ];
    }

    public function shouldRenderRainEffect(array $settings, array $weather, array $visual, bool $hasWeather): bool
    {
        if (!$hasWeather) {
            return false;
        }

        if (empty($settings['enable_weather_animations']) && empty($settings['enable_rain_effect'])) {
            return false;
        }

        $condition = strtolower((string)($weather['condition'] ?? ''));
        $icon = strtolower((string)($weather['icon'] ?? ''));
        $visualCondition = strtolower((string)($visual['condition'] ?? ''));
        $excluded = ['snow', 'sleet', 'hail', 'fog', 'cloudy', 'clear-day', 'clear-night', 'partly-cloudy-day', 'partly-cloudy-night', 'dry'];
        if (in_array($condition, $excluded, true) || in_array($icon, $excluded, true) || in_array($visualCondition, ['snow', 'sleet', 'hail', 'fog'], true)) {
            return false;
        }

        $precipitation = $this->firstWeatherValue($weather, ['precipitation_10', 'precipitation_30', 'precipitation_60', 'precipitation']);
        $hasPrecipitation = is_numeric($precipitation) && (float)$precipitation > 0;

        if (in_array($condition, ['rain'], true) || in_array($icon, ['rain'], true) || $visualCondition === 'rain') {
            return true;
        }

        if (in_array($condition, ['thunderstorm'], true) || in_array($icon, ['thunderstorm'], true) || $visualCondition === 'thunderstorm') {
            return $hasPrecipitation;
        }

        return $hasPrecipitation;
    }

    public function shouldRenderLightningEffect(array $settings, array $weather, array $visual, bool $hasWeather): bool
    {
        if (!$hasWeather) {
            return false;
        }

        if (empty($settings['enable_weather_animations']) && empty($settings['enable_rain_effect'])) {
            return false;
        }

        $condition = strtolower((string)($weather['condition'] ?? ''));
        $icon = strtolower((string)($weather['icon'] ?? ''));
        $visualCondition = strtolower((string)($visual['condition'] ?? ''));

        return in_array($condition, ['thunderstorm'], true)
            || in_array($icon, ['thunderstorm'], true)
            || $visualCondition === 'thunderstorm';
    }

    public function conditionLabel(array $visual, array $strings): string
    {
        $key = 'condition.' . (string)($visual['condition'] ?? 'unknown');
        return (string)($strings[$key] ?? $strings['condition.unknown'] ?? 'Current weather');
    }

    public function displayValue(string $field, mixed $value, string $unitSystem): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return '-';
        }

        $number = (float)$value;
        return match ($field) {
            'temperature' => $unitSystem === 'imperial'
                ? $this->formatNumber($number * 9 / 5 + 32) . ' °F'
                : $this->formatNumber($number) . ' °C',
            'wind' => $unitSystem === 'imperial'
                ? $this->formatNumber($number * 0.621371) . ' mph'
                : $this->formatNumber($number) . ' km/h',
            'pressure' => $unitSystem === 'imperial'
                ? $this->formatNumber($number * 0.029529983, 2) . ' inHg'
                : $this->formatNumber($number) . ' hPa',
            'precipitation' => $unitSystem === 'imperial'
                ? $this->formatNumber($number * 0.0393701, 2) . ' in'
                : $this->formatNumber($number) . ' mm',
            'visibility' => $unitSystem === 'imperial'
                ? $this->formatNumber($number * 0.000621371, 1) . ' mi'
                : $this->formatNumber($number / 1000, 1) . ' km',
            default => $this->formatNumber($number),
        };
    }

    public function formatNumber(float $value, int $decimals = 1): string
    {
        $rounded = round($value, $decimals);
        $string = number_format($rounded, $decimals, '.', '');
        return str_contains($string, '.') ? rtrim(rtrim($string, '0'), '.') : $string;
    }

    public function formatTimestamp(?string $timestamp): string
    {
        if (!$timestamp) {
            return '';
        }

        try {
            $timezone = new DateTimeZone((string)$this->globalSetting('timezone', 'Europe/Berlin'));
            return (new DateTimeImmutable($timestamp))->setTimezone($timezone)->format('d.m.Y H:i');
        } catch (\Throwable) {
            return $timestamp;
        }
    }

    public function firstWeatherValue(array $weather, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $weather) && $weather[$key] !== null && $weather[$key] !== '') {
                return $weather[$key];
            }
        }
        return null;
    }

    public function compassDirection(mixed $degrees): string
    {
        if ($degrees === null || $degrees === '' || !is_numeric($degrees)) {
            return '';
        }

        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = (int)round(((float)$degrees % 360) / 45) % 8;
        return $directions[$index];
    }

    public function globalSettingsStrings(): array
    {
        return [
            'title' => $this->t('global.title', 'Bright Sky API settings'),
            'description' => $this->t('global.description', 'Configure the Bright Sky endpoint and request behavior used by all BrightSky DWD Weather slides.'),
            'endpoint' => $this->t('global.endpoint', 'Bright Sky current weather endpoint'),
            'endpoint_help' => $this->t('global.endpoint_help', 'Default: https://api.brightsky.dev/current_weather'),
            'request_settings' => $this->t('global.request_settings', 'Request behavior'),
            'cache_ttl' => $this->t('global.cache_ttl', 'Cache TTL in seconds'),
            'cache_ttl_help' => $this->t('global.cache_ttl_help', 'Minimum 60, maximum 86400.'),
            'timeout' => $this->t('global.timeout', 'HTTP timeout in seconds'),
            'timeout_help' => $this->t('global.timeout_help', 'Minimum 3, maximum 60.'),
            'timezone' => $this->t('global.timezone', 'Timezone'),
            'timezone_help' => $this->t('global.timezone_help', 'IANA timezone used for Bright Sky responses and timestamps, for example Europe/Berlin.'),
            'station_settings' => $this->t('global.station_settings', 'Station data'),
            'station_data_path' => $this->t('global.station_data_path', 'Station data path'),
            'station_data_path_help' => $this->t('global.station_data_path_help', 'Plugin-relative JSON station list path. Default: data/stations.json'),
            'max_dist' => $this->t('global.max_dist', 'Maximum station distance in meters'),
            'max_dist_help' => $this->t('global.max_dist_help', 'Passed to Bright Sky as max_dist. Default: 50000.'),
            'user_agent' => $this->t('global.user_agent', 'User-Agent header'),
            'user_agent_help' => $this->t('global.user_agent_help', 'Sent with server-side Bright Sky requests. Default: Hugin BrightSky DWD Weather Plugin/1.0'),
            'notice' => $this->t('global.notice', 'Bright Sky is open source. You can run your own Bright Sky API server and point this plugin at it.'),
            'notice_link' => $this->t('global.notice_link', 'Official Bright Sky infrastructure guide'),
        ];
    }

    private function fetchCurrentWeather(string $dwdStationId, PluginApi $api): array
    {
        $cacheKey = sha1(json_encode([
            $this->currentWeatherUrl(),
            $dwdStationId,
            $this->globalSetting('timezone', 'Europe/Berlin'),
            $this->globalSetting('max_dist_meters', self::DEFAULT_MAX_DIST_METERS),
        ], JSON_UNESCAPED_SLASHES));
        $cacheFile = $api->pluginCachePath($this->getName(), $cacheKey . '.json');
        $ttl = max(60, (int)$this->globalSetting('cache_ttl_seconds', 900));

        if (is_file($cacheFile) && (filemtime($cacheFile) ?: 0) >= time() - $ttl) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $url = $this->buildUrlWithQuery($this->currentWeatherUrl(), [
            'dwd_station_id' => $dwdStationId,
            'tz' => (string)$this->globalSetting('timezone', 'Europe/Berlin'),
            'units' => 'dwd',
            'max_dist' => (int)$this->globalSetting('max_dist_meters', self::DEFAULT_MAX_DIST_METERS),
        ]);
        $payload = $this->httpGetJson($url);

        @file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $payload;
    }

    private function globalSetting(string $key, mixed $fallback): mixed
    {
        return $this->activeGlobalSettings[$key] ?? $this->getDefaultGlobalSettings()[$key] ?? $fallback;
    }

    private function normalizeEndpoint(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            $url = self::DEFAULT_CURRENT_WEATHER_URL;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException($this->t('errors.invalid_endpoint', 'BrightSky DWD Weather: invalid Bright Sky API endpoint.'));
        }

        return rtrim($url, '?&');
    }

    private function normalizeIntegerSetting(mixed $value, int $min, int $max, string $errorKey, string $errorDefault): int
    {
        $string = trim((string)$value);
        if ($string === '' || !ctype_digit($string)) {
            throw new RuntimeException($this->t($errorKey, $errorDefault));
        }
        $integer = (int)$string;
        if ($integer < $min || $integer > $max) {
            throw new RuntimeException($this->t($errorKey, $errorDefault));
        }
        return $integer;
    }

    private function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone);
        if ($timezone === '') {
            $timezone = 'Europe/Berlin';
        }

        try {
            new DateTimeZone($timezone);
        } catch (\Throwable) {
            throw new RuntimeException($this->t('errors.invalid_timezone', 'BrightSky DWD Weather: invalid timezone.'));
        }

        return $timezone;
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return self::DEFAULT_STATION_DATA_PATH;
        }
        return trim(str_replace('\\', '/', $path), '/');
    }

    private function normalizeStringSetting(mixed $value, string $default): string
    {
        $string = trim((string)$value);
        return $string !== '' ? $string : $default;
    }

    private function currentWeatherUrl(): string
    {
        $url = trim((string)$this->globalSetting('brightsky_current_weather_url', self::DEFAULT_CURRENT_WEATHER_URL));
        if ($url === '') {
            $url = self::DEFAULT_CURRENT_WEATHER_URL;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException($this->t('errors.invalid_endpoint', 'BrightSky DWD Weather: invalid Bright Sky API endpoint.'));
        }
        return rtrim($url, '?&');
    }

    private function httpGetJson(string $url): array
    {
        $timeout = max(3, (int)$this->globalSetting('http_timeout_seconds', 12));
        $headers = [
            'Accept: application/json',
            'User-Agent: ' . (string)$this->globalSetting('user_agent', self::DEFAULT_USER_AGENT),
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!is_string($body) || $status < 200 || $status >= 300) {
                throw new RuntimeException($this->t('errors.api_request_failed', 'BrightSky DWD Weather: API request failed.'));
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => $timeout,
                    'header' => implode("\r\n", $headers),
                ],
            ]);
            $body = @file_get_contents($url, false, $context);
            if (!is_string($body)) {
                throw new RuntimeException($this->t('errors.api_request_failed', 'BrightSky DWD Weather: API request failed.'));
            }
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException($this->t('errors.invalid_api_response', 'BrightSky DWD Weather: invalid API response.'));
        }
        return $data;
    }

    private function buildUrlWithQuery(string $base, array $query): string
    {
        $separator = str_contains($base, '?')
            ? (str_ends_with($base, '?') || str_ends_with($base, '&') ? '' : '&')
            : '?';

        return $base . $separator . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function loadStations(): array
    {
        if ($this->stations !== null) {
            return $this->stations;
        }

        $relative = trim((string)$this->globalSetting('station_data_path', self::DEFAULT_STATION_DATA_PATH), '/');
        $file = $this->rootPath . '/' . $relative;
        $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        $stations = is_array($data['stations'] ?? null) ? $data['stations'] : [];

        return $this->stations = array_values(array_filter($stations, static fn ($station) => is_array($station)));
    }

    private function findStationByDwdId(string $dwdStationId): ?array
    {
        $dwdStationId = $this->normalizeStationId($dwdStationId);
        if ($dwdStationId === '') {
            return null;
        }

        foreach ($this->loadStations() as $station) {
            if ((string)($station['dwd_station_id'] ?? '') === $dwdStationId) {
                return $station;
            }
        }
        return null;
    }

    private function findStationByQuery(string $query): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $asId = $this->normalizeStationId($query);
        if ($asId !== '') {
            $station = $this->findStationByDwdId($asId);
            if ($station) {
                return $station;
            }
        }

        $needle = $this->normalizeSearchText($query);
        foreach ($this->loadStations() as $station) {
            $name = $this->normalizeSearchText((string)($station['name'] ?? ''));
            $wmo = $this->normalizeSearchText((string)($station['wmo_station_id'] ?? ''));
            if ($name === $needle || ($wmo !== '' && $wmo === $needle)) {
                return $station;
            }
        }
        return null;
    }

    private function normalizeStationId(mixed $value): string
    {
        $stationId = strtoupper(trim((string)$value));
        if ($stationId === '') {
            return '';
        }
        if (ctype_digit($stationId) && strlen($stationId) <= 5) {
            return str_pad($stationId, 5, '0', STR_PAD_LEFT);
        }
        if (!preg_match('/^[A-Z0-9]{1,8}$/', $stationId)) {
            return '';
        }
        return $stationId;
    }

    private function normalizeSearchText(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'], $value);
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function resolveVisuals(string $icon, string $condition): array
    {
        $icon = trim($icon);
        if ($icon === '' && $condition !== '') {
            $icon = match ($condition) {
                'fog', 'rain', 'sleet', 'snow', 'hail', 'thunderstorm' => $condition,
                default => 'cloudy',
            };
        }

        return match ($icon) {
            'clear-day' => ['condition' => 'clear-day', 'theme' => 'clear-day', 'icon' => 'sun'],
            'clear-night' => ['condition' => 'clear-night', 'theme' => 'clear-night', 'icon' => 'moon'],
            'partly-cloudy-day' => ['condition' => 'partly-cloudy-day', 'theme' => 'partly-cloudy-day', 'icon' => 'partly-cloudy-day'],
            'partly-cloudy-night' => ['condition' => 'partly-cloudy-night', 'theme' => 'partly-cloudy-night', 'icon' => 'partly-cloudy-night'],
            'fog' => ['condition' => 'fog', 'theme' => 'fog', 'icon' => 'fog'],
            'wind' => ['condition' => 'wind', 'theme' => 'wind', 'icon' => 'wind'],
            'rain' => ['condition' => 'rain', 'theme' => 'rain', 'icon' => 'rain'],
            'sleet' => ['condition' => 'sleet', 'theme' => 'sleet', 'icon' => 'sleet'],
            'snow' => ['condition' => 'snow', 'theme' => 'snow', 'icon' => 'snow'],
            'hail' => ['condition' => 'hail', 'theme' => 'hail', 'icon' => 'hail'],
            'thunderstorm' => ['condition' => 'thunderstorm', 'theme' => 'storm', 'icon' => 'thunderstorm'],
            'cloudy' => ['condition' => 'cloudy', 'theme' => 'cloudy', 'icon' => 'cloud'],
            default => ['condition' => $condition === 'dry' ? 'dry' : 'unknown', 'theme' => 'neutral', 'icon' => 'cloud'],
        };
    }
}
