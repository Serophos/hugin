<?php
$itemCount = count($items);
$gridClass = 'count-' . max(0, min(6, $itemCount));
$showCo2 = !empty($settings['display_co2']);
$showWater = !empty($settings['display_water']);
$showAnimalWelfare = !empty($settings['display_animal_welfare']);
$showRainforest = !empty($settings['display_rainforest']);
$showAnyEco = $showCo2 || $showWater || $showAnimalWelfare || $showRainforest;
$showHeader = !empty($settings['show_header']);
$backgroundColor = (string)($settings['background_color'] ?? '#f1f5f9');
$styleParts = ['--tl1menu-bg-color:' . $backgroundColor];
if (!empty($backgroundImageUrl)) {
    $styleParts[] = '--tl1menu-bg-image:url(' . $backgroundImageUrl . ')';
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

                    <div class="tl1menu__prices">
                        <div class="tl1menu__price"><span><?= e(__('plugins.tl-1menu.frontend.student')) ?></span><strong><?= e($service->formatPrice($item->getPrice('student'), $language)) ?></strong></div>
                        <div class="tl1menu__price"><span><?= e(__('plugins.tl-1menu.frontend.staff')) ?></span><strong><?= e($service->formatPrice($item->getPrice('staff'), $language)) ?></strong></div>
                        <div class="tl1menu__price"><span><?= e(__('plugins.tl-1menu.frontend.guest')) ?></span><strong><?= e($service->formatPrice($item->getPrice('guest'), $language)) ?></strong></div>
                    </div>

                    <?php if ($hasZeroPrice): ?>
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
