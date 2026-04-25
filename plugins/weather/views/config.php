<?php
$strings = $strings ?? [];
$queryValue = trim((string)($settings['location_query'] ?? ''));
if ($queryValue === '') {
    $queryValue = (string)($settings['location_name'] ?? '');
}
?>
<div class="plugin-settings-card weather-plugin-config">
    <h3><?= e($strings['title'] ?? 'Weather plugin') ?></h3>
    <p class="muted"><?= e($strings['description'] ?? '') ?></p>
    <?php if (!$plugin->isCommercialMode()): ?>
        <div class="alert warning">
            <?= e($strings['free_notice'] ?? '') ?>
        </div>
    <?php endif; ?>

    <label class="full-width"><?= e($strings['location_search'] ?? 'Location search') ?>
        <input
            type="text"
            name="plugin_settings[<?= e($plugin->getName()) ?>][location_query]"
            value="<?= e($queryValue) ?>"
            placeholder="<?= e($strings['location_placeholder'] ?? '') ?>"
            data-weather-role="location-query"
            autocomplete="off"
        >
    </label>
    <div class="weather-search-results" data-weather-role="search-results"></div>
    <p class="muted small"><?= e($strings['search_help'] ?? '') ?></p>
    <div class="grid-2 compact-grid">
        <label><?= e($strings['selected_location'] ?? 'Selected location') ?>
            <input type="text" value="<?= e($settings['location_name']) ?>" data-weather-role="selected-location-label" readonly>
        </label>
        <label><?= e($strings['timezone'] ?? 'Timezone') ?>
            <input type="text" name="plugin_settings[<?= e($plugin->getName()) ?>][timezone_name]" value="<?= e($settings['timezone_name']) ?>" readonly data-weather-role="timezone-name">
        </label>
    </div>

    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][location_name]" value="<?= e($settings['location_name']) ?>" data-weather-role="location-name">
    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][latitude]" value="<?= e((string)$settings['latitude']) ?>" data-weather-role="latitude">
    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][longitude]" value="<?= e((string)$settings['longitude']) ?>" data-weather-role="longitude">
    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][country_code]" value="<?= e($settings['country_code']) ?>" data-weather-role="country-code">

    <div class="grid-3 compact-grid">
        <label><?= e($strings['temperature_unit'] ?? 'Temperature unit') ?>
            <select name="plugin_settings[<?= e($plugin->getName()) ?>][temperature_unit]">
                <option value="celsius" <?= selected($settings['temperature_unit'], 'celsius') ?>>°C</option>
                <option value="fahrenheit" <?= selected($settings['temperature_unit'], 'fahrenheit') ?>>°F</option>
            </select>
        </label>
        <label><?= e($strings['wind_speed_unit'] ?? 'Wind speed unit') ?>
            <select name="plugin_settings[<?= e($plugin->getName()) ?>][wind_speed_unit]">
                <option value="kmh" <?= selected($settings['wind_speed_unit'], 'kmh') ?>>km/h</option>
                <option value="ms" <?= selected($settings['wind_speed_unit'], 'ms') ?>>m/s</option>
                <option value="mph" <?= selected($settings['wind_speed_unit'], 'mph') ?>>mph</option>
                <option value="kn" <?= selected($settings['wind_speed_unit'], 'kn') ?>>kn</option>
            </select>
        </label>
        <label><?= e($strings['precipitation_unit'] ?? 'Precipitation unit') ?>
            <select name="plugin_settings[<?= e($plugin->getName()) ?>][precipitation_unit]">
                <option value="mm" <?= selected($settings['precipitation_unit'], 'mm') ?>>mm</option>
                <option value="inch" <?= selected($settings['precipitation_unit'], 'inch') ?>>inch</option>
            </select>
        </label>
    </div>

    <div class="checkbox-grid compact">
        <label class="checkbox-row">
            <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][show_datetime]" value="1" <?= checked($settings['show_datetime']) ?>> <?= e($strings['show_datetime'] ?? 'Show client date and time') ?>
        </label>
    </div>

    <p class="muted small"><?= e($strings['footer_note'] ?? '') ?></p>
</div>

<script>
(function () {
    const script = document.currentScript;
    const scope = script ? (script.closest('[data-plugin-slide-type]') || document) : document;
    const root = scope.querySelector('.weather-plugin-config');
    if (!root) return;
    if (root.dataset.weatherSearchInitialized === '1') return;
    root.dataset.weatherSearchInitialized = '1';

    const queryInput = root.querySelector('[data-weather-role="location-query"]');
    const resultsBox = root.querySelector('[data-weather-role="search-results"]');
    const selectedLabel = root.querySelector('[data-weather-role="selected-location-label"]');
    const locationNameInput = root.querySelector('[data-weather-role="location-name"]');
    const latitudeInput = root.querySelector('[data-weather-role="latitude"]');
    const longitudeInput = root.querySelector('[data-weather-role="longitude"]');
    const countryCodeInput = root.querySelector('[data-weather-role="country-code"]');
    const timezoneInput = root.querySelector('[data-weather-role="timezone-name"]');
    const strings = {
        noResults: <?= json_encode($strings['search_no_results'] ?? 'No locations found.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        failed: <?= json_encode($strings['search_failed'] ?? 'Location lookup failed.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
    const geocodingBaseUrl = <?= json_encode($plugin->getGeocodingBaseUrl(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    let abortController = null;
    let debounceTimer = null;

    function clearResults() {
        resultsBox.innerHTML = '';
        resultsBox.style.display = 'none';
    }

    function applyResult(item) {
        const locationParts = [item.name, item.admin1, item.country].filter(Boolean);
        const label = locationParts.join(', ');
        locationNameInput.value = label;
        selectedLabel.value = label;
        latitudeInput.value = item.latitude ?? '';
        longitudeInput.value = item.longitude ?? '';
        countryCodeInput.value = item.country_code ?? '';
        timezoneInput.value = item.timezone ?? '';
        queryInput.value = label;
        clearResults();
    }

    function renderResults(items) {
        if (!Array.isArray(items) || items.length === 0) {
            resultsBox.innerHTML = '<div class="weather-result-empty">' + strings.noResults + '</div>';
            resultsBox.style.display = 'block';
            return;
        }
        resultsBox.innerHTML = '';
        items.slice(0, 8).forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'weather-result-item';
            const label = [item.name, item.admin1, item.country].filter(Boolean).join(', ');
            button.textContent = label;
            button.addEventListener('click', () => applyResult(item));
            resultsBox.appendChild(button);
        });
        resultsBox.style.display = 'block';
    }

    async function searchLocations(query) {
        if (abortController) abortController.abort();
        abortController = new AbortController();
        const url = new URL(geocodingBaseUrl, window.location.href);
        url.searchParams.set('name', query);
        url.searchParams.set('count', '8');
        url.searchParams.set('language', 'en');
        url.searchParams.set('format', 'json');
        try {
            const response = await fetch(url.toString(), {signal: abortController.signal});
            if (!response.ok) throw new Error('Location lookup failed');
            const payload = await response.json();
            renderResults(payload.results || []);
        } catch (error) {
            if (error.name === 'AbortError') return;
            resultsBox.innerHTML = '<div class="weather-result-empty">' + strings.failed + '</div>';
            resultsBox.style.display = 'block';
        }
    }

    queryInput.addEventListener('input', () => {
        const query = queryInput.value.trim();
        clearTimeout(debounceTimer);
        locationNameInput.value = '';
        latitudeInput.value = '';
        longitudeInput.value = '';
        countryCodeInput.value = '';
        timezoneInput.value = '';
        selectedLabel.value = '';
        if (query.length < 2) {
            clearResults();
            return;
        }
        debounceTimer = setTimeout(() => searchLocations(query), 250);
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) clearResults();
    });
})();
</script>
