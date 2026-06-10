# Hugin TL1 Menu Plugin

The TL1 Menu plugin adds the `TL1 Menu` slide type to Hugin. It downloads a TL1 XML menu feed, maps menu rows and metadata, and renders a menu slide with prices, allergens/additives, categories, locations, and sustainability values.

## How it works

- Global settings are configured in Hugin’s plugin global settings page.
- Parser mapping is configured in the TL1 Menu setup panel and saved in the plugin global settings database record.
- At runtime, the plugin loads global settings and parser configuration from the database.

## Global plugin settings

The plugin global settings page controls operational defaults:

- `menu_url`: TL1 XML feed URL
- `cache_ttl`: XML cache lifetime in seconds (minimum 60)
- `debug_date`: optional date override for preview/rendering
- default slide language and default mensa
- default excluded food types
- default display toggles for CO2, water, animal welfare, rainforest, and header visibility
- global background color, optional background image, and environment display style
- environment metric icons

These defaults are used for new TL1 Menu slides and may be overridden per slide.

## Setup panel

The TL1 Menu setup panel analyzes the configured XML feed and stores parser configuration in the TL1 plugin global settings.

The setup form lets you define:

- field mapping for TL1 values such as date, mensa/location, food type, title, description, allergens, and environment data
- price groups
- mensa/location entries and location IDs
- food type mappings and categories
- token catalog entries for allergens/additives
- category metadata and icons

The generated parser setup is stored under `parser_config`; operational defaults stay in the same plugin global settings record.

## Runtime behavior

- The plugin downloads and caches the XML feed under `storage/cache/plugins/tl1-menu/speiseplan.xml`.
- It parses TL1 rows using the configured field mapping.
- It selects the slide’s mensa by mapping TL1 location IDs.
- It applies excluded food types and category assignments.
- It renders the menu in card or list layout with prices, labels, and environment indicators.

## Current config model

The TL1 plugin global settings database record contains a `parser_config` entry with parser configuration, including:

- `schema_version`
- `field_definitions`
- `field_mapping`
- `price_groups`
- `mensen`
- `standort_namen`
- `food_types`
- `categories`
- `token_catalog`
- `setup`

Global runtime defaults are managed separately through the admin plugin settings.

## Keep in mind

- Do not rely on plugin-local PHP config files; the plugin now uses database-backed setup data for XML parser mapping.
- Use the setup panel to regenerate or adjust mapping configuration when the feed changes.
- Keep feed URL, cache TTL, and default slide values in the global settings page.
