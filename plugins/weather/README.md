# Hugin Weather Plugin

The Weather plugin adds the `weather` slide type to Hugin. It renders current weather for a selected location using Open-Meteo data, with server-side fetching, caching, configurable units, and optional date/time display.

## Configuration

Runtime options are managed in Hugin's plugin global settings page at `/admin/plugins/weather/settings` and stored in the database.

Existing `plugins/weather/config.php` files are no longer read by the plugin. Keep the file only as a manual migration reference if your installation previously customized it.

## Settings

| Setting | Type | Default | Description |
| --- | --- | --- | --- |
| `weather_base_url` | URL string | `https://api.open-meteo.com/v1/forecast` | Open-Meteo forecast endpoint used for current weather requests. |
| `geocoding_base_url` | URL string | `https://geocoding-api.open-meteo.com/v1/search` | Open-Meteo geocoding endpoint used when resolving a location search. |
| `api_key` | string | empty | Optional Open-Meteo API key. If set, the plugin sends it as `apikey` in server-side requests. |
| `cache_ttl_seconds` | integer | `3600` | Number of seconds that fetched current weather data may be reused from `storage/cache/plugins/weather/`. A value of `3600` means one hour. |
| `http_timeout_seconds` | integer | `12` | Timeout in seconds for server-side API requests. The plugin enforces a minimum effective timeout of `3` seconds. |
| `user_agent` | string | `Hugin Weather Plugin/1.0` | HTTP `User-Agent` header sent to Open-Meteo geocoding and weather endpoints. Change this if your deployment needs a custom contact or product identifier. |

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
