<?php
$weather = $weather ?? [];
$settings = $settings ?? [];
$units = $units ?? [];
$visual = $visual ?? [];
$strings = $strings ?? [];
$locationName = (string)($settings['location_name'] ?? '');
$showDateTime = !empty($settings['show_datetime']);
$conditionLabel = (string)($visual['label'] ?? ($strings['unknown_condition'] ?? 'Unknown'));
$tempValue = $weather['temperature'] ?? null;
$windValue = $weather['wind_speed'] ?? null;
$precipValue = $weather['precipitation'] ?? null;
$weatherIcon = $visual['icon'] ?? '';
?>
<div class="dwd-weather-slide theme-<?= e($visual['theme'] ?? 'neutral') ?>" data-weather-refresh-seconds="3600">
    <div class="dwd-weather-background dwd-weather-background-1"></div>
    <div class="dwd-weather-background dwd-weather-background-2"></div>
    <div class="dwd-weather-content">
        <div class="dwd-weather-header">
            <div>
                <div class="dwd-weather-location"><?= e($locationName !== '' ? $locationName : ($strings['unknown_location'] ?? 'Unknown location')) ?></div>
                <div class="dwd-weather-condition"><?= e($conditionLabel) ?></div>
            </div>
            <?php if ($showDateTime): ?>
                <div class="dwd-weather-datetime" data-weather-clock>
                    <div class="dwd-weather-date"></div>
                    <div class="dwd-weather-time"></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="dwd-weather-main">
            <div class="dwd-weather-temp-wrap">
                <div class="dwd-weather-temp"><?= e($tempValue !== null ? $plugin->formatNumber((float)$tempValue) : '—') ?><span><?= e($units['temperature'] ?? '°C') ?></span></div>
                <div class="dwd-weather-meta-row"><?= e($strings['label_wind'] ?? 'Wind') ?> <?= e($windValue !== null ? $plugin->formatNumber((float)$windValue) : '—') ?> <?= e($units['wind'] ?? '') ?></div>
            </div>
            <div class="dwd-weather-icon-wrap">
                <?php if ($weatherIcon !== ''): ?>
                    <img src="<?= e($weatherIcon) ?>" alt="<?= e($conditionLabel) ?>" class="dwd-weather-icon">
                <?php endif; ?>
            </div>
        </div>

        <div class="dwd-weather-details">
            <div class="dwd-weather-detail-card">
                <div class="dwd-weather-detail-label"><?= e($strings['label_precipitation'] ?? 'Precipitation') ?></div>
                <div class="dwd-weather-detail-value"><?= e($precipValue !== null ? $plugin->formatNumber((float)$precipValue) : '—') ?> <?= e($units['precipitation'] ?? '') ?></div>
            </div>
        </div>
    </div>
</div>
