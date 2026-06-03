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
$headerSurfaceColor = $isLightBackground ? 'rgba(255, 255, 255, 0.86)' : 'rgba(15, 23, 42, 0.76)';
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
    $styleParts[] = '--tl1menu-list-image:url("' . $safeBackgroundImageUrl . '")';
}

$groups = [];
foreach ($items as $item) {
    if (!is_object($item)) {
        continue;
    }
    $classification = trim((string)($item->classification ?? '')) ?: 'other';
    if (!isset($groups[$classification])) {
        $categoryData = $service->getCategoryDisplayData($classification, $language);
        $groups[$classification] = [
            'key' => $classification,
            'icon' => $categoryData['icon'] ?? '',
            'label' => $categoryData['label'] ?? $service->getCategoryLabel($classification, $language),
            'items' => [],
        ];
    }
    $groups[$classification]['items'][] = $item;
}
?>
<div class="tl1menu tl1menu-list" data-tl1menu-list data-item-count="<?= e((string)$itemCount) ?>" data-has-image="<?= !empty($backgroundImageUrl) ? '1' : '0' ?>" style="<?= e(implode(';', $styleParts)) ?>">
    <?php if ($showHeader): ?>
        <header class="tl1menu-list__header">
            <div>
                <div class="tl1menu-list__eyebrow"><?= e(__('plugins.tl1-menu.frontend.eyebrow')) ?></div>
                <h2 class="tl1menu-list__title"><?= e($mensaLabel) ?></h2>
            </div>
            <div class="tl1menu-list__date"><?= e($formattedDate) ?></div>
        </header>
    <?php endif; ?>

    <?php if ($errorMessage !== null): ?>
        <div class="tl1menu-list__empty">
            <div class="tl1menu-list__empty-icon">!</div>
            <p><?= e($errorMessage) ?></p>
        </div>
    <?php elseif ($itemCount === 0): ?>
        <div class="tl1menu-list__empty">
            <div class="tl1menu-list__empty-icon">&middot;</div>
            <p><?= e(__('plugins.tl1-menu.frontend.no_data_message', ['mensa' => $mensaLabel, 'date' => $formattedDate])) ?></p>
        </div>
    <?php else: ?>
        <div class="tl1menu-list__stage">
            <?php if (!empty($backgroundImageUrl)): ?>
                <aside class="tl1menu-list__image" aria-hidden="true"></aside>
            <?php endif; ?>
            <main class="tl1menu-list__food">
                <div class="tl1menu-list__flow">
                    <?php foreach ($groups as $group): ?>
                        <section class="tl1menu-list__category tl1menu-list__category--<?= e($group['key']) ?>">
                            <?php $groupIcon = $plugin->categoryIconDisplayData((string)($group['icon'] ?? ''), $api); ?>
                            <h3 class="tl1menu-list__category-title">
                                <?php if ($groupIcon['value'] !== ''): ?>
                                    <span class="tl1menu-list__category-icon" aria-hidden="true">
                                        <?php if ($groupIcon['type'] === 'image'): ?>
                                            <img src="<?= e($groupIcon['value']) ?>" alt="">
                                        <?php else: ?>
                                            <?= e($groupIcon['value']) ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <span><?= e((string)$group['label']) ?></span>
                            </h3>
                            <div class="tl1menu-list__items">
                                <?php foreach ($group['items'] as $item): ?>
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
                                    <article class="tl1menu-list__item tl1menu-list__item--<?= e($classification) ?>">
                                        <div class="tl1menu-list__item-main">
                                            <div class="tl1menu-list__meal">
                                                <h4 class="tl1menu-list__meal-title"><?= e($title) ?></h4>
                                                <?php if ($description !== ''): ?>
                                                    <p class="tl1menu-list__description"><?= e($description) ?></p>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($showAnyPrice): ?>
                                                <div class="tl1menu-list__prices tl1menu-list__prices--count-<?= e((string)$visiblePriceCount) ?>">
                                                    <?php foreach ($visiblePriceGroups as $priceGroup): ?>
                                                        <?php $priceKey = (string)$priceGroup['key']; ?>
                                                        <div class="tl1menu-list__price"><span><?= e($service->getPriceGroupLabel($priceKey, $language)) ?></span><strong><?= e($service->formatPrice($item->getPrice($priceKey), $language)) ?></strong></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($displayCategories !== []): ?>
                                            <div class="tl1menu-list__badges">
                                                <?php foreach ($displayCategories as $categoryKey => $categoryData): ?>
                                                    <?php $categoryIcon = $plugin->categoryIconDisplayData((string)($categoryData['icon'] ?? ''), $api); ?>
                                                    <span class="tl1menu-list__badge tl1menu-list__badge--<?= e($categoryKey) ?>">
                                                        <span class="tl1menu-list__badge-icon" aria-hidden="true">
                                                            <?php if ($categoryIcon['type'] === 'image'): ?>
                                                                <img src="<?= e($categoryIcon['value']) ?>" alt="">
                                                            <?php else: ?>
                                                                <?= e($categoryIcon['value']) ?>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span><?= e($categoryData['label']) ?></span>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($hasZeroPrice && $showAnyPrice): ?>
                                            <div class="tl1menu-list__price-note"><?= e(__('plugins.tl1-menu.frontend.price_at_counter')) ?></div>
                                        <?php endif; ?>

                                        <div class="tl1menu-list__meta">
                                            <div><strong><?= e(__('plugins.tl1-menu.frontend.allergens')) ?></strong><span><?= e($allergens !== '' ? $allergens : __('plugins.tl1-menu.frontend.none')) ?></span></div>
                                            <div><strong><?= e(__('plugins.tl1-menu.frontend.additives')) ?></strong><span><?= e($additives !== '' ? $additives : __('plugins.tl1-menu.frontend.none')) ?></span></div>
                                        </div>

                                        <?php if ($showAnyEco): ?>
                                            <div class="tl1menu-list__eco">
                                                <?php if ($showCo2): ?>
                                                    <?php
                                                    $environmentVariant = 'list';
                                                    $metricKey = 'co2';
                                                    $metricLabel = __('plugins.tl1-menu.frontend.co2');
                                                    $metricValue = $service->formatEnvironmentalValue(isset($item->environment['co2_value']) && is_numeric($item->environment['co2_value']) ? (float)$item->environment['co2_value'] : null, 'co2', $language) ?? '-';
                                                    $metricRating = (string)($item->environment['co2_rating'] ?? '');
                                                    require __DIR__ . '/partials/environment_metric.php';
                                                    ?>
                                                <?php endif; ?>
                                                <?php if ($showWater): ?>
                                                    <?php
                                                    $environmentVariant = 'list';
                                                    $metricKey = 'water';
                                                    $metricLabel = __('plugins.tl1-menu.frontend.water');
                                                    $metricValue = $service->formatEnvironmentalValue(isset($item->environment['water_value']) && is_numeric($item->environment['water_value']) ? (float)$item->environment['water_value'] : null, 'water', $language) ?? '-';
                                                    $metricRating = (string)($item->environment['water_rating'] ?? '');
                                                    require __DIR__ . '/partials/environment_metric.php';
                                                    ?>
                                                <?php endif; ?>
                                                <?php if ($showAnimalWelfare): ?>
                                                    <?php
                                                    $environmentVariant = 'list';
                                                    $metricKey = 'animal_welfare';
                                                    $metricLabel = __('plugins.tl1-menu.frontend.animal_welfare');
                                                    $metricValue = '';
                                                    $metricRating = (string)($item->environment['animal_welfare'] ?? '');
                                                    require __DIR__ . '/partials/environment_metric.php';
                                                    ?>
                                                <?php endif; ?>
                                                <?php if ($showRainforest): ?>
                                                    <?php
                                                    $environmentVariant = 'list';
                                                    $metricKey = 'rainforest';
                                                    $metricLabel = __('plugins.tl1-menu.frontend.rainforest');
                                                    $metricValue = '';
                                                    $metricRating = (string)($item->environment['rainforest'] ?? '');
                                                    require __DIR__ . '/partials/environment_metric.php';
                                                    ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    <?php endif; ?>
</div>
