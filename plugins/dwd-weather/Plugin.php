<?php
namespace Plugins\DwdWeather;

use App\Core\AbstractSlidePlugin;
use App\Core\PluginApi;
use RuntimeException;

class Plugin extends AbstractSlidePlugin
{
    private const DWD_POI_BASE_URL = 'https://opendata.dwd.de/weather/weather_reports/poi/';

    private array $config;

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
        return [];
    }

    public function isCommercialMode(array $globalSettings = []): bool
    {
        return false; // DWD data is always free
    }

    public function renderAdminSettings(array $slide, array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);

        return $this->renderView('views/config.php', [
            'plugin' => $this,
            'settings' => $settings,
            'strings' => $this->adminStrings(),
        ]);
    }

    public function renderGlobalSettings(array $settings, PluginApi $api): string
    {
        return '';
    }

    public function normalizeGlobalSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        return [];
    }

    public function normalizeSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        $settings = array_replace($this->getDefaultSettings(), $existingSettings, $input);
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
            throw new RuntimeException($this->t('plugin.dwd-weather.error.invalid_temperature_unit', 'DWD Weather plugin: invalid temperature unit.'));
        }
        if (!in_array($wind, ['kmh', 'ms', 'mph', 'kn'], true)) {
            throw new RuntimeException($this->t('plugin.dwd-weather.error.invalid_wind_speed_unit', 'DWD Weather plugin: invalid wind speed unit.'));
        }
        if (!in_array($precip, ['mm', 'inch'], true)) {
            throw new RuntimeException($this->t('plugin.dwd-weather.error.invalid_precipitation_unit', 'DWD Weather plugin: invalid precipitation unit.'));
        }

        $settings['temperature_unit'] = $temp;
        $settings['wind_speed_unit'] = $wind;
        $settings['precipitation_unit'] = $precip;
        $settings['show_datetime'] = !empty($settings['show_datetime']);

        if (($settings['location_name'] === '' || $settings['latitude'] === '' || $settings['longitude'] === '') && $settings['location_query'] !== '') {
            $resolved = $this->resolveLocationFromQuery($settings['location_query']);
            $settings['location_name'] = (string)($resolved['location_name'] ?? '');
            $settings['latitude'] = (string)($resolved['latitude'] ?? '');
            $settings['longitude'] = (string)($resolved['longitude'] ?? '');
            $settings['country_code'] = (string)($resolved['country_code'] ?? '');
            $settings['timezone_name'] = (string)($resolved['timezone_name'] ?? '');
        }

        if ($settings['location_name'] === '' || $settings['latitude'] === '' || $settings['longitude'] === '') {
            throw new RuntimeException($this->t('plugin.dwd-weather.error.location_required', 'DWD Weather plugin: please search and select a location.'));
        }

        return $settings;
    }

    public function renderFrontend(array $slide, array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        $weather = $this->fetchCurrentWeather($settings, $api);
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
                'unknown_location' => $this->t('plugin.dwd-weather.unknown_location', 'Unknown location'),
                'unknown_condition' => $this->t('plugin.dwd-weather.condition.unknown', 'Unknown'),
                'label_feels_like' => $this->t('plugin.dwd-weather.label.feels_like', 'Feels like'),
                'label_wind' => $this->t('plugin.dwd-weather.label.wind', 'Wind'),
                'label_precipitation' => $this->t('plugin.dwd-weather.label.precipitation', 'Precipitation'),
            ],
        ]);
    }

    public function getFrontendAssets(array $slide, array $settings, PluginApi $api): array
    {
        return [
            'css' => [$api->pluginAssetUrl($this->getName(), 'assets/dwd-weather.css')],
            'js' => [$api->pluginAssetUrl($this->getName(), 'assets/dwd-weather.js')],
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
        return __('plugins.' . $this->getName() . '.' . ltrim($key, '.'), [], $default);
    }

    public function adminStrings(): array
    {
        return [
            'title' => $this->t('plugin.dwd-weather.admin.title', 'DWD Weather plugin'),
            'description' => $this->t('plugin.dwd-weather.admin.description', 'Displays current weather for one selected location using DWD Open Data weather observations.'),
            'free_notice' => $this->t('plugin.dwd-weather.admin.free_notice', 'This plugin uses free DWD Open Data. Weather data is updated every 10 minutes.'),
            'location_search' => $this->t('plugin.dwd-weather.admin.location_search', 'Location search'),
            'location_placeholder' => $this->t('plugin.dwd-weather.admin.location_placeholder', 'Search city or location'),
            'search_help' => $this->t('plugin.dwd-weather.admin.search_help', 'Type a city and save the slide. If the live search list is unavailable, the first matching location will be resolved automatically on save.'),
            'selected_location' => $this->t('plugin.dwd-weather.admin.selected_location', 'Selected location'),
            'timezone' => $this->t('plugin.dwd-weather.admin.timezone', 'Timezone'),
            'temperature_unit' => $this->t('plugin.dwd-weather.admin.temperature_unit', 'Temperature unit'),
            'wind_speed_unit' => $this->t('plugin.dwd-weather.admin.wind_speed_unit', 'Wind speed unit'),
            'precipitation_unit' => $this->t('plugin.dwd-weather.admin.precipitation_unit', 'Precipitation unit'),
            'show_datetime' => $this->t('plugin.dwd-weather.admin.show_datetime', 'Show client date and time'),
            'footer_note' => $this->t('plugin.dwd-weather.admin.footer_note', 'Weather data is fetched server-side and cached for 10 minutes. The frontend clock uses the client browser time and timezone.'),
            'search_no_results' => $this->t('plugin.dwd-weather.admin.search_no_results', 'No locations found.'),
            'search_failed' => $this->t('plugin.dwd-weather.admin.search_failed', 'Location lookup failed.'),
        ];
    }

    private function sanitizeCoordinate(mixed $value, string $field): string
    {
        $string = trim((string)$value);
        if ($string === '' || !is_numeric($string)) {
            return '';
        }
        $number = (float)$string;
        if ($field === 'latitude' && ($number < -90 || $number > 90)) {
            throw new RuntimeException($this->t('plugin.dwd-weather.error.invalid_latitude', 'DWD Weather plugin: invalid latitude.'));
        }
        if ($field === 'longitude' && ($number < -180 || $number > 180)) {
            throw new RuntimeException($this->t('plugin.dwd-weather.error.invalid_longitude', 'DWD Weather plugin: invalid longitude.'));
        }
        return rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
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

    private function getDwdStations(): array
    {
        return [
            // Major POI Stations
            ['id' => 'P0034', 'name' => 'Hamburg', 'lat' => 53.5511, 'lon' => 9.9937],
            ['id' => 'P0049', 'name' => 'Berlin', 'lat' => 52.5200, 'lon' => 13.4050],
            ['id' => 'P0130', 'name' => 'München', 'lat' => 48.1351, 'lon' => 11.5820],
            ['id' => 'P0229', 'name' => 'Köln', 'lat' => 50.9375, 'lon' => 6.9603],
            ['id' => 'P0267', 'name' => 'Frankfurt', 'lat' => 50.1109, 'lon' => 8.6821],
            ['id' => 'P0297', 'name' => 'Stuttgart', 'lat' => 48.7758, 'lon' => 9.1829],
            ['id' => 'P0326', 'name' => 'Düsseldorf', 'lat' => 51.2277, 'lon' => 6.7735],
            ['id' => 'P0367', 'name' => 'Dortmund', 'lat' => 51.5136, 'lon' => 7.4653],
            ['id' => 'P0409', 'name' => 'Essen', 'lat' => 51.4556, 'lon' => 7.0116],
            ['id' => 'P0441', 'name' => 'Bremen', 'lat' => 53.0793, 'lon' => 8.8017],
            ['id' => 'P0476', 'name' => 'Dresden', 'lat' => 51.0504, 'lon' => 13.7373],
            ['id' => 'P0510', 'name' => 'Hannover', 'lat' => 52.3759, 'lon' => 9.7320],
            ['id' => 'P0544', 'name' => 'Nürnberg', 'lat' => 49.4521, 'lon' => 11.0767],
            ['id' => 'P0575', 'name' => 'Duisburg', 'lat' => 51.4344, 'lon' => 6.7623],
            ['id' => 'P0611', 'name' => 'Bochum', 'lat' => 51.4818, 'lon' => 7.2197],
            ['id' => 'P0648', 'name' => 'Wuppertal', 'lat' => 51.2562, 'lon' => 7.1508],
            ['id' => 'P0688', 'name' => 'Bielefeld', 'lat' => 52.0302, 'lon' => 8.5325],
            ['id' => 'P0724', 'name' => 'Bonn', 'lat' => 50.7374, 'lon' => 7.0982],
            ['id' => 'P0761', 'name' => 'Münster', 'lat' => 51.9624, 'lon' => 7.6257],
            ['id' => 'P0798', 'name' => 'Karlsruhe', 'lat' => 49.0069, 'lon' => 8.4037],
            // Regional & Airport Stations
            ['id' => '01008', 'name' => 'Kiel', 'lat' => 54.3233, 'lon' => 10.1393],
            ['id' => '02186', 'name' => 'Sylt', 'lat' => 54.9150, 'lon' => 8.3050],
            ['id' => '03005', 'name' => 'Helgoland', 'lat' => 54.1850, 'lon' => 7.8883],
            ['id' => '06210', 'name' => 'Frankfurt Airport', 'lat' => 50.0365, 'lon' => 8.5425],
            ['id' => '10004', 'name' => 'Zugspitze', 'lat' => 47.4208, 'lon' => 10.9860],
            ['id' => '10382', 'name' => 'Berlin Tempelhof', 'lat' => 52.4751, 'lon' => 13.4019],
            ['id' => '11010', 'name' => 'Potsdam', 'lat' => 52.3886, 'lon' => 13.0645],
            ['id' => '16021', 'name' => 'Leipzig', 'lat' => 51.3397, 'lon' => 12.3731],
            ['id' => '16022', 'name' => 'Chemnitz', 'lat' => 50.8242, 'lon' => 12.9244],
            ['id' => '17022', 'name' => 'Dresden Airport', 'lat' => 51.1315, 'lon' => 13.6865],
            ['id' => '21824', 'name' => 'Hanover Airport', 'lat' => 52.4614, 'lon' => 9.6852],
            ['id' => '22113', 'name' => 'Hamburg Airport', 'lat' => 53.6304, 'lon' => 10.0095],
            ['id' => '26063', 'name' => 'Erfurt', 'lat' => 50.9789, 'lon' => 11.0170],
            ['id' => '34123', 'name' => 'Würzburg', 'lat' => 49.7912, 'lon' => 9.9533],
            ['id' => '37171', 'name' => 'Bamberg', 'lat' => 49.8913, 'lon' => 10.8855],
            ['id' => '40155', 'name' => 'Nuremberg Airport', 'lat' => 49.4992, 'lon' => 11.0748],
            ['id' => 'A191', 'name' => 'Bremen Airport', 'lat' => 53.0476, 'lon' => 8.7867],
            ['id' => 'B006', 'name' => 'Braunschweig', 'lat' => 52.2688, 'lon' => 10.5282],
            ['id' => 'E008', 'name' => 'Aachen', 'lat' => 50.7887, 'lon' => 6.0842],
            ['id' => 'E082', 'name' => 'Augsburg', 'lat' => 48.3705, 'lon' => 10.8910],
            ['id' => 'E355', 'name' => 'Bayreuth', 'lat' => 49.9458, 'lon' => 11.5781],
            ['id' => 'F105', 'name' => 'Flensburg', 'lat' => 54.7705, 'lon' => 9.4267],
            ['id' => 'H012', 'name' => 'Hannover', 'lat' => 52.3759, 'lon' => 9.7320],
            ['id' => 'K017', 'name' => 'Kaiserslautern', 'lat' => 49.4447, 'lon' => 7.7666],
            ['id' => 'L031', 'name' => 'Ludwigshafen', 'lat' => 49.4836, 'lon' => 8.4422],
            ['id' => 'M225', 'name' => 'Mannheim', 'lat' => 49.4891, 'lon' => 8.4673],
            ['id' => 'M348', 'name' => 'Mönchengladbach', 'lat' => 51.1649, 'lon' => 6.3933],
            ['id' => 'N272', 'name' => 'Nürnberg', 'lat' => 49.4521, 'lon' => 11.0767],
            ['id' => 'O025', 'name' => 'Oberstaufen', 'lat' => 47.6372, 'lon' => 10.3167],
            ['id' => 'P741', 'name' => 'Passau', 'lat' => 48.5677, 'lon' => 13.4532],
            ['id' => 'Q055', 'name' => 'Quedlinburg', 'lat' => 51.7837, 'lon' => 11.1434],
            ['id' => 'Z901', 'name' => 'Zugspitze', 'lat' => 47.4208, 'lon' => 10.9860],
            ['id' => 'Z920', 'name' => 'Zwiefalten', 'lat' => 48.2017, 'lon' => 9.2689],
        ];
    }

    private function normalizeSearchString(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = str_replace(['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'], ['ae', 'oe', 'ue', 'ae', 'oe', 'ue', 'ss'], $normalized);
        return preg_replace('/[^a-z0-9 ]/', '', $normalized);
    }

    private function resolveLocationFromQuery(string $query): array
    {
        $stations = $this->getDwdStations();
        $queryNormalized = $this->normalizeSearchString($query);

        // First try exact matches
        foreach ($stations as $station) {
            if ($this->normalizeSearchString($station['name']) === $queryNormalized) {
                return [
                    'location_name' => $station['name'],
                    'latitude' => (string)$station['lat'],
                    'longitude' => (string)$station['lon'],
                    'country_code' => 'DE',
                    'timezone_name' => 'Europe/Berlin',
                ];
            }
        }

        // Then try partial matches
        foreach ($stations as $station) {
            if (str_contains($this->normalizeSearchString($station['name']), $queryNormalized)) {
                return [
                    'location_name' => $station['name'],
                    'latitude' => (string)$station['lat'],
                    'longitude' => (string)$station['lon'],
                    'country_code' => 'DE',
                    'timezone_name' => 'Europe/Berlin',
                ];
            }
        }

        throw new RuntimeException($this->t('plugin.dwd-weather.error.location_not_found', 'DWD Weather plugin: no matching location found.'));
    }

    private function fetchCurrentWeather(array $settings, PluginApi $api): array
    {
        $cacheKey = sha1(json_encode([
            $settings['latitude'],
            $settings['longitude'],
            $settings['temperature_unit'],
            $settings['wind_speed_unit'],
            $settings['precipitation_unit'],
        ], JSON_UNESCAPED_SLASHES));
        $cacheFile = $api->pluginCachePath($this->getName(), $cacheKey . '.json');
        $ttl = (int)($this->config['cache_ttl_seconds'] ?? 600); // 10 minutes for DWD data
        if (is_file($cacheFile) && (filemtime($cacheFile) ?: 0) >= time() - $ttl) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $weather = $this->fetchDwdWeather($settings['latitude'], $settings['longitude']);
        @file_put_contents($cacheFile, json_encode($weather, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $weather;
    }

    private function fetchDwdWeather(string $latitude, string $longitude): array
    {
        // Find nearest DWD station
        $station = $this->findNearestStation((float)$latitude, (float)$longitude);
        if (!$station) {
            throw new RuntimeException($this->t('plugin.dwd-weather.error.no_station_found', 'DWD Weather plugin: no weather station found for this location.'));
        }

        // Download CSV data for the station
        $url = self::DWD_POI_BASE_URL . $station['id'] . '-BEOB.csv';
        $csvData = $this->httpGetCsv($url);

        if (empty($csvData)) {
            throw new RuntimeException($this->t('plugin.dwd-weather.error.no_data', 'DWD Weather plugin: no weather data available for this station.'));
        }

        // Parse the latest weather data
        return $this->parseLatestWeatherData($csvData);
    }

    private function findNearestStation(float $lat, float $lon): ?array
    {
        $stations = $this->getDwdStations();

        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($stations as $station) {
            $distance = $this->calculateDistance($lat, $lon, $station['lat'], $station['lon']);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $station;
            }
        }

        return $nearest;
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function httpGetCsv(string $url): array
    {
        $timeout = max(3, (int)($this->config['http_timeout_seconds'] ?? 12));
        $headers = [
            'Accept: text/csv',
            'User-Agent: ' . (string)($this->config['user_agent'] ?? 'Hugin DWD Weather Plugin/1.0'),
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
                $errorMsg = 'DWD API error: HTTP ' . $status . ' for ' . $url;
                error_log('DWD Weather Plugin: ' . $errorMsg . ' - Response: ' . substr((string)$body, 0, 200));
                throw new RuntimeException($this->t('plugin.dwd-weather.error.api_request_failed', 'DWD Weather plugin: API request failed.'));
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
                error_log('DWD Weather Plugin: file_get_contents failed for ' . $url . ' - ' . (isset($http_response_header) ? implode(', ', $http_response_header) : 'No response'));
                throw new RuntimeException($this->t('plugin.dwd-weather.error.api_request_failed', 'DWD Weather plugin: API request failed.'));
            }
        }

        return $this->parseCsv($body);
    }

    private function parseCsv(string $csvContent): array
    {
        $lines = explode("\n", trim($csvContent));
        $data = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $fields = str_getcsv($line, ';');
            if (count($fields) >= 15) { // Ensure we have enough fields
                $data[] = [
                    'station_id' => $fields[0] ?? '',
                    'timestamp' => $fields[1] ?? '',
                    'temperature' => is_numeric($fields[2] ?? null) ? (float)$fields[2] : null,
                    'humidity' => is_numeric($fields[3] ?? null) ? (float)$fields[3] : null,
                    'wind_speed' => is_numeric($fields[4] ?? null) ? (float)$fields[4] : null,
                    'wind_direction' => is_numeric($fields[5] ?? null) ? (float)$fields[5] : null,
                    'precipitation' => is_numeric($fields[6] ?? null) ? (float)$fields[6] : null,
                    'pressure' => is_numeric($fields[7] ?? null) ? (float)$fields[7] : null,
                    'visibility' => is_numeric($fields[8] ?? null) ? (float)$fields[8] : null,
                    'weather_code' => is_numeric($fields[9] ?? null) ? (int)$fields[9] : null,
                ];
            }
        }

        return $data;
    }

    private function parseLatestWeatherData(array $csvData): array
    {
        if (empty($csvData)) {
            throw new RuntimeException($this->t('plugin.dwd-weather.error.no_data', 'DWD Weather plugin: no weather data available.'));
        }

        // Get the most recent data point
        $latest = end($csvData);

        // Convert DWD weather codes to WMO codes used by the visual system
        $dwdCode = $latest['weather_code'] ?? null;
        $wmoCode = $this->convertDwdToWmoCode($dwdCode);

        // Calculate apparent temperature (simplified)
        $temperature = $latest['temperature'] ?? null;
        $windSpeed = $latest['wind_speed'] ?? null;
        $humidity = $latest['humidity'] ?? null;

        $apparentTemperature = $temperature;
        if ($temperature !== null && $windSpeed !== null && $humidity !== null) {
            // Simplified wind chill calculation for temperatures below 10°C
            if ($temperature < 10 && $windSpeed > 4.8) {
                $apparentTemperature = 13.12 + 0.6215 * $temperature - 11.37 * pow($windSpeed, 0.16) + 0.3965 * $temperature * pow($windSpeed, 0.16);
            }
        }

        return [
            'temperature_2m' => $temperature,
            'apparent_temperature' => $apparentTemperature,
            'precipitation' => $latest['precipitation'] ?? 0,
            'weather_code' => $wmoCode,
            'wind_speed_10m' => $windSpeed,
            'is_day' => $this->isDaytime(), // Simplified - could be improved with timezone
        ];
    }

    private function convertDwdToWmoCode(?int $dwdCode): int
    {
        // Mapping from DWD weather codes to WMO 4677 codes
        return match ($dwdCode) {
            0 => 0, // Clear sky
            1, 2 => 1, // Mainly clear -> Few clouds
            3 => 2, // Partly cloudy -> Scattered clouds
            4 => 3, // Overcast
            5, 6 => 45, // Fog
            7 => 48, // Depositing rime fog
            8, 9 => 51, // Drizzle
            10, 11 => 53, // Moderate drizzle
            12 => 55, // Dense drizzle
            13, 14 => 61, // Light rain
            15, 16 => 63, // Moderate rain
            17 => 65, // Heavy rain
            18, 19 => 66, // Light freezing rain
            20, 21 => 67, // Heavy freezing rain
            22, 23 => 71, // Light snow
            24, 25 => 73, // Moderate snow
            26 => 75, // Heavy snow
            27 => 77, // Snow grains
            28, 29 => 80, // Light rain showers
            30, 31 => 81, // Moderate rain showers
            32 => 82, // Violent rain showers
            33, 34 => 85, // Light snow showers
            35, 36 => 86, // Heavy snow showers
            37, 38 => 95, // Thunderstorm
            39, 40 => 96, // Thunderstorm with hail
            default => 0, // Default to clear
        };
    }

    private function isDaytime(): bool
    {
        $hour = (int)date('H');
        return $hour >= 6 && $hour <= 21; // Simplified daytime check
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
            'User-Agent: ' . (string)($this->config['user_agent'] ?? 'Hugin DWD Weather Plugin/1.0'),
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
                throw new RuntimeException($this->t('plugin.dwd-weather.error.api_request_failed', 'DWD Weather plugin: API request failed.'));
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
                throw new RuntimeException($this->t('plugin.dwd-weather.error.api_request_failed', 'DWD Weather plugin: API request failed.'));
            }
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException($this->t('plugin.dwd-weather.error.invalid_api_response', 'DWD Weather plugin: invalid API response.'));
        }
        return $data;
    }

    private function resolveVisuals(int $code, bool $isDay): array
    {
        return match (true) {
            $code === 0 => [
                'label' => $this->t($isDay ? 'plugin.dwd-weather.condition.sunny' : 'plugin.dwd-weather.condition.clear_night', $isDay ? 'Sunny' : 'Clear night'),
                'theme' => $isDay ? 'clear-day' : 'clear-night',
                'icon' => $isDay ? 'sun' : 'moon',
            ],
            in_array($code, [1, 2, 3], true) => [
                'label' => $this->t('plugin.dwd-weather.condition.cloudy', 'Cloudy'),
                'theme' => 'cloudy',
                'icon' => 'cloud',
            ],
            in_array($code, [45, 48], true) => [
                'label' => $this->t('plugin.dwd-weather.condition.fog', 'Fog'),
                'theme' => 'fog',
                'icon' => 'fog',
            ],
            in_array($code, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82], true) => [
                'label' => $this->t('plugin.dwd-weather.condition.rain', 'Rain'),
                'theme' => 'rain',
                'icon' => 'rain',
            ],
            in_array($code, [71, 73, 75, 77, 85, 86], true) => [
                'label' => $this->t('plugin.dwd-weather.condition.snow', 'Snow'),
                'theme' => 'snow',
                'icon' => 'snow',
            ],
            in_array($code, [95, 96, 99], true) => [
                'label' => $this->t('plugin.dwd-weather.condition.storm', 'Storm'),
                'theme' => 'storm',
                'icon' => 'storm',
            ],
            default => [
                'label' => $this->t('plugin.dwd-weather.condition.current_weather', 'Current weather'),
                'theme' => 'neutral',
                'icon' => 'cloud',
            ],
        };
    }
}
