# Hugin Weather Plugin

The Weather plugin adds the `weather` slide type to Hugin. It renders current weather for a selected location using Open-Meteo data, with server-side fetching, caching, configurable units, and optional date/time display.

## Configuration Files

This plugin has two configuration layers:

- `plugins/weather/config.php`: file-based runtime defaults for cache and HTTP behavior.
- `/admin/plugins/weather/settings`: Hugin global plugin settings stored in the database for Open-Meteo endpoints and an optional API key.

This README documents the file-based `config.php` settings. The file is required at runtime; a missing `config.php` is treated as an incomplete plugin setup.

## `config.php` Map

```php
<?php
return [
    'cache_ttl_seconds' => 3600,
    'http_timeout_seconds' => 12,
    'user_agent' => 'Hugin Weather Plugin/1.0',
];
```

## Settings

| Setting | Type | Default | Description |
| --- | --- | --- | --- |
| `cache_ttl_seconds` | integer | `3600` | Number of seconds that fetched current weather data may be reused from `storage/cache/plugins/weather/`. A value of `3600` means one hour. |
| `http_timeout_seconds` | integer | `12` | Timeout in seconds for server-side API requests. The plugin enforces a minimum effective timeout of `3` seconds. |
| `user_agent` | string | `Hugin Weather Plugin/1.0` | HTTP `User-Agent` header sent to Open-Meteo geocoding and weather endpoints. Change this if your deployment needs a custom contact or product identifier. |

## Related Global Plugin Settings

The following settings are not stored in `config.php`; they are edited in the Hugin admin area and saved in `plugin_global_settings`:

| Setting | Default | Description |
| --- | --- | --- |
| `weather_base_url` | `https://api.open-meteo.com/v1/forecast` | Open-Meteo forecast endpoint used for current weather requests. |
| `geocoding_base_url` | `https://geocoding-api.open-meteo.com/v1/search` | Open-Meteo geocoding endpoint used when resolving a location search. |
| `api_key` | empty | Optional Open-Meteo API key. If set, the plugin sends it as `apikey` in server-side requests. |

## Runtime Behavior

1. A slide stores location, coordinate, unit, and display options in slide plugin settings.
2. The plugin resolves location searches through the configured geocoding endpoint.
3. Current weather requests are made server-side.
4. Weather responses are cached below `storage/cache/plugins/weather/` using `cache_ttl_seconds`.
5. Frontend CSS and JavaScript are served through Hugin's plugin asset route.

## Common Adjustments

- Increase `cache_ttl_seconds` to reduce API traffic.
- Decrease `cache_ttl_seconds` when displays need fresher weather data.
- Increase `http_timeout_seconds` for slow networks or proxy setups.
- Set a custom `user_agent` if your API provider or proxy policy requires one.
