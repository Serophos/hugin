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
$layout = in_array((string)($settings['layout'] ?? 'card'), ['card', 'list'], true) ? (string)($settings['layout'] ?? 'card') : 'card';
$isListLayout = $layout === 'list';
$showCo2 = !empty($settings['display_co2']);
$showWater = !empty($settings['display_water']);
$showAnimalWelfare = !empty($settings['display_animal_welfare']);
$showRainforest = !empty($settings['display_rainforest']);
$showAnyEco = $showCo2 || $showWater || $showAnimalWelfare || $showRainforest;
$environmentIndicators = is_array($environmentIndicators ?? null) ? $environmentIndicators : [];
$ecoIndicatorVisibility = [
    'co2' => $showCo2,
    'water' => $showWater,
    'animal_welfare' => $showAnimalWelfare,
    'rainforest' => $showRainforest,
];
$showStudentPrice = !empty($settings['display_student_price']);
$showEmployeePrice = !empty($settings['display_employee_price']);
$showGuestPrice = !empty($settings['display_guest_price']);
$visiblePriceCount = ($showStudentPrice ? 1 : 0) + ($showEmployeePrice ? 1 : 0) + ($showGuestPrice ? 1 : 0);
$showAnyPrice = $visiblePriceCount > 0;
$showHeader = !empty($settings['show_header']);
$backgroundColor = normalize_hex_color((string)($globalSettings['background_color'] ?? '#f1f5f9'), '#f1f5f9');
$isLightBackground = color_luminance($backgroundColor) > 0.42;
$headerTextColor = readable_text_color($backgroundColor);
$headerMutedColor = $isLightBackground ? '#334155' : '#e2e8f0';
$headerSurfaceColor = $isLightBackground ? 'rgba(255, 255, 255, 0.78)' : 'rgba(15, 23, 42, 0.72)';
$headerBorderColor = $isLightBackground ? 'rgba(15, 23, 42, 0.10)' : 'rgba(255, 255, 255, 0.18)';
$textColor = $headerTextColor;
$mutedTextColor = $isLightBackground ? '#334155' : '#e2e8f0';
$subtleTextColor = $isLightBackground ? '#64748b' : '#cbd5e1';
$ruleColor = $isLightBackground ? 'rgba(15, 23, 42, 0.24)' : 'rgba(248, 250, 252, 0.34)';
$softRuleColor = $isLightBackground ? 'rgba(15, 23, 42, 0.14)' : 'rgba(248, 250, 252, 0.22)';
$listDensity = 'short';
$listColumnCount = 1;
$showListImage = false;
$listColumns = [];

if ($isListLayout && $itemCount > 0) {
    if ($itemCount <= 4) {
        $listDensity = 'short';
        $listColumnCount = 1;
        $showListImage = !empty($backgroundImageUrl);
    } elseif ($itemCount <= 9) {
        $listDensity = 'medium';
        $listColumnCount = 2;
        $showListImage = !empty($backgroundImageUrl);
    } elseif ($itemCount <= 14) {
        $listDensity = 'dense';
        $listColumnCount = 3;
    } else {
        $listDensity = 'extra';
        $listColumnCount = 3;
    }

    $listCategoryOrder = [
        'vegan' => 10,
        'vegetarian' => 20,
        'fish' => 30,
        'meat' => 40,
    ];
    $listGroups = [];
    foreach ($items as $item) {
        $categoryKey = is_object($item) && isset($item->classification) ? trim((string)$item->classification) : '';
        if ($categoryKey === '' && is_object($item) && isset($item->categories) && is_array($item->categories) && $item->categories !== []) {
            $categoryKey = trim((string)$item->categories[0]);
        }
        if ($categoryKey === '') {
            $categoryKey = 'meat';
        }
        if (!isset($listGroups[$categoryKey])) {
            $categoryData = $service->getCategoryDisplayData($categoryKey, $language);
            $listGroups[$categoryKey] = [
                'key' => $categoryKey,
                'label' => $categoryData['label'] ?? $service->getCategoryLabel($categoryKey, $language),
                'icon' => $categoryData['icon'] ?? '',
                'order' => $listCategoryOrder[$categoryKey] ?? 100,
                'items' => [],
            ];
        }
        $listGroups[$categoryKey]['items'][] = $item;
    }
    uasort($listGroups, static function (array $a, array $b): int {
        return [(int)$a['order'], (string)$a['label']] <=> [(int)$b['order'], (string)$b['label']];
    });

    $listColumns = array_fill(0, $listColumnCount, []);
    $targetItemsPerColumn = max(1, (int)ceil($itemCount / $listColumnCount));
    $currentColumn = 0;
    $currentColumnItems = 0;
    foreach ($listGroups as $group) {
        $groupItemCount = count($group['items']);
        if (
            $currentColumn < $listColumnCount - 1
            && $currentColumnItems > 0
            && $groupItemCount <= $targetItemsPerColumn
            && $currentColumnItems + $groupItemCount > $targetItemsPerColumn
        ) {
            $currentColumn++;
            $currentColumnItems = 0;
        }
        foreach ($group['items'] as $item) {
            if ($currentColumn < $listColumnCount - 1 && $currentColumnItems >= $targetItemsPerColumn) {
                $currentColumn++;
                $currentColumnItems = 0;
            }
            $lastGroupIndex = count($listColumns[$currentColumn]) - 1;
            if ($lastGroupIndex < 0 || $listColumns[$currentColumn][$lastGroupIndex]['key'] !== $group['key']) {
                $listColumns[$currentColumn][] = [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'icon' => $group['icon'],
                    'items' => [],
                ];
                $lastGroupIndex = count($listColumns[$currentColumn]) - 1;
            }
            $listColumns[$currentColumn][$lastGroupIndex]['items'][] = $item;
            $currentColumnItems++;
        }
    }
}

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
if ($isListLayout) {
    $styleParts[] = '--tl1menu-list-fg:' . $textColor;
    $styleParts[] = '--tl1menu-list-muted:' . $mutedTextColor;
    $styleParts[] = '--tl1menu-list-subtle:' . $subtleTextColor;
    $styleParts[] = '--tl1menu-list-rule:' . $ruleColor;
    $styleParts[] = '--tl1menu-list-rule-soft:' . $softRuleColor;
    $styleParts[] = '--tl1menu-list-data-columns:' . $listColumnCount;
}

$rootClasses = ['tl1menu'];
if ($isListLayout) {
    $rootClasses[] = 'tl1menu--layout-list';
    $rootClasses[] = 'tl1menu--list-' . $listDensity;
} else {
    $rootClasses[] = 'tl1menu--layout-card';
    $rootClasses[] = 'tl1menu--' . $gridClass;
    if (!empty($backgroundImageUrl)) {
        $rootClasses[] = 'tl1menu--with-image';
    }
}
?>
<div class="<?= e(implode(' ', $rootClasses)) ?>" style="<?= e(implode(';', $styleParts)) ?>">
    <?php if ($showHeader): ?>
        <?php if ($isListLayout): ?>
            <header class="tl1menu-list__header">
                <div>
                    <div class="tl1menu-list__eyebrow"><?= e(__('plugins.tl-1menu.frontend.eyebrow')) ?></div>
                    <h2 class="tl1menu-list__title"><?= e($mensaLabel) ?></h2>
                </div>
                <div class="tl1menu-list__date"><?= e($formattedDate) ?></div>
            </header>
        <?php else: ?>
            <header class="tl1menu__header">
                <div>
                    <div class="tl1menu__eyebrow"><?= e(__('plugins.tl-1menu.frontend.eyebrow')) ?></div>
                    <h2 class="tl1menu__title"><?= e($mensaLabel) ?></h2>
                </div>
                <div class="tl1menu__date"><?= e($formattedDate) ?></div>
            </header>
        <?php endif; ?>
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
    <?php elseif ($isListLayout): ?>
        <div class="tl1menu-list-board tl1menu-list-board--<?= e($listDensity) ?><?= $showListImage ? ' tl1menu-list-board--has-image' : '' ?>">
            <?php if ($showListImage): ?>
                <div class="tl1menu-list__image" aria-hidden="true"></div>
            <?php endif; ?>

            <?php foreach ($listColumns as $column): ?>
                <div class="tl1menu-list__column">
                    <?php foreach ($column as $group): ?>
                        <section class="tl1menu-list__group tl1menu-list__group--<?= e((string)$group['key']) ?>">
                            <h3 class="tl1menu-list__group-title">
                                <?php if ((string)$group['icon'] !== ''): ?>
                                    <span class="tl1menu-list__group-icon" aria-hidden="true"><?= e((string)$group['icon']) ?></span>
                                <?php endif; ?>
                                <span><?= e((string)$group['label']) ?></span>
                            </h3>

                            <div class="tl1menu-list__meals">
                                <?php foreach ($group['items'] as $item): ?>
                                    <?php
                                    $classification = $item->classification;
                                    $title = $item->getLocalizedTitle($language);
                                    $description = $item->getLocalizedDescription($language);
                                    $hasZeroPrice = $item->hasZeroPrice();
                                    $allergens = implode(', ', array_values($item->allergens));
                                    $additives = implode(', ', array_values($item->additives));
                                    $displayCategoryLabels = [];
                                    foreach ($item->categories as $categoryKey) {
                                        $categoryData = $service->getCategoryDisplayData($categoryKey, $language);
                                        if ($categoryData === null) {
                                            continue;
                                        }
                                        $displayCategoryLabels[$categoryKey] = $categoryData['label'];
                                    }
                                    if ($displayCategoryLabels === []) {
                                        $displayCategoryLabels[$classification] = $service->getCategoryLabel($classification, $language);
                                    }
                                    $categories = implode(', ', array_values($displayCategoryLabels));
                                    ?>
                                    <article class="tl1menu-list__meal tl1menu-list__meal--<?= e($classification) ?>">
                                        <div class="tl1menu-list__meal-main">
                                            <h4 class="tl1menu-list__meal-title"><?= e($title) ?></h4>
                                            <?php if ($description !== ''): ?>
                                                <p class="tl1menu-list__description"><?= e($description) ?></p>
                                            <?php endif; ?>

                                            <div class="tl1menu-list__details">
                                                <div class="tl1menu-list__detail">
                                                    <span><?= e(__('plugins.tl-1menu.frontend.allergens')) ?></span>
                                                    <strong><?= e($allergens !== '' ? $allergens : __('plugins.tl-1menu.frontend.none')) ?></strong>
                                                </div>
                                                <div class="tl1menu-list__detail">
                                                    <span><?= e(__('plugins.tl-1menu.frontend.additives')) ?></span>
                                                    <strong><?= e($additives !== '' ? $additives : __('plugins.tl-1menu.frontend.none')) ?></strong>
                                                </div>
                                            </div>

                                            <?php if ($showAnyEco): ?>
                                                <div class="tl1menu-list__eco">
                                                    <?php foreach ($ecoIndicatorVisibility as $indicatorKey => $isVisible): ?>
                                                        <?php
                                                        if (!$isVisible) {
                                                            continue;
                                                        }
                                                        $ecoDisplay = $service->getEnvironmentalRatingDisplayData(
                                                            $item,
                                                            $indicatorKey,
                                                            is_array($environmentIndicators[$indicatorKey] ?? null) ? $environmentIndicators[$indicatorKey] : []
                                                        );
                                                        if ($ecoDisplay === null) {
                                                            continue;
                                                        }
                                                        ?>
                                                        <div class="tl1menu-list__eco-item">
                                                            <span class="tl1menu-list__eco-label"><?= e($ecoDisplay['label']) ?></span>
                                                            <span class="<?= e('tl1menu-list__eco-rating tl1menu-list__eco-rating--' . $ecoDisplay['css_class'] . (!$ecoDisplay['is_available'] ? ' tl1menu-list__eco-rating--none' : '')) ?>" aria-label="<?= e($ecoDisplay['aria_label']) ?>">
                                                                <?php for ($symbolIndex = 1; $symbolIndex <= 3; $symbolIndex++): ?>
                                                                    <?php $symbolState = $symbolIndex <= (int)$ecoDisplay['filled_count'] ? 'filled' : 'empty'; ?>
                                                                    <span class="<?= e('tl1menu-list__eco-symbol tl1menu-list__eco-symbol--' . $symbolState) ?>" aria-hidden="true">
                                                                        <svg viewBox="0 0 24 24" focusable="false"><use href="<?= e($ecoDisplay['icon_url']) ?>"></use></svg>
                                                                    </span>
                                                                <?php endfor; ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($showAnyPrice): ?>
                                            <div class="tl1menu-list__prices tl1menu-list__prices--count-<?= e((string)$visiblePriceCount) ?>">
                                                <?php if ($showStudentPrice): ?>
                                                    <div class="tl1menu-list__price"><span><?= e(__('plugins.tl-1menu.frontend.student')) ?></span><strong><?= e($service->formatPrice($item->getPrice('student'), $language)) ?></strong></div>
                                                <?php endif; ?>
                                                <?php if ($showEmployeePrice): ?>
                                                    <div class="tl1menu-list__price"><span><?= e(__('plugins.tl-1menu.frontend.staff')) ?></span><strong><?= e($service->formatPrice($item->getPrice('staff'), $language)) ?></strong></div>
                                                <?php endif; ?>
                                                <?php if ($showGuestPrice): ?>
                                                    <div class="tl1menu-list__price"><span><?= e(__('plugins.tl-1menu.frontend.guest')) ?></span><strong><?= e($service->formatPrice($item->getPrice('guest'), $language)) ?></strong></div>
                                                <?php endif; ?>
                                                <?php if ($hasZeroPrice): ?>
                                                    <div class="tl1menu-list__price-note"><?= e(__('plugins.tl-1menu.frontend.price_at_counter')) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
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
                                    <div class="tl1menu__category-badge tl1menu__category-badge--<?= e($categoryKey) ?>">
                                        <span class="tl1menu__category-icon" aria-hidden="true"><?= e($categoryData['icon']) ?></span>
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
                            <?php if ($showStudentPrice): ?>
                                <div class="tl1menu__price"><span><?= e(__('plugins.tl-1menu.frontend.student')) ?></span><strong><?= e($service->formatPrice($item->getPrice('student'), $language)) ?></strong></div>
                            <?php endif; ?>
                            <?php if ($showEmployeePrice): ?>
                                <div class="tl1menu__price"><span><?= e(__('plugins.tl-1menu.frontend.staff')) ?></span><strong><?= e($service->formatPrice($item->getPrice('staff'), $language)) ?></strong></div>
                            <?php endif; ?>
                            <?php if ($showGuestPrice): ?>
                                <div class="tl1menu__price"><span><?= e(__('plugins.tl-1menu.frontend.guest')) ?></span><strong><?= e($service->formatPrice($item->getPrice('guest'), $language)) ?></strong></div>
                            <?php endif; ?>
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
                            <?php foreach ($ecoIndicatorVisibility as $indicatorKey => $isVisible): ?>
                                <?php
                                if (!$isVisible) {
                                    continue;
                                }
                                $ecoDisplay = $service->getEnvironmentalRatingDisplayData(
                                    $item,
                                    $indicatorKey,
                                    is_array($environmentIndicators[$indicatorKey] ?? null) ? $environmentIndicators[$indicatorKey] : []
                                );
                                if ($ecoDisplay === null) {
                                    continue;
                                }
                                ?>
                                <div class="tl1menu__eco-item">
                                    <span class="tl1menu__eco-label"><?= e($ecoDisplay['label']) ?></span>
                                    <span class="<?= e('tl1menu__eco-rating tl1menu__eco-rating--' . $ecoDisplay['css_class'] . (!$ecoDisplay['is_available'] ? ' tl1menu__eco-rating--none' : '')) ?>" aria-label="<?= e($ecoDisplay['aria_label']) ?>">
                                        <?php for ($symbolIndex = 1; $symbolIndex <= 3; $symbolIndex++): ?>
                                            <?php $symbolState = $symbolIndex <= (int)$ecoDisplay['filled_count'] ? 'filled' : 'empty'; ?>
                                            <span class="<?= e('tl1menu__eco-symbol tl1menu__eco-symbol--' . $symbolState) ?>" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" focusable="false"><use href="<?= e($ecoDisplay['icon_url']) ?>"></use></svg>
                                            </span>
                                        <?php endfor; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
