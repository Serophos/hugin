# Hugin Weather Plugin (Plugin API v2)

This plugin adds a `weather` slide type to Hugin v5.

## Features
- Current weather only
- Open-Meteo city search in admin config
- Temperature, wind speed, and precipitation unit selection
- Optional client-side date/time display
- Responsive gradient background and large weather icons
- One-hour server-side cache in Hugin's global plugin cache storage
- Global plugin settings for Open-Meteo endpoints and API key
- One-hour frontend refresh

## Install
1. Copy the `weather` folder into `plugins/` of your Hugin installation.
2. Ensure the plugin is enabled in `/admin/plugins`.
3. Create or edit a slide and select the `weather` slide type.
4. Search and select a city, then save the slide.

## Commercial use
Open `/admin/plugins`, choose **Configure** for the Weather plugin, then set the Open-Meteo endpoint and API key according to your Open-Meteo commercial plan. These values are stored in Hugin's plugin settings database table.

## Notes
- Built for Hugin Plugin API v2.
- Cache files are written through `PluginApi::pluginCachePath()` below `storage/cache/plugins/weather/`.
