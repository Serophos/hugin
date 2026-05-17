<?php
$metricKey = (string)($metricKey ?? '');
$metricLabel = (string)($metricLabel ?? '');
$metricValue = isset($metricValue) ? (string)$metricValue : '';
$metricRating = strtoupper(trim((string)($metricRating ?? '')));
$environmentVariant = (string)($environmentVariant ?? 'card');
$environmentDisplayStyle = (string)($environmentDisplayStyle ?? 'symbols');
$iconMap = [
    'co2' => 'leaf',
    'water' => 'drop',
    'animal_welfare' => 'heart',
    'rainforest' => 'tree',
];
$valueIconFallbacks = [
    'co2' => '☁',
    'water' => '💧',
    'animal_welfare' => '♡',
    'rainforest' => '♧',
];
$iconName = $iconMap[$metricKey] ?? 'leaf';
$iconSet = is_array($environmentIconAssets[$iconName] ?? null) ? $environmentIconAssets[$iconName] : [];
$filledIcon = (string)($iconSet['filled'] ?? '');
$outlineIcon = (string)($iconSet['outline'] ?? $filledIcon);
$ratingClass = strtolower($metricRating);
$ratingText = $metricRating !== '' ? $metricRating : '-';
?>
<?php if ($environmentDisplayStyle === 'symbols'): ?>
    <?php
    $element = $environmentVariant === 'list' ? 'span' : 'div';
    $className = $environmentVariant === 'list'
        ? 'tl1menu-list__eco-item tl1menu-env tl1menu-env--list tl1menu-env--symbols tl1menu-env--' . $metricKey
        : 'tl1menu__eco-item tl1menu-env tl1menu-env--card tl1menu-env--symbols tl1menu-env--' . $metricKey;
    $filledCount = $plugin->environmentalSymbolFillCount($metricRating);
    $ariaLabel = trim($metricLabel . ' ' . $ratingText);
    ?>
    <<?= $element ?> class="<?= e($className) ?>" aria-label="<?= e($ariaLabel) ?>">
        <span class="tl1menu-env__label"><?= e($metricLabel) ?></span>
        <span class="tl1menu-env__symbols" aria-hidden="true">
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <?php $isFilled = $i <= $filledCount; ?>
                <img class="tl1menu-env__symbol tl1menu-env__symbol--<?= $isFilled ? 'filled' : 'outline' ?>" src="<?= e($isFilled ? $filledIcon : $outlineIcon) ?>" alt="">
            <?php endfor; ?>
        </span>
    </<?= $element ?>>
<?php elseif ($environmentVariant === 'list'): ?>
    <span class="tl1menu-list__eco-item">
        <strong><?= e($metricLabel) ?></strong>
        <?php if ($metricValue !== ''): ?><?= e($metricValue) ?><?php endif; ?>
        <b class="tl1menu-list__grade tl1menu-list__grade--<?= e($ratingClass) ?>"><?= e($ratingText) ?></b>
    </span>
<?php else: ?>
    <div class="tl1menu__eco-item">
        <span class="tl1menu__eco-icon" aria-hidden="true">
            <?php if ($filledIcon !== ''): ?>
                <img class="tl1menu-env__value-icon" src="<?= e($filledIcon) ?>" alt="">
            <?php else: ?>
                <?= e($valueIconFallbacks[$metricKey] ?? '') ?>
            <?php endif; ?>
        </span>
        <span class="tl1menu__eco-label"><?= e($metricLabel) ?></span>
        <strong><?= e($metricValue !== '' ? $metricValue : '-') ?></strong>
        <span class="tl1menu__grade tl1menu__grade--<?= e($ratingClass) ?>"><?= e($ratingText) ?></span>
    </div>
<?php endif; ?>
