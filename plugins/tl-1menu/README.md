# Hugin TL-1 Menu Plugin

The TL-1 Menu plugin adds the `tl-1menu` slide type to Hugin. It downloads a TL1 XML menu feed, maps location and food-type metadata, and renders today's menu for a selected mensa or cafeteria with prices, categories, allergens, additives, and sustainability data.

## Configuration Files

The plugin loads `plugins/tl-1menu/config.php`. If that file is missing, it falls back to `plugins/tl-1menu/config.example.php`.

This README documents the runtime settings accepted in `config.php`. Some visual defaults can also be overridden from Hugin's plugin global settings page and stored in the database.

## `config.php` Overview

The config file is organized into these groups:

- Feed and cache settings
- Slide default settings
- Environmental display settings
- Food type and category maps
- Mensa/location maps
- Location display names
- Category display metadata

## Feed And Cache Settings

| Setting | Type | Default in current config | Description |
| --- | --- | --- | --- |
| `menu_url` | URL string | `https://plan.studentenwerk.sh/speiseplan/spei2.xml` | Remote TL1 XML feed URL. The plugin downloads this feed server-side. |
| `cache_ttl` | integer | `1800` | XML cache lifetime in seconds. The repository enforces a minimum effective TTL of `60` seconds. Cached XML is stored at `storage/cache/plugins/tl-1menu/speiseplan.xml`. |
| `debug_date` | string | `12.05.2026` | Optional date override for rendering and state data. If non-empty and parseable by PHP `strtotime()`, the plugin renders that date instead of today. Set to an empty string or null for production. |

## Slide Default Settings

These values initialize new TL-1 Menu slide settings.

| Setting | Type | Default in current config | Description |
| --- | --- | --- | --- |
| `default_language` | string | `de` | Default slide language. Supported values are `de` and `en`. |
| `default_mensa` | string | `mensa1` | Default selected mensa key. Must match a key in the `mensen` map. |
| `default_exclude` | integer array | `[120, 121, 122, 123, 124, 125, 801]` | Food type IDs excluded by default when creating a slide. Only IDs present in `food_types` are kept by slide validation. |
| `default_display_co2` | boolean | `true` | Whether CO2 metrics are shown by default. |
| `default_display_water` | boolean | `false` | Whether water metrics are shown by default. |
| `default_display_animal_welfare` | boolean | `false` | Whether animal welfare ratings are shown by default. |
| `default_display_rainforest` | boolean | `false` | Whether rainforest ratings are shown by default. |
| `default_show_header` | boolean | `true` | Whether the menu header is visible by default. |
| `default_background_color` | hex color string | `#f1f5f9` | Default global background color for TL-1 Menu slides. Used to initialize global plugin settings. |
| `default_environment_display_style` | string | optional, fallback `symbols` | Default global environmental display style. Supported values are `symbols` and `values`. The current `config.php` omits this key, so the code falls back to `symbols`. |

## Environmental Icon Settings

| Setting | Type | Description |
| --- | --- | --- |
| `environment_rating_icons` | array | Maps environmental metric keys such as `co2`, `water`, `animal_welfare`, and `rainforest` to plugin-relative icon paths or absolute URLs. The current renderer uses built-in filled/outline icons from `assets/img/` and does not read this map, so editing this setting alone does not change the rendered icons. |

Example:

```php
'environment_rating_icons' => [
    'co2' => 'assets/img/eco-leaf.svg#icon',
    'water' => 'assets/img/eco-drop.svg#icon',
    'animal_welfare' => 'assets/img/eco-heart.svg#icon',
    'rainforest' => 'assets/img/eco-tree.svg#icon',
],
```

## Food Type And Category Mapping

### `food_types`

`food_types` maps TL1 numeric food type IDs to internal keys, localized labels, and optional category tags.

```php
'food_types' => [
    49 => [
        'key' => 'vegan',
        'categories' => ['vegan'],
        'labels' => [
            'de' => 'Vegan',
            'en' => 'Vegan',
        ],
    ],
],
```

Fields:

| Field | Type | Description |
| --- | --- | --- |
| `key` | string | Stable internal name for this food type. Used as a fallback label and in admin selection lists. |
| `categories` | string array | Optional category tags added to dishes of this type, for example `vegan`, `vegetarian`, `fish`, or `beef`. |
| `labels` | locale map | Display labels for the food type. The service chooses the requested language, current locale, default language, then fallback labels. |

The plugin also supports a legacy `food_type_categories` map if present, but the current config stores categories directly inside each `food_types` entry.

### `pseudo_allergen_category_map`

Maps pseudo allergen/additive tokens from the XML feed to category tags.

```php
'pseudo_allergen_category_map' => [
    'VE' => 'vegetarian',
    'VN' => 'vegan',
    'Fi' => 'fish',
],
```

The parser normalizes token keys to uppercase and merges these categories with categories derived from `food_types`.

### `categories`

`categories` defines how classification/category tags are displayed.

```php
'categories' => [
    'vegan' => [
        'icon' => '...',
        'labels' => [
            'de' => 'Vegan',
            'en' => 'Vegan',
        ],
    ],
],
```

Fields:

| Field | Type | Description |
| --- | --- | --- |
| `icon` | string | Short display marker for the category. It can be text or an icon glyph. |
| `labels` | locale map | Localized category label used on rendered menu items. |

The service also understands a legacy `category_display` map if present.

## Mensa And Location Mapping

### `mensen`

`mensen` maps slide-selectable mensa keys to TL1 location IDs.

```php
'mensen' => [
    'mensa1' => [
        'label' => 'Mensa 1',
        'locations' => [411],
    ],
],
```

Fields:

| Field | Type | Description |
| --- | --- | --- |
| `label` | string | Admin/frontend label for the mensa key. |
| `locations` | integer array | TL1 location IDs included when this mensa is selected. The parser also accepts equivalent keys such as `location_ids`, `standorte`, `ids`, `id`, or `location_id`. |

The current config includes combined entries such as `mensacafete1` as well as individual entries such as `mensa1` and `cafete1`.

### `standort_namen`

`standort_namen` maps raw TL1 location IDs to human-readable location names.

```php
'standort_namen' => [
    411 => 'Mensa 1',
    413 => 'Cafeteria 1',
],
```

The parser uses this map when building each menu item. The service also uses it as a fallback label when a mensa entry maps to exactly one location and has no explicit `label`.

## Global Plugin Settings Derived From Config

The admin global plugin settings page initializes these values from `config.php` and stores overrides in Hugin's `plugin_global_settings` table:

| Global setting | Initialized from | Description |
| --- | --- | --- |
| `background_color` | `default_background_color` | Global background color for TL-1 Menu slides. |
| `background_media_asset_id` | `null` | Optional global image background selected from Hugin's media library. |
| `environment_display_style` | `default_environment_display_style`, fallback `symbols` | Global style for environmental metrics. Supported values are `symbols` and `values`. |

Slide-level settings may use the global background and environment style, override them, or disable custom background images.

## Runtime Behavior

1. The repository downloads `menu_url` and caches the XML in `storage/cache/plugins/tl-1menu/speiseplan.xml`.
2. The parser reads TL1 rows, normalizes dates, locations, food types, prices, allergens, additives, and environmental values.
3. `mensen` selects which TL1 location IDs belong to a slide.
4. `default_exclude` and slide-specific exclusions filter unwanted food type IDs.
5. `food_types` and `pseudo_allergen_category_map` assign categories.
6. `categories` controls category labels and display markers.
7. The slide renders either `card` or `list` mode, depending on slide settings.

## Common Adjustments

- Set `debug_date` to an empty string or null when displays should show the actual current date.
- Change `default_mensa` to the most common location for new slides.
- Add or adjust `mensen` entries when the feed changes location IDs.
- Add new `food_types` entries when TL1 introduces new type IDs.
- Keep `default_exclude` focused on side dishes, desserts, or other items that should not appear in the default signage view.
- Increase `cache_ttl` to reduce feed requests or decrease it when the source feed changes frequently.
