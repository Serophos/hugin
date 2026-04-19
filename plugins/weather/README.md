# Hugin Weather Plugin (v5 API)

This plugin adds a `weather` slide type to Hugin v5.

## Features
- Current weather only
- Open-Meteo city search in admin config
- Temperature, wind speed, and precipitation unit selection
- Optional client-side date/time display
- Responsive gradient background and large weather icons
- One-hour server-side cache and one-hour frontend refresh

## Install
1. Copy the `weather` folder into `plugins/` of your Hugin installation.
2. Ensure the plugin is enabled in `/admin/plugins`.
3. Create or edit a slide and select the `weather` slide type.
4. Search and select a city, then save the slide.

## Commercial use
Edit `plugins/weather/config.php` and set `commercial_mode`, `weather_base_url`, and `api_key` according to your Open-Meteo commercial plan.

## Notes
- Built for the current Hugin v5 plugin API.
- Manifest labels are single-language because current v5 core does not localize `plugin.json`.
- Included `lang/en.php` and `lang/de.php` are for future use if core i18n is extended to plugins.
