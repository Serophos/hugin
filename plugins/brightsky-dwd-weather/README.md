# Hugin BrightSky DWD Weather Plugin

The BrightSky DWD Weather plugin adds the `brightsky-dwd-weather` slide type to Hugin. It renders current weather from Deutscher Wetterdienst data through the Bright Sky API, using a bundled DWD station list and optional weather animations.

## Configuration

Runtime options are managed in Hugin's plugin global settings page at `/admin/plugins/brightsky-dwd-weather/settings` and stored in the database.

Existing `plugins/brightsky-dwd-weather/config.php` files are no longer read by the plugin. Keep the file only as a manual migration reference if your installation previously customized it.

## Settings

| Setting | Type | Default | Description |
| --- | --- | --- | --- |
| `brightsky_current_weather_url` | URL string | `https://api.brightsky.dev/current_weather` | Bright Sky current weather endpoint. This can point to a self-hosted Bright Sky API. |
| `station_data_path` | string | `data/stations.json` | Plugin-relative path to the station list used for admin station search and DWD station resolution. The file is read from the plugin directory. |
| `cache_ttl_seconds` | integer | `900` | Default cache lifetime for Bright Sky responses, in seconds. The admin setting normalizes this to the range `60` to `86400`. |
| `http_timeout_seconds` | integer | `12` | Default timeout for Bright Sky HTTP requests, in seconds. The admin setting normalizes this to the range `3` to `60`. |
| `max_dist_meters` | integer | `50000` | Maximum distance passed to Bright Sky as `max_dist` when requesting current weather for a DWD station. This value is included in the cache key. |
| `timezone` | string | `Europe/Berlin` | IANA timezone used for Bright Sky requests and displayed timestamps. |
| `user_agent` | string | `Hugin BrightSky DWD Weather Plugin/1.0` | HTTP `User-Agent` header sent to the Bright Sky API. |

## Runtime Behavior

1. The admin slide form loads station search data from `station_data_path` through the plugin asset route.
2. Each slide stores the selected DWD station, display name, unit system, and animation options.
3. The frontend requests current weather from `brightsky_current_weather_url` with `dwd_station_id`, `tz`, `units=dwd`, and `max_dist`.
4. API responses are cached below `storage/cache/plugins/brightsky-dwd-weather/` using `cache_ttl_seconds`.
5. Weather animations render only when enabled on the slide and supported by the current weather conditions.

## Common Adjustments

- Use a self-hosted Bright Sky API by changing `brightsky_current_weather_url` in the admin global settings.
- Replace `data/stations.json` if you maintain a custom station subset.
- Reduce `cache_ttl_seconds` for fresher readings or increase it to reduce API traffic.
