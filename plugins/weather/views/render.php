<?php
$weather = $weather ?? [];
$settings = $settings ?? [];
$units = $units ?? [];
$visual = $visual ?? [];
$strings = $strings ?? [];
$locationName = (string)($settings['location_name'] ?? '');
$showDateTime = !empty($settings['show_datetime']);
$conditionLabel = (string)($visual['label'] ?? ($strings['unknown_condition'] ?? 'Unknown'));
$tempValue = $weather['temperature_2m'] ?? null;
$feelsLike = $weather['apparent_temperature'] ?? null;
$windValue = $weather['wind_speed_10m'] ?? null;
$precipValue = $weather['precipitation'] ?? null;
?>
<div class="weather-slide theme-<?= e($visual['theme'] ?? 'neutral') ?>" data-weather-refresh-seconds="3600">
    <div class="weather-background weather-background-1"></div>
    <div class="weather-background weather-background-2"></div>
    <div class="weather-content">
        <div class="weather-header">
            <div>
                <div class="weather-location"><?= e($locationName !== '' ? $locationName : ($strings['unknown_location'] ?? 'Unknown location')) ?></div>
                <div class="weather-condition"><?= e($conditionLabel) ?></div>
            </div>
            <?php if ($showDateTime): ?>
                <div class="weather-datetime" data-weather-clock>
                    <div class="weather-date"></div>
                    <div class="weather-time"></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="weather-main">
            <div class="weather-temp-wrap">
                <div class="weather-temp"><?= e($tempValue !== null ? $plugin->formatNumber((float)$tempValue) : '—') ?><span><?= e($units['temperature'] ?? '°C') ?></span></div>
                <div class="weather-meta-row"><?= e($strings['label_feels_like'] ?? 'Feels like') ?> <?= e($feelsLike !== null ? $plugin->formatNumber((float)$feelsLike) : '—') ?><?= e($units['temperature'] ?? '°C') ?></div>
            </div>
            <div class="weather-icon-wrap">
                <img src="<?= e($iconUrl) ?>" alt="<?= e($conditionLabel) ?>" class="weather-icon">
            </div>
        </div>

        <div class="weather-details">
            <div class="weather-detail-card">
                <div class="weather-detail-label"><?= e($strings['label_wind'] ?? 'Wind') ?></div>
                <div class="weather-detail-value"><?= e($windValue !== null ? $plugin->formatNumber((float)$windValue) : '—') ?> <?= e($units['wind'] ?? '') ?></div>
            </div>
            <div class="weather-detail-card">
                <div class="weather-detail-label"><?= e($strings['label_precipitation'] ?? 'Precipitation') ?></div>
                <div class="weather-detail-value"><?= e($precipValue !== null ? $plugin->formatNumber((float)$precipValue) : '—') ?> <?= e($units['precipitation'] ?? '') ?></div>
            </div>
        </div>
    </div>
</div>
