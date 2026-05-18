<?php
$weather = $weather ?? [];
$settings = $settings ?? [];
$strings = $strings ?? [];
$visual = $visual ?? ['theme' => 'neutral', 'icon' => 'cloud'];
$unitSystem = (string)($unitSystem ?? 'metric');
$stationName = trim((string)($settings['display_name'] ?? ''));
if ($stationName === '') {
    $stationName = (string)(($station['name'] ?? null) ?: ($settings['station_name'] ?? ''));
}
$conditionLabel = $plugin->conditionLabel($visual, $strings);
$temperature = $weather['temperature'] ?? null;
$windSpeed = $plugin->firstWeatherValue($weather, ['wind_speed_10', 'wind_speed_30', 'wind_speed_60', 'wind_speed']);
$windDirection = $plugin->firstWeatherValue($weather, ['wind_direction_10', 'wind_direction_30', 'wind_direction_60', 'wind_direction']);
$gustSpeed = $plugin->firstWeatherValue($weather, ['wind_gust_speed_10', 'wind_gust_speed_30', 'wind_gust_speed_60', 'wind_gust_speed']);
$pressure = $weather['pressure_msl'] ?? null;
$humidity = $weather['relative_humidity'] ?? null;
$precipitation = $plugin->firstWeatherValue($weather, ['precipitation_60', 'precipitation_30', 'precipitation_10', 'precipitation']);
$visibility = $weather['visibility'] ?? null;
$timestamp = $plugin->formatTimestamp((string)($weather['timestamp'] ?? ''));
$direction = $plugin->compassDirection($windDirection);
$footerStationName = trim((string)(($station['name'] ?? null) ?: ($settings['station_name'] ?? '')));
$footerStationId = trim((string)($settings['dwd_station_id'] ?? ($station['dwd_station_id'] ?? '')));
$footerStation = $footerStationName;
if ($footerStationId !== '') {
    $footerStation = $footerStationName !== '' ? $footerStationName . ' (' . $footerStationId . ')' : $footerStationId;
}
?>
<div class="brightsky-weather-slide theme-<?= e($visual['theme'] ?? 'neutral') ?>" data-brightsky-weather-clock="<?= !empty($settings['show_datetime']) ? '1' : '0' ?>" data-brightsky-rain-effect="<?= !empty($rainEffectEnabled) ? '1' : '0' ?>" data-brightsky-lightning-effect="<?= !empty($lightningEffectEnabled) ? '1' : '0' ?>">
    <div class="brightsky-weather-bg brightsky-weather-bg-1"></div>
    <div class="brightsky-weather-bg brightsky-weather-bg-2"></div>
    <?php if (!empty($lightningEffectEnabled)): ?>
        <canvas class="brightsky-weather-lightning-canvas" data-brightsky-lightning-canvas aria-hidden="true"></canvas>
    <?php endif; ?>
    <?php if (!empty($rainEffectEnabled)): ?>
        <canvas class="brightsky-weather-rain-canvas" data-brightsky-rain-canvas aria-hidden="true"></canvas>
    <?php endif; ?>
    <div class="brightsky-weather-content">
        <header class="brightsky-weather-header">
            <div>
                <div class="brightsky-weather-location"><?= e($stationName !== '' ? $stationName : ($strings['unknown_station'] ?? 'Unknown station')) ?></div>
                <div class="brightsky-weather-condition"><?= e($conditionLabel) ?></div>
            </div>
            <?php if (!empty($settings['show_datetime'])): ?>
                <div class="brightsky-weather-clock">
                    <div class="brightsky-weather-date" data-brightsky-weather-date></div>
                    <div class="brightsky-weather-time" data-brightsky-weather-time></div>
                </div>
            <?php endif; ?>
        </header>

        <?php if ($error !== ''): ?>
            <main class="brightsky-weather-error">
                <div><?= e($strings['no_data'] ?? 'No current weather data available.') ?></div>
                <small><?= e($error) ?></small>
            </main>
        <?php else: ?>
            <main class="brightsky-weather-main">
                <div class="brightsky-weather-temp-wrap">
                    <div class="brightsky-weather-temp"><?= e($plugin->displayValue('temperature', $temperature, $unitSystem)) ?></div>
                    <?php if ($timestamp !== ''): ?>
                        <div class="brightsky-weather-updated"><?= e($strings['updated'] ?? 'Updated') ?> <?= e($timestamp) ?></div>
                    <?php endif; ?>
                </div>
                <div class="brightsky-weather-icon-wrap">
                    <img src="<?= e($iconUrl) ?>" alt="<?= e($conditionLabel) ?>" class="brightsky-weather-icon">
                </div>
            </main>

            <section class="brightsky-weather-details">
                <div class="brightsky-weather-detail">
                    <span><?= e($strings['wind'] ?? 'Wind') ?></span>
                    <strong><?= e($plugin->displayValue('wind', $windSpeed, $unitSystem)) ?><?= e($direction !== '' ? ' ' . $direction : '') ?></strong>
                </div>
                <div class="brightsky-weather-detail">
                    <span><?= e($strings['gusts'] ?? 'Gusts') ?></span>
                    <strong><?= e($plugin->displayValue('wind', $gustSpeed, $unitSystem)) ?></strong>
                </div>
                <div class="brightsky-weather-detail">
                    <span><?= e($strings['pressure'] ?? 'Pressure') ?></span>
                    <strong><?= e($plugin->displayValue('pressure', $pressure, $unitSystem)) ?></strong>
                </div>
                <div class="brightsky-weather-detail">
                    <span><?= e($strings['humidity'] ?? 'Humidity') ?></span>
                    <strong><?= e($humidity !== null ? $plugin->formatNumber((float)$humidity, 0) . ' %' : '-') ?></strong>
                </div>
                <div class="brightsky-weather-detail">
                    <span><?= e($strings['precipitation'] ?? 'Precipitation') ?></span>
                    <strong><?= e($plugin->displayValue('precipitation', $precipitation, $unitSystem)) ?></strong>
                </div>
                <div class="brightsky-weather-detail">
                    <span><?= e($strings['visibility'] ?? 'Visibility') ?></span>
                    <strong><?= e($plugin->displayValue('visibility', $visibility, $unitSystem)) ?></strong>
                </div>
            </section>
        <?php endif; ?>

        <footer class="brightsky-weather-footer">
            <span><?= e($strings['station'] ?? 'Station') ?>: <?= e($footerStation) ?></span>
            <span><?= e($strings['attribution'] ?? 'Data: Deutscher Wetterdienst (DWD), via Bright Sky') ?></span>
        </footer>
    </div>
</div>
