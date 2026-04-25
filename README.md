# Hugin

Hugin is a compact PHP/MySQL digital signage application with:

- public display URLs using human-readable slugs
- multiple displays
- reusable channels assignable to one or many displays
- reusable schedules assigned to display/channel pairs
- reusable slides assignable to one or many channels
- built-in slide types for images, videos, and external websites
- configurable effects: `fade`, `slide-left`, `slide-right`, `slide-up`, `slide-down`, `zoom`, `flip`, `blur`, `none`
- media uploads and reusable media library
- drag-and-drop sorting for displays, channels, and slides
- password protected admin area
- CSRF protection on all POST actions except the public display heartbeat endpoint
- role-based users (`admin`, `editor`)
- root URL redirect to `/admin` for logged-in users or `/admin/login` otherwise
- automatic display heartbeats for dashboard online/offline statistics
- plugin system v2 for custom slide types
- system-wide internationalization (i18n) with language files

## Requirements

- PHP 8.1+
- MySQL 8+
- Apache or Nginx

## Quick start

1. Copy `config/config.example.php` to `config/config.php`
2. Adjust database settings, optional `app.base_url`, and `app.locale`
3. Import `database.sql`
4. Point your web root to the `public/` folder
5. Make sure `public/uploads/` is writable by PHP
6. Open `/admin/login`

## Default users

Both seeded users use the password `admin123!`.

- Admin user: `admin`
- Editor user: `editor`


## Internationalization

Hugin now uses language files for all user-facing strings.

- Core translations live in `app/lang/`
- Plugin translations live in `plugins/<plugin>/lang/`
- The active language is set globally in `config/config.php`

Example:

```php
'app' => [
    // ...
    'locale' => 'en',
    'fallback_locale' => 'en',
],
```

This package includes the default language `en`.

## Plugin system v2

Plugins live in the `plugins/` folder. Each plugin is self-contained and can:

- register one custom slide type
- provide its own slide configuration UI in the admin slide form
- provide global plugin settings in the plugin admin area
- store slide-scoped and global plugin settings in the database
- render its own frontend output
- load optional frontend CSS/JS assets
- read display and heartbeat metadata through the plugin API
- read and upload media assets through Hugin's media library
- store cache and private plugin data below `storage/`

### Plugin folder structure

```text
plugins/
  your-plugin/
    plugin.json
    Plugin.php
    views/
    assets/
```

### Required manifest keys

`plugin.json` should define at least:

- `name`
- `display_name`
- `version`
- `description`
- `slide_type`
- `api_version`
- `main`
- `class`

Set `api_version` to `2` for plugins that use the global settings, media-library, or storage helpers.

### Plugin class hooks

Plugins usually extend `App\Core\AbstractSlidePlugin`. The v2 hooks are:

```php
public function getDefaultSettings(): array;
public function renderAdminSettings(array $slide, array $settings, PluginApi $api): string;
public function normalizeSettings(array $input, array $existingSettings, PluginApi $api): array;
public function renderFrontend(array $slide, array $settings, PluginApi $api): string;
public function getFrontendAssets(array $slide, array $settings, PluginApi $api): array;
public function getStateData(array $slide, array $settings, PluginApi $api): array;

public function getDefaultGlobalSettings(): array;
public function renderGlobalSettings(array $settings, PluginApi $api): string;
public function normalizeGlobalSettings(array $input, array $existingSettings, PluginApi $api): array;
```

Slide settings are available to admins and editors through the slide editor. Global plugin settings are available only to admins at `/admin/plugins`.

### Media library from plugins

Use the `PluginApi` object passed to plugin hooks:

```php
$images = $api->listMediaAssets('image');
$asset = $api->getMediaAsset((int)$settings['media_asset_id']);
$url = $api->mediaAssetUrl($asset);
```

For upload fields inside slide settings, use a file input named like:

```php
<input type="file" name="plugin_settings[your-plugin][media_file]" accept="image/*,video/*">
```

Then normalize it with:

```php
$file = $api->pluginUploadedFile($this->getName(), 'media_file');
$asset = $api->storeMediaAsset($file, 'Optional media label');
```

For global settings, use the root `plugin_global_settings`:

```php
$file = $api->pluginUploadedFile($this->getName(), 'media_file', 'plugin_global_settings');
```

### Plugin storage and cache

Plugins should not write into their own source folders. Use:

```php
$cacheFile = $api->pluginCachePath($this->getName(), 'feed.json');
$dataFile = $api->pluginStoragePath($this->getName(), 'private/state.json');
```

These resolve to:

- `storage/cache/plugins/<plugin>/...`
- `storage/plugins/<plugin>/...`

### Included example plugin

This package includes `plugins/screen-meta`, an API v2 example plugin slide type that renders the latest heartbeat metadata collected from the client and exposes a small global settings dialog.

Manage plugins in the admin backend at `/admin/plugins`.

## Important schema change

This version changes the content model and adds plugin support:

- `channels` are reusable content containers
- `schedules` and `schedule_rules` store reusable schedule definitions
- `channel_display_schedule_assignments` links channels, displays, and schedules
- `slides` are reusable content items
- `channel_slide_assignments` links slides to channels
- `plugins` stores plugin registry state
- `plugin_global_settings` stores plugin-wide settings
- `slide_plugin_data` stores plugin settings per slide

If you are upgrading from an earlier package, re-import `database.sql` or migrate your data accordingly.

## Notes

- Website slides are rendered in an iframe. Some websites block embedding using `X-Frame-Options` or CSP.
- External image/video URLs still work alongside uploaded media.
- Drag-and-drop sorting saves automatically after you drop a row.
- The example plugin is trusted local PHP code. Only install plugins you control or review.

## Display online detection

Each display page sends a heartbeat on load and every 5 minutes after that.

- Green status: display seen within the last 30 minutes
- Red status: no heartbeat for more than 30 minutes
- The dashboard also shows the currently active channel and the last seen IP address
