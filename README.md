# Hugin

![Hugin Admin Backend Dashboard showing Data about Digital Signage displays etc.](/docs/Hugin%20Dashboard.jpg)
## 1. What Hugin Is

Hugin is an open source PHP/MySQL digital signage system for managing information displays from a browser. It provides public display URLs for screens, an admin backend for content management, reusable media and slides, scheduled playlists, display monitoring, and a plugin API for custom slide types.

Hugin is designed for simple web-based signage deployments: point a display browser at `/display/<slug>`, manage content in `/admin`, and let Hugin resolve the currently active playlist from the display, schedule, priority, and timezone.

Current application metadata:

- Version: `0.8`
- License: `AGPL-3.0-or-later`
- Runtime: PHP, MySQL, JavaScript
- Plugin API version: `2`

## 2. Installation And Configuration

### Requirements

- PHP `8.0+`
- MySQL or MariaDB compatible with the provided schema
- Composer
- Node.js `18+` and npm for frontend asset development or release builds
- Apache with `mod_rewrite` or an equivalent Nginx/PHP-FPM setup
- A web server document root pointing to `public/`

### Quick Installation

1. Install PHP dependencies:

   ```bash
   composer install
   ```

2. Create the configuration file:

   ```bash
   cp config/config.example.php config/config.php
   ```

3. Edit `config/config.php`.

4. Create an empty database, then import the schema and demo data into it:

   ```bash
   mysql -u root -p -e "CREATE DATABASE hugin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p hugin < database.sql
   ```

5. Set the same database name in `config/config.php`.

6. Point the web server document root to `public/`.

7. Make sure PHP can write uploaded media below `public/uploads/`.

8. Open `/admin/login`.

The seeded demo users use the password `admin123!`:

- `admin`, role `admin`, active by default
- `editor`, role `editor`, inactive by default

Change initial passwords immediately on a real installation. Hugin shows a warning to users until their password has been changed after account creation.

### Frontend Asset Builds

Hugin keeps generated frontend assets committed so a normal production deployment can remain PHP/Composer-only after checkout. Use npm when changing generated admin assets or preparing a release artifact:

```bash
npm ci
npm run build
```

`npm run build` currently regenerates the admin icon SVGs in `public/assets/icons/admin` from `@rsuite/icon-font`. Use `npm run check` in CI or before committing to verify those generated assets are current.

### `config.php`

Hugin requires `config/config.php` at runtime. Copy `config/config.example.php` to `config/config.php` during setup and configure the boot, web server, and database values for your installation. Runtime settings such as uploads, monitoring, branding, and accessibility are managed in `/admin/settings`.

```php
<?php
return [
    'app' => [
        'name' => 'Hugin | Open Source Digital Signage',
        'base_url' => '', // Example: https://signage.example.org
        'session_name' => 'hugin_session',
        'debug' => false,
        'locale' => 'en',
        'fallback_locale' => 'en',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'info_display',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
];
```

Notes:

- `app.base_url` may be empty when Hugin runs at the domain root. Set it when the app is served from a subdirectory or behind a proxy path.
- `app.locale` and `app.fallback_locale` select language files from `app/lang/` and plugin language files from `plugins/<plugin>/lang/`.
- Upload limits, Monitoring API settings, branding defaults, and accessibility contact/preferences are stored in the `app_settings` table and edited in Hugin's Global settings form.
- Public routes are handled by `public/index.php`; Apache installs can use the bundled `public/.htaccess`.

## 3. Feature List

### Displays

- Public display pages at `/display/<slug>`.
- Landscape and vertical display orientation.
- Per-display default slide duration, transition effect, timezone, icon, active flag, and sort order.
- Manual display reload requests from the admin UI.
- Automatic heartbeat collection from display clients.
- Display state endpoint at `/display/<slug>/state` for client-side refresh detection.
- Display clients wait until the next full minute on first load and may show the Hugin logo during that startup phase.

### Locations And Display Groups

- Organize displays by location.
- Create display groups inside locations.
- Move displays between groups in bulk.
- Maintain group layout metadata: x/y position, width, height, rotation, and sort order.
- Optionally synchronize grouped playlist/config reloads to the next full minute. During these later updates, the current content remains visible and the Hugin startup logo is not shown. Clients use their local epoch clock for this behavior, so grouped displays should run with NTP-synchronized system clocks. This does not add server-side slide orchestration or live slide-change synchronization.

### Playlists

- Playlists are stored internally as channels.
- A playlist can be assigned to one or more displays.
- Each display assignment uses a schedule and priority.
- Playlists can override or inherit transition effects.
- Playlists can override the display slide duration.
- Drag-and-drop playlist ordering per display.

### Schedules

- Built-in system `Fulltime` schedule.
- Custom weekly time-slot schedules.
- Multiple weekday/time rules per schedule.
- Schedule resolution uses the display timezone.
- Higher-priority scheduled playlists can override full-time playlists.

### Slides

Built-in slide types:

- `image`: external image URL or media library asset.
- `video`: external video URL or media library asset.
- `website`: external URL rendered in an iframe.
- `text`: safe Markdown content rendered with Parsedown.

Slide capabilities:

- Reuse one slide in multiple playlists.
- Per-slide duration override.
- Optional slide title position or hidden title.
- Active/inactive state.
- Preview route at `/preview-slide/<id>`.
- Drag-and-drop ordering inside playlists.

Text slide capabilities:

- Markdown with safe mode enabled.
- Background color or media background.
- Text color and translucent text box color.
- Text box layouts: `top-left`, `top-right`, `center`, `bottom-left`, `bottom-right`.
- Animations: `none`, `fade-up`, `fly-left`, `fly-right`, `fly-top`, `fly-bottom`, `soft-bounce`, `gentle-zoom`.
- Blur toggle, box width, animation duration, and animation delay.
- Optional QR code URL with configurable QR position, size, foreground color, and background color.

### Media Library

- Upload and reuse image or video assets.
- Supported images: JPG, PNG, GIF, WEBP.
- Supported videos: MP4, WEBM, OGG.
- Filter media by kind.
- Media usage checks prevent deleting assets that are still referenced.
- Uploaded files are stored below `public/uploads/YYYY/MM/`.

### Admin, Users, And Security

- Password-protected admin backend.
- Roles: `admin` and `editor`.
- Admin-only user management.
- Admin-only display, location, group, plugin, and global settings management.

### Appearance And Settings

- Global branding settings in `/admin/settings`.
- Default background color, text color, heading font, and text font.
- Public `branding` settings are readable by plugins through the plugin API.
- Core and plugin internationalization via PHP language files.

### Plugins

Plugins can add custom slide types, slide settings, global plugin settings, frontend assets, and state data. Bundled plugin slide types include:

- `flip-clock`: responsive split-flap style clock.
- `weather`: current weather using Open-Meteo data.
- `brightsky-dwd-weather`: DWD weather using Bright Sky station data.
- `tl1-menu`: TL1 menu display with prices, allergens, categories, and sustainability data.
- `screen-meta`: Demo / "Hello World" plugin: renders the latest display heartbeat metadata.

## 4. Monitoring API

The Monitoring API exposes JSON status data for external monitoring systems. Enable it in `/admin/settings`, set a Bearer token, and adjust the online/stale thresholds if needed.

All monitoring endpoints require a Bearer token:

```bash
curl -H "Authorization: Bearer replace_with_a_long_random_token" \
  https://signage.example.org/api/monitoring/health
```

### Endpoints

`GET /api/monitoring/health`

Returns a compact health result for active displays:

- `status`: `ok`, `warning`, or `critical`
- `timestamp`
- `totals`: active display counts by `online`, `stale`, `offline`, and `never_seen`
- `checks`
- `thresholds`

`GET /api/monitoring/summary`

Returns aggregate display status:

- total, active, inactive, online, stale, offline, and never-seen display counts
- configured thresholds
- active channel distribution

`GET /api/monitoring/displays`

Returns all display statuses. Add `?active_only=1` to include only active displays.

`GET /api/monitoring/displays/<slug>`

Returns one display status by slug.

### Status Values

- `online`: last heartbeat is within `online_threshold_seconds`.
- `stale`: last heartbeat is older than the online threshold but within `stale_threshold_seconds`.
- `offline`: last heartbeat is older than the stale threshold.
- `never_seen`: active display has no heartbeat yet.
- `inactive`: display is disabled.

### Display Payload

Display responses include:

- display id, name, slug, description, active flag, and status
- last seen timestamp and seconds/minutes since last seen
- resolved active playlist/channel
- client metadata: IP, browser, OS, platform, language, timezone, online state, cookies, user agent
- screen metadata: screen size, available size, viewport size, pixel ratio, color depth, orientation, touch points, CPU concurrency, and device memory

## 5. Short Plugin API Development Documentation

Plugins live in `plugins/<plugin-name>/`. A plugin is trusted local PHP code and should be installed only from sources you control or have reviewed.

### Minimal Structure

```text
plugins/
  your-plugin/
    plugin.json
    Plugin.php
    views/
    assets/
    lang/
      en.php
      de.php
```

### Manifest

`plugin.json` should define:

```json
{
  "name": "your-plugin",
  "display_name": {
    "en": "Your Plugin",
    "de": "Dein Plugin"
  },
  "version": "1.0.0",
  "description": {
    "en": "Adds a custom slide type.",
    "de": "Fuegt einen eigenen Slide-Typ hinzu."
  },
  "slide_type": "your-plugin",
  "api_version": 2,
  "main": "Plugin.php",
  "class": "Plugins\\YourPlugin\\Plugin",
  "icon": "assets/img/slide_your_plugin.svg"
}
```

`display_name` and `description` can be strings or locale maps. Locale maps are resolved from the configured locale, fallback locale, base language, then English.

### Plugin Class

Plugins normally extend `App\Core\AbstractSlidePlugin`:

```php
<?php
namespace Plugins\YourPlugin;

use App\Core\AbstractSlidePlugin;
use App\Core\PluginApi;

class Plugin extends AbstractSlidePlugin
{
    public function getDefaultSettings(): array
    {
        return ['heading' => 'Hello'];
    }

    public function renderAdminSettings(array $slide, array $settings, PluginApi $api): string
    {
        return $this->renderView('views/config.php', [
            'settings' => array_replace($this->getDefaultSettings(), $settings),
            'plugin' => $this,
        ]);
    }

    public function normalizeSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        return [
            'heading' => trim((string)($input['heading'] ?? 'Hello')),
        ];
    }

    public function renderFrontend(array $slide, array $settings, PluginApi $api): string
    {
        return '<div class="your-plugin-slide">' . e($settings['heading'] ?? '') . '</div>';
    }

    public function getFrontendAssets(array $slide, array $settings, PluginApi $api): array
    {
        return [
            'css' => [$api->pluginAssetUrl($this->getName(), 'assets/your-plugin.css')],
            'js' => [$api->pluginAssetUrl($this->getName(), 'assets/your-plugin.js')],
        ];
    }
}
```

Available hooks:

- `getDefaultSettings()`
- `renderAdminSettings(array $slide, array $settings, PluginApi $api)`
- `normalizeSettings(array $input, array $existingSettings, PluginApi $api)`
- `renderFrontend(array $slide, array $settings, PluginApi $api)`
- `getFrontendAssets(array $slide, array $settings, PluginApi $api)`
- `getStateData(array $slide, array $settings, PluginApi $api)`
- `getDefaultGlobalSettings()`
- `renderGlobalSettings(array $settings, PluginApi $api)`
- `normalizeGlobalSettings(array $input, array $existingSettings, PluginApi $api)`

Slide settings are edited in the slide form. Global plugin settings are edited by admins at `/admin/plugins/<plugin>/settings`.

### Access The Media Library From A Plugin

Use the `PluginApi` instance passed into plugin hooks.

List all media or only one kind:

```php
$allAssets = $api->listMediaAssets();
$images = $api->listMediaAssets('image');
$videos = $api->listMediaAssets('video');
```

Load one media asset and convert it to a public URL:

```php
$asset = $api->getMediaAsset((int)($settings['media_asset_id'] ?? 0));
$url = $api->mediaAssetUrl($asset);
```

Store an uploaded file from slide settings:

```php
$file = $api->pluginUploadedFile($this->getName(), 'media_file');
$asset = $api->storeMediaAsset($file, 'Optional media label');
```

The matching admin form field must be nested under `plugin_settings[<plugin-name>]`:

```php
<input
    type="file"
    name="plugin_settings[your-plugin][media_file]"
    accept="image/*,video/*"
>
```

For global plugin settings, use the `plugin_global_settings` root:

```php
$file = $api->pluginUploadedFile($this->getName(), 'media_file', 'plugin_global_settings');
$asset = $api->storeMediaAsset($file, 'Global plugin media');
```

Media uploads are only available in admin contexts where Hugin has a current user and an upload manager.

### Access Hugin Settings From A Plugin

Plugins can read public Hugin settings through `PluginApi`. Currently the public namespace is `branding`.

Read all branding settings:

```php
$branding = $api->getHuginSettings('branding');
```

Read one setting with a fallback:

```php
$background = $api->getHuginSetting('branding', 'default_background_color', '#0f172a');
$textColor = $api->getHuginSetting('branding', 'default_text_color', '#f8fafc');
$headingFont = $api->getHuginSetting('branding', 'default_font_heading', '');
$textFont = $api->getHuginSetting('branding', 'default_font_text', '');
```

Unknown or private namespaces return an empty array or the provided default.

### Display, Playlist, Heartbeat, And State Context

Frontend plugin hooks can inspect the current display, playlist/channel, and latest heartbeat:

```php
$display = $api->getDisplay();
$channel = $api->getChannel();
$heartbeat = $api->getHeartbeat();
$screen = $api->getScreenMetadata();
$system = $api->getSystemMeta();
```

Use `getStateData()` to add plugin-specific state into `/display/<slug>/state`. Hugin includes this state in its display signature so clients can refresh when plugin data changes.

### Plugin Storage And Cache

Plugins should not write generated data into their source folders. Use the storage helpers:

```php
$dataFile = $api->pluginStoragePath($this->getName(), 'private/state.json');
$cacheFile = $api->pluginCachePath($this->getName(), 'feed.json');
```

These resolve below:

- `storage/plugins/<plugin>/`
- `storage/cache/plugins/<plugin>/`

### Assets And Translations

Serve plugin assets through Hugin:

```php
$cssUrl = $api->pluginAssetUrl($this->getName(), 'assets/your-plugin.css');
```

Plugin language files live in `plugins/<plugin>/lang/<locale>.php` and are loaded below the translation namespace `plugins.<plugin>`. Inside `AbstractSlidePlugin`, use:

```php
$label = $this->t('settings.heading', 'Heading');
```

Admin plugin JavaScript can use Hugin's shared modal dialog API for alerts and confirmations:

```js
const accepted = await window.HuginDialog.confirm({
  title: 'Delete item?',
  message: 'This action cannot be undone.',
  icon: 'trash',
  buttons: ['cancel', { preset: 'delete', label: 'Delete item' }],
  acceptButton: 'delete'
});
```

Available dialog icons are `none`, `information`, `question`, `exclamation`, `warning`, `error`, and `trash`. Standard button presets are `ok`, `yes`, `no`, `cancel`, and `delete`; labels should still come from Hugin or plugin translations.

### Lifecycle Notes

- Hugin discovers plugins with `plugin.json` and a loadable PHP class implementing `SlidePluginInterface`.
- Plugin registry data is synchronized automatically at boot and in the plugin admin area.
- Plugins can be enabled or disabled in `/admin/plugins`.
- Disabling a plugin can deactivate slides that use its slide type.
- Plugin slide settings are stored in `slide_plugin_data`.
- Plugin global settings are stored in `plugin_global_settings`.
- Frontend CSS and JavaScript assets returned by `getFrontendAssets()` are included only when slides using that plugin are rendered.
