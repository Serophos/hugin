# Hugin BrightSky DWD Weather Plugin

The BrightSky DWD Weather plugin adds the `brightsky-dwd-weather` slide type to Hugin. It renders current weather from Deutscher Wetterdienst data through the Bright Sky API, using a bundled DWD station list and optional weather animations.

## Configuration Files

This plugin has two configuration layers:

- `plugins/brightsky-dwd-weather/config.php`: file-based defaults and local runtime settings.
- `/admin/plugins/brightsky-dwd-weather/settings`: Hugin global plugin settings stored in the database for the Bright Sky endpoint, cache TTL, timeout, and timezone.

The admin global settings are initialized from `config.php` defaults and can override several runtime values without editing files.

## `config.php` Map

```php
<?php
return [
    'brightsky_current_weather_url' => 'https://api.brightsky.dev/current_weather',
    'station_data_path' => 'data/stations.json',
    'cache_ttl_seconds' => 900,
    'http_timeout_seconds' => 12,
    'max_dist_meters' => 50000,
    'timezone' => 'Europe/Berlin',
    'debug_force_rain_effect' => false,
    'debug_force_lightning_effect' => false,
    'user_agent' => 'Hugin BrightSky DWD Weather Plugin/1.0',
];
```

## Settings

| Setting | Type | Default | Description |
| --- | --- | --- | --- |
| `brightsky_current_weather_url` | URL string | `https://api.brightsky.dev/current_weather` | Bright Sky current weather endpoint. This value is also exposed as a global plugin setting in the admin UI and can point to a self-hosted Bright Sky API. |
| `station_data_path` | string | `data/stations.json` | Plugin-relative path to the station list used for admin station search and DWD station resolution. The file is read from the plugin directory. |
| `cache_ttl_seconds` | integer | `900` | Default cache lifetime for Bright Sky responses, in seconds. The global admin setting normalizes this to the range `60` to `86400`. |
| `http_timeout_seconds` | integer | `12` | Default timeout for Bright Sky HTTP requests, in seconds. The global admin setting normalizes this to the range `3` to `60`. |
| `max_dist_meters` | integer | `50000` | Maximum distance passed to Bright Sky as `max_dist` when requesting current weather for a DWD station. This stays file-based and is included in the cache key. |
| `timezone` | string | `Europe/Berlin` | IANA timezone used for Bright Sky requests and displayed timestamps. This value is also exposed as a global plugin setting in the admin UI. |
| `debug_force_rain_effect` | boolean | `false` | Forces the rain animation when weather animations are enabled. Intended for local visual testing only. |
| `debug_force_lightning_effect` | boolean | `false` | Forces the lightning animation when weather animations are enabled. Intended for local visual testing only. |
| `user_agent` | string | `Hugin BrightSky DWD Weather Plugin/1.0` | HTTP `User-Agent` header sent to the Bright Sky API. |

## Global Setting Overrides

The plugin global settings page can override these `config.php` values at runtime:

- `brightsky_current_weather_url`
- `cache_ttl_seconds`
- `http_timeout_seconds`
- `timezone`

These overrides are stored in Hugin's `plugin_global_settings` table. The following values remain file-only:

- `station_data_path`
- `max_dist_meters`
- `debug_force_rain_effect`
- `debug_force_lightning_effect`
- `user_agent`

## Runtime Behavior

1. The admin slide form loads station search data from `station_data_path` through the plugin asset route.
2. Each slide stores the selected DWD station, display name, unit system, and animation options.
3. The frontend requests current weather from `brightsky_current_weather_url` with `dwd_station_id`, `tz`, `units=dwd`, and `max_dist`.
4. API responses are cached below `storage/cache/plugins/brightsky-dwd-weather/` using `cache_ttl_seconds`.
5. Weather animations render only when enabled on the slide, unless a debug force flag is enabled in `config.php`.

## Common Adjustments

- Use a self-hosted Bright Sky API by changing `brightsky_current_weather_url` in the admin global settings or in `config.php`.
- Replace `data/stations.json` if you maintain a custom station subset.
- Reduce `cache_ttl_seconds` for fresher readings or increase it to reduce API traffic.
- Keep debug force flags disabled outside local testing.
