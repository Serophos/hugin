<?php
$strings = $strings ?? [];
$formId = 'plugin_settings';
?>
<div class="plugin-global-settings-form weather-global-config">
    <h3><?= e($strings['title'] ?? 'Open-Meteo API settings') ?></h3>
    <p class="muted"><?= e($strings['description'] ?? '') ?></p>

    <fieldset class="full-width">
        <legend><?= e($strings['weather_base_url'] ?? 'Weather API endpoint') ?></legend>
        <label class="full-width"><?= e($strings['weather_base_url'] ?? 'Weather API endpoint') ?>
            <input
                type="url"
                name="plugin_global_settings[<?= e($plugin->getName()) ?>][weather_base_url]"
                value="<?= e((string)($settings['weather_base_url'] ?? '')) ?>"
                placeholder="<?= e($strings['weather_base_url_placeholder'] ?? 'https://api.open-meteo.com/v1/forecast') ?>"
                autocomplete="off"
                <?= field_attrs('weather_base_url', $formId) ?>
            >
            <?= field_error_html('weather_base_url', $formId) ?>
        </label>
        <p class="muted small"><?= e($strings['weather_base_url_help'] ?? '') ?></p>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e($strings['geocoding_base_url'] ?? 'Geocoding API endpoint') ?></legend>
        <label class="full-width"><?= e($strings['geocoding_base_url'] ?? 'Geocoding API endpoint') ?>
            <input
                type="url"
                name="plugin_global_settings[<?= e($plugin->getName()) ?>][geocoding_base_url]"
                value="<?= e((string)($settings['geocoding_base_url'] ?? '')) ?>"
                placeholder="<?= e($strings['geocoding_base_url_placeholder'] ?? 'https://geocoding-api.open-meteo.com/v1/search') ?>"
                autocomplete="off"
                <?= field_attrs('geocoding_base_url', $formId) ?>
            >
            <?= field_error_html('geocoding_base_url', $formId) ?>
        </label>
        <p class="muted small"><?= e($strings['geocoding_base_url_help'] ?? '') ?></p>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e($strings['api_key'] ?? 'API key') ?></legend>
        <label class="full-width"><?= e($strings['api_key'] ?? 'API key') ?>
            <input
                type="password"
                name="plugin_global_settings[<?= e($plugin->getName()) ?>][api_key]"
                value="<?= e((string)($settings['api_key'] ?? '')) ?>"
                placeholder="<?= e($strings['api_key_placeholder'] ?? '') ?>"
                autocomplete="off"
                <?= field_attrs('api_key', $formId) ?>
            >
            <?= field_error_html('api_key', $formId) ?>
        </label>
        <p class="muted small"><?= e($strings['api_key_help'] ?? '') ?></p>
    </fieldset>

    <div class="grid-2 compact-grid">
        <label><?= e($strings['cache_ttl'] ?? 'Cache TTL in seconds') ?>
            <input
                type="number"
                name="plugin_global_settings[<?= e($plugin->getName()) ?>][cache_ttl_seconds]"
                value="<?= e((string)($settings['cache_ttl_seconds'] ?? 3600)) ?>"
                min="1"
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
                value="<?= e((string)($settings['http_timeout_seconds'] ?? 12)) ?>"
                min="1"
                step="1"
                <?= field_attrs('http_timeout_seconds', $formId) ?>
            >
            <?= field_error_html('http_timeout_seconds', $formId) ?>
            <span class="muted small"><?= e($strings['timeout_help'] ?? '') ?></span>
        </label>
    </div>

    <label class="full-width"><?= e($strings['user_agent'] ?? 'User-Agent header') ?>
        <input
            type="text"
            name="plugin_global_settings[<?= e($plugin->getName()) ?>][user_agent]"
            value="<?= e((string)($settings['user_agent'] ?? 'Hugin Weather Plugin/1.0')) ?>"
            autocomplete="off"
            <?= field_attrs('user_agent', $formId) ?>
        >
        <?= field_error_html('user_agent', $formId) ?>
        <span class="muted small"><?= e($strings['user_agent_help'] ?? '') ?></span>
    </label>
</div>
