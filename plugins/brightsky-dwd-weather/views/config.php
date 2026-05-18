<?php
$strings = $strings ?? [];
$formId = 'slide';
$fieldPrefix = 'plugin_settings.' . $plugin->getName() . '.';
$queryValue = trim((string)($settings['station_query'] ?? ''));
if ($queryValue === '') {
    $queryValue = (string)($settings['station_name'] ?? '');
}
$selectedLabel = trim((string)($settings['station_name'] ?? ''));
if ($selectedLabel !== '' && ($settings['dwd_station_id'] ?? '') !== '') {
    $selectedLabel .= ' · ' . (string)$settings['dwd_station_id'];
}
$displayNameValue = trim((string)($settings['display_name'] ?? ''));
if ($displayNameValue === '') {
    $displayNameValue = (string)($settings['station_name'] ?? '');
}
?>
<div class="plugin-settings-card brightsky-dwd-weather-config">
    <style>
        .brightsky-dwd-weather-config { position: relative; }
        .brightsky-dwd-weather-results {
            display: none;
            margin-top: -4px;
            margin-bottom: 12px;
            border: 1px solid #d3dae6;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.10);
            overflow: hidden;
            max-height: 280px;
            overflow-y: auto;
        }
        .brightsky-dwd-weather-result {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 6px 16px;
            width: 100%;
            border: 0;
            background: transparent;
            padding: 12px 14px;
            text-align: left;
            cursor: pointer;
        }
        .brightsky-dwd-weather-result:hover { background: #f4f7fb; }
        .brightsky-dwd-weather-result strong { color: #162033; }
        .brightsky-dwd-weather-result span { color: #536071; font-size: 0.9em; }
        .brightsky-dwd-weather-empty { padding: 12px 14px; color: #536071; }
        .brightsky-dwd-weather-readonly {
            color: #536071;
            background: #f4f7fb;
            border-color: #d9e1ec;
            cursor: not-allowed;
        }
        .brightsky-dwd-weather-readonly:focus {
            outline: 2px solid #cbd7e6;
            outline-offset: 1px;
        }
    </style>

    <h3><?= e($strings['title'] ?? 'BrightSky DWD Weather') ?></h3>
    <p class="muted"><?= e($strings['description'] ?? '') ?></p>

    <label class="full-width"><?= e($strings['station_search'] ?? 'DWD station search') ?>
        <input
            type="text"
            name="plugin_settings[<?= e($plugin->getName()) ?>][station_query]"
            value="<?= e($queryValue) ?>"
            placeholder="<?= e($strings['station_placeholder'] ?? '') ?>"
            data-brightsky-role="station-query"
            autocomplete="off"
            <?= field_attrs($fieldPrefix . 'station_query', $formId) ?>
        >
        <?= field_error_html($fieldPrefix . 'station_query', $formId) ?>
    </label>
    <div class="brightsky-dwd-weather-results" data-brightsky-role="station-results"></div>
    <p class="muted small"><?= e($strings['search_help'] ?? '') ?></p>

    <label class="full-width"><?= e($strings['display_name'] ?? 'Name shown on slide') ?>
        <input
            type="text"
            name="plugin_settings[<?= e($plugin->getName()) ?>][display_name]"
            value="<?= e($displayNameValue) ?>"
            data-brightsky-role="display-name"
            autocomplete="off"
            <?= field_attrs($fieldPrefix . 'display_name', $formId) ?>
        >
        <?= field_error_html($fieldPrefix . 'display_name', $formId) ?>
    </label>

    <div class="grid-2 compact-grid">
        <label><?= e($strings['selected_station'] ?? 'Selected station') ?>
            <input type="text" value="<?= e($selectedLabel) ?>" data-brightsky-role="selected-station-label" class="brightsky-dwd-weather-readonly" readonly>
        </label>
        <label><?= e($strings['dwd_station_id'] ?? 'DWD station ID') ?>
            <input type="text" value="<?= e((string)($settings['dwd_station_id'] ?? '')) ?>" data-brightsky-role="selected-dwd-id" class="brightsky-dwd-weather-readonly" readonly>
        </label>
    </div>

    <div class="grid-2 compact-grid">
        <label><?= e($strings['wmo_station_id'] ?? 'WMO ID') ?>
            <input type="text" value="<?= e((string)($settings['wmo_station_id'] ?? '')) ?>" data-brightsky-role="selected-wmo-id" class="brightsky-dwd-weather-readonly" readonly>
        </label>
        <label><?= e($strings['coordinates'] ?? 'Coordinates') ?>
            <input type="text" value="<?= e(trim((string)($settings['latitude'] ?? '') . ', ' . (string)($settings['longitude'] ?? ''), ', ')) ?>" data-brightsky-role="selected-coordinates" class="brightsky-dwd-weather-readonly" readonly>
        </label>
    </div>

    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][station_name]" value="<?= e((string)($settings['station_name'] ?? '')) ?>" data-brightsky-role="station-name">
    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][dwd_station_id]" value="<?= e((string)($settings['dwd_station_id'] ?? '')) ?>" data-brightsky-role="dwd-station-id">
    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][wmo_station_id]" value="<?= e((string)($settings['wmo_station_id'] ?? '')) ?>" data-brightsky-role="wmo-station-id">
    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][latitude]" value="<?= e((string)($settings['latitude'] ?? '')) ?>" data-brightsky-role="latitude">
    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][longitude]" value="<?= e((string)($settings['longitude'] ?? '')) ?>" data-brightsky-role="longitude">
    <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][height_m]" value="<?= e((string)($settings['height_m'] ?? '')) ?>" data-brightsky-role="height-m">

    <div class="grid-2 compact-grid">
        <label><?= e($strings['unit_system'] ?? 'Units') ?>
            <select name="plugin_settings[<?= e($plugin->getName()) ?>][unit_system]"<?= field_attrs($fieldPrefix . 'unit_system', $formId) ?>>
                <option value="metric" <?= selected($settings['unit_system'] ?? 'metric', 'metric') ?>><?= e($strings['metric'] ?? 'Metric') ?></option>
                <option value="imperial" <?= selected($settings['unit_system'] ?? 'metric', 'imperial') ?>><?= e($strings['imperial'] ?? 'Imperial') ?></option>
            </select>
            <?= field_error_html($fieldPrefix . 'unit_system', $formId) ?>
        </label>
        <label class="checkbox-row">
            <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][show_datetime]" value="1" <?= checked($settings['show_datetime'] ?? true) ?>> <?= e($strings['show_datetime'] ?? 'Show date and time') ?>
        </label>
    </div>

    <label class="checkbox-row">
        <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][enable_weather_animations]" value="1" <?= checked(($settings['enable_weather_animations'] ?? false) || ($settings['enable_rain_effect'] ?? false)) ?>> <?= e($strings['weather_animations'] ?? 'Show weather animations') ?>
    </label>
    <p class="muted small"><?= e($strings['weather_animations_help'] ?? 'Shows subtle animations that match the current weather, such as raindrops or lightning. Designed to preserve readability.') ?></p>

    <p class="muted small"><?= e($strings['footer_note'] ?? '') ?></p>
</div>

<script>
(function () {
    const script = document.currentScript;
    const scope = script ? (script.closest('[data-plugin-slide-type]') || document) : document;
    const root = scope.querySelector('.brightsky-dwd-weather-config');
    if (!root || root.dataset.brightskyStationSearchInitialized === '1') return;
    root.dataset.brightskyStationSearchInitialized = '1';

    const stationDataUrl = <?= json_encode($stationDataUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const strings = {
        noResults: <?= json_encode($strings['no_results'] ?? 'No matching DWD station found.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        loadingFailed: <?= json_encode($strings['loading_failed'] ?? 'Station list could not be loaded.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
    const queryInput = root.querySelector('[data-brightsky-role="station-query"]');
    const resultsBox = root.querySelector('[data-brightsky-role="station-results"]');
    const selectedLabel = root.querySelector('[data-brightsky-role="selected-station-label"]');
    const displayNameInput = root.querySelector('[data-brightsky-role="display-name"]');
    const selectedDwdId = root.querySelector('[data-brightsky-role="selected-dwd-id"]');
    const selectedWmoId = root.querySelector('[data-brightsky-role="selected-wmo-id"]');
    const selectedCoordinates = root.querySelector('[data-brightsky-role="selected-coordinates"]');
    const stationNameInput = root.querySelector('[data-brightsky-role="station-name"]');
    const dwdStationIdInput = root.querySelector('[data-brightsky-role="dwd-station-id"]');
    const wmoStationIdInput = root.querySelector('[data-brightsky-role="wmo-station-id"]');
    const latitudeInput = root.querySelector('[data-brightsky-role="latitude"]');
    const longitudeInput = root.querySelector('[data-brightsky-role="longitude"]');
    const heightInput = root.querySelector('[data-brightsky-role="height-m"]');

    let stations = [];
    let loaded = false;

    function normalize(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[ä]/g, 'ae')
            .replace(/[ö]/g, 'oe')
            .replace(/[ü]/g, 'ue')
            .replace(/[ß]/g, 'ss');
    }

    function clearResults() {
        resultsBox.innerHTML = '';
        resultsBox.style.display = 'none';
    }

    function empty(message) {
        resultsBox.innerHTML = '';
        const item = document.createElement('div');
        item.className = 'brightsky-dwd-weather-empty';
        item.textContent = message;
        resultsBox.appendChild(item);
        resultsBox.style.display = 'block';
    }

    function applyStation(station) {
        const label = station.name + ' · ' + station.dwd_station_id;
        queryInput.value = station.name;
        if (displayNameInput) displayNameInput.value = station.name || '';
        selectedLabel.value = label;
        selectedDwdId.value = station.dwd_station_id || '';
        selectedWmoId.value = station.wmo_station_id || '';
        selectedCoordinates.value = [station.latitude, station.longitude].filter((value) => value !== null && value !== undefined && value !== '').join(', ');
        stationNameInput.value = station.name || '';
        dwdStationIdInput.value = station.dwd_station_id || '';
        wmoStationIdInput.value = station.wmo_station_id || '';
        latitudeInput.value = station.latitude ?? '';
        longitudeInput.value = station.longitude ?? '';
        heightInput.value = station.height_m ?? '';
        clearResults();
    }

    function render(items) {
        if (!items.length) {
            empty(strings.noResults);
            return;
        }
        resultsBox.innerHTML = '';
        items.slice(0, 30).forEach((station) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'brightsky-dwd-weather-result';
            const title = document.createElement('strong');
            title.textContent = station.name || station.dwd_station_id;
            const id = document.createElement('span');
            id.textContent = 'DWD ' + (station.dwd_station_id || '-') + (station.wmo_station_id ? ' · WMO ' + station.wmo_station_id : '');
            const meta = document.createElement('span');
            meta.textContent = [station.device_type, station.latitude + ', ' + station.longitude].filter(Boolean).join(' · ');
            button.appendChild(title);
            button.appendChild(id);
            button.appendChild(meta);
            button.addEventListener('click', () => applyStation(station));
            resultsBox.appendChild(button);
        });
        resultsBox.style.display = 'block';
    }

    function filterStations(query) {
        const terms = normalize(query).split(/\s+/).filter(Boolean);
        if (!terms.length) return [];
        return stations.filter((station) => {
            const haystack = normalize([
                station.name,
                station.dwd_station_id,
                station.wmo_station_id,
                station.wigos_identifier,
                station.device_type,
                station.network
            ].filter(Boolean).join(' '));
            return terms.every((term) => haystack.includes(term));
        });
    }

    async function ensureLoaded() {
        if (loaded) return true;
        try {
            const response = await fetch(stationDataUrl, {credentials: 'same-origin'});
            if (!response.ok) throw new Error('Station list failed');
            const payload = await response.json();
            stations = Array.isArray(payload.stations) ? payload.stations : [];
            loaded = true;
            return true;
        } catch (error) {
            empty(strings.loadingFailed);
            return false;
        }
    }

    queryInput.addEventListener('input', async () => {
        const query = queryInput.value.trim();
        stationNameInput.value = '';
        dwdStationIdInput.value = '';
        wmoStationIdInput.value = '';
        latitudeInput.value = '';
        longitudeInput.value = '';
        heightInput.value = '';
        selectedLabel.value = '';
        selectedDwdId.value = '';
        selectedWmoId.value = '';
        selectedCoordinates.value = '';
        if (displayNameInput) displayNameInput.value = '';
        if (query.length < 2) {
            clearResults();
            return;
        }
        if (await ensureLoaded()) {
            render(filterStations(query));
        }
    });

    queryInput.addEventListener('focus', async () => {
        const query = queryInput.value.trim();
        if (query.length >= 2 && await ensureLoaded()) {
            render(filterStations(query));
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) clearResults();
    });
})();
</script>
