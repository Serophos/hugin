<?php
$strings = $strings ?? [];
$formId = 'plugin_settings';
?>
<div class="plugin-global-settings-form brightsky-dwd-weather-global-config">
    <h3><?= e($strings['title'] ?? 'Bright Sky API settings') ?></h3>
    <p class="muted"><?= e($strings['description'] ?? '') ?></p>

    <fieldset class="full-width">
        <legend><?= e($strings['endpoint'] ?? 'Bright Sky current weather endpoint') ?></legend>
        <label class="full-width"><span class="visually-hidden"><?= e($strings['endpoint'] ?? 'Bright Sky current weather endpoint') ?></span>
            <input
                type="url"
                name="plugin_global_settings[<?= e($plugin->getName()) ?>][brightsky_current_weather_url]"
                value="<?= e((string)($settings['brightsky_current_weather_url'] ?? '')) ?>"
                placeholder="https://api.brightsky.dev/current_weather"
                autocomplete="off"
                <?= field_attrs('brightsky_current_weather_url', $formId) ?>
            >
            <?= field_error_html('brightsky_current_weather_url', $formId) ?>
        </label>
        <p class="muted small"><?= e($strings['endpoint_help'] ?? '') ?></p>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e($strings['request_settings'] ?? 'Request behavior') ?></legend>
        <div class="grid-2 compact-grid">
            <label><?= e($strings['cache_ttl'] ?? 'Cache TTL in seconds') ?>
                <input
                    type="number"
                    name="plugin_global_settings[<?= e($plugin->getName()) ?>][cache_ttl_seconds]"
                    value="<?= e((string)($settings['cache_ttl_seconds'] ?? '')) ?>"
                    min="60"
                    max="86400"
                    step="1"
                    <?= field_attrs('cache_ttl_seconds', $formId) ?>
                >
                <?= field_error_html('cache_ttl_seconds', $formId) ?>
                <span class="muted small"><?= e($strings['cache_ttl_help'] ?? '') ?></span>
            </label>

            <label><?= e($strings['timeout'] ?? 'HTTP timeout in seconds') ?>
                <input
                    type="number"
                    name="plugin_global_settings[<?= e($plugin->getName()) ?>][http_timeout_seconds]"
                    value="<?= e((string)($settings['http_timeout_seconds'] ?? '')) ?>"
                    min="3"
                    max="60"
                    step="1"
                    <?= field_attrs('http_timeout_seconds', $formId) ?>
                >
                <?= field_error_html('http_timeout_seconds', $formId) ?>
                <span class="muted small"><?= e($strings['timeout_help'] ?? '') ?></span>
            </label>
        </div>

        <label class="full-width"><?= e($strings['timezone'] ?? 'Timezone') ?>
            <select name="plugin_global_settings[<?= e($plugin->getName()) ?>][timezone]"<?= field_attrs('timezone', $formId) ?>>
                <?php foreach (($timezoneOptions ?? []) as $timezone): ?>
                    <option value="<?= e($timezone) ?>" <?= selected((string)($settings['timezone'] ?? 'Europe/Berlin'), $timezone) ?>><?= e($timezone) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error_html('timezone', $formId) ?>
            <span class="muted small"><?= e($strings['timezone_help'] ?? '') ?></span>
        </label>

        <label class="full-width"><?= e($strings['user_agent'] ?? 'User-Agent header') ?>
            <input
                type="text"
                name="plugin_global_settings[<?= e($plugin->getName()) ?>][user_agent]"
                value="<?= e((string)($settings['user_agent'] ?? 'Hugin BrightSky DWD Weather Plugin/1.0')) ?>"
                autocomplete="off"
                <?= field_attrs('user_agent', $formId) ?>
            >
            <?= field_error_html('user_agent', $formId) ?>
            <span class="muted small"><?= e($strings['user_agent_help'] ?? '') ?></span>
        </label>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e($strings['station_settings'] ?? 'Station data') ?></legend>
        <div class="grid-2 compact-grid">
            <label><?= e($strings['station_data_path'] ?? 'Station data path') ?>
                <input
                    type="text"
                    name="plugin_global_settings[<?= e($plugin->getName()) ?>][station_data_path]"
                    value="<?= e((string)($settings['station_data_path'] ?? 'data/stations.json')) ?>"
                    autocomplete="off"
                    <?= field_attrs('station_data_path', $formId) ?>
                >
                <?= field_error_html('station_data_path', $formId) ?>
                <span class="muted small"><?= e($strings['station_data_path_help'] ?? '') ?></span>
            </label>

            <label><?= e($strings['max_dist'] ?? 'Maximum station distance in meters') ?>
                <input
                    type="number"
                    name="plugin_global_settings[<?= e($plugin->getName()) ?>][max_dist_meters]"
                    value="<?= e((string)($settings['max_dist_meters'] ?? 50000)) ?>"
                    min="1"
                    step="1"
                    <?= field_attrs('max_dist_meters', $formId) ?>
                >
                <?= field_error_html('max_dist_meters', $formId) ?>
                <span class="muted small"><?= e($strings['max_dist_help'] ?? '') ?></span>
            </label>
        </div>
    </fieldset>

    <div class="alert info">
        <?= e($strings['notice'] ?? 'Bright Sky is open source. You can run your own Bright Sky API server and point this plugin at it.') ?>
        <a href="<?= e($infrastructureUrl ?? 'https://github.com/jdemaeyer/brightsky-infrastructure/') ?>" target="_blank" rel="noopener noreferrer"><?= e($strings['notice_link'] ?? 'Official Bright Sky infrastructure guide') ?></a>
    </div>
</div>
