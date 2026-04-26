<?php $strings = $strings ?? []; ?>
<div class="plugin-settings-card weather-global-config">
    <h3><?= e($strings['title'] ?? 'Open-Meteo API settings') ?></h3>
    <p class="muted"><?= e($strings['description'] ?? '') ?></p>

    <fieldset class="full-width">
        <legend><?= e($strings['weather_base_url'] ?? 'Weather API endpoint') ?></legend>
        <label class="full-width"><?= e($strings['weather_base_url'] ?? 'Weather API endpoint') ?>
            <input
                type="url"
                name="plugin_global_settings[<?= e($plugin->getName()) ?>][weather_base_url]"
                value="<?= e((string)($settings['weather_base_url'] ?? '')) ?>"
                placeholder="https://api.open-meteo.com/v1/forecast"
                autocomplete="off"
            >
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
                placeholder="https://geocoding-api.open-meteo.com/v1/search"
                autocomplete="off"
            >
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
                autocomplete="off"
            >
        </label>
        <p class="muted small"><?= e($strings['api_key_help'] ?? '') ?></p>
    </fieldset>
</div>
