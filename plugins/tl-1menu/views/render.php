<?php
$items = is_array($items ?? null) ? $items : [];
usort($items, static function ($a, $b): int {
    $aSpalte = is_object($a) && isset($a->spalte) && (int)$a->spalte > 0 ? (int)$a->spalte : PHP_INT_MAX;
    $bSpalte = is_object($b) && isset($b->spalte) && (int)$b->spalte > 0 ? (int)$b->spalte : PHP_INT_MAX;
    $aTitle = is_object($a) && isset($a->titleDe) ? (string)$a->titleDe : '';
    $bTitle = is_object($b) && isset($b->titleDe) ? (string)$b->titleDe : '';

    return [$aSpalte, $aTitle] <=> [$bSpalte, $bTitle];
});

$itemCount = count($items);
$gridClass = 'count-' . max(0, min(8, $itemCount));
$showCo2 = !empty($settings['display_co2']);
$showWater = !empty($settings['display_water']);
$showAnimalWelfare = !empty($settings['display_animal_welfare']);
$showRainforest = !empty($settings['display_rainforest']);
$showAnyEco = $showCo2 || $showWater || $showAnimalWelfare || $showRainforest;
$priceGroups = $service->getPriceGroups();
$displayPriceGroups = is_array($settings['display_price_groups'] ?? null) ? $settings['display_price_groups'] : [];
$visiblePriceGroups = [];
foreach ($priceGroups as $priceGroup) {
    $priceKey = (string)($priceGroup['key'] ?? '');
    if ($priceKey !== '' && ($displayPriceGroups === [] || !empty($displayPriceGroups[$priceKey]))) {
        $visiblePriceGroups[] = $priceGroup;
    }
}
$visiblePriceCount = count($visiblePriceGroups);
$showAnyPrice = $visiblePriceCount > 0;
$showHeader = !empty($settings['show_header']);
$backgroundColor = normalize_hex_color((string)($backgroundColor ?? ($globalSettings['background_color'] ?? '#f1f5f9')), '#f1f5f9');
$isLightBackground = color_luminance($backgroundColor) > 0.42;
$headerTextColor = readable_text_color($backgroundColor);
$headerMutedColor = $isLightBackground ? '#334155' : '#e2e8f0';
$headerSurfaceColor = $isLightBackground ? 'rgba(255, 255, 255, 0.78)' : 'rgba(15, 23, 42, 0.72)';
$headerBorderColor = $isLightBackground ? 'rgba(15, 23, 42, 0.10)' : 'rgba(255, 255, 255, 0.18)';
$styleParts = [
    '--tl1menu-bg-color:' . $backgroundColor,
    '--tl1menu-header-fg:' . $headerTextColor,
    '--tl1menu-header-muted:' . $headerMutedColor,
    '--tl1menu-header-surface:' . $headerSurfaceColor,
    '--tl1menu-header-border:' . $headerBorderColor,
];
if (!empty($backgroundImageUrl)) {
    $safeBackgroundImageUrl = str_replace(["\\", "\"", "\n", "\r"], ['%5C', '%22', '', ''], (string)$backgroundImageUrl);
    $styleParts[] = '--tl1menu-bg-image:url("' . $safeBackgroundImageUrl . '")';
}
?>
<div class="tl1menu tl1menu--<?= e($gridClass) ?><?= !empty($backgroundImageUrl) ? ' tl1menu--with-image' : '' ?>" style="<?= e(implode(';', $styleParts)) ?>">
    <?php if ($showHeader): ?>
        <header class="tl1menu__header">
            <div>
                <div class="tl1menu__eyebrow"><?= e(__('plugins.tl-1menu.frontend.eyebrow')) ?></div>
                <h2 class="tl1menu__title"><?= e($mensaLabel) ?></h2>
            </div>
            <div class="tl1menu__date"><?= e($formattedDate) ?></div>
        </header>
    <?php endif; ?>

    <?php if ($errorMessage !== null): ?>
        <div class="tl1menu__empty">
            <div class="tl1menu__empty-icon">⚠</div>
            <p><?= e($errorMessage) ?></p>
        </div>
    <?php elseif ($itemCount === 0): ?>
        <div class="tl1menu__empty">
            <div class="tl1menu__empty-icon">🍽</div>
            <p><?= e(__('plugins.tl-1menu.frontend.no_data_message', ['mensa' => $mensaLabel, 'date' => $formattedDate])) ?></p>
        </div>
    <?php else: ?>
        <div class="tl1menu__grid">
            <?php foreach ($items as $item): ?>
                <?php
                $classification = $item->classification;
                $title = $item->getLocalizedTitle($language);
                $description = $item->getLocalizedDescription($language);
                $hasZeroPrice = $item->hasZeroPrice();
                $allergens = implode(', ', array_values($item->allergens));
                $additives = implode(', ', array_values($item->additives));
                $displayCategories = [];
                foreach ($item->categories as $categoryKey) {
                    $categoryData = $service->getCategoryDisplayData($categoryKey, $language);
                    if ($categoryData === null) {
                        continue;
                    }
                    $displayCategories[$categoryKey] = $categoryData;
                }
                ?>
                <article class="tl1menu__card tl1menu__card--<?= e($classification) ?>">
                    <div class="tl1menu__card-top">
                        <?php if ($displayCategories !== []): ?>
                            <div class="tl1menu__category-list">
                                <?php foreach ($displayCategories as $categoryKey => $categoryData): ?>
                                    <?php $categoryIcon = $plugin->categoryIconDisplayData((string)($categoryData['icon'] ?? ''), $api); ?>
                                    <div class="tl1menu__category-badge tl1menu__category-badge--<?= e($categoryKey) ?>">
                                        <span class="tl1menu__category-icon" aria-hidden="true">
                                            <?php if ($categoryIcon['type'] === 'image'): ?>
                                                <img src="<?= e($categoryIcon['value']) ?>" alt="">
                                            <?php else: ?>
                                                <?= e($categoryIcon['value']) ?>
                                            <?php endif; ?>
                                        </span>
                                        <span><?= e($categoryData['label']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <h3 class="tl1menu__meal-title"><?= e($title) ?></h3>
                        <?php if ($description !== ''): ?>
                            <p class="tl1menu__description"><?= e($description) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ($showAnyPrice): ?>
                        <div class="tl1menu__prices tl1menu__prices--count-<?= e((string)$visiblePriceCount) ?>">
                            <?php foreach ($visiblePriceGroups as $priceGroup): ?>
                                <?php $priceKey = (string)$priceGroup['key']; ?>
                                <div class="tl1menu__price"><span><?= e($service->getPriceGroupLabel($priceKey, $language)) ?></span><strong><?= e($service->formatPrice($item->getPrice($priceKey), $language)) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($hasZeroPrice && $showAnyPrice): ?>
                        <div class="tl1menu__price-note"><?= e(__('plugins.tl-1menu.frontend.price_at_counter')) ?></div>
                    <?php endif; ?>

                    <div class="tl1menu__info-grid">
                        <div class="tl1menu__meta-box">
                            <div class="tl1menu__meta-title"><?= e(__('plugins.tl-1menu.frontend.allergens')) ?></div>
                            <div class="tl1menu__meta-value"><?= e($allergens !== '' ? $allergens : __('plugins.tl-1menu.frontend.none')) ?></div>
                        </div>
                        <div class="tl1menu__meta-box">
                            <div class="tl1menu__meta-title"><?= e(__('plugins.tl-1menu.frontend.additives')) ?></div>
                            <div class="tl1menu__meta-value"><?= e($additives !== '' ? $additives : __('plugins.tl-1menu.frontend.none')) ?></div>
                        </div>
                    </div>

                    <?php if ($showAnyEco): ?>
                        <div class="tl1menu__eco">
                            <?php if ($showCo2): ?>
                                <?php
                                $environmentVariant = 'card';
                                $metricKey = 'co2';
                                $metricLabel = __('plugins.tl-1menu.frontend.co2');
                                $metricValue = $service->formatEnvironmentalValue(isset($item->environment['co2_value']) && is_numeric($item->environment['co2_value']) ? (float)$item->environment['co2_value'] : null, 'co2', $language) ?? '-';
                                $metricRating = (string)($item->environment['co2_rating'] ?? '');
                                require __DIR__ . '/partials/environment_metric.php';
                                ?>
                            <?php endif; ?>
                            <?php if ($showWater): ?>
                                <?php
                                $environmentVariant = 'card';
                                $metricKey = 'water';
                                $metricLabel = __('plugins.tl-1menu.frontend.water');
                                $metricValue = $service->formatEnvironmentalValue(isset($item->environment['water_value']) && is_numeric($item->environment['water_value']) ? (float)$item->environment['water_value'] : null, 'water', $language) ?? '-';
                                $metricRating = (string)($item->environment['water_rating'] ?? '');
                                require __DIR__ . '/partials/environment_metric.php';
                                ?>
                            <?php endif; ?>
                            <?php if ($showAnimalWelfare): ?>
                                <?php
                                $environmentVariant = 'card';
                                $metricKey = 'animal_welfare';
                                $metricLabel = __('plugins.tl-1menu.frontend.animal_welfare');
                                $metricValue = '-';
                                $metricRating = (string)($item->environment['animal_welfare'] ?? '');
                                require __DIR__ . '/partials/environment_metric.php';
                                ?>
                            <?php endif; ?>
                            <?php if ($showRainforest): ?>
                                <?php
                                $environmentVariant = 'card';
                                $metricKey = 'rainforest';
                                $metricLabel = __('plugins.tl-1menu.frontend.rainforest');
                                $metricValue = '-';
                                $metricRating = (string)($item->environment['rainforest'] ?? '');
                                require __DIR__ . '/partials/environment_metric.php';
                                ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
