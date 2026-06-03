-- Rename the TL1 Menu plugin identifier from tl1menu to tl1-menu.
-- This migration updates persisted plugin references in the plugin registry and plugin settings tables.

/*UPDATE plugins
SET plugin_name = 'tl1-menu'
WHERE plugin_name = 'tl1menu';*/

UPDATE slide_plugin_data
SET plugin_name = 'tl1-menu'
WHERE plugin_name = 'tl1menu';

UPDATE plugin_global_settings
SET plugin_name = 'tl1-menu'
WHERE plugin_name = 'tl1menu';
