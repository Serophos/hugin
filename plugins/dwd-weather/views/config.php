<?php
$strings = $strings ?? [];
$formId = 'slide';
$fieldPrefix = 'plugin_settings.' . $plugin->getName() . '.';
$queryValue = trim((string)($settings['location_query'] ?? ''));
if ($queryValue === '') {
    $queryValue = (string)($settings['location_name'] ?? '');
}
?>
<div class="plugin-settings-card dwd-weather-plugin-config">
    <h3><?= e($strings['title'] ?? 'DWD Weather plugin') ?></h3>
    <p class="muted"><?= e($strings['description'] ?? '') ?></p>
    <div class="alert info">
        <?= e($strings['free_notice'] ?? '') ?>
    </div>

    <label class="full-width"><?= e($strings['location_search'] ?? 'Location search') ?>
        <input
            type="text"
            name="plugin_settings[<?= e($plugin->getName()) ?>][location_query]"
            value="<?= e($queryValue) ?>"
            placeholder="<?= e($strings['location_placeholder'] ?? '') ?>"
            data-dwd-weather-role="location-query"
            autocomplete="off"
            <?= field_attrs($fieldPrefix . 'location_query', $formId) ?>
        >
        <?= field_error_html($fieldPrefix . 'location_query', $formId) ?>
    </label>
    <div class="dwd-weather-search-results" data-dwd-weather-role="search-results"></div>
    <p class="muted small"><?= e($strings['search_help'] ?? '') ?></p>
    <div class="grid-2 compact-grid">
        <label><?= e($strings['selected_location'] ?? 'Selected location') ?>
            <input type="text" value="<?= e($settings['location_name']) ?>" data-dwd-weather-role="selected-location-label" readonly>
        </label>
        <label><?= e($strings['timezone'] ?? 'Timezone') ?>
            <input type="text" name="plugin_settings[<?= e($plugin->getName()) ?>][timezone_name]" value="<?= e($settings['timezone_name']) ?>" readonly data-dwd-weather-role="timezone-name">
        </label>
    </div>

    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][location_name]" value="<?= e($settings['location_name']) ?>" data-dwd-weather-role="location-name">
    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][latitude]" value="<?= e((string)$settings['latitude']) ?>" data-dwd-weather-role="latitude">
    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][longitude]" value="<?= e((string)$settings['longitude']) ?>" data-dwd-weather-role="longitude">
    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][country_code]" value="<?= e($settings['country_code']) ?>" data-dwd-weather-role="country-code">

    <div class="grid-3 compact-grid">
        <label><?= e($strings['temperature_unit'] ?? 'Temperature unit') ?>
            <select name="plugin_settings[<?= e($plugin->getName()) ?>][temperature_unit]"<?= field_attrs($fieldPrefix . 'temperature_unit', $formId) ?>>
                <option value="celsius" <?= selected($settings['temperature_unit'], 'celsius') ?>>°C</option>
                <option value="fahrenheit" <?= selected($settings['temperature_unit'], 'fahrenheit') ?>>°F</option>
            </select>
            <?= field_error_html($fieldPrefix . 'temperature_unit', $formId) ?>
        </label>
        <label><?= e($strings['wind_speed_unit'] ?? 'Wind speed unit') ?>
            <select name="plugin_settings[<?= e($plugin->getName()) ?>][wind_speed_unit]"<?= field_attrs($fieldPrefix . 'wind_speed_unit', $formId) ?>>
                <option value="kmh" <?= selected($settings['wind_speed_unit'], 'kmh') ?>>km/h</option>
                <option value="ms" <?= selected($settings['wind_speed_unit'], 'ms') ?>>m/s</option>
                <option value="mph" <?= selected($settings['wind_speed_unit'], 'mph') ?>>mph</option>
                <option value="kn" <?= selected($settings['wind_speed_unit'], 'kn') ?>>kn</option>
            </select>
            <?= field_error_html($fieldPrefix . 'wind_speed_unit', $formId) ?>
        </label>
        <label><?= e($strings['precipitation_unit'] ?? 'Precipitation unit') ?>
            <select name="plugin_settings[<?= e($plugin->getName()) ?>][precipitation_unit]"<?= field_attrs($fieldPrefix . 'precipitation_unit', $formId) ?>>
                <option value="mm" <?= selected($settings['precipitation_unit'], 'mm') ?>>mm</option>
                <option value="inch" <?= selected($settings['precipitation_unit'], 'inch') ?>>inch</option>
            </select>
            <?= field_error_html($fieldPrefix . 'precipitation_unit', $formId) ?>
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
    const root = scope.querySelector('.dwd-weather-plugin-config');
    if (!root) return;
    if (root.dataset.dwdWeatherSearchInitialized === '1') return;
    root.dataset.dwdWeatherSearchInitialized = '1';

    const queryInput = root.querySelector('[data-dwd-weather-role="location-query"]');
    const resultsBox = root.querySelector('[data-dwd-weather-role="search-results"]');
    const selectedLabel = root.querySelector('[data-dwd-weather-role="selected-location-label"]');
    const locationNameInput = root.querySelector('[data-dwd-weather-role="location-name"]');
    const latitudeInput = root.querySelector('[data-dwd-weather-role="latitude"]');
    const longitudeInput = root.querySelector('[data-dwd-weather-role="longitude"]');
    const countryCodeInput = root.querySelector('[data-dwd-weather-role="country-code"]');
    const timezoneInput = root.querySelector('[data-dwd-weather-role="timezone-name"]');
    const strings = {
        noResults: <?= json_encode($strings['search_no_results'] ?? 'No locations found.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        failed: <?= json_encode($strings['search_failed'] ?? 'Location lookup failed.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
    const dwdStations = [
        // Major POI Stations
        {name: 'Hamburg', lat: 53.5511, lon: 9.9937},
        {name: 'Berlin', lat: 52.5200, lon: 13.4050},
        {name: 'München', lat: 48.1351, lon: 11.5820},
        {name: 'Köln', lat: 50.9375, lon: 6.9603},
        {name: 'Frankfurt', lat: 50.1109, lon: 8.6821},
        {name: 'Stuttgart', lat: 48.7758, lon: 9.1829},
        {name: 'Düsseldorf', lat: 51.2277, lon: 6.7735},
        {name: 'Dortmund', lat: 51.5136, lon: 7.4653},
        {name: 'Essen', lat: 51.4556, lon: 7.0116},
        {name: 'Bremen', lat: 53.0793, lon: 8.8017},
        {name: 'Dresden', lat: 51.0504, lon: 13.7373},
        {name: 'Hannover', lat: 52.3759, lon: 9.7320},
        {name: 'Nürnberg', lat: 49.4521, lon: 11.0767},
        {name: 'Duisburg', lat: 51.4344, lon: 6.7623},
        {name: 'Bochum', lat: 51.4818, lon: 7.2197},
        {name: 'Wuppertal', lat: 51.2562, lon: 7.1508},
        {name: 'Bielefeld', lat: 52.0302, lon: 8.5325},
        {name: 'Bonn', lat: 50.7374, lon: 7.0982},
        {name: 'Münster', lat: 51.9624, lon: 7.6257},
        {name: 'Karlsruhe', lat: 49.0069, lon: 8.4037},
        // Regional & Airport Stations
        {name: 'Kiel', lat: 54.3233, lon: 10.1393},
        {name: 'Sylt', lat: 54.9150, lon: 8.3050},
        {name: 'Helgoland', lat: 54.1850, lon: 7.8883},
        {name: 'Frankfurt Airport', lat: 50.0365, lon: 8.5425},
        {name: 'Zugspitze', lat: 47.4208, lon: 10.9860},
        {name: 'Berlin Tempelhof', lat: 52.4751, lon: 13.4019},
        {name: 'Potsdam', lat: 52.3886, lon: 13.0645},
        {name: 'Leipzig', lat: 51.3397, lon: 12.3731},
        {name: 'Chemnitz', lat: 50.8242, lon: 12.9244},
        {name: 'Dresden Airport', lat: 51.1315, lon: 13.6865},
        {name: 'Hanover Airport', lat: 52.4614, lon: 9.6852},
        {name: 'Hamburg Airport', lat: 53.6304, lon: 10.0095},
        {name: 'Erfurt', lat: 50.9789, lon: 11.0170},
        {name: 'Würzburg', lat: 49.7912, lon: 9.9533},
        {name: 'Bamberg', lat: 49.8913, lon: 10.8855},
        {name: 'Nuremberg Airport', lat: 49.4992, lon: 11.0748},
        {name: 'Bremen Airport', lat: 53.0476, lon: 8.7867},
        {name: 'Braunschweig', lat: 52.2688, lon: 10.5282},
        {name: 'Aachen', lat: 50.7887, lon: 6.0842},
        {name: 'Augsburg', lat: 48.3705, lon: 10.8910},
        {name: 'Bayreuth', lat: 49.9458, lon: 11.5781},
        {name: 'Flensburg', lat: 54.7705, lon: 9.4267},
        {name: 'Kaiserslautern', lat: 49.4447, lon: 7.7666},
        {name: 'Ludwigshafen', lat: 49.4836, lon: 8.4422},
        {name: 'Mannheim', lat: 49.4891, lon: 8.4673},
        {name: 'Mönchengladbach', lat: 51.1649, lon: 6.3933},
        {name: 'Oberstaufen', lat: 47.6372, lon: 10.3167},
        {name: 'Passau', lat: 48.5677, lon: 13.4532},
        {name: 'Quedlinburg', lat: 51.7837, lon: 11.1434},
        {name: 'Zwiefalten', lat: 48.2017, lon: 9.2689},
    ];

    let abortController = null;
    let debounceTimer = null;

    function clearResults() {
        resultsBox.innerHTML = '';
        resultsBox.style.display = 'none';
    }

    function applyResult(item) {
        locationNameInput.value = item.name;
        selectedLabel.value = item.name;
        latitudeInput.value = item.lat;
        longitudeInput.value = item.lon;
        countryCodeInput.value = 'DE';
        timezoneInput.value = 'Europe/Berlin';
        queryInput.value = item.name;
        clearResults();
    }

    function renderResults(items) {
        if (!Array.isArray(items) || items.length === 0) {
            resultsBox.innerHTML = '<div class="dwd-weather-result-empty">' + strings.noResults + '</div>';
            resultsBox.style.display = 'block';
            return;
        }
        resultsBox.innerHTML = '';
        items.slice(0, 8).forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'dwd-weather-result-item';
            button.textContent = item.name;
            button.addEventListener('click', () => applyResult(item));
            resultsBox.appendChild(button);
        });
        resultsBox.style.display = 'block';
    }

    function normalizeString(value) {
        return value
            .toLowerCase()
            .replace(/ä/g, 'ae')
            .replace(/ö/g, 'oe')
            .replace(/ü/g, 'ue')
            .replace(/ß/g, 'ss')
            .replace(/[^a-z0-9 ]/g, '');
    }

    function searchLocations(query) {
        if (abortController) abortController.abort();
        abortController = new AbortController();
        const queryNormalized = normalizeString(query);
        const results = dwdStations.filter(station =>
            normalizeString(station.name).includes(queryNormalized)
        );
        renderResults(results);
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