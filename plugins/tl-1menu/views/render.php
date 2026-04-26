<?php
$itemCount = count($items);
$gridClass = 'count-' . max(0, min(6, $itemCount));
$showCo2 = !empty($settings['display_co2']);
$showWater = !empty($settings['display_water']);
$showAnimalWelfare = !empty($settings['display_animal_welfare']);
$showRainforest = !empty($settings['display_rainforest']);
$showAnyEco = $showCo2 || $showWater || $showAnimalWelfare || $showRainforest;
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
$imageOverlay = $isLightBackground
    ? 'linear-gradient(180deg, rgba(255,255,255,0.80) 0%, rgba(241,245,249,0.92) 100%)'
    : 'linear-gradient(180deg, rgba(15,23,42,0.66) 0%, rgba(15,23,42,0.78) 100%)';
$styleParts = [
    '--tl1menu-bg-color:' . $backgroundColor,
    '--tl1menu-header-fg:' . $headerTextColor,
    '--tl1menu-header-muted:' . $headerMutedColor,
    '--tl1menu-header-surface:' . $headerSurfaceColor,
    '--tl1menu-header-border:' . $headerBorderColor,
    '--tl1menu-image-overlay:' . $imageOverlay,
];
if (!empty($backgroundImageUrl)) {
    $safeBackgroundImageUrl = str_replace(["\\", "\"", "\n", "\r"], ['%5C', '%22', '', ''], (string)$backgroundImageUrl);
    $styleParts[] = '--tl1menu-bg-image:url("' . $safeBackgroundImageUrl . '")';
}
$categoryIcons = [
    'vegan' => '🌿',
    'vegetarian' => '🥕',
    'fish' => '🐟',
    'meat' => '🍖',
    'streetfood' => '🍔',
    'sh_teller' => '🍽',
    'kuechenklassiker' => '👨‍🍳',
    'your_favorite' => '⭐',
    'pork_higher_welfare' => '🐖',
    'pork' => '🐖',
    'fish_higher_welfare' => '🐟',
    'poultry' => '🐔',
    'beef_higher_welfare' => '🐄',
    'beef' => '🐄',
    'mensa_vital' => '💚',
    'international' => '🌍',
    'bio' => '🌱',
];
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
                    if (!trans_has('plugins.tl-1menu.categories.' . $categoryKey)) {
                        continue;
                    }
                    $displayCategories[$categoryKey] = [
                        'icon' => $categoryIcons[$categoryKey] ?? '🏷',
                        'label' => __('plugins.tl-1menu.categories.' . $categoryKey),
                    ];
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
                            <?php if ($showCo2): ?>
                                <div class="tl1menu__eco-item">
                                    <span class="tl1menu__eco-icon" aria-hidden="true">☁</span>
                                    <span class="tl1menu__eco-label"><?= e(__('plugins.tl-1menu.frontend.co2')) ?></span>
                                    <strong><?= e($service->formatEnvironmentalValue(isset($item->environment['co2_value']) && is_numeric($item->environment['co2_value']) ? (float)$item->environment['co2_value'] : null, 'co2', $language) ?? '–') ?></strong>
                                    <span class="tl1menu__grade tl1menu__grade--<?= e(strtolower((string)($item->environment['co2_rating'] ?? ''))) ?>"><?= e((string)($item->environment['co2_rating'] ?? '–')) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($showWater): ?>
                                <div class="tl1menu__eco-item">
                                    <span class="tl1menu__eco-icon" aria-hidden="true">💧</span>
                                    <span class="tl1menu__eco-label"><?= e(__('plugins.tl-1menu.frontend.water')) ?></span>
                                    <strong><?= e($service->formatEnvironmentalValue(isset($item->environment['water_value']) && is_numeric($item->environment['water_value']) ? (float)$item->environment['water_value'] : null, 'water', $language) ?? '–') ?></strong>
                                    <span class="tl1menu__grade tl1menu__grade--<?= e(strtolower((string)($item->environment['water_rating'] ?? ''))) ?>"><?= e((string)($item->environment['water_rating'] ?? '–')) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($showAnimalWelfare): ?>
                                <div class="tl1menu__eco-item">
                                    <span class="tl1menu__eco-icon" aria-hidden="true">🐾</span>
                                    <span class="tl1menu__eco-label"><?= e(__('plugins.tl-1menu.frontend.animal_welfare')) ?></span>
                                    <strong>–</strong>
                                    <span class="tl1menu__grade tl1menu__grade--<?= e(strtolower((string)($item->environment['animal_welfare'] ?? ''))) ?>"><?= e((string)($item->environment['animal_welfare'] ?? '–')) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($showRainforest): ?>
                                <div class="tl1menu__eco-item">
                                    <span class="tl1menu__eco-icon" aria-hidden="true">🌳</span>
                                    <span class="tl1menu__eco-label"><?= e(__('plugins.tl-1menu.frontend.rainforest')) ?></span>
                                    <strong>–</strong>
                                    <span class="tl1menu__grade tl1menu__grade--<?= e(strtolower((string)($item->environment['rainforest'] ?? ''))) ?>"><?= e((string)($item->environment['rainforest'] ?? '–')) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
