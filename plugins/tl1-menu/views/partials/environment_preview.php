<?php
$environmentPreviewMetrics = is_array($environmentPreviewMetrics ?? null) ? $environmentPreviewMetrics : [];
$environmentIconAssets = is_array($environmentIconAssets ?? null) ? $environmentIconAssets : [];
$environmentPreviewModes = is_array($environmentPreviewModes ?? null) ? array_values(array_intersect($environmentPreviewModes, ['card', 'list'])) : ['card', 'list'];
if ($environmentPreviewModes === []) {
    $environmentPreviewModes = ['card'];
}
$environmentPreviewLayout = (string)($environmentPreviewLayout ?? 'dynamic');
$environmentPreviewLayout = in_array($environmentPreviewLayout, ['dynamic', 'all'], true) ? $environmentPreviewLayout : 'dynamic';
$environmentPreviewShowModeLabels = !empty($environmentPreviewShowModeLabels);
$globalEnvironmentDisplayStyle = (string)($globalEnvironmentDisplayStyle ?? 'symbols');
if (!in_array($globalEnvironmentDisplayStyle, ['symbols', 'values'], true)) {
    $globalEnvironmentDisplayStyle = 'symbols';
}
?>
<div class="tl1menu-admin-env-preview full-width" data-tl1menu-env-preview data-tl1menu-env-preview-layout="<?= e($environmentPreviewLayout) ?>" data-global-environment-style="<?= e($globalEnvironmentDisplayStyle) ?>">
    <div class="tl1menu-field-label"><?= e(__('plugins.tl1-menu.config.environment_preview')) ?></div>
    <div class="tl1menu-admin-env-preview__surfaces">
        <?php foreach ($environmentPreviewModes as $previewMode): ?>
            <div class="tl1menu-admin-env-preview__mode" data-tl1menu-env-preview-mode="<?= e($previewMode) ?>">
                <?php if ($environmentPreviewShowModeLabels): ?>
                    <div class="tl1menu-admin-env-preview__mode-label"><?= e(__('plugins.tl1-menu.config.display_modes.' . $previewMode)) ?></div>
                <?php endif; ?>

                <div class="tl1menu <?= $previewMode === 'list' ? 'tl1menu-list ' : '' ?>tl1menu-admin-env-preview__surface" data-tl1menu-env-preview-surface="<?= e($previewMode) ?>">
                    <?php if ($previewMode === 'list'): ?>
                        <article class="tl1menu-list__item tl1menu-list__item--vegan tl1menu-admin-env-preview__list-item">
                            <div class="tl1menu-list__eco">
                                <?php foreach ($environmentPreviewMetrics as $previewMetric): ?>
                                    <?php foreach (['symbols', 'values'] as $previewStyle): ?>
                                        <div class="tl1menu-admin-env-preview__metric" data-tl1menu-env-preview-item data-tl1menu-env-setting="<?= e((string)($previewMetric['setting'] ?? '')) ?>" data-tl1menu-env-style="<?= e($previewStyle) ?>">
                                            <?php
                                            $environmentVariant = 'list';
                                            $environmentDisplayStyle = $previewStyle;
                                            $metricKey = (string)($previewMetric['key'] ?? '');
                                            $metricLabel = (string)($previewMetric['label'] ?? '');
                                            $metricValue = (string)($previewMetric['value'] ?? '');
                                            $metricRating = (string)($previewMetric['rating'] ?? '');
                                            require __DIR__ . '/environment_metric.php';
                                            ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                            <p class="tl1menu-admin-env-preview__empty" data-tl1menu-env-preview-empty><?= e(__('plugins.tl1-menu.config.environment_preview_empty')) ?></p>
                        </article>
                    <?php else: ?>
                        <article class="tl1menu__card tl1menu__card--vegan tl1menu-admin-env-preview__card">
                            <div class="tl1menu__eco">
                                <?php foreach ($environmentPreviewMetrics as $previewMetric): ?>
                                    <?php foreach (['symbols', 'values'] as $previewStyle): ?>
                                        <div class="tl1menu-admin-env-preview__metric" data-tl1menu-env-preview-item data-tl1menu-env-setting="<?= e((string)($previewMetric['setting'] ?? '')) ?>" data-tl1menu-env-style="<?= e($previewStyle) ?>">
                                            <?php
                                            $environmentVariant = 'card';
                                            $environmentDisplayStyle = $previewStyle;
                                            $metricKey = (string)($previewMetric['key'] ?? '');
                                            $metricLabel = (string)($previewMetric['label'] ?? '');
                                            $metricValue = (string)($previewMetric['value'] ?? '');
                                            $metricRating = (string)($previewMetric['rating'] ?? '');
                                            require __DIR__ . '/environment_metric.php';
                                            ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                            <p class="tl1menu-admin-env-preview__empty" data-tl1menu-env-preview-empty><?= e(__('plugins.tl1-menu.config.environment_preview_empty')) ?></p>
                        </article>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
