<?php
namespace Plugins\Weather;

use App\Core\AbstractSlidePlugin;
use App\Core\PluginApi;
use RuntimeException;

class Plugin extends AbstractSlidePlugin
{
    private const DEFAULT_GEOCODING_BASE_URL = 'https://geocoding-api.open-meteo.com/v1/search';
    private const DEFAULT_WEATHER_BASE_URL = 'https://api.open-meteo.com/v1/forecast';

    private array $config;
    private ?array $translations = null;

    public function __construct(array $manifest, string $rootPath)
    {
        parent::__construct($manifest, $rootPath);
        $file = $rootPath . '/config.php';
        $this->config = is_file($file) ? (array) require $file : [];
    }

    public function getDefaultSettings(): array
    {
        return [
            'location_query' => '',
            'location_name' => '',
            'latitude' => '',
            'longitude' => '',
            'country_code' => '',
            'timezone_name' => '',
            'temperature_unit' => 'celsius',
            'wind_speed_unit' => 'kmh',
            'precipitation_unit' => 'mm',
            'show_datetime' => true,
        ];
    }

    public function getDefaultGlobalSettings(): array
    {
        return [
            'weather_base_url' => self::DEFAULT_WEATHER_BASE_URL,
            'geocoding_base_url' => self::DEFAULT_GEOCODING_BASE_URL,
            'api_key' => '',
        ];
    }

    public function isCommercialMode(array $globalSettings = []): bool
    {
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $globalSettings);
        return trim((string)($globalSettings['api_key'] ?? '')) !== ''
            || trim((string)($globalSettings['weather_base_url'] ?? '')) !== self::DEFAULT_WEATHER_BASE_URL;
    }

    public function getGeocodingBaseUrl(array $globalSettings = []): string
    {
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $globalSettings);
        return (string)$globalSettings['geocoding_base_url'];
    }

    public function renderAdminSettings(array $slide, array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        $globalSettings = $this->loadGlobalSettings($api);

        return $this->renderView('views/config.php', [
            'plugin' => $this,
            'settings' => $settings,
            'globalSettings' => $globalSettings,
            'strings' => $this->adminStrings(),
        ]);
    }

    public function renderGlobalSettings(array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultGlobalSettings(), $settings);

        return $this->renderView('views/global_config.php', [
            'plugin' => $this,
            'settings' => $settings,
            'strings' => $this->globalSettingsStrings(),
        ]);
    }

    public function normalizeGlobalSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        $settings = array_replace($this->getDefaultGlobalSettings(), $existingSettings, $input);

        return [
            'weather_base_url' => $this->normalizeEndpoint(
                $settings['weather_base_url'] ?? '',
                self::DEFAULT_WEATHER_BASE_URL,
                'plugin.weather.error.invalid_weather_endpoint',
                'Weather plugin: invalid weather API endpoint.'
            ),
            'geocoding_base_url' => $this->normalizeEndpoint(
                $settings['geocoding_base_url'] ?? '',
                self::DEFAULT_GEOCODING_BASE_URL,
                'plugin.weather.error.invalid_geocoding_endpoint',
                'Weather plugin: invalid geocoding API endpoint.'
            ),
            'api_key' => trim((string)($settings['api_key'] ?? '')),
        ];
    }

    public function normalizeSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        $settings = array_replace($this->getDefaultSettings(), $existingSettings, $input);
        $globalSettings = $this->loadGlobalSettings($api);
        $settings['location_query'] = trim((string)($settings['location_query'] ?? ''));
        $settings['location_name'] = trim((string)($settings['location_name'] ?? ''));
        $settings['latitude'] = $this->sanitizeCoordinate($settings['latitude'] ?? null, 'latitude');
        $settings['longitude'] = $this->sanitizeCoordinate($settings['longitude'] ?? null, 'longitude');
        $settings['country_code'] = strtoupper(substr(trim((string)($settings['country_code'] ?? '')), 0, 8));
        $settings['timezone_name'] = trim((string)($settings['timezone_name'] ?? ''));

        $temp = (string)($settings['temperature_unit'] ?? 'celsius');
        $wind = (string)($settings['wind_speed_unit'] ?? 'kmh');
        $precip = (string)($settings['precipitation_unit'] ?? 'mm');

        if (!in_array($temp, ['celsius', 'fahrenheit'], true)) {
            throw new RuntimeException($this->t('plugin.weather.error.invalid_temperature_unit', 'Weather plugin: invalid temperature unit.'));
        }
        if (!in_array($wind, ['kmh', 'ms', 'mph', 'kn'], true)) {
            throw new RuntimeException($this->t('plugin.weather.error.invalid_wind_speed_unit', 'Weather plugin: invalid wind speed unit.'));
        }
        if (!in_array($precip, ['mm', 'inch'], true)) {
            throw new RuntimeException($this->t('plugin.weather.error.invalid_precipitation_unit', 'Weather plugin: invalid precipitation unit.'));
        }

        $settings['temperature_unit'] = $temp;
        $settings['wind_speed_unit'] = $wind;
        $settings['precipitation_unit'] = $precip;
        $settings['show_datetime'] = !empty($settings['show_datetime']);

        if (($settings['location_name'] === '' || $settings['latitude'] === '' || $settings['longitude'] === '') && $settings['location_query'] !== '') {
            $resolved = $this->resolveLocationFromQuery($settings['location_query'], $globalSettings);
            $settings['location_name'] = (string)($resolved['location_name'] ?? '');
            $settings['latitude'] = (string)($resolved['latitude'] ?? '');
            $settings['longitude'] = (string)($resolved['longitude'] ?? '');
            $settings['country_code'] = (string)($resolved['country_code'] ?? '');
            $settings['timezone_name'] = (string)($resolved['timezone_name'] ?? '');
        }

        if ($settings['location_name'] === '' || $settings['latitude'] === '' || $settings['longitude'] === '') {
            throw new RuntimeException($this->t('plugin.weather.error.location_required', 'Weather plugin: please search and select a location.'));
        }

        return $settings;
    }

    public function renderFrontend(array $slide, array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        $globalSettings = $this->loadGlobalSettings($api);
        $weather = $this->fetchCurrentWeather($settings, $globalSettings, $api);
        $visual = $this->resolveVisuals((int)($weather['weather_code'] ?? -1), !empty($weather['is_day']));
        $units = [
            'temperature' => $settings['temperature_unit'] === 'fahrenheit' ? '°F' : '°C',
            'wind' => $this->windUnitLabel($settings['wind_speed_unit']),
            'precipitation' => $settings['precipitation_unit'] === 'inch' ? 'in' : 'mm',
        ];
        $iconUrl = $api->pluginAssetUrl($this->getName(), 'assets/icons/' . $visual['icon'] . '.svg');

        return $this->renderView('views/render.php', [
            'plugin' => $this,
            'settings' => $settings,
            'weather' => $weather,
            'units' => $units,
            'visual' => $visual,
            'iconUrl' => $iconUrl,
            'strings' => [
                'unknown_location' => $this->t('plugin.weather.unknown_location', 'Unknown location'),
                'unknown_condition' => $this->t('plugin.weather.condition.unknown', 'Unknown'),
                'label_feels_like' => $this->t('plugin.weather.label.feels_like', 'Feels like'),
                'label_wind' => $this->t('plugin.weather.label.wind', 'Wind'),
                'label_precipitation' => $this->t('plugin.weather.label.precipitation', 'Precipitation'),
            ],
        ]);
    }

    public function getFrontendAssets(array $slide, array $settings, PluginApi $api): array
    {
        return [
            'css' => [$api->pluginAssetUrl($this->getName(), 'assets/weather.css')],
            'js' => [$api->pluginAssetUrl($this->getName(), 'assets/weather.js')],
        ];
    }

    public function getStateData(array $slide, array $settings, PluginApi $api): array
    {
        return [
            'location_name' => $settings['location_name'] ?? null,
            'latitude' => $settings['latitude'] ?? null,
            'longitude' => $settings['longitude'] ?? null,
            'temperature_unit' => $settings['temperature_unit'] ?? null,
            'wind_speed_unit' => $settings['wind_speed_unit'] ?? null,
            'precipitation_unit' => $settings['precipitation_unit'] ?? null,
        ];
    }

    public function formatNumber(float $value): string
    {
        $rounded = round($value, 1);
        $string = number_format($rounded, 1, '.', '');
        return str_ends_with($string, '.0') ? substr($string, 0, -2) : $string;
    }

    public function t(string $key, string $default = ''): string
    {
        $messages = $this->getTranslations();
        return array_key_exists($key, $messages) ? (string)$messages[$key] : $default;
    }

    public function adminStrings(): array
    {
        return [
            'title' => $this->t('plugin.weather.admin.title', 'Weather plugin'),
            'description' => $this->t('plugin.weather.admin.description', 'Displays current weather for one selected location using Open-Meteo current weather data.'),
            'free_notice' => $this->t('plugin.weather.admin.free_notice', 'Free Open-Meteo mode is enabled. The public endpoint is intended for non-commercial use. For commercial use, configure an API key and commercial endpoint in the plugin settings page.'),
            'location_search' => $this->t('plugin.weather.admin.location_search', 'Location search'),
            'location_placeholder' => $this->t('plugin.weather.admin.location_placeholder', 'Search city or location'),
            'search_help' => $this->t('plugin.weather.admin.search_help', 'Type a city and save the slide. If the live search list is unavailable, the first matching location will be resolved automatically on save.'),
            'selected_location' => $this->t('plugin.weather.admin.selected_location', 'Selected location'),
            'timezone' => $this->t('plugin.weather.admin.timezone', 'Timezone'),
            'temperature_unit' => $this->t('plugin.weather.admin.temperature_unit', 'Temperature unit'),
            'wind_speed_unit' => $this->t('plugin.weather.admin.wind_speed_unit', 'Wind speed unit'),
            'precipitation_unit' => $this->t('plugin.weather.admin.precipitation_unit', 'Precipitation unit'),
            'show_datetime' => $this->t('plugin.weather.admin.show_datetime', 'Show client date and time'),
            'footer_note' => $this->t('plugin.weather.admin.footer_note', 'Weather data is fetched server-side and cached for one hour. The frontend clock uses the client browser time and timezone.'),
            'search_no_results' => $this->t('plugin.weather.admin.search_no_results', 'No locations found.'),
            'search_failed' => $this->t('plugin.weather.admin.search_failed', 'Location lookup failed.'),
        ];
    }

    public function globalSettingsStrings(): array
    {
        return [
            'title' => $this->t('plugin.weather.global.title', 'Open-Meteo API settings'),
            'description' => $this->t('plugin.weather.global.description', 'Configure the Open-Meteo endpoints used by all weather slides. Leave the API key empty for the public non-commercial API.'),
            'weather_base_url' => $this->t('plugin.weather.global.weather_base_url', 'Weather API endpoint'),
            'weather_base_url_help' => $this->t('plugin.weather.global.weather_base_url_help', 'Default public endpoint: https://api.open-meteo.com/v1/forecast'),
            'geocoding_base_url' => $this->t('plugin.weather.global.geocoding_base_url', 'Geocoding API endpoint'),
            'geocoding_base_url_help' => $this->t('plugin.weather.global.geocoding_base_url_help', 'Default public endpoint: https://geocoding-api.open-meteo.com/v1/search'),
            'api_key' => $this->t('plugin.weather.global.api_key', 'API key'),
            'api_key_help' => $this->t('plugin.weather.global.api_key_help', 'The key is stored in the database and sent only from server-side API requests.'),
        ];
    }

    private function getTranslations(): array
    {
        if ($this->translations !== null) {
            return $this->translations;
        }

        $locale = 'en';
        $configFile = dirname($this->rootPath, 2) . '/config/config.php';
        if (is_file($configFile)) {
            $config = require $configFile;
            if (is_array($config)) {
                $app = is_array($config['app'] ?? null) ? $config['app'] : [];
                $locale = (string)($app['locale'] ?? 'en');
            }
        }

        $messages = [];
        $fallbackFile = $this->rootPath . '/lang/en.php';
        if (is_file($fallbackFile)) {
            $loaded = require $fallbackFile;
            if (is_array($loaded)) {
                $messages = $loaded;
            }
        }

        $localeFile = $this->rootPath . '/lang/' . preg_replace('/[^a-z0-9_-]/i', '', strtolower($locale)) . '.php';
        if (is_file($localeFile)) {
            $loaded = require $localeFile;
            if (is_array($loaded)) {
                $messages = array_replace($messages, $loaded);
            }
        }

        $this->translations = $messages;
        return $messages;
    }

    private function sanitizeCoordinate(mixed $value, string $field): string
    {
        $string = trim((string)$value);
        if ($string === '' || !is_numeric($string)) {
            return '';
        }
        $number = (float)$string;
        if ($field === 'latitude' && ($number < -90 || $number > 90)) {
            throw new RuntimeException($this->t('plugin.weather.error.invalid_latitude', 'Weather plugin: invalid latitude.'));
        }
        if ($field === 'longitude' && ($number < -180 || $number > 180)) {
            throw new RuntimeException($this->t('plugin.weather.error.invalid_longitude', 'Weather plugin: invalid longitude.'));
        }
        return rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
    }

    private function loadGlobalSettings(PluginApi $api): array
    {
        return array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
    }

    private function normalizeEndpoint(mixed $value, string $default, string $errorKey, string $errorDefault): string
    {
        $url = trim((string)$value);
        if ($url === '') {
            return $default;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException($this->t($errorKey, $errorDefault));
        }

        return rtrim($url, '?&');
    }

    private function windUnitLabel(string $value): string
    {
        return match ($value) {
            'ms' => 'm/s',
            'mph' => 'mph',
            'kn' => 'kn',
            default => 'km/h',
        };
    }

    private function resolveLocationFromQuery(string $query, array $globalSettings): array
    {
        $queryParams = [
            'name' => $query,
            'count' => 1,
            'language' => 'en',
            'format' => 'json',
        ];
        $apiKey = trim((string)($globalSettings['api_key'] ?? ''));
        if ($apiKey !== '') {
            $queryParams['apikey'] = $apiKey;
        }

        $url = $this->buildUrlWithQuery($this->getGeocodingBaseUrl($globalSettings), $queryParams);
        $payload = $this->httpGetJson($url);
        $result = is_array($payload['results'][0] ?? null) ? $payload['results'][0] : null;
        if (!$result) {
            throw new RuntimeException($this->t('plugin.weather.error.location_not_found', 'Weather plugin: no matching location found.'));
        }

        $locationParts = [
            $result['name'] ?? null,
            $result['admin1'] ?? null,
            $result['country'] ?? null,
        ];
        $locationParts = array_values(array_filter(array_map(static fn ($v) => is_scalar($v) ? trim((string)$v) : '', $locationParts)));

        return [
            'location_name' => implode(', ', $locationParts),
            'latitude' => $this->sanitizeCoordinate($result['latitude'] ?? null, 'latitude'),
            'longitude' => $this->sanitizeCoordinate($result['longitude'] ?? null, 'longitude'),
            'country_code' => strtoupper(substr(trim((string)($result['country_code'] ?? '')), 0, 8)),
            'timezone_name' => trim((string)($result['timezone'] ?? '')),
        ];
    }

    private function fetchCurrentWeather(array $settings, array $globalSettings, PluginApi $api): array
    {
        $cacheKey = sha1(json_encode([
            $globalSettings['weather_base_url'],
            $globalSettings['api_key'],
            $settings['latitude'],
            $settings['longitude'],
            $settings['temperature_unit'],
            $settings['wind_speed_unit'],
            $settings['precipitation_unit'],
        ], JSON_UNESCAPED_SLASHES));
        $cacheFile = $api->pluginCachePath($this->getName(), $cacheKey . '.json');
        $ttl = (int)($this->config['cache_ttl_seconds'] ?? 3600);
        if (is_file($cacheFile) && (filemtime($cacheFile) ?: 0) >= time() - $ttl) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $url = $this->buildWeatherUrl($settings, $globalSettings);
        $payload = $this->httpGetJson($url);
        $current = is_array($payload['current'] ?? null) ? $payload['current'] : null;
        if (!$current) {
            throw new RuntimeException($this->t('plugin.weather.error.current_weather_missing', 'Weather plugin: weather API did not return current data.'));
        }

        @file_put_contents($cacheFile, json_encode($current, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $current;
    }

    private function buildWeatherUrl(array $settings, array $globalSettings): string
    {
        $base = (string)($globalSettings['weather_base_url'] ?? self::DEFAULT_WEATHER_BASE_URL);
        $query = [
            'latitude' => $settings['latitude'],
            'longitude' => $settings['longitude'],
            'current' => 'temperature_2m,apparent_temperature,precipitation,weather_code,wind_speed_10m,is_day',
            'temperature_unit' => $settings['temperature_unit'],
            'wind_speed_unit' => $settings['wind_speed_unit'],
            'precipitation_unit' => $settings['precipitation_unit'],
            'timezone' => 'auto',
        ];

        $apiKey = trim((string)($globalSettings['api_key'] ?? ''));
        if ($apiKey !== '') {
            $query['apikey'] = $apiKey;
        }

        return $this->buildUrlWithQuery($base, $query);
    }

    private function buildUrlWithQuery(string $base, array $query): string
    {
        $separator = '?';
        if (str_contains($base, '?')) {
            $separator = str_ends_with($base, '?') || str_ends_with($base, '&') ? '' : '&';
        }

        return $base . $separator . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function httpGetJson(string $url): array
    {
        $timeout = max(3, (int)($this->config['http_timeout_seconds'] ?? 12));
        $headers = [
            'Accept: application/json',
            'User-Agent: ' . (string)($this->config['user_agent'] ?? 'Hugin Weather Plugin/1.0'),
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
                throw new RuntimeException($this->t('plugin.weather.error.api_request_failed', 'Weather plugin: API request failed.'));
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
                throw new RuntimeException($this->t('plugin.weather.error.api_request_failed', 'Weather plugin: API request failed.'));
            }
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException($this->t('plugin.weather.error.invalid_api_response', 'Weather plugin: invalid API response.'));
        }
        return $data;
    }

    private function resolveVisuals(int $code, bool $isDay): array
    {
        return match (true) {
            $code === 0 => [
                'label' => $this->t($isDay ? 'plugin.weather.condition.sunny' : 'plugin.weather.condition.clear_night', $isDay ? 'Sunny' : 'Clear night'),
                'theme' => $isDay ? 'clear-day' : 'clear-night',
                'icon' => $isDay ? 'sun' : 'moon',
            ],
            in_array($code, [1, 2, 3], true) => [
                'label' => $this->t('plugin.weather.condition.cloudy', 'Cloudy'),
                'theme' => 'cloudy',
                'icon' => 'cloud',
            ],
            in_array($code, [45, 48], true) => [
                'label' => $this->t('plugin.weather.condition.fog', 'Fog'),
                'theme' => 'fog',
                'icon' => 'fog',
            ],
            in_array($code, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82], true) => [
                'label' => $this->t('plugin.weather.condition.rain', 'Rain'),
                'theme' => 'rain',
                'icon' => 'rain',
            ],
            in_array($code, [71, 73, 75, 77, 85, 86], true) => [
                'label' => $this->t('plugin.weather.condition.snow', 'Snow'),
                'theme' => 'snow',
                'icon' => 'snow',
            ],
            in_array($code, [95, 96, 99], true) => [
                'label' => $this->t('plugin.weather.condition.storm', 'Storm'),
                'theme' => 'storm',
                'icon' => 'storm',
            ],
            default => [
                'label' => $this->t('plugin.weather.condition.current_weather', 'Current weather'),
                'theme' => 'neutral',
                'icon' => 'cloud',
            ],
        };
    }
}
